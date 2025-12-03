<?php
// reorder_command.php - Repasser une commande
session_start();
require_once 'db/config.php';

if (!isset($_SESSION['user_id']) || !isset($_GET['commande_id'])) {
    header('Location: suivi_commande.php');
    exit;
}

$uid = (int)$_SESSION['user_id'];
$commande_id = (int)$_GET['commande_id'];

// Vérifier que la commande appartient bien à l'utilisateur
$stmt = $conn->prepare("SELECT * FROM commandes WHERE commande_id = ? AND user_id = ?");
$stmt->execute([$commande_id, $uid]);
$commande = $stmt->fetch();

if (!$commande) {
    $_SESSION['error'] = "Commande introuvable.";
    header('Location: suivi_commande.php');
    exit;
}

// Récupérer les articles de cette commande
$stmt = $conn->prepare("
    SELECT cp.*, pl.nom_plat 
    FROM commande_plats cp
    JOIN plats pl ON cp.plat_id = pl.plat_id
    WHERE cp.commande_id = ?
");
$stmt->execute([$commande_id]);
$items = $stmt->fetchAll();

if (empty($items)) {
    $_SESSION['error'] = "Cette commande ne contient aucun article.";
    header('Location: suivi_commande.php');
    exit;
}

// Vider le panier actuel
$conn->prepare("DELETE FROM panier WHERE user_id = ?")->execute([$uid]);

// Ajouter tous les articles dans le panier
$stmt = $conn->prepare("INSERT INTO panier (user_id, plat_id, quantite) VALUES (?, ?, ?)");

$added_count = 0;
foreach ($items as $item) {
    // Vérifier que le plat existe toujours
    $check = $conn->prepare("SELECT plat_id FROM plats WHERE plat_id = ?");
    $check->execute([$item['plat_id']]);
    
    if ($check->fetch()) {
        $stmt->execute([$uid, $item['plat_id'], $item['quantite']]);
        $added_count++;
    }
}

if ($added_count > 0) {
    $_SESSION['success'] = "✅ $added_count article(s) ajouté(s) à ton panier !";
} else {
    $_SESSION['error'] = "⚠️ Aucun article disponible dans cette commande.";
}

header('Location: panier.php');
exit;
?>