<?php
// login.php
session_start();
require_once 'db/config.php';
require_once 'config_recaptcha.php';

// Obtenir ip des client
$ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';

// Nettoyage quotidien toute les 24 heure
$conn->query("DELETE FROM tentatives_conn WHERE attempt_time < (NOW() - INTERVAL 1 DAY)");

$error = '';

// Si formulaire envoyé
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $motdepasse = $_POST['motdepasse'] ?? '';
    $recaptcha_response = $_POST['g-recaptcha-response'] ?? '';

    // Ã©mail pour la vérification brute force
    $email_verif = $email !== '' ? $email : '';

    // liimite tentatives
    $limite_tentatives = 5;

    // Limite tentatives par IP
$limite_tentatives = 5;

$stmt = $conn->prepare("
    SELECT COUNT(*) 
    FROM tentatives_conn
    WHERE ip = ?
      AND attempt_time > (NOW() - INTERVAL 5 MINUTE)
");
$stmt->execute([$ip]);
$tentatives_ip = $stmt->fetchColumn();

// Si limite atteinte - blocage
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
</style>
  </div>
</div>
<script src="assets/3d-flip.js"></script>
</body>
</html>