<?php
// admin_users.php
session_start();
require_once 'db/config.php';
require_once 'mail_helper.php';
require_once 'delete_user_helper.php';
/* Vérification admin (centralisée) */
require_once __DIR__ . '/auth_helper.php';
fh_require_admin($conn);
require_once __DIR__ . '/csrf_helper.php';

$message = "";
$error = "";

// Récupérer les messages de la session (après redirection)
if (isset($_SESSION['admin_message'])) {
    $message = $_SESSION['admin_message'];
    unset($_SESSION['admin_message']);
}
if (isset($_SESSION['admin_error'])) {
    $error = $_SESSION['admin_error'];
    unset($_SESSION['admin_error']);
}

// Traitement des actions admin
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST['csrf_token']) || !fh_verify_csrf($_POST['csrf_token'])) {
        $_SESSION['admin_error'] = 'Jeton CSRF invalide.';
        header('Location: admin_users.php');
        exit;
    }
    $target_user_id = (int)($_POST['target_user_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $raison = trim($_POST['raison'] ?? '');

    if ($target_user_id <= 0) {
        $_SESSION['admin_error'] = "❌ Paramètres invalides.";
        header('Location: admin_users.php');
        exit;
    }

    // Récupérer les informations de l'utilisateur ciblé
    $stmt_target = $conn->prepare("SELECT user_id, type_compte, role, compte_actif FROM users WHERE user_id = ? LIMIT 1");
    $stmt_target->execute([$target_user_id]);
    $target_user = $stmt_target->fetch(PDO::FETCH_ASSOC);
    if (!$target_user) {
        $_SESSION['admin_error'] = "❌ Utilisateur introuvable.";
        header('Location: admin_users.php');
        exit;
    }

    $target_is_admin = ($target_user['type_compte'] === 'admin') || (!empty($target_user['role']) && $target_user['role'] === 'admin');
    if ($target_is_admin) {
        $stmt_count = $conn->prepare("SELECT COUNT(*) FROM users WHERE (type_compte = 'admin' OR role = 'admin') AND compte_actif = 1");
        $stmt_count->execute();
        $admin_count = (int)$stmt_count->fetchColumn();
        if ($admin_count <= 1) {
            $_SESSION['admin_error'] = "❌ Impossible de modifier/supprimer le dernier compte administrateur.";
            header('Location: admin_users.php');
            exit;
        }
    }

    if ($target_user_id > 0 && $action) {
        try {
            $conn->beginTransaction();

            switch ($action) {
                case 'desactiver':
                    $stmt = $conn->prepare("UPDATE users SET compte_actif = 0, date_desactivation = NOW() WHERE user_id = ?");
                    $stmt->execute([$target_user_id]);
                    $_SESSION['admin_message'] = "✅ Compte désactivé avec succès.";
                    break;

                case 'activer':
                    $stmt = $conn->prepare("UPDATE users SET compte_actif = 1, date_desactivation = NULL WHERE user_id = ?");
                    $stmt->execute([$target_user_id]);
                    $_SESSION['admin_message'] = "✅ Compte réactivé avec succès.";
                    break;

                case 'supprimer':
                    $conn->rollBack(); // fh_delete_user gère sa propre transaction
                    fh_delete_user($conn, $target_user_id);
                    $_SESSION['admin_message'] = "🗑️ Compte supprimé avec succès.";
                    // Enregistrer l'action dans l'historique (hors transaction déjà commitée)
                    $stmt = $conn->prepare("INSERT INTO admin_actions (admin_id, target_user_id, action_type, raison) VALUES (?, ?, ?, ?)");
                    $stmt->execute([(int)fh_current_user_id(), $target_user_id, $action, $raison]);
                    header("Location: admin_users.php");
                    exit;
            }

            // Enregistrer l'action dans l'historique (pour desactiver/activer)
            $stmt = $conn->prepare("INSERT INTO admin_actions (admin_id, target_user_id, action_type, raison) VALUES (?, ?, ?, ?)");
            $stmt->execute([(int)fh_current_user_id(), $target_user_id, $action, $raison]);

            $conn->commit();

            header("Location: admin_users.php");
            exit;

        } catch (Exception $e) {
            if ($conn->inTransaction()) {
                    $conn->rollBack();
                }
                error_log('[admin_users] Erreur transaction admin action: ' . $e->getMessage());
                $_SESSION['admin_error'] = "❌ Erreur serveur. Contacte l'administrateur.";
            header("Location: admin_users.php");
            exit;
        }
    } else {
        $_SESSION['admin_error'] = "❌ Paramètres invalides.";
        header("Location: admin_users.php");
        exit;
    }
}

// Récupérer tous les utilisateurs avec leurs stats
$stmt = $conn->query("
    SELECT u.*, 
           ps.nb_visites,
           COUNT(DISTINCT a.avis_id) as nb_avis,
           COUNT(DISTINCT r.restaurant_id) as nb_restaurants
    FROM users u
    LEFT JOIN profil_stats ps ON u.user_id = ps.user_id
    LEFT JOIN avis a ON u.user_id = a.user_id
    LEFT JOIN restaurants r ON u.user_id = r.proprietaire_id
    GROUP BY u.user_id
    ORDER BY u.user_id ASC
");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer l'historique des actions
$stmt = $conn->query("
    SELECT aa.*, 
           u.nom_user as target_nom,
           a.nom_user as admin_nom
    FROM admin_actions aa
    LEFT JOIN users u ON aa.target_user_id = u.user_id
    LEFT JOIN users a ON aa.admin_id = a.user_id
    ORDER BY aa.date_action DESC
    LIMIT 20
");
$historique = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <link rel="icon" type="image/x-icon" href="FoodHubLogo.ico">
    <meta charset="UTF-8">
    <title>Admin - Gestion des utilisateurs</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="admin_users.css">
</head>
<body>

<?php include 'sidebar.php'; ?>
<audio id="player" autoplay loop>
    <source src="https://raw.githubusercontent.com/HDSachaNewLive/foodhub-assets/main/16. System Music - Download Management.mp3" type="audio/mp3">
</audio>
<?php include 'slider_son.php'; ?>

<main class="container">
    <h1 class="admin-title">👥 Gestion des utilisateurs</h1>

    <?php if ($message): ?>
        <div class="success"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="admin-table">
        <h2>Liste des utilisateurs</h2>
        
        <div class="users-grid">
            <?php foreach ($users as $u): ?>
                <div class="user-card <?= !$u['compte_actif'] ? 'disabled' : '' ?>">
                    <div class="user-photo">
                        <?php if ($u['photo_profil'] && file_exists($u['photo_profil'])): ?>
                            <img src="<?= htmlspecialchars($u['photo_profil']) ?>" alt="Photo">
                        <?php else: ?>
                            <div class="default-photo">
                                <?= strtoupper(substr($u['nom_user'], 0, 2)) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="user-info">
                        <h4>
                            <a href="profil_public.php?user_id=<?= $u['user_id'] ?>" 
                               class="user-name">
                                <?= htmlspecialchars($u['nom_user']) ?>
                            </a>
                        </h4>
                        
                        <div class="user-badges">
                            <?php $isAdminBadge = ($u['type_compte'] === 'admin') || (!empty($u['role']) && $u['role'] === 'admin'); ?>
                            <?php if ($isAdminBadge): ?>
                                <span class="badge admin">⚙️ Admin</span>
                            <?php else: ?>
                                <span class="badge <?= $u['type_compte'] ?>">
                                    <?= $u['type_compte'] === 'proprietaire' ? '🏪 Proprio' : '👤 Client' ?>
                                </span>
                            <?php endif; ?>
                            
                            <?php if (!$u['compte_actif']): ?>
                                <span class="badge disabled">🚫 Désactivé</span>
                            <?php endif; ?>
                        </div>
                        
                        <p class="user-email">📧 <?= htmlspecialchars($u['email']) ?></p>
                        
                        <div class="user-stats-mini">
                            <span>💬 <?= $u['nb_avis'] ?> avis</span>
                            <?php if ($u['type_compte'] === 'proprietaire'): ?>
                                <span>🍽️ <?= $u['nb_restaurants'] ?> restos</span>
                            <?php endif; ?>
                            <span>👁️ <?= $u['nb_visites'] ?? 0 ?> vues</span>
                        </div>
                        
                        <p class="user-date">
                            Inscrit le <?= date('d/m/Y', strtotime($u['date_creation'])) ?>
                        </p>
                    </div>
                    
                    <?php if ($u['user_id'] != 1): ?>
                        <div class="user-actions">
                            <?php if ($u['compte_actif']): ?>
                                <button class="btn-action desactiver" 
                                        onclick="showActionModal(<?= $u['user_id'] ?>, 'desactiver', '<?= addslashes($u['nom_user']) ?>')">
                                    ⏸️ Désactiver
                                </button>
                            <?php else: ?>
                                <button class="btn-action activer" 
                                        onclick="showActionModal(<?= $u['user_id'] ?>, 'activer', '<?= addslashes($u['nom_user']) ?>')">
                                    ▶️ Activer
                                </button>
                            <?php endif; ?>
                            
                            <button class="btn-action supprimer" 
                                    onclick="showActionModal(<?= $u['user_id'] ?>, 'supprimer', '<?= addslashes($u['nom_user']) ?>')">
                                🗑️ Supprimer
                            </button>
                        </div>
                    <?php else: ?>
                        <div class="user-actions">
                            <span class="protected-badge">🔒 Compte protégé</span>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <br>

    <div class="admin-table">
        <h2 style="color: black; margin-bottom: 9px; margin-top: 5px;">📜 Historique des actions</h2>
        
        <?php if (empty($historique)): ?>
            <p>Aucune action enregistrée.</p>
        <?php else: ?>
            <table>
                <tr>
                    <th>Date</th>
                    <th>Utilisateur</th>
                    <th>Action</th>
                    <th>Raison</th>
                </tr>
                <?php foreach ($historique as $h): ?>
                    <tr>
                        <td><?= date('d/m/Y H:i', strtotime($h['date_action'])) ?></td>
                        <td><?= htmlspecialchars($h['target_nom'] ?? 'Compte supprimé') ?></td>
                        <td>
                            <span class="action-badge <?= $h['action_type'] ?>">
                                <?= ucfirst($h['action_type']) ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars($h['raison'] ?: '-') ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>
    </div>
    
    <p><a href="home.php" class="back-link">⬅️ Retour à l'accueil</a></p>
</main>

<!-- Modal de confirmation -->
<div id="actionModal" class="modal">
    <div class="modal-content">
        <h3 id="modalTitle"></h3>
        <p id="modalText"></p>
        
        <form method="POST" id="actionForm">
            <input type="hidden" name="target_user_id" id="targetUserId">
            <input type="hidden" name="action" id="actionType">
            <?= fh_csrf_field() ?>
            
            <label for="raison">Raison (optionnelle) :</label>
            <textarea name="raison" id="raison" rows="3" 
                      placeholder="Expliquez pourquoi vous effectuez cette action..."></textarea>
            
            <div class="modal-actions">
                <button type="submit" class="btn-confirm" id="confirmBtn">Confirmer</button>
                <button type="button" class="btn-cancel" onclick="closeModal()">Annuler</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/three@0.149.0/build/three.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/vanta/dist/vanta.waves.min.js"></script>

<script>
window.vantaEffect = VANTA.WAVES({
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
});

function showActionModal(userId, action, userName) {
    const modal = document.getElementById('actionModal');
    const title = document.getElementById('modalTitle');
    const text = document.getElementById('modalText');
    const confirmBtn = document.getElementById('confirmBtn');
    
    document.getElementById('targetUserId').value = userId;
    document.getElementById('actionType').value = action;
    
    const messages = {
        'desactiver': {
            title: '⏸️ Désactiver le compte',
            text: `Êtes-vous sûr de vouloir désactiver le compte de "${userName}" ? L'utilisateur ne pourra plus se connecter.`,
            btnText: 'Désactiver',
            btnClass: 'desactiver'
        },
        'activer': {
            title: '▶️ Activer le compte',
            text: `Voulez-vous réactiver le compte de "${userName}" ?`,
            btnText: 'Activer',
            btnClass: 'activer'
        },
        'supprimer': {
            title: '🗑️ Supprimer le compte',
            text: `⚠️ ATTENTION : Êtes-vous sûr de vouloir SUPPRIMER définitivement le compte de "${userName}" ? Cette action est IRRÉVERSIBLE et supprimera toutes ses données.`,
            btnText: 'Supprimer définitivement',
            btnClass: 'supprimer'
        }
    };
    
    const msg = messages[action];
    title.textContent = msg.title;
    text.textContent = msg.text;
    confirmBtn.textContent = msg.btnText;
    confirmBtn.className = 'btn-confirm ' + msg.btnClass;
    
    modal.style.display = 'flex';
}

function closeModal() {
    document.getElementById('actionModal').style.display = 'none';
    document.getElementById('raison').value = '';
}

// Fermer modal en cliquant en dehors
window.onclick = function(event) {
    const modal = document.getElementById('actionModal');
    if (event.target === modal) {
        closeModal();
    }
}
</script>

<style>
/*déplacé vers admin_users.css*/
</style>

</body>
</html>