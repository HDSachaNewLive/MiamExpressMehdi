<?php
// google_login.php
// Redirige l'utilisateur vers la page de connexion Google

session_start();
require_once 'config_google.php';

// Générer un état aléatoire pour prévenir les attaques CSRF (CSCquoi?)
$state = bin2hex(random_bytes(16));
$_SESSION['google_oauth_state'] = $state;

// Construire les paramètres de la requête OAuth
$params = [
    'response_type' => 'code',
    'client_id' => GOOGLE_CLIENT_ID,
    'redirect_uri' => GOOGLE_REDIRECT_URI,
    'scope' => GOOGLE_SCOPES,
    'state' => $state,
    // Forcer la sélection du compte à chaque connexion parce que j'y ai pensé
];

$url = GOOGLE_AUTH_URL . '?' . http_build_query($params);

header('Location: ' . $url);
exit;
