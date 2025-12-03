<?php
// suivi_commande.php
session_start();
require_once 'db/config.php';

if (!isset($_SESSION['user_id'])) {
  header('Location: login.php');
  exit;
}

$uid = $_SESSION['user_id'];
$commande_id = (int)($_GET['commande_id'] ?? 0);

// Annulation
if (isset($_POST['annuler_commande'])) {
  $cancel_id = (int)$_POST['commande_id'];
  $stmt = $conn->prepare("UPDATE commandes SET statut = 'annulee' WHERE commande_id = ? AND user_id = ? AND statut NOT IN ('livree','annulee')");
  $stmt->execute([$cancel_id, $uid]);
  header("Location: suivi_commande.php?commande_id=$cancel_id");
  exit;
}

if ($commande_id) {
  $stmt = $conn->prepare("SELECT c.*, u.nom_user, cp.code_reduction, cp.restaurant_id as coupon_restaurant_id
                          FROM commandes c 
                          JOIN users u ON c.user_id = u.user_id 
                          LEFT JOIN coupons cp ON c.coupon_id = cp.coupon_id
                          WHERE c.commande_id = ? AND c.user_id = ?");
  $stmt->execute([$commande_id, $uid]);
  $cmd = $stmt->fetch();

  if (!$cmd) die("Commande introuvable.");

  $stmt = $conn->prepare("SELECT cp.*, pl.nom_plat, pl.restaurant_id, r.nom_restaurant
                          FROM commande_plats cp 
                          JOIN plats pl ON cp.plat_id = pl.plat_id 
                          JOIN restaurants r ON pl.restaurant_id = r.restaurant_id
                          WHERE cp.commande_id = ?");
  $stmt->execute([$commande_id]);
  $items = $stmt->fetchAll();
  
  // Calculer le sous-total et eligible_total
  $sous_total = 0;
  $eligible_total = 0;
  foreach ($items as $it) {
      $item_total = $it['prix_unitaire'] * $it['quantite'];
      $sous_total += $item_total;
      
      // Si coupon limité à un restaurant
      if ($cmd['coupon_restaurant_id'] && $it['restaurant_id'] == $cmd['coupon_restaurant_id']) {
          $eligible_total += $item_total;
      }
  }
}
else {
  $stmt = $conn->prepare("SELECT * FROM commandes WHERE user_id = ? ORDER BY date_commande DESC");
  $stmt->execute([$uid]);
  $commandes = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Suivi de tes commandes - FoodHub</title>
  <link rel="stylesheet" href="assets/style.css">
  <audio autoplay> <source src="assets\done.wav" type="audio/mpeg"> </audio>
  <?php include "sidebar.php"; ?>
  <style>
    .order-container {
      backdrop-filter: blur(17px);
      background: rgba(255, 255, 255, 0.15);
      padding: 1.5rem;
      border-radius: 1.2rem;
      box-shadow: 0 4px 10px rgba(0,0,0,0.1);
      margin-bottom: 1.3rem;
      transition: transform .2s;
    }
    .order-container:hover {
      transform: translateY(-3px);
    }
    .order-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    .order-status {
      font-weight: 600;
      padding: 0.3rem 0.7rem;
      border-radius: 0.6rem;
    }
    .status-en_attente { background: #ffe7a1; color: #8b6b00; }
    .status-en_preparation { background: #ffce8b; color: #8a4800; }
    .status-livree { background: #c5f5c0; color: #176b00; }
    .status-annulee { background: #ffc9c9; color: #a10000; }
    .status-en_livraison { background: #a1c4ff; color: #00458b; }
    
    .order-list li {
      list-style: none;
      background: #fff7f4;
      margin: 0.5rem 0;
      padding: .5rem 1rem;
      border-radius: .6rem;
    }
    .btn {
      background: var(--accent);
      color: white;
      border: none;
      padding: .6rem 1rem;
      border-radius: .8rem;
      cursor: pointer;
      transition: 0.2s;
      text-decoration: none;
      display: inline-block;
    }
    .btn:hover {
      background: var(--accent-dark);
    }
    .btn-cancel {
      background: #ff6666;
    }
    .btn-cancel:hover {
      background: #e55;
    }
    .back-link {
      display: inline-block;
      margin-top: 1.2rem;
      text-decoration: none;
      color: var(--accent-dark);
    }
    .btn-glass {
      justify-content: center;
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
      display: inline-block;
      text-align: center;
    }

    .btn-glass:hover {
      color: white;
      transform: translateY(-2px);
      box-shadow: 0 12px 40px rgba(0,0,0,0.35);
      background: rgba(249, 158, 72, 0.55);
    }
    
    .order-summary {
      background: rgba(255, 255, 255, 0.25);
      padding: 1rem;
      border-radius: 0.8rem;
      margin-top: 1rem;
    }
    
    .summary-line {
      display: flex;
      justify-content: space-between;
      padding: 0.5rem 0;
    }
    
    .summary-line.discount {
      color: #4CAF50;
      font-weight: 600;
    }
    
    .summary-line.total {
      border-top: 2px solid rgba(255, 107, 107, 0.3);
      margin-top: 0.5rem;
      padding-top: 0.8rem;
      font-weight: 700;
      color: #ff6b6b;
      font-size: 1.2rem;
      margin-bottom: -8px;
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
      margin-top: 0.8rem;
      padding: 0.8rem;
      background: rgba(255, 193, 7, 0.15);
      border-left: 4px solid #FFC107;
      border-radius: 8px;
      color: #856404;
      font-size: 0.95rem;
    }
    .btn-cancel{
      margin-top: 1rem;
    }
  </style>
</head>
<body>
  <audio id="player" loop autoplay> <source src="assets\January 2015 - Nintendo eShop Music.mp3" type="audio/mpeg"> </audio>
  <?php include "slider_son.php"; ?>
  <main class="container">
  <h2>🛍️ Suivi de tes commandes</h2>

  <?php if ($commande_id): ?>
    <div class="order-container" data-commande-id="<?= $cmd['commande_id'] ?>" data-date="<?= $cmd['date_commande'] ?>">
      <div class="order-header">
        <h3>Commande #<?= htmlspecialchars($cmd['numero_utilisateur']) ?></h3>
        <span class="order-status status-<?= strtolower(str_replace(' ', '_', $cmd['statut'])) ?>">
          <?= htmlspecialchars($cmd['statut']) ?>
        </span>
      </div>
      <p><strong>Date :</strong> <?= htmlspecialchars($cmd['date_commande']) ?></p>
      
      <h4>Contenu :</h4>
      <ul class="order-list">
        <?php foreach($items as $it): ?>
          <li><?= htmlspecialchars($it['nom_plat']) ?> × <?= (int)$it['quantite'] ?> — <?= number_format($it['prix_unitaire'],2) ?> €</li>
        <?php endforeach; ?>
      </ul>
      
      <div class="order-summary">
        <div class="summary-line">
          <span>Sous-total :</span>
          <span><?= number_format($sous_total, 2) ?> €</span>
        </div>
        
        <?php if ($cmd['montant_reduction'] > 0): ?>
          <div class="summary-line discount">
            <span>
              Réduction <?php if ($cmd['code_reduction']): ?>
                <span class="coupon-badge">🎟️ <?= htmlspecialchars($cmd['code_reduction']) ?></span>
              <?php endif; ?>:
            </span>
            <span>-<?= number_format($cmd['montant_reduction'], 2) ?> €</span>
          </div>
          
          <?php if ($cmd['coupon_restaurant_id'] && $eligible_total > 0): ?>
            <?php 
              // Récupérer le nom du restaurant concerné
              $stmt_resto = $conn->prepare("SELECT nom_restaurant FROM restaurants WHERE restaurant_id = ?");
              $stmt_resto->execute([$cmd['coupon_restaurant_id']]);
              $resto_name = $stmt_resto->fetchColumn();
            ?>
            <div class="coupon-info">
              📌 Ce coupon s'applique uniquement aux articles de <strong><?= htmlspecialchars($resto_name) ?></strong> (<?= number_format($eligible_total, 2) ?> €)
            </div>
          <?php endif; ?>
        <?php endif; ?>
        
        <div class="summary-line total">
          <span>Total :</span>
          <span><?= number_format($cmd['montant_total'], 2) ?> €</span>
        </div>
      </div id="boutons">
        <div id="boutons" style="display: flex; gap: 1rem; justify-content: center; align-items: center; flex-wrap: wrap; margin-top: 1rem;">
          <a href="reorder_command.php?commande_id=<?= $cmd['commande_id'] ?>" class="btn btn-fin">
          🔄 Commander à nouveau
          </a>
        
          <a href="export_invoice.php?commande_id=<?= $cmd['commande_id'] ?>&format=pdf" class="btn btn-fin" target="_blank">
          📄 Télécharger la facture (PDF)
          </a>
        </div>
      <style>
      #boutons {
        margin-bottom: -22px;
        margin-top: -4px;
      }
      .btn-fin {
      background: linear-gradient(135deg, #FFC107, #FF9800);
      color: white;
      padding: 0.7rem 1.5rem;
      border-radius: 10px;
      text-decoration: none;
      font-weight: 600;
      transition: all 0.3s ease;
      display: inline-block;
      }

      .btn-fin:hover {
      transform: translateY(-2px) scale(1.035);
      box-shadow: 0 6px 20px rgba(255, 193, 7, 0.4);
      background: linear-gradient(135deg, #FFC107, #FF9800);
      color: #ffffff;
      }
      </style>
      
      <br>
      <?php if (!in_array(strtolower($cmd['statut']), ['livree', 'annulee'])): ?>
        <form method="post" onsubmit="return confirm('Tu veux vraiment annuler cette commande ? 😢');">
          <input type="hidden" name="commande_id" value="<?= $cmd['commande_id'] ?>">
          <?php if ($cmd['statut'] === 'en_preparation' || $cmd['statut'] === 'en_attente'):?>
          <button type="submit" name="annuler_commande" class="btn btn-cancel">Annuler la commande</button>
          <?php endif; ?>
        </form>
      <?php endif; ?>

      <a href="suivi_commande.php" class="back-link">← Retour à mes commandes
        <audio autoplay> <source src="assets\done.wav" type="audio/mpeg"> </audio>
      </a>
    </div>

  <?php else: ?>
    <?php if (empty($commandes)): ?>
      <p>Tu n'as encore passé aucune commande 😢</p>
    <div style="margin-top: 2rem; display: flex; gap: 1.5rem; flex-wrap: wrap; justify-content: center; align-items: center;">
      <a href="restaurants.php" class="btn btn-glass">🔎 Explorer les restaurants</a>
      <a href="panier.php" class="btn btn-glass">🛒 Voir mon panier</a>
    </div>
    <?php else: ?>
      <?php foreach ($commandes as $c): ?>
        <div class="order-container" data-commande-id="<?= $c['commande_id'] ?>" data-date="<?= $c['date_commande'] ?>">
          <div class="order-header">
            <h3>Commande #<?= htmlspecialchars($c['numero_utilisateur']) ?></h3>
            <span class="order-status status-<?= strtolower(str_replace(' ', '_', $c['statut'])) ?>">
              <?= htmlspecialchars($c['statut']) ?>
            </span>
          </div>
          <p><strong>Date :</strong> <?= htmlspecialchars($c['date_commande']) ?></p>
          <p><strong>Total :</strong> <?= number_format($c['montant_total'],2) ?> €
            <?php if ($c['montant_reduction'] > 0): ?>
              <span style="color: #4CAF50; font-weight: 600;">
                (Réduction : -<?= number_format($c['montant_reduction'],2) ?> €)
              </span>
            <?php endif; ?>
          </p>
          <a class="btn" href="suivi_commande.php?commande_id=<?= $c['commande_id'] ?>">Voir détails</a>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  <?php endif; ?>

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

<script>
document.addEventListener('DOMContentLoaded', () => {
  const timing = {
    en_attente: 10000,
    en_preparation: 15000,
    en_livraison: 20000
  };

  const orders = document.querySelectorAll('.order-container');

  orders.forEach(order => {
    let statusEl = order.querySelector('.order-status');
    let currentStatus = statusEl.textContent.trim().toLowerCase();
    const commandeId = order.dataset.commandeId;

    const updateStatus = () => {
      if (!currentStatus) return;
      let delay = timing[currentStatus] || 0;
      if (delay === 0) return;

      setTimeout(async () => {
        try {
          const resp = await fetch('update_order_status.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'commande_id=' + encodeURIComponent(commandeId)
          });
          const data = await resp.json();
          if (data.success) {
            currentStatus = data.newStatus;
            statusEl.textContent = currentStatus.replace('_',' ');
            statusEl.className = 'order-status status-' + currentStatus;
            updateStatus();
          }
        } catch (err) {
          console.error(err);
        }
      }, delay);
    };

    updateStatus();
  });
});
</script>
</body>
</html>