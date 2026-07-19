<?php
// process_approvisionnement.php
session_start();
require_once 'db/config.php';
require_once __DIR__ . '/csrf_helper.php';

header('Content-Type: application/json');

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

$uid          = (int)$_SESSION['user_id'];
$montant      = (float)($_POST['montant'] ?? 0);
$card_number  = $_POST['card_number'] ?? '';
$card_expiry  = $_POST['card_expiry'] ?? '';
$card_cvv     = $_POST['card_cvv'] ?? '';
$card_name    = trim($_POST['card_name'] ?? '');

// Validation montant
if ($montant < 5 || $montant > 500) {
    echo json_encode(['success' => false, 'message' => 'Montant invalide (5€ - 500€)']);
    exit;
}

// Validation numéro de carte (simulé, jamais stocké en base)
$card_clean = preg_replace('/\s/', '', $card_number);
if (strlen($card_clean) !== 16 || !ctype_digit($card_clean)) {
    echo json_encode(['success' => false, 'message' => 'Numéro de carte invalide']);
    exit;
}

// Validation date d'expiration + vérifier que la carte n'est pas expirée
if (!preg_match('/^(\d{2})\/(\d{2})$/', $card_expiry, $m)) {
    echo json_encode(['success' => false, 'message' => 'Date d\'expiration invalide']);
    exit;
}
$exp_mois = (int)$m[1];
$exp_an   = 2000 + (int)$m[2];
if ($exp_mois < 1 || $exp_mois > 12 || mktime(0, 0, 0, $exp_mois + 1, 1, $exp_an) < time()) {
    echo json_encode(['success' => false, 'message' => 'Carte expirée ou date invalide']);
    exit;
}

// Validation CVV
if (strlen($card_cvv) !== 3 || !ctype_digit($card_cvv)) {
    echo json_encode(['success' => false, 'message' => 'CVV invalide']);
    exit;
}

if ($card_name === '') {
    echo json_encode(['success' => false, 'message' => 'Nom du titulaire requis']);
    exit;
}

try {
    $conn->beginTransaction();

    $stmt = $conn->prepare("UPDATE users SET solde = solde + ? WHERE user_id = ?");
    $stmt->execute([$montant, $uid]);

    // Seuls les 4 derniers chiffres sont conservés — jamais le numéro complet ni le CVV
    $carte_masquee = '**** **** **** ' . substr($card_clean, -4);

    $stmt = $conn->prepare("
        INSERT INTO approvisionnements (user_id, montant, carte_masquee, date_approvisionnement, statut)
        VALUES (?, ?, ?, NOW(), 'validé')
    ");
    $stmt->execute([$uid, $montant, $carte_masquee]);

    // Récupérer le nouveau solde réel pour l'animation côté client
    $stmtSolde = $conn->prepare("SELECT solde FROM users WHERE user_id = ?");
    $stmtSolde->execute([$uid]);
    $nouveau_solde = (float)$stmtSolde->fetchColumn();

    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Approvisionnement réussi',
        'montant' => number_format($montant, 2, '.', ''),
        'nouveau_solde' => number_format($nouveau_solde, 2, '.', ''),
        'carte_masquee' => $carte_masquee,
        'date_transaction' => date('d/m/Y H:i')
    ]);

} catch (Exception $e) {
    $conn->rollBack();
    error_log('[process_approvisionnement] Erreur: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erreur serveur.']);
}
