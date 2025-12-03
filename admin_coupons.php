<?php
session_start();
require_once 'db/config.php';

/* Vérification admin */
if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] != 1) {
    header("Location: index.php");
    exit;
}

/* Suppression d'un coupon */
if (isset($_GET['delete'])) {
    $id = (int) $_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM coupons WHERE coupon_id = ?");
    $stmt->execute([$id]);
    // Utiliser une session pour stocker le message
    $_SESSION['deleted_message'] = true;
    header("Location: admin_coupons.php");
    exit;
}

/* Création d'un coupon */
$message = "";
$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = trim($_POST['code']);
    $type = $_POST['type'];
    $valeur = $_POST['valeur'];

    $date_debut = $_POST['date_debut'];
    $date_fin   = $_POST['date_fin'];

    // Correction format datetime-local → MySQL
    $date_debut = str_replace('T', ' ', $date_debut) . ':00';
    $date_fin   = str_replace('T', ' ', $date_fin) . ':00';

    $utilisation_max = !empty($_POST['utilisation_max']) ? $_POST['utilisation_max'] : NULL;
    $restaurant_id   = !empty($_POST['restaurant_id']) ? $_POST['restaurant_id'] : NULL;

    $stmt = $conn->prepare("
        INSERT INTO coupons (code_reduction, type, valeur, date_debut, date_fin, utilisation_max, restaurant_id)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    try {
        $stmt->execute([
            $code, $type, $valeur, $date_debut, $date_fin,
            $utilisation_max, $restaurant_id
        ]);
        // Utiliser une session pour stocker le message
        $_SESSION['created_message'] = true;
        header("Location: admin_coupons.php");
        exit;
    } catch (PDOException $e) {
        // Erreur code déjà existant (duplicate entry)
        if ($e->getCode() == 23000) {
            $error = "❌ Ce code existe déjà.";
        } else {
            $error = "❌ Erreur : " . $e->getMessage();
        }
    }
}

/* Charger restaurants */
$stmt = $conn->query("SELECT restaurant_id, nom_restaurant FROM restaurants");
$restaurants = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* Charger coupons */
$stmt = $conn->query("SELECT * FROM coupons ORDER BY date_debut DESC");
$coupons = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Afficher l'erreur seulement si elle existe ET qu'on vient de faire un POST
if ($error && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $error = "";
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Admin - Gestion des coupons</title>
    <link rel="stylesheet" href="assets/style.css">
</head>

<body>

<?php include 'sidebar.php'; ?>
<audio id="player" autoplay loop> <source src="assets/Able Sisters Shop - Animal Crossing New Horizons Soundtrack.mp3" type="audio/mp3"> </audio>
<?php include 'slider_son.php'; ?>

<main class="container">

    <h2 class="admin-title">🎟️ Gestion des coupons</h2>

    <?php if (isset($_SESSION['created_message'])): ?>
        <div class="success">✅ Coupon créé avec succès !</div>
        <?php unset($_SESSION['created_message']); ?>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['deleted_message'])): ?>
        <div class="success">🗑️ Coupon supprimé avec succès !</div>
        <?php unset($_SESSION['deleted_message']); ?>
    <?php endif; ?>

    <div class="admin-form">
        <h3>Créer un nouveau coupon</h3>

        <form method="POST">

            <label>Code du coupon :</label>
            <input type="text" name="code" required placeholder="ex: PROMO10">

            <label>Type de réduction :</label>
            <select name="type">
                <option value="pourcentage">Pourcentage (%)</option>
                <option value="montant">Montant (€)</option>
            </select>

            <label>Valeur :</label>
            <input type="number" step="0.01" name="valeur" required placeholder="ex: 10">

            <label>Date de début :</label>
            <input type="datetime-local" name="date_debut" required>

            <label>Date de fin :</label>
            <input type="datetime-local" name="date_fin" required>

            <label>Nombre max d'utilisations (optionnel) :</label>
            <input type="number" name="utilisation_max" placeholder="Laisser vide pour illimité">

            <label>Limiter à un restaurant :</label>
            <select name="restaurant_id">
                <option value="">Tous les restaurants</option>
                <?php foreach ($restaurants as $r): ?>
                    <option value="<?= $r['restaurant_id'] ?>"><?= htmlspecialchars($r['nom_restaurant']) ?></option>
                <?php endforeach; ?>
            </select>

            <button class="btn" type="submit">Créer le coupon</button>
        </form>
    </div>

    <div class="admin-table">
        <h3>Coupons existants</h3>

        <?php if (empty($coupons)): ?>
            <p>Aucun coupon pour le moment.</p>
        <?php else: ?>

        <table>
            <tr>
                <th>Code</th>
                <th>Type</th>
                <th>Valeur</th>
                <th>Début</th>
                <th>Fin</th>
                <th>Utilisations</th>
                <th>Restaurant</th>
                <th>Statut</th>
                <th>Action</th>
            </tr>

            <?php foreach ($coupons as $c): ?>
            <?php 
                $now = date('Y-m-d H:i:s');
                $is_expired = $c['date_fin'] < $now;
                $is_max_used = $c['utilisation_max'] && $c['utilisations'] >= $c['utilisation_max'];
                $is_active = $c['actif'] && !$is_expired && !$is_max_used && $c['date_debut'] <= $now;
            ?>
            <tr class="<?= $is_active ? '' : 'inactive-coupon' ?>">
                <td><strong><?= htmlspecialchars($c['code_reduction']) ?></strong></td>
                <td><?= $c['type'] === 'pourcentage' ? '📊 Pourcentage' : '💰 Montant' ?></td>
                <td><?= $c['valeur'] ?><?= $c['type'] === 'pourcentage' ? '%' : '€' ?></td>
                <td><?= date('d/m/Y H:i', strtotime($c['date_debut'])) ?></td>
                <td><?= date('d/m/Y H:i', strtotime($c['date_fin'])) ?></td>
                <td>
                    <span class="usage-counter"><?= $c['utilisations'] ?></span> / 
                    <?= $c['utilisation_max'] ? $c['utilisation_max'] : "∞" ?>
                </td>
                <td><?= $c['restaurant_id'] ? $c['restaurant_id'] : "🌍 Tous" ?></td>
                <td>
                    <?php if (!$c['actif']): ?>
                        <span class="status-badge inactive">Désactivé</span>
                    <?php elseif ($is_expired): ?>
                        <span class="status-badge expired">Expiré</span>
                    <?php elseif ($is_max_used): ?>
                        <span class="status-badge maxed">Limite atteinte</span>
                    <?php elseif ($c['date_debut'] > $now): ?>
                        <span class="status-badge pending">À venir</span>
                    <?php else: ?>
                        <span class="status-badge active">✅ Actif</span>
                    <?php endif; ?>
                </td>
                <td>
                    <a class="delete" href="?delete=<?= $c['coupon_id'] ?>" onclick="return confirm('Supprimer ce coupon ?');">
                        Supprimer
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>

        <?php endif; ?>
    </div>
    
    <p><a href="home.php" class="back-link">⬅ Retour à l'accueil</a></p>

</main>

<script src="https://cdn.jsdelivr.net/npm/three@0.149.0/build/three.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/vanta/dist/vanta.waves.min.js"></script>

<script>
VANTA.WAVES({
  el: "body",
  mouseControls: true,
  touchControls: true,
  gyroControls: false,
  minHeight: 1205.00,
  minWidth: 200.00,
  scale: 1.00,
  scaleMobile: 1.00,
  color: 0xf6b26b,
  shininess: 60,
  waveHeight: 22,
  waveSpeed: 0.7,
  zoom: 1.1
})
</script>

<style>
.container {
    backdrop-filter: blur(15px);
    background: rgba(255, 255, 255, 0.25);
    padding: 2rem;
    border-radius: 1.5rem;
    max-width: 1200px;
    margin: 100px auto;
}

.admin-title {
    color: #ff6b6b;
    margin-bottom: 2rem;
    text-align: center;
    font-size: 2rem;
}

.success {
    background: rgba(0, 255, 127, 0.25);
    padding: 12px 20px;
    border-radius: 10px;
    margin-bottom: 20px;
    border-left: 4px solid #00ff7f;
    color: #006837;
    font-weight: 600;
}

.error {
    background: rgba(255, 77, 77, 0.25);
    padding: 12px 20px;
    border-radius: 10px;
    margin-bottom: 20px;
    border-left: 4px solid #ff4d4d;
    color: #8b0000;
    font-weight: 600;
}

.admin-form,
.admin-table {
    background: rgba(255, 255, 255, 0.20);
    backdrop-filter: blur(14px);
    border-radius: 1.3rem;
    padding: 2rem;
    margin-bottom: 2rem;
    box-shadow: 0 6px 15px rgba(0,0,0,0.12);
}

.admin-form h3,
.admin-table h3 {
    margin-top: 0;
    color: #333;
    margin-bottom: 1.5rem;
    font-size: 1.6rem;
}

.admin-form label {
    margin-top: 1rem;
    display: block;
    font-weight: bold;
    color: #333;
    margin-bottom: 0.5rem;
}

.admin-form input,
.admin-form select {
    width: 100%;
    padding: 0.8rem;
    margin-top: 0.4rem;
    border-radius: 0.8rem;
    border: 1px solid rgba(0, 0, 0, 0.1);
    background: rgba(255, 255, 255, 0.6);
    font-family: 'HSR', sans-serif;
    transition: all 0.3s ease;
}

.admin-form input:focus,
.admin-form select:focus {
    outline: none;
    border-color: #ff6b6b;
    background: rgba(255, 255, 255, 0.8);
    transform: scale(1.01);
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
  margin-top: 20px;
  margin-left: ;
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
.admin-form h3 {
    color: #ff6b6b;
}
/* Correction de la table qui dépasse */
.admin-table {
    overflow-x: hidden;
}

table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 1rem;
    font-size: 0.9rem;
    table-layout: fixed;
    max-width: 1070px;   
}

@media (max-width: 1200px) {
    .admin-table {
        overflow-x: scroll;
    }
}
th, td {
    padding: 1rem;
    border-bottom: 1px solid rgba(0, 0, 0, 0.1);
    text-align: left;
    word-wrap: break-word;
    font-size: 0.85rem;
}

th {
    background: rgba(255, 230, 230, 0.6);
    font-weight: 700;
    color: #333;
}

tr.inactive-coupon {
    opacity: 0.5;
}

.usage-counter {
    font-weight: 700;
    color: #ff6b6b;
}

.status-badge {
    padding: 0.3rem 0.8rem;
    border-radius: 12px;
    font-size: 0.8rem;
    font-weight: 600;
    display: inline-block;
}

.status-badge.active {
    background: rgba(76, 175, 80, 0.2);
    color: #2e7d32;
}

.status-badge.inactive {
    background: rgba(158, 158, 158, 0.2);
    color: #616161;
}

.status-badge.expired {
    background: rgba(244, 67, 54, 0.2);
    color: #c62828;
}

.status-badge.maxed {
    background: rgba(255, 152, 0, 0.2);
    color: #e65100;
}

.status-badge.pending {
    background: rgba(33, 150, 243, 0.2);
    color: #1565c0;
}

a.delete {
    color: #ff3b3b;
    font-weight: bold;
    text-decoration: none;
    transition: all 0.3s ease;
}

a.delete:hover {
    color: #cc0000;
    text-decoration: underline;
}

.back-link {
    display: inline-block;
    margin-top: 2rem;
    color: #ff6b6b;
    text-decoration: none;
    font-weight: 600;
    transition: all 0.3s ease;
    margin-top: -40px;
}

.back-link:hover {
    color: #ff8c42;
    transform: translateX(-5px);
}
</style>

</body>
</html>