<?php
//get_recommendations.php
session_start();
require_once 'db/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_POST['plat_id'])) {
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

$plat_id = (int)$_POST['plat_id'];

// Récupérer le restaurant_id du plat
$stmt = $conn->prepare("SELECT restaurant_id FROM plats WHERE plat_id = ?");
$stmt->execute([$plat_id]);
$plat = $stmt->fetch();

if (!$plat) {
    echo json_encode(['error' => 'Plat introuvable']);
    exit;
}

$restaurant_id = $plat['restaurant_id'];

// explications Requête SQL pour trouver les plats commandés ensemble
// pour chaque plat commandé avec le plat_id sélectionné,
// on compte combien de fois ils ont ete commandés ensemble
//on calcule le pourcentage (sur toutes les commandes contenant le plat)
//on filtre pour garder uniquement ceux commandés pluss de 3 fois ensemble
//on limite à 3 recommandations max

$sql = "
    SELECT 
        p2.plat_id,
        p2.nom_plat,
        p2.prix,
        COUNT(*) as frequency,
        ROUND((COUNT(*) * 100.0 / (
            SELECT COUNT(DISTINCT cp1.commande_id) 
            FROM commande_plats cp1 
            WHERE cp1.plat_id = ?
        )), 0) as percentage
    FROM commande_plats cp1
    JOIN commande_plats cp2 ON cp1.commande_id = cp2.commande_id
    JOIN plats p2 ON cp2.plat_id = p2.plat_id
    WHERE cp1.plat_id = ?
      AND cp2.plat_id != ?
      AND p2.restaurant_id = ?
    GROUP BY p2.plat_id
    HAVING frequency >= 3
    ORDER BY frequency DESC
    LIMIT 3
";

$stmt = $conn->prepare($sql);
$stmt->execute([$plat_id, $plat_id, $plat_id, $restaurant_id]);
$recommendations = $stmt->fetchAll(PDO::FETCH_ASSOC); //ici on stocke le résultat de la requete pour l'afficher dans le message

echo json_encode([
    'success' => true,
    'recommendations' => $recommendations
]);