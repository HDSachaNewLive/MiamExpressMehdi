<?php
// login.php
session_start();
require_once 'db/config.php';
require_once 'config_recaptcha.php';

//vérifier si la bdd contient des utilisateurs
$stmt = $conn->query("SELECT COUNT(*) as nb_users FROM users");
$nb_users = $stmt->fetchColumn();
$is_first_install = ($nb_users == 0);//booléan qui a un bon boule

//obtenir ip des client
$ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';

// Nettoyage quotidien toute les 24 heure
$conn->query("DELETE FROM tentatives_conn WHERE attempt_time < (NOW() - INTERVAL 1 DAY)");

$error = '';

// Récupérer les erreurs Google OAuth transmises par google_callback.php
if (isset($_SESSION['error_login'])) {
    $error = $_SESSION['error_login'];
    unset($_SESSION['error_login']);
}

// Si formulaire envoyé
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $motdepasse = $_POST['motdepasse'] ?? '';
    $recaptcha_response = $_POST['g-recaptcha-response'] ?? '';

    // email pour la vérification brute force
    $email_verif = $email !== '' ? $email : '';

    // limite tentatives
    $limite_tentatives = 5;

    // Limite tentatives par IP ET EMAIL
    $stmt = $conn->prepare("
        SELECT COUNT(*) 
        FROM tentatives_conn
        WHERE ip = ?
          AND attempt_time > (NOW() - INTERVAL 5 MINUTE)
    ");
    $stmt->execute([$ip]);
    $tentatives_ip = $stmt->fetchColumn();

    // Si limite atteinte alors blocage
    if ($tentatives_ip >= $limite_tentatives) {
        $error = "Trop de tentatives depuis cette IP. Réessaie dans 5 minutes.";
    } 
    // On ne continue que si aucune erreur
    if ($error === '') {
        if ($email === '' || $motdepasse === '') {
            $error = "Email et mot de passe requis.";
        } else {
            $stmt = $conn->prepare("SELECT user_id, nom_user, motdepasse, type_compte, adresse_livraison, compte_actif FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && !$user['compte_actif']) {
                $error = "Votre compte a été désactivé. <a href='contact_admin.php' class='btn-contact'>Contactez l'administrateur.</a>";
                 
                // Enregistrer la tentative
                $stmt = $conn->prepare("
                  INSERT INTO tentatives_conn (ip, email, attempt_time)
                  VALUES (?, ?, NOW())
                  ");
                $stmt->execute([$ip, $email_verif]);
            } elseif ($user && password_verify($motdepasse, $user['motdepasse'])) {

                // Connexion réussie et reset possible des tentatives

                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['nom_user'] = $user['nom_user'];
                $_SESSION['type_compte'] = $user['type_compte'];
                $_SESSION['adresse_livraison'] = $user['adresse_livraison'];

                header("Location: home.php");
                exit;

            } else {
                $error = "Identifiants incorrects.";

                // Enregistrer la tentative ratée
                $stmt = $conn->prepare("
                  INSERT INTO tentatives_conn (ip, email, attempt_time)
                  VALUES (?, ?, NOW())
                  ");
                $stmt->execute([$ip, $email_verif]);

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
  <meta charset="utf-8">
  <title>Connexion - FoodHub</title>
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
  <audio id="player" autoplay loop>
  <source src="assets/2010 Toyota Corolla.mp3" type="audio/mpeg"></audio>
  <script src="https://www.google.com/recaptcha/api.js" async defer></script>
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
      <div class="success"><?= htmlspecialchars($success) ?></div>
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

    <div class="separator">
      <span>ou</span>
    </div>

    <form method="post" action="login.php" class="form">
      <input type="email" name="email" placeholder="Email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"><br>
      
      <input type="password" name="motdepasse" placeholder="Mot de passe" required><br>

      <div class="g-recaptcha" data-sitekey="<?= RECAPTCHA_SITE_KEY ?>" style="display: flex; justify-content: left; margin: 20px 0;"></div>

      <button class="btn" type="submit">Se connecter</button>
    </form>

    <p>Pas encore de compte ? <a href="register.php">Inscription</a></p>
    
    <?php endif; ?>
    
    <p><a href="index.php">Retour</a></p>
  </main>

<!-- scripts du fond 3D -->
<script src="https://cdn.jsdelivr.net/npm/three@0.149.0/build/three.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/vanta/dist/vanta.waves.min.js"></script>

<script>
VANTA.WAVES({
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
})
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
  margin: 10px 0 6px 0;
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

/* Séparateur "ou" */
.separator {
  display: flex;
  align-items: center;
  margin: 14px 0;
  color: #888;
  font-size: 0.9rem;
  gap: 10px;
}
.separator::before,
.separator::after {
  content: '';
  flex: 1;
  height: 1px;
  background: rgba(0, 0, 0, 0.15);
}

/* Style pour le message de première installation */
.first-install-notice {
  background: linear-gradient(135deg, rgba(255, 235, 205, 0.4), rgba(255, 200, 200, 0.4));
  backdrop-filter: blur(15px);
  padding: 2rem;
  border-radius: 1.5rem;
  border: 2px solid rgba(255, 107, 107, 0.3);
  box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
  text-align: center;
  margin: 1rem 0;
}

.first-install-notice h3 {
  color: #ff6b6b;
  margin-top: 0;
  margin-bottom: 1rem;
  font-size: 1.8rem;
  text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.1);
}

.first-install-notice p {
  color: #333;
  margin: 0.8rem 0;
  font-size: 1.1rem;
  line-height: 1.6;
}

.first-install-notice p strong {
  color: #000000a8;
  font-weight: 700;
}

.link-highlight {
  color: #ff6b6b;
  font-weight: 700;
  text-decoration: none;
  padding: 0.3rem 0.8rem;
  background: rgba(255, 107, 107, 0.1);
  border-radius: 8px;
  transition: all 0.3s ease;
  display: inline-block;
  margin: 0.5rem 0;
}

.link-highlight:hover {
  background: rgba(255, 107, 107, 0.2);
  transform: scale(1.025);
  color: #ff8c42;
}

.info-box {
  background: rgba(33, 150, 243, 0.1);
  border-left: 4px solid #50d3fbff;
  padding: 1rem;
  border-radius: 0.8rem;
  margin-top: 1.5rem;
  text-align: left;
}

.info-box p {
  margin: 0.5rem 0;
  font-size: 0.95rem;
  color: #36a6d6ff;
}

.info-box p:first-child {
  font-weight: 700;
  color: #2196F3;
}

.btn-contact {
  color: #ff6b6b;
  font-weight: 700;
  text-decoration: none;
  padding: 0.3rem 0.8rem;
  background: rgba(255, 107, 107, 0.1);
  border-radius: 8px;
  transition: all 0.3s ease;
  display: inline-block;
}

.btn-contact:hover {
  background: rgba(255, 107, 107, 0.2);
  transform: scale(1.025);
  color: #ff8c42;
}
</style>
  </div>
</div>
<script src="assets/3d-flip.js"></script>
</body>
</html>
