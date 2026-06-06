<?php
// register.php
session_start();
require_once 'db/config.php';
require_once 'mail_helper.php';

if (isset($_SESSION["user_id"])) {
    header("Location: home.php");
    exit();
}

// Vérifier si la BDD contient des utilisateurs
$stmt = $conn->query("SELECT COUNT(*) as nb_users FROM users");
$nb_users = $stmt->fetchColumn();
$is_first_install = ($nb_users == 0);

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom               = trim($_POST['nom'] ?? '');
    $email             = trim($_POST['email'] ?? '');
    $telephone         = trim($_POST['telephone'] ?? '');
    $motdepasse        = $_POST['motdepasse'] ?? '';
    $adresse_livraison = trim($_POST['adresse_livraison'] ?? '');
    $type_compte       = in_array($_POST['type_compte'] ?? 'client', ['client','proprietaire']) ? $_POST['type_compte'] : 'client';
    $email_fictif      = isset($_POST['email_fictif']) ? 1 : 0; // 1 = fictif (pas de vérif), 0 = réel
    
    // Un propriétaire doit obligatoirement avoir un email réel
    if ($type_compte === 'proprietaire' && $email_fictif) {
      $errors[] = "Les comptes propriétaire nécessitent une adresse email réelle et vérifiable.";
      $email_fictif = 0;
    }
    if ($nom === '' || $email === '' || $motdepasse === '') {
        $errors[] = "Nom, email et mot de passe sont requis.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Email invalide.";
    } else {
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors[] = "Un compte avec cet email existe déjà.";
        } else {
            $hash = password_hash($motdepasse, PASSWORD_DEFAULT);

            // Si email fictif → déjà vérifié ; sinon → en attente de vérification
            $email_verifie = $email_fictif ? 1 : 0;

            $stmt = $conn->prepare("
                INSERT INTO users (nom_user, email, telephone, motdepasse, adresse_livraison, type_compte, email_fictif, email_verifie)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$nom, $email, $telephone, $hash, $adresse_livraison, $type_compte, $email_fictif, $email_verifie]);
            $new_uid = (int)$conn->lastInsertId();

            // Connecter directement l'utilisateur pour éviter la perte du flag nouveau_compte
            // au passage par login.php (le flag doit survivre jusqu'à home.php)
            $_SESSION['user_id']           = $new_uid;
            $_SESSION['nom_user']          = $nom;
            $_SESSION['type_compte']       = $type_compte;
            $_SESSION['adresse_livraison'] = $adresse_livraison;
            $_SESSION['nouveau_compte']    = true; // déclenchera l'animation de bienvenue dans home.php

            if (!$email_fictif) {
                // Envoyer l'email de vérification
                $sent = fh_send_verify_email($conn, $new_uid, $nom, $email);
                if ($sent) {
                    $_SESSION['success'] = "Compte créé ! Un email de vérification a été envoyé à <strong>" . htmlspecialchars($email) . "</strong>. Vérifie ta boîte de réception (et tes spams).";
                } else {
                    // Envoi échoué : on bascule en mode fictif pour ne pas bloquer l'utilisateur
                    $conn->prepare("UPDATE users SET email_fictif = 1, email_verifie = 1 WHERE user_id = ?")->execute([$new_uid]);
                    $_SESSION['success'] = "Compte créé. (L'envoi de l'email de vérification a échoué — ton compte est activé directement.)";
                }
            } else {
                $_SESSION['success'] = "Compte créé. Tu peux te connecter.";
            }

            header("Location: tos.php");
            exit;
        }
    }
}

// Récupérer erreur éventuelle Google OAuth
$google_error = $_SESSION['error_login'] ?? '';
unset($_SESSION['error_login']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <link rel="icon" type="image/x-icon" href="FoodHubLogo.ico">
  <meta charset="utf-8">
  <title>Inscription - FoodHub</title>
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
  <audio id="player" autoplay loop> <source src="https://raw.githubusercontent.com/HDSachaNewLive/foodhub-assets/main/Nintendo DSi Shop Theme (High Quality, 2019 Remastered).mp3" type="audio/mpeg"> </audio>
  <?php include "slider_son.php"; ?>
  <?php include 'vanta_freeze.php'; ?>
<div class="page-wrapper">
  <div class="page flip_in" id="current-page">
  <main class="container">
    <h2>Inscription</h2>

    <?php if ($is_first_install): ?>
      <div class="first-install-notice">
        <h3>🎉 Première installation</h3>
        <p>Créez le premier compte administrateur pour commencer.</p>
        <p><strong>N'oubliez pas votre email ou votre mot de passe pour la suite !</strong></p>
      </div>
    <?php endif; ?>

    <?php if (!empty($google_error)): ?>
      <div class="error"><?= htmlspecialchars($google_error) ?></div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
      <div class="error">
        <?php foreach ($errors as $e) echo "<div>".htmlspecialchars($e)."</div>"; ?>
      </div>
    <?php endif; ?>

    <!-- bouton Google OAuth (crée un compte client automatiquement) -->
    <a href="foodhub_google_login.php" class="btn-google">
      <svg viewBox="0 0 24 24" width="20" height="20" xmlns="http://www.w3.org/2000/svg">
        <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
        <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
        <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/>
        <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
      </svg>
      S'inscrire avec Google
    </a>

    <div class="separateur">
      <span>ou créer un compte manuellement</span>
    </div>

    <form method="post" action="register.php" class="form">
      <input type="text" name="nom" maxlength="45" placeholder="Nom complet (max 45 caractères, espaces compris)" required value="<?= htmlspecialchars($_POST['nom'] ?? '') ?>"><br>
      <input type="email" name="email" placeholder="Email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"><br>
      <input type="text" name="telephone" placeholder="Téléphone (optionnel)" value="<?= htmlspecialchars($_POST['telephone'] ?? '') ?>"><br>
      <input type="text" name="adresse_livraison" placeholder="Adresse de livraison (optionnel)" value="<?= htmlspecialchars($_POST['adresse_livraison'] ?? '') ?>" data-address-autocomplete><br>
      <input type="password" name="motdepasse" placeholder="Mot de passe" required><br>
      <br>
      <label style="text-align: center;">Type de compte :</label><br>
      <select name="type_compte" class="form" id="type_compte">
        <option value="client" <?= (($_POST['type_compte'] ?? '') === 'client') ? 'selected' : '' ?>>Client</option>
        <option value="proprietaire" <?= (($_POST['type_compte'] ?? '') === 'proprietaire') ? 'selected' : '' ?>>Propriétaire (ajout de restaurants)</option>
      </select><br>

      <!-- Checkbox email fictif/réel -->
      <div class="email-type-box" id="email-type-box">
        <label class="checkbox-label" for="email_fictif">
          <input type="checkbox" name="email_fictif" id="email_fictif" value="1"
                 <?= isset($_POST['email_fictif']) ? 'checked' : '' ?>>
          <span class="checkmark"></span>
          <span class="checkbox-text">
            <strong>Mon adresse email est fictive / temporaire</strong><br>
            <small>⚠️ Cocher cette case si ton email n'existe pas vraiment (ex : test@test.com). Tu ne recevras pas d'email de confirmation mais certaines fonctionnalités seront limitées.</small>
          </span>
        </label>
        <div class="email-info-box" id="email-info-box">
          <span id="email-info-text">
            <!-- mis à jour en JS -->
          </span>
        </div>
      </div>
      <div class="proprio-email-notice" id="proprio-email-notice" style="display:none;">
        <span>📧 <strong>Compte propriétaire :</strong> Une adresse email réelle est obligatoire pour gérer vos restaurants et recevoir les notifications importantes.</span>
      </div>

      <button class="btn btn-glass" type="submit">Créer un compte</button>
    </form>

    <p>Déjà inscrit ? <a href="login.php">Connecte-toi</a></p>
    <p><a href="<?= isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'index.php' ?>">Retour</a></p>
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
  color: 0xffe599,
  shininess: 40,
  waveHeight: 25,
  waveSpeed: 0.9,
  zoom: 0.9
});

// Mise à jour de la box d'info selon l'état de la checkbox
(function(){
  const cb           = document.getElementById('email_fictif');
  const infoBox      = document.getElementById('email-info-box');
  const infoTxt      = document.getElementById('email-info-text');
  const emailTypeBox = document.getElementById('email-type-box');
  const propioNotice = document.getElementById('proprio-email-notice');
  const typeSelect   = document.getElementById('type_compte');

  function updateCheckbox() {
    if (cb.checked) {
      infoBox.style.background = 'rgba(255, 193, 7, 0.15)';
      infoBox.style.borderLeftColor = '#FFC107';
      infoTxt.innerHTML = '⚠️ <strong>Email fictif :</strong> Ton compte sera activé immédiatement, sans vérification. Le mot de passe oublié ne sera pas disponible par email.';
    } else {
      infoBox.style.background = 'rgba(76, 175, 80, 0.12)';
      infoBox.style.borderLeftColor = '#4CAF50';
      infoTxt.innerHTML = '✅ <strong>Email réel :</strong> Un email de vérification te sera envoyé après l\'inscription. Tu pourras aussi utiliser la récupération de mot de passe par email.';
    }
    infoBox.style.display = 'block';
  }

  function updateTypeCompte() {
    const isProprietaire = typeSelect.value === 'proprietaire';
    if (isProprietaire) {
      emailTypeBox.style.display = 'none';
      cb.checked = false;
      propioNotice.style.display = 'block';
    } else {
      emailTypeBox.style.display = 'block';
      propioNotice.style.display = 'none';
      updateCheckbox();
    }
  }

  cb.addEventListener('change', updateCheckbox);
  typeSelect.addEventListener('change', updateTypeCompte);
  updateTypeCompte(); // état initial
})();
</script>
<style>
.container {
  backdrop-filter: blur(12px);
  background: rgba(255, 255, 255, 0.29); 
}

.form input, .form select {
  width: 100%;
  margin: 10px 0;
  padding: 12px;
  border-radius: 15px;
  border: none;
  background: rgba(255, 255, 255, 0.25);
  color: #000000;
  font-size: 1rem;
  outline: none;
  transition: background 0.3s ease, transform 0.2s;
  font-family: 'HSR';
  margin-left: auto;
}
.form input:focus, .form select:focus {
  background: rgba(255, 255, 255, 0.35);
  transform: scale(1.02);
}

/* Bouton Google */
.btn-google {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  width: 100%;
  padding: 12px 20px;
  margin: 6px 0;
  background: #ffffff;
  color: #3c4043;
  border: 1px solid rgba(0, 0, 0, 0.12);
  border-radius: 12px;
  font-family: 'HSR', sans-serif;
  font-size: 1rem;
  font-weight: 600;
  text-decoration: none;
  cursor: pointer;
  transition: all 0.3s ease;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.12);
  box-sizing: border-box;
}
.btn-google:hover {
  background: #f8f8f8;
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.2);
  transform: translateY(-2px);
  color: #3c4043;
}

/* Séparateur */
.separateur {
  display: flex;
  align-items: center;
  margin: 14px 0;
  color: #888;
  font-size: 0.85rem;
  gap: 10px;
}
.separateur::before, .separateur::after {
  content: '';
  flex: 1;
  height: 1px;
  background: rgba(0, 0, 0, 0.15);
}

/* Première installation */
.first-install-notice {
  background: linear-gradient(135deg, rgba(255, 235, 205, 0.4), rgba(255, 200, 200, 0.4));
  backdrop-filter: blur(15px);
  padding: 1rem 1.5rem;
  border-radius: 1rem;
  border: 2px solid rgba(255, 107, 107, 0.3);
  box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
  text-align: center;
  margin: 0.8rem 0;
}
.first-install-notice h3 { color: #ff6b6b; margin-top: 0; margin-bottom: 0.5rem; font-size: 1.3rem; }
.first-install-notice p  { color: #333; margin: 0.4rem 0; font-size: 0.95rem; line-height: 1.4; }
.first-install-notice p strong { color: #000000a8; font-weight: 700; }

/* Email type box */
.email-type-box {
  width: 100%;
  margin: 14px auto 10px;
  text-align: left;
}

.checkbox-label {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  cursor: pointer;
  padding: 12px 14px;
  background: rgba(255, 255, 255, 0.25);
  border-radius: 12px;
  border: 1px solid rgba(0,0,0,0.1);
  transition: all 0.3s ease;
  font-family: 'HSR', sans-serif;
  font-size: 0.9rem;
  color: #333;
}
.checkbox-label:hover {
  background: rgba(255, 255, 255, 0.4);
  border-color: rgba(255, 107, 107, 0.3);
}
.checkbox-label input[type="checkbox"] {
  width: 18px !important;
  height: 18px !important;
  min-width: 18px;
  margin: 3px 0 0 0 !important;
  padding: 0 !important;
  border-radius: 4px !important;
  accent-color: #ff6b6b;
  cursor: pointer;
  flex-shrink: 0;
  transform: none !important;
}
.checkbox-label input[type="checkbox"]:focus {
  transform: none !important;
}
.checkbox-text small { color: #666; font-size: 0.8rem; line-height: 1.4; }

.email-info-box {
  display: none;
  margin-top: 8px;
  padding: 10px 14px;
  border-radius: 10px;
  border-left: 4px solid #4CAF50;
  font-size: 0.88rem;
  line-height: 1.5;
  color: #333;
  font-family: 'HSR', sans-serif;
  transition: all 0.3s ease;
}

.btn-glass {
  display: flex;
  justify-content: center;    
  text-align: center; 
  margin-top: 17px;
  font-family: 'HSR', sans-serif;
  font-size: 1.3rem;
  padding: 15px 23px;
  backdrop-filter: blur(15px);
  background: rgba(231, 173, 131, 0.44);
  color: white;
  border: none;
  border-radius: 12px;
  box-shadow: 0 8px 30px rgba(0,0,0,0.25);
  cursor: pointer;
  transition: all 0.3s ease, transform 0.2s;
  text-decoration: none;
  max-width: fit-content; 
}
.btn-glass:hover {
  color: white;
  transform: translateY(-2px);
  box-shadow: 0 12px 40px rgba(0,0,0,0.35);
  background: rgba(249, 158, 72, 0.55);
}

.proprio-email-notice {
  width: 100%;
  margin: 14px auto 10px;
  padding: 12px 14px;
  background: rgba(33, 150, 243, 0.12);
  border-left: 4px solid #2196F3;
  border-radius: 12px;
  font-family: 'HSR', sans-serif;
  font-size: 0.88rem;
  color: #1565c0;
  line-height: 1.5;
  text-align: left;
}
</style>
  </div>
</div>
<script src="address-autocomplete.js"></script>
<script src="assets/3d-flip.js"></script>

</body>
</html>
