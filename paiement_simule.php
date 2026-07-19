<?php
// paiement_simule.php
session_start();
require_once 'db/config.php';
require_once __DIR__ . '/csrf_helper.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
$uid = (int)$_SESSION['user_id'];
$commande_id = (int)($_GET['commande_id'] ?? 0);

if (!$commande_id) header('Location: home.php');

$stmt = $conn->prepare("SELECT c.*, cp.code_reduction, cp.restaurant_id as coupon_restaurant_id FROM commandes c LEFT JOIN coupons cp ON c.coupon_id = cp.coupon_id WHERE c.commande_id = ? AND c.user_id = ?");
$stmt->execute([$commande_id, $uid]);
$commande = $stmt->fetch();
if (!$commande) abort_404('commande');

// Si la commande a déjà été traitée (paiement déjà confirmé), inutile de repasser par ici
if ($commande['statut'] !== 'en_attente') {
    header("Location: suivi_commande.php?commande_id=".$commande_id);
    exit;
}

// Solde disponible pour cette commande
$stmt = $conn->prepare("SELECT solde FROM users WHERE user_id = ?");
$stmt->execute([$uid]);
$solde_actuel = (float)($stmt->fetchColumn() ?: 0);
$solde_max_utilisable = min($solde_actuel, (float)$commande['montant_total']);

// Items avec restaurant_id
$stmt = $conn->prepare("
    SELECT cp.*, pl.nom_plat, pl.restaurant_id, r.nom_restaurant
    FROM commande_plats cp 
    JOIN plats pl ON cp.plat_id = pl.plat_id
    JOIN restaurants r ON pl.restaurant_id = r.restaurant_id
    WHERE cp.commande_id = ?
");
$stmt->execute([$commande_id]);
$items = $stmt->fetchAll();

// Calculer sous-total et eligible_total (total eligible au calcul du sous-total)
$sous_total = 0;
$eligible_total = 0;
foreach ($items as $it) {
    $item_total = $it['prix_unitaire'] * $it['quantite'];
    $sous_total += $item_total;
    
    // Si coupon limité à un restaurant, calculer eligible_total (total re-calculé pour coupon qui n'est valable que dans un rsetaurant, à condition qui ce dernier soit dans le panier))
    if ($commande['coupon_restaurant_id'] && $it['restaurant_id'] == $commande['coupon_restaurant_id']) {
        $eligible_total += $item_total;
    }
}

$erreur_solde = '';

// Confirmer le paiement
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (empty($_POST['csrf_token']) || !fh_verify_csrf($_POST['csrf_token'])) { header('Location: paiement_simule.php?commande_id=' . $commande_id); exit; }

  $montant_solde_choisi = (float)($_POST['montant_solde'] ?? 0);
  // Clamp défensif : jamais plus que le solde dispo, ni plus que le total de la commande
  if ($montant_solde_choisi < 0) $montant_solde_choisi = 0;
  if ($montant_solde_choisi > $solde_max_utilisable) $montant_solde_choisi = $solde_max_utilisable;
  $montant_solde_choisi = round($montant_solde_choisi, 2);

  $conn->beginTransaction();
  try {
      if ($montant_solde_choisi > 0) {
          $deduc = $conn->prepare("UPDATE users SET solde = solde - ? WHERE user_id = ? AND solde >= ?");
          $deduc->execute([$montant_solde_choisi, $uid, $montant_solde_choisi]);
          if ($deduc->rowCount() === 0) {
              throw new Exception('Solde insuffisant au moment de la confirmation.');
          }
      }

      // WHERE statut='en_attente' : évite un double traitement en cas de double-soumission
      $upd = $conn->prepare("UPDATE commandes SET montant_solde_utilise = ?, statut = 'en_preparation', date_paiement = NOW() WHERE commande_id = ? AND user_id = ? AND statut = 'en_attente'");
      $upd->execute([$montant_solde_choisi, $commande_id, $uid]);

      if ($upd->rowCount() === 0) {
          throw new Exception('Cette commande a déjà été traitée.');
      }

      $conn->commit();
      header("Location: suivi_commande.php?commande_id=".$commande_id);
      exit;

  } catch (Exception $e) {
      $conn->rollBack();
      error_log('[paiement_simule] Erreur: ' . $e->getMessage());
      $erreur_solde = "⚠️ Une erreur est survenue (" . htmlspecialchars($e->getMessage()) . "). Réessaie.";
  }
}
?>
<!doctype html>
<html lang="fr">
<head>
  <link rel="icon" type="image/x-icon" href="FoodHubLogo.ico">
  <audio autoplay> <source src="https://raw.githubusercontent.com/HDSachaNewLive/foodhub-assets/main/done-download.wav" type="audio/mpeg"> </audio>
  <meta charset="utf-8">
  <title>Paiement simulé</title>
  <link rel="stylesheet" href="assets/style.css">
  <style>
    body {
      margin: 0;
      font-family: 'HSR', sans-serif;
      color: #fff;
      overflow-x: hidden;
    }

    .container {
      max-width: 700px;
      margin: 120px auto;
      padding: 40px 50px;
      border-radius: 24px;
      backdrop-filter: blur(18px);
      background: rgba(255, 255, 255, 0.08);
      box-shadow: 0 8px 30px rgba(0, 0, 0, 0.45);
      border: 1px solid rgba(255, 255, 255, 0.25);
      text-align: center;
      animation: fadeIn 0.8s ease;
    }

    h2 {
      color: var(--accent);
      margin-bottom: 15px;
      font-size: 1.8rem;
      letter-spacing: 1px;
    }

    p {
      color: rgba(53, 53, 53, 0.85);
      font-size: 1.1rem;
      margin-bottom: 10px;
    }

    ul {
      list-style: none;
      padding: 0;
      margin: 20px 0 30px;
    }

    ul li {
      background: rgba(255, 255, 255, 0.12);
      border: 1px solid rgba(255, 255, 255, 0.25);
      border-radius: 12px;
      padding: 12px 18px;
      margin-bottom: 10px;
      text-align: left;
      display: flex;
      justify-content: space-between;
      align-items: center;
      transition: all 0.3s ease;
      color: rgba(53, 53, 53, 0.85);
    }

    ul li:hover {
      background: rgba(255, 255, 255, 0.22);
      transform: translateY(-2px);
    }
    
    .payment-summary {
      background: rgba(255, 255, 255, 0.15);
      padding: 1.5rem;
      border-radius: 15px;
      margin: 1.5rem 0;
      text-align: left;
    }
    
    .summary-line {
      display: flex;
      justify-content: space-between;
      padding: 0.5rem 0;
      font-size: 1.1rem;
      color: rgba(53, 53, 53, 0.85);
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
    
    .coupon-badge {
      display: inline-block;
      background: rgba(76, 175, 80, 0.2);
      color: #2e7d32;
      padding: 0.3rem 0.8rem;
      border-radius: 12px;
      font-size: 0.85rem;
      font-weight: 600;
      margin-left: 0.5rem;
    }

    .coupon-info {
      margin-top: 0.5rem;
      padding: 0.8rem;
      background: rgba(255, 193, 7, 0.15);
      border-left: 4px solid #FFC107;
      border-radius: 8px;
      color: #856404;
      font-size: 0.95rem;
      text-align: left;
    }

    .solde-slider-box {
      background: rgba(76, 175, 80, 0.1);
      border: 1px solid rgba(76, 175, 80, 0.25);
      border-radius: 18px;
      padding: 1.5rem 1.6rem;
      margin: 1.5rem 0;
      text-align: left;
      box-shadow: 0 6px 20px rgba(76, 175, 80, 0.1);
    }

    .solde-slider-header {
      display: flex;
      justify-content: space-between;
      align-items: baseline;
      flex-wrap: wrap;
      gap: 0.4rem;
      margin-bottom: 1.1rem;
    }

    .solde-slider-header label {
      font-weight: 700;
      color: #2e7d32;
      font-size: 1.05rem;
    }

    .solde-slider-dispo {
      font-size: 0.85rem;
      font-weight: 600;
      color: #4caf50;
      background: rgba(76, 175, 80, 0.15);
      padding: 0.25rem 0.7rem;
      border-radius: 999px;
    }

    /* ── Track du slider avec remplissage dégradé dynamique ── */
    .solde-slider-track-wrap {
      padding: 0.4rem 0;
      margin-bottom: 1rem;
    }

    #montant-solde-slider {
      -webkit-appearance: none;
      appearance: none;
      width: 100%;
      height: 8px;
      border-radius: 999px;
      background: linear-gradient(to right, #4CAF50 0%, #4CAF50 0%, rgba(76, 175, 80, 0.18) 0%, rgba(76, 175, 80, 0.18) 100%);
      outline: none;
      cursor: pointer;
      transition: box-shadow 0.2s ease;
    }

    #montant-solde-slider::-webkit-slider-thumb {
      -webkit-appearance: none;
      appearance: none;
      width: 24px;
      height: 24px;
      border-radius: 50%;
      background: linear-gradient(135deg, #4CAF50, #66bb6a);
      border: 3px solid #fff;
      box-shadow: 0 3px 10px rgba(76, 175, 80, 0.5);
      cursor: grab;
      transition: transform 0.15s ease, box-shadow 0.15s ease;
      margin-top: 0;
    }

    #montant-solde-slider::-webkit-slider-thumb:hover {
      transform: scale(1.15);
      box-shadow: 0 4px 16px rgba(76, 175, 80, 0.7);
    }

    #montant-solde-slider::-webkit-slider-thumb:active {
      cursor: grabbing;
      transform: scale(1.05);
    }

    #montant-solde-slider::-moz-range-thumb {
      width: 24px;
      height: 24px;
      border-radius: 50%;
      background: linear-gradient(135deg, #4CAF50, #66bb6a);
      border: 3px solid #fff;
      box-shadow: 0 3px 10px rgba(76, 175, 80, 0.5);
      cursor: grab;
      transition: transform 0.15s ease, box-shadow 0.15s ease;
    }

    #montant-solde-slider::-moz-range-thumb:hover {
      transform: scale(1.15);
    }

    #montant-solde-slider::-moz-range-track {
      height: 8px;
      border-radius: 999px;
      background: transparent;
    }

    .solde-slider-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 0.8rem;
    }

    .solde-quick-buttons {
      display: flex;
      gap: 0.5rem;
    }

    .solde-quick-btn {
      padding: 0.4rem 0.9rem;
      font-size: 0.82rem;
      font-weight: 700;
      font-family: 'HSR', sans-serif;
      color: #2e7d32;
      background: rgba(76, 175, 80, 0.15);
      border: 1px solid rgba(76, 175, 80, 0.3);
      border-radius: 999px;
      cursor: pointer;
      transition: all 0.2s ease;
    }

    .solde-quick-btn:hover {
      background: rgba(76, 175, 80, 0.3);
      transform: translateY(-1px);
    }

    .solde-quick-btn.active {
      background: linear-gradient(135deg, #4CAF50, #66bb6a);
      color: #fff;
      border-color: transparent;
      box-shadow: 0 4px 12px rgba(76, 175, 80, 0.4);
    }

    .solde-input-group {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      background: rgba(255, 255, 255, 0.55);
      border: 1px solid rgba(76, 175, 80, 0.35);
      border-radius: 10px;
      padding: 0.3rem 0.7rem;
    }

    .solde-input-group input[type="number"] {
      width: 80px;
      border: none;
      background: transparent;
      color: #222;
      font-family: 'HSR', sans-serif;
      font-size: 0.98rem;
      font-weight: 600;
      text-align: right;
      outline: none;
    }

    .solde-input-group span {
      color: #2e7d32;
      font-weight: 700;
    }

    .solde-slider-hint {
      margin: 1.1rem 0 0 0;
      font-size: 0.82rem;
      color: rgba(53, 53, 53, 0.75);
    }

    .erreur-paiement {
      background: rgba(255, 77, 77, 0.18);
      border-left: 4px solid #ff4d4d;
      color: #8b0000;
      padding: 0.8rem 1.2rem;
      border-radius: 10px;
      margin: 1rem 0;
      text-align: left;
      font-weight: 600;
    }

    .btn {
      background: linear-gradient(135deg, var(--accent), var(--accent-dark));
      color: white;
      border: none;
      border-radius: 14px;
      padding: 12px 26px;
      font-size: 1.1rem;
      cursor: pointer;
      transition: all 0.3s ease;
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
    }

    .btn:hover {
      transform: scale(1.05);
      box-shadow: 0 8px 25px rgba(0, 0, 0, 0.4);
      background: linear-gradient(135deg, var(--accent-dark), var(--accent));
    }

    a {
      color: var(--accent);
      text-decoration: none;
      font-weight: bold;
      display: inline-block;
      margin-top: 20px;
      transition: color 0.3s ease;
    }

    a:hover {
      color: var(--accent-dark);
    }

    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(10px); }
      to { opacity: 1; transform: translateY(0); }
    }
  </style>
</head>
<body>
  <audio id="player" autoplay loop> <source src="https://raw.githubusercontent.com/HDSachaNewLive/foodhub-assets/main/January 2015 - Nintendo eShop Music.mp3" type="audio/mpeg"> </audio>
  <?php include 'vanta_freeze.php'; ?>
  <?php include "slider_son.php"; ?>
<main class="container">
  <h2>💳 Paiement simulé</h2>
  <p>Commande #<?= (int)$commande['numero_utilisateur'] ?></p>

  <ul>
    <?php foreach($items as $it): ?>
      <li>
        <span><?= htmlspecialchars($it['nom_plat']) ?> ×<?= (int)$it['quantite'] ?></span>
        <span><?= number_format($it['prix_unitaire'],2) ?> €</span>
      </li>
    <?php endforeach; ?>
  </ul>
  
  <div class="payment-summary">
    <div class="summary-line">
      <span>Sous-total :</span>
      <span><?= number_format($sous_total, 2) ?> €</span>
    </div>
    
    <?php if ($commande['montant_reduction'] > 0): ?>
      <div class="summary-line discount">
        <span>
          Réduction <?php if ($commande['code_reduction']): ?>
            <span class="coupon-badge">🎟️ <?= htmlspecialchars($commande['code_reduction']) ?></span>
          <?php endif; ?>:
        </span>
        <span>-<?= number_format($commande['montant_reduction'], 2) ?> €</span>
      </div>
      
      <?php if ($commande['coupon_restaurant_id'] && $eligible_total > 0): ?>
        <?php 
          //récupérer le nom du restaurant concerné
          $stmt_resto = $conn->prepare("SELECT nom_restaurant FROM restaurants WHERE restaurant_id = ?");
          $stmt_resto->execute([$commande['coupon_restaurant_id']]);
          $resto_name = $stmt_resto->fetchColumn();
        ?>
        <div class="coupon-info">
          📌 Ce coupon s'applique uniquement aux articles de <strong><?= htmlspecialchars($resto_name) ?></strong> (<?= number_format($eligible_total, 2) ?> €)
        </div>
      <?php endif; ?>
    <?php endif; ?>

    <div class="summary-line discount" id="ligne-solde" style="<?= $solde_max_utilisable > 0 ? '' : 'display:none' ?>">
      <span>💰 Déduit de ton solde :</span>
      <span id="montant-solde-affiche">-0.00 €</span>
    </div>

    <div class="summary-line total">
      <span id="libelle-total">Reste à payer :</span>
      <span id="montant-reste-affiche"><?= number_format($commande['montant_total'], 2) ?> €</span>
    </div>
  </div>

  <?php if ($solde_max_utilisable > 0): ?>
  <div class="solde-slider-box">
    <div class="solde-slider-header">
      <label for="montant-solde-input">💰 Utiliser mon solde FoodHub</label>
      <span class="solde-slider-dispo"><?= number_format($solde_actuel, 2) ?> € disponible</span>
    </div>

    <div class="solde-slider-track-wrap">
      <input type="range" id="montant-solde-slider" min="0" max="<?= number_format($solde_max_utilisable, 2, '.', '') ?>" step="0.01" value="0">
    </div>

    <div class="solde-slider-row">
      <div class="solde-quick-buttons">
        <button type="button" class="solde-quick-btn" data-pct="0">0%</button>
        <button type="button" class="solde-quick-btn" data-pct="25">25%</button>
        <button type="button" class="solde-quick-btn" data-pct="50">50%</button>
        <button type="button" class="solde-quick-btn" data-pct="100">Max</button>
      </div>
      <div class="solde-input-group">
        <input type="number" id="montant-solde-input" name="montant_solde_display" min="0" max="<?= number_format($solde_max_utilisable, 2, '.', '') ?>" step="0.01" value="0.00">
        <span>€</span>
      </div>
    </div>

    <p class="solde-slider-hint">Choisis combien tu veux déduire de ton solde pour cette commande (jusqu'à <?= number_format($solde_max_utilisable, 2) ?> €). Le reste sera réglé via ton mode de paiement.</p>
  </div>
  <?php endif; ?>

  <?php if ($erreur_solde): ?>
    <div class="erreur-paiement"><?= $erreur_solde ?></div>
  <?php endif; ?>

  <form method="post" id="form-paiement">
    <?= fh_csrf_field() ?>
    <input type="hidden" name="montant_solde" id="montant-solde-hidden" value="0.00">
    <button class="btn" type="submit">💸 Confirmer le paiement</button>
  </form>

  <a href="panier.php">← Retour au panier</a>
</main>

<script>
(function() {
  const soldeMax   = <?= json_encode(round($solde_max_utilisable, 2)) ?>;
  const totalCmd   = <?= json_encode(round((float)$commande['montant_total'], 2)) ?>;
  const slider     = document.getElementById('montant-solde-slider');
  const input      = document.getElementById('montant-solde-input');
  const hiddenIn   = document.getElementById('montant-solde-hidden');
  const ligneSolde = document.getElementById('ligne-solde');
  const soldeAff   = document.getElementById('montant-solde-affiche');
  const resteAff   = document.getElementById('montant-reste-affiche');
  const libelleTot = document.getElementById('libelle-total');

  function formatEuro(n) {
    return n.toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' €';
  }

  function recalculer(valeurBrute) {
    let montant = parseFloat(valeurBrute);
    if (isNaN(montant) || montant < 0) montant = 0;
    if (montant > soldeMax) montant = soldeMax;
    montant = Math.round(montant * 100) / 100;

    const reste = Math.round((totalCmd - montant) * 100) / 100;
    const pct = soldeMax > 0 ? (montant / soldeMax) * 100 : 0;

    if (slider) {
      slider.value = montant;
      slider.style.background = `linear-gradient(to right, #4CAF50 0%, #4CAF50 ${pct}%, rgba(76, 175, 80, 0.18) ${pct}%, rgba(76, 175, 80, 0.18) 100%)`;
    }
    if (input) input.value = montant.toFixed(2);
    hiddenIn.value = montant.toFixed(2);

    if (ligneSolde) soldeAff.textContent = '-' + formatEuro(montant);
    resteAff.textContent = formatEuro(Math.max(0, reste));
    libelleTot.textContent = reste > 0 ? 'Reste à payer :' : 'Total (déjà réglé via ton solde) :';

    // Surligner le bouton de raccourci correspondant, s'il y en a un qui matche exactement
    document.querySelectorAll('.solde-quick-btn').forEach(b => {
      const cible = Math.round((parseFloat(b.dataset.pct) / 100) * soldeMax * 100) / 100;
      b.classList.toggle('active', Math.abs(cible - montant) < 0.005);
    });
  }

  document.querySelectorAll('.solde-quick-btn').forEach(b => {
    b.addEventListener('click', () => {
      const cible = (parseFloat(b.dataset.pct) / 100) * soldeMax;
      recalculer(cible);
    });
  });

  if (slider) {
    slider.addEventListener('input', () => recalculer(slider.value));
    input.addEventListener('input', () => recalculer(input.value));
    recalculer(0);
  }
})();
</script>

<script src="https://cdn.jsdelivr.net/npm/three@0.149.0/build/three.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/vanta/dist/vanta.waves.min.js"></script>

<script>
window.vantaEffect = VANTA.WAVES({
  el: "body",
  mouseControls: true,
  touchControls: true,
  gyroControls: false,
  minHeight: 885.00,
  minWidth: 200.00,
  scale: 1.00,
  scaleMobile: 1.00,
  color: 0xf6b26b,
  shininess: 25,
  waveHeight: 25,
  waveSpeed: 0.9,
  zoom: 1
})
</script>
</body>
</html>