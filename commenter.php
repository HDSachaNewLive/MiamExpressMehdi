<?php
// commenter.php
session_start();
require_once 'db/config.php';
require_once 'upload_helper.php';
require_once 'csrf_helper.php';
if (!isset($_SESSION['user_id'])) {
    if (isset($_POST['ajax_avis'])) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => 'Non connecté.']);
        exit;
    }
    header('Location: login.php'); exit;
}
$uid = (int)$_SESSION['user_id'];
$is_ajax = isset($_POST['ajax_avis']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $restaurant_id = (int)($_POST['restaurant_id'] ?? 0);
    // Vérification CSRF
    if (empty($_POST['csrf_token']) || !fh_verify_csrf($_POST['csrf_token'])) {
        if ($is_ajax) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Jeton CSRF invalide.']);
            exit;
        }
        $_SESSION['message'] = 'Jeton CSRF invalide.';
        header("Location: menu.php?restaurant_id=" . $restaurant_id);
        exit;
    }
    $note          = max(1, min(5, (int)($_POST['note'] ?? 5)));
    $commentaire   = trim($_POST['commentaire'] ?? '');

    if ($is_ajax) { header('Content-Type: application/json; charset=utf-8'); }

    if ($restaurant_id <= 0 || $commentaire === '') {
        if ($is_ajax) { echo json_encode(['success' => false, 'error' => 'Données manquantes.']); exit; }
        header("Location: menu.php?restaurant_id=" . $restaurant_id); exit;
    }

    // Vérifier qu'un avis n'existe pas déjà
    $chk = $conn->prepare("SELECT COUNT(*) FROM avis WHERE user_id = ? AND restaurant_id = ?");
    $chk->execute([$uid, $restaurant_id]);
    
    // Gestion de l'upload d'image (sécurisée)
    $image_path = null;
    if (isset($_FILES['image_avis']) && $_FILES['image_avis']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/avis/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
        $res = fh_handle_image_upload($_FILES['image_avis'], $upload_dir, 5242880);
        if ($res['success']) {
            $image_path = $upload_dir . $res['filename'];
        }
        // Si erreur d'upload, on ignore l'image (le commentaire est toujours possible)
    }

    $ins = $conn->prepare("INSERT INTO avis (user_id, restaurant_id, note, commentaire, image_path) VALUES (?, ?, ?, ?, ?)");
    $ins->execute([$uid, $restaurant_id, $note, $commentaire, $image_path]);
    $new_avis_id = (int)$conn->lastInsertId();

    // Notif pour le propriétaire
    $stmt2 = $conn->prepare("SELECT proprietaire_id, nom_restaurant FROM restaurants WHERE restaurant_id = ?");
    $stmt2->execute([$restaurant_id]);
    $resto = $stmt2->fetch();
    if ($resto && $resto['proprietaire_id'] != $uid) {
        $message = "Un utilisateur a commenté dans la page de votre restaurant « {$resto['nom_restaurant']} » ";
        $conn->prepare("INSERT INTO notifications (user_id, type, restaurant_id, avis_id, message) VALUES (?, 'comment', ?, ?, ?)")
             ->execute([$resto['proprietaire_id'], $restaurant_id, $new_avis_id, $message]);
    }

    if (!$is_ajax) {
        $_SESSION['message'] = "💬 Commentaire ajouté avec succès !";
        header("Location: menu.php?restaurant_id=" . $restaurant_id);
        exit;
    }

    // Mode AJAX : retourner le HTML du nouveau commentaire
    $uStmt = $conn->prepare("SELECT nom_user FROM users WHERE user_id = ?");
    $uStmt->execute([$uid]);
    $nom_user  = $uStmt->fetchColumn();
    $date_avis = date('Y-m-d H:i:s');

    ob_start();
    ?>
<div class="resto-card comment-card" id="comment-<?= $new_avis_id ?>" style="position:relative;">
    <div style="position:absolute; top:5px; right:10px; display:flex; gap:10px; z-index:10; pointer-events:auto;">
        <button type="button" name="edit" class="btn btn-small edit-btn"
            data-id="<?= $new_avis_id ?>"
            data-comment="<?= htmlspecialchars($commentaire, ENT_QUOTES) ?>">Modifier ✏️</button>
        <form method="post" class="form" onsubmit="return confirm('Supprimer ce commentaire ?');">
            <input type="hidden" name="delete_comment_id" value="<?= $new_avis_id ?>">
            <?= fh_csrf_field() ?>
            <button type="submit" class="btn btn-small">❌</button>
        </form>
    </div>
    <div class="comment-meta">
        <a href="profil_public.php?user_id=<?= $uid ?>" style="text-decoration: none;">
            <strong style="color: #ff6b6b;"><?= htmlspecialchars($nom_user) ?></strong>
        </a>
        <span>— <?= $note ?>★ —</span>
        <small><?= htmlspecialchars($date_avis) ?></small>
    </div>
    <?php if ($image_path): ?>
    <div class="comment-image">
        <img src="<?= htmlspecialchars($image_path) ?>" alt="Photo du commentaire"
             onclick="openImageModal('<?= htmlspecialchars($image_path) ?>')"
             style="cursor:pointer;">
    </div>
    <?php endif; ?>
    <p><?= htmlspecialchars($commentaire) ?></p>
    <form class="form ajax-edit-form" id="edit-form-<?= $new_avis_id ?>"
          data-avis-id="<?= $new_avis_id ?>" data-restaurant-id="<?= $restaurant_id ?>"
          style="display:none; margin-top:10px;">
        <input type="hidden" name="comment_id" value="<?= $new_avis_id ?>">
        <input type="hidden" name="restaurant_id" value="<?= $restaurant_id ?>">
        <textarea name="new_comment" rows="3" class="form"></textarea><br>
        <button type="submit" class="btn btn-small">💾 Enregistrer</button>
        <button type="button" class="btn btn-small" onclick="hideEditForm(<?= $new_avis_id ?>)">❌ Annuler</button>
    </form>
    <div class="reply-container" id="reply-container-<?= $new_avis_id ?>"></div>
    <div class="vote-buttons" data-avis-id="<?= $new_avis_id ?>">
        <button class="like-btn" type="button" aria-label="Like">👍 <span class="count">0</span></button>
        <button class="dislike-btn" type="button" aria-label="Dislike">👎 <span class="count">0</span></button>
    </div>
</div>
    <?php
    $html = ob_get_clean();
    echo json_encode(['success' => true, 'html' => $html]);
    exit;
}

header("Location: menu.php?restaurant_id=" . ($restaurant_id ?? 0));
exit;
