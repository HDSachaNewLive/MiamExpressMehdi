<?php
// forum.php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
require_once 'db/config.php';
require_once __DIR__ . '/csrf_helper.php';
require_once __DIR__ . '/auth_helper.php';

$connected = isset($_SESSION['user_id']);
$uid = $connected ? (int)$_SESSION['user_id'] : 0;
$is_admin = fh_is_admin($conn);

$message = '';
$error = '';

// Création d'un nouveau sujet
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_topic']) && $connected) {
  if (empty($_POST['csrf_token']) || !fh_verify_csrf($_POST['csrf_token'])) {
    $error = 'Jeton CSRF invalide.';
  } else {
    $titre = trim($_POST['titre'] ?? '');
    $contenu = trim($_POST['contenu'] ?? '');
    $categorie = in_array($_POST['categorie'] ?? '', ['restaurants', 'recettes', 'conseils', 'general']) 
           ? $_POST['categorie'] : 'general';
        
    if (empty($titre) || empty($contenu)) {
      $error = "Le titre et le message sont requis.";
    } else {
      try {
        $conn->beginTransaction();
                
        $stmt = $conn->prepare("INSERT INTO forum_topics (user_id, titre, categorie) VALUES (?, ?, ?)");
        $stmt->execute([$uid, $titre, $categorie]);
        $topic_id = $conn->lastInsertId();
                
        $stmt = $conn->prepare("INSERT INTO forum_messages (topic_id, user_id, contenu) VALUES (?, ?, ?)");
        $stmt->execute([$topic_id, $uid, $contenu]);
                
        $conn->prepare("UPDATE forum_topics SET nb_reponses = 1 WHERE topic_id = ?")->execute([$topic_id]);
                
        $conn->commit();
        $message = "✅ Sujet créé avec succès !";
        header("Location: forum_topic.php?topic_id=$topic_id");
        exit;
      } catch (Exception $e) {
        $conn->rollBack();
        $error = "❌ Erreur lors de la création du sujet.";
      }
    }
  }
}

// Récupération des sujets, ft=forum_topic
$sql = "
SELECT ft.*, u.nom_user, ft.auteur_supprime,
       (SELECT COUNT(*) FROM forum_messages WHERE topic_id = ft.topic_id) as nb_messages
FROM forum_topics ft
LEFT JOIN users u ON ft.user_id = u.user_id
ORDER BY ft.epingle DESC, ft.derniere_activite DESC
";

$stmt = $conn->query($sql);
$topics = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Statistiques
$stats = $conn->query("
    SELECT 
        (SELECT COUNT(*) FROM forum_topics) as total_topics,
        (SELECT COUNT(*) FROM forum_messages) as total_messages,
        (SELECT COUNT(DISTINCT user_id) FROM forum_messages) as total_membres
")->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <link rel="icon" type="image/x-icon" href="FoodHubLogo.ico">
  <meta charset="utf-8">
  <title>Forum - FoodHub</title>
  <link rel="stylesheet" href="assets/style.css">
  <link rel="stylesheet" href="assets/forum.css">
  <?php include 'sidebar.php'; ?>
</head>
<body>
  <audio id="player" autoplay loop>
    <source src="https://raw.githubusercontent.com/HDSachaNewLive/foodhub-assets/main/Boutique Nook 2 - Animal Crossing New Horizons OST.mp3" type="audio/mpeg">
  </audio>
  <?php include "slider_son.php"; ?>
  
  <main class="container forum-container">
    <h1>💬 Forum de Discussion</h1>
    
    <div class="forum-stats">
      <div class="stat-item">
        <span class="stat-number"><?= $stats['total_topics'] ?></span>
        <span class="stat-label">Sujets</span>
      </div>
      <div class="stat-item">
        <span class="stat-number"><?= $stats['total_messages'] ?></span>
        <span class="stat-label">Messages</span>
      </div>
      <div class="stat-item">
        <span class="stat-number"><?= $stats['total_membres'] ?></span>
        <span class="stat-label">Membres actifs</span>
      </div>
    </div>
    
    <?php if ($message): ?>
      <div class="success"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    
    <?php if ($error): ?>
      <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    
    <div class="forum-filters">
      <button class="filter-btn active" data-filter="all">
        📋 Tous
      </button>
      <button class="filter-btn" data-filter="restaurants">
        🍽️ Restaurants
      </button>
      <button class="filter-btn" data-filter="recettes">
        👨‍🍳 Recettes
      </button>
      <button class="filter-btn" data-filter="conseils">
        💡 Conseils
      </button>
      <button class="filter-btn" data-filter="general">
        💭 Général
      </button>
    </div>
    
    <?php if ($connected): ?>
      <button class="btn-create-topic" onclick="toggleTopicForm()">
        Créer un nouveau sujet
      </button>
      
      <div id="create-topic-form" style="display: none;">
        <form method="post" class="topic-form">
          <input type="hidden" name="create_topic" value="1">
          <?= fh_csrf_field() ?>
          
          <label>Titre du sujet :</label>
          <input type="text" name="titre" required placeholder="Ex: Meilleur kebab de Paris ?">
          
          <label>Catégorie :</label>
          <select name="categorie" required>
            <option value="general">💭 Général</option>
            <option value="restaurants">🍽️ Restaurants</option>
            <option value="recettes">👨‍🍳 Recettes</option>
            <option value="conseils">💡 Conseils</option>
          </select>
          
          <label>Votre message :</label>
          <textarea name="contenu" rows="6" required placeholder="Décrivez votre question ou sujet..."></textarea>
          
          <div class="form-actions">
            <button type="submit" class="btn">Publier</button>
            <button type="button" class="btn btn-cancel" onclick="toggleTopicForm()">Annuler</button>
          </div>
        </form>
      </div>
    <?php else: ?>
      <p class="info-box">
        ℹ️ <a href="login.php">Connecte-toi</a> pour créer un sujet de discussion.
      </p>
    <?php endif; ?>
    
    <div class="topics-list">
      <?php if (empty($topics)): ?>
        <p class="empty-message">Aucun sujet pour le moment. Sois le premier à en créer un !</p>
      <?php else: ?>
        <?php foreach ($topics as $topic): ?>
          <div class="topic-card" data-categorie="<?= htmlspecialchars($topic['categorie']) ?>">
            <div class="topic-icon">
              <?php
              $icons = [
                'restaurants' => '🍽️',
                'recettes' => '👨‍🍳',
                'conseils' => '💡',
                'general' => '💭'
              ];
              echo $icons[$topic['categorie']] ?? '💭';
              ?>
            </div>
            
            <div class="topic-info">
              <?php if ($topic['epingle']): ?>
                <span class="badge-pinned">📌 Épinglé</span>
              <?php endif; ?>
              
              <?php if ($topic['verrouille']): ?>
                <span class="badge-locked">🔒 Verrouillé</span>
              <?php endif; ?>
              
              <h3>
                <a href="forum_topic.php?topic_id=<?= $topic['topic_id'] ?>">
                  <?= htmlspecialchars($topic['titre']) ?>
                </a>
              </h3>
              
              <div class="topic-meta">
                <span>Par <strong><?= $topic['auteur_supprime'] ? '[Supprimé]' : htmlspecialchars($topic['nom_user'] ?? 'Utilisateur supprimé') ?></strong></span>
                <span>•</span>
                <span><?= date('d/m/Y H:i', strtotime($topic['date_creation'])) ?></span>
              </div>
            </div>
            
            <div class="topic-stats">
              <div class="stat">
                <span class="stat-value"><?= $topic['nb_messages'] - 1 ?></span>
                <span class="stat-label">Réponses</span>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
    <a href="home.php" class="back-link">← Retour à l'accueil</a>
  </main>

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
    color: 0xdba1b2,
    shininess: 25,
    waveHeight: 25,
    waveSpeed: 0.9,
    zoom: 0.9
  });
  </script>
  <script>
  function toggleTopicForm() {
    const form = document.getElementById('create-topic-form');
    form.style.display = form.style.display === 'none' ? 'block' : 'none';
  }
  
  // Filtrage dynamique sans rechargement
  document.addEventListener('DOMContentLoaded', () => {
    const filterBtns = document.querySelectorAll('.filter-btn');
    const topicCards = document.querySelectorAll('.topic-card');
    
    filterBtns.forEach(btn => {
      btn.addEventListener('click', () => {
        const filter = btn.dataset.filter;
        
        // Mise à jour boutons actifs
        filterBtns.forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        
        // Filtrage des topics
        topicCards.forEach(card => {
          const categorie = card.dataset.categorie;
          
          if (filter === 'all' || categorie === filter) {
            card.style.display = 'flex';
            card.style.animation = 'fadeIn 0.3s ease';
          } else {
            card.style.display = 'none';
          }
        });
      });
    });
  });
  </script>

<style>
 /* déplacé dans forum.css pour de meilleures perfs */
</style>
</body>
</html>