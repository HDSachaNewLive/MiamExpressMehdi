<?php
session_start();
require_once 'db/config.php';

header('Content-Type: application/json');

$count = 0;
if (isset($_SESSION['user_id'])) {
    $stmt = $conn->prepare("SELECT COALESCE(SUM(quantite), 0) FROM panier WHERE user_id = ?");
    $stmt->execute([(int)$_SESSION['user_id']]);
    $count = (int)$stmt->fetchColumn();
}
echo json_encode(['count' => $count]);