<?php
// profile_proprio.php
session_start();
require_once 'db/config.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }

$uid = (int)$_SESSION['user_id'];
$msg = '';
$errors = [];

// obtenir données existantes
$stmt = $conn->prepare("SELECT nom_user, email, telephone, adresse_livraison, type_compte, date_creation FROM users WHERE user_id = ?");
$stmt->execute([$uid]);
$user = $stmt->fetch();
if (!$user) { die("Utilisateur introuvable."); }

if ($user['type_compte'] !== 'proprietaire') {
    header('Location: profile.php'); // rediriger les autres vers le profil normal
    exit;
}

// récupérer restaurants du proprio
$stmt = $conn->prepare("SELECT * FROM restaurants WHERE proprietaire_id = ? ORDER BY nom_restaurant");
$stmt->execute([$uid]);
$restaurants = $stmt->fetchAll(PDO::FETCH_ASSOC);

// traitement formulaire update
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
  <title>Profil - Propriétaire</title>
  <link rel="stylesheet" href="assets/style.css">
</head>
<?php include 'sidebar.php'; ?>
<body> 
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
  <h2>Mon profil - Propriétaire</h2>
       
  <?php if ($msg): ?><div class="success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
  <?php if (!empty($errors)): ?><div class="error"><?php foreach($errors as $e) echo "<div>".htmlspecialchars($e)."</div>"; ?></div><?php endif; ?>

  <form method="post" action="profile_proprio.php" class="form">
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
  <h3>Mes restaurants</h3>
  <a href="statistiques_vendeur.php" class="btn-stat" style="margin-top: 20px;"> Voir les statistiques</a>
  <?php if (count($restaurants) > 0): ?>
      <div class="restaurant-cards">
          <?php foreach($restaurants as $r): ?>
              <div class="restaurant-card">
                  <h4><?= htmlspecialchars($r['nom_restaurant']) ?></h4>
                  <div class="card-buttons">
                      <a href="vendor_edit_restaurant.php?restaurant_id=<?= $r['restaurant_id'] ?>">✏️ Modifier</a>
                      <a href="menu.php?restaurant_id=<?= $r['restaurant_id'] ?>">👀 Voir</a>
                  </div>
              </div>
          <?php endforeach; ?>
      </div>
  <?php else: ?>
      <p>Tu n'as encore créé aucun restaurant.</p>
  <?php endif; ?>

  <a href="vendor_add_restaurant.php" class="btn" style="margin-top: 25px;">+ Ajouter un restaurant</a>
  <hr>
  <h4>Supprimer le compte</h4>
  <form method="post" action="delete_account.php" onsubmit="return confirm('Tu es sûr ? Cette action est IRRÉVERSIBLE.')">
    <button class="btn-del" type="submit">Supprimer mon compte</button>
  </form>

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
  max-width: 700px;
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
.container h3 {
  color: rgba(0, 0, 0, 0.75)
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

.form input:focus {
  background: rgba(255, 255, 255, 0.35);
  transform: scale(1.02);
}
hr {
  margin-top: 20px;
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
/*styles pour les cartes restos dans le profil*/
.restaurant-cards {
  display: flex;
  flex-wrap: wrap;
  gap: 20px;
  justify-content: center;
  margin-top: 20px;
}

.restaurant-card {
  background: rgba(255, 255, 255, 0.2);
  color: #fff;
  padding: 25px 20px;
  border-radius: 20px;
  min-width: 220px;
  max-width: 260px;
  text-align: center;
  backdrop-filter: blur(15px);
  box-shadow: 0 10px 25px rgba(0,0,0,0.3);
  transition: transform 0.25s, box-shadow 0.25s;
}

.restaurant-card:hover {
  transform: translateY(-5px);
  box-shadow: 0 15px 35px rgba(0,0,0,0.4);
}

.card-buttons a {
  display: inline-block;
  margin: 8px 5px 0 5px;
  padding: 6px 12px;
  border-radius: 12px;
  background: var(--accent);
  color: #fff;
  text-decoration: none;
  font-weight: 600;
  font-size: 0.9rem;
  transition: background 0.3s, transform 0.2s;
}

.card-buttons a:hover {
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
  transform: scale(1.05);
  box-shadow: 0 5px 20px rgba(255, 80, 80, 0.4);
}

/* btn stats */
.btn-stat-color {
  background: rgba(236, 198, 95, 0.67); 
  color: #fff;
}

.btn-stat {
  display: block;      
  margin: 0 auto;     
  text-align: center;  
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
  display: block;
  max-width: fit-content; 
}

.btn-stat:hover {
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 12px 40px rgba(0,0,0,0.35);
    background: rgba(249, 158, 72, 0.55);
}

.btn-stat:active {
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

</style>

</main>
</body>
</html>
