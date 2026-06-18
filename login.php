<?php
// login.php
session_start();
require_once 'db/config.php';
require_once 'config_recaptcha.php';
require_once __DIR__ . '/csrf_helper.php';

// Vérifier si la bdd contient des utilisateurs
$stmt = $conn->query("SELECT COUNT(*) as nb_users FROM users");
$nb_users = $stmt->fetchColumn();
$is_first_install = ($nb_users == 0);

$ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';

// Nettoyage quotidien toutes les 24 heures
$conn->query("DELETE FROM tentatives_conn WHERE attempt_time < (NOW() - INTERVAL 1 DAY)");

$error = '';

// Récupérer les erreurs Google OAuth
if (isset($_SESSION['error_login'])) {
    $error = $_SESSION['error_login'];
    unset($_SESSION['error_login']);
}

// Mot de passe oublié
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_forgot'])) {
    header('Content-Type: application/json; charset=utf-8');
    require_once 'mail_helper.php';

  if (empty($_POST['csrf_token']) || !fh_verify_csrf($_POST['csrf_token'])) {
    echo json_encode(['success' => false, 'error' => 'Jeton CSRF invalide.']);
    exit;
  }

    $email_forgot = trim($_POST['email_forgot'] ?? '');

    if (!filter_var($email_forgot, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'error' => 'Email invalide.']);
        exit;
    }

    $stmt = $conn->prepare("SELECT user_id, nom_user, email_fictif, email_verifie, compte_actif FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email_forgot]);
    $u = $stmt->fetch();

    if (!$u || !$u['compte_actif']) {
        // Réponse volontairement floue pour la sécurité
        echo json_encode(['success' => true, 'fictif' => false]);
        exit;
    }

    if ($u['email_fictif']) {
      // Compte fictif → autoriser un reset inline MAIS ne PAS renvoyer l'user_id au client
      // On place l'user_id dans la session pour éviter toute fuite d'identifiants
      $_SESSION['forgot_fictif_user'] = (int)$u['user_id'];
      // token optionnel / délai
      try {
        $_SESSION['forgot_fictif_token'] = bin2hex(random_bytes(16));
      } catch (Exception $e) {
        $_SESSION['forgot_fictif_token'] = bin2hex(openssl_random_pseudo_bytes(16));
      }
      $_SESSION['forgot_fictif_expires'] = time() + 600; // valable 10 minutes

      // Réponse volontairement dépouillée (pas d'user_id)
      echo json_encode(['success' => true, 'fictif' => true]);
      exit;
    }

    if (!$u['email_verifie']) {
        // Email réel mais pas encore vérifié → impossible d'envoyer le reset
        echo json_encode(['success' => false, 'error' => 'Ton adresse email n\'a pas encore été vérifiée. Consulte ta boîte de réception pour trouver le lien de vérification envoyé lors de l\'inscription.']);
        exit;
    }

    // Email réel et vérifié → envoyer le lien de reset
    $sent = fh_send_reset_email($conn, (int)$u['user_id'], $u['nom_user'], $email_forgot);
    echo json_encode(['success' => true, 'fictif' => false, 'sent' => $sent]);
    exit;
}

// AJAX : Reset mdp compte fictif (inline)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_reset_fictif'])) {
  header('Content-Type: application/json; charset=utf-8');

  if (empty($_POST['csrf_token']) || !fh_verify_csrf($_POST['csrf_token'])) {
    echo json_encode(['success' => false, 'error' => 'Jeton CSRF invalide.']);
    exit;
  }

  $new_pass       = $_POST['new_pass']       ?? '';
  $confirm_pass   = $_POST['confirm_pass']   ?? '';

  // On récupère l'user_id depuis la session (évite d'envoyer l'ID au client)
  $user_id_reset = isset($_SESSION['forgot_fictif_user']) ? (int)$_SESSION['forgot_fictif_user'] : 0;
  $expires = isset($_SESSION['forgot_fictif_expires']) ? (int)$_SESSION['forgot_fictif_expires'] : 0;

  if ($user_id_reset <= 0 || $expires < time()) {
    // Pas de session active pour ce reset
    echo json_encode(['success' => false, 'error' => 'La session de réinitialisation a expiré ou est invalide. Réeessaie depuis l\'email.']);
    exit;
  }

  // Vérifier que le compte existe bien et est fictif
  $stmt = $conn->prepare("SELECT user_id FROM users WHERE user_id = ? AND email_fictif = 1 AND compte_actif = 1 LIMIT 1");
  $stmt->execute([$user_id_reset]);
  if (!$stmt->fetch()) {
    echo json_encode(['success' => false, 'error' => 'Impossible de réinitialiser ce compte ici.']);
    exit;
  }

  if (strlen($new_pass) < 6) {
    echo json_encode(['success' => false, 'error' => 'Mot de passe trop court (min. 6 caractères).']);
    exit;
  }
  if ($new_pass !== $confirm_pass) {
    echo json_encode(['success' => false, 'error' => 'Les mots de passe ne correspondent pas.']);
    exit;
  }

  $hash = password_hash($new_pass, PASSWORD_DEFAULT);
  $conn->prepare("UPDATE users SET motdepasse = ? WHERE user_id = ?")->execute([$hash, $user_id_reset]);

  // Nettoyer la session liée au reset fictif
  unset($_SESSION['forgot_fictif_user'], $_SESSION['forgot_fictif_token'], $_SESSION['forgot_fictif_expires']);

  echo json_encode(['success' => true]);
  exit;
}

// Connexion classique
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['ajax_forgot']) && !isset($_POST['ajax_reset_fictif'])) {
  if (empty($_POST['csrf_token']) || !fh_verify_csrf($_POST['csrf_token'])) {
    $error = 'Jeton CSRF invalide.';
  }

  $email      = trim($_POST['email']      ?? '');
  $motdepasse = $_POST['motdepasse']      ?? '';

    $limite_tentatives = 5;

    // Vérification reCAPTCHA
    $recaptcha_response = $_POST['g-recaptcha-response'] ?? '';
    if (!verifyRecaptcha($recaptcha_response)) {
        $error = "Veuillez valider le reCAPTCHA.";
    }
    
    $stmt = $conn->prepare("
        SELECT COUNT(*) 
        FROM tentatives_conn
        WHERE ip = ?
          AND attempt_time > (NOW() - INTERVAL 5 MINUTE)
    ");
    $stmt->execute([$ip]);
    $tentatives_ip = $stmt->fetchColumn();

    if ($tentatives_ip >= $limite_tentatives) {
        $error = "Trop de tentatives depuis cette IP. Réessaie dans 5 minutes.";
    }

    if ($error === '') {
        if ($email === '' || $motdepasse === '') {
            $error = "Email et mot de passe requis.";
        } else {
            $stmt = $conn->prepare("SELECT user_id, nom_user, motdepasse, type_compte, adresse_livraison, compte_actif, derniere_connexion, date_creation FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && !$user['compte_actif']) {
                $error = "Votre compte a été désactivé. <a href='contact_admin.php' class='btn-contact'>Contactez l'administrateur.</a>";
                $conn->prepare("INSERT INTO tentatives_conn (ip, email, attempt_time) VALUES (?, ?, NOW())")->execute([$ip, $email]);
            } elseif ($user && password_verify($motdepasse, $user['motdepasse'])) {
                $_SESSION['user_id']           = $user['user_id'];
                $_SESSION['nom_user']          = $user['nom_user'];
                $_SESSION['type_compte']       = $user['type_compte'];
                $_SESSION['adresse_livraison'] = $user['adresse_livraison'];
                // Renouveler l'ID de session après authentification pour éviter fixation
                if (function_exists('session_regenerate_id')) {
                  session_regenerate_id(true);
                }

                $last_conn_raw = $user['derniere_connexion'] ?? null;
                $is_first_login = ($last_conn_raw === null || $last_conn_raw === '' || $last_conn_raw === '0000-00-00 00:00:00');
                $prev_conn = $is_first_login ? 0 : (strtotime($last_conn_raw) ?: 0);
                $created   = strtotime($user['date_creation'] ?? '') ?: time();
                $six_am    = mktime(6, 0, 0, (int)date('n'), (int)date('j'), (int)date('Y'));
                if ($is_first_login) {
                    $_SESSION['nouveau_compte'] = true;
                } elseif ((time() - $created) >= 86400 && time() >= $six_am && $prev_conn < $six_am) {
                    $_SESSION['welcome_daily'] = true;
                }

                $conn->prepare("UPDATE users SET derniere_connexion = NOW() WHERE user_id = ?")
                     ->execute([$user['user_id']]);
                header("Location: home.php");
                exit;
            } else {
                $error = "Identifiants incorrects.";
                $conn->prepare("INSERT INTO tentatives_conn (ip, email, attempt_time) VALUES (?, ?, NOW())")->execute([$ip, $email]);
            }
        }
    }
}

$success = $_SESSION['success'] ?? null;
unset($_SESSION['success']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <link rel="icon" type="image/x-icon" href="FoodHubLogo.ico">
  <meta charset="utf-8">
  <title>Connexion - FoodHub</title>
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
  <audio id="player" autoplay loop>
    <source src="https://raw.githubusercontent.com/HDSachaNewLive/foodhub-assets/main/2010 Toyota Corolla.mp3" type="audio/mpeg">
  </audio>
  <script src="https://www.google.com/recaptcha/api.js" async defer></script>
  <?php include 'vanta_freeze.php'; ?>
  <?php include "slider_son.php"; ?>
<div class="page-wrapper">
  <div class="page flip_in" id="current-page">
  <main class="container">
    <h2>Connexion</h2>

    <?php if ($is_first_install): ?>
      <div class="first-install-notice">
        <h3>🎉 Bienvenue sur FoodHub !</h3>
        <p><strong>Première installation détectée.</strong></p>
        <p>Aucun compte n'existe encore dans la base de données.</p>
        <p>👉 <a href="register.php" class="link-highlight">Créez le premier compte administrateur</a> pour commencer à utiliser FoodHub.</p>
        <div class="info-box">
          <p>ℹ️ <strong>Information importante :</strong></p>
          <p>Le premier compte créé (user_id = 1) est unique, et sera automatiquement administrateur et disposera de tous les privilèges.</p>
        </div>
      </div>
    <?php else: ?>

    <?php if ($success): ?>
      <div class="success"><?= $success ?></div>
      <div class="nouveau-compte-notice">
        <div class="nouveau-compte-icon">🎉</div>
        <div class="nouveau-compte-text">
          <strong>Bienvenue sur FoodHub !</strong>
          <p>Nouveau sur la plateforme ? Consultez notre FAQ pour découvrir comment commander, gérer votre panier, utiliser des codes promo, et bien plus encore.</p>
        </div>
        <a href="apropos.php#faq" class="btn-faq">📖 Consulter la FAQ</a>
      </div>
    <?php endif; ?>

    <?php if ($error): ?>
      <div class="error"><?= $error ?></div>
    <?php endif; ?>

    <!-- Bouton Google OAuth -->
    <a href="foodhub_google_login.php" class="btn-google">
      <svg viewBox="0 0 24 24" width="20" height="20" xmlns="http://www.w3.org/2000/svg">
        <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
        <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
        <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/>
        <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
      </svg>
      Se connecter avec Google
    </a>

    <div class="separator"><span>ou</span></div>

    <form method="post" action="login.php" class="form" id="login-form">
      <?= fh_csrf_field() ?>
      <input type="email" name="email" placeholder="Email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"><br>
      <input type="password" name="motdepasse" placeholder="Mot de passe" required><br>
      <div class="g-recaptcha" data-sitekey="<?= RECAPTCHA_SITE_KEY ?>" style="display:flex;justify-content:left;margin:20px 0;"></div>
      <button class="btn" type="submit">Se connecter</button>
    </form>

    <!-- Lien mot de passe oublié -->
    <p style="margin-top: 0.8rem;">
      <button type="button" id="link-forgot" class="forgot-link">🔑 Mot de passe oublié ?</button>
    </p>

    <!-- ── Zone mot de passe oublié (affichée en AJAX) ── -->
    <div id="forgot-section" style="display:none;">
      <div class="separator"><span>Réinitialiser le mot de passe</span></div>

      <!-- Étape 1 : saisie email -->
      <div id="forgot-step1">
        <p class="forgot-info">Saisis l'email associé à ton compte. Selon le type de compte, tu recevras un lien ou pourras modifier ton mot de passe directement ici.</p>
        <div class="form">
          <input type="email" id="forgot-email" placeholder="Ton adresse email" autocomplete="email">
          <button class="btn btn-forgot-send" id="btn-forgot-send" type="button">Envoyer</button>
        </div>
        <div id="forgot-step1-msg" class="forgot-msg" style="display:none;"></div>
      </div>

      <!-- Étape 2a : email réel → message de confirmation -->
      <div id="forgot-step2-real" style="display:none;">
        <div class="verify-sent-box">
          <div style="font-size:2.5rem;margin-bottom:0.5rem;">📧</div>
          <h3>Email envoyé !</h3>
          <p>Un lien de réinitialisation a été envoyé à ton adresse email.<br>Vérifie ta boîte de réception et tes spams.</p>
          <p class="forgot-info">Le lien est valable <strong>1 heure</strong>.</p>
        </div>
      </div>

      <!-- Étape 2b : compte fictif → formulaire inline -->
      <div id="forgot-step2-fictif" style="display:none;">
        <div id="fictif-reset-msg" class="forgot-msg" style="display:none;"></div>
          <div class="fictif-reset-box">
          <div style="font-size:2rem;margin-bottom:0.5rem;">🔑</div>
          <h3>Modifier ton mot de passe</h3>
          <p class="forgot-info">Ton compte utilise une adresse fictive. Tu peux modifier ton mot de passe directement ici.</p>
          <div class="form">
            <input type="password" id="fictif-new-pass"     placeholder="Nouveau mot de passe (min. 6 caractères)">
            <input type="password" id="fictif-confirm-pass" placeholder="Confirmer le mot de passe">
            <button class="btn btn-forgot-send" id="btn-fictif-reset" type="button">Modifier le mot de passe</button>
          </div>
        </div>
      </div>
    </div>

    <p>Pas encore de compte ? <a href="register.php">Inscription</a></p>

    <?php endif; ?>

    <p><a href="index.php">Retour</a></p>
  </main>

<script src="https://cdn.jsdelivr.net/npm/three@0.149.0/build/three.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/vanta/dist/vanta.waves.min.js"></script>

<script>
window.vantaEffect = VANTA.WAVES({
  el: "body",
  mouseControls: true,
  touchControls: true,
  gyroControls: false,
  minHeight: 935.00,
  minWidth: 200.00,
  scale: 1.00,
  scaleMobile: 1.00,
  color: 0xffe599,
  shininess: 40,
  waveHeight: 25,
  waveSpeed: 0.9,
  zoom: 0.9
});

// ── Mot de passe oublié — logique AJAX ─────────────────────
document.addEventListener('DOMContentLoaded', () => {
  const linkForgot       = document.getElementById('link-forgot');
  const forgotSection    = document.getElementById('forgot-section');
  const step1            = document.getElementById('forgot-step1');
  const step2Real        = document.getElementById('forgot-step2-real');
  const step2Fictif      = document.getElementById('forgot-step2-fictif');
  const emailInput       = document.getElementById('forgot-email');
  const btnSend          = document.getElementById('btn-forgot-send');
  const step1Msg         = document.getElementById('forgot-step1-msg');
  const fictifNewPass    = document.getElementById('fictif-new-pass');
  const fictifConfirm    = document.getElementById('fictif-confirm-pass');
  const btnFictifReset   = document.getElementById('btn-fictif-reset');
  const fictifMsg        = document.getElementById('fictif-reset-msg');

  if (!linkForgot) return;

  // Afficher / masquer la section
  linkForgot.addEventListener('click', (e) => {
    e.preventDefault();
    const isVisible = forgotSection.style.display !== 'none';
    forgotSection.style.display = isVisible ? 'none' : 'block';
    if (!isVisible) forgotSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
  });

  // Afficher un message inline dans la zone step1
  function showStep1Msg(text, type = 'error') {
    step1Msg.innerHTML    = text;
    step1Msg.className    = 'forgot-msg ' + type;
    step1Msg.style.display = 'block';
  }

  function showFictifMsg(text, type = 'error') {
    fictifMsg.innerHTML    = text;
    fictifMsg.className    = 'forgot-msg ' + type;
    fictifMsg.style.display = 'block';
  }

  // Étape 1 : envoyer la demande
  if (btnSend) {
    btnSend.addEventListener('click', async () => {
      const email = emailInput.value.trim();
      if (!email) { showStep1Msg('Saisis ton adresse email.'); return; }

      btnSend.disabled       = true;
      btnSend.textContent    = 'Envoi en cours…';
      step1Msg.style.display = 'none';

      try {
        const fd = new FormData();
        fd.append('ajax_forgot', '1');
        fd.append('email_forgot', email);
        const csrfEl = document.querySelector('input[name="csrf_token"]');
        if (csrfEl) fd.append('csrf_token', csrfEl.value);

        const resp = await fetch('login.php', { method: 'POST', body: fd });
        const data = await resp.json();

        if (!data.success) {
          showStep1Msg(data.error || 'Une erreur est survenue.');
          btnSend.disabled    = false;
          btnSend.textContent = 'Envoyer';
          return;
        }

        if (data.fictif) {
          // Compte fictif → formulaire inline (le serveur garde l'user_id en session, on ne l'expose pas au client)
          step1.style.display      = 'none';
          step2Fictif.style.display = 'block';
        } else {
          // Email réel ou compte introuvable → même affichage (sécurité)
          step1.style.display   = 'none';
          step2Real.style.display = 'block';
        }
      } catch (err) {
        showStep1Msg('Erreur réseau. Réessaie dans un instant.');
        btnSend.disabled    = false;
        btnSend.textContent = 'Envoyer';
      }
    });

    // Appuyer sur Entrée dans le champ email
    emailInput.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') { e.preventDefault(); btnSend.click(); }
    });
  }

  // Étape 2b : reset mot de passe fictif
      if (btnFictifReset) {
    btnFictifReset.addEventListener('click', async () => {
      const pass    = fictifNewPass.value;
      const confirm = fictifConfirm.value;

      if (!pass || !confirm) { showFictifMsg('Remplis les deux champs.'); return; }
      if (pass.length < 6)   { showFictifMsg('Mot de passe trop court (min. 6 caractères).'); return; }
      if (pass !== confirm)   { showFictifMsg('Les mots de passe ne correspondent pas.'); return; }

      btnFictifReset.disabled    = true;
      btnFictifReset.textContent = 'Modification…';
      fictifMsg.style.display    = 'none';

      try {
        const fd = new FormData();
        fd.append('ajax_reset_fictif', '1');
        fd.append('new_pass',       pass);
        fd.append('confirm_pass',   confirm);
        const csrfEl2 = document.querySelector('input[name="csrf_token"]');
        if (csrfEl2) fd.append('csrf_token', csrfEl2.value);

        const resp = await fetch('login.php', { method: 'POST', body: fd });
        const data = await resp.json();

        if (data.success) {
          showFictifMsg('✅ Mot de passe modifié ! Tu peux maintenant te connecter.', 'success');
          fictifNewPass.value    = '';
          fictifConfirm.value    = '';
          btnFictifReset.textContent = 'Modifié ✓';
        } else {
          showFictifMsg(data.error || 'Une erreur est survenue.');
          btnFictifReset.disabled    = false;
          btnFictifReset.textContent = 'Modifier le mot de passe';
        }
      } catch (err) {
        showFictifMsg('Erreur réseau. Réessaie.');
        btnFictifReset.disabled    = false;
        btnFictifReset.textContent = 'Modifier le mot de passe';
      }
    });
  }
});
</script>

<style>
.container {
  backdrop-filter: blur(12px);
  background: rgba(255, 255, 255, 0.29);
}

.form input {
  width: 95%;
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
}
.form input:focus {
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
  margin: 10px 0 6px;
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
.separator {
  display: flex;
  align-items: center;
  margin: 14px 0;
  color: #888;
  font-size: 0.9rem;
  gap: 10px;
}
.separator::before, .separator::after {
  content: '';
  flex: 1;
  height: 1px;
  background: rgba(0, 0, 0, 0.15);
}

/* Lien mot de passe oublié */
.forgot-link {
  color: #7cc6e6;
  font-weight: 600;
  margin-top: 7px;
  text-decoration: none;
  transition: all 0.3s ease;
  font-size: 0.95rem;
  background-color: rgba(161, 241, 240, 0.6);
}
.forgot-link:hover { color: #5ab3d8; text-decoration: underline;   background: none; 
background-color: rgba(63, 219, 217, 0.6);
}

/* Zone forgot password */
#forgot-section {
  animation: fadeInUp 0.4s ease;
}
.forgot-info {
  color: #555;
  font-size: 0.9rem;
  line-height: 1.5;
  margin-bottom: 0.8rem;
}

/* Messages inline */
.forgot-msg {
  padding: 10px 14px;
  border-radius: 10px;
  margin-top: 11px;
  font-size: 0.9rem;
  font-weight: 600;
  line-height: 1.4;
  margin-bottom: 11px;
}
.forgot-msg.error   { background: rgba(255,77,77,0.18); border-left: 4px solid #ff4d4d; color: #8b0000; }
.forgot-msg.success { background: rgba(76,175,80,0.18); border-left: 4px solid #4CAF50; color: #2e7d32; }
.forgot-msg.info    { background: rgba(124,198,230,0.18); border-left: 4px solid #7cc6e6; color: #1565c0; }

/* Bouton envoi forgot */
.btn-forgot-send {
  display: inline-flex;
  justify-content: center;
  align-items: center;
  padding: 0.8rem 1.6rem;
  font-size: 1rem;
  font-weight: 600;
  color: #fff;
  background: linear-gradient(135deg, #ff6b6b, #ffc342ff);
  border: none;
  border-radius: 14px;
  cursor: pointer;
  text-decoration: none;
  box-shadow: 0 6px 18px rgba(0,0,0,0.18);
  transition: all 0.35s ease;
  overflow: hidden;
  text-align: center;
  margin-left: ;
}

.btn-forgot-send::after {
  content: "";
  position: absolute;
  top: 0; left: -100%;
  width: 100%;
  height: 100%;
  background: rgba(255,255,255,0.25);
  transition: all 0.4s ease;
  border-radius: 14px;
}

.btn-forgot-send:hover::after { left: 0; }
.btn-forgot-send:hover { 
  transform: translateY(-4px) scale(1.03); 
  box-shadow: 0 12px 25px rgba(0,0,0,0.25); 
  background: linear-gradient(135deg, #ff6b6b, #ffc342ff);
}
.btn-forgot-send:disabled {
  opacity: 0.65;
  cursor: not-allowed;
  transform: none;
}

/* Box confirmation email envoyé */
.verify-sent-box {
  background: rgba(76,175,80,0.12);
  border-left: 4px solid #4CAF50;
  border-radius: 1rem;
  padding: 1.5rem;
  text-align: center;
}
.verify-sent-box h3 { color: #2e7d32; margin: 0 0 0.5rem; }
.verify-sent-box p  { color: #444; font-size: 0.95rem; line-height: 1.5; margin: 0.4rem 0; }

/* Box reset fictif */
.fictif-reset-box {
  background: rgba(124,198,230,0.12);
  border-left: 4px solid #7cc6e6;
  border-radius: 1rem;
  padding: 1.5rem;
  text-align: center;
}
.fictif-reset-box h3 { color: #1565c0; margin: 0 0 0.5rem; }
.fictif-reset-box .form input { background: rgba(255,255,255,0.5); }

/* Première installation */
.first-install-notice {
  background: linear-gradient(135deg, rgba(255,235,205,0.4), rgba(255,200,200,0.4));
  backdrop-filter: blur(15px);
  padding: 1rem 1.5rem;
  border-radius: 1rem;
  border: 2px solid rgba(255,107,107,0.3);
  box-shadow: 0 8px 25px rgba(0,0,0,0.15);
  text-align: center;
  margin: 0.8rem 0;
}
.first-install-notice h3 { color: #ff6b6b; margin-top: 0; margin-bottom: 0.5rem; font-size: 1.3rem; }
.first-install-notice p  { color: #333; margin: 0.4rem 0; font-size: 0.95rem; }
.first-install-notice p strong { color: #000000a8; }

.link-highlight {
  color: #ff6b6b; font-weight: 700; text-decoration: none;
  padding: 0.3rem 0.8rem; background: rgba(255,107,107,0.1);
  border-radius: 8px; transition: all 0.3s ease; display: inline-block; margin: 0.5rem 0;
}
.link-highlight:hover { background: rgba(255,107,107,0.2); transform: scale(1.025); color: #ff8c42; }

.info-box {
  background: rgba(33,150,243,0.1); border-left: 4px solid #50d3fbff;
  padding: 1rem; border-radius: 0.8rem; margin-top: 1.5rem; text-align: left;
}
.info-box p { margin: 0.5rem 0; font-size: 0.95rem; color: #36a6d6ff; }
.info-box p:first-child { font-weight: 700; color: #2196F3; }

.btn-contact {
  color: #ff6b6b; font-weight: 700; text-decoration: none;
  padding: 0.3rem 0.8rem; background: rgba(255,107,107,0.1);
  border-radius: 8px; transition: all 0.3s ease; display: inline-block;
}
.btn-contact:hover { background: rgba(255,107,107,0.2); transform: scale(1.025); color: #ff8c42; }

/* Bloc nouveau compte */
.nouveau-compte-notice {
  display: flex; align-items: center; gap: 1rem;
  background: linear-gradient(135deg, rgba(255,235,205,0.45), rgba(255,193,7,0.2));
  border: 2px solid rgba(255,193,7,0.4); border-radius: 1.2rem;
  padding: 1.2rem 1.4rem; margin-bottom: 1.2rem;
  box-shadow: 0 4px 16px rgba(255,193,7,0.15);
  animation: fadeInNotice 0.5s ease; flex-wrap: wrap; margin-bottom: 0;
}
.nouveau-compte-icon { font-size: 2.2rem; flex-shrink: 0; }
.nouveau-compte-text { flex: 1; text-align: left; min-width: 180px; }
.nouveau-compte-text strong { display: block; color: #856404; font-size: 1.05rem; margin-bottom: 0.3rem; }
.nouveau-compte-text p  { margin: 0; color: #5a4200; font-size: 0.9rem; line-height: 1.5; }
.btn-faq {
  display: inline-flex; align-items: center; gap: 0.4rem;
  padding: 0.7rem 1.2rem;
  background: linear-gradient(135deg, #FFC107, #FF9800);
  color: white; font-family: 'HSR', sans-serif; font-weight: 600;
  font-size: 0.95rem; border-radius: 0.9rem; text-decoration: none;
  white-space: nowrap; box-shadow: 0 4px 14px rgba(255,152,0,0.35);
  transition: all 0.4s ease; flex-shrink: 0;
}
.btn-faq:hover { transform: translateY(-3px) scale(1.04); box-shadow: 0 8px 22px rgba(255,152,0,0.5); color: white; }
@keyframes fadeInNotice {
  from { opacity:0; transform: translateY(-8px); }
  to   { opacity:1; transform: translateY(0); }
}
@keyframes fadeInUp {
  from { opacity:0; transform: translateY(10px); }
  to   { opacity:1; transform: translateY(0); }
}
</style>
  </div>
</div>
<script src="assets/3d-flip.js"></script>
</body>
</html>
