<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false]);
    exit;
}

require_once 'db/config.php';
$uid = (int)$_SESSION['user_id'];

// Supprimer le flag nouveau_compte maintenant que le JS confirme que l'animation a été jouée
unset($_SESSION['nouveau_compte']);

$conn->prepare("
    UPDATE users
    SET derniere_connexion = COALESCE(derniere_connexion, NOW())
    WHERE user_id = ?
")->execute([$uid]);

echo json_encode(['ok' => true]);
