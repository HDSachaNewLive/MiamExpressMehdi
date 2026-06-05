<?php
// get_forum_notifs.php
// Endpoint AJAX — renvoie les notifications forum non lues pour l'utilisateur connecté
session_start();
require_once 'db/config.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['erreur' => 'non_connecte']);
    exit;
}

$uid = (int)$_SESSION['user_id'];

// Récupérer les préférences de l'utilisateur
$stmt_pref = $conn->prepare("
    SELECT notif_forum_actif
    FROM user_preferences
    WHERE user_id = ?
    LIMIT 1
");
$stmt_pref->execute([$uid]);
$pref = $stmt_pref->fetch();

// Si pas de préférence encore, créer avec valeur par défaut activé
if (!$pref) {
    $conn->prepare("INSERT IGNORE INTO user_preferences (user_id, notif_forum_actif) VALUES (?, 1)")
         ->execute([$uid]);
    $pref = ['notif_forum_actif' => 1];
}

if (!(int)$pref['notif_forum_actif']) {
    echo json_encode(['notifs' => [], 'nb_non_lues' => 0, 'desactive' => true]);
    exit;
}

// Récupérer les notifs non lues (max 30 pour l'historique)
$stmt = $conn->prepare("
    SELECT
        fn.notif_id,
        fn.topic_id,
        fn.message_id,
        fn.topic_titre,
        fn.auteur_nom,
        fn.is_read,
        fn.is_reply,
        fn.created_at
    FROM forum_notifs fn
    WHERE fn.user_id = ? AND fn.is_read = 0
    ORDER BY fn.created_at DESC
    LIMIT 30
");
$stmt->execute([$uid]);
$notifs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Compter uniquement les non lues
$stmt_nb = $conn->prepare("
    SELECT COUNT(*) FROM forum_notifs WHERE user_id = ? AND is_read = 0
");
$stmt_nb->execute([$uid]);
$nb_non_lues = (int)$stmt_nb->fetchColumn();

// Formater les dates pour l'affichage
foreach ($notifs as &$n) {
    $ts = strtotime($n['created_at']);
    $now = time();
    $diff = $now - $ts;

    if ($diff < 60) {
        $n['date_formatee'] = "À l'instant";
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        $n['date_formatee'] = "Il y a {$mins} min";
    } elseif ($diff < 86400) {
        $heures = floor($diff / 3600);
        $n['date_formatee'] = "Il y a {$heures}h";
    } else {
        $n['date_formatee'] = date('d/m/Y H:i', $ts);
    }
}
unset($n);

echo json_encode([
    'notifs'      => $notifs,
    'nb_non_lues' => $nb_non_lues
]);
exit;
