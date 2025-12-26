<?php
// contact_admin.php
session_start();
require_once 'db/config.php';

$message = '';
$error = '';

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = trim($_POST['nom'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $sujet = trim($_POST['sujet'] ?? '');
    $contenu = trim($_POST['message'] ?? '');
    $type_message = $_POST['type_message'] ?? 'general';
    
    // Validation
    if (empty($nom) || empty($email) || empty($sujet) || empty($contenu)) {
        $error = "Tous les champs sont requis.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Email invalide.";
    } elseif (strlen($contenu) < 10) {
        $error = "Le message doit contenir au moins 10 caractères.";
    } else {
        try {
            // Insérer le message dans la table messages_admin
            $stmt = $conn->prepare("
                INSERT INTO messages_admin (nom, email, sujet, message, type_message, date_envoi) 
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$nom, $email, $sujet, $contenu, $type_message]);
            
            // Créer une notification pour l'admin (user_id = 1)
            $notif_message = "Nouveau message de contact : " . $sujet;
            $notif = $conn->prepare("
                INSERT INTO notifications (user_id, type, restaurant_id, avis_id, message) 
                VALUES (1, 'comment', 0, 0, ?)
            ");
            $notif->execute([$notif_message]);
            
            // Sauvegarder le message dans la session pour le rediriger
            $_SESSION['contact_success'] = "✅ Votre message a été envoyé avec succès ! L'administrateur vous répondra dans les plus brefs délais.";
            
            // Redirection pour éviter la resoumission du formulaire
            header("Location: contact_admin.php");
            exit;
            
        } catch (Exception $e) {
            $error = "❌ Erreur lors de l'envoi du message. Veuillez réessayer.";
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
    <meta charset="UTF-8">
    <title>Contact - FoodHub</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="assets/contact_admin.css">
    <?php include 'sidebar.php'; ?>
</head>
<body>
    <audio id="player" autoplay loop>
        <source src="assets/Nintendo 3DS Internet Settings Theme (High Quality, 2022 Remastered).mp3" type="audio/mpeg">
    </audio>
    <?php include "slider_son.php"; ?>
    <style>
        #volume-slider {
    background: linear-gradient(135deg, #33b0d2ff, #58edf5ff); }
        #volume-button {
    background: linear-gradient(135deg, #33b0d2ff, #58edf5ff);
    }
    </style>

    <main class="container">
        <h1>📧 Contacter l'Administrateur</h1>
        
        <?php if ($message): ?>
            <div class="success" style="background: rgba(76, 175, 80, 0.2); padding: 1rem; border-radius: 1rem; margin-bottom: 1.5rem; border-left: 4px solid #4CAF50;">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="error" style="background: rgba(244, 67, 54, 0.2); padding: 1rem; border-radius: 1rem; margin-bottom: 1.5rem; border-left: 4px solid #f44336;">
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
                
                <form method="POST" class="contact-form">
                    <div class="form-group">
                        <label for="nom">Nom complet *</label>
                        <input type="text" id="nom" name="nom" required 
                               value="<?= htmlspecialchars($_POST['nom'] ?? '') ?>"
                               placeholder="Votre nom et prénom">
                    </div>

                    <div class="form-group">
                        <label for="email">Email *</label>
                        <input type="email" id="email" name="email" required 
                               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                               placeholder="votre.email@exemple.com">
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
                               value="<?= htmlspecialchars($_POST['sujet'] ?? '') ?>"
                               placeholder="Résumé de votre demande"
                               maxlength="200">
                    </div>

                    <div class="form-group">
                        <label for="message">Message * (minimum 10 caractères)</label>
                        <textarea id="message" name="message" required 
                                  placeholder="Décrivez votre demande en détail..."
                                  minlength="10"><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>
                    </div>

                    <button type="submit" class="btn-submit">Envoyer le message</button>
                </form>
            </div>
        </div>

        <div class="apropos-section">
            <div class="section-content">
                <h2>ℹ️ Informations importantes</h2>
                <div class="mission-text">
                    <p><strong>⏱️ Délai de réponse :</strong> L'administrateur s'efforce de répondre dans un délai de 24 à 48 heures.</p>
                    <p><strong>📧 Réponse :</strong> Vous recevrez une réponse à l'adresse email fournie.</p>
                    <p><strong>🔒 Confidentialité :</strong> Vos informations ne seront utilisées que pour traiter votre demande.</p>
                </div>
            </div>
        </div>

        <p><a href="<?= isset($_SESSION['user_id']) ? 'home.php' : 'index.php' ?>" class="back-link">← Retour</a></p>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/three@0.149.0/build/three.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/vanta/dist/vanta.waves.min.js"></script>

    <script>
    VANTA.WAVES({
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
        textarea.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = (this.scrollHeight) + 'px';
        });
    }
    </script>
</body>
</html>