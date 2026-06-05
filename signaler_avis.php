<?php
// signaler_avis.php
session_start();
require_once 'db/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Non connecté']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
    exit;
}

$user_id       = (int)$_SESSION['user_id'];
$avis_id       = (int)($_POST['avis_id']       ?? 0);
$restaurant_id = (int)($_POST['restaurant_id'] ?? 0);
$raison        = trim($_POST['raison']         ?? '');

if (!$avis_id || !$restaurant_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Données manquantes']);
    exit;
}

// Infos de l'avis + signaleur en une requête
$stmt = $conn->prepare("
    SELECT
        a.commentaire,
        a.user_id                 AS auteur_id,
        u_auteur.nom_user         AS auteur_nom,
        u_signaleur.nom_user      AS signaleur_nom,
        u_signaleur.email         AS signaleur_email,
        r.nom_restaurant
    FROM avis a
    JOIN users u_auteur    ON u_auteur.user_id    = a.user_id
    JOIN users u_signaleur ON u_signaleur.user_id = ?
    JOIN restaurants r     ON r.restaurant_id     = a.restaurant_id
    WHERE a.avis_id = ? AND a.restaurant_id = ?
");
$stmt->execute([$user_id, $avis_id, $restaurant_id]);
$data = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$data) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Avis introuvable']);
    exit;
}

// Pas de auto-signalement
if ((int)$data['auteur_id'] === $user_id) {
    echo json_encode(['success' => false, 'error' => 'Tu ne peux pas signaler ton propre commentaire']);
    exit;
}

// Anti-doublon : même signaleur + même avis
$stmt_dup = $conn->prepare("
    SELECT COUNT(*)
    FROM messages_admin
    WHERE type_message = 'signalement'
      AND sujet LIKE ?
      AND nom = ?
");
$stmt_dup->execute(["%[avis_id:{$avis_id}]%", $data['signaleur_nom']]);
if ($stmt_dup->fetchColumn() > 0) {
    echo json_encode(['success' => false, 'error' => 'Tu as déjà signalé ce commentaire']);
    exit;
}

// Sujet structuré — permet à admin_messages.php de retrouver les IDs
$sujet = "[avis_id:{$avis_id}][resto_id:{$restaurant_id}] Signalement — {$data['nom_restaurant']}";

$raison_txt = $raison !== '' ? $raison : 'Non précisée';
$lien = "menu.php?restaurant_id={$restaurant_id}#comment-{$avis_id}";

$message  = "🚩 Signalement soumis par : {$data['signaleur_nom']}\n";
$message .= "📍 Restaurant : {$data['nom_restaurant']} (ID : {$restaurant_id})\n";
$message .= "💬 Auteur du commentaire : {$data['auteur_nom']}\n";
$message .= "🔗 Lien direct : {$lien}\n\n";
$message .= "📝 Commentaire signalé :\n« {$data['commentaire']} »\n\n";
$message .= "📋 Raison du signalement : {$raison_txt}";

// INSERT dans la table existante — aucune migration requise
$conn->prepare("
    INSERT INTO messages_admin (nom, email, sujet, message, type_message)
    VALUES (?, ?, ?, ?, 'signalement')
")->execute([
    $data['signaleur_nom'],
    $data['signaleur_email'],
    $sujet,
    $message,
]);

echo json_encode(['success' => true]);
exit;