<?php
// panier.php
session_start();
require_once 'db/config.php';
require_once __DIR__ . '/csrf_helper.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
$user_id = (int)$_SESSION['user_id'];
$message = '';
$error = '';

// Initialisation des variables
$coupon_applied = null;
$discount_amount = 0;
$eligible_total = 0;

// Ajouter / mise à jour du panier
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['plat_id']) && !isset($_POST['supprimer_panier_id'])) {
  if (empty($_POST['csrf_token']) || !fh_verify_csrf($_POST['csrf_token'])) { header("Location: panier.php"); exit; }
    $plat_id = (int)$_POST['plat_id'];
    $quantite = max(1, (int)$_POST['quantite']);
    $stmt = $conn->prepare("SELECT panier_id, quantite FROM panier WHERE user_id=? AND plat_id=?");
    $stmt->execute([$user_id, $plat_id]);
    $row = $stmt->fetch();
    if ($row) {
        $new_qte = $row['quantite'] + $quantite;
        $stmt = $conn->prepare("UPDATE panier SET quantite=? WHERE panier_id=?");
        $stmt->execute([$new_qte, $row['panier_id']]);
    } else {
        $stmt = $conn->prepare("INSERT INTO panier (user_id, plat_id, quantite) VALUES (?, ?, ?)");
        $stmt->execute([$user_id, $plat_id, $quantite]);
    }
    header("Location: panier.php");
    exit;
}

// Supprimer un article
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['supprimer_panier_id'])) {
  if (empty($_POST['csrf_token']) || !fh_verify_csrf($_POST['csrf_token'])) { header("Location: panier.php"); exit; }
  $panier_id = (int)$_POST['supprimer_panier_id'];
  $stmt = $conn->prepare("DELETE FROM panier WHERE panier_id = ? AND user_id = ?");
  $stmt->execute([$panier_id, $user_id]);
  header("Location: panier.php");
  exit;
}

// Vider le panier
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['vider_panier'])) {
  if (empty($_POST['csrf_token']) || !fh_verify_csrf($_POST['csrf_token'])) { header("Location: panier.php"); exit; }
  $stmt = $conn->prepare("DELETE FROM panier WHERE user_id = ?");
  $stmt->execute([$user_id]);
  $message = "🗑️ Panier vidé avec succès !";
  header("Location: panier.php");
  exit;
}

// Récupérer items AVANT de traiter le coupon
$stmt = $conn->prepare("
  SELECT p.panier_id, pl.plat_id, pl.nom_plat AS plat_nom, pl.prix, p.quantite, r.nom_restaurant AS resto_nom, r.restaurant_id
  FROM panier p
  JOIN plats pl ON p.plat_id = pl.plat_id
  JOIN restaurants r ON pl.restaurant_id = r.restaurant_id
  WHERE p.user_id = ?
");
$stmt->execute([$user_id]);
$items = $stmt->fetchAll();

// Appliquer un coupon
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply_coupon'])) {
  if (empty($_POST['csrf_token']) || !fh_verify_csrf($_POST['csrf_token'])) { header("Location: panier.php"); exit; }
    $code = trim($_POST['coupon_code']);
    
    if (empty($code)) {
        // Ne pas définir d'erreur PHP, elle sera gérée en JS
    } else {
        // Vérifier le coupon
        $stmt = $conn->prepare("SELECT * FROM coupons WHERE code_reduction = ? AND actif = 1");
        $stmt->execute([$code]);
        $coupon = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$coupon) {
            $error = "❌ Ce code de réduction n'existe pas.";
        } elseif ($coupon['date_debut'] > date('Y-m-d H:i:s') || $coupon['date_fin'] < date('Y-m-d H:i:s')) {
            $error = "⏰ Ce code de réduction n'est pas valide à cette période.";
        } elseif ($coupon['utilisation_max'] && $coupon['utilisations'] >= $coupon['utilisation_max']) {
            $error = "🚫 Ce code a atteint sa limite d'utilisation.";
        } else {
            // Stocker le coupon en session
            $_SESSION['coupon'] = $coupon;
            $coupon_applied = $coupon;
            $message = "✅ Code de réduction appliqué !";
        }
    }
}

// Retirer le coupon
if (isset($_POST['remove_coupon'])) {
  if (empty($_POST['csrf_token']) || !fh_verify_csrf($_POST['csrf_token'])) { header("Location: panier.php"); exit; }
  unset($_SESSION['coupon']);
  header("Location: panier.php");
  exit;
}

// Récupérer le coupon de la session
if (isset($_SESSION['coupon'])) {
    $coupon_applied = $_SESSION['coupon'];
}

// Total et calcul de réduction
$total = 0; 

foreach ($items as $it) {
    $item_total = $it['prix'] * $it['quantite'];
    $total += $item_total;
    
    // Si un coupon est appliqué et limité à un restaurant
    if ($coupon_applied && $coupon_applied['restaurant_id']) {
        if ($it['restaurant_id'] == $coupon_applied['restaurant_id']) {
            $eligible_total += $item_total;
        }
    }
}

// Calculer la réduction
if ($coupon_applied) {
    // Vérifier si le coupon est limité à un restaurant
    if ($coupon_applied['restaurant_id']) {
        if ($eligible_total == 0) {
            $message = '';
            $error = "⚠️ Ce coupon n'est valable que pour un restaurant spécifique qui n'est pas dans votre panier.";
            unset($_SESSION['coupon']);
            $coupon_applied = null;
            $discount_amount = 0;
        } else {
            // Appliquer la réduction uniquement sur les articles éligibles
            if ($coupon_applied['type'] === 'pourcentage') {
                $discount_amount = ($eligible_total * $coupon_applied['valeur']) / 100;
            } else {
                $discount_amount = min($coupon_applied['valeur'], $eligible_total);
            }
        }
    } else {
        // Coupon valable sur tout le panier
        if ($coupon_applied['type'] === 'pourcentage') {
            $discount_amount = ($total * $coupon_applied['valeur']) / 100;
        } else {
            $discount_amount = min($coupon_applied['valeur'], $total);
        }
    }
}

$final_total = max(0, $total - $discount_amount);

// Adresse + solde utilisateur pour préremplir checkout
$stmt = $conn->prepare("SELECT adresse_livraison, solde FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();
$adresse_pref = $user['adresse_livraison'] ?? '';
$solde_utilisateur = (float)($user['solde'] ?? 0);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <link rel="icon" type="image/x-icon" href="FoodHubLogo.ico">
  <meta charset="utf-8">
  <title>Panier</title>
  <link rel="stylesheet" href="assets/style.css">
  <?php include 'sidebar.php'; ?>
</head>
<body>
  <audio id="player" autoplay loop> <source src="https://raw.githubusercontent.com/HDSachaNewLive/foodhub-assets/main/July 2014 Nintendo eShop Music.mp3" type="audio/mpeg"> </audio>
  <?php include "slider_son.php"; ?>

  <main class="container">
    <h2>🛒 Mon panier</h2>
    
    <?php if ($message): ?>
      <div class="success"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    
    <?php if ($error): ?>
      <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    
    <?php if (empty($items)): ?>
      <p>Ton panier est vide.</p>
      <div style="margin-top: 2rem; display: flex; gap: 1.5rem; flex-wrap: wrap; justify-content: center; align-items: center;">
        <a href="restaurants.php" class="btn btn-glass">🍔 Explorer les restaurants</a>
      </div>
    <?php else: ?>
      <table>
        <tr><th>Plat</th><th>Restaurant</th><th>Quantité</th><th>Prix</th><th>Sous-total</th><th></th></tr>
        <?php foreach ($items as $it): ?>
          <tr>
            <td><?= htmlspecialchars($it['plat_nom']) ?></td>
            <td><?= htmlspecialchars($it['resto_nom']) ?></td>
            <td>
              <input type="number" min="1" value="<?= (int)$it['quantite'] ?>" 
              data-panier-id="<?= (int)$it['panier_id'] ?>" class="qty-input">
            </td>
            <td><?= number_format($it['prix'],2) ?> €</td>
            <td><?= number_format($it['prix'] * $it['quantite'],2) ?> €</td>
            <td>
              <form method="post" style="display:inline;">
                <input type="hidden" name="supprimer_panier_id" value="<?= (int)$it['panier_id'] ?>">
                <?= fh_csrf_field() ?>
                <button class="btn-del" type="submit">Supprimer</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </table>
      <!-- bouton vider la panier -->
      <div style="margin-top: 1rem; text-align: left;">
        <form method="post" onsubmit="return confirm('⚠️ Êtes-vous sûr de vouloir vider votre panier ? Cette action est irréversible.');">
        <?= fh_csrf_field() ?>
        <button type="submit" name="vider_panier" class="btn-clear-cart">🗑️ Vider le panier</button>
        </form>
      </div>

      <!-- Section Coupon -->
      <div class="coupon-section">
        <h3>Code de réduction</h3>
        <?php if ($coupon_applied): ?>
          <div class="coupon-applied">
            <span class="coupon-code">🎉 <?= htmlspecialchars($coupon_applied['code_reduction']) ?> appliqué</span>
            <span class="coupon-discount">
              -<?= $coupon_applied['type'] === 'pourcentage' ? $coupon_applied['valeur'] . '%' : number_format($coupon_applied['valeur'], 2) . ' €' ?>
            </span>
            <form method="post" style="display:inline;">
              <?= fh_csrf_field() ?>
              <button type="submit" name="remove_coupon" class="btn-remove-coupon">❌ Retirer</button>
            </form>
          </div>
          <?php if ($coupon_applied['restaurant_id'] && $eligible_total > 0): ?>
            <?php 
              // Récupérer le nom du restaurant concerné
              $stmt_resto = $conn->prepare("SELECT nom_restaurant FROM restaurants WHERE restaurant_id = ?");
              $stmt_resto->execute([$coupon_applied['restaurant_id']]);
              $resto_name = $stmt_resto->fetchColumn();
            ?>
            <p class="coupon-info">📌 Ce coupon s'applique uniquement aux articles de <strong><?= htmlspecialchars($resto_name) ?></strong> (<?= number_format($eligible_total, 2) ?> €)</p>
          <?php endif; ?>
        <?php else: ?>
          <form method="post" class="coupon-form" id="coupon-form">
            <?= fh_csrf_field() ?>
            <input type="text" name="coupon_code" placeholder="Entrez votre code" class="coupon-input" id="coupon-input">
            <button type="submit" name="apply_coupon" class="btn-apply-coupon">Appliquer</button>
          </form>
        <?php endif; ?>
      </div>
      
      <!-- Résumé -->
      <div class="cart-summary">
        <div class="summary-line">
          <span>Sous-total :</span>
          <span id="summary-sous-total"><?= number_format($total,2) ?> €</span>
        </div>
        <div class="summary-line discount" id="summary-reduction-line" <?= $discount_amount > 0 ? '' : 'style="display:none"' ?>>
          <span>Réduction :</span>
          <span id="summary-reduction">-<?= number_format($discount_amount,2) ?> €</span>
        </div>
        <div class="summary-line total">
          <span>Total :</span>
          <span id="summary-total"><?= number_format($final_total,2) ?> €</span>
        </div>
      </div>

      <?php if ($solde_utilisateur > 0): ?>
      <div class="solde-checkout-box">
        <span>💰 Tu as <strong><?= number_format($solde_utilisateur, 2) ?> €</strong> de solde FoodHub. Tu pourras choisir combien en utiliser à l'étape suivante.</span>
      </div>
      <?php endif; ?>

      <h4>Adresse de livraison</h4>
      <form method="post" action="checkout.php" id="checkout-form">
        <input type="hidden" name="from_cart" value="1">
        <?= fh_csrf_field() ?>
        <input type="text" name="adresse_livraison" value="<?= htmlspecialchars($adresse_pref) ?>" placeholder="Adresse de livraison (modifiable)" data-address-autocomplete>
        
        <br> <!-- Saut de ligne -->
        
        <label>Mode de paiement :</label>
        <select name="mode_paiement">
          <option value="carte">Carte (simulée)</option>
          <option value="livraison">Paiement à la livraison</option>
        </select><br><br>
        <button class="btn-pay" type="submit" id="btn-valider-commande">Procéder au paiement (simulé)</button>
      </form>
    <?php endif; ?>
    <p><a href="restaurants.php">⬅ Continuer à commander</a></p>
    <p><a href="home.php">🏠 Retour à l'accueil</a></p>
    <p><a href="<?= isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'restaurants.php' ?>">⬅ Retour</a></p>
  </main>


<style>
.container {
  backdrop-filter: blur(12px);
  background: rgba(255, 255, 255, 0.29); 
}

.container form input {
  width: 95%;
  margin: 10px 0;
  padding: 12px;
  border-radius: 15px;
  border: none;
  background: rgba(255, 255, 255, 0.25);
  color: #000000;
  font-size: 1rem;
  outline: none;
  transition: background 0.3s ease, transform 0.2s;
  font-family: 'HSR';
}
.container form input:focus {
  background: rgba(255, 255, 255, 0.35);
  transform: scale(1.02);
}
.tr .btn-del {
  margin-top: -0px;
  backdrop-filter: blur(20px);
  background: rgba(231, 48, 48, 0.15);
  transition: all ease 0.4s;
}
.tr .btn-del:hover{
  box-shadow: 0 6px 20px rgba(175, 96, 76, 0.4);
  background: rgba(231, 76, 60, 0.85);
  transition: all ease 0.4s;
}

.btn-pay {
  display: inline-flex;
  justify-content: center;
  align-items: center;
  padding: 1rem 1.7rem;
  font-size: 1rem;
  font-weight: 600;
  color: #fff;
  background: linear-gradient(135deg, #ff6b6b, #ffc342ff);
  border: none;
  border-radius: 14px;
  cursor: pointer;
  text-decoration: none;
  box-shadow: 0 6px 18px rgba(0,0,0,0.18);
  transition: all 0.35s ease;
  overflow: hidden;
  text-align: center;
  margin-left: ;
}

.btn-pay::after {
  content: "";
  position: absolute;
  top: 0; left: -100%;
  width: 100%;
  height: 100%;
  background: rgba(255,255,255,0.25);
  transition: all 0.4s ease;
  border-radius: 14px;
}

.btn-pay:hover::after { left: 0; }
.btn-pay:hover { 
  transform: translateY(-4px) scale(1.03); 
  box-shadow: 0 12px 25px rgba(0,0,0,0.25); 
  background: linear-gradient(135deg, #ff8c42, #ff6b6b);
}

h4 {
  margin-bottom: 10px;
}

.btn-glass {
  display: block;      
  margin: 0 auto;     
  text-align: center;  
  font-family: 'HSR', sans-serif;
  font-size: 1.3rem;
  padding: 15px 23px;
  backdrop-filter: blur(15px);
  background: rgba(231, 173, 131, 0.44);
  color: white;
  border: none;
  border-radius: 12px;
  box-shadow: 0 8px 30px rgba(0,0,0,0.25);
  cursor: pointer;
  transition: all 0.3s ease, transform 0.2s;
  text-decoration: none;
  display: block;
  max-width: fit-content; 
}

.btn-glass:hover {
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 12px 40px rgba(0,0,0,0.35);
    background: rgba(249, 158, 72, 0.55);
}

.success {
  background: rgba(0, 255, 127, 0.25);
  padding: 10px 15px;
  border-radius: 10px;
  margin-bottom: 15px;
  border-left: 4px solid #00ff7f;
  color: #006837;
  font-weight: 600;
}

.error {
  background: rgba(255, 77, 77, 0.25);
  padding: 10px 15px;
  border-radius: 10px;
  margin-bottom: 15px;
  border-left: 4px solid #ff4d4d;
  color: #8b0000;
  font-weight: 600;
}

/* Section Coupon */
.coupon-section {
  background: rgba(255, 255, 255, 0.18);
  backdrop-filter: blur(14px);
  padding: 1.5rem;
  border-radius: 1.3rem;
  margin: 2rem 0;
  box-shadow: 0 6px 15px rgba(0,0,0,0.12);
}

.coupon-section h3 {
  margin-top: 0;
  color: #ff6b6b;
  margin-bottom: 1rem;
}

.coupon-form {
  display: flex;
  gap: 1rem;
  align-items: center;
}

.coupon-input {
  flex: 1;
  padding: 12px;
  border-radius: 10px;
  border: 1px solid rgba(255, 255, 255, 0.3);
  background: rgba(255, 255, 255, 0.25);
  color: #000;
  font-size: 1rem;
  font-family: 'HSR';
}

.btn-apply-coupon {
  padding: 12px 24px;
  background: linear-gradient(135deg, #4CAF50, #45a049);
  color: white;
  border: none;
  border-radius: 10px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  font-family: 'HSR';
}

.btn-apply-coupon:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(76, 175, 80, 0.4);
  background: linear-gradient(135deg, #4CAF50, #45a049);
  transition: all 0.3s ease;
}

.coupon-applied {
  display: flex;
  align-items: center;
  gap: 1rem;
  background: rgba(76, 175, 80, 0.15);
  padding: 1rem;
  border-radius: 10px;
  border-left: 4px solid #4CAF50;
}

.coupon-code {
  flex: 1;
  font-weight: 600;
  color: #2e7d32;
}

.coupon-discount {
  font-weight: 700;
  color: #1b5e20;
  font-size: 1.1rem;
}

.btn-remove-coupon {
  padding: 8px 16px;
  background: rgba(255, 77, 77, 0.8);
  color: white;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.3s ease;
  font-family: 'HSR';
}

.btn-remove-coupon:hover {
  background: #ff4d4d;
  transform: scale(1.05);
}

.coupon-info {
  margin-top: 1rem;
  padding: 0.8rem;
  background: rgba(255, 193, 7, 0.15);
  border-left: 4px solid #FFC107;
  border-radius: 8px;
  color: #856404;
  font-size: 0.95rem;
}

/* Résumé du panier */
.cart-summary {
  background: rgba(255, 255, 255, 0.18);
  backdrop-filter: blur(14px);
  padding: 1.5rem;
  border-radius: 1.3rem;
  margin: 1.5rem 0;
  box-shadow: 0 6px 15px rgba(0,0,0,0.12);
}

.summary-line {
  display: flex;
  justify-content: space-between;
  padding: 0.5rem 0;
  font-size: 1.1rem;
}

.summary-line.discount {
  color: #4CAF50;
  font-weight: 600;
}

.summary-line.total {
  border-top: 2px solid rgba(255, 107, 107, 0.3);
  margin-top: 0.5rem;
  padding-top: 1rem;
  font-size: 1.3rem;
  font-weight: 700;
  color: #ff6b6b;
}

/* message flash */
.flash-message {
  position: fixed;
  top: 20px;
  left: 50%;
  transform: translateX(-50%);
  background: rgba(255, 255, 255, 0.2);
  backdrop-filter: blur(10px);
  color: #727272ff;
  padding: 12px 20px;
  border-radius: 12px;
  font-family: 'HSR', sans-serif;
  box-shadow: 0 4px 20px rgba(0,0,0,0.3);
  animation: fadeIn 0.3s ease;
  z-index: 2000;
}
.flash-message.success { border: 1px solid #7fff7f; }
.flash-message.error { border: 1px solid #ff6b6b; }
.flash-message.hide { opacity: 0; transform: translate(-50%, -10px); transition: all 0.4s; }

@keyframes fadeIn {
  from { opacity: 0; transform: translate(-50%, -10px); }
  to { opacity: 1; transform: translate(-50%, 0); }
}
/* Box info solde */
.solde-checkout-box {
  background: rgba(76, 175, 80, 0.15);
  border-left: 4px solid #4CAF50;
  border-radius: 0.8rem;
  padding: 1rem 1.2rem;
  margin: 1.2rem 0;
  color: #2e7d32;
  font-weight: 600;
  font-size: 0.95rem;
}

/* btn vider panier */
.btn-clear-cart {
  padding: 0.7rem 1.2rem;
  font-size: 0.9rem;
  font-weight: 600;
  background: #fd5252;
  color: white;
  justify-content: left;
  border: none;
  border-radius: 10px;
  margin-bottom: -10px;
  cursor: pointer;
  transition: all 0.3s ease;
  font-family: 'HSR';
  box-shadow: 0 4px 12px rgba(255, 77, 77, 0.3);
}

.btn-clear-cart:hover {
  background: #e03e3e;
  transform: translateY(-2px);
  box-shadow: 0 6px 18px rgba(255, 77, 77, 0.5);
}
</style>

<script>
let recalculEnCours = null;
let validationEnCours = false;
let soumissionDirecte = false;

async function updateQuantite(panierId, quantite) {
  const csrfEl = document.querySelector('input[name="csrf_token"]');
  const csrfVal = csrfEl ? encodeURIComponent(csrfEl.value) : '';
  const bodyStr = `panier_id=${encodeURIComponent(panierId)}&quantite=${encodeURIComponent(quantite)}` + (csrfVal ? `&csrf_token=${csrfVal}` : '');
  const response = await fetch('update_panier.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: bodyStr
  });
  if (!response.ok) throw new Error('Erreur mise à jour panier');
}

function appliquerRecalcul(data) {
  if (data.error) return data;

  data.lignes.forEach(ligne => {
    const input = document.querySelector(`.qty-input[data-panier-id="${ligne.panier_id}"]`);
    if (input) {
      const tr = input.closest('tr');
      if (tr) {
        const sousTotalCell = tr.querySelectorAll('td')[4];
        if (sousTotalCell) {
          sousTotalCell.textContent = parseFloat(ligne.sous_total).toLocaleString('fr-FR', {minimumFractionDigits: 2}) + ' €';
        }
      }
    }
  });

  const elSousTotal = document.getElementById('summary-sous-total');
  if (elSousTotal) {
    elSousTotal.textContent = parseFloat(data.sous_total).toLocaleString('fr-FR', {minimumFractionDigits: 2}) + ' €';
  }

  const elReductionLine = document.getElementById('summary-reduction-line');
  const elReduction = document.getElementById('summary-reduction');
  if (elReductionLine && elReduction) {
    if (data.has_reduction) {
      elReductionLine.style.display = '';
      elReduction.textContent = '-' + parseFloat(data.reduction).toLocaleString('fr-FR', {minimumFractionDigits: 2}) + ' €';
    } else {
      elReductionLine.style.display = 'none';
    }
  }

  const elTotal = document.getElementById('summary-total');
  if (elTotal) {
    elTotal.textContent = parseFloat(data.total).toLocaleString('fr-FR', {minimumFractionDigits: 2}) + ' €';
  }

  if (data.coupon_error) {
    showMessage(data.coupon_error, 'error');
    const couponApplied = document.querySelector('.coupon-applied');
    if (couponApplied) couponApplied.style.display = 'none';
  }

  return data;
}

async function recalculerPanier() {
  const promise = (async () => {
    const res = await fetch('recalcul_panier.php');
    const data = await res.json();
    return appliquerRecalcul(data);
  })();

  recalculEnCours = promise;
  try {
    return await promise;
  } catch (err) {
    console.error('Erreur recalcul panier:', err);
    throw err;
  } finally {
    if (recalculEnCours === promise) recalculEnCours = null;
  }
}

async function synchroniserQuantites() {
  const updates = [];
  document.querySelectorAll('.qty-input').forEach(input => {
    let qty = parseInt(input.value, 10);
    if (isNaN(qty) || qty < 1) {
      qty = 1;
      input.value = 1;
    }
    updates.push(updateQuantite(input.dataset.panierId, qty));
  });
  await Promise.all(updates);
}

async function preparerValidationCommande() {
  await synchroniserQuantites();
  if (recalculEnCours) await recalculEnCours;
  return recalculerPanier();
}

document.querySelectorAll('.qty-input').forEach(input => {
  input.addEventListener('change', async (e) => {
    const newQty = parseInt(e.target.value, 10);
    if (isNaN(newQty) || newQty < 1) {
      e.target.value = 1;
      return;
    }

    const panierId = e.target.dataset.panierId;
    e.target.style.opacity = '0.5';

    try {
      await updateQuantite(panierId, newQty);
      await recalculerPanier();
      e.target.style.opacity = '1';
    } catch (err) {
      showMessage('Erreur lors de la mise à jour', 'error');
      e.target.style.opacity = '1';
    }
  });
});

const checkoutForm = document.getElementById('checkout-form');
if (checkoutForm) {
  checkoutForm.addEventListener('submit', async (e) => {
    if (soumissionDirecte) return;

    e.preventDefault();
    if (validationEnCours) return;

    const btn = document.getElementById('btn-valider-commande');
    const originalText = btn ? btn.textContent : '';
    validationEnCours = true;
    if (btn) {
      btn.disabled = true;
      btn.textContent = 'Calcul en cours...';
    }

    try {
      await preparerValidationCommande();
      soumissionDirecte = true;
      checkoutForm.submit();
    } catch (err) {
      showMessage('Erreur lors du calcul. Veuillez réessayer.', 'error');
      validationEnCours = false;
      if (btn) {
        btn.disabled = false;
        btn.textContent = originalText;
      }
    }
  });
}

// Validation du formulaire de coupon
document.addEventListener("DOMContentLoaded", () => {
  const couponForm = document.getElementById("coupon-form");
  
  if (couponForm) {
    couponForm.addEventListener("submit", (e) => {
      const couponInput = document.getElementById("coupon-input");
      const code = couponInput.value.trim();
      
      if (code === "") {
        e.preventDefault();
        showMessage("⚠️ Veuillez entrer un code de réduction.", "error");
      }
    });
  }
});

// Fonction pour afficher un message flash
function showMessage(text, type = "success") {
  // Supprime l'ancien message si y'en a un
  const oldMsg = document.querySelector(".flash-message");
  if (oldMsg) oldMsg.remove();

  // Crée un nouveau
  const msg = document.createElement("div");
  msg.className = `flash-message ${type}`;
  msg.textContent = text;
  document.body.appendChild(msg);

  // Disparaît après 3 secs
  setTimeout(() => {
    msg.classList.add("hide");
    setTimeout(() => msg.remove(), 400);
  }, 3000);
}
</script>
<script src="https://cdn.jsdelivr.net/npm/three@0.149.0/build/three.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/vanta/dist/vanta.waves.min.js"></script>

<script>
//fixer hauteur du body à la hauteur de la fenêtre
document.addEventListener('DOMContentLoaded', () => {
  //créer conteneur fixe pour Vanta en arrière-plan
  const vantaBg = document.createElement('div');
  vantaBg.id = 'vanta-bg';
  vantaBg.style.cssText = `
    position: fixed;
    top: 0;
    left: 0;
    width: 110vw;
    height: 150vh;
    z-index: 2;
    pointer-events: none;
  `;
  document.body.insertBefore(vantaBg, document.body.firstChild);

  // vanta js
  window.vantaEffect = VANTA.WAVES({
    el: "#vanta-bg",
    mouseControls: true,
    touchControls: true,
    gyroControls: false,
    minHeight: 885.00,
    minWidth: 200.00,
    scale: 1.00,
    scaleMobile: 1.00,
    color: 0xf6b26b,
    shininess: 60,
    waveHeight: 22,
    waveSpeed: 0.7,
    zoom: 1.1
  });
});
</script>
<style>
body {
  background: none !important;
  overflow-x: clip;
}

canvas.vanta-canvas {
  position: absolute !important;
  top: 0;
  left: 0;
  width: fit-content;
  height: fit-content;
  z-index: 1 !important;
}
</style>
<script src="address-autocomplete.js"></script>
</body>
</html>