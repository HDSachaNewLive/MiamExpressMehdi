<?php
// toggle_favori.php
session_start();
require_once 'db/config.php';
require_once 'csrf_helper.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'not_logged']);
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$restaurant_id = (int)($_POST['restaurant_id'] ?? 0);

if (!$restaurant_id) {
    echo json_encode(['error' => 'invalid_request']);
    exit;
}

// Si un token CSRF est présent, vérifier-le (sinon on reste compatible)
if (isset($_POST['csrf_token']) && !fh_verify_csrf($_POST['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['error' => 'forbidden', 'message' => 'Jeton CSRF invalide.']);
    exit;
}

try {
    // Vérifier si déjà en favori
    $stmt = $conn->prepare("SELECT favori_id FROM favoris WHERE user_id = ? AND restaurant_id = ?");
    $stmt->execute([$user_id, $restaurant_id]);
    
    if ($stmt->fetch()) {
        // Retirer des favoris
        $conn->prepare("DELETE FROM favoris WHERE user_id = ? AND restaurant_id = ?")
             ->execute([$user_id, $restaurant_id]);
        echo json_encode(['success' => true, 'action' => 'removed', 'is_favorite' => false]);
    } else {
        // Ajouter aux favoris
        $conn->prepare("INSERT INTO favoris (user_id, restaurant_id) VALUES (?, ?)")
             ->execute([$user_id, $restaurant_id]);
        echo json_encode(['success' => true, 'action' => 'added', 'is_favorite' => true]);
    }
} catch (Exception $e) {
    error_log('[toggle_favori] Erreur: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'server_error', 'message' => 'Erreur serveur.']);
}