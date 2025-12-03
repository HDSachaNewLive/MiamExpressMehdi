<?php
// surprise_me.php - Génère une sélection aléatoire de plats dans home.php
session_start();
require_once 'db/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'not_logged']);
    exit;
}

$budget_min = isset($_POST['budget_min']) ? (float)$_POST['budget_min'] : 0;
$budget_max = isset($_POST['budget_max']) ? (float)$_POST['budget_max'] : 100;

// Validation
if ($budget_min >= $budget_max) {
    echo json_encode(['error' => 'invalid_budget', 'message' => 'Budget minimum doit être inférieur au maximum']);
    exit;
}

// requête sql mon gars ? et ? = valeurs de budget_min et budget_max
$sql = "
    SELECT pl.plat_id, pl.nom_plat, pl.prix, pl.description_plat, r.nom_restaurant, r.restaurant_id
    FROM plats pl
    JOIN restaurants r ON pl.restaurant_id = r.restaurant_id
    WHERE r.verified = 1
    AND pl.prix BETWEEN ? AND ?
    ORDER BY RAND() 
    LIMIT 5
";

try {
    $stmt = $conn->prepare($sql);
    $stmt->execute([$budget_min, $budget_max]);
    $plats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($plats)) {
        echo json_encode(['error' => 'no_results', 'message' => 'Aucun plat trouvé dans cette gamme de prix']);
        exit;
    }
    
    // Calcul du total
    $total = 0;
    foreach ($plats as $plat) {
        $total += (float)$plat['prix'];
    }
    
    echo json_encode([
        'success' => true,
        'plats' => $plats,
        'total' => number_format($total, 2, '.', ''),
        'count' => count($plats)
    ]);
    
} catch (Exception $e) {
    echo json_encode(['error' => 'server_error', 'message' => $e->getMessage()]);
}
?>