<?php
// delete_account.php
session_start();
require_once 'db/config.php';
require_once 'mail_helper.php';
require_once 'delete_user_helper.php'; // ← nouveau

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$uid = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once 'csrf_helper.php';
    if (empty($_POST['csrf_token']) || !fh_verify_csrf($_POST['csrf_token'])) {
        $_SESSION['delete_error'] = 'Jeton CSRF invalide.';
        header('Location: profile.php');
        exit;
    }
    try {
        fh_delete_user($conn, $uid, $_SESSION['google_token'] ?? null);

        $_SESSION = [];
        session_destroy();

        header("Location: index.php?deleted=1");
        exit;

    } catch (Exception $e) {
        error_log('[delete_account] Erreur suppression compte ' . $uid . ' : ' . $e->getMessage());
        $_SESSION['delete_error'] = 'Erreur serveur lors de la suppression du compte. Contacte l\'administrateur.';
        header('Location: profile.php');
        exit;
    }
}

header("Location: profile.php");
exit;