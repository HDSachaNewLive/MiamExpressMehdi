<?php
// admin_notify_list.php
session_start();
require_once '..\db\config.php';

// Vérification admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] != 1) {
    header("Location: index");
    exit;
}

$message = '';
$error   = '';

// ── Ajouter un destinataire ──────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'ajouter') {
    $nom   = trim($_POST['nom']   ?? '');
    $email = trim($_POST['email'] ?? '');

    if ($nom === '' || $email === '') {
        $error = '❌ Nom et email sont requis.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = '❌ Adresse email invalide.';
    } else {
        try {
            $stmt = $conn->prepare("INSERT INTO notify_list (nom, email) VALUES (?, ?)");
            $stmt->execute([$nom, $email]);
            $_SESSION['notify_msg'] = "✅ {$email} ajouté à la liste.";
            header("Location: admin_notify_list");
            exit;
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $error = "❌ Cet email est déjà dans la liste.";
            } else {
                $error = "❌ Erreur : " . $e->getMessage();
            }
        }
    }
}

// ── Supprimer un destinataire ────────────────────────────────
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $conn->prepare("DELETE FROM notify_list WHERE id = ?")->execute([$id]);
    $_SESSION['notify_msg'] = "🗑️ Destinataire supprimé.";
    header("Location: admin_notify_list");
    exit;
}

// ── Activer / désactiver ─────────────────────────────────────
if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $conn->prepare("UPDATE notify_list SET actif = NOT actif WHERE id = ?")->execute([$id]);
    header("Location: admin_notify_list");
    exit;
}

// ── Envoyer manuellement (test) ──────────────────────────────
if (isset($_GET['send_now'])) {
    // On lance le script CLI en arrière-plan
    $php    = PHP_BINARY; // ex: C:\wamp64\bin\php\php8.x\php.exe
    $script = __DIR__ . DIRECTORY_SEPARATOR . 'notify_open.php';
    if (PHP_OS_FAMILY === 'Windows') {
        pclose(popen("start /B \"\" \"{$php}\" \"{$script}\"", 'r'));
    } else {
        exec("{$php} {$script} > /dev/null 2>&1 &");
    }
    $_SESSION['notify_msg'] = "📨 Envoi manuel déclenché ! Vérifie la console ou le log.";
    header("Location: admin_notify_list");
    exit;
}

// ── Flash message ────────────────────────────────────────────
if (isset($_SESSION['notify_msg'])) {
    $message = $_SESSION['notify_msg'];
    unset($_SESSION['notify_msg']);
}

// ── Charger la liste ─────────────────────────────────────────
$liste = $conn->query("SELECT * FROM notify_list ORDER BY date_ajout DESC")->fetchAll();

// ── Charger le log des envois ────────────────────────────────
$logs = $conn->query("SELECT * FROM notify_log ORDER BY date_envoi DESC LIMIT 10")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <link rel="icon" type="image/x-icon" href="..\FoodHubLogo.ico">
    <meta charset="UTF-8">
    <title>Admin — Liste de notifications</title>
    <link rel="stylesheet" href="..\assets\style.css">
</head>
<body>

<?php include '..\sidebar.php'; ?>
<audio id="player" autoplay loop>
    <source src="..\assets/Mairie - Animal Crossing New Horizons OST.mp3" type="audio/mp3">
</audio>
<?php include '..\slider_son.php'; ?>

<main class="container">

    <h2 class="admin-title">📨 Notifications d'ouverture du site</h2>

    <?php if ($message): ?>
        <div class="success"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Info box -->
    <div class="info-box">
        ℹ️ Ces personnes reçoivent automatiquement un email quand tu lances
        <strong>Run FoodHub.bat</strong>. Le lien ngrok y est inclus.
    </div>

    <!-- Bouton envoi manuel -->
    <div style="text-align:center;margin-bottom:1.5rem;">
        <a href="?send_now=1"
           class="btn-send-now"
           onclick="return confirm('Envoyer la notification maintenant à tous les actifs ?')">
            📨 Envoyer manuellement maintenant
        </a>
    </div>

    <!-- Formulaire ajout -->
    <div class="admin-form">
        <h3>Ajouter un destinataire</h3>
        <form method="POST">
            <input type="hidden" name="action" value="ajouter">
            <label>Nom :</label>
            <input type="text" name="nom" placeholder="ex: Yanis CDN" required
                   value="<?= htmlspecialchars($_POST['nom'] ?? '') ?>">
            <label>Email :</label>
            <input type="email" name="email" placeholder="ex: yanis@gmail.com" required
                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
            <button class="btn" type="submit">Ajouter</button>
        </form>
    </div>

    <!-- Table des destinataires -->
    <div class="admin-table">
        <h3>Destinataires (<?= count($liste) ?>)</h3>
        <?php if (empty($liste)): ?>
            <p style="text-align:center;color:#666;">Aucun destinataire pour le moment.</p>
        <?php else: ?>
        <table>
            <tr>
                <th>Nom</th>
                <th>Email</th>
                <th>Statut</th>
                <th>Ajouté le</th>
                <th>Actions</th>
            </tr>
            <?php foreach ($liste as $d): ?>
            <tr class="<?= $d['actif'] ? '' : 'inactive-row' ?>">
                <td><?= htmlspecialchars($d['nom']) ?></td>
                <td><?= htmlspecialchars($d['email']) ?></td>
                <td>
                    <?php if ($d['actif']): ?>
                        <span class="status-badge active">✅ Actif</span>
                    <?php else: ?>
                        <span class="status-badge inactive">⏸️ Désactivé</span>
                    <?php endif; ?>
                </td>
                <td><?= date('d/m/Y H:i', strtotime($d['date_ajout'])) ?></td>
                <td>
                    <a href="?toggle=<?= $d['id'] ?>" class="btn-action-small toggle">
                        <?= $d['actif'] ? '⏸️ Désactiver' : '▶️ Activer' ?>
                    </a>
                    <a href="?delete=<?= $d['id'] ?>" class="btn-action-small delete"
                       onclick="return confirm('Supprimer <?= htmlspecialchars($d['email']) ?> ?')">
                        🗑️ Supprimer
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
        <?php endif; ?>
    </div>

    <!-- Log des envois -->
    <?php if (!empty($logs)): ?>
    <div class="admin-table">
        <h3>Historique des envois (10 derniers)</h3>
        <table>
            <tr>
                <th>Date</th>
                <th>Nb envoyés</th>
                <th>Statut</th>
                <th>Détail</th>
            </tr>
            <?php foreach ($logs as $l): ?>
            <tr>
                <td><?= date('d/m/Y H:i', strtotime($l['date_envoi'])) ?></td>
                <td><?= (int)$l['nb_envoyes'] ?></td>
                <td>
                    <span class="status-badge <?= $l['statut'] === 'ok' ? 'active' : 'inactive' ?>">
                        <?= $l['statut'] === 'ok' ? '✅ OK' : '❌ Erreur' ?>
                    </span>
                </td>
                <td style="font-size:0.8rem;color:#666;white-space:pre-wrap;max-width:300px;word-break:break-all;">
                    <?= htmlspecialchars($l['detail'] ?? '') ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
    <?php endif; ?>

    <p><a href="home" class="back-link">⬅ Retour à l'accueil</a></p>
</main>

<script src="https://cdn.jsdelivr.net/npm/three@0.149.0/build/three.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/vanta/dist/vanta.waves.min.js"></script>
<script>
VANTA.WAVES({
    el: "body",
    mouseControls: true,
    touchControls: true,
    gyroControls: false,
    minHeight: 1205.00,
    minWidth: 200.00,
    scale: 1.00,
    scaleMobile: 1.00,
    color: 0x7cc6e6,
    shininess: 60,
    waveHeight: 22,
    waveSpeed: 0.7,
    zoom: 1.1
});
</script>

<style>
.container {
    backdrop-filter: blur(15px);
    background: linear-gradient(135deg, rgba(124, 198, 230, 0.2), rgba(90, 179, 216, 0.2));
    padding: 2rem;
    border-radius: 1.5rem;
    max-width: 1100px;
    margin: 100px auto;
    box-shadow: 0 8px 30px rgba(124, 198, 230, 0.2);
}

.admin-title {
    color: #7cc6e6;
    margin-bottom: 1.5rem;
    text-align: center;
    font-size: 2rem;
    text-shadow: 2px 2px 4px rgba(124, 198, 230, 0.3);
}

.info-box {
    background: linear-gradient(135deg, rgba(255, 193, 7, 0.15), rgba(255, 152, 0, 0.15));
    padding: 1rem;
    border-radius: 1rem;
    margin-bottom: 1.5rem;
    border-left: 4px solid #FFC107;
    color: #856404;
    font-weight: 600;
    text-align: center;
}

.success {
    background: rgba(0, 255, 127, 0.25);
    padding: 12px 20px;
    border-radius: 10px;
    margin-bottom: 20px;
    border-left: 4px solid #00ff7f;
    color: #006837;
    font-weight: 600;
}

.error {
    background: rgba(255, 77, 77, 0.25);
    padding: 12px 20px;
    border-radius: 10px;
    margin-bottom: 20px;
    border-left: 4px solid #ff4d4d;
    color: #8b0000;
    font-weight: 600;
}

.admin-form,
.admin-table {
    background: rgba(255, 255, 255, 0.20);
    backdrop-filter: blur(14px);
    border-radius: 1.3rem;
    padding: 2rem;
    margin-bottom: 2rem;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
}

.admin-form h3,
.admin-table h3 {
    margin-top: 0;
    color: #7cc6e6;
    margin-bottom: 1.2rem;
    font-size: 1.4rem;
}

.admin-form label {
    display: block;
    font-weight: 700;
    color: #333;
    margin-bottom: 0.4rem;
    margin-top: 0.8rem;
}

.admin-form input {
    width: 100%;
    padding: 0.8rem;
    border-radius: 0.8rem;
    border: 1px solid rgba(124, 198, 230, 0.3);
    background: rgba(255, 255, 255, 0.7);
    font-family: 'HSR', sans-serif;
    font-size: 1rem;
    box-sizing: border-box;
    transition: all 0.3s ease;
}

.admin-form input:focus {
    outline: none;
    border-color: #7cc6e6;
    box-shadow: 0 0 10px rgba(124, 198, 230, 0.3);
}

.btn {
    display: inline-flex;
    justify-content: center;
    align-items: center;
    padding: 0.8rem 1.6rem;
    font-size: 1rem;
    font-weight: 600;
    color: #fff;
    background: linear-gradient(135deg, #7cc6e6, #5ab3d8);
    border: none;
    border-radius: 12px;
    cursor: pointer;
    text-decoration: none;
    box-shadow: 0 4px 14px rgba(124, 198, 230, 0.4);
    transition: all 0.35s ease;
    margin-top: 1rem;
    font-family: 'HSR', sans-serif;
}

.btn:hover {
    transform: translateY(-3px) scale(1.03);
    box-shadow: 0 8px 20px rgba(124, 198, 230, 0.5);
    background: linear-gradient(135deg, #5ab3d8, #7cc6e6);
}

.btn-send-now {
    display: inline-block;
    padding: 0.9rem 2rem;
    font-size: 1.1rem;
    font-weight: 700;
    color: #fff;
    background: linear-gradient(135deg, #ff6b6b, #ffc342);
    border-radius: 14px;
    cursor: pointer;
    text-decoration: none;
    box-shadow: 0 6px 18px rgba(255, 107, 107, 0.35);
    transition: all 0.35s ease;
    font-family: 'HSR', sans-serif;
}

.btn-send-now:hover {
    transform: translateY(-3px) scale(1.03);
    box-shadow: 0 10px 25px rgba(255, 107, 107, 0.5);
    background: linear-gradient(135deg, #ff8c42, #ff6b6b);
    color: #fff;
}

table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 1rem;
    font-size: 0.9rem;
}

th, td {
    padding: 0.9rem 1rem;
    border-bottom: 1px solid rgba(124, 198, 230, 0.2);
    text-align: left;
    word-wrap: break-word;
}

th {
    background: rgba(124, 198, 230, 0.2);
    font-weight: 700;
    color: #333;
}

tr.inactive-row {
    opacity: 0.5;
}

.status-badge {
    padding: 0.3rem 0.7rem;
    border-radius: 12px;
    font-size: 0.8rem;
    font-weight: 600;
    display: inline-block;
}

.status-badge.active {
    background: rgba(76, 175, 80, 0.2);
    color: #2e7d32;
}

.status-badge.inactive {
    background: rgba(158, 158, 158, 0.2);
    color: #616161;
}

.btn-action-small {
    display: inline-block;
    padding: 0.4rem 0.7rem;
    border-radius: 0.6rem;
    text-decoration: none;
    font-size: 0.8rem;
    font-weight: 600;
    transition: all 0.3s ease;
    margin-right: 0.3rem;
}

.btn-action-small.toggle {
    background: rgba(124, 198, 230, 0.2);
    color: #1565c0;
}

.btn-action-small.toggle:hover {
    background: rgba(124, 198, 230, 0.4);
}

.btn-action-small.delete {
    background: rgba(244, 67, 54, 0.15);
    color: #c62828;
}

.btn-action-small.delete:hover {
    background: rgba(244, 67, 54, 0.3);
}

.back-link {
    display: inline-block;
    margin-top: 1rem;
    color: #7cc6e6;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s ease;
}

.back-link:hover {
    color: #5ab3d8;
    transform: translateX(-5px);
}
</style>

</body>
</html>