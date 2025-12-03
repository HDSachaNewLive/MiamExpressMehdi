<?php
session_start();
require_once 'db/config.php';

if (!isset($_SESSION['user_id']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(403);
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$panier_id = (int)($_POST['panier_id'] ?? 0);
$quantite = max(1, (int)($_POST['quantite'] ?? 1));

$stmt = $conn->prepare("UPDATE panier SET quantite=? WHERE panier_id=? AND user_id=?");
$stmt->execute([$quantite, $panier_id, $user_id]);

http_response_code(200);
