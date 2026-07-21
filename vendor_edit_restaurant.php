<?php
// vendor_edit_restaurant.php
session_start();
require_once 'db/config.php';
include 'vanta_freeze.php';
require_once 'csrf_helper.php';
require_once 'upload_helper.php';
require_once 'detection_NSFW.php';
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
    abort_404('restaurant');
}

// vérifier appartenance
$check = $conn->prepare("SELECT * FROM restaurants WHERE restaurant_id = ? AND proprietaire_id = ?");
$check->execute([$rid, $owner_id]);
$resto = $check->fetch();
if (!$resto) abort_404('restaurant');

// récupérer plats
$stmt = $conn->prepare("SELECT * FROM plats WHERE restaurant_id=?");
$stmt->execute([$rid]);
$plats = $stmt->fetchAll();

// Validation CSRF pour toutes les requêtes POST (forms doivent inclure fh_csrf_field())
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (empty($_POST['csrf_token']) || !fh_verify_csrf($_POST['csrf_token'])) {
    $_SESSION['msg'] = 'Jeton CSRF invalide.';
    header('Location: vendor_edit_restaurant.php?restaurant_id=' . $rid);
    exit;
  }
}

//GESTION POST

// mise à jour restaurant
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_restaurant'])) {
    $nom = trim($_POST['nom_restaurant'] ?? '');
    $adresse = trim($_POST['adresse'] ?? '');
    $latitude = isset($_POST['latitude']) && $_POST['latitude'] !== '' ? (float)$_POST['latitude'] : null;
    $longitude = isset($_POST['longitude']) && $_POST['longitude'] !== '' ? (float)$_POST['longitude'] : null;
    $categorie = trim($_POST['categorie'] ?? '');
    $desc = trim($_POST['description_resto'] ?? '');
    $ouvert = isset($_POST['ouvert']) ? 1 : 0;

    // re-vérification du contenu à chaque modif du resto (empêche de valider un nom propre puis de le remplacer par un nom sensible) (merci à etowaru pour avoir trouvé l'oubli)
    $plats_pour_check = [];
    foreach ($plats as $p_check) {
        $plats_pour_check[] = ['nom_plat' => $p_check['nom_plat'], 'description_plat' => $p_check['description_plat']];
    }
    $precheck = fh_verify_restaurant($nom, $desc, $adresse, $categorie, $plats_pour_check);

    if ($precheck['score'] >= 20) {
        $msg = "Modification refusée : le contenu ne respecte pas les règles de FoodHub. Vérifiez le nom, la description, les plats ou l'adresse.";
    } else {
        $upd = $conn->prepare("UPDATE restaurants SET nom_restaurant=?, adresse=?, latitude=?, longitude=?, categorie=?, description_resto=?, ouvert=? WHERE restaurant_id=?");
        $upd->execute([$nom, $adresse, $latitude, $longitude, $categorie, $desc, $ouvert, $rid]);

        if ($precheck['score'] >= 10) {
            // si contenu limite on repasse le restaurant en attente de vérification admin
            $conn->prepare("UPDATE restaurants SET verified = 0 WHERE restaurant_id = ?")->execute([$rid]);
            $notifStmt = $conn->prepare("INSERT INTO notifications (user_id, type, restaurant_id, avis_id, message) VALUES (?, 'comment', ?, NULL, ?)");
            $notifStmt->execute([1, $rid, "Restaurant modifié et signalé pour vérification : " . $nom . " ⚠️ [À vérifier — contenu signalé]"]);
            $msg = "Restaurant mis à jour. ⚠️ Contenu signalé : le restaurant repasse en attente de validation par l'admin.";
        } else {
            $msg = "Restaurant mis à jour.";
        }
    }

    $check->execute([$rid, $owner_id]);
    $resto = $check->fetch();
}

// supprimer plat
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_plat_id'])) {
    $pid = (int)$_POST['delete_plat_id'];
    // Supprimer l'image si elle existe
    $imgStmt = $conn->prepare("SELECT image_path FROM plats WHERE plat_id=? AND restaurant_id=?");
    $imgStmt->execute([$pid, $rid]);
    $platRow = $imgStmt->fetch();
    if ($platRow && $platRow['image_path'] && file_exists($platRow['image_path'])) {
        unlink($platRow['image_path']);
    }
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

    // vérification du contenu du plat avant UPDATE en BDD
    $precheck_plat = fh_check_content($nom . ' ' . $desc);

    if ($precheck_plat['score'] >= 20) {
        $msg = "Plat refusé : le nom ou la description ne respecte pas les règles de FoodHub.";
    } else {
        // Gestion image du plat (sécurisé)
        $image_path = null;
        $upload_dir = 'uploads/plats/';
        if (isset($_FILES['plat_image'])) {
          if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
          $uploadRes = fh_handle_uploaded_field('plat_image', $upload_dir, 5242880);
          if ($uploadRes['success'] && !empty($uploadRes['results'][0]['success'])) {
            $image_path = $upload_dir . $uploadRes['results'][0]['filename'];
          } elseif (!empty($uploadRes['results'][0]['error'])) {
            $msg = $uploadRes['results'][0]['error'] ?? $uploadRes['error'] ?? "Image invalide ou trop volumineuse (max 5MB).";
          }
        }

        $stmt = $conn->prepare("INSERT INTO plats (restaurant_id, nom_plat, description_plat, type_plat, prix, image_path) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$rid, $nom, $desc, $type, $prix, $image_path]);

        if ($precheck_plat['score'] >= 10) {
            $conn->prepare("UPDATE restaurants SET verified = 0 WHERE restaurant_id = ?")->execute([$rid]);
            $notifStmt = $conn->prepare("INSERT INTO notifications (user_id, type, restaurant_id, avis_id, message) VALUES (?, 'comment', ?, NULL, ?)");
            $notifStmt->execute([1, $rid, "Nouveau plat signalé pour vérification sur : " . $resto['nom_restaurant'] . " ⚠️ [À vérifier — contenu signalé]"]);
            if (!$msg) $msg = "Plat ajouté avec succès. ⚠️ Contenu signalé, le restaurant repasse en attente de validation.";
        } else {
            if (!$msg) $msg = "Plat ajouté avec succès.";
        }

        $stmt = $conn->prepare("SELECT * FROM plats WHERE restaurant_id=?");
        $stmt->execute([$rid]);
        $plats = $stmt->fetchAll();
    }
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

// ajouter/remplacer image d'un plat existant
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_plat_image'])) {
    $pid = (int)$_POST['plat_id'];

    //vérif si un fichier a été sélectionnér
    if (!isset($_FILES['plat_image_existing']) || $_FILES['plat_image_existing']['error'] === UPLOAD_ERR_NO_FILE) {
        $msg = "⚠️ Aucune image sélectionnée.";
    } else {
        //récupérer lancienne image
        $imgStmt = $conn->prepare("SELECT image_path FROM plats WHERE plat_id=? AND restaurant_id=?");
        $imgStmt->execute([$pid, $rid]);
        $platRow = $imgStmt->fetch();

        if (isset($_FILES['plat_image_existing']) && $_FILES['plat_image_existing']['error'] !== UPLOAD_ERR_NO_FILE) {
          $upload_dir = 'uploads/plats/';
          if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
          $uploadRes = fh_handle_uploaded_field('plat_image_existing', $upload_dir, 5242880);
          if ($uploadRes['success'] && !empty($uploadRes['results'][0]['success'])) {
            $destination = $upload_dir . $uploadRes['results'][0]['filename'];
            if ($platRow && $platRow['image_path'] && file_exists($platRow['image_path'])) {
              unlink($platRow['image_path']);
            }
            $stmt = $conn->prepare("UPDATE plats SET image_path=? WHERE plat_id=? AND restaurant_id=?");
            $stmt->execute([$destination, $pid, $rid]);
            $msg = "Image du plat mise à jour.";
          } else {
            $msg = $uploadRes['results'][0]['error'] ?? $uploadRes['error'] ?? "Image invalide ou trop volumineuse (max 5MB).";
          }
        }
    }

    $stmt = $conn->prepare("SELECT * FROM plats WHERE restaurant_id=?");
    $stmt->execute([$rid]);
    $plats = $stmt->fetchAll();
}

// supprimer image d'un plat existant
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_plat_image'])) {
    $pid = (int)$_POST['plat_id'];
    $imgStmt = $conn->prepare("SELECT image_path FROM plats WHERE plat_id=? AND restaurant_id=?");
    $imgStmt->execute([$pid, $rid]);
    $platRow = $imgStmt->fetch();
    if ($platRow && $platRow['image_path'] && file_exists($platRow['image_path'])) {
        unlink($platRow['image_path']);
    }
    $stmt = $conn->prepare("UPDATE plats SET image_path=NULL WHERE plat_id=? AND restaurant_id=?");
    $stmt->execute([$pid, $rid]);
    $msg = "Image supprimée.";

    $stmt = $conn->prepare("SELECT * FROM plats WHERE restaurant_id=?");
    $stmt->execute([$rid]);
    $plats = $stmt->fetchAll();
}

// supprimer restaurant
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_restaurant'])) {
    // Supprimer les images des plats
    $imgStmt = $conn->prepare("SELECT image_path FROM plats WHERE restaurant_id=?");
    $imgStmt->execute([$rid]);
    foreach ($imgStmt->fetchAll() as $p) {
        if ($p['image_path'] && file_exists($p['image_path'])) unlink($p['image_path']);
    }
    $conn->prepare("DELETE FROM plats WHERE restaurant_id=?")->execute([$rid]);
    $conn->prepare("DELETE FROM restaurants WHERE restaurant_id=? AND proprietaire_id=?")->execute([$rid, $owner_id]);
    header("Location: restaurants.php");
    exit;
}
?>

<!doctype html>
<html lang="fr">
<head>
  <link rel="icon" type="image/x-icon" href="FoodHubLogo.ico">
  <meta charset="utf-8">
  <title>Modifier restaurant - <?= htmlspecialchars($resto['nom_restaurant']) ?></title>
  <link rel="stylesheet" href="assets/style.css">
</head>

<body>
  <audio id="player" autoplay loop> <source src="https://raw.githubusercontent.com/HDSachaNewLive/foodhub-assets/main/Mii Editor - Mii Maker (Wii U) OST.mp3" type="audio/mpeg"> </audio>
  <?php include "slider_son.php"; ?>
  <?php include "vanta_freeze.php" ?>
  <style>
    #volume-slider { background: linear-gradient(135deg, #54dc5de1, #7ff687ff); }
    #volume-button { background: linear-gradient(135deg, #40db5fff, #93f2aaff); }
  </style>

<main class="container">

<h1>Modifier : <?= htmlspecialchars($resto['nom_restaurant']) ?></h1>
<?php if ($msg) echo "<div class='success'>".htmlspecialchars($msg)."</div>"; ?>

<!-- FORMULAIRE RESTAURANT -->
<form method="post" class="form">
  <input type="hidden" name="restaurant_id" value="<?= (int)$resto['restaurant_id'] ?>">
  <input type="hidden" name="update_restaurant" value="1">
  <?= fh_csrf_field() ?>
    <label>Nom du restaurant</label>
    <input name="nom_restaurant" maxlength="55" value="<?= htmlspecialchars($resto['nom_restaurant']) ?>"><br>
    <label>Adresse</label>
    <input name="adresse" value="<?= htmlspecialchars($resto['adresse']) ?>" data-address-autocomplete data-lat="[name=latitude]" data-lng="[name=longitude]"><br>
    <label>Latitude</label>
    <input name="latitude" value="<?= htmlspecialchars($resto['latitude'] ?? '') ?>"><br>
    <label>Longitude</label>
    <input name="longitude" value="<?= htmlspecialchars($resto['longitude'] ?? '') ?>"><br>
    <label>Catégorie</label>
    <input name="categorie" value="<?= htmlspecialchars($resto['categorie']) ?>"><br>
    <label>Description</label>
    <textarea placeholder="Entrez une description..."name="description_resto"><?= htmlspecialchars($resto['description_resto']) ?></textarea>
    <hr>
    <div class="toggle-cuisine-wrapper">
        <label class="toggle-cuisine-label" for="ouvert">
            <div class="toggle-cuisine-info">
                <span class="toggle-cuisine-titre">
                    <?= ($resto['ouvert'] ?? 1) ? '✅ Menu accessible' : '🚫 Menu fermé' ?>
                </span>
                <span class="toggle-cuisine-sous">Activez pour ouvrir, désactivez pour fermer temporairement</span>
            </div>
            <div class="toggle-wrapper">
                <input type="checkbox" name="ouvert" id="ouvert"
                      class="toggle-checkbox"
                      <?= ($resto['ouvert'] ?? 1) ? 'checked' : '' ?>>
                <span class="toggle-slider"></span>
            </div>
        </label>
    </div>
    <button class="btn" name="enregistrer" type="submit">Enregistrer les modifications</button>
</form>

<hr>

<!-- FORMULAIRE AJOUTER PLAT -->
<main class="container-add-plat">
<h3>Ajouter un plat</h3>
<form method="post" class="form form-add-plat" enctype="multipart/form-data">
    <input type="hidden" name="restaurant_id" value="<?= (int)$resto['restaurant_id'] ?>">
                <input type="hidden" name="add_plat" value="1">
                <?= fh_csrf_field() ?>
    <label>Nom du plat</label>
    <input placeholder="Nom du plat (Ex: Pâtes forêstières)" type="text" name="plat_nom" required>
    <label>Description</label>
    <textarea placeholder="Description du plat..." name="plat_desc"></textarea>
    <label>Type de plat</label>
    <select name="plat_type" required>
        <option value="" disabled>-- Sélectionner --</option>
        <option value="entree">🥗 Entrée</option>
        <option value="plat" selected>🍽️ Plat</option>
        <option value="accompagnement">🍚 Accompagnement</option>
        <option value="boisson">🥤 Boisson</option>
        <option value="dessert">🍰 Dessert</option>
        <option value="sauce">🧂 Sauce</option>
    </select>
    <label>Prix (€)</label>
    <input type="number" step="0.01" name="plat_prix" required>
    <label>Image du plat <span style="color:#888;font-size:0.85rem;">(optionnel, max 5MB)</span></label>
    <input type="file" name="plat_image" accept="image/*" class="file-input-plat" id="add-plat-image-input">
    <div id="add-plat-preview" style="display:none; margin: 0.5rem auto; text-align:center;">
        <img id="add-plat-preview-img" src="" alt="Aperçu" class="plat-preview-img">
    </div>
    <button class="btn" type="submit" style="margin-top: 22px;">Ajouter</button>
</form>
</main>

<hr>

<!-- LISTE DES PLATS -->
<h3>Plats existants</h3>
<?php if($plats): ?>
    <?php foreach($plats as $p): ?>
    <div class="resto-card">
        <strong><?= htmlspecialchars($p['nom_plat']) ?></strong> — <?= number_format($p['prix'],2) ?> €
        <p><?= htmlspecialchars($p['description_plat']) ?></p>

        <!-- Image actuelle du plat -->
        <?php if (!empty($p['image_path']) && file_exists($p['image_path'])): ?>
            <div class="plat-current-image">
                <img src="<?= htmlspecialchars($p['image_path']) ?>" alt="<?= htmlspecialchars($p['nom_plat']) ?>" class="plat-preview-img">
                <!-- Supprimer l'image -->
                <form method="post" style="display:inline; margin-top:2px;">
                    <input type="hidden" name="restaurant_id" value="<?= (int)$resto['restaurant_id'] ?>">
                    <input type="hidden" name="plat_id" value="<?= (int)$p['plat_id'] ?>">
                  <?= fh_csrf_field() ?>
                    <button type="submit" name="delete_plat_image" class="btn-img-del" onclick="return confirm('Supprimer l\'image de ce plat ?')">🗑️ Supprimer</button>
                </form>
            </div>
        <?php endif; ?>

        <!-- Changer/ajouter image -->
        <form method="post" class="form-inline-img" enctype="multipart/form-data">
            <input type="hidden" name="restaurant_id" value="<?= (int)$resto['restaurant_id'] ?>">
            <input type="hidden" name="plat_id" value="<?= (int)$p['plat_id'] ?>">
          <?= fh_csrf_field() ?>
            <label class="label-img-small">
                <?= (!empty($p['image_path']) && file_exists($p['image_path'])) ? '🔄 Changer l\'image' : '📷 Ajouter une image' ?>
                <span style="color:#888;font-size:0.8rem;">(optionnel, max 5MB)</span>
            </label>
            <input type="file" name="plat_image_existing" accept="image/*"
                   class="file-input-plat" id="img-input-<?= (int)$p['plat_id'] ?>"
                   onchange="previewExistingImage(this, <?= (int)$p['plat_id'] ?>)">
            <div id="preview-existing-<?= (int)$p['plat_id'] ?>" style="display:none; margin:0.5rem 0; text-align:center;">
                <img id="preview-existing-img-<?= (int)$p['plat_id'] ?>" src="" alt="Aperçu" class="plat-preview-img">
            </div>
            <button type="submit" name="update_plat_image" class="btn btn-img-update">
                <?= (!empty($p['image_path']) && file_exists($p['image_path'])) ? '🔄 Remplacer' : '📷 Enregistrer' ?>
            </button>
        </form>

        <!-- Modifier le type du plat -->
        <form method="post" style="margin:8px 0; display:flex; gap:8px; align-items:center;">
            <input type="hidden" name="restaurant_id" value="<?= (int)$resto['restaurant_id'] ?>">
            <input type="hidden" name="plat_id" value="<?= (int)$p['plat_id'] ?>">
            <input type="hidden" name="update_plat_type" value="1">
          <?= fh_csrf_field() ?>
            <label style="margin:0;">Type :</label>
            <select name="new_type" onchange="this.form.submit()" style="background: transparent; backdrop-filter: blur(17px); width:auto; padding:6px;">
                <option value="entree" <?= $p['type_plat'] === 'entree' ? 'selected' : '' ?>>🥗 Entrée</option>
                <option value="plat" <?= $p['type_plat'] === 'plat' ? 'selected' : '' ?>>🍽️ Plat</option>
                <option value="accompagnement" <?= $p['type_plat'] === 'accompagnement' ? 'selected' : '' ?>>🍚 Accompagnement</option>
                <option value="boisson" <?= $p['type_plat'] === 'boisson' ? 'selected' : '' ?>>🥤 Boisson</option>
                <option value="dessert" <?= $p['type_plat'] === 'dessert' ? 'selected' : '' ?>>🍰 Dessert</option>
                <option value="sauce" <?= $p['type_plat'] === 'sauce' ? 'selected' : '' ?>>🧂 Sauce</option>
            </select>
        </form>

        <form method="post" style="margin-top:8px;" onsubmit="return confirm('Supprimer le plat « <?= addslashes(htmlspecialchars($p['nom_plat'])) ?> » ? Cette action est irréversible.');">
            <input type="hidden" name="delete_plat_id" value="<?= (int)$p['plat_id'] ?>">
            <input type="hidden" name="restaurant_id" value="<?= (int)$resto['restaurant_id'] ?>">
          <?= fh_csrf_field() ?>
            <button class="btn-alt" type="submit">Supprimer le plat</button>
        </form>
    </div>
    <?php endforeach; ?>
<?php else: ?>
    <p>Aucun plat pour le moment.</p>
<?php endif; ?>

<!-- BOUTON SUPPRIMER LE RESTAURANT -->
<form method="post" onsubmit="return confirm('Tu es sûr de vouloir supprimer ce restaurant ? Cette action est irréversible.');">
  <input type="hidden" name="delete_restaurant" value="1">
  <?= fh_csrf_field() ?>
  <button class="btn-alt" type="submit" style="background:#ff4d4d;color:white;width:45%;margin-top:10px; left: 425px;">
    🗑️ Supprimer le restaurant
  </button>
</form>

<button class="back-link"><a href="restaurants.php">← Retour</a></button>
</main>

<script>
// Preview image pour le formulaire "ajouter un plat"
document.getElementById('add-plat-image-input')?.addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = function(ev) {
        document.getElementById('add-plat-preview-img').src = ev.target.result;
        document.getElementById('add-plat-preview').style.display = 'block';
    };
    reader.readAsDataURL(file);
});

// Preview image pour les plats existants
function previewExistingImage(input, platId) {
    const file = input.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = function(ev) {
        document.getElementById('preview-existing-img-' + platId).src = ev.target.result;
        document.getElementById('preview-existing-' + platId).style.display = 'block';
    };
    reader.readAsDataURL(file);
}
</script>

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
  padding-bottom: 12px;
}

.back-link {
  background: #ff6b6b5f;
  font-size: 0.9rem;
  border-radius: 10px;
  transition: 0.4s ease all;
  margin-bottom: 14px;
}

.back-link:hover {
  color: #db5b5bee;
  background: #ff6b6bb4;
  transform: translateX(3px);
}

h1 { text-align: center; }
h2, h3 {
  text-align: center;
  color: rgba(0, 0, 0, 0.75);
  margin-bottom: 25px;
}

.form {
  display: flex;
  flex-direction: column;
  gap: 6px;
  margin-bottom: 20px;
  align-self: center;
}
.form input {
  gap: -2px;
  margin-bottom: -5px;
}

.form-add-plat {
  gap: 0;
}

.form-add-plat label {
  margin-top: 20px;
  margin-bottom: 0;
}

.form-add-plat label:first-of-type {
  margin-top: 0;
}

.form-add-plat input[type="text"],
.form-add-plat input[type="number"],
.form-add-plat textarea,
.form-add-plat select,
.form-add-plat input[type="file"] {
  margin-bottom: 0;
}

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
  border: 0px solid rgba(255, 255, 255, 0.2);
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

.form textarea {
  min-height: 100px;
}
.form input:focus,
.form select:focus,
.form textarea:focus {
  background: rgba(255, 255, 255, 0.25);
  border-color: var(--accent);
  transform: scale(1.01);
}

/* Input file style */
.file-input-plat {
  width: 90% !important;
  padding: 10px 12px !important;
  background: rgba(255, 255, 255, 0.2) !important;
  border-radius: 10px !important;
  cursor: pointer;
  color: #333 !important;
  font-size: 0.9rem !important;
}

/* Preview image */
.plat-preview-img {
  max-width: 160px;
  max-height: 110px;
  object-fit: cover;
  border-radius: 0.6rem;
  border: 2px solid rgba(255, 107, 107, 0.4);
  box-shadow: 0 4px 12px rgba(0,0,0,0.2);
}

/* Image actuelle dans les cartes plats */
.plat-current-image {
  display: flex;
  align-items: center;
  gap: 1rem;
  margin: 0.8rem 0;
  padding: 0.8rem;
  background: rgba(255,255,255,0.1);
  border-radius: 0.8rem;
  flex-wrap: wrap;
}

/* Formulaire inline pour l'image des plats existants */
.form-inline-img {
  margin: 0.5rem 0;
  padding: 0.8rem;
  background: rgba(255,255,255,0.08);
  border-radius: 0.8rem;
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.label-img-small {
  font-weight: 600;
  font-size: 0.9rem;
  color: rgba(0,0,0,0.7);
  font-family: 'HSR';
}

.btn-img-update {
  width: fit-content !important;
  padding: 8px 17px !important;
  font-size: 0.9rem !important;
  margin: 4px 0 0 0 !important;
}

.btn-img-del {
  padding: 6px 12px;
  background: rgba(255, 77, 77, 0.75);
  color: white;
  border: none;
  border-radius: 8px;
  font-family: 'HSR', sans-serif;
  font-size: 0.85rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
}

.btn-img-del:hover {
  background: #ff4d4d;
  transform: translateY(-1px);
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
  background: rgba(219, 91, 91, 0.51);
  backdrop-filter: blur(13px);
}

.btn-alt {
  background: rgba(255, 217, 217, 0.15);
  color: #eda4a4ff;
}

.btn-alt:hover {
  background: rgba(239, 185, 185, 0.58);
}

.btn[name="enregistrer"]:hover {
  background: rgba(219, 91, 91, 0.51);
  backdrop-filter: blur(13px);
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
  transition: all 0.3s ease, background 0.3s ease;
}

.resto-card:hover {
  transform: translateY(-3px);
  background: rgba(255, 255, 255, 0.2);
}

.resto-card p {
  text-overflow: ellipsis;
  margin-top: 5px;
  color: rgba(87, 87, 87, 0.85);
}

a {
  color: var(--accent);
  text-decoration: none;
  transition: color 0.3s ease;
}

a:hover { color: var(--accent-dark); }

@keyframes fadeIn {
  from { opacity: 0; transform: translateY(10px); }
  to { opacity: 1; transform: translateY(0); }
}

/* Toggle cuisine fermée */
.toggle-cuisine-wrapper {
    margin: 1rem auto;
    width: 100%;
    margin-top: 0px;
}
.toggle-cuisine-label {
    box-shadow: 0 4px 9px rgba(0,0,0,0.3);
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
    cursor: pointer;
    padding: 1rem 1.2rem;
    background: rgba(255,255,255,0.15);
    border-radius: 1rem;
    border: 1px solid rgba(255,255,255,0.2);
    transition: all 0.3s ease;
}
.toggle-cuisine-label:hover { background: rgba(255,255,255,0.25); }
.toggle-cuisine-info { display: flex; flex-direction: column; gap: 0.3rem; flex: 1; }
.toggle-cuisine-titre { font-weight: 700; color: #222; font-size: 1rem; margin-bottom: 6px; }
.toggle-cuisine-sous { font-size: 0.82rem; color: #555; line-height: 1.4; margin-bottom: 6px; }
.toggle-wrapper { position: relative; width: 54px; height: 28px; flex-shrink: 0; }
.toggle-checkbox { opacity: 0; width: 0; height: 0; position: absolute; }
.toggle-slider {
    position: absolute; inset: 0;
    background: rgba(158,158,158,0.4);
    border-radius: 28px;
    transition: all 0.35s ease;
    cursor: pointer;
}
.toggle-slider::before {
    content: "";
    position: absolute;
    width: 22px; height: 22px;
    left: 3px; top: 3px;
    background: white;
    border-radius: 50%;
    transition: all 0.35s ease;
    box-shadow: 0 2px 6px rgba(0,0,0,0.2);
}
.toggle-checkbox:checked + .toggle-slider {
    background: linear-gradient(135deg, #4CAF50, #45a049);
    box-shadow: 0 4px 14px rgba(76,175,80,0.4);
}
.toggle-checkbox:checked + .toggle-slider::before { transform: translateX(26px); }

.form select{
  -webkit-backdrop-filter: blur(16px);
}
option {
  background: transparent;
  backdrop-filter: blur(16px);
}
</style>

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
  color: 0x59c48a,
  shininess: 25,
  waveHeight: 25,
  waveSpeed: 0.9,
  zoom: 1
});

(function(){
  const adjustHeight = el => {
    if (!el) return;
    el.style.height = 'auto'; // taille de base définie auto en s'adaptant à la taille du texte mais je l'ai override avec un min-height: 100px dans le css
    
    if (el.scrollHeight > 120) {
      el.style.height = '120px';
      el.style.overflowY = 'scroll'; 
    } else {
      el.style.height = el.scrollHeight + 'px';
      el.style.overflowY = 'hidden';
    }
  };
  const attachAutoResize = el => {
    if (!el) return; // si il trouve pas d'élément textarea on retourne rien
    if (el.__autoResizeAttached) return;
    el.__autoResizeAttached = true; // j'ai mis ça pour garder en mémoire qu'on est sur d'avoir rattaché l'élément qu'il faut
    adjustHeight(el);
    el.addEventListener('input', () => adjustHeight(el)); // ajust auto à chaque input (comme ça si on fait un saut de ligne par exemple, ça change la taille tt de suite)
  };
  document.querySelectorAll('textarea').forEach(attachAutoResize); // applique l'effet a tous les textarea de la page
})();
</script>
<script src="address-autocomplete.js"></script>
</body>
</html>