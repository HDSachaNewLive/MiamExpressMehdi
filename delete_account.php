<?php
// delete_account.php
session_start();
require_once 'db/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$uid = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $conn->beginTransaction();

        // 1. Supprimer les restaurants et les plats associés
        $stmt = $conn->prepare("SELECT restaurant_id FROM restaurants WHERE proprietaire_id = ?");
        $stmt->execute([$uid]);
        $restaurants = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (!empty($restaurants)) {
            $in = str_repeat('?,', count($restaurants)-1) . '?';
            // Supprimer les plats liés
            $conn->prepare("DELETE FROM plats WHERE restaurant_id IN ($in)")->execute($restaurants);
            // Supprimer les restaurants
            $conn->prepare("DELETE FROM restaurants WHERE restaurant_id IN ($in)")->execute($restaurants);
        }

        // 2. Supprimer TOUS les votes (likes/dislikes) de l'utilisateur
        $conn->prepare("DELETE FROM avis_votes WHERE user_id = ?")->execute([$uid]);

        // 3. Supprimer les notifications
        $conn->prepare("DELETE FROM notifications WHERE user_id = ?")->execute([$uid]);

        // 4. Mettre à jour les compteurs de likes/dislikes dans la table avis
        $conn->prepare("
            UPDATE avis 
            SET likes = (
                SELECT COUNT(*) 
                FROM avis_votes 
                WHERE avis_id = avis.avis_id 
                AND type = 'like'
            ),
            dislikes = (
                SELECT COUNT(*) 
                FROM avis_votes 
                WHERE avis_id = avis.avis_id 
                AND type = 'dislike'
            )"
        )->execute();

        // 5. Supprimer l'utilisateur (et le reste en cascade)
        $conn->prepare("DELETE FROM users WHERE user_id = ?")->execute([$uid]);

        $conn->commit();

        $_SESSION = [];
        session_destroy();
        header("Location: index.php");
        exit;

    } catch (Exception $e) {
        $conn->rollBack();
        die("Erreur lors de la suppression du compte : " . $e->getMessage());
    }
}

header("Location: profile.php");
exit;
