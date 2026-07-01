<?php
// youtube_auth_callback.php
// Callback du flux OAuth YouTube (voir youtube_auth.php).
// Échange le code d'autorisation contre un refresh_token, stocké en base
// dans la table parametres_stream (même mécanisme clé/valeur que stream_smash.php).
session_start();
require_once 'db/config.php';
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/config_google.php';

fh_require_admin($conn);

function yt_set_param(PDO $conn, string $key, string $value): void {
    $conn->prepare("INSERT INTO parametres_stream (cle, valeur) VALUES (?,?) ON DUPLICATE KEY UPDATE valeur=VALUES(valeur)")
         ->execute([$key, $value]);
}

$erreur = null;

// Vérification du state CSRF
if (empty($_GET['state']) || empty($_SESSION['yt_oauth_state']) || $_GET['state'] !== $_SESSION['yt_oauth_state']) {
    $erreur = "Jeton de sécurité invalide (state). Recommence la procédure depuis le panneau admin.";
} elseif (!empty($_GET['error'])) {
    $erreur = "Autorisation refusée ou annulée : " . htmlspecialchars($_GET['error']);
} elseif (empty($_GET['code'])) {
    $erreur = "Aucun code d'autorisation reçu de Google.";
} else {
    $redirect_uri = 'https://foodhub-sio.alwaysdata.net/youtube_auth_callback.php';

    $post_fields = [
        'code'          => $_GET['code'],
        'client_id'     => GOOGLE_CLIENT_ID,
        'client_secret' => GOOGLE_CLIENT_SECRET,
        'redirect_uri'  => $redirect_uri,
        'grant_type'    => 'authorization_code',
    ];

    $context = stream_context_create([
        'http' => [
            'method'  => 'POST',
            'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
            'content' => http_build_query($post_fields),
            'timeout' => 10,
            'ignore_errors' => true,
        ],
    ]);

    $resp = @file_get_contents(GOOGLE_TOKEN_URL, false, $context);
    $data = $resp !== false ? json_decode($resp, true) : null;

    if (!$data || isset($data['error'])) {
        $erreur = "Échec de l'échange du code : " . htmlspecialchars($resp ?: 'réponse vide');
        error_log("[FH youtube_auth] échec échange code : " . ($resp ?: 'réponse vide'));
    } elseif (empty($data['refresh_token'])) {
        // Arrive si l'utilisateur avait déjà autorisé l'app sans repasser par prompt=consent.
        $erreur = "Google n'a pas renvoyé de refresh_token. Révoque l'accès existant sur "
                . "<a href='https://myaccount.google.com/permissions' target='_blank'>myaccount.google.com/permissions</a> "
                . "puis recommence la procédure.";
        error_log("[FH youtube_auth] aucun refresh_token reçu (probablement déjà autorisé sans prompt=consent)");
    } else {
        yt_set_param($conn, 'yt_oauth_refresh_token', $data['refresh_token']);
        // On stocke aussi l'access_token + son expiration pour éviter un échange immédiat inutile
        yt_set_param($conn, 'yt_oauth_access_token', $data['access_token'] ?? '');
        yt_set_param($conn, 'yt_oauth_access_token_expires', (string)(time() + (int)($data['expires_in'] ?? 0) - 60));
        unset($_SESSION['yt_oauth_state']);
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Connexion YouTube - FoodHub Admin</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<main class="container" style="max-width: 600px;">
    <?php if ($erreur): ?>
        <h2>❌ Erreur</h2>
        <p><?= $erreur ?></p>
        <p><a href="youtube_auth.php">Réessayer</a></p>
    <?php else: ?>
        <h2>✅ Chaîne YouTube connectée !</h2>
        <p>FoodHub peut maintenant détecter automatiquement quand tu es en live, même en non répertorié.</p>
    <?php endif; ?>
    <p style="margin-top: 1rem;"><a href="stream_smash.php">← Retour au stream</a></p>
</main>
</body>
</html>
