<?php
//logout.php
session_start();
require_once 'db/config.php';

// Supprimer la session du compteur en ligne
$conn->prepare("DELETE FROM sessions_actives WHERE session_id = ?")
     ->execute([session_id()]);

$_SESSION = [];
session_destroy();
header("Location: index.php");
exit;
?>