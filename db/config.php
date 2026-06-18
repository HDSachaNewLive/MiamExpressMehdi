<?php
// db/config.php
require_once __DIR__ . '/../vendor/autoload.php';

$servername = "mysql-foodhub-sio.alwaysdata.net";
$username = "foodhub-sio";
$password = getenv('DB_PASS');
$dbname = "foodhub-sio_db";
 
try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8mb4", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    error_log("[FoodHub] Connexion BDD échouée : " . $e->getMessage());
    // En CLI (cron), pas de http_response_code ni de die HTML
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, "[FoodHub] Connexion BDD échouée : " . $e->getMessage() . "\n");
        exit(1);
    }
    http_response_code(503);
    die("🍽️ FoodHub est momentanément indisponible. Réessayez dans quelques instants.");
}

// Logique web uniquement (ignorée en CLI/cron)
if (PHP_SAPI !== 'cli') {
    require_once __DIR__ . '/../not_found_helper.php';
    //Vérification compte désactivé
    //Pages qui ont PAS besoin de cette vérification
    $page_actuelle = basename($_SERVER['PHP_SELF']);
    $pages_exclues = [
    'desactive.php', 'logout.php', 'contact_admin.php', 'index.php',
    'register.php', 'login.php', 'reset_password.php', 'verify_email.php',
    'apropos.php', 'tos.php', 'credits-remerciements.php', '404.php',
    ];
    if (
        session_status() === PHP_SESSION_ACTIVE &&
        isset($_SESSION['user_id']) &&
        !in_array($page_actuelle, $pages_exclues)
    ) {
        $stmt_actif = $conn->prepare("SELECT compte_actif FROM users WHERE user_id = ?");
        $stmt_actif->execute([(int)$_SESSION['user_id']]);
        $user_actif = $stmt_actif->fetchColumn();

        if ($user_actif === '0' || $user_actif === 0) {
            header("Location: /desactive.php");
            exit;
        }
    }

    // Tracking sessions compteur
    if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['user_id'])) {
        try {
            $conn->prepare("
                INSERT INTO sessions_actives (session_id, user_id, derniere_activite)
                VALUES (?, ?, NOW())
                ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), derniere_activite = NOW()
            ")->execute([session_id(), (int)$_SESSION['user_id']]);

            // Nettoyage des sessions inactives 1 fois sur 10
            if (rand(1, 10) === 1) {
                $conn->exec("DELETE FROM sessions_actives WHERE derniere_activite < (NOW() - INTERVAL 5 MINUTE)");
            }
        } catch (PDOException $e) {
            // si la table existe pas encore, ça casse rien
        }
    }
}
?>
