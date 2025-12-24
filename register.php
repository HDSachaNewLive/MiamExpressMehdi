<?php
// register.php
session_start();
require_once 'db/config.php';

// Vérifier si la BDD contient des utilisateurs
$stmt = $conn->query("SELECT COUNT(*) as nb_users FROM users");
$nb_users = $stmt->fetchColumn();
$is_first_install = ($nb_users == 0);

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = trim($_POST['nom'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telephone = trim($_POST['telephone'] ?? '');
    $motdepasse = $_POST['motdepasse'] ?? '';
    $adresse_livraison = trim($_POST['adresse_livraison'] ?? '');
    $type_compte = in_array($_POST['type_compte'] ?? 'client', ['client','proprietaire']) ? $_POST['type_compte'] : 'client';

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
            $stmt = $conn->prepare("INSERT INTO users (nom_user, email, telephone, motdepasse, adresse_livraison, type_compte) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$nom, $email, $telephone, $hash, $adresse_livraison, $type_compte]);
            $_SESSION['success'] = "Compte créé. Tu peux te connecter.";
            header("Location: tos.php");
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <title>Inscription - FoodHub</title>
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
  <audio id="player" autoplay  loop> <source src="assets\Nintendo DSi Shop Theme (High Quality, 2019 Remastered).mp3" type="audio/mpeg"> </audio>
  <?php include "slider_son.php"; ?>
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

    <?php if (!empty($errors)): ?>
      <div class="error">
        <?php foreach ($errors as $e) echo "<div>".htmlspecialchars($e)."</div>"; ?>
      </div>
    <?php endif; ?>

    <form method="post" action="register.php" class="form">
      <input type="text" name="nom" placeholder="Nom complet" required value="<?= htmlspecialchars($_POST['nom'] ?? '') ?>"><br>
      <input type="email" name="email" placeholder="Email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"><br>
      <input type="text" name="telephone" placeholder="Téléphone (optionnel)" value="<?= htmlspecialchars($_POST['telephone'] ?? '') ?>"><br>
      <input type="text" name="adresse_livraison" placeholder="Adresse de livraison (optionnel)" value="<?= htmlspecialchars($_POST['adresse_livraison'] ?? '') ?>"><br>
      <input type="password" name="motdepasse" placeholder="Mot de passe" required><br>
      <br>
      <label>Type de compte :</label><br>
      <select name="type_compte" class="form">
        <option value="client" <?= (($_POST['type_compte'] ?? '') === 'client') ? 'selected' : '' ?>>Client</option>
        <option value="proprietaire" <?= (($_POST['type_compte'] ?? '') === 'proprietaire') ? 'selected' : '' ?>>Propriétaire (restaurant)</option>
      </select><br>

      <button class="btn btn-glass" type="submit">Créer un compte</button>
    </form>

    <p>Déjà inscrit ? <a href="login.php">Connecte-toi</a></p>
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
.form .label {
  margin-top: 330px;
}

/* Style pour le message de première installation */
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

.first-install-notice h3 {
  color: #ff6b6b;
  margin-top: 0;
  margin-bottom: 0.5rem;
  font-size: 1.3rem;
  text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.1);
}

.first-install-notice p {
  color: #333;
  margin: 0.4rem 0;
  font-size: 0.95rem;
  line-height: 1.4;
}

.first-install-notice p strong {
  color: #000000a8;
  font-weight: 700;
}

.btn-glass {
  display: flex;
  justify-content: center;    
  text-align: center; 
  margin-top: 10px;
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
</style>
  </div>
</div>
<script src="assets/3d-flip.js"></script>
</body>
</html>
