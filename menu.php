<?php
// menu.php
session_start();
require_once 'db/config.php';

if (!isset($_GET['restaurant_id'])) {
    die("Restaurant non spécifié.");
}
$restaurant_id = (int)$_GET['restaurant_id'];
$uid = $_SESSION['user_id'] ?? 0;

// restaurant + owner
$stmt = $conn->prepare("
  SELECT r.*, u.nom_user AS owner_name, u.user_id AS owner_id, adresse AS adresse_resto, categorie AS categorie
  FROM restaurants r
  LEFT JOIN users u ON r.proprietaire_id = u.user_id
  WHERE r.restaurant_id = ?
");
$stmt->execute([$restaurant_id]);
$restaurant = $stmt->fetch();
if (!$restaurant) die("Restaurant introuvable.");

// plats avec type_plat
$stmt = $conn->prepare("SELECT * FROM plats WHERE restaurant_id = ? ORDER BY nom_plat");
$stmt->execute([$restaurant_id]);
$plats = $stmt->fetchAll();

// Grouper les plats par type
$plats_par_type = [];
foreach ($plats as $p) {
    $type = $p['type_plat'] ?? 'plat';
    if (!isset($plats_par_type[$type])) {
        $plats_par_type[$type] = [];
    }
    $plats_par_type[$type][] = $p;
}

// avis & moyenne
$stmt = $conn->prepare("SELECT AVG(note) AS avg_note, COUNT(*) AS cnt FROM avis WHERE restaurant_id = ?");
$stmt->execute([$restaurant_id]);
$rating = $stmt->fetch();
$stmt = $conn->prepare("
    SELECT a.*, u.nom_user, u.user_id
    FROM avis a 
    JOIN users u ON a.user_id = u.user_id 
    WHERE a.restaurant_id = ? 
    ORDER BY a.date_avis DESC
");
$stmt->execute([$restaurant_id]);
$avis = $stmt->fetchAll();

// Gestion des votes par utilisateur
$userVotes = [];
if (isset($_SESSION['user_id'])) {
    $stmt = $conn->prepare("SELECT avis_id, type FROM avis_votes WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $votes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($votes as $v) {
        $userVotes[(int)$v['avis_id']] = $v['type'];
    }
}

// Gestion POST (suppression/réponse commentaires)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $uid = (int)$_SESSION['user_id'];

    if (isset($_POST['delete_comment_id'])) {
        $cid = (int)$_POST['delete_comment_id'];
        $stmt = $conn->prepare("SELECT user_id, restaurant_id FROM avis WHERE avis_id=?");
        $stmt->execute([$cid]);
        $comment = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($comment) {
            $stmt2 = $conn->prepare("SELECT COUNT(*) FROM restaurants WHERE restaurant_id=? AND proprietaire_id=?");
            $stmt2->execute([$comment['restaurant_id'], $uid]);
            $isOwner = $stmt2->fetchColumn() > 0;

            if ($uid === (int)$comment['user_id'] || $isOwner) {
                $stmt3 = $conn->prepare("DELETE FROM avis_votes WHERE avis_id=?");
                $stmt3->execute([$cid]);
                $stmt4 = $conn->prepare("DELETE FROM avis WHERE avis_id=?");
                $stmt4->execute([$cid]);
                $_SESSION['message'] = "💬 Commentaire supprimé avec succès !";
            }
        }
        header("Location: menu.php?restaurant_id=".$comment['restaurant_id']);
        exit;
    }

    if (isset($_POST['reply_comment_id'], $_POST['reply_text']) && trim($_POST['reply_text']) !== '') {
        $rid = (int)$_POST['reply_comment_id'];
        $reply = trim($_POST['reply_text']);
        $uid = (int)$_SESSION['user_id'];

        $stmt = $conn->prepare("
            UPDATE avis 
            SET reponse = ? 
            WHERE avis_id = ? 
            AND restaurant_id IN (SELECT restaurant_id FROM restaurants WHERE proprietaire_id = ?)
        ");
        $stmt->execute([$reply, $rid, $uid]);

        $stmt2 = $conn->prepare("SELECT user_id, restaurant_id FROM avis WHERE avis_id = ?");
        $stmt2->execute([$rid]);
        $comment = $stmt2->fetch(PDO::FETCH_ASSOC);

        if ($comment && $comment['user_id'] != $uid) {
            $message = "Le propriétaire a répondu à ton commentaire";
            $stmt3 = $conn->prepare("
                INSERT INTO notifications (user_id, type, restaurant_id, avis_id, message) 
                VALUES (?, 'reply', ?, ?, ?)
            ");
            $stmt3->execute([$comment['user_id'], $comment['restaurant_id'], $rid, $message]);
        }

        header("Location: menu.php?restaurant_id=" . $comment['restaurant_id']);
        exit;
    }
}

// Mapping des icônes par type
$type_icons = [
    'entree' => '🥗',
    'plat' => '🍽️',
    'accompagnement' => '🍚',
    'boisson' => '🥤',
    'dessert' => '🍰',
    'sauce' => '🧂'
];

$type_labels = [
    'entree' => 'Entrées',
    'plat' => 'Plats',
    'accompagnement' => 'Accompagnements',
    'boisson' => 'Boissons',
    'dessert' => 'Desserts',
    'sauce' => 'Sauces'
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <title><?= htmlspecialchars($restaurant['nom_restaurant']) ?> - Menu</title>
  <link rel="stylesheet" href="assets/style.css">
  <link rel="stylesheet" href="assets/recommandation.css">
  <?php include 'sidebar.php'; ?>
</head>
<body>
  <audio id="player" autoplay loop> <source src="assets\July 2014 Nintendo eShop Music.mp3" type="audio/mpeg"> </audio>
  <?php include "slider_son.php"; ?>
  <style>
    #volume-slider {
    background: linear-gradient(135deg, #f55858ff, #f4a8a8ff); }
    #volume-button {
    background: linear-gradient(135deg, #f55858ff, #f7a6a6ff);
    }
    #volume-widget {
    right: -100px;
    }
  </style>
  
  <main class="container">
    <?php if (isset($_SESSION['message'])): ?>
      <div class="flash-message success">
    <?= htmlspecialchars($_SESSION['message']) ?>
      </div>
    <?php unset($_SESSION['message']); ?>
    <?php endif; ?>
    
    <h2 style="text-shadow: 2px 2px 4px rgba(255, 107, 107, 0.3);"><?= htmlspecialchars($restaurant['nom_restaurant']) ?> 🍽️</h2>
    
    <p class="container-desc"><?= htmlspecialchars($restaurant['description_resto']) ?></p>
    <p>Propriétaire : <?= htmlspecialchars($restaurant['owner_name'] ?? 'Indépendant') ?></p>
    <p>Adresse : <?= htmlspecialchars($restaurant['adresse_resto']) ?></p>
    <p>Catégorie : <?= htmlspecialchars($restaurant['categorie']) ?: "Non renseigné" ?></p>
    <p>Notation moyenne : <?= $rating['cnt'] ? round($rating['avg_note'],1) . " ★ (" . $rating['cnt'] . ")" : "Aucune note" ?></p>

    <!-- Filtres de type de plats -->
    <?php if (count($plats_par_type) > 1): ?>
    <div class="menu-filters">
      <button class="filter-btn active" data-filter="all">Tous</button>
      <?php foreach ($plats_par_type as $type => $items): ?>
        <button class="filter-btn" data-filter="<?= htmlspecialchars($type) ?>">
          <?= $type_icons[$type] ?? '' ?> <?= $type_labels[$type] ?? ucfirst($type) ?> (<?= count($items) ?>)
        </button>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Affichage des plats par type -->
    <?php foreach ($plats_par_type as $type => $items): ?>
    <div class="menu-category" data-category="<?= htmlspecialchars($type) ?>">
      <h3 class="category-title">
        <?= $type_icons[$type] ?? '' ?> 
        <?= $type_labels[$type] ?? ucfirst($type) ?>
      </h3>
      <div class="resto-list">
        <?php foreach ($items as $p): ?>
          <div class="resto-card">
            <h3><?= htmlspecialchars($p['nom_plat']) ?></h3>
            <p><?= htmlspecialchars($p['description_plat'] ?? "Aucune description") ?></p>
            <p><strong><?= number_format($p['prix'], 2) ?> €</strong></p>
            <?php if(isset($_SESSION['user_id'])): ?>
            <div class="add-to-cart" data-plat-id="<?= (int)$p['plat_id'] ?>">
              <input type="number" class="quantity-input" value="1" min="1" style="width:60px">
              <button class="btn add-to-cart-btn">🛒 Ajouter</button>
            </div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endforeach; ?>

    <!-- Section avis -->
    <h3>Avis</h3>
    <?php if (empty($avis)): ?>
      <p>Aucun avis pour le moment.</p>
    <?php endif; ?>
    <?php foreach ($avis as $a): ?>
  <div class="resto-card comment-card" id="comment-<?= (int)$a['avis_id'] ?>" style="position:relative;">
    <?php if ($uid === (int)$a['user_id'] || $uid === (int)$restaurant['owner_id']): ?>
    <div style="position:absolute; top:5px; right:10px; display:flex; gap:10px; z-index:10; pointer-events:auto;">
      <?php if ($uid === (int)$a['user_id']): ?>
        <button type="button"
        class="btn btn-small edit-btn"
        data-id="<?= (int)$a['avis_id'] ?>" 
        data-comment="<?= htmlspecialchars($a['commentaire'], ENT_QUOTES) ?>">
        Modifier ✏️
        </button>
      <?php endif; ?>

    <form method="post" class="form" onsubmit="return confirm('Supprimer ce commentaire ?');">
      <input type="hidden" name="delete_comment_id" value="<?= (int)$a['avis_id'] ?>">
      <button type="submit" class="btn btn-small">❌</button>
    </form>
  </div>
<?php endif; ?>

    <div class="comment-meta">
    <a href="profil_public.php?user_id=<?= $a['user_id'] ?>" style="text-decoration: none;">
      <strong style="color: #ff6b6b;"><?= htmlspecialchars($a['nom_user']) ?></strong>
    </a>
    <span>— <?= (int)$a['note'] ?>★ —</span>
    <small><?= htmlspecialchars($a['date_avis']) ?></small>
    </div>
    
    <!-- Affichage de l'image si elle existe -->
    <?php if (!empty($a['image_path']) && file_exists($a['image_path'])): ?>
      <div class="comment-image">
        <img src="<?= htmlspecialchars($a['image_path']) ?>" alt="Photo du commentaire">
      </div>
    <?php endif; ?>

    <!-- commentaires-->
    <p><?= htmlspecialchars($a['commentaire']) ?></p>

    <form method="post" action="edit_comment.php" class="form edit-form" id="edit-form-<?= (int)$a['avis_id'] ?>" style="display:none; margin-top:10px;">
    <input type="hidden" name="comment_id" value="<?= (int)$a['avis_id'] ?>">
    <input type="hidden" name="restaurant_id" value="<?= $restaurant_id ?>">
    <textarea name="new_comment" rows="3" class="form"></textarea><br>
    <button type="submit" class="btn btn-small">💾 Enregistrer</button>
    <button type="button" class="btn btn-small" onclick="hideEditForm(<?= (int)$a['avis_id'] ?>)">❌ Annuler</button>
    </form>

    <?php if (!empty($a['reponse'])): ?>
      <p><em>Réponse du propriétaire: <?= nl2br(htmlspecialchars($a['reponse'])) ?></em></p>
    <?php endif; ?>

    <?php if ($uid === (int)$restaurant['owner_id'] && $uid !== (int)$a['user_id']): ?>
      <form method="post" class="form">
        <input type="hidden" name="reply_comment_id" value="<?= (int)$a['avis_id'] ?>">
        <input type="text" name="reply_text" placeholder="Répondre..." value="" class="form">
        <button type="submit" class="btn btn-small">💬 Répondre</button>
      </form>
    <?php endif; ?>

    <?php
    $likes = (int)($a['likes'] ?? 0);
    $dislikes = (int)($a['dislikes'] ?? 0);
    $curAvisId = (int)$a['avis_id'];
    $userVote = $userVotes[$curAvisId] ?? null;
    ?>
    <div class="vote-buttons" data-avis-id="<?= $curAvisId ?>">
      <button class="like-btn <?= $userVote === 'like' ? 'active' : '' ?>" type="button" aria-label="Like">
        👍 <span class="count"><?= $likes ?></span>
      </button>
      <button class="dislike-btn <?= $userVote === 'dislike' ? 'active dislike' : '' ?>" type="button" aria-label="Dislike">
        👎 <span class="count"><?= $dislikes ?></span>
      </button>
    </div>

</div>

<?php endforeach; ?>

    <?php if (isset($_SESSION['user_id'])): ?>
      <h4>Laisser un avis</h4>
      <form method="post" action="commenter.php" class="form" enctype="multipart/form-data">
        <input type="hidden" name="restaurant_id" value="<?= $restaurant_id ?>">
        <label>Note (1-5 étoiles)</label>
        <select name="note">
          <option>5</option><option>4</option><option>3</option><option>2</option><option>1</option>
        </select><br>
        <textarea name="commentaire" placeholder="Ton avis..." rows="4" class="form"></textarea><br>
        
        <div class="file-upload">
        <label for="image_avis" class="file-label">
          📷 Ajouter une photo (optionnel)
        </label>
        <input type="file" name="image_avis" id="image_avis" accept="image/*" class="file-input">
        <div id="image-preview-container" style="display: none; margin-top: 10px; position: relative;">
          <img id="image-preview" src="" alt="Aperçu" style="max-width: 200px; max-height: 200px; border-radius: 10px;">
          <button type="button" id="remove-image" class="btn-remove-image">❌ Supprimer</button>
        </div>
        <span id="file-name" class="file-name"></span>
        </div>

        <button class="btn-publish" type="submit">Publier</button>
      </form>
    <?php else: ?>
      <p><a href="login.php">Connecte-toi</a> pour laisser un avis.</p>
    <?php endif; ?>
    <!--modal pour afficher l'image en grand -->
    <div id="imageModal" class="modal" onclick="closeImageModal()">
      <span class="close">&times;</span>
      <img class="modal-content" id="modalImage">
    </div>

    <p><a style="margin-bottom: -10px;" href="restaurants.php">⬅ Voir d'autres restaurants</a></p>
    <p><a href="<?= isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'home.php' ?>">⬅ Retour</a></p>
  </main>

<script>
// Gestion des filtres
document.addEventListener("DOMContentLoaded", () => {
  const filterBtns = document.querySelectorAll(".filter-btn");
  const categories = document.querySelectorAll(".menu-category");

  filterBtns.forEach(btn => {
    btn.addEventListener("click", () => {
      const filter = btn.dataset.filter;
      
      // Mise à jour boutons actifs
      filterBtns.forEach(b => b.classList.remove("active"));
      btn.classList.add("active");

      // Affich des catégories
      categories.forEach(cat => {
        if (filter === "all" || cat.dataset.category === filter) {
          cat.style.display = "block";
        } else {
          cat.style.display = "none";
        }
      });
    });
  });


  //gestion panier avec recommandations selon plat ajouté au panier
document.querySelectorAll(".add-to-cart-btn").forEach(btn => {
  btn.addEventListener("click", async (e) => {
    const container = e.target.closest(".add-to-cart");
    const platId = container.dataset.platId;
    const quantity = container.querySelector(".quantity-input").value;

    try {
      //ajt au panier
      const response = await fetch("panier.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded",
        },
        body: `plat_id=${platId}&quantite=${quantity}`
      });

      if (response.ok) {
        showMessage("✅ Article ajouté au panier avec succès!", "success", "panier.php", "🛒 Voir le panier");
        
        //récup les recommandations
        const recoResponse = await fetch("get_recommendations.php", {
          method: "POST",
          headers: {
            "Content-Type": "application/x-www-form-urlencoded",
          },
          body: `plat_id=${platId}`
        });
        
        const recoData = await recoResponse.json();
        
        if (recoData.success && recoData.recommendations.length > 0) {
          showRecommendations(recoData.recommendations);
        }
      } else {
        showMessage("❌ Erreur lors de l'ajout au panier", "error");
      }
    } catch (err) {
      showMessage("❌ Erreur de connexion", "error");
      }
  });
});

  // Fonction pour afficher les recommandations
  function showRecommendations(recommendations) {
  // Supprimer l'ancienne modal si elle existe
  const oldModal = document.getElementById('recommendation-modal');
  if (oldModal) oldModal.remove();
  
  // Créer la modal
  const modal = document.createElement('div');
  modal.id = 'recommendation-modal';
  modal.className = 'recommendation-modal';
  
  let recoHTML = `
    <div class="recommendation-content">
      <button class="recommendation-close" type="button">❌</button>
      <h3>Les clients ont aussi commandé :</h3>
      <div class="recommendation-items">
  `;
  
  recommendations.forEach(reco => {
    recoHTML += `
      <div class="recommendation-item">
        <div class="reco-info">
          <h4>${escapeHtml(reco.nom_plat)}</h4>
          <p class="reco-price">${parseFloat(reco.prix).toFixed(2)} €</p>
          <p class="reco-stat">${reco.percentage}% des clients l'ont choisi</p>
        </div>
        <button class="btn-add-reco" data-plat-id="${reco.plat_id}">
          🛒 Ajouter
        </button>
      </div>
    `;
  });
  
  recoHTML += `
      </div>
    </div>
  `;
  
  modal.innerHTML = recoHTML;
  document.body.appendChild(modal);
  
  // animation
  setTimeout(() => modal.classList.add('show'), 10);
  
  // Gestionnaire pour le bouton de fermeture
  const closeBtn = modal.querySelector('.recommendation-close');
  if (closeBtn) {
    closeBtn.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      closeRecommendations();
    });
  }
  
  // Gérer les clics sur "Ajouter"
  modal.querySelectorAll('.btn-add-reco').forEach(btn => {
    btn.addEventListener('click', async (e) => {
      const platId = e.target.dataset.platId;
      
      try {
        const response = await fetch("panier.php", {
          method: "POST",
          headers: {
            "Content-Type": "application/x-www-form-urlencoded",
          },
          body: `plat_id=${platId}&quantite=1`
        });
        
        if (response.ok) {
          e.target.textContent = '✓ Ajouté';
          e.target.disabled = true;
          e.target.style.background = '#4CAF50';
        }
      } catch (err) {
        showMessage("❌ Erreur", "error");
      }
    });
  });
  
  // Fermer en cliquant en dehors
  modal.addEventListener('click', (e) => {
    if (e.target === modal) {
      closeRecommendations();
    }
  });
  
  // Fermer avec la touche ESC (escape)
  const escapeHandler = (e) => {
    if (e.key === 'Escape') {
      closeRecommendations();
      document.removeEventListener('keydown', escapeHandler);
    }
  };
  document.addEventListener('keydown', escapeHandler);
}

  function closeRecommendations() {
    const modal = document.getElementById('recommendation-modal');
    if (modal) {
      modal.classList.remove('show');
      setTimeout(() => modal.remove(), 300);
    }
  }

  function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }

  //empecher soumission vide pour commentaires et réponses proprio
  const forms = document.querySelectorAll("form");
  forms.forEach(form => {
    form.addEventListener("submit", e => {
      const replyField = form.querySelector('input[name="reply_text"]');
      const commentField = form.querySelector('textarea[name="commentaire"]');

      if (replyField && replyField.value.trim() === "") {
        e.preventDefault();
        showMessage("⚠️ Tu dois écrire une réponse avant d'envoyer.", "error");
      }

      if (commentField && commentField.value.trim() === "") {
        e.preventDefault();
        showMessage("⚠️ Ton avis est vide.", "error");
      }
    });
  });
});

function showMessage(text, type = "success", buttonUrl = null, buttonText = null) {
  const oldMsg = document.querySelector(".flash-message");
  if (oldMsg) oldMsg.remove();

  const msg = document.createElement("div");
  msg.className = `flash-message ${type}`;
  
  if (buttonUrl && buttonText) {
    msg.innerHTML = `
      <div style="display: flex; align-items: center; justify-content: space-between; gap: 10px;">
        <span>${text}</span>
        <a href="${buttonUrl}" class="flash-btn">${buttonText}</a>
      </div>
    `;
  } else {
    msg.textContent = text;
  }
  
  document.body.appendChild(msg);

  setTimeout(() => {
    msg.classList.add("hide");
    setTimeout(() => msg.remove(), 400);
  }, 3000);
}

document.addEventListener("click", function(e) {
  if (e.target.classList.contains("edit-btn")) {
    const id = e.target.dataset.id;
    const text = e.target.dataset.comment;
    const form = document.getElementById(`edit-form-${id}`);
    const textarea = form.querySelector("textarea");

    form.style.display = "block";
    textarea.value = text;

    setTimeout(() => {
      textarea.style.height = 'auto';
      textarea.style.height = textarea.scrollHeight + 'px';
    }, 0);
  }
});
</script>

<style>
.container {
  backdrop-filter: blur(5px);
}

/* Filtres de menu */
.menu-filters {
  display: flex;
  flex-wrap: wrap;
  gap: 0.8rem;
  margin: 2rem 0;
  margin-top: 10px;
  justify-content: center;
  margin-bottom: 15px;
}

.filter-btn {
  padding: 0.7rem 1.2rem;
  border-radius: 25px;
  border: 2px solid rgba(255, 107, 107, 0.3);
  background: rgba(255, 255, 255, 0.2);
  backdrop-filter: blur(10px);
  color: #333;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  font-family: 'HSR', sans-serif;
}

.filter-btn:hover {
  transform: translateY(-2px);
  background: rgba(255, 107, 107, 0.2);
  border-color: #ff6b6b;
}

.filter-btn.active {
  background: linear-gradient(135deg, #ff6b6b, #ff8c42);
  color: white;
  border-color: #ff6b6b;
  box-shadow: 0 4px 15px rgba(255, 107, 107, 0.4);
}

/* Titres de catégories */
.category-title {
  font-size: 1.8rem;
  color: #ff6b6b;
  margin: 2rem 0 1rem 0;
  text-align: center;
  font-weight: 700;
  margin-top: 5px;
}

.flash-message {
  position: fixed;
  top: 20px;
  left: 50%;
  transform: translateX(-50%);
  background: rgba(255, 255, 255, 0.2);
  backdrop-filter: blur(10px);
  color: #727272ff;
  padding: 12px 20px;
  border-radius: 12px;
  font-family: 'HSR', sans-serif;
  box-shadow: 0 4px 20px rgba(0,0,0,0.3);
  animation: fadeIn 0.3s ease;
  z-index: 10000000;
}

.flash-message.success { border: 1px solid #7fff7f; }
.flash-message.error { border: 1px solid #ff6b6b; }
.flash-message.hide { opacity: 0; transform: translate(-50%, -10px); transition: all 0.4s; }

.flash-btn {
  padding: 0.4rem 0.8rem;
  background: rgba(255, 255, 255, 0.3);
  color: #333;
  border-radius: 8px;
  text-decoration: none;
  font-weight: 600;
  font-size: 0.9rem;
  transition: all 0.3s ease;
  white-space: nowrap;
  border: 1px solid rgba(255, 255, 255, 0.5);
}

.flash-btn:hover {
  background: rgba(255, 255, 255, 0.5);
  transform: scale(1.05);
  color: #41cf64ff;
}

@keyframes fadeIn {
  from { opacity: 0; transform: translate(-50%, -10px); }
  to { opacity: 1; transform: translate(-50%, 0); }
}

.container-desc {
  backdrop-filter: blur(12px);
  background: rgba(249, 249, 249, 0.81);
  max-width: 1000px;
  margin: 20px auto;
  padding: 30px;
  border-radius: 15px;
  box-shadow: 0 8px 25px var(--shadow);
}

.comment-card {
  margin-top: 15px;
  padding-top: 25px;
  position: relative;
  overflow-wrap: break-word;
  transition: all 0.3s ease;
}

.comment-card > div[style*="position:absolute"] {
  pointer-events: none;
}

.comment-card > div[style*="position:absolute"] button {
  pointer-events: auto; 
}

.comment-card p {
  margin-top: 10px;
  margin-bottom: 10px;
  line-height: 1.5;
  word-wrap: break-word;
  white-space: pre-line;
}

.comment-card:hover {
  transform: translateY(-3px);
  background: rgba(255, 255, 255, 0.31);
}

.comment-card strong,
.comment-card small {
  font-size: 1.1rem;
}

.comment-card .comment-meta {
  display: flex;
  align-items: left;
  gap: 8px;
  font-size: 1.15rem;
  font-weight: 600;
  text-align: center;
  color: #333;
  margin-top: -6px;
  margin-bottom: 5px;
}

.comment-card {
  position: relative; 
  z-index: 1;
}

.comment-card > div[style*="position:absolute"] {
  z-index: 2;
}

.container .resto-card:hover {
  background: rgba(255, 255, 255, 0.39);
  transition: all 0.3s ease;
}

.container .resto-card {
  transition: all 0.3s ease;
}

textarea[name="commentaire"] {
  resize: none;       
  overflow-y: hidden; 
  min-height: 80px;
}

form textarea[name="commentaire"]:focus{
  background: rgba(255, 255, 255, 0.35);
  transform: scale(1.02);
  transition: 0.3s all ease;
}

form label:focus{
  background: rgba(255, 255, 255, 0.35);
  transform: scale(1.02);
  transition: 0.3s all ease;
}

h4 {
  font-size: 1.25rem;
  margin-bottom: 19px;
}

button.btn-publish {
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
  margin-top: 7px;
}

button.btn-publish::after {
  content: "";
  position: absolute;
  top: 0; left: -100%;
  width: 100%;
  height: 100%;
  background: rgba(255,255,255,0.25);
  transition: all 0.4s ease;
  border-radius: 14px;
}

button.btn-publish:hover::after { 
  left: 0; 
}

button.btn-publish:hover { 
  transform: translateY(-4px) scale(1.03); 
  box-shadow: 0 12px 25px rgba(0,0,0,0.25); 
  background: linear-gradient(135deg, #ff8c42, #ff6b6b);
}

.vote-buttons {
  display: flex;
  gap: 10px;
  margin-top: 10px;
}

.vote-buttons button {
  background: rgba(255,255,255,0.04);
  border: 1px solid rgba(255,255,255,0.06);
  color: inherit;
  padding: 6px 10px;
  border-radius: 10px;
  cursor: pointer;
  font-weight: 600;
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.vote-buttons .count { font-weight: 700; margin-left: 4px; }
.vote-buttons button:hover { transform: translateY(-2px); }

.vote-buttons button.active {
  background-color: #4f9cff;
  color: #fff;
  border-color: #4f9cff;
}

.vote-buttons button.active.dislike {
  background-color: #ff6b6b;
  border-color: #ff6b6b;
}

/* Styles pour les images */

.comment-image {
  margin: 1rem 0;
  border-radius: 0.8rem;
  overflow: hidden;
  max-width: 100%;
}

.comment-image img {
  width: 100%;
  max-width: 500px;
  height: auto;
  border-radius: 0.8rem;
  cursor: pointer;
  transition: all 0.3s ease;
  box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
}

.comment-image img:hover {
  transform: scale(1.02);
  box-shadow: 0 6px 20px rgba(0, 0, 0, 0.3);
}

.file-upload {
  margin: 1rem 0;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.file-label {
  display: inline-flex;
  justify-content: center;
  align-items: center;
  padding: 0.9rem 1.8rem;
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
  position: relative;
  width: fit-content;
  margin-top: -10px;
  margin-bottom: -13px;
}

.file-label:hover {
  transform: translateY(-4px) scale(1.03);
  box-shadow: 0 12px 25px rgba(0,0,0,0.25);
  background: linear-gradient(135deg, #ff8c42, #ff6b6b);
}

.file-label::after {
  content: "";
  position: absolute;
  top: 0; left: -100%;
  width: 100%;
  height: 100%;
  background: rgba(255,255,255,0.25);
  transition: all 0.4s ease;
  border-radius: 14px;
}

.file-label:hover::after {
  left: 0;
}
.file-input {
  display: none;
}

.file-name {
  color: #666;
  font-size: 0.9rem;
  font-style: italic;
}

/* Modal pour agrandir l'image */
.modal {
  display: none;
  position: fixed;
  z-index: 9999;
  padding-top: 60px;
  left: 0;
  top: 0;
  width: 100%;
  height: 100%;
  overflow: auto;
  background-color: rgba(0,0,0,0.9);
  backdrop-filter: blur(10px);
}

.modal-content {
  margin: auto;
  display: block;
  width: 80%;
  max-width: 900px;
  border-radius: 0.8rem;
  animation: zoom 0.3s;
}

@keyframes zoom {
  from {transform: scale(0.7)}
  to {transform: scale(1)}
}

.close {
  position: absolute;
  top: 15px;
  right: 35px;
  color: #f1f1f1;
  font-size: 40px;
  font-weight: bold;
  transition: 0.3s;
  cursor: pointer;
}

.close:hover,
.close:focus {
  color: #bbb;
}

/* Images dans les commentaires */
.comment-image {
  margin: 1rem 0;
  border-radius: 0.8rem;
  overflow: hidden;
  max-width: 100%;
}

.comment-image img {
  width: 100%;
  max-width: fit-content;
  max-height: 200px;
  object-fit: contain;
  border-radius: 0.8rem;
  box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
  display: block;
  cursor: default;
  pointer-events: none;
}

/* Preview de l'image */
#image-preview-container {
  background: rgba(255, 255, 255, 0.78);
  padding: 1rem;
  border-radius: 10px;
  display: inline-block; 
  backdrop-filter: blur(25px);
  margin-top: 50px;
  border: 1px solid rgba(117, 117, 117, 0.3);
}

#image-preview {
  display: block;
  margin-bottom: 0.5rem;
  border: 1px solid rgba(255, 255, 255, 0.3);
}

.btn-remove-image {
  background: rgba(255, 77, 77, 0.8);
  color: white;
  border: none;
  padding: 0.5rem 1rem;
  border-radius: 8px;
  cursor: pointer;
  font-weight: 600;
  transition: all 0.3s ease;
  display: block;
  margin-top: 13px;
}

.btn-remove-image:hover {
  background: #ff4d4d;
  transform: scale(1.02);
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
  minHeight: 1000.00,
  minWidth: 300.00,
  scale: 1.00,
  scaleMobile: 1.00,
  color: 0xdba1b2,
  shininess: 25,
  waveHeight: 25,
  waveSpeed: 0.9,
  zoom: 0.9
})
</script>

<script>
// affiche le nom du fichier sélectionné
document.getElementById('image_avis')?.addEventListener('change', function(e) {
  const fileName = e.target.files[0]?.name || 'Aucun fichier choisi';
  document.getElementById('file-name').textContent = fileName;
});

// Script pour la gestion de limage
document.addEventListener('DOMContentLoaded', () => {
  const fileInput = document.getElementById('image_avis');
  const previewContainer = document.getElementById('image-preview-container');
  const preview = document.getElementById('image-preview');
  const removeBtn = document.getElementById('remove-image');
  const fileName = document.getElementById('file-name');

  if (fileInput) {
    fileInput.addEventListener('change', function(e) {
      const file = e.target.files[0];
      
      if (file) {
        //vérifier la taille (5MB max)
        if (file.size > 5242880) {
          showMessage('⚠️ L\'image est trop volumineuse (max 5MB)', 'error');
          fileInput.value = '';
          return;
        }

        //vérifier le type
        const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        if (!allowedTypes.includes(file.type)) {
          showMessage('⚠️ Format non supporté (jpg, png, gif, webp uniquement)', 'error');
          fileInput.value = '';
          return;
        }

        //     afficher l'aperçu
        const reader = new FileReader();
        reader.onload = function(e) {
          preview.src = e.target.result;
          previewContainer.style.display = 'block';
          fileName.textContent = file.name;
        };
        reader.readAsDataURL(file);
      }
    });

    //bouton pour supprimer
    removeBtn?.addEventListener('click', function() {
      fileInput.value = '';
      previewContainer.style.display = 'none';
      preview.src = '';
      fileName.textContent = '';
    });
  }
});
</script>
<script>
//ouvrir l'image en modal
function openImageModal(src) {
  const modal = document.getElementById('imageModal');
  const modalImg = document.getElementById('modalImage');
  modal.style.display = 'block';
  modalImg.src = src;
}

//fermer le modal
function closeImageModal() {
  document.getElementById('imageModal').style.display = 'none';
}

//fermer avec la touche Escape
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    closeImageModal();
  }
});
</script>

<script>
function showEditForm(id, oldText) {
  const form = document.getElementById(`edit-form-${id}`);
  if (form) {
    form.style.display = "block";
    form.querySelector("textarea").value = oldText;
  }
}

function hideEditForm(id) {
  const form = document.getElementById(`edit-form-${id}`);
  if (form) {
    form.style.display = "none";
  }
}
</script>

<script>
  // auto resize textarea (utilisé dans d'autres pages aussi)
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

<script src="assets/update_vote.js"></script>
</body>
</html>