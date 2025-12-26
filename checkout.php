<?php
// checkout.php
session_start();
require_once 'db/config.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
$uid = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: panier.php'); exit; }

$adresse = trim($_POST['adresse_livraison'] ?? $_SESSION['adresse_livraison'] ?? '');
$mode = in_array($_POST['mode_paiement'] ?? 'carte', ['carte','paypal','livraison']) ? $_POST['mode_paiement'] : 'carte';

// Récupérer panier avec restaurant_id
$stmt = $conn->prepare("
    SELECT p.plat_id, p.quantite, pl.prix, pl.restaurant_id
    FROM panier p 
    JOIN plats pl ON p.plat_id = pl.plat_id 
    WHERE p.user_id = ?
");
$stmt->execute([$uid]);
$items = $stmt->fetchAll();
if (empty($items)) { header('Location: panier.php'); exit; }

// Calcul total et eligible_total
$total = 0;
$eligible_total = 0;
foreach ($items as $it) {
    $item_total = $it['prix'] * $it['quantite'];
    $total += $item_total;
}

// Vérifier et appliquer le coupon
$coupon_applied = null;
$discount_amount = 0;
$coupon_id = null;

if (isset($_SESSION['coupon'])) {
    $coupon = $_SESSION['coupon'];
    
    // Revérifier la validité du coupon
    $stmt = $conn->prepare("SELECT * FROM coupons WHERE coupon_id = ? AND actif = 1");
    $stmt->execute([$coupon['coupon_id']]);
    $coupon_check = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($coupon_check && 
        $coupon_check['date_debut'] <= date('Y-m-d H:i:s') && 
        $coupon_check['date_fin'] >= date('Y-m-d H:i:s') &&
        (!$coupon_check['utilisation_max'] || $coupon_check['utilisations'] < $coupon_check['utilisation_max'])) {
        
        $coupon_applied = $coupon_check;
        $coupon_id = $coupon_check['coupon_id'];
        
        // Vérifier si le coupon est limité à un restaurant
        if ($coupon_applied['restaurant_id']) {
            // Calculer eligible_total pour ce restaurant uniquement
            foreach ($items as $it) {
                if ($it['restaurant_id'] == $coupon_applied['restaurant_id']) {
                    $eligible_total += $it['prix'] * $it['quantite'];
                }
            }
            
            if ($eligible_total > 0) {
                // Appliquer la réduction sur les articles éligibles
                if ($coupon_applied['type'] === 'pourcentage') {
                    $discount_amount = ($eligible_total * $coupon_applied['valeur']) / 100;
                } else {
                    $discount_amount = min($coupon_applied['valeur'], $eligible_total);
                }
            }
        } else {
            // Coupon valable sur tout le panier
            if ($coupon_applied['type'] === 'pourcentage') {
                $discount_amount = ($total * $coupon_applied['valeur']) / 100;
            } else {
                $discount_amount = min($coupon_applied['valeur'], $total);
            }
        }
    }
}
//gestion de utilisation solde
$solde_utilise = 0;
$use_solde = isset($_POST['use_solde']) && $_POST['use_solde'] == '1';

if ($use_solde) {
    $solde_amount = (float)($_POST['solde_amount'] ?? 0);
    
    //récup le solde actuel de l'utilisateur
    $stmt = $conn->prepare("SELECT solde FROM users WHERE user_id = ?");
    $stmt->execute([$uid]);
    $user_data = $stmt->fetch();
    $solde_disponible = (float)($user_data['solde'] ?? 0);
    
    // Vérifier que le montant demandé est valide
    if ($solde_amount > 0 && $solde_amount <= $solde_disponible) {
        // Ne pas utiliser plus que le total de la commande
        $solde_utilise = min($solde_amount, $total - $discount_amount);
    }
}

$final_total = max(0, $total - $discount_amount - $solde_utilise);

// Numéro utilisateur pour cette commande
$stmt = $conn->prepare("SELECT MAX(numero_utilisateur) AS max_num FROM commandes WHERE user_id = ?");
$stmt->execute([$uid]);
$maxNum = $stmt->fetch()['max_num'] ?? 0;
$numero_utilisateur = $maxNum + 1;

// Transaction pour garantir la cohérence
$conn->beginTransaction();

try {
    // INSERT unique dans commandes
    $stmt = $conn->prepare("
    INSERT INTO commandes (user_id, numero_utilisateur, montant_total, montant_reduction, montant_solde_utilise, coupon_id, mode_paiement, date_commande, statut) 
    VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), 'en_attente')
    ");
    $stmt->execute([$uid, $numero_utilisateur, $final_total, $discount_amount, $solde_utilise, $coupon_id, $mode]);
    $commande_id = $conn->lastInsertId();

    // Si du solde a été utilisé, le déduire du compte utilisateur
    if ($solde_utilise > 0) {
        $stmt = $conn->prepare("UPDATE users SET solde = solde - ? WHERE user_id = ?");
        $stmt->execute([$solde_utilise, $uid]);
        
        // Enregistrer l'utilisation du solde
        $stmt = $conn->prepare("
            INSERT INTO utilisations_solde (user_id, commande_id, montant_utilise, date_utilisation) 
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$uid, $commande_id, $solde_utilise]);
    }
    
    // Insérer lignes
    $ins_item = $conn->prepare("INSERT INTO commande_plats (commande_id, plat_id, quantite, prix_unitaire) VALUES (?, ?, ?, ?)");
    foreach ($items as $it) {
        $ins_item->execute([$commande_id, $it['plat_id'], $it['quantite'], $it['prix']]);
    }

    // Si un coupon a été utilisé, incrémenter son compteur
    if ($coupon_id) {
        $stmt = $conn->prepare("UPDATE coupons SET utilisations = utilisations + 1 WHERE coupon_id = ?");
        $stmt->execute([$coupon_id]);
    }

    // Vider panier
    $del = $conn->prepare("DELETE FROM panier WHERE user_id = ?");
    $del->execute([$uid]);
    
    // Retirer le coupon de la session
    unset($_SESSION['coupon']);
    
    $conn->commit();
    
    // Rediriger vers paiement simulé
    header("Location: paiement_simule.php?commande_id=".$commande_id);
    exit;
    
} catch (Exception $e) {
    $conn->rollBack();
    die("Erreur lors de la création de la commande : " . $e->getMessage());
}
?>