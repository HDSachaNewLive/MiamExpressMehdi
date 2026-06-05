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
    try {
        fh_delete_user($conn, $uid, $_SESSION['google_token'] ?? null);

        $_SESSION = [];
        session_destroy();

        header("Location: index.php?deleted=1");
        exit;

    } catch (Exception $e) {
        die("Erreur lors de la suppression du compte : " . htmlspecialchars($e->getMessage()));
    }
}

header("Location: profile.php");
exit;