<?php
// update_order_status.php
session_start();
require_once 'db/config.php';
header('Content-Type: application/json');

if (!isset($_POST['commande_id'])) {
    echo json_encode(['success' => false, 'message' => 'Commande non spécifiée.']);
    exit;
}

$commande_id = (int)$_POST['commande_id'];

// Récupérer le statut actuel
$stmt = $conn->prepare("SELECT statut FROM commandes WHERE commande_id = ?");
$stmt->execute([$commande_id]);
$cmd = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$cmd) {
    echo json_encode(['success' => false, 'message' => 'Commande introuvable.']);
    exit;
}

// Définir le prochain statut
$nextStatus = [
    'en_attente' => 'en_preparation',
    'en_preparation' => 'en_livraison',
    'en_livraison' => 'livree'
];

$current = $cmd['statut'];
if (isset($nextStatus[$current])) {
    $newStatus = $nextStatus[$current];
    $stmt = $conn->prepare("UPDATE commandes SET statut = ? WHERE commande_id = ?");
    $stmt->execute([$newStatus, $commande_id]);

    echo json_encode(['success' => true, 'newStatus' => $newStatus]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Pas de statut suivant.']);
