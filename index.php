<!-- index.php -->
<?php header(header: "Content-Type: text/html; charset=utf-8"); 
session_start();
$connected = isset($_SESSION['user_id']);
if ($connected):
  header("Location: home.php");
endif;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <audio autoplay loop> <source src="assets\04. Account Selection.flac" type="audio/mpeg"> </audio>
  <meta charset="utf-8">
  <title>FoodHub - Accueil</title>
  <link rel="stylesheet" href="assets/style.css">
</head>

<body>
<div class="page-wrapper">
  <div class="page flip_in" id="current-page">
  
  <main class="hero-container">
  <div class="hero-text">
    <h1>MiamExpress 🍽️</h1>
    <p class="subtitle">Commandez d'où vous voulez, chez qui, et quand vous voulez.<br>Commandez facilement depuis plusieurs restaurants près de chez vous</p>
    <!-- boutons qui redirigent vers autres pages -->
    <div class="links">
      <a href="register.php" class="btn btn-main">S'inscrire</a>
      <a href="login.php" class="btn btn-main">Se connecter</a>
      <a href="restaurants.php" class="btn btn-alt" data-no-ajax>Voir les restaurants</a>
    </div>
  </div>
</main>
  <div class="contact-info" style="margin-top: 0px;top:0px; text-align: center;">
    <p>Besoin d'aide ? <a href="contact_admin.php"><button class="btn-contact">Contactez l'administrateur</button> <a>
      
    </a>
  </a></p>
  </div>
<style>

#bg-video {
  position: fixed;
  right: 0;
  bottom: 0;
  min-width: 100%;
  min-height: 100%;
  width: auto;
  height: auto;
  z-index: -1; 
  object-fit: cover; 
  filter: brightness(90%); 
}
.links {
  display: flex;
  flex-direction: column; 
  align-items: center;    
  gap: 15px;              
  margin-top: 30px;
}

.links .btn {
  min-width: 200px;      
  text-align: center;
}

.hero-container {
  position: center;
  height: relative;
  display: flex;
  justify-content: center;
  align-items: center;
  text-align: center;
  color: #353535dd;
  font-family: 'HSR', sans-serif;
  max-width: 1000px;
  margin: 80px auto;
  backdrop-filter: blur(12px);
  background: rgba(255, 255, 255, 0.29); 
  padding: 90px;
  margin-bottom: 5px;
  border-radius: 15px;
  box-shadow: 0 8px 25px var(--shadow);
}

.hero-text h1 {
  font-size: 4rem;
  margin-bottom: 20px;
  color: #ff6b6b;
  animation: fadeScale 1.5s ease forwards;
}

.hero-text .subtitle {
  font-size: 1.5rem;
  margin-bottom: 40px;
  animation: fadeScale 1.8s ease forwards;
}

.links a {
  display: inline-block;
  min-width: 200px;
  padding: 18px 34px;
  border-radius: 28px;
  text-align: center;
  text-decoration: none;
  font-weight: 600;
  font-size: 1.25rem;
  color: #004b74;
  background: rgba(255, 255, 255, 0.2);
  border: 2px solid rgba(255, 255, 255, 0.5);
  box-shadow:
    inset 0 2px 4px rgba(255,255,255,0.4),
    0 6px 15px rgba(0, 80, 140, 0.2);
  backdrop-filter: blur(14px);
  transition: all 0.3s ease;
  position: relative;
  overflow: hidden;
  cursor: pointer;
}

/* Reflet glossy du haut */
.links a::before {
  content: "";
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 50%;
  border-radius: 28px 28px 0 0;
  background: linear-gradient(180deg, rgba(255,255,255,0.9) 0%, rgba(255,255,255,0.05) 100%);
  opacity: 0.7;
  transition: opacity 0.25s ease;
}

/* Glow interne doux */
.links a::after {
  content: "";
  position: absolute;
  inset: 0;
  border-radius: 28px;
  box-shadow: inset 0 0 10px rgba(0,170,255,0.25);
  pointer-events: none;
  transition: box-shadow 0.25s ease;
}

/* liens effet Wii U bleu brillant --- */
.links a:hover {
  color: #fff;
  background: linear-gradient(180deg, rgba(0,180,255,0.85) 0%, rgba(0,120,220,0.9) 100%);
  border: 2px solid rgba(255,255,255,0.9);
  box-shadow:
    inset 0 3px 6px rgba(255,255,255,0.8),
    0 8px 20px rgba(0,180,255,0.7);
  transform: scale(1.05);
}

.links a:hover::before {
  opacity: 1;
}

.links a:hover::after {
  box-shadow: inset 0 0 18px rgba(255,255,255,0.6);
}

/* --- Effet de clic --- */
.links a:active {
  transform: scale(0.97);
  background: linear-gradient(180deg, rgba(0,150,255,0.9) 0%, rgba(0,100,180,0.95) 100%);
  box-shadow:
    inset 0 4px 12px rgba(0,70,130,0.5),
    inset 0 -2px 4px rgba(255,255,255,0.4);
}

@keyframes fadeScale {
  0% {opacity: 0; transform: scale(0.8);}
  100% {opacity: 1; transform: scale(1);}
}
p .btn-contact {
    padding: 0.5rem 1rem;
    border: none;
    border-radius: 0.6rem;
    cursor: pointer;
    font-weight: 600;
    font-size: 0.85rem;
    transition: all 0.3s ease;
    font-family: 'HSR', sans-serif;
    margin-bottom: -60px;
    margin-top: 0px;
    animation: fadeScale 1.8s ease forwards;
}

p .btn-contact {
    background: rgba(255, 152, 0, 0.3);
    color: #f08011ff;
}

p .btn-contact:hover {
    background: rgba(255, 152, 0, 0.5);
    transform: scale(1.03);
}
</style>
  </div>
</div>
<script src="assets/3d-flip.js"></script>

<script>
  // son au survol
  const hoverSound = new Audio('assets/00240 - WAV_240_GUESS_BANK_MEN.wav');

  // son au clic
  const clickSound = new Audio('assets/00259 - WAV_259_GUESS_BANK_MEN.wav');

  const buttons = document.querySelectorAll('.links a');

  buttons.forEach(btn => {
    // survol
    btn.addEventListener('mouseenter', () => {
      hoverSound.currentTime = 0;
      hoverSound.play();
    });

    // click
    btn.addEventListener('click', () => {
      clickSound.currentTime = 0;
      clickSound.play();
    });
  });
</script>

</body>
<video autoplay muted loop id="bg-video">
  <source src="assets/fond WiiU.webm" type="video/webm">Ton navigateur ne supporte pas la vidéo de fond</video>
</html>