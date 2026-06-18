<?php
// update_order_status.php
session_start();
require_once 'db/config.php';
header('Content-Type: application/json');
require_once __DIR__ . '/csrf_helper.php';
require_once __DIR__ . '/auth_helper.php';

// Doit être connecté
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Non autorisé.']);
    exit;
}

if (!isset($_POST['commande_id'])) {
    echo json_encode(['success' => false, 'message' => 'Commande non spécifiée.']);
    exit;
}

// Vérification CSRF obligatoire : le token doit être présent et valide.
if (empty($_POST['csrf_token']) || !fh_verify_csrf($_POST['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Jeton CSRF invalide ou manquant.']);
    exit;
}

$commande_id = (int)$_POST['commande_id'];
$uid = (int)$_SESSION['user_id'];

// Récupérer la commande avec son propriétaire de restaurant
$stmt = $conn->prepare("
    SELECT c.statut, c.user_id AS client_id, r.proprietaire_id
    FROM commandes c
    JOIN commande_plats cp ON cp.commande_id = c.commande_id
    JOIN plats p ON p.plat_id = cp.plat_id
    JOIN restaurants r ON r.restaurant_id = p.restaurant_id
    WHERE c.commande_id = ?
    LIMIT 1
");
$stmt->execute([$commande_id]);
$cmd = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$cmd) {
    echo json_encode(['success' => false, 'message' => 'Commande introuvable.']);
    exit;
}

// Seul le propriétaire du restaurant OU l'admin peuvent faire avancer une commande
// Le client lui-même ne peut PAS avancer sa propre commande (il peut juste l'annuler via suivi_commande.php)
$is_admin = fh_is_admin($conn);
$is_proprio = ($uid === (int)$cmd['proprietaire_id']);

if (!$is_admin && !$is_proprio) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Non autorisé.']);
    exit;
}

// Définir le prochain statut
$nextStatus = [
    'en_attente'     => 'en_preparation',
    'en_preparation' => 'en_livraison',
    'en_livraison'   => 'livree'
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
