<?php
// mes_favoris.php
session_start();
require_once 'db/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = (int)$_SESSION['user_id'];

// Récupérer les favoris
$stmt = $conn->prepare("
    SELECT r.*, 
           AVG(a.note) as note_moyenne,
           COUNT(DISTINCT a.avis_id) as nb_avis,
           f.date_ajout
    FROM favoris f
    JOIN restaurants r ON f.restaurant_id = r.restaurant_id
    LEFT JOIN avis a ON r.restaurant_id = a.restaurant_id
    WHERE f.user_id = ?
    GROUP BY r.restaurant_id
    ORDER BY f.date_ajout DESC
");
$stmt->execute([$user_id]);
$favoris = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Mes Favoris - FoodHub</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="restaurants.css">
    <?php include 'sidebar.php'; ?>
</head>
<body>
    <audio id="player" autoplay loop>
        <source src="assets/Blue Archive OST 25 - Future Bossa.mp3" type="audio/mpeg">
    </audio>
    <?php include 'slider_son.php'; ?>
  <style>
    #volume-slider {
    background: linear-gradient(135deg, #54dc5de1, #7ff687ff); }
    #volume-button {
    background: linear-gradient(135deg, #40db5fff, #93f2aaff);
    }
  </style>
    <main class="container" style="margin-top: 120px;">
        <h1 style="color: #ff6b6b; text-align: center; margin-bottom: 2rem;">❤️ Mes restaurants favoris</h1>

        <?php if (empty($favoris)): ?>
            <div class="empty-state">
                <p style="text-align: center; font-size: 1.2rem; color: #666;">
                    Tu n'as pas encore de favoris 😢
                </p>
                <div style="text-align: center; margin-top: 2rem;">
                    <a href="restaurants.php" class="btn-glass">🔍 Découvrir les restaurants</a>
                </div>
            </div>
        <?php else: ?>
            <div class="resto-list">
                <?php foreach ($favoris as $resto): ?>
                    <div class="resto-card">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                            <h3><?= htmlspecialchars($resto['nom_restaurant']) ?></h3>
                            <button class="btn-favorite active" 
                                    data-restaurant-id="<?= $resto['restaurant_id'] ?>"
                                    title="Retirer des favoris">
                                <span class="favorite-icon">❤️</span>
                            </button>
                        </div>
                        
                        <?php if ($resto['note_moyenne']): ?>
                            <div class="rating">
                                <?php 
                                $note = round($resto['note_moyenne']);
                                for ($i = 1; $i <= 5; $i++): 
                                ?>
                                    <span class="star <?= $i <= $note ? 'filled' : '' ?>">★</span>
                                <?php endfor; ?>
                                <span class="rating-text">
                                    <?= number_format($resto['note_moyenne'], 1) ?> 
                                    (<?= $resto['nb_avis'] ?> avis)
                                </span>
                            </div>
                        <?php endif; ?>

                        <p><strong>📍</strong> <?= htmlspecialchars($resto['adresse'] ?? 'Non renseignée') ?></p>
                        <p><strong>🍽️</strong> <?= htmlspecialchars($resto['categorie'] ?? 'Restauration') ?></p>
                        
                        <?php if ($resto['description_resto']): ?>
                            <p><?= htmlspecialchars(substr($resto['description_resto'], 0, 100)) ?>...</p>
                        <?php endif; ?>

                        <p style="font-size: 0.85rem; color: #888; margin-top: 1rem;">
                            ⭐ Ajouté le <?= date('d/m/Y', strtotime($resto['date_ajout'])) ?>
                        </p>

                        <a class="btn" href="menu.php?restaurant_id=<?= $resto['restaurant_id'] ?>">
                            Voir le menu
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <p><a href="restaurants.php" class="back-link" style="margin-bottom: -10px;">← Retour aux restaurants</a></p>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/three@0.149.0/build/three.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/vanta/dist/vanta.waves.min.js"></script>

<script>
    // Gestion des favoris
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('.btn-favorite').forEach(btn => {
            btn.addEventListener('click', async (e) => {
                e.preventDefault();
                e.stopPropagation();
                
                const restaurantId = btn.dataset.restaurantId;
                
                try {
                    const response = await fetch('toggle_favori.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: `restaurant_id=${restaurantId}`
                    });
                    
                    const data = await response.json();
                    
                    if (data.success && data.action === 'removed') {
                        // Retirer visuellement
                        btn.closest('.resto-card').style.animation = 'fadeOut 0.3s ease';
                        setTimeout(() => {
                            btn.closest('.resto-card').remove();
                            
                            // Vérifier s'il reste des favoris
                            if (document.querySelectorAll('.resto-card').length === 0) {
                                location.reload();
                            }
                        }, 300);
                    }
                } catch (err) {
                    console.error(err);
                }
            });
        });
    });
    </script>

    <style>
    .container {
        backdrop-filter: blur(15px);
        background: rgba(255, 255, 255, 0.25);
        padding: 2rem;
        border-radius: 1.5rem;
        max-width: 1200px;
        margin: 120px auto;
    }

    .btn-favorite {
        background: rgba(240, 168, 168, 0.33);
        border: none;
        cursor: pointer;
        font-size: 1.5rem;
        transition: transform 0.2s ease;
        padding: 0.5rem;
        margin-right: -10px;
        margin-top: -10px;
    }

    .btn-favorite:hover {
        transform: scale(1.2);
    }

    @keyframes fadeOut {
        to { opacity: 0; transform: scale(0.9); }
    }

    .btn-glass {
        font-family: 'HSR', sans-serif;
        font-size: 1.2rem;
        padding: 12px 24px;
        backdrop-filter: blur(15px);
        background: rgba(255, 107, 107, 0.4);
        color: white;
        border: none;
        border-radius: 12px;
        box-shadow: 0 6px 20px rgba(0,0,0,0.2);
        cursor: pointer;
        transition: all 0.3s ease;
        text-decoration: none;
        display: inline-block;
    }

    .btn-glass:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        background: rgba(255, 107, 107, 0.6);
        color: white;
    }

    .rating {
        margin: 0.5rem 0;
    }

    .star {
        color: #ddd;
        font-size: 1.2rem;
    }

    .star.filled {
        color: #ffd700;
    }

    .rating-text {
        margin-left: 0.5rem;
        color: #666;
        font-size: 0.9rem;
    }
        .resto-card {
        background: rgba(255, 255, 255, 0.2);
        backdrop-filter: blur(15px);
        padding: 1.5rem;
        border-radius: 1rem;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        border: 1px solid rgba(255, 255, 255, 0.3);
        min-width: 280px;
        transition: all 0.3s ease;
        color: #333;
        position: relative;
        overflow: hidden;
    }

    .resto-card::before {
        content: "";
        position: absolute;
        top: -50%;
        left: -50%;
        width: 200%;
        height: 200%;
        background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
        transform: rotate(45deg);
        pointer-events: none;
        transition: all 0.5s ease;
    }

    .resto-card:hover {
        transform: translateY(-5px) scale(1.02);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
        background: rgba(255, 255, 255, 0.25);
        border-color: rgba(255, 255, 255, 0.4);
    }

    .resto-card:hover::before {
        top: -20%;
        left: -20%;
    }

    .resto-card h3 {
        margin-top: 0;
        color: #333;
        font-size: 1.3rem;
        font-weight: 600;
        margin-bottom: 0.8rem;
        text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.1);
    }

    .resto-card p {
        margin: 0.5rem 0;
        color: #555;
        font-size: 0.95rem;
        line-height: 1.4;
    }

    .resto-card p strong {
        color: #ff6b6b;
        font-weight: 600;
    }
    .resto-list {
        display: flex;
        flex-wrap: wrap;
        gap: 1.5rem;
        justify-content: center;
    }
    </style>

<script>
//fixer hauteur du body à la hauteur de la fenêtre
document.addEventListener('DOMContentLoaded', () => {
  //créer conteneur fixe pour Vanta en arrière-plan
  const vantaBg = document.createElement('div');
  vantaBg.id = 'vanta-bg';
  vantaBg.style.cssText = `
    position: fixed;
    top: 0;
    left: 0;
    width: 110vw;
    height: 130vh;
    z-index: 2;
    pointer-events: none;
  `;
  document.body.insertBefore(vantaBg, document.body.firstChild);
VANTA.WAVES({
  el: "#vanta-bg",
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
  zoom: 0.9
    });
});
</script>
</body>
</html>