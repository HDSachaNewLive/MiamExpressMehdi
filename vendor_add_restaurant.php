<?php
//vendor_add_restaurant.php
session_start();
require_once "db/config.php";
if (!isset($_SESSION["user_id"]) || ($_SESSION["type_compte"] ?? "") !== "proprietaire") { 
    header("Location: login.php"); exit; 
}
$owner_id = (int)$_SESSION["user_id"];
$msg = "";

// ID du propriétaire du site (super-admin) qui recevra la notif de vérification
$siteOwnerId = 1;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nom = trim($_POST["nom_restaurant"] ?? "");
    $adresse = trim($_POST["adresse"] ?? "");
    $latitude = $_POST["latitude"] !== "" ? (float)$_POST["latitude"] : null;
    $longitude = $_POST["longitude"] !== "" ? (float)$_POST["longitude"] : null;
    $categorie = trim($_POST["categorie"] ?? "");
    $desc = trim($_POST["description_resto"] ?? "");
    $plats = $_POST["plats"] ?? [];

    if ($nom === "") {
        $msg = "Nom du restaurant requis.";
    } else {
        // Insert restaurant
        $ins = $conn->prepare("INSERT INTO restaurants (proprietaire_id, nom_restaurant, adresse, latitude, longitude, categorie, description_resto) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $ins->execute([$owner_id, $nom, $adresse, $latitude, $longitude, $categorie, $desc]);
        $resto_id = $conn->lastInsertId();

        // Insert plats avec type_plat + image facultative
        $upload_dir = 'uploads/plats/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
        $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

        foreach ($plats as $idx => $p) {
            $p_nom = trim($p["nom"] ?? "");
            $p_prix = (float)($p["prix"] ?? 0);
            $p_desc = trim($p["description"] ?? "");
            $p_type = in_array($p["type"] ?? "", ["entree", "plat", "accompagnement", "boisson", "dessert", "sauce"])
                      ? $p["type"] : "plat";

            if ($p_nom !== "" && $p_prix > 0) {
                // Gestion image — nom de champ plat : "plat_image_0", "plat_image_1", etc.
                // PHP ne supporte pas les $_FILES multidimensionnels (plats[0][image]),
                // on utilise donc des noms plats séparés.
                $image_path = null;
                $file_key   = "plat_image_" . $idx;

                if (isset($_FILES[$file_key]) && $_FILES[$file_key]["error"] === UPLOAD_ERR_OK) {
                    $file_tmp  = $_FILES[$file_key]["tmp_name"];
                    $file_name = $_FILES[$file_key]["name"];
                    $file_size = $_FILES[$file_key]["size"];
                    $file_ext  = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

                    if (in_array($file_ext, $allowed_ext) && $file_size <= 5242880) {
                        $new_filename = 'plat_new_' . $resto_id . '_' . $idx . '_' . uniqid() . '.' . $file_ext;
                        $destination  = $upload_dir . $new_filename;
                        if (move_uploaded_file($file_tmp, $destination)) {
                            $image_path = $destination;
                        }
                    }
                }

                $stmt = $conn->prepare("INSERT INTO plats (restaurant_id, nom_plat, description_plat, type_plat, prix, image_path) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$resto_id, $p_nom, $p_desc, $p_type, $p_prix, $image_path]);
            }
        }

        // Marquer comme non vérifié
        try {
            $colStmt = $conn->prepare("
                SELECT COUNT(*) AS cnt
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'restaurants'
                  AND COLUMN_NAME = 'verified'
            ");
            $colStmt->execute();
            $colRes = $colStmt->fetch(PDO::FETCH_ASSOC);
            if ($colRes && (int)$colRes["cnt"] > 0) {
                $u = $conn->prepare("UPDATE restaurants SET verified = 0 WHERE restaurant_id = ?");
                $u->execute([$resto_id]);
            }
        } catch (Exception $e) {
            // ne rien faire si erreur
        }

        // Créer notification pour super-admin pour user user_id = 1
        try {
            $message = "Nouveau restaurant à vérifier : " . $nom;
            $notifStmt = $conn->prepare("INSERT INTO notifications (user_id, type, restaurant_id, avis_id, message) VALUES (?, 'comment', ?, NULL, ?)");
            $notifStmt->execute([1, $resto_id, $message]);
        } catch (Exception $e) {
            // ne pas bloquer
        }

        $msg = "Restaurant et plats ajoutés ✅ (en attente de validation par l'admin)";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <link rel="icon" type="image/x-icon" href="FoodHubLogo.ico">
  <meta charset="UTF-8">
  <title>Ajouter un restaurant</title>
  <link rel="stylesheet" href="assets/style.css">
  <?php include 'sidebar.php'; ?>
</head>
<body>
  <audio id="player" autoplay loop> <source src="https://raw.githubusercontent.com/HDSachaNewLive/foodhub-assets/main/Mii Editor - Mii Maker (Wii U) OST.mp3" type="audio/mpeg"> </audio>
  <?php include "slider_son.php"; ?>
  <style>
    #volume-slider {
    background: linear-gradient(135deg, #54dc5de1, #7ff687ff); }
    #volume-button {
    background: linear-gradient(135deg, #40db5fff, #93f2aaff);
    }
  </style>
<main class="container">
<h2>Ajouter un restaurant</h2>
<?php if ($msg) echo "<div class=\"success\">".htmlspecialchars($msg)."</div>"; ?>

<form method="post" class="form" id="restaurant-form" enctype="multipart/form-data">
    <input name="nom_restaurant" placeholder="Nom du restaurant" maxlength="55" required>
    <input name="adresse" placeholder="Adresse" data-address-autocomplete data-lat="[name=latitude]" data-lng="[name=longitude]">
    <input name="latitude" placeholder="Latitude">
    <input name="longitude" placeholder="Longitude">
    <input name="categorie" placeholder="Catégorie (ex: Italien)">
    <textarea name="description_resto" placeholder="Décrivez votre restaurant"></textarea>

    <hr>
    <h3>Plats</h3>
    <div id="plats-container"></div>
    <button type="button" class="btn" id="add-plat">+ Ajouter un plat</button><br><br>
     <hr>
     <br>
    <button type="submit" class="btn-add">Ajouter le restaurant</button>
</form>

<p><a style="margin-top: 10px; margin-bottom: 0px;" href="<?= isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'home.php' ?>">← Retour</a></p>
</main>

<script>
let platIndex = 0;
document.getElementById("add-plat").addEventListener("click", function(){
    const container = document.getElementById("plats-container");
    const div = document.createElement("div");
    div.className = "plat-block";
    div.dataset.idx = platIndex;
    div.innerHTML = `
        <input name="plats[${platIndex}][nom]" placeholder="Nom du plat" required>
        <input name="plats[${platIndex}][prix]" type="number" step="0.01" placeholder="Prix (€)" required>
        <textarea name="plats[${platIndex}][description]" placeholder="Description"></textarea>
        <select name="plats[${platIndex}][type]" required>
            <option value="">-- Type de plat --</option>
            <option value="entree">🥗 Entrée</option>
            <option value="plat" selected>🍽️ Plat</option>
            <option value="accompagnement">🍚 Accompagnement</option>
            <option value="boisson">🥤 Boisson</option>
            <option value="dessert">🍰 Dessert</option>
            <option value="sauce">🧂 Sauce</option>
        </select>
        <label class="label-img-plat">📷 Image du plat <span class="label-img-hint">(facultatif, max 5 Mo — jpg, png, webp)</span></label>
        <input type="file" name="plat_image_${platIndex}" accept="image/*" class="file-input-plat">
        <div class="plat-img-preview" style="display:none;">
            <img src="" alt="Aperçu" class="plat-preview-img">
            <button type="button" class="btn-img-remove">✕ Retirer l'image</button>
        </div>
        <button type="button" class="btn btn-red btn-del-plat">🗑️ Supprimer ce plat</button>
    `;
    container.appendChild(div);

    // auto-resize textarea
    const ta = div.querySelector("textarea");
    if (ta) {
        ta.style.height = 'auto';
        ta.addEventListener('input', function(){ this.style.height = 'auto'; this.style.height = this.scrollHeight + 'px'; });
    }

    // preview image
    const fileInput = div.querySelector('input[type="file"]');
    const previewBox = div.querySelector('.plat-img-preview');
    const previewImg = div.querySelector('.plat-preview-img');
    const removeBtn  = div.querySelector('.btn-img-remove');

    fileInput.addEventListener('change', function() {
        const file = this.files[0];
        if (!file) return;
        const allowed = ['image/jpeg','image/jpg','image/png','image/gif','image/webp'];
        if (!allowed.includes(file.type) || file.size > 5242880) {
            alert('Image invalide ou trop volumineuse (max 5 Mo).');
            this.value = '';
            previewBox.style.display = 'none';
            return;
        }
        const reader = new FileReader();
        reader.onload = e => {
            previewImg.src = e.target.result;
            previewBox.style.display = 'flex';
        };
        reader.readAsDataURL(file);
    });

    removeBtn.addEventListener('click', function() {
        fileInput.value = '';
        previewImg.src = '';
        previewBox.style.display = 'none';
    });

    div.querySelector(".btn-del-plat").addEventListener("click", function(){
        div.remove();
    });

    platIndex++;
});
</script>

<style>
.container {
  max-width: 800px;
  margin: 80px auto;
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
  padding-bottom: 10px;
}

.container h1 {
  color: #f37163;
}
.container h4 {
  color:rgba(0, 0, 0, 0.75);
} 
.container h3 {
  color : rgba(32, 32, 32, 0.75);
}
.container p {
  color: #000000cb;
}

.form input, .form select {
  width: 90%;
  margin: 10px 0;
  padding: 12px;
  border-radius: 10px;
  border: none;
  background: rgba(255, 255, 255, 0.25);
  color: #000000;
  font-size: 1rem;
  outline: none;
  backdrop-filter: blur(15px);
  background: rgba(255, 255, 255, 0.15);
  box-shadow: 0 8px 30px rgba(0, 0, 0, 0.25);
  transition: background 0.3s ease, transform 0.2s;
  font-family: 'HSR';
}

.form input:focus, .form select:focus {
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

.btn-add {
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

.btn-add:hover {
  background: rgba(255, 100, 100, 0.75);
  box-shadow: 0 8px 35px rgba(255, 80, 80, 0.5);
  transform: translateY(-3px) scale(1.03);
}

.success {
  background: rgba(0, 255, 127, 0.25);
  padding: 10px;
  border-radius: 10px;
  margin-bottom: 15px;
}

textarea {
  resize: none;
  overflow-y: hidden;
  min-height: 80px;
  width: 90%;
  padding: 12px;
  border-radius: 10px;
  border: none;
  background: rgba(255, 255, 255, 0.15);
  color: #000;
  font-family: 'HSR';
  font-size: 1rem;
  outline: none;
  box-shadow: 0 8px 30px rgba(0, 0, 0, 0.25);
  transition: background 0.3s ease, transform 0.2s;
}

textarea:focus {
  transform: scale(1.03);
}

@keyframes fadeIn {
  from { opacity: 0; transform: translateY(10px); }
  to { opacity: 1; transform: translateY(0); }
}

/* Bloc plat */
.plat-block {
  background: rgba(255,255,255,0.1);
  border: 1px solid rgba(255,255,255,0.2);
  border-radius: 14px;
  padding: 16px 20px;
  margin-bottom: 18px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
}

/* Label image */
.label-img-plat {
  width: 90%;
  font-weight: 600;
  font-size: 0.9rem;
  color: rgba(0,0,0,0.7);
  font-family: 'HSR';
  text-align: left;
  margin-top: 4px;
}

.label-img-hint {
  color: #888;
  font-size: 0.8rem;
  font-weight: 400;
}

/* Input file */
.file-input-plat {
  width: 90%;
  padding: 8px 12px;
  background: rgba(255,255,255,0.2);
  border-radius: 10px;
  border: 1px solid rgba(255,255,255,0.2);
  cursor: pointer;
  color: #333;
  font-size: 0.9rem;
  font-family: 'HSR';
  box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

/* Zone preview */
.plat-img-preview {
  width: 90%;
  align-items: center;
  gap: 1rem;
  padding: 10px;
  background: rgba(255,255,255,0.15);
  border-radius: 10px;
  flex-wrap: wrap;
  justify-content: center;
}

/* Image preview */
.plat-preview-img {
  max-width: 140px;
  max-height: 100px;
  object-fit: cover;
  border-radius: 0.6rem;
  border: 2px solid rgba(255,107,107,0.4);
  box-shadow: 0 4px 12px rgba(0,0,0,0.2);
}

/* Bouton retirer image */
.btn-img-remove {
  padding: 6px 14px;
  background: rgba(255,77,77,0.7);
  color: white;
  border: none;
  border-radius: 8px;
  font-family: 'HSR', sans-serif;
  font-size: 0.85rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
}

.btn-img-remove:hover {
  background: #ff4d4d;
  transform: translateY(-1px);
}

/* Bouton supprimer le plat */
.btn-del-plat {
  margin-top: 6px;
  background: rgba(255,80,80,0.25) !important;
  color: #ff4d4d !important;
  width: auto !important;
  font-size: 0.9rem !important;
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
})
</script>

<script>
(function(){
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
})();
</script>
<script src="address-autocomplete.js"></script>
</body>
</html>