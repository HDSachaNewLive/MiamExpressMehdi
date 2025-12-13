<?php
// profile.php
session_start();
require_once 'db/config.php';
if (!isset($_SESSION['user_id'])) { 
  header('Location: login.php'); 
  exit; 
}

$uid = (int)$_SESSION['user_id'];
$msg = '';
$errors = [];

// obtenir données existantes
$stmt = $conn->prepare("SELECT nom_user, user_id, email, telephone, adresse_livraison, type_compte, date_creation FROM users WHERE user_id = ?");
$stmt->execute([$uid]);
$user = $stmt->fetch();
if (!$user) { die("Utilisateur introuvable."); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = trim($_POST['nom_user'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $tel = trim($_POST['telephone'] ?? '');
    $adresse = trim($_POST['adresse_livraison'] ?? '');
    $new_pass = $_POST['motdepasse'] ?? '';
    $confirm_pass = $_POST['confirm_motdepasse'] ?? '';

    if ($nom === '' || $email === '') $errors[] = "Nom et email requis.";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Email invalide.";

    if ($email !== $user['email']) {
      $s = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
      $s->execute([$email, $uid]);
      if ($s->fetch()) $errors[] = "Cet email est déjà utilisé.";
    }

    if ($new_pass !== '') {
      if ($new_pass !== $confirm_pass) $errors[] = "Les mots de passe ne correspondent pas.";
      elseif (strlen($new_pass) < 6) $errors[] = "Mot de passe trop court (6+).";
    }

    if (empty($errors)) {
      if ($new_pass !== '') {
        $hash = password_hash($new_pass, PASSWORD_DEFAULT);
        $u = $conn->prepare("UPDATE users SET nom_user=?, email=?, telephone=?, adresse_livraison=?, motdepasse=? WHERE user_id=?");
        $u->execute([$nom, $email, $tel, $adresse, $hash, $uid]);
      } else {
        $u = $conn->prepare("UPDATE users SET nom_user=?, email=?, telephone=?, adresse_livraison=? WHERE user_id=?");
        $u->execute([$nom, $email, $tel, $adresse, $uid]);
      }
      $msg = "Profil mis à jour.";
      $_SESSION['nom_user'] = $nom;
      $_SESSION['adresse_livraison'] = $adresse;
      // rafraichir les données de l'utilisateur dans la BDD
      $stmt = $conn->prepare("SELECT nom_user, email, telephone, adresse_livraison, type_compte, date_creation FROM users WHERE user_id = ?");
      $stmt->execute([$uid]);
      $user = $stmt->fetch();
    }
}
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <title>Profil</title>
  <link rel="stylesheet" href="assets/style.css">
  </head>
<body>
  <?php include 'sidebar.php'; ?>
  <audio id="player" autoplay loop> <source src="assets\Account Settings Wii U System Music.mp3" type="audio/mpeg"> </audio>
  <?php include "slider_son.php"; ?>
  <style>
    #volume-slider {
    background: linear-gradient(135deg, #33b0d2ff, #58edf5ff); }
    #volume-button {
    background: linear-gradient(135deg, #33b0d2ff, #58edf5ff);
    }
  </style>
  <main class="container">
  <h2>Mon profil</h2>

  <?php if ($msg): ?><div class="success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
  <?php if (!empty($errors)): ?><div class="error"><?php foreach($errors as $e) echo "<div>".htmlspecialchars($e)."</div>"; ?></div><?php endif; ?>

  <form method="post" action="profile.php" class="form">
    <input type="text" name="nom_user" required value="<?= htmlspecialchars($_POST['nom_user'] ?? $user['nom_user']) ?>"><br>
    <input type="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? $user['email']) ?>"><br>
    <input type="text" name="telephone" placeholder="Téléphone" value="<?= htmlspecialchars($_POST['telephone'] ?? $user['telephone']) ?>"><br>
    <input type="text" name="adresse_livraison" placeholder="Adresse de livraison" value="<?= htmlspecialchars($_POST['adresse_livraison'] ?? $user['adresse_livraison']) ?>"><br>
    <p style="color:rgba(0, 0, 0, 0.75); font-size:1rem; margin-bottom:15px;">
    Compte créé le : 
    <b><?= htmlspecialchars(date("d/m/Y H:i", strtotime($user['date_creation']))) ?></b>
</p>

    <hr>
    <p>Changer le mot de passe (laisser vide pour garder l'actuel)</p>
    <input type="password" name="motdepasse" placeholder="Nouveau mot de passe"><br>
    <input type="password" name="confirm_motdepasse" placeholder="Confirmer le mot de passe"><br>

    <button class="btn" type="submit">Sauvegarder les modifications</button>
  </form>

  <hr>
  <h4>Supprimer le compte</h4>
  <?php if ($user['user_id'] != 1): ?>
  <form method="post" action="delete_account.php" onsubmit="return confirm('Tu es sûr ? Cette action est IRRÉVERSIBLE.')">
    <button class="btn-del" type="submit">Supprimer mon compte</button>
  </form>
  <?php endif; ?>
  <?php if ($user['user_id'] === 1): ?>
  <p>Vous ne pouvez pas supprimer votre compte.</p>
  <ul>
    <li>ℹ️ Vous avez l'ID utilisateur n°1, par conséquent vous êtes administrateur et votre rôle est indispensable au fonctionnement du site.</li>
  </ul>
  <?php endif; ?>

  <p><a href="home.php">← Retour</a></p>

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
  color: 0x7cc6e6,
  shininess: 25,
  waveHeight: 25,
  waveSpeed: 0.9,
  zoom: 0.9
})
</script>

<style>
.container {
  max-width: 600px;
  margin: 100px auto;
  padding: 40px;
  border-radius: 20px;
  backdrop-filter: blur(15px);
  background: rgba(255, 255, 255, 0.15);
  box-shadow: 0 8px 30px rgba(0, 0, 0, 0.25);
  border: 1px solid rgba(255, 255, 255, 0.25);
  color: #fff;
  text-align: center;
  font-family: 'HSR', sans-serif;
  animation: fadeIn 0.8s ease;
}

.container h1 {
  color: #f37163;
}
.container h4 {
  color:rgba(0, 0, 0, 0.75);
} 
.container p {
  color: #000000cb;
}

.form input {
  width: 90%;
  margin: 10px 0;
  padding: 12px;
  border-radius: 10px;
  border: none;
  background: rgba(255, 255, 255, 0.25);
  color: #000000;
  font-size: 1rem;
  outline: none;
  transition: background 0.3s ease, transform 0.2s;
  font-family: 'HSR';
}
hr {
  margin-top: 20px;
}
.form input:focus {
  background: rgba(255, 255, 255, 0.35);
  transform: scale(1.02);
}

.btn {
  font-family: 'HSR', sans-serif;
  background: var(--accent);
  color: white;
  border: none;
  border-radius: 12px;
  padding: 10px 18px;
  cursor: pointer;
  transition: all 0.3s ease;
}

.btn:hover {
  background: var(--accent-dark);
  transform: translateY(-2px);
}

.container .btn-del {
  padding: 15px 23px;
  font-size: 1.3rem;
  backdrop-filter: blur(15px);
  background: rgba(231, 131, 131, 0.62);
  box-shadow: 0 8px 30px rgba(0, 0, 0, 0.25);
  border: none;
  border-radius: 12px;
  color: #fff;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease, transform 0.2s;
}

.container .btn-del:hover {
  background: rgba(255, 100, 100, 0.75);
  box-shadow: 0 8px 35px rgba(255, 80, 80, 0.5);
  transform: translateY(-3px) scale(1.03);
}

.container .btn-del:active {
  transform: scale(0.98);
  box-shadow: 0 5px 20px rgba(255, 80, 80, 0.4);
}
.success {
  background: rgba(0, 255, 127, 0.25);
  padding: 10px;
  border-radius: 10px;
  margin-bottom: 15px;
}

.error {
  background: rgba(255, 77, 77, 0.25);
  padding: 10px;
  border-radius: 10px;
  margin-bottom: 15px;
}

@keyframes fadeIn {
  from { opacity: 0; transform: translateY(10px); }
  to { opacity: 1; transform: translateY(0); }
}
ul li {
      background: rgba(255, 255, 255, 0.12);
      border: 1px solid rgba(255, 255, 255, 0.25);
      border-radius: 12px;
      padding: 12px 18px;
      margin-bottom: 10px;
      text-align: center;
      display: flex;
      justify-content: space-between;
      align-items: center;
      transition: all 0.3s ease;
      color: rgba(53, 53, 53, 0.85);
    }

ul li:hover {
  background: rgba(255, 255, 255, 0.22);
  transform: translateY(-2px);
}

</style>

</main>
</body>
</html>
