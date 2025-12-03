<?php
// register.php
session_start();
require_once 'db/config.php';

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
            header("Location: login.php");
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

      <label>Type de compte :</label><br>
      <select name="type_compte" class="form">
        <option value="client" <?= (($_POST['type_compte'] ?? '') === 'client') ? 'selected' : '' ?>>Client</option>
        <option value="proprietaire" <?= (($_POST['type_compte'] ?? '') === 'proprietaire') ? 'selected' : '' ?>>Propriétaire (restaurant)</option>
      </select><br>

      <button class="btn" type="submit">Créer un compte</button>
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
</style>
  </div>
</div>
<script src="assets/3d-flip.js"></script>
</body>
</html>
