<?php
// credits-remerciements.php
session_start();
require_once 'db/config.php';

$connected = isset($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Crédits - FoodHub</title>
    <link rel="stylesheet" href="assets/style.css">
    <?php if ($connected): ?>
        <?php include 'sidebar.php'; ?>
    <?php endif; ?>
</head>
<body>
    <?php if ($connected): ?>
        <audio id="player" autoplay loop>
            <source src="https://raw.githubusercontent.com/HDSachaNewLive/foodhub-assets/main/iiSU Overview.mp3" type="audio/mpeg">
        </audio>
        <?php include 'slider_son.php'; ?>
    <?php endif; ?>

    <div class="credits-overlay"></div>
    
    <div class="credits-container">
        <div class="credits-scroll" id="credits-scroll">
            
            <div class="credits-spacer"></div>
            
            <div class="credits-section logo-section">
                <h1 class="credits-logo">🍽️ FoodHub</h1>
                <p class="credits-subtitle">La plateforme qui simplifie la commande de repas en ligne</p>
            </div>

            <div class="credits-section">
                <h2 class="credits-title">Direction du Projet</h2>
                <p class="credits-role">Chef de Projet</p>
                <p class="credits-name">Mehdi Guerbas</p>
            </div>

            <div class="credits-section">
                <h2 class="credits-title">Développement</h2>
                <p class="credits-role">Développeur Principal</p>
                <p class="credits-name">Mehdi Guerbas</p>
                <p class="credits-role">Développeur Backend</p>
                <p class="credits-name">Mehdi Guerbas</p>
                <p class="credits-role">Développeur Frontend</p>
                <p class="credits-name">Mehdi Guerbas</p>
            </div>

            <div class="credits-section">
                <h2 class="credits-title">Design & Interface</h2>
                <p class="credits-role">Designer UI/UX</p>
                <p class="credits-name">Mehdi Guerbas</p>
                <p class="credits-role">Designer Graphique</p>
                <p class="credits-name">Mehdi Guerbas</p>
            </div>

            <div class="credits-section">
                <h2 class="credits-title">Base de Données</h2>
                <p class="credits-role">Architecte Base de Données</p>
                <p class="credits-name">Mehdi Guerbas</p>
                <p class="credits-role">Administrateur Base de Données</p>
                <p class="credits-name">M. Jallon</p>
            </div>

            <div class="credits-section">
                <h2 class="credits-title">Tests & Qualité</h2>
                <p class="credits-role">Responsable QA</p>
                <p class="credits-name">YanisCDN</p>
                <p class="credits-role">Testeur Principal</p>
                <p class="credits-name">YanisCDN & Mehdi Guerbas</p>
            </div>

            <div class="credits-section">
                <h2 class="credits-title">Documentation</h2>
                <p class="credits-role">Rédacteur Technique</p>
                <p class="credits-name">Mehdi Guerbas</p>
            </div>

            <div class="credits-section">
                <h2 class="credits-title">Technologies Utilisées</h2>
                <p class="credits-tech">PHP 8.3</p>
                <p class="credits-tech">MySQL / phpMyAdmin</p>
                <p class="credits-tech">JavaScript ES6+</p>
                <p class="credits-tech">HTML5 & CSS3</p>
                <p class="credits-tech">Three.js & Vanta.js</p>
                <p class="credits-tech">Leaflet.js</p>
                <p class="credits-tech">Chart.js</p>
            </div>

            <div class="credits-section">
                <h2 class="credits-title">Bibliothèques & Frameworks</h2>
                <p class="credits-tech">PDO (PHP Data Objects)</p>
                <p class="credits-tech">OpenStreetMap</p>
                <p class="credits-tech">Google reCAPTCHA</p>
            </div>

            <div class="credits-section">
                <h2 class="credits-title">Ressources Audio</h2>
                <p class="credits-tech">Nintendo eShop Music</p>
                <p class="credits-tech">HoYo-Mix Music</p>
                <p class="credits-tech">Animal Crossing OST</p>
                <p class="credits-tech">Blue Archive OST</p>
                <p class="credits-tech">Wuthering Waves OST</p>
                <p class="credits-tech">Neverness to Everness OST</p>
                <p class="credits-tech">Divers effets sonores</p>
            </div>

            <div class="credits-section">
                <h2 class="credits-title">Remerciements</h2>
                <p class="credits-thanks">N. Kannan (idées et suggestions) 🌸</p>
                <p class="credits-thanks">YanisCDN (Testeur Originel)</p>
                <p class="credits-thanks">Ey. OUDJEDI DAMERDJI (soutien/suggestions)</p>
                <p class="credits-thanks">J. FALEK (soutien/suggestions)</p>
                <p class="credits-thanks">Zachabian13</p>
                <p class="credits-thanks">M. Jallon (tests et feedback)</p>
                <p class="credits-thanks">Et tous ceux qui ont contribué au projet</p>
            </div>

            <div class="credits-section">
                <h2 class="credits-title">Bêta Testeurs</h2>
                <p class="credits-tester">Mehdi Guerbas</p>
                <p class="credits-tester">YanisCDN</p>
                <p class="credits-tester">M. Jallon</p>
            </div>

            <div class="credits-section">
                <h2 class="credits-title">Support & Maintenance</h2>
                <p class="credits-role">Administrateur Système</p>
                <p class="credits-name">Mehdi Guerbas</p>
            </div>

            <div class="credits-section final-section">
                <h2 class="credits-title-large">© 2025-2026 FoodHub</h2>
                <p class="credits-final">Merci d'avoir utilisé FoodHub !</p>
                <p class="credits-final">Développé avec beaucoup de requêtes SQL</p>
                <p class="credits-final">GLOIRE AU SQL !!!!!!</p>
                <p class="credits-final"> </p>
                <p class="credits-final"> </p>
                <p class="credits-final"> </p>
                <p class="credits-final"> </p>
                <p class="credits-final"> </p>
                <p class="credits-final"> </p>
                <p class="credits-final"> </p>
                <p class="credits-final"> </p>
                <p class="credits-final"> </p>
                <p class="credits-final"> </p>
                <p class="credits-final"> </p>
            </div>

            <div class="credits-controls">
                <button class="credits-btn" onclick="togglePause()">⏸️ Pause</button>
                <button class="credits-btn" onclick="replayCredits()">🔄 Rejouer</button>
                <a href="<?= $connected ? 'home.php' : 'index.php' ?>" class="credits-btn credits-btn-back">← Retour</a>
            </div>

            <div class="credits-spacer-end"></div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/three@0.149.0/build/three.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/vanta/dist/vanta.waves.min.js"></script>

    <script>
    // Gestion du défilement
    const creditsScroll = document.getElementById('credits-scroll');
    const audioPlayer = document.getElementById('player');
    let isPaused = false;
    let creditsDuration = 136; // durée par défaut

    function startCredits() {
        creditsScroll.style.animation = 'none';
        creditsScroll.offsetHeight; // Force reflow
        creditsScroll.style.animation = `scrollUp ${creditsDuration}s linear forwards`;
        if (audioPlayer) {
            audioPlayer.currentTime = 0;
            audioPlayer.play().catch(() => {
                // L'autoplay peut être bloqué
            });
        }
    }

    function replayCredits() {
        isPaused = false;
        updatePauseButton();
        startCredits();
    }

    function togglePause() {
        isPaused = !isPaused;
        creditsScroll.style.animationPlayState = isPaused ? 'paused' : 'running';
        if (audioPlayer) {
            if (isPaused) {
                audioPlayer.pause();
            } else {
                audioPlayer.play().catch(() => {});
            }
        }
        updatePauseButton();
    }

    function updatePauseButton() {
        const btn = document.querySelector('.credits-btn');
        btn.textContent = isPaused ? '▶️ Reprendre' : '⏸️ Pause';
    }

    // Démarrage automatique
    window.addEventListener('load', () => {
        // Récupérer la durée de l'audio si disponible
        if (audioPlayer) {
            if (audioPlayer.duration > 0) {
                creditsDuration = audioPlayer.duration;
            } else {
                audioPlayer.addEventListener('loadedmetadata', () => {
                    creditsDuration = audioPlayer.duration;
                });
            }
        }
        setTimeout(startCredits, 500);
    });

    // Synchroniser le redémarrage avec la fin de l'audio
    if (audioPlayer) {
        audioPlayer.addEventListener('ended', () => {
            if (!isPaused) {
                replayCredits();
            }
        });
    }

    // Événement de fin
    creditsScroll.addEventListener('animationend', () => {
        setTimeout(() => {
            if (confirm('Les crédits sont terminés. Revenir à l\'accueil ?')) {
                window.location.href = '<?= $connected ? "home.php" : "index.php" ?>';
            }
        }, 1000);
    });
    </script>

    <style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        margin: 0;
        padding: 0;
        overflow: hidden;
        font-family: 'HSR', sans-serif;
        background: #000;
        color: #fff;
        height: 100vh;
    }

    .credits-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: linear-gradient(
            180deg,
            rgba(0, 0, 0, 0.9) 0%,
            rgba(0, 0, 0, 0.3) 20%,
            rgba(0, 0, 0, 0.3) 80%,
            rgba(0, 0, 0, 0.9) 100%
        );
        pointer-events: none;
        z-index: 10;
        opacity: 0.5;
    }

    .credits-container {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        overflow: hidden;
        display: flex;
        align-items: flex-end;
        justify-content: center;
        z-index: 5;
    }

    .credits-scroll {
        width: 100%;
        max-width: 900px;
        text-align: center;
        padding: 0 2rem;
        position: absolute;
        top: -250px;
        animation-fill-mode: forwards;
    }

    @keyframes scrollUp {
        0% {
            transform: translateY(0px);
        }
        100% {
            transform: translateY(calc(-63% - 100vh));
        }
    }

    .credits-spacer {
        height: 50vh;
    }

    .credits-spacer-end {
        height: 100vh;
    }

    .credits-section {
        margin: 5rem 0;
        padding: 0;
    }

    .logo-section {
        margin: 8rem 0;
    }

    .credits-logo {
        font-size: 4.5rem;
        margin: 0 0 1.5rem 0;
        background: linear-gradient(135deg, #ff6b6b, #ffc342);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        font-weight: 700;
        text-shadow: 0 0 40px rgba(255, 107, 107, 0.6);
        letter-spacing: 2px;
    }

    .credits-subtitle {
        font-size: 1.4rem;
        color: rgba(255, 255, 255, 0.95);
        font-weight: 400;
        line-height: 1.6;
        text-shadow: 0 2px 10px rgba(0, 0, 0, 0.8);
    }

    .credits-title {
        font-size: 2rem;
        margin: 0 0 2.5rem 0;
        color: #ffc342;
        text-transform: uppercase;
        letter-spacing: 4px;
        font-weight: 600;
        text-shadow: 0 0 20px rgba(255, 195, 66, 0.6);
    }

    .credits-title-large {
        font-size: 3rem;
        margin: 0 0 2rem 0;
        color: #fff;
        letter-spacing: 3px;
        text-shadow: 0 0 30px rgba(255, 255, 255, 0.5);
    }

    .credits-role {
        font-size: 1.1rem;
        color: #ff8c8c;
        margin: 2rem 0 0.5rem 0;
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 2px;
        text-shadow: 0 2px 8px rgba(0, 0, 0, 0.8);
    }

    .credits-name {
        font-size: 1.5rem;
        color: rgba(255, 255, 255, 0.95);
        margin: 0.5rem 0 1.5rem 0;
        font-weight: 400;
        text-shadow: 0 2px 8px rgba(0, 0, 0, 0.8);
    }

    .credits-tech,
    .credits-thanks,
    .credits-tester {
        font-size: 1.2rem;
        color: rgba(255, 255, 255, 0.9);
        margin: 1.2rem 0;
        line-height: 1.8;
        font-weight: 400;
        text-shadow: 0 2px 8px rgba(0, 0, 0, 0.8);
    }

    .credits-thanks {
        font-size: 1.3rem;
    }

    .final-section {
        margin: 10rem 0;
    }

    .credits-final {
        font-size: 1.6rem;
        color: rgba(255, 255, 255, 0.95);
        margin: 2rem 0;
        font-weight: 400;
        text-shadow: 0 2px 10px rgba(0, 0, 0, 0.8);
    }

    .credits-controls {
        position: fixed;
        bottom: 2rem;
        left: 50%;
        transform: translateX(-50%);
        z-index: 200;
        display: flex;
        gap: 1rem;
        background: rgba(0, 0, 0, 0.9);
        backdrop-filter: blur(10px);
        padding: 1rem 1.5rem;
        border-radius: 50px;
        border: 1px solid rgba(255, 255, 255, 0.2);
    }

    .credits-btn {
        padding: 0.8rem 1.5rem;
        font-size: 1rem;
        font-weight: 600;
        color: #fff;
        background: linear-gradient(135deg, #ff6b6b, #ffc342);
        border: none;
        border-radius: 25px;
        cursor: pointer;
        text-decoration: none;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
        transition: all 0.3s ease;
        font-family: 'HSR', sans-serif;
        display: inline-block;
    }

    .credits-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(255, 107, 107, 0.5);
    }

    .credits-btn-back {
        background: linear-gradient(135deg, #6b9eff, #42c3ff);
    }

    /* Responsive */
    @media (max-width: 768px) {
        .credits-logo {
            font-size: 2.5rem;
        }

        .credits-subtitle {
            font-size: 1rem;
        }

        .credits-title {
            font-size: 1.5rem;
        }

        .credits-title-large {
            font-size: 2rem;
        }

        .credits-role {
            font-size: 0.9rem;
        }

        .credits-name {
            font-size: 1.2rem;
        }

        .credits-tech,
        .credits-thanks,
        .credits-tester {
            font-size: 1rem;
        }

        .credits-final {
            font-size: 1.2rem;
        }

        .credits-controls {
            flex-direction: column;
            gap: 0.5rem;
        }
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
window.vantaEffect = VANTA.WAVES({
  el: "#vanta-bg",
  mouseControls: true,
  touchControls: true,
  gyroControls: false,
  minHeight: 885.00,
  minWidth: 200.00,
  scale: 1.00,
  scaleMobile: 1.00,
  color: 0xf6b26b,
  shininess: 25,
  waveHeight: 25,
  waveSpeed: 0.7,
  zoom: 1
    });
});
</script>
<style>
body {
  background: none !important;
  overflow-x: clip;
}

canvas.vanta-canvas {
  position: absolute !important;
  top: 0;
  left: 0;
  width: fit-content;
  height: fit-content;
  z-index: 1 !important;
}
</style>
</body>
</html>