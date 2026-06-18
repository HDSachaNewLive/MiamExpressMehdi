<?php
// auth_helper.php
// Fonctions d'autorisation centralisées pour FoodHub

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Détermine si l'utilisateur courant est administrateur.
 * Logique :
 * - si la session contient explicitement `type_compte === 'admin'` -> admin
 * - si la colonne `role` existe en base et vaut 'admin', on l'utilise (optionnel)
 *
 * @param PDO|null $conn
 * @return bool
 */
function fh_is_admin(?PDO $conn = null): bool {
    if (empty($_SESSION['user_id'])) return false;

    if (isset($_SESSION['is_admin'])) {
        return (bool)$_SESSION['is_admin'];
    }

    if (isset($_SESSION['type_compte']) && $_SESSION['type_compte'] === 'admin') {
        $_SESSION['is_admin'] = true;
        return true;
    }

    // Vérifier colonne `role` si présente en base (préférer un rôle explicite)
    if ($conn) {
        try {
            $stmt = $conn->prepare('SELECT role FROM users WHERE user_id = ? LIMIT 1');
            $stmt->execute([(int)$_SESSION['user_id']]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row && isset($row['role']) && $row['role'] === 'admin') {
                $_SESSION['is_admin'] = true;
                return true;
            }
        } catch (Exception $e) {
            // Ignorer les erreurs ici : absence de colonne, droits, etc.
        }
    }

    // NOTE: Le fallback historique `user_id === 1` a été retiré pour empêcher
    // l'élévation de privilèges accidentelle. Assurez-vous que les comptes
    // administrateurs ont `type_compte = 'admin'` ou `role = 'admin'` en base.
    $_SESSION['is_admin'] = false;
    return false;
}

/**
 * Enforce admin access. Envoie 403 JSON pour requêtes AJAX, ou redirige vers index.
 *
 * @param PDO|null $conn
 * @return void
 */
function fh_require_admin(?PDO $conn = null): void {
    if (!fh_is_admin($conn)) {
        // Détecter requête AJAX/JSON
        $isAjax = (strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest')
               || (stripos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false)
               || (isset($_POST['ajax']) || isset($_POST['ajax_forgot']) || isset($_POST['ajax_reset_fictif']));

        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Non autorisé.']);
            exit;
        }

        header('Location: index.php');
        exit;
    }
}

/**
 * Retourne l'ID de l'utilisateur courant si connecté
 */
function fh_current_user_id(): ?int {
    return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
}
