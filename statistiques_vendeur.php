<?php
session_start();
require_once 'db/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$uid = (int)$_SESSION['user_id'];

// Vérifier si on est propriétaire
$stmt = $conn->prepare("SELECT type_compte FROM users WHERE user_id = ?");
$stmt->execute([$uid]);
$user = $stmt->fetch();
if (!$user || $user['type_compte'] !== 'proprietaire') {
    header('Location: profile.php');
    exit;
}

// Tous les restaurants du proprio (sert au filtre ET à la vérif anti-IDOR)
$stmt = $conn->prepare("SELECT restaurant_id, nom_restaurant FROM restaurants WHERE proprietaire_id = ? ORDER BY nom_restaurant ASC");
$stmt->execute([$uid]);
$mes_restaurants = $stmt->fetchAll(PDO::FETCH_ASSOC);

$tous_les_ids = array_column($mes_restaurants, 'restaurant_id');
if (empty($tous_les_ids)) $tous_les_ids = [0];

/* ── Filtre restaurant (GET, lecture seule) ──
   On ne fait confiance à $_GET['restaurant_id'] que s'il correspond
   à un restaurant appartenant réellement au proprio connecté (anti-IDOR). */
$restaurant_filtre = null;
$restaurant_filtre_nom = null;
if (isset($_GET['restaurant_id']) && $_GET['restaurant_id'] !== '' && $_GET['restaurant_id'] !== 'all') {
    $requested_id = (int)$_GET['restaurant_id'];
    if (in_array($requested_id, $tous_les_ids, true)) {
        $restaurant_filtre = $requested_id;
        foreach ($mes_restaurants as $r) {
            if ((int)$r['restaurant_id'] === $requested_id) {
                $restaurant_filtre_nom = $r['nom_restaurant'];
                break;
            }
        }
    }
    // ID ne correspondant à aucun restaurant du proprio → ignoré silencieusement,
    // on retombe sur la vue "tous les restaurants".
}

// IDs effectivement utilisés dans les requêtes selon le filtre choisi
$restaurant_ids = $restaurant_filtre !== null ? [$restaurant_filtre] : $tous_les_ids;

// Pour chaque requête IN (sécurisé)
$placeholders = implode(',', array_fill(0, count($restaurant_ids), '?'));

/* 2️⃣ CA TOTAL - Corrigé pour prendre en compte les réductions et commandes annulées */
// Calculer le CA pour chaque commande en tenant compte des réductions proportionnelles
$stmt = $conn->prepare("
    SELECT 
        c.commande_id,
        c.montant_total AS total_commande,
        c.montant_reduction,
        c.statut,
        SUM(cp.prix_unitaire * cp.quantite) AS sous_total_proprio
    FROM commandes c
    JOIN commande_plats cp ON c.commande_id = cp.commande_id
    JOIN plats p ON cp.plat_id = p.plat_id
    WHERE p.restaurant_id IN ($placeholders)
      AND c.statut != 'annulee'
    GROUP BY c.commande_id
");
$stmt->execute($restaurant_ids);
$commandes = $stmt->fetchAll(PDO::FETCH_ASSOC);

$ca_total = 0;
foreach ($commandes as $cmd) {
    // Calculer le sous-total total de la commande
    $stmt2 = $conn->prepare("
        SELECT SUM(cp.prix_unitaire * cp.quantite) AS sous_total_commande
        FROM commande_plats cp
        WHERE cp.commande_id = ?
    ");
    $stmt2->execute([$cmd['commande_id']]);
    $sous_total_commande = $stmt2->fetchColumn() ?? 0;
    
    if ($sous_total_commande > 0) {
        // Calculer la proportion de cette commande qui appartient au proprio
        $proportion = $cmd['sous_total_proprio'] / $sous_total_commande;
        
        // Appliquer la réduction proportionnellement
        $reduction_proprio = $cmd['montant_reduction'] * $proportion;
        
        // Ajouter au CA (sous-total du proprio - sa part de réduction)
        $ca_total += ($cmd['sous_total_proprio'] - $reduction_proprio);
    }
}

/* Affichage avec le symbole € */
$ca_total_formatted = number_format($ca_total, 2, ',', ' ') . ' €';

// 3️⃣ MOYENNE CORRECT - Exclure les commandes annulées
$stmt = $conn->prepare("
    SELECT c.commande_id
    FROM commandes c
    JOIN commande_plats cp ON c.commande_id = cp.commande_id
    JOIN plats p ON cp.plat_id = p.plat_id
    WHERE p.restaurant_id IN ($placeholders)
      AND c.statut != 'annulee'
    GROUP BY c.commande_id
");
$stmt->execute($restaurant_ids);
$nb_commandes = $stmt->rowCount();

$avg_cmd = $nb_commandes > 0 ? ($ca_total / $nb_commandes) : 0;

/* 4️⃣ PLAT LE PLUS VENDU (respecte le filtre restaurant) */
$stmt = $conn->prepare("
    SELECT pl.nom_plat, SUM(cp.quantite) AS total_qte
    FROM commande_plats cp
    JOIN plats pl ON cp.plat_id = pl.plat_id
    JOIN restaurants r ON pl.restaurant_id = r.restaurant_id
    JOIN commandes c ON cp.commande_id = c.commande_id
    WHERE r.restaurant_id IN ($placeholders)
      AND c.statut != 'annulee'
    GROUP BY cp.plat_id
    ORDER BY total_qte DESC
    LIMIT 1
");
$stmt->execute($restaurant_ids);
$top_plat = $stmt->fetch();

/* 5️⃣ TOP 5 (respecte le filtre restaurant) */
$stmt = $conn->prepare("
    SELECT pl.nom_plat, SUM(cp.quantite) AS total_qte
    FROM commande_plats cp
    JOIN plats pl ON cp.plat_id = pl.plat_id
    JOIN restaurants r ON pl.restaurant_id = r.restaurant_id
    JOIN commandes c ON cp.commande_id = c.commande_id
    WHERE r.restaurant_id IN ($placeholders)
      AND c.statut != 'annulee'
    GROUP BY cp.plat_id
    ORDER BY total_qte DESC
    LIMIT 5
");
$stmt->execute($restaurant_ids);
$top_plats = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* 6️⃣ COMMANDES PAR JOUR - Exclure les commandes annulées */
$stmt = $conn->prepare("
    SELECT DATE(c.date_commande) AS jour, COUNT(DISTINCT c.commande_id) AS nb_cmd
    FROM commandes c
    JOIN commande_plats cp ON c.commande_id = cp.commande_id
    JOIN plats p ON cp.plat_id = p.plat_id
    WHERE p.restaurant_id IN ($placeholders)
      AND c.date_commande >= DATE_SUB(NOW(), INTERVAL 7 DAY)
      AND c.statut != 'annulee'
    GROUP BY jour
    ORDER BY jour ASC
");
$stmt->execute($restaurant_ids);
$cmds = $stmt->fetchAll(PDO::FETCH_ASSOC);

$jours = array_column($cmds, 'jour');
$nb_cmds = array_column($cmds, 'nb_cmd');
?>
<!doctype html>
<html lang="fr">
<head>
  <link rel="icon" type="image/x-icon" href="FoodHubLogo.ico">
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Propriétaire - Statistiques</title>
  <link rel="stylesheet" href="assets/style.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
:root {
  --accent: #ff6b6b;
  --card-bg: rgba(255,255,255,0.18);
  --text-dark: #222;
}

.container {
  max-width: 900px;
  margin: 80px auto;
  padding: 2rem;
  backdrop-filter: blur(15px);
  background: rgba(255, 255, 255, 0.25);
  border-radius: 1.5rem;
  box-shadow: 0 8px 25px rgba(0,0,0,0.18);
}

.btn {
  display: inline-flex;
  justify-content: center;
  align-items: center;
  padding: 1rem 1.8rem;
  font-size: 1rem;
  font-weight: 600;
  color: #fff;
  background: linear-gradient(135deg, #ff6b6b, #ffc342ff);
  border: none;
  border-radius: 14px;
  cursor: pointer;
  text-decoration: none;
  box-shadow: 0 6px 18px rgba(0,0,0,0.18);
  transition: all 0.35s ease;
  overflow: hidden;
  text-align: center;
  margin-top: 25px;
  margin-bottom: -5px;
}

.btn::after {
  content: "";
  position: absolute;
  top: 0; left: -100%;
  width: 100%;
  height: 100%;
  background: rgba(255,255,255,0.25);
  transition: all 0.4s ease;
  border-radius: 14px;
}

.btn:hover::after { left: 0; }
.btn:hover { 
  transform: translateY(-4px) scale(1.03); 
  box-shadow: 0 12px 25px rgba(0,0,0,0.25); 
  background: linear-gradient(135deg, #ff8c42, #ff6b6b);
}

.section-title {
  color: var(--accent);
  font-size: 1.9rem;
  margin-bottom: 10px;
  margin-top: -5px;
  text-align: center;
}

/* ── Filtre par restaurant ── */
.filtre-restaurant {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.7rem;
  margin-bottom: 25px;
  flex-wrap: wrap;
}

.filtre-restaurant label {
  font-weight: 600;
  color: #333;
  font-size: 0.95rem;
}

.filtre-restaurant select {
  padding: 0.6rem 1rem;
  border-radius: 0.8rem;
  border: 2px solid rgba(255, 107, 107, 0.25);
  background: rgba(255, 255, 255, 0.55);
  font-family: 'HSR', sans-serif;
  font-size: 0.95rem;
  color: #333;
  cursor: pointer;
  transition: all 0.3s ease;
  max-width: 280px;
}

.filtre-restaurant select:focus {
  outline: none;
  border-color: #ff6b6b;
  background: rgba(255, 255, 255, 0.8);
  box-shadow: 0 0 10px rgba(255, 107, 107, 0.3);
}

.stats-grid {
  display: flex;
  flex-wrap: wrap;
  gap: 1rem;
  justify-content: flex-start;
  margin-top: 0.8rem;
}

.stat-card {
  flex: 1 1 calc(50% - 0.5rem);
  min-width: 250px;
  background: rgba(255,255,255,0.18);
  backdrop-filter: blur(14px);
  padding: 1.4rem;
  border-radius: 1.3rem;
  text-align: center;
  box-shadow: 0 6px 15px rgba(0,0,0,0.12);
  transition: transform 0.25s, box-shadow 0.25s;
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: center;
}

.stat-card:hover {
  transform: translateY(-5px) scale(1.03);
  box-shadow: 0 12px 28px rgba(0,0,0,0.2);
}

.stat-card h4 { 
  font-size: 1.3rem; 
  margin-bottom: 0.5rem; 
  color: #ff6b6b; 
  margin-top: 13px; 
}

.stat-card p { 
  font-size: 2.5rem; 
  font-weight: 700; 
  color: #333; 
  text-shadow: 1px 1px 3px rgba(0,0,0,0.2); 
  margin-top: -0px;
}

.stat-card::before {
  content: "";
  position: absolute;
  top: -50%; left: -50%;
  width: 200%; height: 200%;
  background: radial-gradient(circle, rgba(255,255,255,0.2) 0%, transparent 70%);
  transform: rotate(45deg);
  pointer-events: none;
  transition: all 0.5s ease;
}

.stat-card:hover::before { top: -20%; left: -20%; }

.chart-container {
  margin: 28px auto;
  padding: 18px;
  background: rgba(255,255,255,0.18);
  backdrop-filter: blur(10px);
  border-radius: 18px;
  box-shadow: 0 6px 15px rgba(0,0,0,0.12);
  width: 100%;
  max-width: 900px;
  box-sizing: border-box;
  text-align: center;
}

.chart-container h3.section-title {
  margin-bottom: 12px;
  margin-top: 0;
}

.chart-box {
  width: 100%;
  position: relative;
  min-height: 320px;
}

.chart-box canvas {
  position: absolute !important;
  inset: 0;
  width: 100% !important;
  height: 100% !important;
}

@media (max-width: 600px) {
  :root { --chart-min-height: 160px; --chart-max-width: 100%; }
}
</style>
</head>

<body>
<audio id="player" autoplay loop> <source src="https://raw.githubusercontent.com/HDSachaNewLive/foodhub-assets/main/yume 2kki - lotus waters (tiktok frutiger aero version).mp3" type="audio/mpeg"> </audio>
<?php include 'sidebar.php'; ?>
<main>
<?php include 'slider_son.php'; ?>
<style>
    #volume-slider {
    background: linear-gradient(135deg, #33b0d2ff, #58edf5ff); }
    #volume-button {
    background: linear-gradient(135deg, #33b0d2ff, #58edf5ff);
    }
  </style>
<section class="container">
<h2 class="section-title">
  📊 Statistiques — <?= $restaurant_filtre !== null ? htmlspecialchars($restaurant_filtre_nom) : 'Tous vos restaurants' ?>
</h2>

<?php if (count($mes_restaurants) > 1): ?>
<div class="filtre-restaurant">
  <label for="restaurant-select">🏪 Filtrer par restaurant :</label>
  <select id="restaurant-select" onchange="if(this.value==='all'){window.location.href='statistiques_vendeur.php';}else{window.location.href='statistiques_vendeur.php?restaurant_id='+encodeURIComponent(this.value);}">
    <option value="all" <?= $restaurant_filtre === null ? 'selected' : '' ?>>🌍 Tous mes restaurants</option>
    <?php foreach ($mes_restaurants as $r): ?>
      <option value="<?= (int)$r['restaurant_id'] ?>" <?= $restaurant_filtre === (int)$r['restaurant_id'] ? 'selected' : '' ?>>
        <?= htmlspecialchars($r['nom_restaurant']) ?>
      </option>
    <?php endforeach; ?>
  </select>
</div>
<?php endif; ?>

<div class="stats-grid">
  <div class="stat-card">
    <h4>Chiffre d'affaires total</h4>
    <p class="counter" data-target="<?= $ca_total ?>"><span class="number">0</span> €</p>
</div>

<div class="stat-card">
    <h4>Moyenne par commande</h4>
    <p class="counter" data-target="<?= $avg_cmd ?>">
        <span class="number">0,00</span> €
    </p>
</div>

<div class="stat-card">
  <h4>Plat le plus vendu</h4>
  <p><?= htmlspecialchars($top_plat['nom_plat'] ?? '-') ?></p>
</div>
</div>

<a href="profile_proprio.php" class="btn">← Retour</a>
</section>

<div class="chart-container" style="max-width:900px;">
  <h3 class="section-title">📈 Commandes sur 7 jours</h3>
  <div class="chart-box">
    <canvas id="cmdsChart"></canvas>
  </div>
</div>

<div class="chart-container" style="max-width:900px;">
  <h3 class="section-title">🍽️ Top 5 plats les plus vendus</h3>
  <div class="chart-box">
    <canvas id="topPlatsChart"></canvas>
  </div>
</div>
</main>
<script>
function updateChartBoxes() {
  document.querySelectorAll('.chart-box > canvas').forEach(function(canvas) {
    const h = Math.ceil(canvas.getBoundingClientRect().height);
    if (h && canvas.parentElement) canvas.parentElement.style.minHeight = h + 'px';
  });
}

function debounce(fn, wait){ let t; return function(...args){ clearTimeout(t); t = setTimeout(()=>fn.apply(this,args), wait); }; }

const cmdsCtx = document.getElementById("cmdsChart").getContext('2d');
new Chart(cmdsCtx, {
  type: "bar",
  data: {
    labels: <?= json_encode($jours) ?>,
    datasets: [{
      label: "Commandes",
      data: <?= json_encode($nb_cmds) ?>,
      backgroundColor: "#ff6b6b66",
      borderColor: "#ff6b6b",
      borderWidth: 2
    }]
  },
  options: {
  responsive: true,
  maintainAspectRatio: false,
  scales: {
    x: { beginAtZero: true },
    y: { beginAtZero: true, precision: 0 }
  }
}
});

const topCtx = document.getElementById("topPlatsChart").getContext('2d');
new Chart(topCtx, {
  type: "pie",
  data: {
    labels: <?= json_encode(array_column($top_plats, 'nom_plat')) ?>,
    datasets: [{
      data: <?= json_encode(array_column($top_plats, 'total_qte')) ?>,
      backgroundColor: ["#ff6b6b","#ffb86c","#ffdca8","#a3d8ff","#d7ffd9"]
    }]
  },
  options: {
  responsive: true,
  maintainAspectRatio: false
}
});

setTimeout(updateChartBoxes, 50);
window.addEventListener('resize', debounce(updateChartBoxes, 150));
</script>

<script>
document.addEventListener("DOMContentLoaded", () => {
  const counters = document.querySelectorAll(".counter");
  counters.forEach(counter => {
    const numberEl = counter.querySelector(".number") || counter;
    const isInt = counter.dataset.targetInt === "1";
    const raw = counter.getAttribute("data-target") || "0";
    let target = 0;
    if (isInt) {
      const cleaned = raw.replace(/[^0-9\-]/g, "");
      target = parseInt(cleaned, 10);
      if (isNaN(target)) target = 0;
    } else {
      const cleaned = raw.toString().replace(/[^0-9.,\-]/g, "").replace(",", ".");
      target = parseFloat(cleaned);
      if (isNaN(target)) target = 0;
    }
    let current = 0;
    const step = isInt ? Math.max(Math.ceil(target / 50), 1) : Math.max(target / 50, 0.01);
    function tick() {
      current += step;
      if (current < target) {
        numberEl.textContent = isInt ? String(Math.floor(current)) : current.toFixed(2).replace('.', ',');
        requestAnimationFrame(tick);
      } else {
        numberEl.textContent = isInt ? String(target) : target.toFixed(2).replace('.', ',');
      }
    }
    tick();
  });
});
</script>

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
  color: 0x7cc6e6,
  shininess: 25,
  waveHeight: 25,
  waveSpeed: 0.9,
  zoom: 0.9
})
</script>
</body>
</html>