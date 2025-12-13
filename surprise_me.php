<?php
// surprise_me.php - Version avec contrainte sur le total
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

// sélection plats aléatoires dont le total respecte le budget
//plusieurs tentatives et on garde celle qui correspond le mieux au budget
// ? et ? valeur de bugdet_min et budget_max
$sql = "
    SELECT pl.plat_id, pl.nom_plat, pl.prix, pl.description_plat, r.nom_restaurant, r.restaurant_id
    FROM plats pl
    JOIN restaurants r ON pl.restaurant_id = r.restaurant_id
    WHERE r.verified = 1
    AND pl.prix <= ?
    ORDER BY RAND()
";

try {
    $stmt = $conn->prepare($sql);
    $stmt->execute([$budget_max]);
    $all_plats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($all_plats)) {
        echo json_encode(['error' => 'no_results', 'message' => 'Aucun plat trouvé dans cette gamme de prix']);
        exit;
    }
    
    //on ajoute des plats tant que le total reste dans le budget
    $selected_plats = [];
    $current_total = 0;
    
    foreach ($all_plats as $plat) {
        $plat_prix = (float)$plat['prix'];
        
        // Si on peut ajouter ce plat sans dépasser budget_max
        if ($current_total + $plat_prix <= $budget_max) {
            $selected_plats[] = $plat;
            $current_total += $plat_prix;
            
            // Si on a atteint budget_min et qu'on a au moins 3 plats, on peut s'arrêter
            if ($current_total >= $budget_min && count($selected_plats) >= 3) {
                break;
            }
            
            // Maximum 5 plats
            if (count($selected_plats) >= 5) {
                break;
            }
        }
    }
    
    // Vérifier que le total est bien dans le budget demandé
    if ($current_total < $budget_min) {
        echo json_encode([
            'error' => 'no_results', 
            'message' => 'Impossible de trouver une sélection dans cette gamme de prix. Essayez d\'augmenter votre budget.'
        ]);
        exit;
    }
    
    if (empty($selected_plats)) {
        echo json_encode(['error' => 'no_results', 'message' => 'Aucune sélection possible dans cette gamme de prix']);
        exit;
    }
    
    echo json_encode([
        'success' => true,
        'plats' => $selected_plats,
        'total' => number_format($current_total, 2, '.', ''),
        'count' => count($selected_plats)
    ]);
    
} catch (Exception $e) {
    echo json_encode(['error' => 'server_error', 'message' => $e->getMessage()]);
}
?>