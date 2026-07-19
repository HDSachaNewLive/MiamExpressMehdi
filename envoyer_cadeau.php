<?php
// envoyer_cadeau.php
session_start();
require_once 'db/config.php';
require_once __DIR__ . '/csrf_helper.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Non connecté']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

if (empty($_POST['csrf_token']) || !fh_verify_csrf($_POST['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Session expirée, recharge la page.']);
    exit;
}

$uid             = (int)$_SESSION['user_id'];
$destinataire_id = (int)($_POST['destinataire_id'] ?? 0);
$montant         = (float)($_POST['montant'] ?? 0);
$message         = trim($_POST['message'] ?? '');
if (mb_strlen($message) > 255) {
    $message = mb_substr($message, 0, 255);
}

// Validation de base
if ($destinataire_id <= 0) {
    echo json_encode(['success' => false, 'message' => "Choisis un destinataire dans la liste proposée."]);
    exit;
}
if ($destinataire_id === $uid) {
    echo json_encode(['success' => false, 'message' => "Tu ne peux pas t'envoyer un cadeau à toi-même 😅"]);
    exit;
}
if ($montant < 1 || $montant > 500) {
    echo json_encode(['success' => false, 'message' => "Le montant doit être compris entre 1€ et 500€."]);
    exit;
}
$montant = round($montant, 2);

// Vérifier que le destinataire existe et est actif
$stmt = $conn->prepare("SELECT nom_user, compte_actif FROM users WHERE user_id = ?");
$stmt->execute([$destinataire_id]);
$dest = $stmt->fetch();
if (!$dest || !$dest['compte_actif']) {
    echo json_encode(['success' => false, 'message' => "Ce destinataire est introuvable ou son compte est désactivé."]);
    exit;
}

// Vérifier le solde de l'expéditeur
$stmt = $conn->prepare("SELECT solde, nom_user FROM users WHERE user_id = ?");
$stmt->execute([$uid]);
$expediteur = $stmt->fetch();
$solde_actuel = (float)($expediteur['solde'] ?? 0);

if ($solde_actuel < $montant) {
    echo json_encode([
        'success' => false,
        'message' => "Solde insuffisant : tu as seulement " . number_format($solde_actuel, 2) . " € disponible."
    ]);
    exit;
}

try {
    $conn->beginTransaction();

    // Déduire chez l'expéditeur avec re-vérification atomique (évite une race condition)
    $deduc = $conn->prepare("UPDATE users SET solde = solde - ? WHERE user_id = ? AND solde >= ?");
    $deduc->execute([$montant, $uid, $montant]);
    if ($deduc->rowCount() === 0) {
        throw new Exception('Solde insuffisant au moment de l\'envoi.');
    }

    // Créditer le destinataire
    $conn->prepare("UPDATE users SET solde = solde + ? WHERE user_id = ?")->execute([$montant, $destinataire_id]);

    // Enregistrer le cadeau
    $ins = $conn->prepare("INSERT INTO cadeaux (expediteur_id, destinataire_id, montant, message, date_envoi) VALUES (?, ?, ?, ?, NOW())");
    $ins->execute([$uid, $destinataire_id, $montant, $message !== '' ? $message : null]);
    $cadeau_id = $conn->lastInsertId();

    // Notifier le destinataire
    $notif_msg = htmlspecialchars($expediteur['nom_user']) . " t'a envoyé un cadeau de " . number_format($montant, 2) . " € !";
    $conn->prepare("
        INSERT INTO notifications (user_id, type, restaurant_id, avis_id, cadeau_id, message, is_read, created_at)
        VALUES (?, 'cadeau', NULL, NULL, ?, ?, 0, NOW())
    ")->execute([$destinataire_id, $cadeau_id, $notif_msg]);

    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Cadeau envoyé avec succès à ' . htmlspecialchars($dest['nom_user']) . ' !',
        'montant' => number_format($montant, 2, '.', ''),
        'destinataire_nom' => htmlspecialchars($dest['nom_user']),
        'date_transaction' => date('d/m/Y H:i'),
        'nouveau_solde' => number_format($solde_actuel - $montant, 2, '.', '')
    ]);

} catch (Exception $e) {
    $conn->rollBack();
    error_log('[envoyer_cadeau] Erreur: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erreur serveur, réessaie plus tard.']);
}
