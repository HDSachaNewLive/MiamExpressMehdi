<?php
// search_users.php
// Recherche d'utilisateurs pour l'autocomplétion du destinataire d'un cadeau
session_start();
require_once 'db/config.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([]);
    exit;
}

$uid = (int)$_SESSION['user_id'];
$q = trim($_GET['q'] ?? '');

// Minimum 2 caractères pour lancer la recherche
if (mb_strlen($q) < 2) {
    echo json_encode([]);
    exit;
}

$stmt = $conn->prepare("
    SELECT user_id, nom_user
    FROM users
    WHERE nom_user LIKE ?
      AND user_id != ?
      AND compte_actif = 1
    ORDER BY nom_user ASC
    LIMIT 8
");
$stmt->execute(['%' . $q . '%', $uid]);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($results);
