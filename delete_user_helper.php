<?php
// delete_user_helper.php
// Fonction partagée de suppression d'un compte utilisateur.
// Utilisée par delete_account.php (auto-suppression) et admin_users.php (suppression admin).

require_once __DIR__ . '/mail_helper.php';

/**
 * Supprime complètement un utilisateur et toutes ses données liées.
 * Envoie un email de confirmation si l'email est réel et vérifié.
 *
 * @param PDO         $conn        Connexion PDO active
 * @param int         $uid         ID de l'utilisateur à supprimer
 * @param string|null $google_token Token Google à révoquer (null si non applicable)
 * @throws Exception En cas d'erreur lors de la suppression
 */
function fh_delete_user(PDO $conn, int $uid, ?string $google_token = null): void
{
    $conn->beginTransaction();

    try {
        // 1. Récupérer les informations avant suppression
        $stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
        $stmt->execute([$uid]);
        $user = $stmt->fetch();

        if (!$user) {
            throw new Exception("Utilisateur non trouvé");
        }

        // 2. Supprimer les images de profil
        if (!empty($user['photo_profil']) && file_exists(__DIR__ . '/' . $user['photo_profil'])) {
            @unlink(__DIR__ . '/' . $user['photo_profil']);
        }

        // 3. Supprimer les images d'avis de l'utilisateur
        $stmt = $conn->prepare("SELECT image_path FROM avis WHERE user_id = ?");
        $stmt->execute([$uid]);
        $avis_images = $stmt->fetchAll(PDO::FETCH_COLUMN);
        foreach ($avis_images as $img) {
            if (!empty($img) && file_exists(__DIR__ . '/' . $img)) {
                @unlink(__DIR__ . '/' . $img);
            }
        }

        // 4. Supprimer les restaurants et les plats du propriétaire
        $stmt = $conn->prepare("SELECT restaurant_id FROM restaurants WHERE proprietaire_id = ?");
        $stmt->execute([$uid]);
        $restaurants = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (!empty($restaurants)) {
            $in = str_repeat('?,', count($restaurants) - 1) . '?';

            // Supprimer les images des plats
            $stmt = $conn->prepare("SELECT image_path FROM plats WHERE restaurant_id IN ($in)");
            $stmt->execute($restaurants);
            $plat_images = $stmt->fetchAll(PDO::FETCH_COLUMN);
            foreach ($plat_images as $img) {
                if (!empty($img) && file_exists(__DIR__ . '/' . $img)) {
                    @unlink(__DIR__ . '/' . $img);
                }
            }

            $conn->prepare("DELETE FROM plats WHERE restaurant_id IN ($in)")->execute($restaurants);
            $conn->prepare("DELETE FROM avis WHERE restaurant_id IN ($in)")->execute($restaurants);
            $conn->prepare("DELETE FROM restaurants WHERE restaurant_id IN ($in)")->execute($restaurants);
        }

        // 5. Transférer les messages de forum à l'admin en marquant auteur supprimé
        $conn->prepare("UPDATE forum_messages SET user_id = 1, auteur_supprime = 1 WHERE user_id = ?")->execute([$uid]);

        // 6. Supprimer les avis de l'utilisateur
        $conn->prepare("DELETE FROM avis WHERE user_id = ?")->execute([$uid]);

        // 7. Supprimer les votes (likes/dislikes)
        $conn->prepare("DELETE FROM avis_votes WHERE user_id = ?")->execute([$uid]);

        // 8. Mettre à jour les compteurs de likes/dislikes dans la table avis
        $conn->prepare("
            UPDATE avis
            SET likes = (
                SELECT COUNT(*) FROM avis_votes
                WHERE avis_id = avis.avis_id AND type = 'like'
            ),
            dislikes = (
                SELECT COUNT(*) FROM avis_votes
                WHERE avis_id = avis.avis_id AND type = 'dislike'
            )
        ")->execute();

        // 9. Supprimer le panier
        $conn->prepare("DELETE FROM panier WHERE user_id = ?")->execute([$uid]);

        // 10. Supprimer les favoris
        $conn->prepare("DELETE FROM favoris WHERE user_id = ?")->execute([$uid]);

        // 11. Supprimer les commandes et articles associés
        $stmt = $conn->prepare("SELECT commande_id FROM commandes WHERE user_id = ?");
        $stmt->execute([$uid]);
        $commandes = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (!empty($commandes)) {
            $in = str_repeat('?,', count($commandes) - 1) . '?';
            $conn->prepare("DELETE FROM commande_plats WHERE commande_id IN ($in)")->execute($commandes);
            $conn->prepare("DELETE FROM commandes WHERE commande_id IN ($in)")->execute($commandes);
        }

        // 12. Supprimer les notifications
        $conn->prepare("DELETE FROM notifications WHERE user_id = ?")->execute([$uid]);

        // 13. Supprimer les tokens email
        $conn->prepare("DELETE FROM email_tokens WHERE user_id = ?")->execute([$uid]);

        // 14. Supprimer les préférences utilisateur
        $conn->prepare("DELETE FROM user_preferences WHERE user_id = ?")->execute([$uid]);

        // 15. Supprimer les statistiques de profil
        $conn->prepare("DELETE FROM profil_stats WHERE user_id = ?")->execute([$uid]);

        // 16. Supprimer les notifications forum
        $conn->prepare("DELETE FROM forum_notifs WHERE user_id = ?")->execute([$uid]);

        // 17. Transférer les sujets de forum à l'admin en marquant auteur supprimé
        $conn->prepare("UPDATE forum_topics SET user_id = 1, auteur_supprime = 1 WHERE user_id = ?")->execute([$uid]);
        
        // 18. Révoquer le token Google si présent
        if (!empty($google_token)) {
            @file_get_contents("https://oauth2.googleapis.com/revoke?token=" . urlencode($google_token));
        }

        // 19. Envoyer le mail de confirmation de suppression
        if (!empty($user['email']) && !empty($user['email_verifie'])) {
            fh_send_account_deletion_email($user['nom_user'], $user['email']);
        }

        // 20. Supprimer l'utilisateur (en dernier)
        $conn->prepare("DELETE FROM users WHERE user_id = ?")->execute([$uid]);

        $conn->commit();

    } catch (Exception $e) {
        $conn->rollBack();
        throw $e;
    }
}