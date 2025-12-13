<?php
// forum_topic.php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
require_once 'db/config.php';

//gérer les requêtes AJAX (t'es un JAXX) d'abord (avant tout contenu HTML)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax']) && isset($_POST['reply'])) {
    header('Content-Type: application/json; charset=utf-8');
    
    $connected = isset($_SESSION['user_id']);
    $uid = $connected ? (int)$_SESSION['user_id'] : 0;
    
    if (!$connected) {
        echo json_encode(['success' => false, 'error' => 'Non connecté']);
        exit;
    }
    
    $topic_id = (int)($_GET['topic_id'] ?? 0);
    $contenu = trim($_POST['contenu'] ?? '');
    
    if (empty($contenu)) {
        echo json_encode(['success' => false, 'error' => 'Le message est vide']);
        exit;
    }
    
    // Vérifier que le sujet existe
    $stmt = $conn->prepare("SELECT verrouille FROM forum_topics WHERE topic_id = ?");
    $stmt->execute([$topic_id]);
    $topic = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$topic) {
        echo json_encode(['success' => false, 'error' => 'Sujet introuvable']);
        exit;
    }
    
    $is_admin = ($uid === 1);
    if ($topic['verrouille'] && !$is_admin) {
        echo json_encode(['success' => false, 'error' => 'Ce sujet est verrouillé']);
        exit;
    }
    
    // Insérer le message
    $stmt = $conn->prepare("INSERT INTO forum_messages (topic_id, user_id, contenu) VALUES (?, ?, ?)");
    $stmt->execute([$topic_id, $uid, $contenu]);
    $message_id = $conn->lastInsertId();
    
    //lettre à  jour le sujet
    $conn->prepare("UPDATE forum_topics SET nb_reponses = nb_reponses + 1, derniere_activite = NOW() WHERE topic_id = ?")->execute([$topic_id]);
    
    // récup le nom de l'utilisateur
    $stmt = $conn->prepare("SELECT nom_user FROM users WHERE user_id = ?");
    $stmt->execute([$uid]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'message' => [
            'message_id' => $message_id,
            'contenu' => $contenu,
            'nom_user' => $user['nom_user'] ?? 'Utilisateur',
            'timestamp' => time(),
            'date_formatted' => date('d/m/Y à  H:i'),
            'is_own' => true,
            'can_delete' => true
        ]
    ]);
    exit;
}

//init variables (bah ouais)
$connected = isset($_SESSION['user_id']);
$uid = $connected ? (int)$_SESSION['user_id'] : 0;
$is_admin = ($uid === 1);

$topic_id = isset($_GET['topic_id']) ? (int)$_GET['topic_id'] : 0;

if (!$topic_id) {
    header('Location: forum.php');
    exit;
}

$message = '';
$error = '';

// Récupérer le sujet
$stmt = $conn->prepare("
    SELECT ft.*, u.nom_user 
    FROM forum_topics ft
    JOIN users u ON ft.user_id = u.user_id
    WHERE ft.topic_id = ?
");
$stmt->execute([$topic_id]);
$topic = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$topic) {
    die("Sujet introuvable.");
}

//augm les vues
$conn->prepare("UPDATE forum_topics SET vues = vues + 1 WHERE topic_id = ?")->execute([$topic_id]);

// Actions admin
if ($is_admin && isset($_POST['admin_action'])) {
    $action = $_POST['admin_action'];
    
    if ($action === 'epingler') {
        $conn->prepare("UPDATE forum_topics SET epingle = NOT epingle WHERE topic_id = ?")->execute([$topic_id]);
        $message = "✅ Sujet épinglé/désépinglé";
    } elseif ($action === 'verrouiller') {
        $conn->prepare("UPDATE forum_topics SET verrouille = NOT verrouille WHERE topic_id = ?")->execute([$topic_id]);
        $message = "✅ Sujet verrouillé/déverrouillé";
    } elseif ($action === 'supprimer') {
        $conn->prepare("DELETE FROM forum_topics WHERE topic_id = ?")->execute([$topic_id]);
        header('Location: forum.php');
        exit;
    }
    
    $stmt->execute([$topic_id]);
    $topic = $stmt->fetch(PDO::FETCH_ASSOC);
    //redirection après l'action pour éviter la resoumission
    header("Location: forum_topic.php?topic_id=" . $topic_id);
    exit;
}


// Supprimer un message
if (isset($_POST['delete_message']) && ($connected || $is_admin)) {
    $message_id = (int)$_POST['message_id'];
    
    $stmt = $conn->prepare("SELECT user_id FROM forum_messages WHERE message_id = ?");
    $stmt->execute([$message_id]);
    $msg = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($msg && ($msg['user_id'] == $uid || $is_admin)) {
        $conn->prepare("DELETE FROM forum_messages WHERE message_id = ?")->execute([$message_id]);
        $conn->prepare("UPDATE forum_topics SET nb_reponses = nb_reponses - 1 WHERE topic_id = ?")->execute([$topic_id]);
        $message = "✅ Message supprimé.";
    }
}

// Récupérer les messages
$stmt = $conn->prepare("
    SELECT fm.*, u.nom_user
    FROM forum_messages fm
    JOIN users u ON fm.user_id = u.user_id
    WHERE fm.topic_id = ?
    ORDER BY fm.date_message ASC
");
$stmt->execute([$topic_id]);
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <title><?= htmlspecialchars($topic['titre']) ?> - Forum FoodHub</title>
  <link rel="stylesheet" href="assets/style.css">
  <?php include 'sidebar.php'; ?>
  <style>
    #volume-widget {
      margin-top: 10px;
    }
  </style>
</head>
<body>
  <audio id="player" autoplay loop>
    <source src="assets/Aéroport - Animal Crossing New Horizons OST.mp3" type="audio/mpeg">
  </audio>
  <?php include "slider_son.php"; ?>
  
  <main class="container forum-topic-container">
    <div class="breadcrumb">
      <a href="forum.php">← Forum</a> > <?= htmlspecialchars($topic['titre']) ?>
    </div>
    
    <div class="topic-header">
      <h1><?= htmlspecialchars($topic['titre']) ?></h1>
      
      <?php if ($topic['epingle']): ?>
        <span class="badge-pinned">📌 Épinglé</span>
      <?php endif; ?>
      
      <?php if ($topic['verrouille']): ?>
        <span class="badge-locked">🔒 Verrouillé</span>
      <?php endif; ?>
    </div>
    
    <?php if ($message): ?>
      <div class="success"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    
    <?php if ($error): ?>
      <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    
    <?php if ($is_admin): ?>
      <div class="admin-actions">
        <form method="post" style="display: inline;">
          <input type="hidden" name="admin_action" value="epingler">
          <button type="submit" class="btn-admin">
            <?= $topic['epingle'] ? '📌 Désépingler' : '📌 Épingler' ?>
          </button>
        </form>
        
        <form method="post" style="display: inline;">
          <input type="hidden" name="admin_action" value="verrouiller">
          <button type="submit" class="btn-admin">
            <?= $topic['verrouille'] ? '🔓 Déverrouiller' : '🔒 Verrouiller' ?>
          </button>
        </form>
        
        <form method="post" style="display: inline;" onsubmit="return confirm('Supprimer ce sujet ?')">
          <input type="hidden" name="admin_action" value="supprimer">
          <button type="submit" class="btn-admin btn-danger">🗑️ Supprimer</button>
        </form>
      </div>
    <?php endif; ?>
    
    <!-- Messages avec formulaire d'envoi intégré -->
    <div class="forum-messages-section">
      <div class="messages-container" id="messages-container">
<?php 
$previous_user_id = null;
$previous_time = null;

foreach ($messages as $index => $msg): 
  $current_user_id = (int)$msg['user_id'];
  $current_time = strtotime($msg['date_message']);
  $time_diff = $previous_time ? ($current_time - $previous_time) : PHP_INT_MAX;
  
  $start_new_group = ($current_user_id !== $previous_user_id) || ($time_diff > 300);
  $is_own = ($current_user_id === $uid);
  
  if ($start_new_group):
?>
        <div class="message-group <?= $is_own ? 'own' : 'other' ?>">
          <div class="group-header">
            <span class="author-name"><?= htmlspecialchars($msg['nom_user']) ?></span>
            <?php if ($msg['user_id'] == $topic['user_id'] && $index === 0): ?>
                <span class="badge-author">✨ Auteur</span>
            <?php endif; ?>
            <span class="group-time"><?= date('d/m/Y à H:i', $current_time) ?></span>
          </div>
          <div class="group-messages">
            <?php endif; ?>
            <div class="message-bubble" data-message-id="<?= $msg['message_id'] ?>" data-timestamp="<?= $current_time ?>">
              <div class="bubble-content"><?= htmlspecialchars($msg['contenu']) ?></div>
              <div class="bubble-footer">
            <?php if ($msg['modifie']): ?>
                <span class="bubble-edited">✏️</span>
            <?php endif; ?>
            <?php if ($connected && ($msg['user_id'] == $uid || $is_admin)): ?>
                <form method="post" style="display: inline;" onsubmit="return confirm('Supprimer ?')">
                  <input type="hidden" name="delete_message" value="1">
                  <input type="hidden" name="message_id" value="<?= $msg['message_id'] ?>">
                  <button type="submit" class="btn-delete-tiny">🗑️</button>
                </form>
            <?php endif; ?>
              </div>
            </div>
            <?php
            $next_msg = $messages[$index + 1] ?? null;
            $close_group = !$next_msg || ((int)$next_msg['user_id'] !== $current_user_id) || 
                 ((strtotime($next_msg['date_message']) - $current_time) > 300);
            if ($close_group):
            ?>
          </div>
        </div>
            <?php 
  endif;
  
  $previous_user_id = $current_user_id;
  $previous_time = $current_time;
endforeach; 
?>
      </div>
      
      <?php if ($connected): ?>
        <?php if ($topic['verrouille'] && !$is_admin): ?>
          <div class="info-box">
            🔒 Ce sujet est verrouillé.
          </div>
        <?php else: ?>
          <div class="reply-form-box">
            <form method="post" class="reply-form" id="reply-form">
              <input type="hidden" name="reply" value="1">
              <textarea name="contenu" id="reply-content" placeholder="Écris un message..."></textarea>
              <button type="submit" class="btn-send">Envoyer</button>
            </form>
          </div>
        <?php endif; ?>
      <?php else: ?>
        <div class="info-box">
          ℹ️ <a href="login.php">Connecte-toi</a> pour envoyer un message
        </div>
      <?php endif; ?>
    </div>
    
    <p><a href="forum.php" class="back-link">← Retour au forum</a></p>
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
  // Auto-resize textarea
  function adjustHeight(el) {
    el.style.height = "auto";
    el.style.height = (el.scrollHeight) + "px";
}
  (function(){
    const adjustHeight = el => {
      if (!el) return;
      el.style.height = 'auto';
      el.style.height = el.scrollHeight + 'px';
    };

    const attachAutoResize = el => {
      if (!el) return;
      if (el.__autoResizeAttached) return;
      el.__autoResizeAttached = true;
      adjustHeight(el);
      el.addEventListener('input', () => adjustHeight(el));
    };

    document.querySelectorAll('textarea').forEach(attachAutoResize);
  })();
  
  document.addEventListener('DOMContentLoaded', () => {
    const textarea = document.getElementById('reply-content');
    const form = document.getElementById('reply-form');
    const messagesContainer = document.getElementById('messages-container');
    
    // Envoi AJAX du message
    if (form) {
      form.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const contenu = textarea.value.trim();
        if (!contenu) return;
        
        const formData = new FormData();
        formData.append('ajax', '1');
        formData.append('reply', '1');
        formData.append('contenu', contenu);
        
        try {
          const response = await fetch(window.location.href, {
            method: 'POST',
            body: formData
          });
          
          const text = await response.text();
          console.log('Response:', text);
          
          let data;
          try {
            data = JSON.parse(text);
          } catch (e) {
            console.error('Failed to parse JSON:', text.substring(0, 200));
            alert('Erreur serveur (réponse invalide)');
            return;
          }
          
          if (data.success) {
            // Ajouter le message au DOM
            addMessageToDOM(data.message);
            textarea.value = '';
            adjustHeight(textarea);
            
            // Scroll vers le bas
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
          } else {
            alert(data.error || 'Erreur lors de l\'envoi');
          }
        } catch (err) {
          console.error('Fetch error:', err);
          alert('Erreur de connexion: ' + err.message);
        }
      });
    }
  });
  
  function addMessageToDOM(message) {
    const container = document.getElementById('messages-container');
    const lastGroup = container.querySelector('.message-group:last-child');
    const isOwn = message.is_own;
    
    // Créer la bulle de message
    const bubble = document.createElement('div');
    bubble.className = 'message-bubble';
    bubble.dataset.messageId = message.message_id;
    bubble.dataset.timestamp = message.timestamp;
    bubble.innerHTML = `
      <div class="bubble-content">${escapeHtml(message.contenu)}</div>
      <div class="bubble-footer">
        ${message.can_delete ? '<button type="button" class="btn-delete-tiny" onclick="deleteMessage(' + message.message_id + ')">🗑️</button>' : ''}
      </div>
    `;
    
    // Vérifier si on peut ajouter au dernier groupe
    if (lastGroup && lastGroup.classList.contains(isOwn ? 'own' : 'other')) {
      lastGroup.querySelector('.group-messages').appendChild(bubble);
    } else {
      // Créer un nouveau groupe
      const newGroup = document.createElement('div');
      newGroup.className = `message-group ${isOwn ? 'own' : 'other'}`;
      newGroup.innerHTML = `
        <div class="group-header">
          <span class="author-name">${escapeHtml(message.nom_user)}</span>
          <span class="group-time">${message.date_formatted}</span>
        </div>
        <div class="group-messages"></div>
      `;
      newGroup.querySelector('.group-messages').appendChild(bubble);
      container.appendChild(newGroup);
    }
    
    // Animation
    bubble.style.animation = 'fadeIn 0.3s ease';
  }
  
  function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }
  
  function deleteMessage(messageId) {
    if (!confirm('Supprimer ce message ?')) return;
    
    const form = document.createElement('form');
    form.method = 'post';
    form.innerHTML = `
      <input type="hidden" name="delete_message" value="1">
      <input type="hidden" name="message_id" value="${messageId}">
    `;
    document.body.appendChild(form);
    form.submit();
  }
  
  // Afficher l'heure au survol en haut à gauche d'une bulle de message, eh ouais, on plagie discord maintenant
// récupération directe DEPUIS la BDD (M.Jallon le goat)
  let currentHoverBubble = null;
  
  document.addEventListener('mouseover', (e) => {
    const bubble = e.target.closest('.message-bubble');
    
    // Si on change de bulle, nettoyer l'ancienne
    if (currentHoverBubble && currentHoverBubble !== bubble) {
      const oldTime = currentHoverBubble.querySelector('.hover-time');
      if (oldTime) oldTime.remove();
    }
    
    if (bubble && bubble.dataset.time && !bubble.querySelector('.hover-time')) {
      currentHoverBubble = bubble;
      const timeStr = bubble.dataset.time;
      
      const timeSpan = document.createElement('span');
      timeSpan.className = 'hover-time';
      timeSpan.textContent = timeStr;
      bubble.appendChild(timeSpan);
    }
  });
  // Vérifier si on sort vraiment de la bulle (pas juste un élément enfant)
  document.addEventListener('mouseout', (e) => {
    const bubble = e.target.closest('.message-bubble');
    if (bubble && !bubble.contains(e.relatedTarget)) {
      const hoverTime = bubble.querySelector('.hover-time');
      if (hoverTime) {
        hoverTime.remove();
      }
      if (currentHoverBubble === bubble) {
        currentHoverBubble = null;
      }
    }
  });
</script>

  <style>
  .forum-topic-container {
    backdrop-filter: blur(15px);
    background: rgba(255, 255, 255, 0.25);
    padding: 2rem;
    border-radius: 1.5rem;
    max-width: 900px;
    margin: 100px auto;
    padding-bottom: 20px;
  }
  
  .breadcrumb {
    margin-bottom: 1.5rem;
    font-size: 0.9rem;
    color: #666;
  }
  
  .breadcrumb a {
    color: #ff6b6b;
    text-decoration: none;
    transition: color 0.3s ease;
  }
  
  .breadcrumb a:hover {
    color: #ff8c42;
  }
  
  .topic-header {
    margin-bottom: 1.5rem;
  }
  
  .topic-header h1 {
    color: #333;
    font-size: 1.8rem;
    margin-bottom: 1rem;
  }
  
  .badge-pinned,
  .badge-locked {
    display: inline-block;
    padding: 0.3rem 0.7rem;
    border-radius: 12px;
    font-size: 0.8rem;
    font-weight: 600;
    margin-right: 0.5rem;
    margin-bottom: 0rem;
  }
  
  .badge-pinned {
    background: rgba(255, 193, 7, 0.2);
    color: #856404;
  }
  
  .badge-locked {
    background: rgba(158, 158, 158, 0.2);
    color: #616161;
  }
  
  .admin-actions {
    display: flex;
    gap: 1rem;
    margin: 1.5rem 0;
    padding: 1rem;
    background: rgba(255, 193, 7, 0.1);
    border-radius: 0.8rem;
    border-left: 4px solid #FFC107;
    flex-wrap: wrap;
  }
  
  .btn-admin {
    padding: 0.5rem 1rem;
    border-radius: 0.6rem;
    border: none;
    background: rgba(255, 193, 7, 0.3);
    color: #856404;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    font-family: 'HSR', sans-serif;
  }
  
  .btn-admin:hover {
    background: rgba(255, 193, 7, 0.5);
    transform: translateY(-2px);
  }
  
  .btn-danger {
    background: rgba(244, 67, 54, 0.3) !important;
    color: #c62828 !important;
  }
  
  .btn-danger:hover {
    background: rgba(244, 67, 54, 0.5) !important;
  }
  
  /* Section des messages avec formulaire */
  .forum-messages-section {
    background: rgba(255, 255, 255, 0.15);
    border-radius: 1rem;
    padding: 1.5rem;
    margin: 2rem 0;
    display: flex;
    flex-direction: column;
    max-height: 600px;
  }
  
  .messages-container {
    flex: 1;
    overflow-y: auto;
    margin-bottom: 1rem;
    padding-right: 0.5rem;
    display: flex;
    flex-direction: column;
  }
  
  .messages-container::-webkit-scrollbar {
    width: 8px;
  }
  
  .messages-container::-webkit-scrollbar-track {
    background: rgba(0, 0, 0, 0.05);
    border-radius: 10px;
  }
  
  .messages-container::-webkit-scrollbar-thumb {
    background: rgba(255, 107, 107, 0.4);
    border-radius: 10px;
  }
  
  .messages-container::-webkit-scrollbar-thumb:hover {
    background: rgba(255, 107, 107, 0.6);
  }
  
  .message-group {
    margin-bottom: 0.6rem;
    display: flex;
    flex-direction: column;
    width: 100%;
  }
  
  .message-group.own {
    align-items: flex-end;
  }
  
  .message-group.other {
    align-items: flex-start;
  }
  
  .group-header {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.2rem;
    font-size: 0.8rem;
    width: 100%;
  }
  
  .message-group.own .group-header {
    justify-content: flex-end;
  }
  
  .message-group.other .group-header {
    justify-content: flex-start;
  }
  
  .author-name {
    font-weight: 600;
    color: #333;
  }
  
  .badge-author {
    padding: 0.2rem 0.5rem;
    border-radius: 8px;
    font-size: 0.7rem;
    background: rgba(255, 193, 7, 0.2);
    color: #856404;
    font-weight: 600;
  }
  
  .group-time {
    color: #888;
    font-size: 0.8rem;
  }
  
  .group-messages {
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
    align-items: flex-start;
  }
  
  .message-group.own .group-messages {
    align-self: flex-end;
    align-items: flex-end;
  }
  
  .message-group.other .group-messages {
    align-self: flex-start;
    align-items: flex-start;
  }
  
  .message-bubble {
    padding: 0.5rem 0.8rem;
    border-radius: 0.8rem;
    transition: all 0.2s ease;
    position: relative;
    display: inline-block;
  }
  
  .own .message-bubble {
    background: linear-gradient(135deg, #ff6b6b, #ff8c42);
    color: white;
    border-top-right-radius: 0.3rem;
  }
  
  .other .message-bubble {
    background: rgba(255, 255, 255, 0.4);
    backdrop-filter: blur(10px);
    color: #333;
    border-top-left-radius: 0.3rem;
  }
  
  .message-bubble:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
  }
  
  .bubble-content {
    line-height: 1.3;
    word-wrap: break-word;
    word-break: break-word;
    font-size: 0.88rem;
    white-space: pre-wrap;
    max-width: 400px;
    overflow-wrap: break-word;
  }
  
  .bubble-footer {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-top: 0.2rem;
    font-size: 0.7rem;
    opacity: 0.8;
  }
  
  .bubble-edited {
    font-size: 0.7rem;
  }
  
  .btn-delete-tiny {
    background: rgba(255, 255, 255, 0.2);
    border: none;
    padding: 0.1rem 0.3rem;
    border-radius: 0.3rem;
    cursor: pointer;
    font-size: 0.7rem;
    transition: all 0.2s ease;
  }
  
  .btn-delete-tiny:hover {
    background: rgba(244, 67, 54, 0.3);
  }
  
  .hover-time {
    position: absolute;
    top: 1px;
    left: -48px;
    background: rgba(0, 0, 0, 0.7);
    color: white;
    padding: 2px 6px;
    border-radius: 4px;
    font-size: 0.75rem;
    pointer-events: none;
    white-space: nowrap;
    animation: fadeIn 0.2s ease;
  }
  
  @keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
  }

  .reply-form-box {
    background: rgba(255, 255, 255, 0.2);
    backdrop-filter: blur(10px);
    padding: 0.8rem 1rem;
    border-radius: 0.8rem;
    border-top: 1px solid rgba(255, 255, 255, 0.3);
    margin-top: auto;
    padding: 4px 10px;
    gap: 6px;
    }
  
  .reply-form {
    display:flex;
    gap: 0.8rem;
    align-items: center;
  }
  
#reply-content {
    flex: 1;
    padding: 0.8rem;
    border-radius: 0.8rem;
    border: 2px solid rgba(255, 107, 107, 0.3);
    background: rgba(255, 255, 255, 0.6);
    font-family: 'HSR', sans-serif;
    font-size: 0.95rem;
    resize: none;
    min-height: 30px;
    max-height: 120px;
    transition: all 0.3s ease;
    white-space: pre-wrap;
    overflow-y: auto;
    box-sizing: border-box;
}
  
  #reply-content:focus {
    outline: none;
    border-color: #ff6b6b;
    background: rgba(255, 255, 255, 0.8);
    box-shadow: 0 0 10px rgba(255, 107, 107, 0.2);
  }
  
  .btn-send {
    background: linear-gradient(135deg, #4CAF50, #45a049);
    color: white;
    border: none;
    border-radius: 0.6rem;
    padding: 0.8rem 1rem;
    cursor: pointer;
    font-weight: 600;
    font-size: 1rem;
    transition: all 0.2s ease;
    flex-shrink: 0;
    height: fit-content;
  }
  
  .btn-send:hover {
    background: linear-gradient(135deg, #45a049, #388e3c);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(76, 175, 80, 0.3);
  }
  
  .info-box {
    background: rgba(33, 150, 243, 0.1);
    padding: 1rem;
    border-radius: 0.8rem;
    border-left: 4px solid #2196F3;
    margin: 0;
    color: #333;
  }
  
  .info-box a {
    color: #2196F3;
    font-weight: 600;
    text-decoration: none;
  }
  
  .back-link {
    display: inline-block;
    margin-top: 0rem;
    margin-bottom: -0.2rem;
    color: #ff6b6b;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s ease;
  }
  
  .back-link:hover {
    color: #ff8c42;
    transform: translateX(-5px);
  }
  
  @media (max-width: 768px) {
    .forum-messages-section {
      max-height: 400px;
    }
    
    .group-messages {
      max-width: 85%;
    }
    
    .reply-form {
      flex-direction: column;
    }
    
    .btn-send {
      width: 100%;
    }
  }
  </style>
</body>
</html>