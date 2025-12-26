<?php
// apropos.php
session_start();
require_once 'db/config.php';

$message = ''; // ne servent à rien car en fait on peut pas envoyer de mail à cause de WAMP, et il n'y a aucun bouton qui causerait d'erreurs
$error = ''; // pas de POST dans cette page donc pas d'erreur a verifier (inutile)

?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <title>À propos - FoodHub</title>
  <link rel="stylesheet" href="assets/style.css">
  <link rel="stylesheet" href="assets/apropos.css">
  <?php include 'sidebar.php'; ?>
</head>
<body>
  <audio id="player" autoplay loop> <source src="assets\Nintendo 3DS Internet Settings Theme (High Quality, 2022 Remastered).mp3" type="audio/mpeg"> </audio>
  <?php include "slider_son.php"; ?>
  <main class="container">
    <h1>À propos de FoodHub</h1>
    
    <!--section a propos -->
    <div class="apropos-section">
      <div class="section-content">
        <h2>Notre Mission</h2>
        <div class="mission-text">
          <p><strong>FoodHub</strong>, c'est une plateforme qui simplifie la commande de repas en ligne.</p>
          <p>Mon objectif : connecter les utilisateurs avec les meilleurs restaurants autour d'eux, tout en offrant une expérience fluide, rapide et agréable.</p>
          <p>Ce projet/site a été conçu dans le cadre d'un  projet pour mon <strong>BTS SIO</strong> pour démontrer l'intégration de plusieurs technologies web (PHP, MySQL, JavaScript, CSS, HTML).</p>
        </div>
        
        <div class="developer-info">
          <h3>Développeur</h3>
          <p>Projet réalisé par <strong>Mehdi</strong>, étudiant en BTS SIO.</p>
          <p>Mentions/Remerciements :
            <ul>
              <li><strong>M. Jallon (A.K.A Jallon Le GOAT)</strong></li>
              <li><strong>M. Bourdon (sudo rm -rf /*)</strong></li>
              <li><strong>Navine KANNAN (pour les idées proposées)</strong></li>
            </ul>
          </p>
          <p>📧 <a href="mailto:mehdiguerbas5@gmail.com" class="contact-link">mehdiguerbas5@gmail.com</a></p>
        </div>
        <div class="tester-info">
          <h3>Testeur(s)</h3>
          <p>Tests réalisés par:</p>
          <ul>
              <li><strong>M. Jallon (A.K.A Jallon Le GOAT)</strong></li>
              <li><strong>M. Bourdon (sudo rm -rf /*)</strong></li>
              
          </ul>
          <p>Mentions/Remerciements :
            <p><strong>Yanis Abdelkaoui (A.K.A YanisCDN, Le Testeur Originel)</strong></p>
          </p>
        </div>
      </div>
    </div>

    <!--FAQ -->
    <div class="faq-section">
      <div class="section-content">
        <h2>❓ Foire Aux Questions FAQ</h2>
        
        <div class="faq-container">
          <div class="faq-item">
            <h3>Comment passer une commande ?</h3>
            <p>Connectez-vous/Créez un compte, allez sur la page d'un restaurant, ajoutez vos plats au panier, validez la commande et attendez la confirmation.</p>
          </div>
          
          <div class="faq-item">
            <h3>Puis-je commander dans plusieurs restaurants à la fois ?</h3>
            <p>Oui, FoodHub permet de combiner plusieurs restaurants dans une seule commande. Tant que vous ne passez pas commande, votre panier sera sauvegardé.</p>
          </div>
          
          <div class="faq-item">
            <h3>Comment laisser un avis ?</h3>
            <p>Rendez-vous sur la page du restaurant sur lequel vous souhaitez ajouter un avis, descendez jusqu'à la section "Avis", rédigez votre avis, choisissez une note et cliquez sur "Publier".
            <ul>
                <li>Note: Vous devez être connecté(e) pour publier un avis.</li>
            </ul>
            </p>
          </div>
          
          <div class="faq-item">
            <h3>Les paiements sont-ils sécurisés ?</h3>
            <p>Les paiements sont simulés pour le moment dans le cadre du projet.</p>
          </div>
          
          <div class="faq-item">
            <h3>Comment devenir propriétaire de restaurant ?/Comment ajouter un restaurant ?</h3>
            <p>Créez un compte en tant que "Propriétaire" et ajoutez votre restaurant via votre profil ou la page d'acceuil.
                <ul>
                    <li>Note: Vous devez être connecté(e) pour ajouter un restaurant.</li>
                    <li>Attention: Une fois votre restaurant ajouté, il ne sera pas immédiatement visible, il faudra attendre la vérification du propriétaire par l'administrateur du site.</li>
                    <li>Note: Une fois votre compte crée, vous ne pouvez plus changer de type de compte.</li>
                </ul>
            </p>
          </div>
          
          <div class="faq-item">
            <h3>Puis-je annuler ma commande ?</h3>
            <p>Oui, vous pouvez annuler votre commande tant qu'elle n'est pas en cours de livraison.</p>
          </div>
        </div>
      </div>
    </div>

    <p><a style="margin-top: 0px;" href="<?= isset($_SESSION['user_id']) ? 'home.php' : 'index.php' ?>" class="back-link">⬅ Retour</a></p>
  </main>

  <!-- Scripts du fond3D ma gueule -->
  <script src="https://cdn.jsdelivr.net/npm/three@0.149.0/build/three.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/vanta/dist/vanta.waves.min.js"></script>

<script>
  VANTA.WAVES({
  el: "body",
  mouseControls: true,
  touchControls: true,
  minHeight: 200.00,
  minWidth: 200.00,
  scale: 1.00,
  scaleMobile: 1.00,
  color: 0xdba1b2,
})
</script>

<style>
 /* dans /assets/apropos.css*/
  </style>
</body>
</html>