<?php
// edit_comment.php
session_start();
require_once 'db/config.php';
require_once 'csrf_helper.php';

if (!isset($_SESSION['user_id'])) {
    if (isset($_POST['ajax_edit_comment'])) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => 'Non connecté.']);
        exit;
    }
    header('Location: login.php'); exit;
}

$uid      = (int)$_SESSION['user_id'];
$is_ajax  = isset($_POST['ajax_edit_comment']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Vérification CSRF
    if (empty($_POST['csrf_token']) || !fh_verify_csrf($_POST['csrf_token'])) {
        if ($is_ajax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'error' => 'Jeton CSRF invalide.']);
            exit;
        }
        $_SESSION['message'] = 'Jeton CSRF invalide.';
        header('Location: ' . ($restaurant_id > 0 ? "menu.php?restaurant_id=$restaurant_id" : 'restaurants.php'));
        exit;
    }
    $comment_id    = (int)($_POST['comment_id']    ?? 0);
    $restaurant_id = (int)($_POST['restaurant_id'] ?? 0);
    $new_comment   = trim($_POST['new_comment']    ?? '');

    if ($is_ajax) { header('Content-Type: application/json; charset=utf-8'); }

    if ($new_comment === '') {
        if ($is_ajax) { echo json_encode(['success' => false, 'error' => 'Le commentaire est vide.']); exit; }
        $_SESSION['message'] = "⚠️ Ton commentaire est vide.";
        header("Location: " . ($restaurant_id > 0 ? "menu.php?restaurant_id=$restaurant_id" : "restaurants.php")); exit;
    }

    if ($comment_id > 0) {
        $stmt = $conn->prepare("SELECT restaurant_id FROM avis WHERE avis_id = ? AND user_id = ?");
        $stmt->execute([$comment_id, $uid]);
        $avis = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($avis) {
            $conn->prepare("UPDATE avis SET commentaire = ? WHERE avis_id = ?")
                 ->execute([$new_comment, $comment_id]);

            if ($is_ajax) { echo json_encode(['success' => true]); exit; }
            $_SESSION['message'] = "💾 Commentaire modifié avec succès !";
            header("Location: menu.php?restaurant_id=" . $avis['restaurant_id']); exit;
        }
    }

    if ($is_ajax) { echo json_encode(['success' => false, 'error' => 'Commentaire introuvable ou non autorisé.']); exit; }
}

header("Location: restaurants.php");
exit;