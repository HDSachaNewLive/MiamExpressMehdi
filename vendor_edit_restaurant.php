<?php
// vendor_edit_restaurant.php
session_start();
require_once 'db/config.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['type_compte'] ?? '') !== 'proprietaire') {
    header('Location: login.php');
    exit;
}
$owner_id = (int)$_SESSION['user_id'];
$msg = '';

if (isset($_GET['restaurant_id'])) {
    $rid = (int)$_GET['restaurant_id'];
} elseif (isset($_POST['restaurant_id'])) {
    $rid = (int)$_POST['restaurant_id'];
} else {
    die("Aucun restaurant spécifié.");
}

// vérifier appartenance
$check = $conn->prepare("SELECT * FROM restaurants WHERE restaurant_id = ? AND proprietaire_id = ?");
$check->execute([$rid, $owner_id]);
$resto = $check->fetch();
if (!$resto) die("Accès refusé ou restaurant introuvable.");

// récupérer plats
$stmt = $conn->prepare("SELECT * FROM plats WHERE restaurant_id=?");
$stmt->execute([$rid]);
$plats = $stmt->fetchAll();

// -------------------- GESTION POST --------------------

// mise à jour restaurant
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_restaurant'])) {
    $nom = trim($_POST['nom_restaurant'] ?? '');
    $adresse = trim($_POST['adresse'] ?? '');
    $latitude = isset($_POST['latitude']) && $_POST['latitude'] !== '' ? (float)$_POST['latitude'] : null;
    $longitude = isset($_POST['longitude']) && $_POST['longitude'] !== '' ? (float)$_POST['longitude'] : null;
    $categorie = trim($_POST['categorie'] ?? '');
    $desc = trim($_POST['description_resto'] ?? '');

    $upd = $conn->prepare("UPDATE restaurants SET nom_restaurant=?, adresse=?, latitude=?, longitude=?, categorie=?, description_resto=? WHERE restaurant_id=?");
    $upd->execute([$nom, $adresse, $latitude, $longitude, $categorie, $desc, $rid]);
    $msg = "Restaurant mis à jour.";

    $check->execute([$rid, $owner_id]);
    $resto = $check->fetch();
}

// supprimer plat
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_plat_id'])) {
    $pid = (int)$_POST['delete_plat_id'];
    $stmt = $conn->prepare("DELETE FROM plats WHERE plat_id=? AND restaurant_id=?");
    $stmt->execute([$pid, $rid]);
    $msg = "Plat supprimé avec succès.";

    $stmt = $conn->prepare("SELECT * FROM plats WHERE restaurant_id=?");
    $stmt->execute([$rid]);
    $plats = $stmt->fetchAll();
}

// ajouter plat
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_plat'])) {
    $nom = trim($_POST['plat_nom']);
    $desc = trim($_POST['plat_desc'] ?? '');
    $prix = (float)$_POST['plat_prix'];
    $type = in_array($_POST['plat_type'] ?? "", ["entree", "plat", "accompagnement", "boisson", "dessert", "sauce"]) 
            ? $_POST['plat_type'] : "plat";

    $stmt = $conn->prepare("INSERT INTO plats (restaurant_id, nom_plat, description_plat, type_plat, prix) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$rid, $nom, $desc, $type, $prix]);
    $msg = "Plat ajouté avec succès.";

    $stmt = $conn->prepare("SELECT * FROM plats WHERE restaurant_id=?");
    $stmt->execute([$rid]);
    $plats = $stmt->fetchAll();
}

// modifier type d'un plat
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_plat_type'])) {
    $pid = (int)$_POST['plat_id'];
    $type = in_array($_POST['new_type'] ?? "", ["entree", "plat", "accompagnement", "boisson", "dessert", "sauce"]) 
            ? $_POST['new_type'] : "plat";
    
    $stmt = $conn->prepare("UPDATE plats SET type_plat=? WHERE plat_id=? AND restaurant_id=?");
    $stmt->execute([$type, $pid, $rid]);
    $msg = "Type de plat mis à jour.";

    $stmt = $conn->prepare("SELECT * FROM plats WHERE restaurant_id=?");
    $stmt->execute([$rid]);
    $plats = $stmt->fetchAll();
}

// supprimer restaurant
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_restaurant'])) {
    $conn->prepare("DELETE FROM plats WHERE restaurant_id=?")->execute([$rid]);
    $conn->prepare("DELETE FROM restaurants WHERE restaurant_id=? AND proprietaire_id=?")->execute([$rid, $owner_id]);
    header("Location: restaurants.php");
    exit;
}

?>

<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <title>Modifier restaurant - <?= htmlspecialchars($resto['nom_restaurant']) ?></title>
  <link rel="stylesheet" href="assets/style.css">
  </head>

<body>
  <audio id="player" autoplay loop> <source src="assets\Mii Editor - Mii Maker (Wii U) OST.mp3" type="audio/mpeg"> </audio>
  <?php include "slider_son.php"; ?>
  <style>
    #volume-slider {
    background: linear-gradient(135deg, #54dc5de1, #7ff687ff); }
    #volume-button {
    background: linear-gradient(135deg, #40db5fff, #93f2aaff);
    }
  </style>
<main class="container">

<h1>Modifier : <?= htmlspecialchars($resto['nom_restaurant']) ?></h1>
<?php if ($msg) echo "<div class='success'>".htmlspecialchars($msg)."</div>"; ?>

<!-- FORMULAIRE RESTAURANT -->
<form method="post" class="form">
    <input type="hidden" name="restaurant_id" value="<?= (int)$resto['restaurant_id'] ?>">
    <input type="hidden" name="update_restaurant" value="1">
    <label>Nom du restaurant</label>
    <input name="nom_restaurant" value="<?= htmlspecialchars($resto['nom_restaurant']) ?>"><br>
    <label>Adresse</label>
    <input name="adresse" value="<?= htmlspecialchars($resto['adresse']) ?>"><br>
    <label>Latitude</label>
    <input name="latitude" value="<?= htmlspecialchars($resto['latitude'] ?? '') ?>"><br>
    <label>Longitude</label>
    <input name="longitude" value="<?= htmlspecialchars($resto['longitude'] ?? '') ?>"><br>
    <label>Catégorie</label>
    <input name="categorie" value="<?= htmlspecialchars($resto['categorie']) ?>"><br>
    <label>Description</label>
    <textarea name="description_resto"><?= htmlspecialchars($resto['description_resto']) ?></textarea>
    <button class="btn" type="submit">Enregistrer les modifications</button>
</form>

<hr>

<!-- FORMULAIRE AJOUTER PLAT -->
<main class="container-add-plat">
<h3>Ajouter un plat</h3>
<form method="post" class="form">
    <input type="hidden" name="restaurant_id" value="<?= (int)$resto['restaurant_id'] ?>">
    <input type="hidden" name="add_plat" value="1">
    <label>Nom du plat</label>
    <input type="text" name="plat_nom" required><br>
    <label>Description</label>
    <textarea name="plat_desc"></textarea><br>
    <label>Type de plat</label>
    <select name="plat_type" required>
        <option value="">-- Sélectionner --</option>
        <option value="entree">🥗 Entrée</option>
        <option value="plat" selected>🍽️ Plat</option>
        <option value="accompagnement">🍚 Accompagnement</option>
        <option value="boisson">🥤 Boisson</option>
        <option value="dessert">🍰 Dessert</option>
        <option value="sauce">🧂 Sauce</option>
    </select><br>
    <label>Prix (€)</label>
    <input type="number" step="0.01" name="plat_prix" required>
    <button class="btn" type="submit">Ajouter</button>
</form>
</main>
<hr>

<!-- LISTE DES PLATS -->
<h3>Plats existants</h3>
<?php if($plats): ?>
    <?php foreach($plats as $p): ?>
    <div class="resto-card">
        <strong><?= htmlspecialchars($p['nom_plat']) ?></strong> - <?= number_format($p['prix'],2) ?> €
        <p><?= htmlspecialchars($p['description_plat']) ?></p>
        
        <!-- Modifier le type du plat -->
        <form method="post" style="margin:8px 0; display:flex; gap:8px; align-items:center;">
            <input type="hidden" name="restaurant_id" value="<?= (int)$resto['restaurant_id'] ?>">
            <input type="hidden" name="plat_id" value="<?= (int)$p['plat_id'] ?>">
            <input type="hidden" name="update_plat_type" value="1">
            <label style="margin:0;">Type :</label>
            <select name="new_type" onchange="this.form.submit()" style="width:auto; padding:6px;">
                <option value="entree" <?= $p['type_plat'] === 'entree' ? 'selected' : '' ?>>🥗 Entrée</option>
                <option value="plat" <?= $p['type_plat'] === 'plat' ? 'selected' : '' ?>>🍽️ Plat</option>
                <option value="accompagnement" <?= $p['type_plat'] === 'accompagnement' ? 'selected' : '' ?>>🍚 Accompagnement</option>
                <option value="boisson" <?= $p['type_plat'] === 'boisson' ? 'selected' : '' ?>>🥤 Boisson</option>
                <option value="dessert" <?= $p['type_plat'] === 'dessert' ? 'selected' : '' ?>>🍰 Dessert</option>
                <option value="sauce" <?= $p['type_plat'] === 'sauce' ? 'selected' : '' ?>>🧂 Sauce</option>
            </select>
        </form>
        
        <form method="post" style="margin-top:8px;">
            <input type="hidden" name="delete_plat_id" value="<?= (int)$p['plat_id'] ?>">
            <input type="hidden" name="restaurant_id" value="<?= (int)$resto['restaurant_id'] ?>">
            <button class="btn-alt" type="submit">Supprimer</button>
        </form>
    </div>
    <?php endforeach; ?>
<?php else: ?>
    <p>Aucun plat pour le moment.</p>
<?php endif; ?>

<!-- BOUTON SUPPRIMER LE RESTAURANT -->
<form method="post" onsubmit="return confirm('Tu es sûr de vouloir supprimer ce restaurant ? Cette action est irréversible.');">
  <input type="hidden" name="delete_restaurant" value="1">
  <button class="btn-alt" type="submit" style="background:#ff4d4d;color:white;width:45%;margin-top:10px; left: 425px;">
    🗑️ Supprimer le restaurant
  </button>
</form>

<p><a href="restaurants.php">← Retour</a></p>
</main>

<style>
.container {
  max-width: 850px;
  margin: 100px auto;
  padding: 40px;
  border-radius: 20px;
  backdrop-filter: blur(20px);
  background: rgba(255, 255, 255, 0.08);
  box-shadow: 0 8px 30px rgba(0, 0, 0, 0.4);
  border: 1px solid rgba(255, 255, 255, 0.25);
  text-align: left;
  animation: fadeIn 0.8s ease;
}

h1 {
  text-align: center;
}
h2, h3 {
  text-align: center;
  color: black;
  margin-bottom: 25px;
  color:rgba(0, 0, 0, 0.75);
}

.form {
  display: flex;
  flex-direction: column;
  gap: 6px;
  margin-bottom: 20px;
  align-self: center;
}

.container-add-plat .form br { display: none; }

.form .btn {
  display: block;
  margin-top: 8px;
  margin: 8px auto 0 auto;
  text-align: center;
  width: fit-content;
}

.form label {
  font-weight: bold;
  font-size: 0.95rem;
  color: #1e1e1eff;
  margin-bottom: 4px;
  font-family: 'HSR';
  width: 90%;
  margin-left: auto;
  margin-right: auto;
  display: block;
}

.form input,
.form select,
.form textarea {
  overflow-y: hidden;
  width: 90%;
  padding: 12px;
  border-radius: 12px;
  border: 1px solid rgba(255, 255, 255, 0.2);
  background: rgba(255, 255, 255, 0.15);
  box-shadow: 0 8px 30px rgba(0, 0, 0, 0.25);
  color: #262626ff;
  font-size: 1rem;
  outline: none;
  transition: all 0.3s ease;
  resize: none;
  font-family: 'HSR';
  display: block;
  margin-left: auto;
  margin-right: auto;
}

.form input:focus,
.form select:focus,
.form textarea:focus {
  background: rgba(255, 255, 255, 0.25);
  border-color: var(--accent);
  transform: scale(1.01);
}

.btn,
.btn-alt {
  width: 65%;
  font-family: 'HSR', sans-serif;
  border: none;
  border-radius: 12px;
  padding: 10px 18px;
  cursor: pointer;
  transition: all 0.3s ease;
  font-size: 1rem;
}

.btn {
  background: var(--accent);
  color: white;
}

.btn:hover {
  background: var(--accent-dark);
  transform: translateY(-2px);
}

.btn-alt {
  background: rgba(255, 217, 217, 0.15);
  color: #eda4a4ff;
}

.btn-alt:hover {
  background: rgba(239, 185, 185, 0.58);
}

.success {
  background: rgba(0, 255, 127, 0.15);
  border-left: 4px solid #00ff7f;
  padding: 12px;
  border-radius: 10px;
  margin-bottom: 20px;
  text-align: center;
}

.resto-card {
  background: rgba(255, 255, 255, 0.12);
  border: 1px solid rgba(255, 255, 255, 0.25);
  border-radius: 14px;
  padding: 15px 20px;
  margin-bottom: 20px;
  box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
  transition: transform 0.3s ease, background 0.3s ease;
}

.resto-card:hover {
  transform: translateY(-3px);
  background: rgba(255, 255, 255, 0.2);
}

.resto-card p {
  margin-top: 5px;
  color: rgba(87, 87, 87, 0.85);
}

a {
  color: var(--accent);
  text-decoration: none;
  transition: color 0.3s ease;
}

a:hover {
  color: var(--accent-dark);
}

@keyframes fadeIn {
  from { opacity: 0; transform: translateY(10px); }
  to { opacity: 1; transform: translateY(0); }
}
</style>

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
  color: 0x59c48a,
  shininess: 25,
  waveHeight: 25,
  waveSpeed: 0.9,
  zoom: 1
})
</script>

<script>
function(){
  const adjustHeight = el => {
    if (!el) return;
    el.style.height = 'auto';
    el.style.height = el.scrollHeight + 'px';
  };

  const attachAutoResize = el => {
    if (!el) return;
    if (el.__autoResizeAttached) return;
    el.__autoResizeAttached = true;
    adjustHeight(el);
    el.addEventListener('input', () => adjustHeight(el));
  };

  document.querySelectorAll('textarea').forEach(attachAutoResize);

  const addPlatBtn = document.getElementById('add-plat');
  if (addPlatBtn) {
    addPlatBtn.addEventListener('click', () => {
      setTimeout(() => {
        document.querySelectorAll('#plats-container textarea').forEach(attachAutoResize);
      }, 0);
    });
  }
}();
</script>
</body>
</html>