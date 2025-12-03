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

        // Insert plats avec type_plat
        foreach ($plats as $p) {
            $p_nom = trim($p["nom"] ?? "");
            $p_prix = (float)($p["prix"] ?? 0);
            $p_desc = trim($p["description"] ?? "");
            $p_type = in_array($p["type"] ?? "", ["entree", "plat", "accompagnement", "boisson", "dessert", "sauce"]) 
                      ? $p["type"] : "plat";
            
            if ($p_nom !== "" && $p_prix > 0) {
                $stmt = $conn->prepare("INSERT INTO plats (restaurant_id, nom_plat, description_plat, type_plat, prix) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$resto_id, $p_nom, $p_desc, $p_type, $p_prix]);
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

        // Créer notification pour super-admin
        try {
            $message = "Nouveau restaurant à vérifier : " . $nom;
            $notifStmt = $conn->prepare("INSERT INTO notifications (user_id, type, restaurant_id, avis_id, message) VALUES (?, 'comment', ?, NULL, ?)");
            $notifStmt->execute([$siteOwnerId, $resto_id, $message]);
        } catch (Exception $e) {
            // ne pas bloquer
        }

        $msg = "Restaurant et plats ajoutés ✅ (en attente de validation)";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Ajouter un restaurant</title>
  <link rel="stylesheet" href="assets/style.css">
  <?php include 'sidebar.php'; ?>
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
<h2>Ajouter un restaurant</h2>
<?php if ($msg) echo "<div class=\"success\">".htmlspecialchars($msg)."</div>"; ?>

<form method="post" class="form" id="restaurant-form">
    <input name="nom_restaurant" placeholder="Nom du restaurant" required>
    <input name="adresse" placeholder="Adresse">
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

<p><a href="home.php">← Retour</a></p>
</main>

<script>
let platIndex = 0;
document.getElementById("add-plat").addEventListener("click", function(){
    const container = document.getElementById("plats-container");
    const div = document.createElement("div");
    div.style.marginBottom = "15px";
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
        <button type="button" class="btn btn-red">Supprimer</button>
    `;
    container.appendChild(div);
    platIndex++;

    div.querySelector("button").addEventListener("click", function(){
        div.remove();
    });
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
</body>
</html>