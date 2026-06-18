<?php
// parametres.php
session_start();
require_once 'db/config.php';
require_once __DIR__ . '/csrf_helper.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$uid = (int)$_SESSION['user_id'];
$msg = '';

// Récupérer ou créer les préférences
$stmt = $conn->prepare("SELECT * FROM user_preferences WHERE user_id = ? LIMIT 1");
$stmt->execute([$uid]);
$prefs = $stmt->fetch();

if (!$prefs) {
    $conn->prepare("INSERT IGNORE INTO user_preferences (user_id, notif_forum_actif, reduire_animations, profil_prive) VALUES (?, 1, 0, 0)")
         ->execute([$uid]);
    $stmt->execute([$uid]);
    $prefs = $stmt->fetch();
}

if ($msg && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $msg = '';
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sauvegarder_prefs'])) {
    if (empty($_POST['csrf_token']) || !fh_verify_csrf($_POST['csrf_token'])) {
        $_SESSION['pref_msg'] = 'Jeton CSRF invalide.';
        header('Location: parametres.php');
        exit;
    }

    $notif_forum        = isset($_POST['notif_forum_actif'])  ? 1 : 0;
    $reduire_animations = isset($_POST['reduire_animations']) ? 1 : 0;
    $profil_prive       = isset($_POST['profil_prive'])       ? 1 : 0;

    $conn->prepare("
        UPDATE user_preferences
        SET notif_forum_actif  = ?,
            reduire_animations = ?,
            profil_prive       = ?
        WHERE user_id = ?
    ")->execute([$notif_forum, $reduire_animations, $profil_prive, $uid]);

    $_SESSION['pref_msg'] = '✅ Préférences sauvegardées !';
    header('Location: parametres.php');
    exit;
}

// Flash message
if (isset($_SESSION['pref_msg'])) {
    $msg = $_SESSION['pref_msg'];
    unset($_SESSION['pref_msg']);
}

// Récupérer les infos de l'utilisateur
$stmt_user = $conn->prepare("SELECT nom_user, type_compte FROM users WHERE user_id = ?");
$stmt_user->execute([$uid]);
$user = $stmt_user->fetch();

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <link rel="icon" type="image/x-icon" href="FoodHubLogo.ico">
    <meta charset="UTF-8">
    <title>Paramètres - FoodHub</title>
    <link rel="stylesheet" href="assets/style.css">
    <?php include 'sidebar.php'; ?>
</head>
<body>
<audio id="player" autoplay loop>
    <source src="https://raw.githubusercontent.com/HDSachaNewLive/foodhub-assets/main/News - Nintendo Switch Music.mp3" type="audio/mpeg">
</audio>
<?php include 'slider_son.php'; ?>
<style>
    #volume-slider { background: linear-gradient(135deg, #33b0d2ff, #58edf5ff); }
    #volume-button { background: linear-gradient(135deg, #33b0d2ff, #58edf5ff); }
</style>

<main class="container">
    <h2>⚙️ Paramètres</h2>

    <?php if ($msg): ?>
        <div class="success"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <form method="POST" class="form-parametres">
        <input type="hidden" name="sauvegarder_prefs" value="1">
        <?= fh_csrf_field() ?>

        <!-- Section Notifications Forum -->
        <div class="pref-section">
            <h3>🔔 Notifications du Forum</h3>
            <p class="pref-description">
                Quand un nouveau message est posté dans un sujet du forum, une notification apparaît en temps réel sur toutes les pages du site.
            </p>

            <label class="toggle-label" for="notif_forum_actif">
                <div class="toggle-info">
                    <span class="toggle-titre">Notifications forum en temps réel</span>
                    <span class="toggle-sous-titre">Afficher un widget avec les nouveaux messages de forum</span>
                </div>
                <div class="toggle-wrapper">
                    <input
                        type="checkbox"
                        id="notif_forum_actif"
                        name="notif_forum_actif"
                        <?= $prefs['notif_forum_actif'] ? 'checked' : '' ?>
                        class="toggle-checkbox"
                    >
                    <span class="toggle-slider"></span>
                </div>
            </label>
        </div>

        <!-- Section Profil public -->
        <div class="pref-section">
            <h3>👤 Profil public</h3>
            <p class="pref-description">
                Contrôlez la visibilité de votre profil public auprès des autres utilisateurs du site.
            </p>

            <label class="toggle-label" for="profil_prive">
                <div class="toggle-info">
                    <span class="toggle-titre">Restreindre l'accès à mon profil public</span>
                    <span class="toggle-sous-titre">Les autres utilisateurs verront un message indiquant que votre profil est privé - vous pourrez toujours le consulter vous-même</span>
                </div>
                <div class="toggle-wrapper">
                    <input
                        type="checkbox"
                        id="profil_prive"
                        name="profil_prive"
                        <?= ($prefs['profil_prive'] ?? 0) ? 'checked' : '' ?>
                        class="toggle-checkbox"
                    >
                    <span class="toggle-slider"></span>
                </div>
            </label>
        </div>

        <!-- Section Accessibilité -->
        <div class="pref-section">
            <h3>♿ Accessibilité</h3>
            <p class="pref-description">
                Options pour améliorer le confort visuel et réduire la charge sur votre appareil.
            </p>

            <label class="toggle-label" for="reduire_animations">
                <div class="toggle-info">
                    <span class="toggle-titre">Réduire les animations</span>
                    <span class="toggle-sous-titre">Gèle l'arrière-plan animé sur toutes les pages</span>
                </div>
                <div class="toggle-wrapper">
                    <input
                        type="checkbox"
                        id="reduire_animations"
                        name="reduire_animations"
                        <?= ($prefs['reduire_animations'] ?? 0) ? 'checked' : '' ?>
                        class="toggle-checkbox"
                    >
                    <span class="toggle-slider"></span>
                </div>
            </label>
        </div>

        <button type="submit" class="btn-sauvegarder">💾 Sauvegarder les paramètres</button>
    </form>

    <p><a href="home.php" class="back-link">← Retour à l'accueil</a></p>
    <p><a href="<?= $user['type_compte'] === 'proprietaire' ? 'profile_proprio.php' : 'profile.php' ?>" class="back-link">← Profil</a></p>
</main>

<script src="https://cdn.jsdelivr.net/npm/three@0.149.0/build/three.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/vanta/dist/vanta.waves.min.js"></script>
<script>
window.vantaEffect = VANTA.WAVES({
    el: "body",
    mouseControls: true,
    touchControls: true,
    gyroControls: false,
    minHeight: 885.00,
    minWidth: 200.00,
    scale: 1.00,
    scaleMobile: 1.00,
    color: 0x7cc6e6,
    shininess: 25,
    waveHeight: 25,
    waveSpeed: 0.9,
    zoom: 0.9
});
</script>

<style>
.container {
    max-width: 700px;
    margin: 100px auto;
    padding: 2.5rem;
    backdrop-filter: blur(15px);
    background: rgba(255, 255, 255, 0.25);
    border-radius: 1.5rem;
    box-shadow: 0 8px 30px rgba(0,0,0,0.2);
    font-family: 'HSR', sans-serif;
    padding-bottom: 17px;
}

.container h2 {
    text-align: center;
    color: #7cc6e6;
    margin-bottom: 2rem;
    font-size: 2.1rem;
    text-shadow: 2px 2px 4px rgba(124, 198, 230, 0.3);
}

.success {
    background: rgba(0, 255, 127, 0.25);
    padding: 12px 20px;
    border-radius: 10px;
    margin-bottom: 1.5rem;
    border-left: 4px solid #00ff7f;
    color: #006837;
    font-weight: 600;
}

.pref-section {
    background: rgba(255, 255, 255, 0.2);
    backdrop-filter: blur(10px);
    border-radius: 1.2rem;
    padding: 1.8rem;
    margin-bottom: 2rem;
    border: 1px solid rgba(124, 198, 230, 0.25);
}

.pref-section h3 {
    color: #7cc6e6;
    margin: 0 0 0.5rem 0;
    font-size: 1.3rem;
}

.pref-description {
    color: #555;
    font-size: 0.9rem;
    line-height: 1.5;
    margin-bottom: 1.5rem;
}

/* Toggle switch */
.toggle-label {
    display: flex;
    justify-content: space-between;
    align-items: center;
    cursor: pointer;
    padding: 1rem 1.2rem;
    background: rgba(255, 255, 255, 0.3);
    border-radius: 1rem;
    border: 1px solid rgba(124, 198, 230, 0.2);
    transition: all 0.3s ease;
    gap: 1rem;
}

.toggle-label:hover {
    background: rgba(255, 255, 255, 0.45);
    border-color: rgba(124, 198, 230, 0.4);
}

.toggle-info {
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
    flex: 1;
}

.toggle-titre {
    font-weight: 700;
    color: #333;
    font-size: 1rem;
}

.toggle-sous-titre {
    font-size: 0.82rem;
    color: #666;
    line-height: 1.4;
}

.toggle-wrapper {
    position: relative;
    width: 54px;
    height: 28px;
    flex-shrink: 0;
}

.toggle-checkbox {
    opacity: 0;
    width: 0;
    height: 0;
    position: absolute;
}

.toggle-slider {
    position: absolute;
    inset: 0;
    background: rgba(158, 158, 158, 0.4);
    border-radius: 28px;
    transition: all 0.35s ease;
    cursor: pointer;
}

.toggle-slider::before {
    content: "";
    position: absolute;
    width: 22px;
    height: 22px;
    left: 3px;
    top: 3px;
    background: white;
    border-radius: 50%;
    transition: all 0.35s ease;
    box-shadow: 0 2px 6px rgba(0,0,0,0.2);
}

.toggle-checkbox:checked + .toggle-slider {
    background: linear-gradient(135deg, #7cc6e6, #5ab3d8);
    box-shadow: 0 4px 14px rgba(124, 198, 230, 0.4);
}

.toggle-checkbox:checked + .toggle-slider::before {
    transform: translateX(26px);
}

.btn-sauvegarder { 
    display: block;
    justify-self: center;
    width: 55%;
    padding: 1rem;
    font-size: 1.1rem;
    font-weight: 700;
    color: #fff;
    background: #51add560;
    border: none;
    border-radius: 14px;
    cursor: pointer;
    font-family: 'HSR', sans-serif;
    box-shadow: 0 6px 18px rgba(124, 198, 230, 0.4);
    transition: all 0.35s ease;
    margin-bottom: 1.5rem;
    backdrop-filter: blur(15px);
    margin-top: 15px;
}

.btn-sauvegarder:hover {
    transform: translateY(-3px) scale(1.02);
    box-shadow: 0 10px 25px rgba(124, 198, 230, 0.55);
    background: #7cc6e6;
}

.back-link {
    display: inline-block;
    margin-top: 0.5rem;
    color: #7cc6e6;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s ease;
}

.back-link:hover {
    color: #5ab3d8;
    transform: translateX(-4px);
}
</style>
</body>
</html>
