<?php
// google_complete_profile.php
// Affiché uniquement lors de la première connexion Google
// Permet de choisir le type de compte, téléphone et adresse

session_start();
require_once 'db/config.php';
require_once __DIR__ . '/csrf_helper.php';

// Doit être connecté ET venir d'une création Google
if (!isset($_SESSION['user_id']) || empty($_SESSION['google_new_account'])) {
    header('Location: home.php');
    exit;
}

$uid = (int)$_SESSION['user_id'];

// Récupérer les données du compte fraîchement créé
$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$uid]);
$user = $stmt->fetch();
if (!$user) { header('Location: logout.php'); exit; }

$errors = [];
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (empty($_POST['csrf_token']) || !fh_verify_csrf($_POST['csrf_token'])) {
    $errors[] = 'Jeton CSRF invalide.';
  } else {
  $type_compte = in_array($_POST['type_compte'] ?? 'client', ['client', 'proprietaire'])
             ? $_POST['type_compte'] : 'client';
    $telephone = trim($_POST['telephone'] ?? '');
    $adresse_livraison = trim($_POST['adresse_livraison'] ?? '');
    $motdepasse = $_POST['motdepasse'] ?? '';
    $confirm_mdp = $_POST['confirm_motdepasse'] ?? '';

    // Validation mot de passe (optionnel mais si renseigné doit être valide)
    if ($motdepasse !== '') {
        if ($motdepasse !== $confirm_mdp) {
            $errors[] = "Les mots de passe ne correspondent pas.";
        } elseif (strlen($motdepasse) < 6) {
            $errors[] = "Mot de passe trop court (minimum 6 caractères).";
        }
    }

    if (empty($errors)) {
        if ($motdepasse !== '') {
            // L'utilisateur veut pouvoir se connecter aussi sans Google
            $hash = password_hash($motdepasse, PASSWORD_DEFAULT);
            $conn->prepare("
                UPDATE users
                SET type_compte = ?, telephone = ?, adresse_livraison = ?, motdepasse = ?
                WHERE user_id = ?
            ")->execute([$type_compte, $telephone, $adresse_livraison, $hash, $uid]);
        } else {
            $conn->prepare("
                UPDATE users
                SET type_compte = ?, telephone = ?, adresse_livraison = ?
                WHERE user_id = ?
            ")->execute([$type_compte, $telephone, $adresse_livraison, $uid]);
        }

        // Mettre à jour la session avec le bon type_compte
        $_SESSION['type_compte']       = $type_compte;
        $_SESSION['adresse_livraison'] = $adresse_livraison;

        // Supprimer le flag : cette page ne sera plus accessible
        unset($_SESSION['google_new_account']);
        $_SESSION['nouveau_compte'] = true;

        $_SESSION['success'] = "✅ Profil complété avec succès ! Bienvenue sur FoodHub 🎉";
        header('Location: tos.php');
        exit;
      }
      }
    }
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <link rel="icon" type="image/x-icon" href="FoodHubLogo.ico">
  <meta charset="utf-8">
  <title>Compléter mon profil - FoodHub</title>
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
  <audio id="player" autoplay loop>
    <source src="https://raw.githubusercontent.com/HDSachaNewLive/foodhub-assets/main/Cafe Palette.mp3" type="audio/mpeg">
  </audio>
  <?php include "slider_son.php"; ?>
  <?php include 'vanta_freeze.php'; ?>
  <main class="container">
    <h2>🎉 Bienvenue, <?= htmlspecialchars($user['nom_user']) ?> !</h2>
    <p class="subtitle">Ton compte a été créé via Google. Complète quelques infos pour bien démarrer.</p>

    <?php if (!empty($errors)): ?>
      <div class="error">
        <?php foreach ($errors as $e) echo "<div>" . htmlspecialchars($e) . "</div>"; ?>
      </div>
    <?php endif; ?>

    <form method="post" class="form">
      <?= fh_csrf_field() ?>

      <label>Type de compte :</label>
      <select name="type_compte" required>
        <option value="client" <?= (($_POST['type_compte'] ?? 'client') === 'client') ? 'selected' : '' ?>>
          👤 Client — je commande des repas
        </option>
        <option value="proprietaire" <?= (($_POST['type_compte'] ?? '') === 'proprietaire') ? 'selected' : '' ?>>
          🏪 Propriétaire — j'ajoute un/des restaurant(s)
        </option>
      </select>

      <label>Téléphone (optionnel) :</label>
      <input type="text" name="telephone" placeholder="ex : 06 12 34 56 78"
             value="<?= htmlspecialchars($_POST['telephone'] ?? '') ?>">

      <label>Adresse de livraison (optionnel) :</label>
      <input type="text" name="adresse_livraison" placeholder="ex : 12 rue de la Paix, Paris"
       value="<?= htmlspecialchars($_POST['adresse_livraison'] ?? '') ?>" data-address-autocomplete>

      <hr>

      <div class="mdp-section">
        <h4>Définir un mot de passe FoodHub (optionnel) :</h4>
        <p class="mdp-info">
          Tu te connectes actuellement uniquement via Google.<br>
          Si tu veux aussi pouvoir te connecter avec ton email et un mot de passe, définis-en un ici.
        </p>
        <input type="password" name="motdepasse" placeholder="Nouveau mot de passe (min. 6 caractères)">
        <input type="password" name="confirm_motdepasse" placeholder="Confirmer le mot de passe">
      </div>

      <button type="submit" class="btn-complete">Terminer la configuration →</button>
    </form>

    <p class="skip-link">
      <a href="tos.php" onclick="<?= "fetch('passer_etape.php'); return true;" ?>">
        Passer cette étape (je complèterai plus tard depuis mon profil)
      </a>
    </p>
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
</script>

<style>
.container {
  max-width: 780px;
  margin: 100px auto;
  padding: 2.5rem;
  border-radius: 1.5rem;
  backdrop-filter: blur(15px);
  background: rgba(255, 255, 255, 0.29);
  box-shadow: 0 8px 30px rgba(0, 0, 0, 0.2);
  text-align: center;
  font-family: 'HSR', sans-serif;
  animation: fadeIn 0.6s ease;
}

.container h2 {
  color: #ff6b6b;
  margin-bottom: 0.5rem;
  font-size: 1.9rem;
  text-shadow: 2px 2px 4px rgba(255, 107, 107, 0.3);
}

.subtitle {
  color: #555;
  margin-bottom: 1.7rem;
  font-size: 1rem;
  line-height: 1.5;
}

.form {
  margin-top: -5px;
  display: flex;
  flex-direction: column;
  gap: 0.3rem;
  text-align: left;
}

.form label {
  font-weight: 700;
  color: #333;
  margin-top: 1rem;
  display: block;
}

.form input,
.form select {
  width: 100%;
  padding: 12px;
  border-radius: 12px;
  border: none;
  background: rgba(255, 255, 255, 0.4);
  color: #0d0d0d;
  font-size: 1rem;
  font-family: 'HSR', sans-serif;
  outline: none;
  transition: all 0.3s ease;
  box-sizing: border-box;
}

.form input:focus,
.form select:focus {
  background: rgba(255, 255, 255, 0.72);
  transform: scale(1.028);
}

hr {
  margin: 1.5rem 0 0.5rem;
  border: none;
  border-top: 1px solid rgba(0,0,0,0.1);
}

.mdp-section h4 {
  color: #333;
  margin: 0.5rem 0 0.3rem;
  font-size: 1rem;
}

.mdp-info {
  color: #666;
  font-size: 0.9rem;
  line-height: 1.5;
  margin-bottom: 0.8rem;
  background: rgba(255, 193, 7, 0.12);
  padding: 0.8rem 1rem;
  border-radius: 0.8rem;
  border-left: 3px solid #FFC107;
}

.btn-complete {
  margin-top: 1.5rem;
  padding: 1rem 2rem;
  font-size: 1.1rem;
  font-weight: 600;
  font-family: 'HSR', sans-serif;
  color: white;
  background: linear-gradient(135deg, #ff6b6b, #ffc342);
  border: none;
  border-radius: 14px;
  cursor: pointer;
  box-shadow: 0 6px 18px rgba(255, 107, 107, 0.35);
  transition: all 0.35s ease;
  position: relative;
  overflow: hidden;
}

.btn-complete:hover {
  transform: translateY(-3px) scale(1.02);
  box-shadow: 0 10px 25px rgba(255, 107, 107, 0.45);
  background: linear-gradient(135deg, #ff8c42, #ff6b6b);
}

.btn-complete::after {
  content: "";
  position: absolute;
  top: 0; left: -100%;
  width: 100%;
  height: 100%;
  background: rgba(255,255,255,0.25);
  transition: all 0.5s ease;
  border-radius: 14px;
}

.btn-complete:hover::after {
  left: 0;
}
.skip-link {
  margin-top: 1.5rem;
  font-size: 0.9rem;
  margin-bottom: -10px;
}

.skip-link a {
  color: #888;
  text-decoration: none;
  transition: color 0.3s ease;
}

.skip-link a:hover {
  color: #555;
  text-decoration: underline;
}

.error {
  background: rgba(255, 77, 77, 0.2);
  padding: 12px 16px;
  border-radius: 10px;
  border-left: 4px solid #ff4d4d;
  color: #8b0000;
  font-weight: 600;
  margin-bottom: 1.5rem;
  text-align: left;
}

@keyframes fadeIn {
  from { opacity: 0; transform: translateY(12px); }
  to   { opacity: 1; transform: translateY(0); }
}
</style>
<script src="address-autocomplete.js"></script>
</body>
</html>