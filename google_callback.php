<?php
// google_callback.php
// en gros mon reuf ce putain de script du turfu il traite le retour de Google OAuth et il gère l'authentification FoodHub (si ça c'est pas magnifique..)

session_start();
require_once 'db/config.php';
require_once 'config_google.php';


// 1. Vérifications de sécu(-rité sociale mdr)


// Vérifier qu'on a reçu un code d'autorisation
if (!isset($_GET['code'])) {
    $_SESSION['error_login'] = "❌ Connexion Google annulée ou échouée.";
    header('Location: login.php');
    exit;
}

// Vérifier le paramètre state pour prévenir les attaques CSRF
if (!isset($_GET['state']) || !isset($_SESSION['google_oauth_state'])
    || $_GET['state'] !== $_SESSION['google_oauth_state']) {
    $_SESSION['error_login'] = "❌ Erreur de sécurité (state invalide). Réessayez.";
    unset($_SESSION['google_oauth_state']);
    header('Location: login.php');
    exit;
}
unset($_SESSION['google_oauth_state']); // consommé, on le supprime

$code = $_GET['code'];


// echange le code contre un token d'acces
$token_data = [
    'code' => $code,
    'client_id' => GOOGLE_CLIENT_ID,
    'client_secret' => GOOGLE_CLIENT_SECRET,
    'redirect_uri' => GOOGLE_REDIRECT_URI,
    'grant_type'=> 'authorization_code',
];

$context = stream_context_create([
    'http' => [
        'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
        'method'  => 'POST',
        'content' => http_build_query($token_data),
    ],
]);

$token_response = @file_get_contents(GOOGLE_TOKEN_URL, false, $context);

if ($token_response === false) {
    $_SESSION['error_login'] = "❌ Impossible de contacter les serveurs Google. Réessayez plus tard.";
    header('Location: login.php');
    exit;
}

$token = json_decode($token_response, true);

if (empty($token['access_token'])) {
    $_SESSION['error_login'] = "❌ Token Google invalide. Réessayez.";
    header('Location: login.php');
    exit;
}


// récup les informations du compte Google
$info_context = stream_context_create([
    'http' => [
        'header' => "Authorization: Bearer " . $token['access_token'] . "\r\n",
        'method' => 'GET',
    ],
]);

$user_info_response = @file_get_contents(GOOGLE_USERINFO_URL, false, $info_context);

if ($user_info_response === false) {
    $_SESSION['error_login'] = "❌ Impossible de récupérer vos informations Google.";
    header('Location: login.php');
    exit;
}

$google_user = json_decode($user_info_response, true);

// Données récupérées depuis Google
$google_id    = $google_user['id'] ?? null; // identifiant unique Google
$google_email = $google_user['email'] ?? null; // adresse email
$google_name  = $google_user['name'] ?? null;// nom complet
$google_photo = $google_user['picture'] ?? null;// URL photo de profil Google si dispo

// Vérifier que les données essentielles sont présentes
if (empty($google_id) || empty($google_email)) {
    $_SESSION['error_login'] = "❌ Données Google incomplètes (email manquant).";
    header('Location: login.php');
    exit;
}

// connexion ou création de compte
try {
    // cas 1 : un compte FoodHub existe déjà avec ce google_id
    $stmt = $conn->prepare("SELECT * FROM users WHERE google_id = ? LIMIT 1");
    $stmt->execute([$google_id]);
    $existing_by_google = $stmt->fetch();

    if ($existing_by_google) {
        // Compte lié à Google : vérifier qu'il est actif
        if (!$existing_by_google['compte_actif']) {
            $_SESSION['error_login'] = "❌ Votre compte a été désactivé. Contactez l'administrateur.";
            header('Location: login.php');
            exit;
        }

        // Mettre à jour uniquement la photo Google (le nom est géré par l'utilisateur dans son profil FoodHub)
        $conn->prepare("UPDATE users SET google_photo = ? WHERE user_id = ?")
             ->execute([$google_photo, $existing_by_google['user_id']]);

        // Recharger pour avoir les données à jour en session
        $stmt2 = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
        $stmt2->execute([$existing_by_google['user_id']]);
        $refreshed = $stmt2->fetch();

        _set_session($refreshed);
        header('Location: home.php');
        exit;
    }

    // cas 2 : un compte FoodHub classique existe avec le même email
    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$google_email]);
    $existing_by_email = $stmt->fetch();

    if ($existing_by_email) {
        // Vérifier qu'il est actif
        if (!$existing_by_email['compte_actif']) {
            $_SESSION['error_login'] = "❌ Votre compte a été désactivé. Contactez l'administrateur.";
            header('Location: login.php');
            exit;
        }

        // Liaison du compte Google : on stocke uniquement google_id et google_photo,
        // le nom FoodHub de l'utilisateur est conservé tel quel
        $conn->prepare("UPDATE users SET google_id = ?, google_photo = ? WHERE user_id = ?")
             ->execute([$google_id, $google_photo, $existing_by_email['user_id']]);

        // refresh les donénes
        $stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
        $stmt->execute([$existing_by_email['user_id']]);
        $updated_user = $stmt->fetch();

        _set_session($updated_user);
        $_SESSION['success'] = "✅ Votre compte Google a été lié à votre compte FoodHub existant !";
        header('Location: home.php');
        exit;
    }

    // cas 3 : aucun compte existant == créer un nouveau compte FoodHub

    // Générer un mot de passe inutilisable (l'utilisateur peut se connectee via Google uniquement (cheh))
    $fake_password = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);

    $stmt = $conn->prepare("
        INSERT INTO users (nom_user, email, motdepasse, type_compte, google_id, google_photo, compte_actif, date_creation)
        VALUES (?, ?, ?, 'client', ?, ?, 1, NOW())
    ");
    $stmt->execute([
        $google_name,
        $google_email,
        $fake_password,
        $google_id,
        $google_photo,
    ]);

    $new_user_id = $conn->lastInsertId();

    //recharger le compte qui vient d'etre créé
    $stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->execute([$new_user_id]);
    $new_user = $stmt->fetch();

    _set_session($new_user);
    $_SESSION['google_new_account'] = true; // flag pour afficher la page de complétion
    header('Location: completer_profil.php');
    exit;

} catch (PDOException $e) {
    // En cas d'erreur BDD colonnes google_id / google_photo manquantes
    $_SESSION['error_login'] = "❌ Erreur serveur : " . htmlspecialchars($e->getMessage())
                              . "\n— Avez-vous bien exécuté la migration SQL ?";
    header('Location: login.php');
    exit;
}

// Fonction : mettre les donner qu'il faut dans la session FoodHub

function _set_session(array $user): void {
    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['nom_user'] = $user['nom_user'];
    $_SESSION['type_compte'] = $user['type_compte'];
    $_SESSION['adresse_livraison'] = $user['adresse_livraison'] ?? '';
    $_SESSION['google_connected'] = true; // flag optionnel pour l'UI
}