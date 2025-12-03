<?php
// forum.php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
require_once 'db/config.php';

$connected = isset($_SESSION['user_id']);
$uid = $connected ? (int)$_SESSION['user_id'] : 0;
$is_admin = ($uid === 1);

$message = '';
$error = '';

// Création d'un nouveau sujet
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_topic']) && $connected) {
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

// Récupération des sujets, ft=forum_topic
$sql = "
SELECT ft.*, u.nom_user, 
       (SELECT COUNT(*) FROM forum_messages WHERE topic_id = ft.topic_id) as nb_messages
FROM forum_topics ft
JOIN users u ON ft.user_id = u.user_id
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
  <meta charset="utf-8">
  <title>Forum - FoodHub</title>
  <link rel="stylesheet" href="assets/style.css">
  <link rel="stylesheet" href="forum.css">
  <?php include 'sidebar.php'; ?>
</head>
<body>
  <audio id="player" autoplay loop>
    <source src="assets/Boutique Nook 2 - Animal Crossing New Horizons OST.mp3" type="audio/mpeg">
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
                <span>Par <strong><?= htmlspecialchars($topic['nom_user']) ?></strong></span>
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
  VANTA.WAVES({
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
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
  }
  .forum-container {
    backdrop-filter: blur(15px);
    background: rgba(255, 255, 255, 0.25);
    padding: 2rem;
    border-radius: 1.5rem;
    max-width: 1200px;
    margin: 100px auto;
  }
  
  #forum-stats {
    display: flex;
    gap: 2rem;
    justify-content: center;
    margin: 2rem 0;
    padding: 1.5rem;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 1rem;
    left: 0%;
  }
  .forum-stats {
    display: flex;
    gap: 2rem;
    justify-content: center;
    margin: 2rem 0;
    padding: 1.5rem;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 1rem;
    left: 0%;
  }
  
  .stat-item {
    text-align: center;
  }
  
  .stat-number {
    display: block;
    font-size: 2rem;
    font-weight: 700;
    color: #ff6b6b;
  }
  
  .stat-label {
    font-size: 0.9rem;
    color: #666;
  }
  .a .back-link {
    margin-top: 50px;
  }
  .forum-filters {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
    justify-content: center;
    margin: 2rem 0;
  }
  
  .filter-btn {
    padding: 0.7rem 1.5rem;
    border-radius: 25px;
    background: rgba(255, 255, 255, 0.3);
    color: #333;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s ease;
    border: 2px solid transparent;
    cursor: pointer;
    font-family: 'HSR', sans-serif;
  }
  
  .filter-btn:hover {
    background: rgba(255, 107, 107, 0.2);
    border-color: #ff6b6b;
  }
  
  .filter-btn.active {
    background: linear-gradient(135deg, #ff6b6b, #ff8c42);
    color: white;
    border-color: #ff6b6b;
  }
  
    .btn-create-topic {
    display: block;
    margin: 2rem auto;
    padding: 1rem 1.8rem;
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
    position: relative; 
    width: fit-content;
    font-family: 'HSR', sans-serif;
}
    .btn {
    margin-top: -10px;
    display: block;
    padding: 1rem 1.8rem;
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
    position: relative; 
    width: fit-content;
    font-family: 'HSR', sans-serif;
  }

  .btn:hover { 
    transform: translateY(-4px) scale(1.03); 
    box-shadow: 0 12px 25px rgba(0,0,0,0.25); 
    background: linear-gradient(135deg, #ff8c42, #ff6b6b);
  }
  .btn::after {
    content: "";
    position: absolute;
    top: 0; left: -100%;
    width: 100%;
    height: 100%;
    background: rgba(255,255,255,0.25);
    transition: all 0.3s ease;
    border-radius: 14px;
  }
  .btn:hover::after { 
    left: 0; 
  }

  .btn-create-topic::after {
    content: "";
    position: absolute;
    top: 0; left: -100%;
    width: 100%;
    height: 100%;
    background: rgba(255,255,255,0.25);
    transition: all 0.4s ease;
    border-radius: 14px;
  }
  

  .btn-create-topic:hover::after { left: 0; }
  .btn-create-topic:hover { 
    transform: translateY(-4px) scale(1.03); 
    box-shadow: 0 12px 25px rgba(0,0,0,0.25); 
    background: linear-gradient(135deg, #ff8c42, #ff6b6b);
  }
  
  .topic-form {
    background: rgba(255, 255, 255, 0.3);
    padding: 2rem;
    padding-top: 13px;
    border-radius: 1rem;
    margin: 2rem 0;
  }
  
  .topic-form label {
    display: block;
    margin-top: 1rem;
    font-weight: 600;
    color: #333;
  }
  
  .topic-form input,
  .topic-form select,
  .topic-form textarea {
    width: 100%;
    padding: 0.8rem;
    margin-top: 0.5rem;
    border-radius: 0.8rem;
    border: 1px solid rgba(0,0,0,0.1);
    background: rgba(255, 255, 255, 0.6);
    font-family: 'HSR', sans-serif;
  }
  
  .topic-form textarea {
    resize: none;
    min-height: 120px;
  }
  
  .form-actions {
    display: flex;
    gap: 1rem;
    margin-top: 1.5rem;
  }
  
  .topics-list {
    margin-top: 2rem;
  }
  
  .topic-card {
    display: flex;
    align-items: center;
    gap: 1.5rem;
    padding: 1.5rem;
    margin-bottom: 1rem;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 1rem;
    transition: all 0.3s ease;
  }
  
  .topic-card:hover {
    transform: translateY(-3px);
    background: rgba(255, 255, 255, 0.3);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
  }
  
  .topic-card.pinned {
    border-left: 4px solid #ff6b6b;
    background: rgba(255, 235, 205, 0.3);
  }
  
  .topic-icon {
    font-size: 2.5rem;
  }
  
  .topic-info {
    flex: 1;
  }
  
  .topic-info h3 {
    margin-top: 10px;
    margin: 0 0 0.5rem 0;
  }
  
  .topic-info h3 a {
    color: #333;
    text-decoration: none;
    font-size: 1.2rem;
    margin-top: 10px;
  }
  
  .topic-info h3 a:hover {
    color: #ff6b6b;
  }
  
  .topic-meta {
    font-size: 0.9rem;
    color: #666;
  }
  
  .badge-pinned,
  .badge-locked {
    display: inline-block;
    padding: 0.3rem 0.7rem;
    border-radius: 12px;
    font-size: 0.72rem;
    font-weight: 600;
    margin-right: 0.5rem;
    margin-bottom: 0.3rem; 
  }
  
  .badge-pinned {
    background: rgba(255, 193, 7, 0.2);
    color: #856404;
  }
  
  .badge-locked {
    background: rgba(108, 117, 125, 0.2);
    color: #495057;
  }
  
  .topic-stats {
    display: flex;
    gap: 2rem;
  }
  
  .topic-stats .stat {
    text-align: center;
  }
  
  .topic-stats .stat-value {
    display: block;
    font-size: 1.5rem;
    font-weight: 700;
    color: #ff6b6b;
  }
  
  .topic-stats .stat-label {
    font-size: 0.8rem;
    color: #666;
  }
  
  .info-box {
    background: rgba(33, 150, 243, 0.1);
    padding: 1rem;
    border-radius: 0.8rem;
    border-left: 4px solid #2196F3;
    margin: 2rem 0;
  }
  
  .empty-message {
    text-align: center;
    padding: 3rem;
    color: #666;
    font-style: italic;
  }
  
  @media (max-width: 768px) {
    .topic-card {
      flex-direction: column;
      align-items: flex-start;
    }
    
    .topic-stats {
      width: 100%;
      justify-content: space-around;
    }
  }
</style>
</body>
</html>