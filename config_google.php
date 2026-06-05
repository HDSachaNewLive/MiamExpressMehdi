<?php
// config_google.php
// Configuration Google OAuth 2.0
// Obtenez vos clés sur https://console.cloud.google.com/

require_once __DIR__ . '/vendor/autoload.php';

define('GOOGLE_CLIENT_ID', getenv('GOOGLE_CLIENT_ID'));
define('GOOGLE_CLIENT_SECRET', getenv('GOOGLE_CLIENT_SECRET'));
define('GOOGLE_REDIRECT_URI', 'https://foodhub-sio.alwaysdata.net/google_callback.php');

if (!GOOGLE_CLIENT_ID || !GOOGLE_CLIENT_SECRET) {
    die('Google OAuth non configuré. Voir README.md');
}

// Scopes demandés à Google (email + profil de base uniquement)
define('GOOGLE_SCOPES', 'openid email profile');

// URL des endpoints Google OAuth
define('GOOGLE_AUTH_URL',     'https://accounts.google.com/o/oauth2/v2/auth');
define('GOOGLE_TOKEN_URL',    'https://oauth2.googleapis.com/token');
define('GOOGLE_USERINFO_URL', 'https://www.googleapis.com/oauth2/v2/userinfo');