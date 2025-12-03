<?php
// panier.php
session_start();
require_once 'db/config.php';

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

// Ajouter / mise à jour (déjà présent) : laisser comme ça
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['plat_id']) && !isset($_POST['supprimer_panier_id'])) {
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

// Suppression
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['supprimer_panier_id'])) {
    $panier_id = (int)$_POST['supprimer_panier_id'];
    $stmt = $conn->prepare("DELETE FROM panier WHERE panier_id = ? AND user_id = ?");
    $stmt->execute([$panier_id, $user_id]);
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

// Adresse utilisateur pour préremplir checkout
$stmt = $conn->prepare("SELECT adresse_livraison FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();
$adresse_pref = $user['adresse_livraison'] ?? '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <title>Panier</title>
  <link rel="stylesheet" href="assets/style.css">
  <?php include 'sidebar.php'; ?>
</head>
<body>
  <audio id="player" autoplay loop> <source src="assets\July 2014 Nintendo eShop Music.mp3" type="audio/mpeg"> </audio>
  <?php include "slider_son.php"; ?>
  <style>#volume-widget {
  right: -100px;
  }
  </style>

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
                <button class="btn-del" type="submit">Supprimer</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </table>
      
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
            <input type="text" name="coupon_code" placeholder="Entrez votre code" class="coupon-input" id="coupon-input">
            <button type="submit" name="apply_coupon" class="btn-apply-coupon">Appliquer</button>
          </form>
        <?php endif; ?>
      </div>
      
      <!-- Résumé -->
      <div class="cart-summary">
        <div class="summary-line">
          <span>Sous-total :</span>
          <span><?= number_format($total,2) ?> €</span>
        </div>
        <?php if ($discount_amount > 0): ?>
          <div class="summary-line discount">
            <span>Réduction :</span>
            <span>-<?= number_format($discount_amount,2) ?> €</span>
          </div>
        <?php endif; ?>
        <div class="summary-line total">
          <span>Total :</span>
          <span><?= number_format($final_total,2) ?> €</span>
        </div>
      </div>

      <h4>Adresse de livraison</h4>
      <form method="post" action="checkout.php">
        <input type="hidden" name="from_cart" value="1">
        <input type="text" name="adresse_livraison" value="<?= htmlspecialchars($adresse_pref) ?>" placeholder="Adresse de livraison (modifiable)"><br>
        <label>Mode de paiement :</label>
        <select name="mode_paiement">
          <option value="carte">Carte (simulée)</option>
          <option value="livraison">Paiement à la livraison</option>
        </select><br><br>
        <button class="btn-pay" type="submit">Procéder au paiement (simulé)</button>
      </form>
    <?php endif; ?>
    <p><a href="restaurants.php">⬅ Continuer à commander</a></p>
    <p><a href="home.php">🏠 Retour à l'accueil</a></p>
  </main>

<script src="https://cdn.jsdelivr.net/npm/three@0.149.0/build/three.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/vanta/dist/vanta.waves.min.js"></script>

<script>
VANTA.WAVES({
  el: "body",
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
})
</script>

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

/* Message flash style menu.php */
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
</style>

<script>
document.querySelectorAll('.qty-input').forEach(input => {
  input.addEventListener('change', async (e) => {
    const newQty = parseInt(e.target.value);
    if (isNaN(newQty) || newQty < 1) {
      e.target.value = 1;
      return;
    }

    const panierId = e.target.dataset.panierId;

    try {
      const response = await fetch('update_panier.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `panier_id=${panierId}&quantite=${newQty}`
      });
      if (response.ok) {
        location.reload();
      } else {
        alert('Erreur lors de la mise à jour');
      }
    } catch(err) {
      alert('Erreur de connexion');
    }
  });
});

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

</body>
</html>