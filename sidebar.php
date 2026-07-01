<!-- sidebar.php -->
<?php
require_once 'auth_helper.php';
require_once __DIR__ . '/csrf_helper.php';
// INSERT: calcul des notifications au début (avant sortie HTML)
$notifCount = 0;
$pendingRestoCount = 0;
$totalNotifCount = 0;
$notif_forum_count = 0;

if (isset($_SESSION['user_id'])) {
    $user_id = (int)$_SESSION["user_id"];
    $owner_email = "mehdiguerbas5@gmail.com"; // email du proprio
    $is_owner = false;
    
    // récupérer email de l'utilisateur
    $uQ = $conn->prepare("SELECT email FROM users WHERE user_id = ?");
    $uQ->execute([$user_id]);
    $uR = $uQ->fetch(PDO::FETCH_ASSOC);
    if ($uR && isset($uR["email"])) $is_owner = ($uR["email"] === $owner_email);
    
    $stmt = $conn->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0");
    $stmt->execute([$user_id]);
    $notifCount = (int)$stmt->fetchColumn();

    if ($is_owner) {
        $pQ = $conn->query("SELECT COUNT(*) FROM restaurants WHERE verified = 0");
        $pendingRestoCount = (int)$pQ->fetchColumn();
    }

    
    if (isset($_SESSION['user_id'])) {
        $stmt_forum_badge = $conn->prepare("
            SELECT COUNT(*) FROM forum_notifs
            WHERE user_id = ? AND is_read = 0
        ");
        $stmt_forum_badge->execute([$user_id]);
        $notif_forum_count = (int)$stmt_forum_badge->fetchColumn();
    }

    $totalNotifCount = $notifCount + $pendingRestoCount;
}

// Nombre d'articles dans le panier
$panierCount = 0;
if (isset($_SESSION['user_id'])) {
    $stmtPanier = $conn->prepare("SELECT COALESCE(SUM(quantite), 0) FROM panier WHERE user_id = ?");
    $stmtPanier->execute([$user_id]);
    $panierCount = (int)$stmtPanier->fetchColumn();
}
?>
<div id="sidebar" class="sidebar no-transition">
  <h2>Menu</h2> 
  <br>
  <a href="home.php">🏠 Accueil</a>
  <?php if (isset($_SESSION['type_compte']) && $_SESSION['type_compte'] === 'proprietaire'): ?>
    <a href="profile_proprio.php">👤 Profil</a>
  <?php elseif (isset($_SESSION['type_compte']) && $_SESSION['type_compte'] === 'client'): ?>
    <a href="profile.php">👤 Profil</a>
  <?php endif; ?>
  <a href="restaurants.php">🍽️ Restaurants</a>
  <a href="panier.php" class="notif-link" id="panier-link">
    🛒 Panier
    <?php if ($panierCount > 0): ?>
        <span class="notif-badge" id="panier-badge"><?php if($panierCount < 10) { echo $panierCount; } else { echo "9+"; }?></span>
    <?php else: ?>
        <span class="notif-badge" id="panier-badge" style="display:none;">0</span>
    <?php endif; ?>
  </a>
  <a href="suivi_commande.php" class="sidebar-link">📦 Suivi des commandes</a>
  
    <?php if(!isset($_SESSION['user_id'])): ?>
    
    <a href="contact_admin.php">📧 Contact</a>
    <?php endif; ?>
    
  <?php if (isset($_SESSION['user_id'])): ?>
    <a id="nte" href="media_player.php">💿 NTE OST</a>
    <a id="smash" href="stream_smash.php">⚔️ Smash en direct</a>
    <a href="mes_favoris.php">❤️ Mes Favoris</a>
    <a href="forum.php" class="notif-link">
        💬 Discussion
    </a>
    <a href="notifications.php" class="notif-link">
      🔔 Notifications
      <?php if ($totalNotifCount > 0): ?>
        <span class="notif-badge"><?= (int)$totalNotifCount ?></span>
      <?php endif; ?>
    </a>
    <a href="parametres.php">⚙️ Paramètres</a>
    
  <?php if (isset($_SESSION['user_id']) && isset($conn) && fh_is_admin($conn)): ?>
    <a href="admin_users.php">👥 Utilisateurs</a>
    <a href="admin_messages.php">📩 Messages</a>
    <a href="admin_coupons.php">🎟️ Coupons</a>
    <a href="admin_annonces.php">📢 Annonces</a>
  <?php endif; ?>
    <a href="contact_admin.php">📧 Contact</a>
    <a href="apropos.php">🧭 À propos</a>
  <?php if (isset($_SESSION['user_id'])): ?>
    <a href="tos.php" style="margin-bottom: 35px;">✒️ Conditions de Service</a>
  <?php endif; ?>
    <a href="logout.php" class="logout">🚪 Déconnexion</a>
  <?php endif; ?>
    <?php if (!isset($_SESSION['user_id'])): ?>
      <a href="index.php" class="logout"> ← Retour</a>
    <?php endif; ?>
</div>

<button id="toggleSidebar" class="menu-btn">
    ☰
    <?php if(isset($totalNotifCount) && $totalNotifCount > 0): ?>
        <span class="menu-badge"><?= $totalNotifCount ?></span>
    <?php endif; ?>
</button>

<?php include 'vanta_freeze.php'; ?>
<!-- Champ CSRF global (caché) pour que le JS puisse le récupérer via document.querySelector -->
<div style="display:none;" id="fh-global-csrf"><?= fh_csrf_field() ?></div>
<?php
// récupération nombre de notifs non lues
$notifCount = 0;
$pendingRestoCount = 0;

if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];

    // Notifs non lues classiques
    $stmt = $conn->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0");
    $stmt->execute([$user_id]);
    $notifCount = (int)$stmt->fetchColumn();

    // Si c'est le propriétaire (super-admin)
    $owner_email = "mehdiguerbas5@gmail.com";
    $uQ = $conn->prepare("SELECT email FROM users WHERE user_id = ?");
    $uQ->execute([$user_id]);
    $email = $uQ->fetchColumn();

    if ($email === $owner_email) {
        // Restos à vérifier
        $pQ = $conn->query("SELECT COUNT(*) FROM restaurants WHERE verified = 0");
        $pendingRestoCount = (int)$pQ->fetchColumn();
    }
}
$totalNotifCount = $notifCount + $pendingRestoCount;
?>

<style>
/* barre */
.sidebar {
  position: fixed;
  top: 0;
  left: -260px;
  width: 240px;
  height: 100vh;
  padding: 25px 20px;
  backdrop-filter: blur(12px);
  background: rgba(190, 190, 190, 0.28);
  border-right: 1px solid rgba(255, 255, 255, 0.2);
  box-shadow: 4px 0 20px rgba(0, 0, 0, 0.15);
  transition: left 0.35s ease;
  z-index: 1000;
  display: flex;
  flex-direction: column;
  justify-content: flex-start;
}
.sidebar h2 {
  margin: 0px auto 0 auto;
  color: white;
}
.sidebar.open {
  left: 0;
}

.sidebar a {
  display: block;
  font-family: 'HSR';
  font-weight: 600;
  color: #fff;
  text-decoration: none;
  margin: 12px 0;
  padding: 10px 15px;
  border-radius: 12px;
  transition: all 0.25s ease;
  background: rgba(92, 92, 92, 0.2);
}

.sidebar a:hover {
  background: rgba(255, 107, 107, 0.25);
  color: #fff;
  transform: translateX(4px);
}

.sidebar .logout {
  margin-top: auto;
  background: rgba(255, 80, 80, 0.2);
}

.sidebar .logout:hover {
  background: rgba(255, 80, 80, 0.35);
}

/*bouton menu */
.menu-btn {
  position: fixed;
  top: 42px;
  left: 14px;
  transform: translateY(-50%);
  font-size: 1.6rem;
  background: rgba(221, 139, 139, 0.15);
  border: none;
  color: rgba(255, 255, 255, 0.68);
  backdrop-filter: blur(10px);
  border-radius: 12px;
  padding: 8px 12px;
  cursor: pointer;
  z-index: 1100;
  transition: all 0.25s ease;
  box-shadow: 0 6px 18px rgba(0,0,0,0.12);
}

.menu-btn:hover {
  background: rgba(255, 255, 255, 0.25);
  transform: translateY(-50%) scale(1.05);
}

/* badge de notifications */
.notif-link {
  position: relative;
}

.notif-badge {
  position: absolute;
  top: 5px;
  right: 15px;
  background: #ff6b6b;
  color: white;
  border-radius: 50%;
  padding: 2px 6px;
  font-size: 12px;
  font-weight: bold;
}

.menu-badge {
  position: absolute;
  top: -5px;
  right: -5px;
  background: #ff6b6b;
  color: white;
  border-radius: 50%;
  padding: 2px 6px;
  font-size: 12px;
  font-weight: bold;
}

h2{
  font-family: 'HSR';
}
/* scrollbar personnalisée */
.sidebar::-webkit-scrollbar {
  width: 4px;
}

.sidebar::-webkit-scrollbar-track {
  background: rgba(255, 255, 255, 0.1);
  border-radius: 10px;
}

.sidebar::-webkit-scrollbar-thumb {
  background: rgba(247, 246, 246, 0.5);
  border-radius: 10px;
}

.sidebar::-webkit-scrollbar-thumb:hover {
  background: rgba(247, 246, 246, 0.7);
}

.sidebar.no-transition {
  transition: none !important;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const sidebar = document.getElementById('sidebar');
  const toggle = document.getElementById('toggleSidebar');
  if (!sidebar || !toggle) return;

  // Retirer no-transition après le premier rendu
  requestAnimationFrame(() => {
    sidebar.classList.remove('no-transition');
  });

  // S'assurer que la sidebar est fermée au chargement
  sidebar.classList.remove('open');

  // ouverture / fermeture
  toggle.addEventListener('click', function (e) {
    e.stopPropagation();
    sidebar.classList.toggle('open');
  });

  // fermer si on clique en dehors
  document.addEventListener('click', function (e) {
    if (!sidebar.contains(e.target) && !toggle.contains(e.target)) {
      sidebar.classList.remove('open');
    }
  });

  // fermer avec ESC
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') sidebar.classList.remove('open');
  });
});

// Mise à jour temps réel du badge panier
function updatePanierBadge() {
    fetch('get_panier_count.php')
        .then(r => r.json())
        .then(data => {
            const badge = document.getElementById('panier-badge');
            if (!badge) return;
            if (data.count > 0) {
              if (data.count > 9) {
                badge.textContent = "9+";
                badge.style.display = '';
              } else {
                badge.textContent = data.count;
                badge.style.display = '';
              }
            } else {
                badge.style.display = 'none';
            }
        })
        .catch(() => {}); // silencieux si erreur réseau
}

// Polling toutes les 1 secondes
setInterval(updatePanierBadge, 1000);

// Mise à jour immédiate si on revient sur la page (onglet redevenu actif)
document.addEventListener('visibilitychange', () => {
    if (!document.hidden) updatePanierBadge();
});
</script>