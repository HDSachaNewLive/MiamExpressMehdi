<?php
// admin_messages.php
session_start();
require_once 'db/config.php';
require_once 'mail_helper.php';

// Vérification admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] != 1) {
    header("Location: index.php");
    exit;
}

$flash_success = '';
$flash_error = '';

/**
 * Extrait l'avis_id et le resto_id encodés dans le sujet d'un signalement.
 * Format : [avis_id:X][resto_id:Y] …
 */
function parse_signalement_sujet(string $sujet): array
{
    $avis_id = null;
    $resto_id = null;
    if (preg_match('/\[avis_id:(\d+)\]/', $sujet, $m))
        $avis_id = (int) $m[1];
    if (preg_match('/\[resto_id:(\d+)\]/', $sujet, $m))
        $resto_id = (int) $m[1];
    return ['avis_id' => $avis_id, 'resto_id' => $resto_id];
}

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Réponse à l'utilisateur 67
    if (isset($_POST['action']) && $_POST['action'] === 'repondre' && isset($_POST['message_id'])) {
        $message_id = (int) $_POST['message_id'];
        $corps_reponse = trim($_POST['corps_reponse'] ?? '');
        $reply_method = $_POST['reply_method'] ?? 'foodhub'; // 'foodhub' | 'email'

        if ($corps_reponse === '') {
            $flash_error = "❌ La réponse ne peut pas être vide.";
        } else {
            // Récupérer le message original
            $stmt = $conn->prepare("SELECT * FROM messages_admin WHERE message_id = ?");
            $stmt->execute([$message_id]);
            $msg_original = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($msg_original) {
                // Toujours sauvegarder la réponse dans messages_admin
                $stmt = $conn->prepare("UPDATE messages_admin SET reponse_admin = ?, lu = 1, date_reponse = NOW() WHERE message_id = ?");
                $stmt->execute([$corps_reponse, $message_id]);

                if ($reply_method === 'email') {
                    //  Envoi par vrai email 
                    $mail_ok = fh_send_admin_reply_email(
                        $msg_original['nom'],
                        $msg_original['email'],
                        $msg_original['sujet'],
                        $corps_reponse
                    );

                    if ($mail_ok) {
                        $flash_success = "✅ Réponse envoyée par email à {$msg_original['email']} et sauvegardée.";
                    } else {
                        $flash_error = "⚠️ Réponse sauvegardée, mais l'envoi de l'email a échoué. Vérifiez la configuration SMTP.";
                    }

                } else {
                    // Notification
                    $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND compte_actif = 1");
                    $stmt->execute([$msg_original['email']]);
                    $user_trouve = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($user_trouve) {
                        $notif_msg = "📬 Réponse de l'admin à votre message « " . $msg_original['sujet'] . " » : " . $corps_reponse;
                        $stmt = $conn->prepare("INSERT INTO notifications (user_id, type, restaurant_id, avis_id, message) VALUES (?, 'reply', 0, 0, ?)");
                        $stmt->execute([$user_trouve['user_id'], $notif_msg]);
                        $flash_success = "✅ Notification FoodHub envoyée à l'utilisateur et réponse sauvegardée.";
                    } else {
                        $flash_error = "⚠️ Réponse sauvegardée, mais aucun compte FoodHub actif trouvé pour cet email — notification impossible.";
                    }
                }
            }
        }
    }

    // Marquer lu / non lu / supprimer
    if (isset($_POST['action']) && isset($_POST['message_id']) && $_POST['action'] !== 'repondre') {
        $message_id = (int) $_POST['message_id'];

        if ($_POST['action'] === 'marquer_lu') {
            $conn->prepare("UPDATE messages_admin SET lu = 1 WHERE message_id = ?")->execute([$message_id]);
        } elseif ($_POST['action'] === 'marquer_non_lu') {
            $conn->prepare("UPDATE messages_admin SET lu = 0 WHERE message_id = ?")->execute([$message_id]);
        } elseif ($_POST['action'] === 'supprimer') {
            $conn->prepare("DELETE FROM messages_admin WHERE message_id = ?")->execute([$message_id]);
        }
    }
}

// Filtres
$filtre_type = $_GET['type'] ?? 'tous';
$filtre_statut = $_GET['statut'] ?? 'tous';

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

$count_stmt = $conn->query("SELECT COUNT(*) FROM messages_admin WHERE lu = 0");
$messages_non_lus = $count_stmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <link rel="icon" type="image/x-icon" href="FoodHubLogo.ico">
    <meta charset="UTF-8">
    <title>Gestion des messages - Admin</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="admin_messages.css">
    <style>
        .container {
            backdrop-filter: blur(15px);
            background: linear-gradient(135deg, rgba(124, 198, 230, 0.2), rgba(90, 179, 216, 0.2));
            padding: 2rem;
            border-radius: 1.5rem;
            max-width: 1200px;
            margin: 100px auto;
            box-shadow: 0 8px 30px rgba(124, 198, 230, 0.2);
            position: relative;
            z-index: 10;
        }

        /* Toggle méthode de réponse dans le modal */
        .reply-method-wrapper {
            display: flex;
            gap: 0.8rem;
            margin: 0.5rem 0 1.2rem 0;
        }

        .reply-method-wrapper input[type="radio"] {
            display: none;
        }

        .reply-method-card {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.25rem;
            padding: 0.9rem 0.6rem;
            border-radius: 0.9rem;
            border: 2px solid rgba(124, 198, 230, 0.25);
            background: rgba(255, 255, 255, 0.6);
            cursor: pointer;
            transition: all 0.22s ease;
            text-align: center;
            user-select: none;
        }

        .reply-method-card:hover {
            border-color: rgba(124, 198, 230, 0.55);
            background: rgba(255, 255, 255, 0.9);
            transform: translateY(-2px);
        }

        .reply-method-card.selected {
            border-color: #7cc6e6;
            background: rgba(124, 198, 230, 0.15);
            box-shadow: 0 4px 14px rgba(124, 198, 230, 0.3);
        }

        /* Quand FoodHub n'est pas dispo : carte grisée */
        .reply-method-card.disabled-card {
            opacity: 0.45;
            cursor: not-allowed;
            pointer-events: none;
        }

        .reply-method-card .rm-icon {
            font-size: 1.5rem;
            line-height: 1;
        }

        .reply-method-card .rm-label {
            font-weight: 700;
            color: #333;
            font-size: 0.88rem;
        }

        .reply-method-card .rm-desc {
            font-size: 0.72rem;
            color: #666;
            line-height: 1.35;
        }

        /* Badge couleur dynamique sous le toggle */
        #modal-method-hint {
            font-size: 0.82rem;
            padding: 0.45rem 0.8rem;
            border-radius: 0.6rem;
            margin-bottom: 1rem;
            transition: all 0.25s ease;
        }

        @keyframes fadeInModal {
            from {
                opacity: 0;
                transform: scale(0.95) translateY(10px);
            }

            to {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }

        .reponse-admin-box {
            margin-top: 1rem;
            padding: 0.9rem 1.1rem;
            background: rgba(124, 198, 230, 0.12);
            border-left: 4px solid #7cc6e6;
            border-radius: 0.8rem;
            font-size: 0.9rem;
            color: #333;
        }

        .reponse-admin-box p {
            margin: 0.4rem 0 0 0;
            line-height: 1.5;
        }

        #modal-reponse {
            z-index: 99999 !important;
        }

        #modal-corps,
        #modal-corps:invalid,
        #modal-corps:-moz-ui-invalid,
        #modal-corps:focus,
        #modal-corps:focus-visible {
            box-shadow: none !important;
            outline: none !important;
        }
    </style>
</head>

<body>
    <?php include 'sidebar.php'; ?>
    <audio id="player" autoplay loop>
        <source
            src="https://raw.githubusercontent.com/HDSachaNewLive/foodhub-assets/main/Mairie - Animal Crossing New Horizons OST.mp3"
            type="audio/mp3">
    </audio>
    <?php include 'slider_son.php'; ?>
    <style>
        #volume-slider {
            background: linear-gradient(135deg, #33b0d2ff, #58edf5ff);
        }

        #volume-button {
            background: linear-gradient(135deg, #33b0d2ff, #58edf5ff);
        }
    </style>

    <main class="container">
        <h2 class="admin-title">📬 Gestion des Messages</h2>

        <?php if ($flash_success): ?>
            <div
                style="background:rgba(0,255,127,0.2);padding:12px 20px;border-radius:12px;margin-bottom:1.5rem;border-left:5px solid #00ff7f;color:#006837;font-weight:600;">
                <?= htmlspecialchars($flash_success) ?>
            </div>
        <?php endif; ?>
        <?php if ($flash_error): ?>
            <div
                style="background:rgba(255,77,77,0.2);padding:12px 20px;border-radius:12px;margin-bottom:1.5rem;border-left:5px solid #ff4d4d;color:#8b0000;font-weight:600;">
                <?= htmlspecialchars($flash_error) ?>
            </div>
        <?php endif; ?>

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
                        <option value="general" <?= $filtre_type === 'general' ? 'selected' : '' ?>>Question générale
                        </option>
                        <option value="compte" <?= $filtre_type === 'compte' ? 'selected' : '' ?>>Problème de compte
                        </option>
                        <option value="signalement" <?= $filtre_type === 'signalement' ? 'selected' : '' ?>>Signalement
                        </option>
                        <option value="technique" <?= $filtre_type === 'technique' ? 'selected' : '' ?>>Problème technique
                        </option>
                        <option value="suggestion" <?= $filtre_type === 'suggestion' ? 'selected' : '' ?>>Suggestion
                        </option>
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
                    <?php
                    // Lookup principal : par email (correspondance exacte)
                    $stmt_check = $conn->prepare("SELECT user_id, nom_user, email FROM users WHERE email = ? AND compte_actif = 1");
                    $stmt_check->execute([$msg['email']]);
                    $user_fh = $stmt_check->fetch(PDO::FETCH_ASSOC);
                    $has_compte = (bool) $user_fh;

                    // Lookup secondaire : si l'email ne matche plus, chercher par nom_user
                    // pour détecter un éventuel changement d'email.
                    $user_by_nom = null;
                    if (!$has_compte) {
                        $stmt_nom = $conn->prepare("SELECT user_id, nom_user, email FROM users WHERE nom_user = ? AND compte_actif = 1");
                        $stmt_nom->execute([$msg['nom']]);
                        $user_by_nom = $stmt_nom->fetch(PDO::FETCH_ASSOC);
                    }
                    ?>
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
                                <strong>De :</strong>

                                <?php if ($has_compte): ?>
                                    <?php
                                    $nom_soumis = htmlspecialchars($msg['nom']);
                                    $nom_actuel = htmlspecialchars($user_fh['nom_user']);
                                    $nom_change = ($nom_actuel !== $nom_soumis);
                                    ?>
                                    <?= $nom_soumis ?>
                                    <?php if ($nom_change): ?>
                                        <span style="font-size:0.78rem;color:#888;font-style:italic;">
                                            (compte FoodHub&nbsp;: <strong style="color:#2e7d32;"><?= $nom_actuel ?></strong>)
                                        </span>
                                    <?php endif; ?>
                                    (<a
                                        href="mailto:<?= htmlspecialchars($msg['email']) ?>"><?= htmlspecialchars($msg['email']) ?></a>)
                                    <span style="margin-left:0.5rem;background:rgba(76,175,80,0.15);color:#2e7d32;
                                                 font-size:0.75rem;font-weight:700;padding:0.15rem 0.55rem;
                                                 border-radius:0.5rem;">✅ Compte FoodHub</span>

                                <?php elseif ($user_by_nom): ?>
                                    <?= htmlspecialchars($msg['nom']) ?>
                                    (<a
                                        href="mailto:<?= htmlspecialchars($msg['email']) ?>"><?= htmlspecialchars($msg['email']) ?></a>)
                                    <span style="margin-left:0.5rem;background:rgba(255,152,0,0.15);color:#7b4d00;
                                                 font-size:0.75rem;font-weight:700;padding:0.15rem 0.55rem;
                                                 border-radius:0.5rem;">📧 Email changé</span>
                                    <span style="display:block;margin-top:0.3rem;font-size:0.8rem;color:#7b4d00;">
                                        Compte FoodHub trouvé par nom · Nouvel email actuel :
                                        <a href="mailto:<?= htmlspecialchars($user_by_nom['email']) ?>"
                                            style="color:#e67e22;font-weight:700;">
                                            <?= htmlspecialchars($user_by_nom['email']) ?>
                                        </a>
                                    </span>

                                <?php else: ?>
                                    <?= htmlspecialchars($msg['nom']) ?>
                                    (<a
                                        href="mailto:<?= htmlspecialchars($msg['email']) ?>"><?= htmlspecialchars($msg['email']) ?></a>)
                                    <span style="margin-left:0.5rem;background:rgba(255,193,7,0.15);color:#856404;
                                                 font-size:0.75rem;font-weight:700;padding:0.15rem 0.55rem;
                                                 border-radius:0.5rem;">⚠️ Sans compte</span>
                                <?php endif; ?>
                            </div>
                            <div class="message-text">
                                <?= nl2br(htmlspecialchars($msg['message'])) ?>
                            </div>

                            <?php if ($msg['type_message'] === 'signalement'):
                                $sig = parse_signalement_sujet($msg['sujet']);
                                ?>
                                <div style="margin:0.8rem 0 0.5rem;padding:0.9rem 1.1rem;
                                        background:rgba(255,107,107,0.08);border-left:4px solid #ff6b6b;
                                        border-radius:0.8rem;font-size:0.88rem;color:#555;">
                                    <strong style="color:#cc3333;">🚩 Signalement d'avis</strong>
                                    <?php if ($sig['avis_id'] && $sig['resto_id']): ?>
                                        &nbsp;—&nbsp;
                                        <a href="menu.php?restaurant_id=<?= $sig['resto_id'] ?>#comment-<?= $sig['avis_id'] ?>"
                                            target="_blank" style="color:#ff6b6b;font-weight:700;text-decoration:none;">
                                            👁️ Voir le commentaire signalé
                                        </a>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="message-actions">
                            <!-- Marquer lu / non lu -->
                            <form method="POST" style="display:inline;">
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

                            <!-- Répondre -->
                            <button type="button" class="btn-action btn-repondre" onclick="ouvrirModalReponse(
                                    <?= $msg['message_id'] ?>,
                                    <?= htmlspecialchars(json_encode($msg['nom']), ENT_QUOTES) ?>,
                                    <?= htmlspecialchars(json_encode($msg['email']), ENT_QUOTES) ?>,
                                    <?= htmlspecialchars(json_encode($msg['sujet']), ENT_QUOTES) ?>,
                                    <?= ($has_compte || $user_by_nom) ? 'true' : 'false' ?>,
                                    <?= htmlspecialchars(json_encode($msg['reponse_admin'] ?? ''), ENT_QUOTES) ?>
                                )">
                                ✉️ <?= !empty($msg['reponse_admin']) ? 'Modifier la réponse' : 'Répondre' ?>
                            </button>

                            <!-- Supprimer -->
                            <form method="POST" style="display:inline;"
                                onsubmit="return confirm('Supprimer ce message définitivement ?')">
                                <input type="hidden" name="message_id" value="<?= $msg['message_id'] ?>">
                                <button type="submit" name="action" value="supprimer" class="btn-action btn-supprimer">
                                    🗑️ Supprimer
                                </button>
                            </form>
                        </div>

                        <?php if (!empty($msg['reponse_admin'])): ?>
                            <div class="reponse-admin-box">
                                <strong>📤 Réponse
                                    envoyée<?= !empty($msg['date_reponse']) ? ' le ' . date('d/m/Y à H:i', strtotime($msg['date_reponse'])) : '' ?>
                                    :</strong>
                                <p><?= nl2br(htmlspecialchars($msg['reponse_admin'])) ?></p>
                            </div>
                        <?php endif; ?>

                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <p><a href="home.php" class="back-link">← Retour à l'accueil</a></p>
    </main>

    <!-- MODAL DE RÉPONSE-->
    <div id="modal-reponse" style="display:none;position:fixed;inset:0;z-index:99999;
                background:rgba(0,0,0,0.55);backdrop-filter:blur(5px);
                align-items:center;justify-content:center;">

        <div style="background:rgba(255,255,255,0.97);backdrop-filter:blur(20px);
                    border-radius:1.5rem;padding:2rem;width:90%;max-width:600px;
                    box-shadow:0 10px 50px rgba(0,0,0,0.25);animation:fadeInModal 0.3s ease;
                    max-height:92vh;overflow-y:auto;">

            <!-- En-tête modal -->
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.2rem;">
                <h3 style="margin:0;color:#7cc6e6;font-size:1.35rem;">✉️ Répondre au message</h3>
                <button onclick="fermerModalReponse()" style="background:none;border:none;font-size:1.4rem;cursor:pointer;
                               color:#aaa;line-height:1;padding:0.2rem 0.4rem;border-radius:0.5rem;
                               transition:color 0.2s ease;" onmouseover="this.style.color='#555'"
                    onmouseout="this.style.color='#aaa'">✕</button>
            </div>

            <p id="modal-destinataire" style="color:#555;font-size:0.88rem;margin:0 0 1.2rem 0;
                      border-bottom:1px solid rgba(124,198,230,0.2);padding-bottom:0.8rem;"></p>

            <form method="POST" id="form-reponse-admin">
                <input type="hidden" name="action" value="repondre">
                <input type="hidden" name="message_id" id="modal-message-id" value="">
                <input type="hidden" name="reply_method" id="modal-reply-method" value="foodhub">

                <!-- Choix de la méthode de réponse -->
                <label style="display:block;font-weight:700;color:#333;margin-bottom:0.5rem;">
                    Mode de réponse :
                </label>

                <div class="reply-method-wrapper" id="reply-toggle">

                    <div class="reply-method-card selected" id="card-foodhub" onclick="setReplyMethod('foodhub')">
                        <span class="rm-icon">📱</span>
                        <span class="rm-label">Notification FoodHub</span>
                        <span class="rm-desc">L'utilisateur reçoit une notif dans l'app.</span>
                    </div>

                    <div class="reply-method-card" id="card-email" onclick="setReplyMethod('email')">
                        <span class="rm-icon">📧</span>
                        <span class="rm-label">Email</span>
                        <span class="rm-desc">Un vrai email est envoyé à l'adresse de l'expéditeur.</span>
                    </div>

                </div>

                <!-- Hint contextuel -->
                <div id="modal-method-hint"
                    style="font-size:0.82rem;padding:0.45rem 0.8rem;border-radius:0.6rem;margin-bottom:1rem;"></div>

                <!-- Corps de la réponse -->
                <label style="display:block;font-weight:700;color:#333;margin-bottom:0.4rem;">
                    Votre réponse :
                </label>
                <textarea name="corps_reponse" id="modal-corps" rows="7" style="width:100%;padding:0.8rem;border-radius:0.8rem;
                           border:2px solid rgba(124,198,230,0.35);
                           font-family:'HSR',sans-serif;font-size:0.95rem;
                           resize:none;box-sizing:border-box;
                           background:rgba(255,255,255,0.85);outline:none;box-shadow:none;
                           transition:all 0.25s ease;" placeholder="Écrivez votre réponse ici..."
                    onfocus="this.style.borderColor='#7cc6e6';this.style.boxShadow='none';"
                    onblur="this.style.borderColor='rgba(124,198,230,0.35)';this.style.boxShadow='none';"></textarea>

                <div style="display:flex;gap:0.8rem;margin-top:1.1rem;justify-content:flex-end;flex-wrap:wrap;">
                    <button type="button" onclick="fermerModalReponse()" style="padding:0.7rem 1.4rem;border:none;border-radius:0.8rem;
                               background:rgba(158,158,158,0.2);color:#555;
                               font-weight:600;font-family:'HSR',sans-serif;
                               cursor:pointer;transition:all 0.25s ease;"
                        onmouseover="this.style.background='rgba(158,158,158,0.35)'"
                        onmouseout="this.style.background='rgba(158,158,158,0.2)'">
                        Annuler
                    </button>
                    <button type="submit" id="modal-submit-btn" style="padding:0.7rem 1.5rem;border:none;border-radius:0.8rem;
                               background:linear-gradient(135deg,#7cc6e6,#5ab3d8);color:#fff;
                               font-weight:700;font-family:'HSR',sans-serif;cursor:pointer;
                               box-shadow:0 4px 14px rgba(124,198,230,0.4);
                               transition:all 0.25s ease;">
                        📱 Envoyer la notification
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Vanta -->
    <script src="https://cdn.jsdelivr.net/npm/three@0.149.0/build/three.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/vanta/dist/vanta.waves.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const vantaBg = document.createElement('div');
            vantaBg.id = 'vanta-bg';
            vantaBg.style.cssText = `
            position:fixed;top:0;left:0;width:110vw;height:130vh;
            z-index:2;pointer-events:none;`;
            document.body.appendChild(vantaBg);
            window.vantaEffect = VANTA.WAVES({
                el: "#vanta-bg",
                mouseControls: true, touchControls: true, gyroControls: false,
                minHeight: 1205, minWidth: 200, scale: 1, scaleMobile: 1,
                color: 0x7cc6e6, shininess: 60, waveHeight: 22, waveSpeed: 0.7, zoom: 1.1
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
            z-index: -9;
        }
    </style>

    <!-- Scripts modal -->
    <script>
        // État courant du modal
        let _currentHasCompte = false;
        let _currentMethod = 'foodhub';

        const HINT = {
            foodhub_ok: {
                text: '✅ Cet utilisateur a un compte FoodHub actif — il recevra la notification dans l\'app.',
                bg: 'rgba(76,175,80,0.12)', color: '#2e7d32', border: '1px solid rgba(76,175,80,0.3)'
            },
            foodhub_ko: {
                text: '⚠️ Aucun compte FoodHub trouvé pour cet email — la notification FoodHub est indisponible.',
                bg: 'rgba(255,193,7,0.12)', color: '#856404', border: '1px solid rgba(255,193,7,0.3)'
            },
            email: {
                text: '📧 Un vrai email sera envoyé directement à l\'adresse de l\'expéditeur.',
                bg: 'rgba(124,198,230,0.12)', color: '#1565c0', border: '1px solid rgba(124,198,230,0.35)'
            }
        };

        const SUBMIT_LABELS = {
            foodhub: '📱 Envoyer la notification',
            email: '📧 Envoyer par email',
        };

        // Ouvre le modal 
        function ouvrirModalReponse(messageId, nom, email, sujet, hasCompte, reponseExistante) {
            _currentHasCompte = hasCompte;

            document.getElementById('modal-message-id').value = messageId;
            document.getElementById('modal-destinataire').textContent =
                'À : ' + nom + ' (' + email + ')  —  Sujet : Re: ' + sujet;

            // Pré-remplir si réponse déjà existante
            document.getElementById('modal-corps').value = reponseExistante || '';

            // Si l'utilisateur n'a pas de compte FoodHub, griser la carte FoodHub et basculer sur email
            const cardFH = document.getElementById('card-foodhub');
            if (!hasCompte) {
                cardFH.classList.add('disabled-card');
                setReplyMethod('email');
            } else {
                cardFH.classList.remove('disabled-card');
                setReplyMethod('foodhub');
            }

            document.getElementById('modal-reponse').style.display = 'flex';
            setTimeout(() => document.getElementById('modal-corps').focus(), 120);
        }

        // Ferme le modal
        function fermerModalReponse() {
            document.getElementById('modal-reponse').style.display = 'none';
        }

        // Change la méthode de réponse 
        function setReplyMethod(method) {
            // Si FoodHub indisponible et on essaie de le sélectionner, bloquer
            if (method === 'foodhub' && !_currentHasCompte) return;

            _currentMethod = method;
            document.getElementById('modal-reply-method').value = method;

            // Visuels des cartes
            document.getElementById('card-foodhub').classList.toggle('selected', method === 'foodhub');
            document.getElementById('card-email').classList.toggle('selected', method === 'email');

            // Hint
            const hintEl = document.getElementById('modal-method-hint');
            let hint;
            if (method === 'email') {
                hint = HINT.email;
            } else {
                hint = _currentHasCompte ? HINT.foodhub_ok : HINT.foodhub_ko;
            }
            hintEl.textContent = hint.text;
            hintEl.style.background = hint.bg;
            hintEl.style.color = hint.color;
            hintEl.style.border = hint.border;

            // Libellé bouton submit
            document.getElementById('modal-submit-btn').textContent = SUBMIT_LABELS[method] || '✉️ Envoyer';
        }

        //  Validation à la soumission 
        document.getElementById('form-reponse-admin').addEventListener('submit', function (e) {
            const corps = document.getElementById('modal-corps').value.trim();
            if (!corps) {
                e.preventDefault();
                const ta = document.getElementById('modal-corps');
                ta.style.borderColor = '#ff6b6b';
                ta.focus();
            }
        });

        //  Fermeture sur clic hors modal / Escape 
        document.getElementById('modal-reponse').addEventListener('click', function (e) {
            if (e.target === this) fermerModalReponse();
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') fermerModalReponse();
        });
    </script>
</body>

</html>