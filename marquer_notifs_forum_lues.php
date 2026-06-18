<?php
// marquer_notifs_forum_lues.php
// Appelé quand l'utilisateur ouvre un topic : marque les notifs liées comme lues
session_start();
require_once 'db/config.php';
require_once __DIR__ . '/csrf_helper.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['succes' => false]);
    exit;
}

$uid      = (int)$_SESSION['user_id'];
$topic_id = (int)($_POST['topic_id'] ?? 0);

// Vérification CSRF optionnelle
if (isset($_POST['csrf_token']) && !fh_verify_csrf($_POST['csrf_token'])) {
    echo json_encode(['succes' => false, 'erreur' => 'CSRF invalide']);
    exit;
}

if (!$topic_id) {
    echo json_encode(['succes' => false, 'erreur' => 'topic_id manquant']);
    exit;
}

$stmt = $conn->prepare("
    UPDATE forum_notifs
    SET is_read = 1
    WHERE user_id = ? AND topic_id = ? AND is_read = 0
");
$stmt->execute([$uid, $topic_id]);

echo json_encode(['succes' => true, 'modifies' => $stmt->rowCount()]);
exit;
