<?php
// forum_topic.php
session_start();
if (!isset($_SESSION['user_id'])) {
  header("Location: login.php");
  exit;
}
require_once 'db/config.php';
require_once __DIR__ . '/csrf_helper.php';
require_once __DIR__ . '/auth_helper.php';
function truncate_words(string $text, int $max_words = 6): string {
    $words = preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY);
    if (count($words) <= $max_words) return $text;
    return implode(' ', array_slice($words, 0, $max_words)) . '…';
}
function render_forum_message(string $text): string {
    $safe = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    $safe = preg_replace(
        '~(https?://[^\s<>"\'()]+)~i',
        '<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>',
        $safe
    );
    return $safe;
}

//gérer les requêtes AJAX (t'es un JAXX) d'abord (avant tout contenu HTML)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax']) && isset($_POST['reply'])) {
  header('Content-Type: application/json; charset=utf-8');

  $connected = isset($_SESSION['user_id']);
  $uid = $connected ? (int) $_SESSION['user_id'] : 0;

  if (!$connected) {
    echo json_encode(['success' => false, 'error' => 'Non connecté']);
    exit;
  }

  if (empty($_POST['csrf_token']) || !fh_verify_csrf($_POST['csrf_token'])) {
    echo json_encode(['success' => false, 'error' => 'Jeton CSRF invalide']);
    exit;
  }

  $topic_id = (int) ($_GET['topic_id'] ?? 0);
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

  $is_admin = fh_is_admin($conn);
  if ($topic['verrouille'] && !$is_admin) {
    echo json_encode(['success' => false, 'error' => 'Ce sujet est verrouillé']);
    exit;
  }

  // Insérer le message
  $parent_id = isset($_POST['parent_id']) && (int)$_POST['parent_id'] > 0 ? (int)$_POST['parent_id'] : null;

  $stmt = $conn->prepare("INSERT INTO forum_messages (topic_id, user_id, contenu, parent_id) VALUES (?, ?, ?, ?)");
  $stmt->execute([$topic_id, $uid, $contenu, $parent_id]);
  $message_id = $conn->lastInsertId();

  //lettre à  jour le sujet
  $conn->prepare("UPDATE forum_topics SET nb_reponses = nb_reponses + 1, derniere_activite = NOW() WHERE topic_id = ?")->execute([$topic_id]);

  // ── Créer des notifications forum pour les autres membres du topic ──
// Récupérer tous les utilisateurs ayant déjà posté dans ce topic
// (sauf l'auteur du nouveau message) ET ayant notif_forum_actif = 1
  $stmt_membres = $conn->prepare("
    SELECT DISTINCT fm2.user_id
    FROM forum_messages fm2
    LEFT JOIN user_preferences up ON fm2.user_id = up.user_id
    WHERE fm2.topic_id = ?
      AND fm2.user_id != ?
      AND fm2.auteur_supprime = 0
      AND (up.notif_forum_actif IS NULL OR up.notif_forum_actif = 1)
");
  $stmt_membres->execute([$topic_id, $uid]);
  $membres = $stmt_membres->fetchAll(PDO::FETCH_COLUMN);

  // Récupérer le titre du topic et le nom de l'auteur du nouveau message
  $stmt_info = $conn->prepare("
    SELECT ft.titre, u.nom_user
    FROM forum_topics ft, users u
    WHERE ft.topic_id = ? AND u.user_id = ?
  ");
  $stmt_info->execute([$topic_id, $uid]);
  $info_notif = $stmt_info->fetch(PDO::FETCH_ASSOC);

  // Récupérer infos du parent (si réponse directe)
  $parent_author_id = null;
  if ($parent_id) {
      $stmt_parent = $conn->prepare("SELECT user_id FROM forum_messages WHERE message_id = ?");
      $stmt_parent->execute([$parent_id]);
      $parent_row = $stmt_parent->fetch(PDO::FETCH_ASSOC);
      if ($parent_row) $parent_author_id = (int)$parent_row['user_id'];
  }

  if ($info_notif && !empty($membres)) {
      $stmt_notif = $conn->prepare("
          INSERT INTO forum_notifs
              (user_id, topic_id, message_id, topic_titre, auteur_nom, is_read, is_reply)
          VALUES (?, ?, ?, ?, ?, 0, ?)
      ");

      foreach ($membres as $membre_id) {
          $is_reply_notif = ($parent_author_id !== null && $parent_author_id === (int)$membre_id) ? 1 : 0;
          $stmt_notif->execute([
              $membre_id,
              $topic_id,
              $message_id,
              $info_notif['titre'],
              $info_notif['nom_user'],
              $is_reply_notif
          ]);
      }
  }

  // récup le nom de l'utilisateur
  $stmt = $conn->prepare("SELECT nom_user FROM users WHERE user_id = ?");
  $stmt->execute([$uid]);
  $user = $stmt->fetch(PDO::FETCH_ASSOC);

  // Récupérer un aperçu du parent si reply
  $parent_preview = null;
  if ($parent_id) {
      $stmt_prev = $conn->prepare("SELECT contenu, u.nom_user FROM forum_messages fm LEFT JOIN users u ON fm.user_id = u.user_id WHERE fm.message_id = ?");
      $stmt_prev->execute([$parent_id]);
      $prev_row = $stmt_prev->fetch(PDO::FETCH_ASSOC);
      if ($prev_row) {
          $parent_preview = [
              'contenu' => truncate_words($prev_row['contenu']),
              'nom_user' => $prev_row['nom_user']
          ];
      }
  }

  echo json_encode([
      'success' => true,
      'message' => [
        'message_id'     => $message_id,
        'contenu'        => $contenu,
        'nom_user'       => $user['nom_user'] ?? 'Utilisateur supprimé',
        'timestamp'      => time(),
        'date_formatted' => date('d/m/Y à H:i'),
        'is_own'         => true,
        'can_delete'     => true,
        'parent_id'      => $parent_id,
        'parent_preview' => $parent_preview,
      ]
  ]);
  exit;
}

// Endpoint AJAX suppression de message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax']) && isset($_POST['delete_message_ajax'])) {
  header('Content-Type: application/json; charset=utf-8');
  $uid_del = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
  if (!$uid_del) {
    echo json_encode(['success' => false, 'error' => 'Non connecté']);
    exit;
  }
  if (empty($_POST['csrf_token']) || !fh_verify_csrf($_POST['csrf_token'])) {
    echo json_encode(['success' => false, 'error' => 'Jeton CSRF invalide']);
    exit;
  }
  $mid = (int) ($_POST['message_id'] ?? 0);
  $is_admin_del = fh_is_admin($conn);
  $stmt = $conn->prepare("SELECT user_id, topic_id FROM forum_messages WHERE message_id = ?");
  $stmt->execute([$mid]);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);
  if ($row && ($row['user_id'] == $uid_del || $is_admin_del)) {
    $conn->prepare("DELETE FROM forum_messages WHERE message_id = ?")->execute([$mid]);
    $conn->prepare("UPDATE forum_topics SET nb_reponses = GREATEST(0, nb_reponses - 1) WHERE topic_id = ?")->execute([$row['topic_id']]);
    echo json_encode(['success' => true]);
  } else {
    echo json_encode(['success' => false, 'error' => 'Non autorisé']);
  }
  exit;
}

// Endpoint AJAX GET polling — renvoie les nouveaux messages depuis last_message_id
if (isset($_GET['poll']) && $_GET['poll'] === '1' && isset($_GET['last_message_id'])) {
  header('Content-Type: application/json; charset=utf-8');
  $uid_poll = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
  if (!$uid_poll) {
    echo json_encode(['error' => 'not_logged']);
    exit;
  }
  $is_admin_poll = fh_is_admin($conn);
  $topic_id_poll = (int) ($_GET['topic_id'] ?? 0);
  $last_id = (int) ($_GET['last_message_id'] ?? 0);
  if (!$topic_id_poll) {
    echo json_encode(['error' => 'invalid_topic']);
    exit;
  }
  $stmt = $conn->prepare("
      SELECT fm.message_id, fm.contenu, fm.date_message, fm.modifie, fm.user_id,
            fm.parent_id,
            COALESCE(u.nom_user, 'Utilisateur supprimé') AS nom_user
      FROM forum_messages fm
      LEFT JOIN users u ON fm.user_id = u.user_id
      WHERE fm.topic_id = ? AND fm.message_id > ?
      ORDER BY fm.date_message ASC
  ");

  $stmt->execute([$topic_id_poll, $last_id]);
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
  $out = [];
  foreach ($rows as $msg) {
    $is_own = ((int) $msg['user_id'] === $uid_poll);
    // Récupérer parent_preview si parent_id défini
    $parent_preview = null;
    if (!empty($msg['parent_id'])) {
        $stmt_p = $conn->prepare("SELECT contenu, COALESCE(u.nom_user,'Utilisateur supprimé') AS nom_user FROM forum_messages fm LEFT JOIN users u ON fm.user_id = u.user_id WHERE fm.message_id = ?");
        $stmt_p->execute([$msg['parent_id']]);
        $p = $stmt_p->fetch(PDO::FETCH_ASSOC);
        if ($p) $parent_preview = ['contenu' => truncate_words($p['contenu']), 'nom_user' => $p['nom_user']];
    }

    $out[] = [
        'message_id'     => (int) $msg['message_id'],
        'contenu'        => $msg['contenu'],
        'nom_user'       => $msg['nom_user'],
        'timestamp'      => strtotime($msg['date_message']),
        'date_formatted' => date('d/m/Y à H:i', strtotime($msg['date_message'])),
        'modifie'        => (bool) $msg['modifie'],
        'is_own'         => $is_own,
        'can_delete'     => $is_own || $is_admin_poll,
        'user_id'        => (int) $msg['user_id'],
        'parent_id'      => $msg['parent_id'] ? (int)$msg['parent_id'] : null,
        'parent_preview' => $parent_preview,
    ];
  }
  
  // Récupérer TOUS les message_ids actuels du topic (pour détecter les suppressions côté client)
  $stmt_all_ids = $conn->prepare("SELECT message_id FROM forum_messages WHERE topic_id = ? ORDER BY message_id ASC");
  $stmt_all_ids->execute([$topic_id_poll]);
  $all_message_ids = $stmt_all_ids->fetchAll(PDO::FETCH_COLUMN);
  
  echo json_encode(['success' => true, 'messages' => $out, 'all_message_ids' => $all_message_ids]);
  exit;
}

//init variables (bah ouais)
$connected = isset($_SESSION['user_id']);
$uid = $connected ? (int) $_SESSION['user_id'] : 0;
$is_admin = fh_is_admin($conn);

$topic_id = isset($_GET['topic_id']) ? (int) $_GET['topic_id'] : 0;

if (!$topic_id) {
  header('Location: forum.php');
  exit;
}

$message = '';
$error = '';

// Récupérer le sujet
$stmt = $conn->prepare("
    SELECT ft.*, COALESCE(u.nom_user, 'Utilisateur supprimé') AS nom_user
    FROM forum_topics ft
    LEFT JOIN users u ON ft.user_id = u.user_id
    WHERE ft.topic_id = ?
");
$stmt->execute([$topic_id]);
$topic = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$topic) {
  abort_404('forum');
}

//augm les vues
$conn->prepare("UPDATE forum_topics SET vues = vues + 1 WHERE topic_id = ?")->execute([$topic_id]);

// Actions admin
if ($is_admin && isset($_POST['admin_action'])) {
  if (empty($_POST['csrf_token']) || !fh_verify_csrf($_POST['csrf_token'])) {
    header("Location: forum_topic.php?topic_id=" . $topic_id);
    exit;
  }
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
  if (empty($_POST['csrf_token']) || !fh_verify_csrf($_POST['csrf_token'])) {
    $error = 'Jeton CSRF invalide.';
  } else {
    $message_id = (int) $_POST['message_id'];

    $stmt = $conn->prepare("SELECT user_id FROM forum_messages WHERE message_id = ?");
    $stmt->execute([$message_id]);
    $msg = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($msg && ($msg['user_id'] == $uid || $is_admin)) {
      $conn->prepare("DELETE FROM forum_messages WHERE message_id = ?")->execute([$message_id]);
      $conn->prepare("UPDATE forum_topics SET nb_reponses = nb_reponses - 1 WHERE topic_id = ?")->execute([$topic_id]);
      $message = "✅ Message supprimé.";
    }
  }
}

// Récupérer les messages
$stmt = $conn->prepare("
    SELECT fm.*, fm.auteur_supprime,
           COALESCE(u.nom_user, 'Utilisateur supprimé') AS nom_user,
           pm.contenu AS parent_contenu,
           COALESCE(pu.nom_user, 'Utilisateur supprimé') AS parent_nom_user
    FROM forum_messages fm
    LEFT JOIN users u ON fm.user_id = u.user_id
    LEFT JOIN forum_messages pm ON fm.parent_id = pm.message_id
    LEFT JOIN users pu ON pm.user_id = pu.user_id
    WHERE fm.topic_id = ?
    ORDER BY fm.date_message ASC
");
$stmt->execute([$topic_id]);
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">

<head>
  <link rel="icon" type="image/x-icon" href="FoodHubLogo.ico">
  <meta charset="utf-8">
  <title><?= htmlspecialchars($topic['titre']) ?> - Forum FoodHub</title>
  <link rel="stylesheet" href="assets/style.css">
  <link rel="stylesheet" href="forum_topic.css">
  <?php include 'sidebar.php'; ?>
</head>

<body>
  <audio id="player" autoplay loop>
    <source
      src="https://raw.githubusercontent.com/HDSachaNewLive/foodhub-assets/main/Aéroport - Animal Crossing New Horizons OST.mp3"
      type="audio/mpeg">
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
          <?= fh_csrf_field() ?>
          <button type="submit" class="btn-admin">
            <?= $topic['epingle'] ? '📌 Désépingler' : '📌 Épingler' ?>
          </button>
        </form>

        <form method="post" style="display: inline;">
          <input type="hidden" name="admin_action" value="verrouiller">
          <?= fh_csrf_field() ?>
          <button type="submit" class="btn-admin">
            <?= $topic['verrouille'] ? '🔓 Déverrouiller' : '🔒 Verrouiller' ?>
          </button>
        </form>

        <form method="post" style="display: inline;" onsubmit="return confirm('Voulez vous VRAIMENT supprimer ce sujet (les messages seront perdus à jamais !) ?')">
          <input type="hidden" name="admin_action" value="supprimer">
          <?= fh_csrf_field() ?>
          <button type="submit" class="btn-admin btn-danger">🗑️ Supprimer</button>
        </form>
      </div>
    <?php endif; ?>

    <!-- Messages avec formulaire d'envoi intégré -->
    <div class="forum-messages-section">
      <div class="messages-container" id="messages-container">
        <?php
        // Calculer le dernier message_id pour le polling
        $last_msg_id = 0;
        $previous_user_id = null;
        $previous_time = null;

        foreach ($messages as $index => $msg):
          if ((int) $msg['message_id'] > $last_msg_id)
            $last_msg_id = (int) $msg['message_id'];
          $current_user_id = (int) $msg['user_id'];
          $current_time = strtotime($msg['date_message']);
          $time_diff = $previous_time ? ($current_time - $previous_time) : PHP_INT_MAX;

          $start_new_group = ($current_user_id !== $previous_user_id) || ($time_diff > 300);
          $is_own = ($current_user_id === $uid) && !$msg['auteur_supprime'];

          if ($start_new_group):
            ?>
            <div class="message-group <?= $is_own ? 'own' : 'other' ?>">
              <div class="group-header">
                  <?php if ($msg['auteur_supprime']): ?>
                    <span class="author-name" style="opacity: 0.7;"><a href="/profil_public.php?user_id=99999999999999999999999999999999999999">[Supprimé]</a></span>
                  <?php elseif ($msg['user_id']): ?>
                    <?php $profil = "profil_public.php?user_id=" . urlencode($msg['user_id']); ?>
                    <a href="<?= $profil ?>">
                      <span id="profil" class="author-name"><?= htmlspecialchars($msg['nom_user']) ?></span>
                    </a>
                  <?php else: ?>
                    <span class="author-name" style="opacity: 0.7;"><?= htmlspecialchars($msg['nom_user']) ?></span>
                  <?php endif; ?>

                  <?php if (!$msg['auteur_supprime'] && $msg['user_id'] == $topic['user_id'] && $index === 0): ?>
                    <span class="badge-author">✨ Auteur</span>
                  <?php endif; ?>
                  <span class="group-time"><?= date('d/m/Y à H:i', $current_time) ?></span>
              </div>
              <!-- si les messages sont groupés -->
              <div class="group-messages">
              <?php endif; ?>
              <div class="bubble-wrapper <?= $is_own ? 'own' : 'other' ?>">
                <div class="bubble-with-actions">
                  <?php if (!$is_own && $connected && !$topic['verrouille']): ?>
                    <button type="button" class="btn-reply-tiny btn-action-hover"
                      onclick="setReplyTarget(<?= $msg['message_id'] ?>, <?= htmlspecialchars(json_encode($msg['auteur_supprime'] ? '[Supprimé]' : $msg['nom_user']), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode(mb_substr($msg['contenu'], 0, 80)), ENT_QUOTES) ?>)">↩</button>
                  <?php endif; ?>
                  <div class="message-bubble" data-message-id="<?= $msg['message_id'] ?>"
                    data-timestamp="<?= $current_time ?>">
                    <?php if (!empty($msg['parent_contenu'])): ?>
                      <div class="bubble-reply-preview-outer">
                        <div class="bubble-reply-preview-inner">
                          <span class="reply-preview-author"><?= htmlspecialchars($msg['parent_nom_user']) ?></span>
                          <span class="reply-preview-text"><?= htmlspecialchars(truncate_words($msg['parent_contenu'])) ?></span>
                        </div>
                      </div>
                    <?php endif; ?>
                    <div class="bubble-content"><?= render_forum_message($msg['contenu']) ?></div>
                    <?php if ($msg['modifie']): ?>
                      <div class="bubble-footer"><span class="bubble-edited">✏️</span></div>
                    <?php endif; ?>
                  </div>
                  <?php if (!(!$msg['auteur_supprime'] && $msg['user_id'] == $topic['user_id'] && $index === 0)): ?>
                    <?php if ($connected && ($msg['user_id'] == $uid || $is_admin)): ?>
                      <button type="button" class="btn-delete-tiny btn-action-hover"
                        onclick="deleteMessage(<?= $msg['message_id'] ?>)">🗑️</button>
                    <?php endif; ?>
                  <?php endif; ?>
                </div>
              </div>
              <?php
              $next_msg = $messages[$index + 1] ?? null;
              $close_group = !$next_msg || ((int) $next_msg['user_id'] !== $current_user_id) ||
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
            <!-- Indicateur de réponse (caché par défaut) -->
            <div id="reply-indicator" style="display:none;">
              <span>↩ Répondre à <strong id="reply-indicator-name"></strong></span>
              <button type="button" class="clear-reply" onclick="clearReplyTarget()" style="cursor:pointer;color:#f35959;">⨉ Annuler</button>
            </div>
            <form method="post" class="reply-form" id="reply-form">
              <input type="hidden" name="reply" value="1">
              <?= fh_csrf_field() ?>
              <input type="hidden" name="parent_id" id="reply-parent-id" value="">
              <textarea maxlength="2000" name="contenu" id="reply-content" placeholder="Écris un message... (max 2000 caractères)"></textarea>
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
    // Auto-resize textarea
    function adjustHeight(el) {
      el.style.height = "auto";
      el.style.height = (el.scrollHeight) + "px";
    }
    (function () {
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
          const parentId = document.getElementById('reply-parent-id').value;
          if (parentId) formData.append('parent_id', parentId);
          const csrfEl = document.querySelector('input[name="csrf_token"]');
          if (csrfEl) formData.append('csrf_token', csrfEl.value);

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

              // Auto-marquer les notifs de ce topic comme lues (important quand on est sur le même topic)
              const topic_id = <?php echo (int) $topic_id; ?>;
              const fd = new FormData();
              fd.append('topic_id', topic_id);
              const csrfEl2 = document.querySelector('input[name="csrf_token"]');
              if (csrfEl2) fd.append('csrf_token', csrfEl2.value);
              fetch('marquer_notifs_forum_lues.php', { method: 'POST', body: fd }).catch(() => {});

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
    
    function linkify(text) {
    return text.replace(
        /(https?:\/\/[^\s<>"'()]+)/gi,
        '<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>'
      );
    }
    
    function setReplyTarget(messageId, authorName, previewText) {
      document.getElementById('reply-parent-id').value = messageId;
      const indicator = document.getElementById('reply-indicator');
      document.getElementById('reply-indicator-name').textContent = authorName;
      indicator.style.display = 'flex';
      document.getElementById('reply-content').focus();
    }

    function clearReplyTarget() {
        document.getElementById('reply-parent-id').value = '';
        document.getElementById('reply-indicator').style.display = 'none';
    }

    function truncateWords(text, maxWords = 6) {
      const words = text.trim().split(/\s+/);
      if (words.length <= maxWords) return text;
      return words.slice(0, maxWords).join(' ') + '…';
    }

    function addMessageToDOM(message) {
      const container = document.getElementById('messages-container');
      const lastGroup = container.querySelector('.message-group:last-child');
      const isOwn = message.is_own;

      // Preview du parent — intégré dans la bulle
      let parentHtml = '';
      if (message.parent_preview) {
          const prev = message.parent_preview;
          const previewText = truncateWords(prev.contenu);
          parentHtml = `
          <div class="bubble-reply-preview-outer">
              <div class="bubble-reply-preview-inner">
                  <span class="reply-preview-author">${escapeHtml(prev.nom_user)}</span>
                  <span class="reply-preview-text">${escapeHtml(previewText)}</span>
              </div>
          </div>`;
      }

      // Bouton reply (jamais sur nos propres messages)
      const replyBtn = (!isOwn && <?php echo json_encode($connected); ?> && !<?php echo json_encode((bool)$topic['verrouille']); ?>)
          ? `<button type="button" class="btn-reply-tiny btn-action-hover" onclick="setReplyTarget(${message.message_id}, '${escapeHtml(message.nom_user).replace(/'/g,"\\'")}', '${escapeHtml(message.contenu).substring(0,80).replace(/'/g,"\\'")}')">↩</button>`
          : '';

      // Bouton delete
      const deleteBtn = message.can_delete
          ? `<button type="button" class="btn-delete-tiny btn-action-hover" onclick="deleteMessage(${message.message_id})">🗑️</button>`
          : '';

      const wrapper = document.createElement('div');
      wrapper.className = `bubble-wrapper ${isOwn ? 'own' : 'other'}`;
      wrapper.innerHTML = `
          <div class="bubble-with-actions">
              ${replyBtn}
              <div class="message-bubble${message.parent_id ? ' is-reply' : ''}" data-message-id="${message.message_id}" data-timestamp="${message.timestamp}">
                  ${parentHtml}
                  <div class="bubble-content">${linkify(escapeHtml(message.contenu))}</div>
              </div>
              ${deleteBtn}
          </div>`;

      // Réponse = nouveau groupe forcé
      const forceNewGroup = !!message.parent_id;

      if (!forceNewGroup && lastGroup && lastGroup.classList.contains(isOwn ? 'own' : 'other')) {
          lastGroup.querySelector('.group-messages').appendChild(wrapper);
      } else {
          const newGroup = document.createElement('div');
          newGroup.className = `message-group ${isOwn ? 'own' : 'other'}${message.parent_id ? ' is-reply-group' : ''}`;
          newGroup.innerHTML = `
              <div class="group-header">
                  <span class="author-name">${escapeHtml(message.nom_user)}</span>
                  <span class="group-time">${message.date_formatted}</span>
              </div>
              <div class="group-messages"></div>`;
          newGroup.querySelector('.group-messages').appendChild(wrapper);
          container.appendChild(newGroup);
      }

      wrapper.style.animation = 'fadeIn 0.3s ease';
      clearReplyTarget();
    }

    function escapeHtml(text) {
      const div = document.createElement('div');
      div.textContent = text;
      return div.innerHTML;
    }

    // ---- Suppression AJAX (temps réel, sans rechargement) ----
    async function deleteMessage(messageId) {
      if (!confirm('Supprimer ce message ?')) return;

      const formData = new FormData();
      formData.append('ajax', '1');
      formData.append('delete_message_ajax', '1');
      formData.append('message_id', messageId);
      const csrfElDel = document.querySelector('input[name="csrf_token"]');
      if (csrfElDel) formData.append('csrf_token', csrfElDel.value);

      try {
        const response = await fetch(window.location.href, { method: 'POST', body: formData });
        const data = await response.json();

        if (data.success) {
          const bubble = document.querySelector(`.message-bubble[data-message-id="${messageId}"]`);
          if (bubble) {
            const wrapper = bubble.closest('.bubble-wrapper') ?? bubble;
            const groupMessages = wrapper.closest('.group-messages');
            wrapper.remove();
            if (groupMessages && !groupMessages.querySelector('.bubble-wrapper')) {
              groupMessages.closest('.message-group')?.remove();
            }
          }
          // Mettre à jour le last_message_id si nécessaire
          updateLastMessageId();
        } else {
          alert(data.error || 'Erreur lors de la suppression');
        }
      } catch (err) {
        console.error('Erreur suppression:', err);
        alert('Erreur de connexion');
      }
    }

    function updateLastMessageId() {
      const bubbles = document.querySelectorAll('.message-bubble[data-message-id]');
      let max = 0;
      bubbles.forEach(b => {
        const id = parseInt(b.dataset.messageId || 0);
        if (id > max) max = id;
      });
      lastMessageId = max;
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

    // ---- Polling temps réel : récupère les nouveaux messages toutes les 3 secondes ----
    let lastMessageId = <?php echo (int) $last_msg_id; ?>;
    let pollingActive = true;
    let pollingTimer = null;
    let knownMessageIds = new Set();

    async function pollNewMessages() {
      if (!pollingActive) return;
      try {
        const url = `forum_topic.php?topic_id=<?= (int) $topic_id ?>&poll=1&last_message_id=${lastMessageId}`;
        const response = await fetch(url);
        const data = await response.json();

        if (data.success) {
          // Marquer les notifs de ce topic comme lues immédiatement
          const fd = new FormData();
          fd.append('topic_id', <?= (int) $topic_id ?>);
          const csrfElPoll = document.querySelector('input[name="csrf_token"]');
          if (csrfElPoll) fd.append('csrf_token', csrfElPoll.value);
          fetch('marquer_notifs_forum_lues.php', { method: 'POST', body: fd }).catch(() => {});

          // ── Détecter les messages supprimés ──
          if (Array.isArray(data.all_message_ids)) {
            const serverIds = new Set(data.all_message_ids);
            for (let id of knownMessageIds) {
              if (!serverIds.has(id)) {
                // Message supprimé - le retirer du DOM avec animation
                const bubble = document.querySelector(`.message-bubble[data-message-id="${id}"]`);
                if (bubble) {
                  bubble.style.animation = 'fadeOut 0.3s ease';
                  setTimeout(() => {
                    const group = bubble.closest('.message-group');
                    bubble.remove();
                    // Si le groupe est vide, le supprimer aussi
                    if (group && group.querySelector('.group-messages').children.length === 0) {
                      group.remove();
                    }
                  }, 300);
                }
                knownMessageIds.delete(id);
              }
            }
          }

          // ── Ajouter les nouveaux messages ──
          if (data.messages && data.messages.length > 0) {
            const container = document.getElementById('messages-container');
            const wasAtBottom = (container.scrollHeight - container.scrollTop - container.clientHeight) < 60;

            data.messages.forEach(msg => {
              if (!document.querySelector(`.message-bubble[data-message-id="${msg.message_id}"]`)) {
                addMessageToDOM(msg);
                knownMessageIds.add(msg.message_id);
              }
              if (msg.message_id > lastMessageId) lastMessageId = msg.message_id;
            });

            if (wasAtBottom) {
              container.scrollTop = container.scrollHeight;
            }
          }
        }
      } catch (err) {
        // erreur réseau silencieuse
      }
      pollingTimer = setTimeout(pollNewMessages, 3000);
    }

    document.addEventListener('DOMContentLoaded', () => {
      // Initialiser knownMessageIds avec les IDs des messages actuels affichés
      document.querySelectorAll('.message-bubble[data-message-id]').forEach(bubble => {
        knownMessageIds.add(parseInt(bubble.dataset.messageId));
      });
      
      // Marquer automatiquement les notifs de ce topic comme lues
      const topic_id = <?php echo (int) $topic_id; ?>;
      const fd = new FormData();
      fd.append('topic_id', topic_id);
      const csrfElInit = document.querySelector('input[name="csrf_token"]');
      if (csrfElInit) fd.append('csrf_token', csrfElInit.value);
      fetch('marquer_notifs_forum_lues.php', { method: 'POST', body: fd }).catch(() => {});
      
      // Scroll vers le dernier message au chargement
      const messagesContainer = document.getElementById('messages-container');
      if (messagesContainer && messagesContainer.scrollHeight > 0) {
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
      }
      
      pollingTimer = setTimeout(pollNewMessages, 3000);
    });

    document.addEventListener('visibilitychange', () => {
      if (document.hidden) {
        pollingActive = false;
        if (pollingTimer) clearTimeout(pollingTimer);
      } else {
        pollingActive = true;
        pollNewMessages();
      }
    });

  </script>

  <style>
    /* dans forum_topic.css */
  </style>
</body>

</html>