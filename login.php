<?php
// login.php
session_start();
require_once 'db/config.php';
require_once 'config_recaptcha.php';

//vérifier si la bdd contient des utilisateurs
$stmt = $conn->query("SELECT COUNT(*) as nb_users FROM users");
$nb_users = $stmt->fetchColumn();
$is_first_install = ($nb_users == 0);

//obtenir ip des client
$ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';

// Nettoyage quotidien toute les 24 heure
$conn->query("DELETE FROM tentatives_conn WHERE attempt_time < (NOW() - INTERVAL 1 DAY)");

$error = '';

// Si formulaire envoyé
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $motdepasse = $_POST['motdepasse'] ?? '';
    $recaptcha_response = $_POST['g-recaptcha-response'] ?? '';

    // email pour la vérification brute force
    $email_verif = $email !== '' ? $email : '';

    // limite tentatives
    $limite_tentatives = 5;

    // Limite tentatives par IP
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
                $error = "Votre compte a été désactivé. Contactez l'administrateur.";
                
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
      <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

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
  minHeight: 885.00,
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
</style>
  </div>
</div>
<script src="assets/3d-flip.js"></script>
</body>
</html>
