<?php
require_once "db/config.php";
session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

$user_id = (int)$_SESSION["user_id"];


// Gestion vérification restos (super-admin)

$owner_email = "mehdiguerbas5@gmail.com"; // email du proprio
$is_owner = false;

// récupérer email de l'utilisateur
$uQ = $conn->prepare("SELECT email FROM users WHERE user_id = ?");
$uQ->execute([$user_id]);
$uR = $uQ->fetch(PDO::FETCH_ASSOC);
if ($uR && isset($uR["email"])) $is_owner = ($uR["email"] === $owner_email);

// --- SUPPRIMER UNE NOTIF SI DEMANDÉE ---
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (isset($_POST["delete_notif_id"])) {
        $nid = (int)$_POST["delete_notif_id"];
        $stmt = $conn->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?");
        $stmt->execute([$nid, $user_id]);
        header("Location: notifications.php");
        exit();
    }

    // Vérification des restos par le proprio
    if ($is_owner && isset($_POST["verify_id"], $_POST["action"])) {
        $rid = (int)$_POST["verify_id"];
        $action = $_POST["action"];

        if ($action === "accept") {
            $stmt = $conn->prepare("UPDATE restaurants SET verified = 1 WHERE restaurant_id = ?");
            $stmt->execute([$rid]);
        } elseif ($action === "refuse") {
            $conn->prepare("DELETE FROM plats WHERE restaurant_id = ?")->execute([$rid]);
            $conn->prepare("DELETE FROM restaurants WHERE restaurant_id = ?")->execute([$rid]);
        }
        header("Location: notifications.php"); // redirect après action
        exit();
    }
}

// ----------------------------------
// MARQUER TOUTES LES NOTIFS COMME LUES
// ----------------------------------
$update = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
$update->execute([$user_id]);

// ----------------------------------
// Récupérer notifications
// ----------------------------------
$sql = "
SELECT 
  n.id AS notif_id,
  n.type AS notif_type,
  n.message AS notif_message,
  n.created_at,
  n.is_read,
  n.restaurant_id,
  n.avis_id,
  r.nom_restaurant,
  a.commentaire AS avis_commentaire
FROM notifications n
LEFT JOIN restaurants r ON n.restaurant_id = r.restaurant_id
LEFT JOIN avis a ON n.avis_id = a.avis_id
WHERE n.user_id = ?
ORDER BY n.created_at DESC
";
$stmt = $conn->prepare($sql);
$stmt->execute([$user_id]);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>🔔 Mes notifications - FoodHub</title>
  <link rel="stylesheet" href="assets/style.css">
  <audio autoplay> <source src="assets/confirm.wav" type="audio/mpeg"> </audio>
  <?php include "sidebar.php"; ?>
  <style>
    .notif-card { backdrop-filter: blur(17px); background: rgba(255,255,255,0.15); padding:1.2rem; border-radius:1.2rem; box-shadow:0 4px 10px rgba(0,0,0,0.1); margin-bottom:1.2rem; transition: transform .18s;}
    .notif-card:hover { transform: translateY(-3px); }
    .notif-head { display:flex; justify-content:space-between; align-items:center; gap:12px;}
    .notif-head h3 { margin:0; font-size:1.05rem; color: var(--accent);}
    .notif-message { margin: .6rem 0 0; font-weight:600; color:#333;}
    .notif-comment { margin-top:10px; padding:10px; background:#fff7f4; border-radius:10px; color:#444; font-size:0.95rem; white-space: pre-wrap;}
    .notif-meta { margin-top:8px; font-size:0.85rem; color:#888;}
    .notif-actions { margin-top:12px; display:flex; align-items:center; gap:10px;}
    .btn { background: var(--accent); color: white; border: none; padding: .55rem 0.9rem; border-radius: .7rem; cursor: pointer; text-decoration: none; font-size: .9rem; }
    .btn:hover { background: var(--accent-dark); }
    .btn-delete { background:#ff6666; }
    .btn-delete:hover { background:#e05555; }
    .notif-empty { color:#666; font-style:italic; margin-top:8px; }

    /* === */
/* Notifications classiques */
.notif-section .notif-card {
    backdrop-filter: blur(15px);
    background: rgba(255,255,255,0.15);
    padding: 1.2rem;
    border-radius: 1.2rem;
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    margin-bottom: 1.5rem;
    transition: transform .18s;
}
.notif-section .notif-card:hover {
    transform: translateY(-3px);
}
.notif-section .notif-head h3 {
    color: var(--accent);
}

/* ============================== */
/* Restos à vérifier (super-admin) */
.resto-card-container {
    backdrop-filter: blur(10px);
    background: rgba(255, 235, 205, 0.25); /* beige clair pour différencier */
    padding: 1.5rem;
    border-radius: 1.2rem;
    border-left: 4px solid var(--accent);
    box-shadow: 0 4px 12px rgba(0,0,0,0.12);
    margin-bottom: 1.8rem;
    transition: transform .18s;
}
.resto-card-container:hover {
    transform: translateY(-3px);
}
.resto-card-container .notif-head h3 {
    color: #d17a3f; /* couleur un peu chaude pour la section resto */
}
.resto-card-container ul {
    padding-left: 1.2rem;
    margin-top: 0.5rem;
}
.resto-card-container li {
    margin-bottom: 0.3rem;
}

/* Boutons spécifiques restos */
.resto-card-container .btn {
    background: #f2a654;
    color: white;
    border-radius: .7rem;
    padding: .55rem 0.9rem;
}
.resto-card-container .btn:hover {
    background: #d1863f;
}
.resto-card-container .btn-delete {
    background: #ff8c8c;
}
.resto-card-container .btn-delete:hover {
    background: #e05555;
}

  </style>
</head>

<body>
    <audio id="player" autoplay loop> <source src="assets/20. Notifications.flac" type="audio/flac"> </audio>
    <?php include "slider_son.php"; ?>
    <style>
    #volume-slider {
    background: linear-gradient(135deg, #f55858ff, #f4a8a8ff); }
    #volume-button {
    background: linear-gradient(135deg, #f55858ff, #f7a6a6ff);
    }
  </style>
<main class="container">
<!-- Section notifications "normales" -->
<h2>🔔 Tes notifications</h2>

<?php if(!empty($notifications)): ?>
    <div class="notif-section">
    <?php foreach($notifications as $n): ?>
        <div class="notif-card <?= $n["is_read"] ? "read" : "unread" ?>">
            <div class="notif-head">
                <h3><?= htmlspecialchars($n["nom_restaurant"] ?? "Notification") ?></h3>
            </div>
            <div class="notif-message"><?= htmlspecialchars($n["notif_message"]) ?></div>

            <?php if(!empty($n["avis_commentaire"])): ?>
                <div class="notif-comment"><?= nl2br(htmlspecialchars($n["avis_commentaire"])) ?></div>
            <?php endif; ?>

            <div class="notif-meta">Reçu le <?= date("d/m/Y à H:i", strtotime($n["created_at"])) ?></div>

            <div class="notif-actions">
                <?php if(!empty($n["restaurant_id"])): 
                    $href = "menu.php?restaurant_id=" . (int)$n["restaurant_id"];
                    if(!empty($n["avis_id"])) $href .= "#comment-" . (int)$n["avis_id"];
                ?>
                    <a class="btn" href="<?= $href ?>">👀 Voir</a>
                <?php endif; ?>

                <form method="post" style="margin:0;" onsubmit="return confirm('Supprimer cette notification ?');">
                    <input type="hidden" name="delete_notif_id" value="<?= (int)$n["notif_id"] ?>">
                    <button type="submit" class="btn btn-delete">❌ Supprimer</button>
                </form>
            </div>
        </div>
    <?php endforeach; ?>
    </div>
<?php else: ?>
    <p>Aucune notification pour le moment 🍃</p>
<?php endif; ?>
<p><a href="home.php" class="back-link">⬅ Retour à l’accueil</a></p>
<!-- ============================= -->
</main>
<?php if($is_owner): ?>
<main class="container">
  <!-- Section vérification restos (proprio) -->
<?php if($is_owner): ?>

<h2>🛠 Vérification des restaurants</h2>
<?php
$pendingStmt = $conn->prepare("SELECT * FROM restaurants WHERE verified = 0 ORDER BY restaurant_id DESC");
$pendingStmt->execute();
$pending = $pendingStmt->fetchAll(PDO::FETCH_ASSOC);

if(!empty($pending)):
    foreach($pending as $resto):
        $rid = (int)$resto["restaurant_id"];
        $nom = htmlspecialchars($resto["nom_restaurant"] ?? "");
        $desc = htmlspecialchars($resto["description_resto"] ?? "");
        $addr = htmlspecialchars($resto["adresse"] ?? "");

        echo "<div class='resto-card-container'>"; // nouveau container séparé

        echo "<div class='notif-head'><h3 style='color:var(--accent);'>{$nom}</h3></div>";
        echo "<p><strong>Description :</strong> {$desc}</p>";
        echo "<p><strong>Adresse :</strong> {$addr}</p>";

        $platsStmt = $conn->prepare("SELECT nom_plat, prix FROM plats WHERE restaurant_id = ?");
        $platsStmt->execute([$rid]);
        $plats = $platsStmt->fetchAll(PDO::FETCH_ASSOC);
        if(!empty($plats)){
            echo "<h4>🍽 Plats proposés :</h4><ul>";
            foreach($plats as $p){
                $pnom = htmlspecialchars($p["nom_plat"] ?? "");
                $pprix = htmlspecialchars($p["prix"] ?? "");
                echo "<li>{$pnom} — {$pprix}€</li>";
            }
            echo "</ul>";
        } else {
            echo "<p class='notif-empty'>Aucun plat ajouté pour le moment.</p>";
        }

        echo "<div class='notif-actions'>";
        echo "<form method='post' style='margin:0; display:flex; gap:10px;'>";
        echo "<input type='hidden' name='verify_id' value='{$rid}'>";
        echo "<button class='btn' name='action' value='accept'>✅ Accepter</button>";
        echo "<button class='btn btn-delete' name='action' value='refuse'>❌ Refuser</button>";
        echo "</form>";
        echo "</div>";

        echo "</div>"; // fin resto-card-container
    endforeach;
else:
    echo "<p>Aucun restaurant en attente pour le moment 🍃</p>";
endif;
endif;
endif;
?>


</main>
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
  color: 0xdba1b2,
  shininess: 25,
  waveHeight: 25,
  waveSpeed: 0.9,
  zoom: 0.9
});
</script>
</body>
</html>
