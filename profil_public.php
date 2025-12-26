<?php
// profil_public.php
session_start();
require_once 'db/config.php';

$target_user_id = (int)($_GET['user_id'] ?? 0);
$connected = isset($_SESSION['user_id']);

if (!$target_user_id) {
    header('Location: home.php');
    exit;
}

// Récupérer les infos de l'utilisateur
$stmt = $conn->prepare("
    SELECT u.*, 
           (SELECT COUNT(*) FROM avis WHERE user_id = u.user_id) as nb_avis,
           (SELECT COUNT(*) FROM restaurants WHERE proprietaire_id = u.user_id) as nb_restaurants
    FROM users u 
    WHERE u.user_id = ?
");
$stmt->execute([$target_user_id]);
$user = $stmt->fetch();

if (!$user) {
    die("Utilisateur introuvable.");
}

// Vérifier si le compte est actif
if (!$user['compte_actif']) {
    die("Ce compte a été désactivé.");
}

// Incrémenter les statistiques de visite (sauf si c'est le propriétaire)
if (!$connected || $_SESSION['user_id'] != $target_user_id) {
    $stmt = $conn->prepare("
        INSERT INTO profil_stats (user_id, nb_visites, derniere_visite)
        VALUES (?, 1, NOW())
        ON DUPLICATE KEY UPDATE 
            nb_visites = nb_visites + 1,
            derniere_visite = NOW()
    ");
    $stmt->execute([$target_user_id]);
}

// Récupérer les stats
$stmt = $conn->prepare("SELECT * FROM profil_stats WHERE user_id = ?");
$stmt->execute([$target_user_id]);
$stats = $stmt->fetch();

// Récupérer les derniers avis
$stmt = $conn->prepare("
    SELECT a.*, r.nom_restaurant, r.restaurant_id
    FROM avis a
    JOIN restaurants r ON a.restaurant_id = r.restaurant_id
    WHERE a.user_id = ?
    ORDER BY a.date_avis DESC
    LIMIT 5
");
$stmt->execute([$target_user_id]);
$derniers_avis = $stmt->fetchAll();

// Récupérer les restaurants si propriétaire
$restaurants = [];
if ($user['type_compte'] === 'proprietaire') {
    $stmt = $conn->prepare("
        SELECT r.*, 
               AVG(a.note) as note_moyenne,
               COUNT(a.avis_id) as nb_avis_resto
        FROM restaurants r
        LEFT JOIN avis a ON r.restaurant_id = a.restaurant_id
        WHERE r.proprietaire_id = ? AND r.verified = 1
        GROUP BY r.restaurant_id
    ");
    $stmt->execute([$target_user_id]);
    $restaurants = $stmt->fetchAll();
}

// affecter couleur Vanta
$couleur_vanta = $user['couleur_vanta'] ?? '#dba1b2';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <title><?= htmlspecialchars($user['nom_user']) ?> - Profil FoodHub</title>
  <link rel="stylesheet" href="assets/style.css">
  <link rel="stylesheet" href="profil_public.css">
  <?php include 'slider_son.php'; ?>
  <?php include 'sidebar.php'; ?>
</head>
<body>
  <?php if ($connected): ?>
    <audio id="player" autoplay loop>
      <source src="assets/3DS-Theme-Shop.mp3" type="audio/mpeg">
    </audio>
    <?php include "slider_son.php"; ?>
  <?php endif; ?>
  
  <main class="container profil-public">
    <div class="profil-header">
      <div class="profil-photo-large">
        <?php if ($user['photo_profil'] && file_exists($user['photo_profil'])): ?>
          <img src="<?= htmlspecialchars($user['photo_profil']) ?>" alt="<?= htmlspecialchars($user['nom_user']) ?>">
        <?php else: ?>
          <div class="default-photo-large">
            <?= strtoupper(substr($user['nom_user'], 0, 2)) ?>
          </div>
        <?php endif; ?>
      </div>
      
      <div class="profil-info-header">
        <h1><?= htmlspecialchars($user['nom_user']) ?></h1>
        
        <div class="profil-badges">
          <?php if ($user['user_id'] == 1): ?>
            <span class="badge admin">⚙️ Administrateur</span>
          <?php else: ?>
            <span class="badge <?= $user['type_compte'] ?>">
              <?= $user['type_compte'] === 'proprietaire' ? '🪙 Propriétaire' : '👤 Client' ?>
            </span>
          <?php endif; ?>
        </div>
        
        <?php if ($user['description_profil']): ?>
          <p class="profil-description"><?= nl2br(htmlspecialchars($user['description_profil'])) ?></p>
        <?php endif; ?>
        
        <div class="profil-meta">
          <span>📅 Membre depuis le <?= date('d/m/Y', strtotime($user['date_creation'])) ?></span>
          <span>👁️ <?= $stats['nb_visites'] ?? 0 ?> visites</span>
        </div>
      </div>
    </div>

    <div class="profil-stats-grid">
      <div class="stat-card">
        <h3>💬 Avis postés</h3>
        <p><?= $user['nb_avis'] ?></p>
      </div>
      
      <?php if ($user['type_compte'] === 'proprietaire'): ?>
        <div class="stat-card">
          <h3>🍽️ Restaurants</h3>
          <p><?= $user['nb_restaurants'] ?></p>
        </div>
      <?php endif; ?>
      
      <div class="stat-card">
        <h3>⭐ Membre depuis</h3>
        <p><?= floor((time() - strtotime($user['date_creation'])) / (60 * 60 * 24)) ?> jours</p>
      </div>
    </div>

    <?php if (!empty($derniers_avis)): ?>
      <div class="profil-section">
        <h2>💬 Derniers avis</h2>
        <div class="avis-list">
          <?php foreach ($derniers_avis as $avis): ?>
            <div class="avis-card">
              <div class="avis-header">
                <a href="menu.php?restaurant_id=<?= $avis['restaurant_id'] ?>" class="restaurant-link">
                  <?= htmlspecialchars($avis['nom_restaurant']) ?>
                </a>
                <div class="stars">
                  <?php for ($i = 1; $i <= 5; $i++): ?>
                    <span class="star <?= $i <= $avis['note'] ? 'filled' : '' ?>">★</span>
                  <?php endfor; ?>
                </div>
              </div>
              <p class="avis-commentaire"><?= htmlspecialchars($avis['commentaire']) ?></p>
              <span class="avis-date"><?= date('d/m/Y', strtotime($avis['date_avis'])) ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

    <?php if (!empty($restaurants)): ?>
      <div class="profil-section">
        <h2>🍽️ Ses restaurants</h2>
        <div class="restaurants-grid">
          <?php foreach ($restaurants as $resto): ?>
            <div class="restaurant-card">
              <h4><?= htmlspecialchars($resto['nom_restaurant']) ?></h4>
              <p class="restaurant-category"><?= htmlspecialchars($resto['categorie']) ?></p>
              <p class="restaurant-address">📍 <?= htmlspecialchars($resto['adresse']) ?></p>
              
              <?php if ($resto['note_moyenne']): ?>
                <div class="restaurant-rating">
                  <div class="stars">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                      <span class="star <?= $i <= round($resto['note_moyenne']) ? 'filled' : '' ?>">★</span>
                    <?php endfor; ?>
                  </div>
                  <span class="rating-text"><?= number_format($resto['note_moyenne'], 1) ?> (<?= $resto['nb_avis_resto'] ?> avis)</span>
                </div>
              <?php endif; ?>
              
              <a href="menu.php?restaurant_id=<?= $resto['restaurant_id'] ?>" class="btn-small">Voir le menu</a>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

    <?php if ($connected): ?>
      <p><a href="<?= isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'home.php' ?>" class="back-link">← Retour</a></p>
    <?php else: ?>
      <p><a href="index.php" class="back-link">← Retour à l'accueil</a></p>
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
    color: <?= json_encode($couleur_vanta) ?>,
    shininess: 25,
    waveHeight: 25,
    waveSpeed: 0.9,
    zoom: 0.9
  });
  </script>

  <style>
  /* déplacé vers profil_public.css */
  </style>
</body>
</html>