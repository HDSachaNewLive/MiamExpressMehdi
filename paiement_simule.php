<?php
// paiement_simule.php
session_start();
require_once 'db/config.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
$uid = (int)$_SESSION['user_id'];
$commande_id = (int)($_GET['commande_id'] ?? 0);

if (!$commande_id) header('Location: home.php');

$stmt = $conn->prepare("SELECT c.*, cp.code_reduction, cp.restaurant_id as coupon_restaurant_id FROM commandes c LEFT JOIN coupons cp ON c.coupon_id = cp.coupon_id WHERE c.commande_id = ? AND c.user_id = ?");
$stmt->execute([$commande_id, $uid]);
$commande = $stmt->fetch();
if (!$commande) die("Commande introuvable.");

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

// Changer statut commande
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $conn->prepare("UPDATE commandes SET statut = 'en_preparation', date_paiement = NOW() WHERE commande_id = ?")->execute([$commande_id]);
  header("Location: suivi_commande.php?commande_id=".$commande_id);
  exit;
}
?>
<!doctype html>
<html lang="fr">
<head>
  <audio autoplay> <source src="assets\done-download.wav" type="audio/mpeg"> </audio>
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
  <audio id="player" autoplay loop> <source src="assets\January 2015 - Nintendo eShop Music.mp3" type="audio/mpeg"> </audio>
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
          // Récupérer le nom du restaurant concerné
          $stmt_resto = $conn->prepare("SELECT nom_restaurant FROM restaurants WHERE restaurant_id = ?");
          $stmt_resto->execute([$commande['coupon_restaurant_id']]);
          $resto_name = $stmt_resto->fetchColumn();
        ?>
        <div class="coupon-info">
          📌 Ce coupon s'applique uniquement aux articles de <strong><?= htmlspecialchars($resto_name) ?></strong> (<?= number_format($eligible_total, 2) ?> €)
        </div>
      <?php endif; ?>
    <?php endif; ?>
    
    <div class="summary-line total">
      <span>Total à payer :</span>
      <span><?= number_format($commande['montant_total'], 2) ?> €</span>
    </div>
  </div>

  <form method="post">
    <button class="btn" type="submit">💸 Confirmer le paiement</button>
  </form>

  <a href="panier.php">← Retour au panier</a>
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
  shininess: 25,
  waveHeight: 25,
  waveSpeed: 0.9,
  zoom: 1
})
</script>
</body>
</html>