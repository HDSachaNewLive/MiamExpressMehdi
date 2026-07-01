<?php
// youtube_auth.php
// Page d'autorisation OAuth — à utiliser UNE SEULE FOIS (ou si le jeton expire/est révoqué)
// pour autoriser FoodHub à lire le statut de live de TA chaîne YouTube, y compris
// les lives non répertoriés. Réservé à l'admin/propriétaire.
session_start();
require_once 'db/config.php';
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/config_google.php';

fh_require_admin($conn);

// CSRF basique pour le paramètre state
if (empty($_SESSION['yt_oauth_state'])) {
    $_SESSION['yt_oauth_state'] = bin2hex(random_bytes(16));
}

$redirect_uri = 'https://foodhub-sio.alwaysdata.net/youtube_auth_callback.php';

$params = [
    'client_id'     => GOOGLE_CLIENT_ID,
    'redirect_uri'  => $redirect_uri,
    'response_type' => 'code',
    'scope'         => 'https://www.googleapis.com/auth/youtube.readonly',
    'access_type'   => 'offline', // requis pour obtenir un refresh_token
    'prompt'        => 'consent', // force le re-consentement pour garantir un refresh_token même en cas de ré-autorisation
    'state'         => $_SESSION['yt_oauth_state'],
];

$auth_url = GOOGLE_AUTH_URL . '?' . http_build_query($params);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Connecter YouTube - FoodHub Admin</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<main class="container" style="max-width: 600px;">
    <h2>🔗 Connecter ta chaîne YouTube</h2>
    <p>Cette étape autorise FoodHub à consulter le statut de live de ta chaîne YouTube, <strong>y compris les streams non répertoriés</strong>, ce que l'API publique classique ne permet pas.</p>
    <p>Tu n'as besoin de faire ça <strong>qu'une seule fois</strong> (sauf si tu révoques l'accès depuis ton compte Google).</p>
    <p style="margin-top: 1.5rem;">
        <a href="<?= htmlspecialchars($auth_url) ?>" class="btn">Autoriser avec Google</a>
    </p>
    <p style="margin-top: 1rem;"><a href="stream_smash.php">← Retour</a></p>
</main>
</body>
</html>
