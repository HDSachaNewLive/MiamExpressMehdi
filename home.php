<?php
// home.php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
require_once 'db/config.php';
$connected = isset($_SESSION['user_id']);

$uid = $_SESSION['user_id'];

// récup Nombre de restaurants
$stmt = $conn->query("SELECT COUNT(*) as nb_restos FROM restaurants WHERE verified = 1");
$countRestaurants = $stmt->fetch()['nb_restos'];
// nbr commandes non annulée
$stmtCommandes = $conn->prepare("SELECT COUNT(*) AS nb_commandes FROM commandes WHERE user_id = ? AND statut != 'annulee'");
$stmtCommandes->execute([$uid]);
$countCommandes = $stmtCommandes->fetch()['nb_commandes'] ?? 0;

// taille panier
$stmtPanier = $conn->prepare("SELECT COALESCE(SUM(quantite),0) AS nb_panier FROM panier WHERE user_id = ?");
$stmtPanier->execute([$uid]);
$countPanier = $stmtPanier->fetch()['nb_panier'] ?? 0;

// Nombre totalutilisateurs
$stmt = $conn->query("SELECT COUNT(*) AS nb_utilisateurs FROM users");
$countUsers = $stmt->fetch()['nb_utilisateurs'] ?? 0;

//recuperer meilleurs avis (4 étoiles) et plus pour le mur de retours positifs
$stmt = $conn->prepare("
  SELECT a.avis_id, a.note, a.commentaire, a.date_avis, a.likes, a.dislikes,
         u.nom_user, r.nom_restaurant, r.restaurant_id
  FROM avis a
  JOIN users u ON a.user_id = u.user_id
  JOIN restaurants r ON a.restaurant_id = r.restaurant_id
  WHERE a.note >= 4 AND r.verified = 1
  ORDER BY a.note DESC, a.likes DESC, a.date_avis DESC
  LIMIT 10
");
$stmt->execute();
$topReviews = $stmt->fetchAll();

// Récupérer les restaurants recommandés (note moyenne 3.5+)
$stmt = $conn->prepare("
  SELECT r.restaurant_id, r.nom_restaurant, r.adresse, r.categorie, r.description_resto,
         AVG(a.note) as note_moyenne, COUNT(a.avis_id) as nb_avis,
         u.nom_user as owner_name
  FROM restaurants r
  LEFT JOIN avis a ON r.restaurant_id = a.restaurant_id
  LEFT JOIN users u ON r.proprietaire_id = u.user_id
  WHERE r.verified = 1
  GROUP BY r.restaurant_id
  HAVING note_moyenne >= 3 OR note_moyenne IS NULL
  ORDER BY note_moyenne DESC, nb_avis DESC
  LIMIT 6
");
$stmt->execute();
$recommendedRestaurants = $stmt->fetchAll();

// Récupérer les 3 dernières commandes de l'utilisateur
$stmt = $conn->prepare("
    SELECT c.commande_id, c.numero_utilisateur, c.date_commande, c.statut, 
           c.montant_total, c.montant_reduction,
           COUNT(DISTINCT cp.plat_id) as nb_articles
    FROM commandes c
    LEFT JOIN commande_plats cp ON c.commande_id = cp.commande_id
    WHERE c.user_id = ?
    GROUP BY c.commande_id
    ORDER BY c.date_commande DESC
    LIMIT 3
");
$stmt->execute([$uid]);
$dernieresCommandes = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <title>Accueil - FoodHub</title>
  <link rel="stylesheet" href="assets/style.css">
  <link rel="stylesheet" href="assets/surprise.css">
  <link rel="stylesheet" href="assets/barre_annonces.css">
  
  <?php include 'sidebar.php'; ?>
  <?php include "slider_son.php"; ?>
</head>
<body>
<audio id="player"></audio>
<div id="music-controls">
  <button id="prev-track" class="music-btn" title="Piste précédente">⏮️</button>
  <button id="next-track" class="music-btn" title="Piste suivante">⏭️</button>
</div>

<style>
#music-controls {
  position: fixed;
  top: 82.5px;
  right: 90px;
  z-index: 99998;
  background: rgba(255, 255, 255, 0.15);
  backdrop-filter: blur(15px);
  padding: 0.4rem 0.4rem;
  border-radius: 1rem;
  display: flex;
  align-items: center;
  gap: 10px;
  box-shadow: 0 6px 18px rgba(0,0,0,0.18);
  animation: fadeIn 0.4s ease forwards;
}

.music-btn {
  display: flex;
  justify-content: center;
  align-items: center;
  width: 10px;
  height: 20px;
  font-size: 0.7rem;
  background: linear-gradient(135deg, #ff6b6b, #ffc342);
  border: none;
  border-radius: 10px;
  cursor: pointer;
  transition: all 0.3s ease;
  box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.music-btn:hover {
  background: linear-gradient(135deg, #ff6b6b, #ffc342);
  transform: scale(1.10);
  box-shadow: 0 6px 18px rgba(0,0,0,0.25);
}

@keyframes fadeIn {
  from { opacity: 0; transform: translateY(-10px); }
  to { opacity: 1; transform: translateY(0); }
}
</style>
</div>
  <main class="container">
    <?php
    $hour = date('H');
    if($hour < 12) $greeting = "Bonjour";
    elseif($hour < 18) $greeting = "Bon après-midi";
    else $greeting = "Bonsoir";
    ?>
    <h2><?= $greeting ?>, <?= htmlspecialchars($_SESSION['nom_user']) ?> !</h2>

    <h3>Coucou^^</h3>
    <p>Tu es connecté(e).</p>
    
    <div class="stats-grid">
      <div class="stat-card">
        <h4>Restaurants disponibles</h4>
        <p><?= $countRestaurants ?></p>
      </div>
      <div class="stat-card">
        <h4>Commandes passées</h4>
        <p><?= $countCommandes ?></p>
      </div>
      <div class="stat-card">
        <h4>Articles dans ton panier</h4>
        <p><?= $countPanier ?></p>
      </div>
      <div class="stat-card">
        <h4>Utilisateurs inscrits</h4>
        <p><?= $countUsers ?></p>
      </div>
    </div>
    <div class="links">
        <?php if (isset($_SESSION['type_compte']) && $_SESSION['type_compte'] === 'proprietaire'): ?>
        <a class="btn" href="profile_proprio.php">Profil</a>
        <?php elseif (isset($_SESSION['type_compte']) && $_SESSION['type_compte'] === 'client'): ?>
        <a class="btn" href="profile.php">Profil</a>
        <?php endif; ?>
      <a class="btn" href="panier.php">Voir mon panier</a>
      <a class="btn" href="restaurants.php">Voir les restaurants</a>
      <?php if (($_SESSION['type_compte']) == 'proprietaire') :?>
        <a class="btn" href="vendor_add_restaurant.php">Ajouter un restaurant</a>
      <?php endif; ?>
      <a class="btn" href="logout.php">Se déconnecter</a>
    </div>
    
  <!-- section surprise plat random -->
<div class="surprise-section">
  <h3>🎲 Plat aléatoire !</h3>
  <p>Laisse-nous choisir pour toi ! Définis ton budget.</p>
  
  <div class="surprise-form">
    <div class="budget-inputs">
      <label>Budget minimum (€)</label>
      <input type="number" id="budget-min" min="0" step="0.5" value="" placeholder="Ex: 5">
      
      <label>Budget maximum (€)</label>
      <input type="number" id="budget-max" min="0" step="0.5" value="" placeholder="Ex: 20">
    </div>
    
    <button class="btn-surprise" onclick="getSurprise()">
      🎲 Surprenez-moi !
    </button>
  </div>
  
  <div id="surprise-results" style="display: none;">
    <h4>🎉 Voici ta sélection surprise !</h4>
    <div id="surprise-plats"></div>
    <div class="surprise-total">
      <strong>Total estimé :</strong> <span id="surprise-total-amount">0.00</span> €
    </div>
    <button class="btn-add-cart" onclick="addSurpriseToCart()">
      🛒 Ajouter tout au panier
    </button>
  </div>
  <script src="assets/surprise_plats.js"></script>
  </div>

  <!-- section dernières commandes -->
  <?php if (!empty($dernieresCommandes)): ?>
  <div class="dernieres-commandes-section">
    <h3>📦 Tes dernières commandes</h3>
  
    <div class="commandes-grid">
    <?php foreach ($dernieresCommandes as $cmd): ?>
      <?php
      // Récupérer les articles de cette commande
      $stmtArticles = $conn->prepare("
        SELECT cp.quantite, pl.nom_plat, pl.prix, r.nom_restaurant
        FROM commande_plats cp
        JOIN plats pl ON cp.plat_id = pl.plat_id
        JOIN restaurants r ON pl.restaurant_id = r.restaurant_id
        WHERE cp.commande_id = ?
        LIMIT 5
      ");
      $stmtArticles->execute([$cmd['commande_id']]);
      $articles = $stmtArticles->fetchAll();
      ?>
      
      <div class="commande-card">
        <div class="commande-header">
          <div class="commande-numero-statut">
            <span class="commande-numero">Commande #<?= $cmd['numero_utilisateur'] ?></span>
            <span class="commande-statut statut-<?= $cmd['statut'] ?>">
              <?php
              $statuts = [
                'en_attente' => '⏳ En attente',
                'en_preparation' => '👨‍🍳 En préparation',
                'en_livraison' => '🚚 En livraison',
                'livree' => '✅ Livrée',
                'annulee' => '❌ Annulée'
              ];
              echo $statuts[$cmd['statut']] ?? $cmd['statut'];
              ?>
            </span>
          </div>
          <div class="commande-montant">
            <?php if ($cmd['montant_reduction'] > 0): ?>
              <span class="montant-barre"><?= number_format($cmd['montant_total'] + $cmd['montant_reduction'], 2) ?> €</span>
              <span class="montant-reduit"><?= number_format($cmd['montant_total'], 2) ?> €</span>
            <?php else: ?>
              <span class="montant-total"><?= number_format($cmd['montant_total'], 2) ?> €</span>
            <?php endif; ?>
          </div>
        </div>
        
        <div class="commande-articles-liste">
          <?php foreach ($articles as $article): ?>
            <div class="article-item">
              <span class="article-quantite">×<?= $article['quantite'] ?></span>
              <span class="article-nom"><?= htmlspecialchars($article['nom_plat']) ?></span>
              <span class="article-resto">(<?= htmlspecialchars($article['nom_restaurant']) ?>)</span>
            </div>
          <?php endforeach; ?>
          <?php if ($cmd['nb_articles'] > 5): ?>
            <div class="article-item article-more">
              ... et <?= $cmd['nb_articles'] - 5 ?> autre<?= ($cmd['nb_articles'] - 5) > 1 ? 's' : '' ?> article<?= ($cmd['nb_articles'] - 5) > 1 ? 's' : '' ?>
            </div>
          <?php endif; ?>
        </div>
        
        <div class="commande-footer" style="margin-top:1rem;">
          <span class="commande-date">
            🕒 <?= date('d/m/y H:i', strtotime($cmd['date_commande'])) ?>
          </span>
          <div class="commande-actions">
            <a href="suivi_commande.php?commande_id=<?= $cmd['commande_id'] ?>" class="btn btn-commande">
              Détails
            </a>
            <?php if ($cmd['statut'] === 'livree'): ?>
              <a href="reorder_command.php?commande_id=<?= $cmd['commande_id'] ?>" class="btn btn-commande">
                Recommander
              </a>
            <?php endif; ?>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
  
  <a href="suivi_commande.php" class="voir-toutes-commandes">Voir toutes mes commandes →</a>
</div>
<?php endif; ?>
    <!--recommandations de restaus -->
    <?php if (!empty($recommendedRestaurants)): ?>
    <div class="recommendations-section">
      <h3>Restaurants recommandés</h3>
      <div class="restaurants-slider">
        <div class="restaurants-track" id="restaurantsTrack">
          <?php foreach ($recommendedRestaurants as $resto): ?>
            <div class="restaurant-card">
              <div class="restaurant-header">
                <h4><?= htmlspecialchars($resto['nom_restaurant']) ?></h4>
                <div class="restaurant-rating">
                  <?php if ($resto['note_moyenne']): ?>
                    <div class="stars">
                      <?php for ($i = 1; $i <= 5; $i++): ?>
                        <span class="star <?= $i <= round($resto['note_moyenne']) ? 'filled' : '' ?>">★</span>
                      <?php endfor; ?>
                      <span class="rating-text"><?= number_format($resto['note_moyenne'], 1) ?> (<?= $resto['nb_avis'] ?> avis)</span>
                    </div>
                  <?php else: ?>
                    <span class="no-rating">Nouveau restaurant</span>
                  <?php endif; ?>
                </div>
              </div>
              <p class="restaurant-category"><?= htmlspecialchars($resto['categorie']) ?></p>
              <p class="restaurant-description"><?= htmlspecialchars(substr($resto['description_resto'], 0, 100)) ?>...</p>
              <p class="restaurant-address">📍 <?= htmlspecialchars($resto['adresse']) ?></p>
              <a href="menu.php?restaurant_id=<?= $resto['restaurant_id'] ?>" class="restaurant-btn">Voir le menu</a>
            </div>
          <?php endforeach; ?>
        </div>
        <div class="restaurants-controls">
          <button class="restaurants-btn prev" onclick="previousRestaurant()">‹</button>
          <button class="restaurants-btn next" onclick="nextRestaurant()">›</button>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- mmur  retours positifs -->
    <?php if (!empty($topReviews)): ?>
    <div class="feedback-wall">
      <h3>Retours positifs de la communauté</h3>
      <div class="reviews-slider">
        <div class="reviews-track" id="reviewsTrack">
          <?php foreach ($topReviews as $review): ?>
            <div class="review-card">
              <div class="review-header">
                <div class="reviewer-info">
                  <strong><?= htmlspecialchars($review['nom_user']) ?></strong>
                  <div class="stars">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                      <span class="star <?= $i <= $review['note'] ? 'filled' : '' ?>">★</span>
                    <?php endfor; ?>
                  </div>
                </div>
                <div class="restaurant-name">
                  <a href="menu.php?restaurant_id=<?= $review['restaurant_id'] ?>">
                    <?= htmlspecialchars($review['nom_restaurant']) ?>
                  </a>
                </div>
              </div>
              <div class="review-content">
                <p>"<?= htmlspecialchars($review['commentaire']) ?>"</p>
              </div>
              <div class="review-footer">
                <div class="review-stats">
                  <span class="likes">👍 <?= $review['likes'] ?></span>
                  <span class="date"><?= date('d/m/Y', strtotime($review['date_avis'])) ?></span>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <?php

// Récupérer les annonces actives
$stmt = $conn->prepare("
  SELECT * FROM annonces 
  WHERE actif = 1 
    AND date_debut <= NOW() 
    AND date_fin >= NOW()
  ORDER BY date_debut DESC
");
$stmt->execute();
$annonces = $stmt->fetchAll();
?>

<!-- CSS pour les annonces -->
<style>
.annonces-section {
  margin: 2rem 0;
  padding: 1.5rem;
  background: rgba(255, 255, 255, 0.15);
  backdrop-filter: blur(15px);
  border-radius: 1.5rem;
  box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
  border: 1px solid rgba(255, 255, 255, 0.2);
  overflow: hidden;
}

.annonces-section h3 {
  text-align: center;
  color: #ff6b6b;
  margin-bottom: 1rem;
  margin-top: 0;
  font-size: 1.5rem;
  text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.3);
}

.annonces-ticker {
  position: relative;
  overflow: hidden;
  border-radius: 1rem;
  background: rgba(255, 255, 255, 0.1);
  padding: 1rem;
}

.annonces-track {
  display: flex;
  animation: scroll 30s linear infinite;
  gap: 1.5rem;
}

.annonces-track:hover {
  animation-play-state: paused;
}

.annonce-card {
  min-width: 100%;
  background: rgba(255, 255, 255, 0.2);
  backdrop-filter: blur(10px);
  border-radius: 0.8rem;
  padding: 1.5rem;
  box-shadow: 0 3px 12px rgba(0, 0, 0, 0.1);
  border: 1px solid rgba(255, 255, 255, 0.3);
  transition: all 0.3s ease;
  animation: fadeIn 0.6s ease-out;
  flex-shrink: 0;
}

.annonce-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
  background: rgba(255, 255, 255, 0.25);
}

.annonce-card strong {
  color: #ff6b6b;
  font-size: 1.2rem;
  display: block;
  margin-bottom: 0.5rem;
}

.annonce-card p {
  color: #444;
  line-height: 1.4;
  margin: 0;
}

@keyframes scroll {
  0% {
    transform: translateX(0);
  }
  100% {
    transform: translateX(calc(-100% - 1.5rem));
  }
}

@keyframes fadeIn {
  from {
    opacity: 0;
    transform: translateX(20px);
  }
  to {
    opacity: 1;
    transform: translateX(0);
  }
}
</style>

</main>
<?php
// Récupérer les annonces actives
$stmt = $conn->prepare("
  SELECT * FROM annonces 
  WHERE actif = 1 
    AND date_debut <= NOW() 
    AND date_fin >= NOW()
  ORDER BY date_debut DESC
  LIMIT 5
");
$stmt->execute();
$annonces = $stmt->fetchAll();
?>

<?php if (!empty($annonces)): ?>
<div class="annonces-sidebar" id="annonces-sidebar">
  <div class="annonces-sidebar-header">
    <h3>📢 Annonces</h3>
  </div>
  <div class="annonces-sidebar-content">
    <?php foreach ($annonces as $annonce): ?>
      <div class="annonce-item">
        <h4><?= htmlspecialchars($annonce['titre']) ?></h4>
        <p class="annonce-text"><?= htmlspecialchars($annonce['message']) ?></p>
        <small class="annonce-date">
          Jusqu'au <?= date('d/m/Y', strtotime($annonce['date_fin'])) ?>
        </small>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<style>

</style>

<!-- btn flottant pour rouvrir la sidebar d'annonces -->
<?php if (!empty($annonces)): ?>
<button id="toggle-annonces" class="annonces-toggle-btn">
    📢
    <span class="annonces-count"><?= count($annonces) ?></span>
</button>
<?php endif; ?>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const sidebar = document.getElementById('annonces-sidebar');
  const toggleBtn = document.getElementById('toggle-annonces');
  const sidebarHeader = document.querySelector('.annonces-sidebar-header');
  
  if (!sidebar || !toggleBtn) return; // en sah il avait raison le mec de la vidéo, ca ressemble bcp au C sharp
  
  // État initial fermé
  sidebar.classList.remove('open');
  
  // Toggle au clic du bouton
  toggleBtn.addEventListener('click', (e) => {
    e.stopPropagation();
    sidebar.classList.toggle('open');
    localStorage.setItem('annonces-sidebar-open', sidebar.classList.contains('open'));
  });
  
  // Fermer en cliquant sur le header
  if (sidebarHeader) {
    sidebarHeader.addEventListener('click', (e) => {
      e.stopPropagation();
      sidebar.classList.remove('open');
      localStorage.setItem('annonces-sidebar-open', 'false');
    });
  }
  
  //fermer en cliquant en dehors
  document.addEventListener('click', (e) => {
    if (sidebar.classList.contains('open') && 
        !sidebar.contains(e.target) && 
        !toggleBtn.contains(e.target)) {
      sidebar.classList.remove('open');
      localStorage.setItem('annonces-sidebar-open', 'false');
    }
  });
  
  //fermer avec Esc
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && sidebar.classList.contains('open')) {
      sidebar.classList.remove('open');
      localStorage.setItem('annonces-sidebar-open', 'false');
    }
  });
});
</script>

<style>
.container {
  backdrop-filter: blur(15px);
  background: rgba(255, 255, 255, 0.25); 
  padding: 2rem;
  border-radius: 1.5rem;
}

.btn {
  display: inline-flex;
  justify-content: center;
  align-items: center;  
  padding: 1rem 1.8rem;
  margin: 0.4rem;
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
  position: relative;
  overflow: hidden;
  text-align: center;
}

.btn::after {
  content: "";
  position: absolute;
  top: 0; left: -100%;
  width: 100%;
  height: 100%;
  background: rgba(255,255,255,0.25);
  transition: all 0.4s ease;
  border-radius: 14px;
}

.btn:hover::after {
  left: 0;
}

.btn:hover {
  transform: translateY(-4px) scale(1.03);
  box-shadow: 0 12px 25px rgba(0,0,0,0.25);
}

.btn-cancel {
  background: linear-gradient(135deg, #ff6666, #ff3b3b);
}

.links {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
  gap: 1.2rem;
  margin-top: 2rem;
}

.links .btn {
  font-size: 1.05rem;
  padding: 1.1rem 1.5rem;
  text-align: center;
}

.links .btn:hover {
  transform: translateY(-3px) scale(1.05);
  background: linear-gradient(135deg, #ff8c42, #ff6b6b);
}

.stats-grid {
  display: flex;
  flex-wrap: wrap;
  gap: 1rem;
  margin-top: 2rem;
}

.stat-card {
  background: rgba(255,255,255,0.18);
  backdrop-filter: blur(14px);
  padding: 1.8rem;
  border-radius: 1.3rem;
  flex: 1 1 200px;
  text-align: center;
  box-shadow: 0 6px 15px rgba(0,0,0,0.12);
  transition: transform 0.25s, box-shadow 0.25s;
  position: relative;
  overflow: hidden;
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: center;
}


.stat-card:hover { 
  transform: translateY(-5px) scale(1.03); 
  box-shadow: 0 12px 28px rgba(0,0,0,0.2);
}

.stat-card h3 {
  font-size: 1.3rem;
  margin-bottom: 0.5rem;
  color: #ff6b6b;
}

.stat-card p {
  font-size: 2.5rem;
  font-weight: 700;
  color: #333;
  text-shadow: 1px 1px 3px rgba(0,0,0,0.2);
}


.stat-card::before {
  content: "";
  position: absolute;
  top: -50%; left: -50%;
  width: 200%; height: 200%;
  background: radial-gradient(circle, rgba(255,255,255,0.2) 0%, transparent 70%);
  transform: rotate(45deg);
  pointer-events: none;
  transition: all 0.5s ease;
}

.stat-card:hover::before {
  top: -20%; left: -20%;
}

/* ssection recommandation RESTO */

.recommendations-section {
  margin: 2rem 0;
  padding: 1.5rem;
  background: rgba(255, 255, 255, 0.15);
  backdrop-filter: blur(15px);
  border-radius: 1.5rem;
  box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
  border: 1px solid rgba(255, 255, 255, 0.2);
}

.recommendations-section h3 {
  text-align: center;
  color: #ff6b6b;
  margin-bottom: 0.7rem;
  margin-top: -0.3rem;
  font-size: 1.5rem;
  text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.3);
}

.restaurants-slider {
  position: relative;
  overflow: hidden;
  border-radius: 1rem;
  background: rgba(255, 255, 255, 0.1);
  padding: 1rem;
  margin-top: 1rem;
  width: 100%;
}

.restaurants-track {
  display: flex;
  transition: transform 0.6s cubic-bezier(0.4, 0, 0.2, 1);
  gap: 1rem;
  width: fit-content;
}

.restaurants-controls {
  position: absolute;
  top: 50%;
  left: 0;
  right: 0;
  transform: translateY(-50%);
  display: flex;
  justify-content: space-between;
  pointer-events: none;
  padding: 0 1rem;
  z-index: 10;
}

.restaurants-btn {
  background: rgba(255, 107, 107, 0.7);
  color: white;
  border: none;
  border-radius: 50%;
  width: 40px;
  height: 40px;
  font-size: 1.2rem;
  cursor: pointer;
  transition: all 0.3s ease;
  pointer-events: all;
  box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
  backdrop-filter: blur(15px);
  display: flex;
  align-items: center;
  justify-content: center;
  position: absolute;
  z-index: 20;
}

.restaurants-btn.prev {
  left: 1px;
  top: 50%;
  transform: translateY(-50%);
}

.restaurants-btn.next {
  right: 3px;
  top: 50%;
  transform: translateY(-50%);
}

.restaurants-btn:hover {
  background: rgba(255, 107, 107, 0.9);
  transform: translateY(-50%) scale(1.1);
  box-shadow: 0 6px 20px rgba(0, 0, 0, 0.4);
}

.restaurants-btn:active {
  transform: translateY(-50%) scale(0.95);
}

.restaurant-card {
  width: 37.01%;
  background: rgba(255, 255, 255, 0.2);
  backdrop-filter: blur(10px);
  border-radius: 1rem;
  padding: 1.6rem;
  box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
  border: 1px solid rgba(255, 255, 255, 0.3);
  transition: all 0.3s ease;
  animation: slideIn 0.6s ease-out;
  margin: 0;
  flex-shrink: 0;
}

.restaurant-card:hover {
  transform: translateY(-5px);
  box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
  background: rgba(255, 255, 255, 0.25);
}

.restaurant-header h4 {
  color: #333;
  margin: 0 0 0.5rem 0;
  font-size: 1.2rem;
}

.restaurant-rating {
  margin-bottom: 0.8rem;
}

.restaurant-rating .stars {
  display: flex;
  gap: 2px;
  margin-bottom: 0.3rem;
}

.restaurant-rating .star {
  color: #ddd;
  font-size: 1rem;
}

.restaurant-rating .star.filled {
  color: #ffd700;
}

.rating-text {
  font-size: 0.9rem;
  color: #666;
  font-weight: 600;
}

.no-rating {
  font-size: 0.9rem;
  color: #ff6b6b;
  font-weight: 600;
  font-style: italic;
}

.restaurant-category {
  color: #ff6b6b;
  font-weight: 600;
  margin: 0.5rem 0;
  font-size: 0.9rem;
}

.restaurant-description {
  color: #555;
  line-height: 1.4;
  margin: 0.8rem 0;
  font-size: 0.95rem;
}

.restaurant-address {
  color: #666;
  font-size: 0.9rem;
  margin: 0.5rem 0 1rem 0;
}

.restaurant-btn {
  display: inline-block;
  background: linear-gradient(135deg, #ff6b6b, #ff8c42);
  color: white;
  text-decoration: none;
  padding: 0.7rem 1.2rem;
  border-radius: 0.8rem;
  font-weight: 600;
  transition: all 0.3s ease;
  box-shadow: 0 4px 15px rgba(255, 107, 107, 0.3);
}

.restaurant-btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(255, 107, 107, 0.4);
  background: linear-gradient(135deg, #ff8c42, #ff6b6b);
}


/*mur retours positifs */
.feedback-wall {
  margin: 2rem 0;
  padding: 1.5rem;
  background: rgba(255, 255, 255, 0.15);
  backdrop-filter: blur(15px);
  border-radius: 1.5rem;
  box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
  border: 1px solid rgba(255, 255, 255, 0.2);
}

.feedback-wall h3 {
  text-align: center;
  color: #ff6b6b;
  margin-top: -0rem;
  font-size: 1.8rem;
  text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.3);
}

.reviews-slider {
  position: relative;
  overflow: hidden;
  border-radius: 1rem;
  background: rgba(255, 255, 255, 0.1);
  padding: 1rem;
  max-width: 100%;
  width: 100%;
}

.reviews-track {
  display: flex;
  transition: transform 0.6s cubic-bezier(0.4, 0, 0.2, 1);
  gap: 1rem;
  max-width: 100%;
  width: fit-content;
}

.review-card {
  width: 100%;
  min-width: 100%;
  max-width: 100%;
  background: rgba(255, 255, 255, 0.2);
  backdrop-filter: blur(10px);
  border-radius: 0.8rem;
  padding: 2rem;
  box-shadow: 0 3px 12px rgba(0, 0, 0, 0.1);
  border: 1px solid rgba(255, 255, 255, 0.3);
  transition: all 0.3s ease;
  animation: slideIn 0.6s ease-out;
  margin: auto 0 auto;
  flex-shrink: 0;
}

.review-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
  background: rgba(255, 255, 255, 0.25);
}

.review-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 0.8rem;
  flex-wrap: wrap;
  gap: 0.4rem;
}

.reviewer-info strong {
  color: #333;
  font-size: 1rem;
  display: block;
  margin-bottom: 0.2rem;
}

.reviewer-info .stars {
  display: flex;
  gap: 1px;
}

.reviewer-info .star {
  color: #ddd;
  font-size: 1rem;
  transition: color 0.2s ease;
}

.reviewer-info .star.filled {
  color: #ffd700;
  text-shadow: 0 0 2px rgba(255, 215, 0, 0.5);
}

.restaurant-name a {
  color: #ff6b6b;
  text-decoration: none;
  font-weight: 600;
  font-size: 0.85rem;
  padding: 0.2rem 0.6rem;
  background: rgba(255, 107, 107, 0.1);
  border-radius: 0.4rem;
  transition: all 0.3s ease;
}

.restaurant-name a:hover {
  background: rgba(255, 107, 107, 0.2);
  transform: scale(1.05);
}

.review-content {
  margin-bottom: 0.8rem;
}

.review-content p {
  color: #444;
  font-style: italic;
  line-height: 1.4;
  margin: 0;
  font-size: 0.9rem;
}

.review-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 0.8rem;
}

.review-stats {
  display: flex;
  gap: 0.8rem;
  align-items: center;
}

.likes {
  color: #4CAF50;
  font-weight: 600;
}

.date {
  color: #666;
}

.slider-controls {
  position: absolute;
  top: 50%;
  left: 0;
  right: 0;
  transform: translateY(-50%);
  display: flex;
  justify-content: space-between;
  pointer-events: none;
  padding: 0 1rem;
  z-index: 10;
}

.btn-add-cart {
  width: 100%;
  justify-content: center;
  align-items: center;  
  font-size: 0.85rem;
  font-weight: 600;
  color: #fff;
  background: linear-gradient(135deg, #ff6b6b, #ffc342ff);
  border: none;
  border-radius: 14px;
  cursor: pointer;
  text-decoration: none;
  box-shadow: 0 6px 18px rgba(0,0,0,0.18);
  transition: all 0.35s ease;
  position: relative;
  overflow: hidden;
  text-align: center;
  align-content: center;
}

.btn-add-cart::after {
  content: "";
  position: absolute;
  top: 0; left: -100%;
  width: 100%;
  height: 100%;
  background: rgba(255,255,255,0.25);
  transition: all 0.4s ease;
  border-radius: 14px;
}

.btn-add-cart:hover::after {
  left: 0;
}

.btn-add-cart:hover {
  transform: translateY(-3px) scale(1.02);
  box-shadow: 0 12px 25px rgba(0,0,0,0.25);
  background: linear-gradient(135deg, #ffc342ff, #ff6b6b);
}

@keyframes slideIn {
  from {
    opacity: 0;
    transform: translateX(20px);
  }
  to {
    opacity: 1;
    transform: translateX(0);
  }
}

/* Messages flash */
/* Messages flash */
.flash-message {
  position: fixed !important;
  top: 20px !important;
  left: 50% !important;
  transform: translateX(-50%) !important;
  background: rgba(255, 255, 255, 0.2);
  backdrop-filter: blur(7px);
  color: #727272;
  padding: 12px 20px;
  border-radius: 12px;
  font-family: 'HSR', sans-serif;
  box-shadow: 0 4px 20px rgba(0,0,0,0.3);
  z-index: 10000 !important;
  text-align: center;
  min-width: 300px;
  max-width: 90%;
  animation: fadeInMessage 0.3s ease;
} 

.flash-message.success { 
  border: 2px solid #4CAF50;
  color: #2e7d32;
}

.flash-message.error { 
  border: 1.5px solid #ff6b6b;
  color: #c62828;
}

.flash-message.hide { 
  opacity: 0; 
  transform: translateX(-50%) translateY(-10px); 
}

@keyframes fadeInMessage {
  from { opacity: 0; transform: translateX(-50%) translateY(-10px); }
  to { opacity: 1; transform: translateX(-50%) translateY(0); }
}
/* classes diff pour correspondre au js si jamais*/
.flash-msg {
  position: fixed !important;
  top: 20px !important;
  left: 50% !important;
  transform: translateX(-50%) !important;
  background: rgba(255, 255, 255, 0.2);
  backdrop-filter: blur(7px);
  color: #727272;
  padding: 12px 20px;
  border-radius: 12px;
  font-family: 'HSR', sans-serif;
  box-shadow: 0 4px 20px rgba(0,0,0,0.3);
  z-index: 10000 !important;
  text-align: center;
  min-width: 300px;
  max-width: 90%;
  animation: fadeInMessage 0.3s ease;
}

.flash-msg.success { 
  border: 2px solid #4CAF50;
  color: #2e7d32;
}

.flash-msg.error { 
  border: 1.5px solid #ff6b6b;
  color: #c62828;
}

.flash-msg.hide { 
  opacity: 0; 
  transform: translateX(-50%) translateY(-10px); 
  transition: all 0.4s;
}
</style>
<style>

/* Section dernières commandes */


.dernieres-commandes-section {
  margin: 2rem 0;
  padding: 1.5rem;
  background: rgba(255, 255, 255, 0.15);
  backdrop-filter: blur(15px);
  border-radius: 1.5rem;
  box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
  border: 2px solid rgba(255, 255, 255, 0.25);
  max-width: 100%;
  overflow: hidden;
}

.dernieres-commandes-section h3 {
  text-align: center;
  color: #ff6b6b;
  margin-bottom: 1.5rem;
  margin-top: 0;
  font-size: 1.5rem;
  text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.3);
}

/* grille affichage honzinzontale qui s'adapte au nbr  de commanes (3 max) */
.commandes-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 1rem;
  margin-bottom: 1rem;
  width: 100%;
}

/* CARTE COMMANDE - HAUTEUR FLEXIBLE */
.commande-card {
  display: flex;
  flex-direction: column;
  background: rgba(255, 255, 255, 0.2);
  backdrop-filter: blur(10px);
  border-radius: 0.8rem;
  padding: 1rem;
  box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
  border: 1px solid rgba(255, 255, 255, 0.3);
  transition: all 0.3s ease;
  min-height: fit-content;
  min-width: 0;
  overflow: hidden;
}

.commande-card:hover {
  transform: translateY(-3px);
  box-shadow: 0 6px 20px rgba(0, 0, 0, 0.2);
  background: rgba(255, 255, 255, 0.3);
}

/* HEADER DE LA COMMANDE */
.commande-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 0.8rem;
  padding-bottom: 0.6rem;
  border-bottom: 2px solid rgba(255, 107, 107, 0.3);
  flex-shrink: 0;
  gap: 0.5rem;
}

.commande-numero-statut {
  display: flex;
  flex-direction: column;
  gap: 0.4rem;
  min-width: 0;
  flex: 1;
}

.commande-numero {
  font-weight: 700;
  font-size: 0.95rem;
  color: #333;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.commande-statut {
  padding: 4px 8px;
  border-radius: 12px;
  font-size: 0.7rem;
  font-weight: 600;
  width: fit-content;
  white-space: nowrap;
}

.statut-en_attente {
  background: rgba(255, 193, 7, 0.25);
  color: #856404;
}

.statut-en_preparation {
  background: rgba(33, 150, 243, 0.25);
  color: #1565c0;
}

.statut-en_livraison {
  background: rgba(156, 39, 176, 0.25);
  color: #6a1b9a;
}

.statut-livree {
  background: rgba(76, 175, 80, 0.25);
  color: #2e7d32;
}

.statut-annulee {
  background: rgba(244, 67, 54, 0.25);
  color: #c62828;
}

.commande-montant {
  text-align: right;
  font-size: 1.1rem;
  font-weight: 700;
  flex-shrink: 0;
}

.montant-total {
  color: #ff6b6b;
}

.montant-barre {
  display: block;
  text-decoration: line-through;
  color: #999;
  font-size: 0.75rem;
  margin-bottom: 0.3rem;
}

.montant-reduit {
  color: #4CAF50;
  display: block;
}

/* LISTE DES ARTICLES - S'ADAPTE À LA HAUTEUR DU CONTENU */
.commande-articles-liste {
  flex: 0 1 auto;
  margin: 0.8rem 0;
  padding: 0.8rem;
  background: rgba(255, 255, 255, 0.15);
  border-radius: 0.8rem;
  border-left: 4px solid #ff6b6b;
  overflow-y: auto;
  overflow-x: hidden;
  min-height: 0;
  max-height: 180px;
  padding-bottom: 10px;
}

.article-item {
  display: flex;
  gap: 0.4rem;
  align-items: baseline;
  padding: 0.3rem 0;
  color: #333;
  font-size: 0.8rem;
  line-height: 1.4;
  min-width: 0;
}

.article-quantite {
  font-weight: 700;
  color: #ff6b6b;
  min-width: 22px;
  font-size: 0.75rem;
  flex-shrink: 0;
}

.article-nom {
  font-weight: 600;
  flex: 1;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  min-width: 0;
}

.article-resto {
  color: #666;
  font-size: 0.7rem;
  font-style: italic;
  white-space: nowrap;
  flex-shrink: 0;
}

.article-more {
  color: #666;
  font-style: italic;
  padding-left: 22px;
  font-size: 0.75rem;
}

/* footdeurue extremeeee */
.commande-footer {
  display: flex;
  flex-direction: column;
  gap: 0.8rem;
  margin-top: auto;
  padding-top: 0.8rem;
  border-top: 2px solid rgba(255, 107, 107, 0.2);
  flex-shrink: 0;
}

.commande-date {
  color: #666;
  font-size: 0.7rem;
  text-align: center;
  font-weight: 500;
}

/* BOUTONS ACTIONS - STYLE ORANGE COHÉRENT */
.commande-actions {
  display: flex;
  gap: 0.5rem;
  justify-content: center;
}

.btn-commande {
  flex: 1;
  display: inline-flex;
  justify-content: center;
  align-items: center;
  padding: 0.6rem 0.8rem;
  font-size: 0.8rem;
  font-weight: 600;
  color: #fff;
  background: linear-gradient(135deg, #ff6b6b, #ffc342);
  border: none;
  border-radius: 12px;
  cursor: pointer;
  text-decoration: none;
  box-shadow: 0 4px 15px rgba(255, 107, 107, 0.3);
  transition: all 0.35s ease;
  position: relative;
  overflow: hidden;
  text-align: center;
  font-family: 'HSR', sans-serif;
  white-space: nowrap;
}

.btn-commande::after {
  content: "";
  position: absolute;
  top: 0;
  left: -100%;
  width: 100%;
  height: 100%;
  background: rgba(255, 255, 255, 0.25);
  transition: all 0.4s ease;
  border-radius: 12px;
}

.btn-commande:hover::after {
  left: 0;
}

.btn-commande:hover {
  transform: translateY(-3px) scale(1.03);
  box-shadow: 0 6px 20px rgba(255, 107, 107, 0.5);
  background: linear-gradient(135deg, #ff8c42, #ff6b6b);
}

.btn-commande:active {
  transform: translateY(-1px) scale(1.01);
}

/* LIEN "VOIR TOUTES" */
.voir-toutes-commandes {
  display: block;
  text-align: center;
  margin-top: 1rem;
  color: #ff6b6b;
  text-decoration: none;
  font-weight: 600;
  transition: all 0.3s ease;
  font-size: 1rem;
}

.voir-toutes-commandes:hover {
  color: #ff8c42;
  transform: scale(1.05);
}

/* SCROLLBAR PERSONNALISÉE */
.commande-articles-liste::-webkit-scrollbar {
  width: 4px;
}

.commande-articles-liste::-webkit-scrollbar-track {
  background: rgba(255, 255, 255, 0.1);
  border-radius: 10px;
}

.commande-articles-liste::-webkit-scrollbar-thumb {
  background: rgba(255, 107, 107, 0.6);
  border-radius: 10px;
}

.commande-articles-liste::-webkit-scrollbar-thumb:hover {
  background: rgba(255, 107, 107, 0.8);
}

/* RESPONSIVE */
@media (max-width: 1024px) {
  .commandes-grid {
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  }
}

@media (max-width: 768px) {
  .commandes-grid {
    grid-template-columns: 1fr;
  }
  
  .commande-card {
    
    min-height: 280px;
  }
}
</style>

<!-- animations sur les stats compteur stylé -->
<script>
document.addEventListener("DOMContentLoaded", () => {
  const counters = document.querySelectorAll(".stats-grid p");
  
  counters.forEach(counter => {
    const target = +counter.textContent;
    counter.textContent = 0;

    if(target > 0) {
      //durée fixe de 1,5 secondes pour tous les compteurs
      const duration = 1500; // 1.5 secondes
      
      //calcul nbr étape nécésaire
      //petits nombres : plus d'étapes (animation plus fluide)
      //grands nombres : moins d'étapes (plus rapide)
      let steps;
      if (target <= 100) {
        steps = target; // Une étape par unité pour les petits nombres
      } else if (target <= 1000) {
        steps = 100; // 100 étapes pour les nombres moyens
      } else {
        steps = 500; // 50 étapes pour les grands nombres
      }
      
      //calculer l'incrément et le temps entre chaque étape
      const increment = target / steps;
      const stepTime = duration / steps;
      
      let current = 0;
      
      const updateCount = () => {
        current += increment;
        
        if(current < target) {
          counter.textContent = Math.floor(current);
          setTimeout(updateCount, stepTime);
        } else {
          counter.textContent = target; //valeur finale exacte
        }
      };
      
      updateCount();
    } else {
      counter.textContent = 0;
    }
  });
});
</script>

<!--script  mur     retours positifs -->
<script>
let currentReviewIndex = 0;
let totalReviews = 0;
let autoSlideInterval;

document.addEventListener("DOMContentLoaded", () => {
  const reviewsTrack = document.getElementById('reviewsTrack');
  if (!reviewsTrack) return;
  
  const reviewCards = reviewsTrack.querySelectorAll('.review-card');
  totalReviews = reviewCards.length;
  
  if (totalReviews === 0) return;
  
  //positiondedepart frr
  updateSliderPosition();
  
  //démarrer le slide automatique
  startAutoSlide();
  
  //pause au survol
  const slider = document.querySelector('.reviews-slider');
  if (slider) {
    slider.addEventListener('mouseenter', stopAutoSlide);
    slider.addEventListener('mouseleave', startAutoSlide);
  }
});
// merci à sliderJS pour le code source
function updateSliderPosition() {
  const reviewsTrack = document.getElementById('reviewsTrack');
  if (!reviewsTrack) return;
  
  const cardWidth = reviewsTrack.querySelector('.review-card').offsetWidth;
  const gap = 16; 
  const translateX = -currentReviewIndex * (cardWidth + gap);
  reviewsTrack.style.transform = `translateX(${translateX}px)`;
}

function nextReview() {
  currentReviewIndex = (currentReviewIndex + 1) % totalReviews;
  updateSliderPosition();
  
  playNavigationSound();
}

function previousReview() {
  currentReviewIndex = (currentReviewIndex - 1 + totalReviews) % totalReviews;
  updateSliderPosition();
  
  playNavigationSound();
}

function startAutoSlide() {
  stopAutoSlide(); // S'assure que y a pas de doublons
  autoSlideInterval = setInterval(() => {
    nextReview();
  }, 4000); // Change d'avis toutes les 4 secondes (mais nan jte jure j'aime bien les pommes)
  //ah bah nan enft
}

function stopAutoSlide() {
  if (autoSlideInterval) {
    clearInterval(autoSlideInterval);
    autoSlideInterval = null;
  }
}

function playNavigationSound() {
  //son
  try {
    const audio = new Audio('assets/00240 - WAV_240_GUESS_BANK_MEN.wav');
    audio.volume = 0.3;
    audio.play().catch(() => {}); //ignorant erreurs si le son ne peut pas être joué
  } catch (e) {
    //ignore erreurs de son
  }
}

//exposer les fonctions globalement pour les boutons
window.nextReview = nextReview;
window.previousReview = previousReview;

// =
//slider restau
let currentRestaurantIndex = 0;
let totalRestaurants = 0;
let autoRestaurantInterval;

document.addEventListener("DOMContentLoaded", () => {
  const restaurantsTrack = document.getElementById('restaurantsTrack');
  if (!restaurantsTrack) return;
  
  const restaurantCards = restaurantsTrack.querySelectorAll('.restaurant-card');
  totalRestaurants = restaurantCards.length;
  
  if (totalRestaurants === 0) return;
  
  // position
  updateRestaurantPosition();
  
  //slide autom comme pour les comms
  startAutoRestaurantSlide();
  
  // Pause au survol
  const restaurantsSlider = document.querySelector('.restaurants-slider');
  if (restaurantsSlider) {
    restaurantsSlider.addEventListener('mouseenter', stopAutoRestaurantSlide);
    restaurantsSlider.addEventListener('mouseleave', startAutoRestaurantSlide);
  }
});

function updateRestaurantPosition() {
  const restaurantsTrack = document.getElementById('restaurantsTrack');
  if (!restaurantsTrack) return;
  
  const cardWidth = restaurantsTrack.querySelector('.restaurant-card').offsetWidth;
  const gap = 16;
  //pour les restaur, on avance de 2 cartes à la fois
  const cardsPerSlide = 2;
  const translateX = -currentRestaurantIndex * cardsPerSlide * (cardWidth + gap);
  restaurantsTrack.style.transform = `translateX(${translateX}px)`;
}

function nextRestaurant() {
  const maxIndex = Math.ceil(totalRestaurants / 2) - 1;
  currentRestaurantIndex = (currentRestaurantIndex + 1) % Math.max(1, maxIndex + 1);
  updateRestaurantPosition();
  
  playNavigationSound();
}

function previousRestaurant() {
  const maxIndex = Math.ceil(totalRestaurants / 2) - 1;
  currentRestaurantIndex = (currentRestaurantIndex - 1 + Math.max(1, maxIndex + 1)) % Math.max(1, maxIndex + 1);
  updateRestaurantPosition();
  
  playNavigationSound();
}

function startAutoRestaurantSlide() {
  stopAutoRestaurantSlide(); // s'assure qu'il n'y a pas de doublons
  autoRestaurantInterval = setInterval(() => {
    nextRestaurant();
  }, 5000); //change toutes les 5 secoudes
}

function stopAutoRestaurantSlide() {
  if (autoRestaurantInterval) {
    clearInterval(autoRestaurantInterval);
    autoRestaurantInterval = null;
  }
}

// meme chose qu'au dessus
window.nextRestaurant = nextRestaurant;
window.previousRestaurant = previousRestaurant;
</script>


<!-- musique -->
<script>
window.addEventListener('load', function() {
  const playlist = [
    "assets/08. June 2011 (3DS).mp3",
    "assets/14. January 2014 (Wii U).mp3",
    "assets/November 2012 Nintendo eShop Music.mp3",
    "assets/WUWA - Resonators (mp3cut.net) volume boost.flac",
    "assets/Aéroport - Animal Crossing New Horizons OST.mp3",
    "assets/Stranger Than Paradise.mp3"
  ];

  const trackNames = [
    "",
    "",
    "",
    "",
    "",
    ""
  ];

  let current = 0;
  const player = document.getElementById("player");
  const trackNameEl = document.getElementById("track-name");
  const nextBtn = document.getElementById("next-track");
  const prevBtn = document.getElementById("prev-track");

  if (!player || !nextBtn || !prevBtn) {
    console.error('❌ Éléments manquants');
    return;
  }

  function playTrack() {
    player.src = playlist[current];
    player.load();
    
    if (trackNameEl) {
      trackNameEl.textContent = trackNames[current];
    }
    
    player.play().catch(err => {
      console.log('En attente interaction utilisateur');
    });
  }

  // Bouton suivant
  nextBtn.onclick = function() {
    current = (current + 1) % playlist.length;
    playTrack();
  };

  // Bouton précédent
  prevBtn.onclick = function() {
    current = (current - 1 + playlist.length) % playlist.length;
    playTrack();
  };

  // Piste terminée = suivante
  player.onended = function() {
    current = (current + 1) % playlist.length;
    playTrack();
  };

  // Démarrer
  playTrack();
});
</script>
<script src="https://cdn.jsdelivr.net/npm/three@0.149.0/build/three.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/vanta/dist/vanta.waves.min.js"></script>

<script>
VANTA.WAVES({
  el: "body",
  mouseControls: true,
  touchControls: true,
  gyroControls: false,
  minHeight: 1205.00,
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
</body>
</html>