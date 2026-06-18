<?php
// contact_admin.php
session_start();
require_once 'db/config.php';
require_once 'mail_helper.php';
require_once __DIR__ . '/csrf_helper.php';

$message = '';
$error = '';

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Vérification CSRF en tête
    if (empty($_POST['csrf_token']) || !fh_verify_csrf($_POST['csrf_token'])) {
        $error = 'Jeton CSRF invalide.';
    } else {
    $nom = trim($_POST['nom'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $sujet = trim($_POST['sujet'] ?? '');
    $contenu = trim($_POST['message'] ?? '');
    $type_message = $_POST['type_message'] ?? 'general';
    $contact_method = $_POST['contact_method'] ?? 'foodhub'; // 'foodhub' | 'email'

    // Validation
    if (empty($nom) || empty($email) || empty($sujet) || empty($contenu)) {
        $error = "Tous les champs sont requis.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Email invalide.";
    } elseif (strlen($contenu) < 10) {
        $error = "Le message doit contenir au moins 10 caractères.";
    } else {
        try {
            // Toujours sauvegarder dans la BDD (pour le panneau admin)
            $stmt = $conn->prepare("
                INSERT INTO messages_admin (nom, email, sujet, message, type_message, date_envoi)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$nom, $email, $sujet, $contenu, $type_message]);

            if ($contact_method === 'email') {
                // Envoyer un vrai email à l'admin
                $mail_ok = fh_send_contact_to_admin_email($nom, $email, $sujet, $contenu, $type_message);

                if ($mail_ok) {
                    $_SESSION['contact_success'] = "✅ Votre message a été envoyé par email à l'administrateur ! Il vous répondra dans les plus brefs délais.";
                } else {
                    // L'envoi mail a échoué mais le message est quand même en BDD
                    $_SESSION['contact_success'] = "⚠️ Votre message a été enregistré, mais l'envoi de l'email a rencontré un problème. L'administrateur le recevra quand même via le panneau FoodHub.";
                }
            } else {
                // Mode FoodHub : créer une notification pour l'admin (user_id = 1)
                $notif_message = "Nouveau message de contact : " . $sujet;
                $notif = $conn->prepare("
                    INSERT INTO notifications (user_id, type, restaurant_id, avis_id, message)
                    VALUES (1, 'comment', 0, 0, ?)
                ");
                $notif->execute([$notif_message]);

                $_SESSION['contact_success'] = "✅ Votre message a été envoyé via FoodHub ! L'administrateur vous répondra dans les plus brefs délais.";
            }

            header("Location: contact_admin.php");
            exit;

        } catch (Exception $e) {
            $error = "❌ Erreur lors de l'envoi du message. Veuillez réessayer.";
        }
    }
    }
}

// Récupérer le message de succès depuis la session
if (isset($_SESSION['contact_success'])) {
    $message = $_SESSION['contact_success'];
    unset($_SESSION['contact_success']);
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <link rel="icon" type="image/x-icon" href="FoodHubLogo.ico">
    <meta charset="UTF-8">
    <title>Contact - FoodHub</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="assets/contact_admin.css">
    <?php include 'sidebar.php'; ?>
</head>

<body>
    <audio id="player" autoplay loop>
        <source
            src="https://raw.githubusercontent.com/HDSachaNewLive/foodhub-assets/main/Nintendo 3DS Internet Settings Theme (High Quality, 2022 Remastered).mp3"
            type="audio/mpeg">
    </audio>
    <?php include "slider_son.php"; ?>
    <style>
        #volume-slider {
            background: linear-gradient(135deg, #33b0d2ff, #58edf5ff);
        }

        #volume-button {
            background: linear-gradient(135deg, #33b0d2ff, #58edf5ff);
        }

        /* ── Sélecteur de méthode de contact ── */
        .method-toggle-wrapper {
            display: flex;
            gap: 1rem;
            margin-top: 0.3rem;
        }

        .method-toggle-wrapper label.method-card {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.4rem;
            padding: 1rem 0.8rem;
            border-radius: 1rem;
            border: 2px solid rgba(124, 198, 230, 0.25);
            background: rgba(255, 255, 255, 0.55);
            cursor: pointer;
            transition: all 0.25s ease;
            text-align: center;
            user-select: none;
        }

        .method-toggle-wrapper label.method-card:hover {
            border-color: rgba(124, 198, 230, 0.6);
            background: rgba(255, 255, 255, 0.8);
            transform: translateY(-2px);
        }

        .method-toggle-wrapper input[type="radio"] {
            display: none;
        }

        .method-toggle-wrapper input[type="radio"]:checked+.method-card {
            border-color: #7cc6e6;
            background: rgba(124, 198, 230, 0.18);
            box-shadow: 0 4px 16px rgba(124, 198, 230, 0.3);
        }

        .method-card .method-icon {
            font-size: 1.7rem;
            line-height: 1;
        }

        .method-card .method-label {
            font-weight: 700;
            color: #333;
            font-size: 0.95rem;
        }

        .method-card .method-desc {
            font-size: 0.78rem;
            color: #666;
            line-height: 1.4;
        }

        /* highlight on checked — wrapper trick needed because CSS can't reach sibling via :checked on hidden input */
        /* JS handles the visual class instead */
        .method-card.selected {
            border-color: #7cc6e6 !important;
            background: rgba(124, 198, 230, 0.18) !important;
            box-shadow: 0 4px 16px rgba(124, 198, 230, 0.3) !important;
        }
    </style>

    <main class="container">
        <h1>📧 Contacter l'Administrateur</h1>

        <?php if ($message): ?>
            <div class="success"
                style="background:rgba(76,175,80,0.2);padding:1rem;border-radius:1rem;margin-bottom:1.5rem;border-left:4px solid #4CAF50;">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="error"
                style="background:rgba(244,67,54,0.2);padding:1rem;border-radius:1rem;margin-bottom:1.5rem;border-left:4px solid #f44336;">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <div class="apropos-section">
            <div class="section-content">
                <h2>Pourquoi nous contacter ?</h2>
                <div class="mission-text">
                    <p>Vous pouvez contacter l'administrateur pour les raisons suivantes :</p>
                    <ul>
                        <li>🔒 Demander des informations sur votre compte</li>
                        <li>🚫 Signaler un comportement abusif</li>
                        <li>❓ Poser une question technique</li>
                        <li>💡 Faire une suggestion d'amélioration</li>
                        <li>⚠️ Signaler un bug ou un problème</li>
                        <li>🗑️ Demander la suppression d'un contenu inapproprié</li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="apropos-section">
            <div class="section-content">
                <h2>Formulaire de contact</h2>

                <form method="POST" class="contact-form" id="contact-form">

                    <?= fh_csrf_field() ?>

                    <div class="form-group">
                        <label for="nom">Nom complet *</label>
                        <input maxlength="45" type="text" id="nom" name="nom" required
                            value="<?= htmlspecialchars($_POST['nom'] ?? '') ?>" placeholder="Votre nom et prénom">
                    </div>

                    <div class="form-group">
                        <label for="email">Email *</label>
                        <input type="email" id="email" name="email" required
                            value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                            placeholder="votre.email@exemple.com">
                        <small style="color:#888;font-size:0.8rem;margin-top:0.3rem;display:block;">
                            Votre email n'est utilisé que pour que l'administrateur puisse vous identifier et vous
                            répondre.
                        </small>
                    </div>

                    <div class="form-group">
                        <label for="type_message">Type de demande *</label>
                        <select id="type_message" name="type_message" required>
                            <option value="general">Question générale</option>
                            <option value="compte">Problème de compte</option>
                            <option value="signalement">Signalement</option>
                            <option value="technique">Problème technique</option>
                            <option value="suggestion">Suggestion</option>
                            <option value="autre">Autre</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="sujet">Sujet *</label>
                        <input type="text" id="sujet" name="sujet" required
                            value="<?= htmlspecialchars($_POST['sujet'] ?? '') ?>" placeholder="Résumé de votre demande"
                            maxlength="200">
                    </div>

                    <div class="form-group">
                        <label for="message">Message * <span style="color:#999;font-weight:400;">(minimum 10
                                caractères)</span></label>
                        <textarea id="message" name="message" required placeholder="Décrivez votre demande en détail..."
                            minlength="10"><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>
                    </div>

                    <!-- ── Choix de la méthode d'envoi ── -->
                    <div class="form-group">
                        <label style="margin-bottom:0.6rem;display:block;">Mode d'envoi *</label>

                        <div class="method-toggle-wrapper" id="method-toggle">
                            <input type="radio" name="contact_method" id="method_foodhub" value="foodhub" checked>
                            <label for="method_foodhub" class="method-card selected" data-for="method_foodhub">
                                <span class="method-icon">📱</span>
                                <span class="method-label">Via FoodHub</span>
                                <span class="method-desc">Message enregistré dans le panneau admin FoodHub. Vous verrez
                                    la réponse dans vos notifications si vous avez un compte.</span>
                            </label>

                            <input type="radio" name="contact_method" id="method_email" value="email">
                            <label for="method_email" class="method-card" data-for="method_email">
                                <span class="method-icon">📧</span>
                                <span class="method-label">Par email</span>
                                <span class="method-desc">Un vrai email est envoyé directement à l'admin. La réponse
                                    arrivera dans votre boîte mail.</span>
                            </label>
                        </div>
                    </div>

                    <button type="submit" class="btn-submit" id="btn-submit">
                        📱 Envoyer via FoodHub
                    </button>
                </form>
            </div>
        </div>

        <div class="apropos-section">
            <div class="section-content">
                <h2>ℹ️ Informations importantes</h2>
                <div class="mission-text">
                    <p><strong>⏱️ Délai de réponse :</strong> L'administrateur s'efforce de répondre dans un délai de 24
                        à 48 heures.</p>
                    <p><strong>📧 Réponse :</strong> Selon le mode choisi, vous recevrez la réponse dans FoodHub ou par
                        email.</p>
                    <p><strong>🔒 Confidentialité :</strong> Vos informations ne seront utilisées que pour traiter votre
                        demande.</p>
                </div>
            </div>
        </div>

        <p><a href="<?= isset($_SESSION['user_id']) ? 'home.php' : 'index.php' ?>" class="back-link">← Retour</a></p>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/three@0.149.0/build/three.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/vanta/dist/vanta.waves.min.js"></script>

    <script>
        window.vantaEffect = VANTA.WAVES({
            el: "body",
            mouseControls: true,
            touchControls: true,
            minHeight: 200.00,
            minWidth: 200.00,
            scale: 1.00,
            scaleMobile: 1.00,
            color: 0x7cc6e6,
        });

        // Auto-resize textarea
        const textarea = document.getElementById('message');
        if (textarea) {
            textarea.addEventListener('input', function () {
                this.style.height = 'auto';
                this.style.height = (this.scrollHeight) + 'px';
            });
        }

        // Gestion visuelle du toggle méthode + libellé bouton submit
        const btnSubmit = document.getElementById('btn-submit');
        const cards = document.querySelectorAll('.method-card');
        const radios = document.querySelectorAll('input[name="contact_method"]');

        const btnLabels = {
            foodhub: '📱 Envoyer via FoodHub',
            email: '📧 Envoyer par email',
        };

        function updateMethod() {
            const checked = document.querySelector('input[name="contact_method"]:checked');
            if (!checked) return;
            const val = checked.value;

            // Mettre à jour les classes des cards
            cards.forEach(card => {
                const targetFor = card.getAttribute('data-for');
                card.classList.toggle('selected', targetFor === checked.id);
            });

            // Mettre à jour le libellé du bouton
            btnSubmit.textContent = btnLabels[val] || '✉️ Envoyer';
        }

        radios.forEach(r => r.addEventListener('change', updateMethod));

        // Clic sur la card = cocher le radio correspondant
        cards.forEach(card => {
            card.addEventListener('click', () => {
                const targetId = card.getAttribute('data-for');
                const radio = document.getElementById(targetId);
                if (radio) {
                    radio.checked = true;
                    updateMethod();
                }
            });
        });

        // Init
        updateMethod();
    </script>
</body>

</html>