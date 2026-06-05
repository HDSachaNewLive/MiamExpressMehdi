<?php
// recalcul_panier.php
session_start();
require_once 'db/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Non connecté']);
    exit;
}

$user_id = (int)$_SESSION['user_id'];

// Récupérer les items du panier depuis la BDD
$stmt = $conn->prepare("
    SELECT p.panier_id, pl.plat_id, pl.nom_plat AS plat_nom, pl.prix, p.quantite,
           r.nom_restaurant AS resto_nom, r.restaurant_id
    FROM panier p
    JOIN plats pl ON p.plat_id = pl.plat_id
    JOIN restaurants r ON pl.restaurant_id = r.restaurant_id
    WHERE p.user_id = ?
");
$stmt->execute([$user_id]);
$items = $stmt->fetchAll();

// Calcul des totaux
$total = 0;
$eligible_total = 0;
$discount_amount = 0;
$coupon_applied = $_SESSION['coupon'] ?? null;
$coupon_error = null;

$lignes = [];
foreach ($items as $it) {
    $sous_total = $it['prix'] * $it['quantite'];
    $total += $sous_total;

    if ($coupon_applied && $coupon_applied['restaurant_id']) {
        if ($it['restaurant_id'] == $coupon_applied['restaurant_id']) {
            $eligible_total += $sous_total;
        }
    }

    $lignes[] = [
        'panier_id'  => $it['panier_id'],
        'sous_total' => number_format($sous_total, 2, '.', ''),
    ];
}

// Calcul réduction
if ($coupon_applied) {
    if ($coupon_applied['restaurant_id']) {
        if ($eligible_total == 0) {
            $coupon_error = "⚠️ Ce coupon n'est valable que pour un restaurant spécifique qui n'est pas dans votre panier.";
            unset($_SESSION['coupon']);
            $coupon_applied = null;
        } else {
            if ($coupon_applied['type'] === 'pourcentage') {
                $discount_amount = ($eligible_total * $coupon_applied['valeur']) / 100;
            } else {
                $discount_amount = min($coupon_applied['valeur'], $eligible_total);
            }
        }
    } else {
        if ($coupon_applied['type'] === 'pourcentage') {
            $discount_amount = ($total * $coupon_applied['valeur']) / 100;
        } else {
            $discount_amount = min($coupon_applied['valeur'], $total);
        }
    }
}

$final_total = max(0, $total - $discount_amount);

echo json_encode([
    'lignes'          => $lignes,
    'sous_total'      => number_format($total, 2, '.', ''),
    'reduction'       => number_format($discount_amount, 2, '.', ''),
    'total'           => number_format($final_total, 2, '.', ''),
    'coupon_error'    => $coupon_error,
    'has_reduction'   => $discount_amount > 0,
]);
