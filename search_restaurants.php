<?php
// search_restaurants.php - Recherche AJAX (restaurants / plats / catégories)
session_start();
require_once 'db/config.php';

header('Content-Type: application/json; charset=utf-8');

$q = trim($_GET['q'] ?? '');

if (mb_strlen($q) < 2) {
    echo json_encode(['results' => []]);
    exit;
}

// Échappe les caractères spéciaux LIKE (% _ \) pour éviter tout comportement
// inattendu si l'utilisateur les saisit lui-même.
function fh_like_escape(string $str): string {
    return addcslashes($str, '%_\\');
}
$like = '%' . fh_like_escape($q) . '%';

$results = [];

/* Restaurants (nom ou catégorie), uniquement les restaurants vérifiés */
$stmt = $conn->prepare("
    SELECT restaurant_id, nom_restaurant, categorie
    FROM restaurants
    WHERE verified = 1
      AND (nom_restaurant LIKE ? ESCAPE '\\\\' OR categorie LIKE ? ESCAPE '\\\\')
    ORDER BY nom_restaurant
    LIMIT 5
");
$stmt->execute([$like, $like]);
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $results[] = [
        'type'     => 'restaurant',
        'label'    => $r['nom_restaurant'],
        'sublabel' => $r['categorie'] ?: '',
        'url'      => 'menu.php?restaurant_id=' . (int)$r['restaurant_id'],
    ];
}

/* Plats (nom), uniquement ceux appartenant à des restaurants vérifiés */
$stmt = $conn->prepare("
    SELECT p.plat_id, p.nom_plat, p.restaurant_id, r.nom_restaurant
    FROM plats p
    JOIN restaurants r ON p.restaurant_id = r.restaurant_id
    WHERE r.verified = 1
      AND p.nom_plat LIKE ? ESCAPE '\\\\'
    ORDER BY p.nom_plat
    LIMIT 5
");
$stmt->execute([$like]);
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $p) {
    $results[] = [
        'type'     => 'plat',
        'label'    => $p['nom_plat'],
        'sublabel' => $p['nom_restaurant'],
        'url'      => 'menu.php?restaurant_id=' . (int)$p['restaurant_id'] . '#plat-' . (int)$p['plat_id'],
    ];
}

echo json_encode(['results' => $results], JSON_UNESCAPED_UNICODE);
