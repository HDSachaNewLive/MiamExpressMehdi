<?php
// admin_messages.php
session_start();
require_once 'db/config.php';

// Vérification admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] != 1) {
    header("Location: index.php");
    exit;
}

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && isset($_POST['message_id'])) {
        $message_id = (int)$_POST['message_id'];
        
        if ($_POST['action'] === 'marquer_lu') {
            $stmt = $conn->prepare("UPDATE messages_admin SET lu = 1 WHERE message_id = ?");
            $stmt->execute([$message_id]);
        } elseif ($_POST['action'] === 'marquer_non_lu') {
            $stmt = $conn->prepare("UPDATE messages_admin SET lu = 0 WHERE message_id = ?");
            $stmt->execute([$message_id]);
        } elseif ($_POST['action'] === 'supprimer') {
            $stmt = $conn->prepare("DELETE FROM messages_admin WHERE message_id = ?");
            $stmt->execute([$message_id]);
        }
    }
}

// Filtres
$filtre_type = $_GET['type'] ?? 'tous';
$filtre_statut = $_GET['statut'] ?? 'tous';

// Récupérer les messages
$sql = "SELECT * FROM messages_admin WHERE 1=1";
$params = [];

if ($filtre_type !== 'tous') {
    $sql .= " AND type_message = ?";
    $params[] = $filtre_type;
}

if ($filtre_statut === 'non_lus') {
    $sql .= " AND lu = 0";
} elseif ($filtre_statut === 'lus') {
    $sql .= " AND lu = 1";
}

$sql .= " ORDER BY date_envoi DESC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Compter les messages non lus
$count_stmt = $conn->query("SELECT COUNT(*) FROM messages_admin WHERE lu = 0");
$messages_non_lus = $count_stmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Gestion des messages - Admin</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="admin_messages.css">
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <audio id="player" autoplay loop> 
        <source src="assets/Mairie - Animal Crossing New Horizons OST.mp3" type="audio/mp3"> 
    </audio>
    <?php include 'slider_son.php'; ?>
    <style>
        #volume-slider {
    background: linear-gradient(135deg, #33b0d2ff, #58edf5ff); }
        #volume-button {
    background: linear-gradient(135deg, #33b0d2ff, #58edf5ff);
    }
    </style>

    <main class="container">
        <h2 class="admin-title">📬 Gestion des Messages</h2>

        <?php if ($messages_non_lus > 0): ?>
            <div class="info-box">
                <strong>📩 <?= $messages_non_lus ?> message(s) non lu(s)</strong>
            </div>
        <?php endif; ?>

        <!-- Filtres -->
        <div class="filters">
            <form method="GET" class="filter-form">
                <div class="filter-group">
                    <label>Type :</label>
                    <select name="type" onchange="this.form.submit()">
                        <option value="tous" <?= $filtre_type === 'tous' ? 'selected' : '' ?>>Tous</option>
                        <option value="general" <?= $filtre_type === 'general' ? 'selected' : '' ?>>Question générale</option>
                        <option value="compte" <?= $filtre_type === 'compte' ? 'selected' : '' ?>>Problème de compte</option>
                        <option value="signalement" <?= $filtre_type === 'signalement' ? 'selected' : '' ?>>Signalement</option>
                        <option value="technique" <?= $filtre_type === 'technique' ? 'selected' : '' ?>>Problème technique</option>
                        <option value="suggestion" <?= $filtre_type === 'suggestion' ? 'selected' : '' ?>>Suggestion</option>
                        <option value="autre" <?= $filtre_type === 'autre' ? 'selected' : '' ?>>Autre</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label>Statut :</label>
                    <select name="statut" onchange="this.form.submit()">
                        <option value="tous" <?= $filtre_statut === 'tous' ? 'selected' : '' ?>>Tous</option>
                        <option value="non_lus" <?= $filtre_statut === 'non_lus' ? 'selected' : '' ?>>Non lus</option>
                        <option value="lus" <?= $filtre_statut === 'lus' ? 'selected' : '' ?>>Lus</option>
                    </select>
                </div>
            </form>
        </div>

        <!-- Liste des messages -->
        <div class="messages-list">
            <?php if (empty($messages)): ?>
                <div class="no-messages">
                    <p>📭 Aucun message pour le moment.</p>
                </div>
            <?php else: ?>
                <?php foreach ($messages as $msg): ?>
                    <div class="message-card <?= $msg['lu'] ? 'lu' : 'non-lu' ?>">
                        <div class="message-header">
                            <div class="message-info">
                                <span class="message-type"><?= htmlspecialchars($msg['type_message']) ?></span>
                                <?php if (!$msg['lu']): ?>
                                    <span class="badge-non-lu">Nouveau</span>
                                <?php endif; ?>
                            </div>
                            <div class="message-date">
                                <?= date('d/m/Y H:i', strtotime($msg['date_envoi'])) ?>
                            </div>
                        </div>

                        <div class="message-content">
                            <h3><?= htmlspecialchars($msg['sujet']) ?></h3>
                            <div class="message-from">
                                <strong>De :</strong> <?= htmlspecialchars($msg['nom']) ?> 
                                (<a href="mailto:<?= htmlspecialchars($msg['email']) ?>"><?= htmlspecialchars($msg['email']) ?></a>)
                            </div>
                            <div class="message-text">
                                <?= nl2br(htmlspecialchars($msg['message'])) ?>
                            </div>
                        </div>

                        <div class="message-actions">
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="message_id" value="<?= $msg['message_id'] ?>">
                                <?php if (!$msg['lu']): ?>
                                    <button type="submit" name="action" value="marquer_lu" class="btn-action btn-lu">
                                        ✓ Marquer comme lu
                                    </button>
                                <?php else: ?>
                                    <button type="submit" name="action" value="marquer_non_lu" class="btn-action btn-non-lu">
                                        ↻ Marquer comme non lu
                                    </button>
                                <?php endif; ?>
                            </form>

                            <a href="mailto:<?= htmlspecialchars($msg['email']) ?>?subject=Re: <?= urlencode($msg['sujet']) ?>" 
                               class="btn-action btn-repondre">
                                ✉️ Répondre
                            </a>

                            <form method="POST" style="display: inline;" 
                                  onsubmit="return confirm('Supprimer ce message ?')">
                                <input type="hidden" name="message_id" value="<?= $msg['message_id'] ?>">
                                <button type="submit" name="action" value="supprimer" class="btn-action btn-supprimer">
                                    🗑️ Supprimer
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <p><a href="home.php" class="back-link">← Retour à l'accueil</a></p>
    </main>

<script src="https://cdn.jsdelivr.net/npm/three@0.149.0/build/three.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/vanta/dist/vanta.waves.min.js"></script>

<script>
//fixer hauteur du body à la hauteur de la fenêtre
document.addEventListener('DOMContentLoaded', () => {
//créer conteneur fixe pour Vanta en arrière-plan
const vantaBg = document.createElement('div');
vantaBg.id = 'vanta-bg';
vantaBg.style.cssText = `
    position: fixed;
    top: 0;
    left: 0;
    width: 110vw;
    height: 130vh;
    z-index: 2;
    pointer-events: none;
  `;
document.body.insertBefore(vantaBg, document.body.firstChild);
VANTA.WAVES({
    el: "#vanta-bg",
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
});
</script>

<style>
body {
  background: none !important;
  overflow-x: clip;
}

canvas.vanta-canvas {
  position: absolute !important;
  top: 0;
  left: 0;
  width: fit-content;
  height: fit-content;
  z-index: 1 !important;
}
</style>
</body>
</html>