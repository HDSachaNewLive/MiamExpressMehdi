<?php
// tos.php - Conditions de Service
session_start();
require_once 'db/config.php';
$connected = isset($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <title>Conditions de Service - FoodHub</title>
  <link rel="stylesheet" href="assets/style.css">
  <link rel="stylesheet" href="assets/apropos.css">
  <?php include 'sidebar.php'; ?>
</head>
<body>
  <audio id="player" autoplay loop>
    <source src="assets/Nintendo 3DS Internet Settings Theme (High Quality, 2022 Remastered).mp3" type="audio/mpeg">
  </audio>
  <?php include "slider_son.php"; ?>
  
  <main class="container">
    <h1>📜 Conditions de Service</h1>
    
    <div class="tos-intro">
      <p class="tos-date"><strong>Dernière mise à jour :</strong> 3 décembre 2025</p>
      <p>Bienvenue sur <strong>FoodHub</strong>. En utilisant ce site, vous acceptez les conditions de service suivantes. Veuillez les lire attentivement.</p>
    <?php if (!isset($_SESSION['user_id'])): ?>
      <div style="margin-top: 2rem; display: flex; gap: 1.5rem; flex-wrap: wrap; justify-content: center; align-items: center;">
      <a href="login.php" class="btn btn-glass">Connexion</a>
      <a href="register.php" class="btn btn-glass">Inscription</a>
      <?php endif; ?>
      </div>
    </div>

    <!-- Section 1 -->
    <div class="apropos-section">
      <div class="section-content">
        <h2>1. Objet du Site</h2>
        <div class="mission-text">
          <p><strong>FoodHub</strong> est une plateforme de commande de repas en ligne développée dans le cadre d'un projet pédagogique pour le BTS SIO.</p>
          <p>Ce site permet de :</p>
          <ul>
            <li>Commander des plats auprès de restaurants partenaires</li>
            <li>Gérer un profil utilisateur (client ou propriétaire)</li>
            <li>Ajouter et gérer des restaurants (propriétaires)</li>
            <li>Laisser des avis et participer à la communauté</li>
          </ul>
          <p class="warning-text">⚠️ <strong>Important :</strong> Les paiements sont entièrement simulés. Aucune transaction financière réelle n'est effectuée sur ce site.</p>
        </div>
      </div>
    </div>

    <!-- Section 2 -->
    <div class="apropos-section">
      <div class="section-content">
        <h2>2. Inscription et Compte Utilisateur</h2>
        <div class="mission-text">
          <p><strong>2.1 Création de compte</strong></p>
          <p>Pour utiliser certaines fonctionnalités, vous devez créer un compte en fournissant des informations exactes et à jour.</p>
          
          <p><strong>2.2 Types de comptes</strong></p>
          <ul>
            <li><strong>Client :</strong> Peut commander des plats et laisser des avis</li>
            <li><strong>Propriétaire :</strong> Peut ajouter et gérer des restaurants</li>
          </ul>
          
          <p><strong>2.3 Sécurité du compte</strong></p>
          <p>Vous êtes responsable de la confidentialité de vos identifiants. Ne partagez jamais votre mot de passe.</p>
          
          <p><strong>2.4 Suppression de compte</strong></p>
          <p>Vous pouvez supprimer votre compte à tout moment depuis votre profil. Cette action est <strong>irréversible</strong> et entraînera la suppression de toutes vos données.</p>
        </div>
      </div>
    </div>

    <!-- Section 3 -->
    <div class="apropos-section">
      <div class="section-content">
        <h2>3. Utilisation du Site</h2>
        <div class="mission-text">
          <p><strong>3.1 Règles d'utilisation</strong></p>
          <p>Vous vous engagez à :</p>
          <ul>
            <li>Utiliser le site de manière légale et respectueuse</li>
            <li>Ne pas publier de contenu offensant, discriminatoire ou illégal</li>
            <li>Ne pas tenter de pirater ou d'endommager le site</li>
            <li>Respecter les autres utilisateurs et propriétaires</li>
          </ul>
          
          <p><strong>3.2 Contenu utilisateur</strong></p>
          <p>Les avis, commentaires et autres contenus que vous publiez restent votre propriété, mais vous accordez à FoodHub le droit de les afficher et de les modérer.</p>
          
          <p><strong>3.3 Modération</strong></p>
          <p>FoodHub se réserve le droit de supprimer tout contenu inapproprié sans préavis.</p>
        </div>
      </div>
    </div>

    <!-- Section 4 -->
    <div class="apropos-section">
      <div class="section-content">
        <h2>4. Commandes et Paiements</h2>
        <div class="mission-text">
          <p><strong>4.1 Nature des transactions</strong></p>
          <p class="warning-text">⚠️ <strong>IMPORTANT :</strong> Toutes les commandes et paiements sur FoodHub sont <strong>entièrement simulés</strong>. Aucun argent réel n'est échangé.</p>
          
          <p><strong>4.2 Processus de commande</strong></p>
          <ol>
            <li>Ajout de plats au panier</li>
            <li>Validation de la commande</li>
            <li>Simulation du paiement</li>
            <li>Suivi de la commande (simulation)</li>
          </ol>
          
          <p><strong>4.3 Codes de réduction</strong></p>
          <p>Des codes promotionnels peuvent être disponibles. Ils sont valables selon les conditions spécifiées dans les annonces.</p>
          
          <p><strong>4.4 Annulation</strong></p>
          <p>Vous pouvez annuler une commande tant qu'elle n'est pas en cours de livraison (simulation).</p>
        </div>
      </div>
    </div>

    <!-- Section 5 -->
    <div class="apropos-section">
      <div class="section-content">
        <h2>5. Propriétaires de Restaurants</h2>
        <div class="mission-text">
          <p><strong>5.1 Ajout de restaurants</strong></p>
          <p>Les propriétaires peuvent ajouter des restaurants après création d'un compte propriétaire.</p>
          
          <p><strong>5.2 Validation</strong></p>
          <p>Tous les restaurants doivent être validés par l'administrateur avant d'être visibles publiquement.</p>
          
          <p><strong>5.3 Gestion</strong></p>
          <p>Les propriétaires sont responsables de :</p>
          <ul>
            <li>La véracité des informations du restaurant</li>
            <li>La gestion du menu et des prix</li>
            <li>La réponse aux avis clients</li>
          </ul>
        </div>
      </div>
    </div>

    <!-- Section 6 -->
    <div class="apropos-section">
      <div class="section-content">
        <h2>6. Propriété Intellectuelle</h2>
        <div class="mission-text">
          <p><strong>6.1 Droits sur le site</strong></p>
          <p>Le design, le code source et les fonctionnalités de FoodHub sont protégés par les droits d'auteur.</p>
          
          <p><strong>6.2 Contenus tiers</strong></p>
          <p>Les logos, noms et marques des restaurants appartiennent à leurs propriétaires respectifs.</p>
          
          <p><strong>6.3 Médias</strong></p>
          <p>Les musiques et ressources utilisées proviennent de sources libres de droits ou sont utilisées à des fins pédagogiques.</p>
        </div>
      </div>
    </div>

    <!-- Section 7 -->
    <div class="apropos-section">
      <div class="section-content">
        <h2>7. Protection des Données</h2>
        <div class="mission-text">
          <p><strong>7.1 Collecte de données</strong></p>
          <p>Nous collectons les informations suivantes :</p>
          <ul>
            <li>Nom, email, téléphone</li>
            <li>Adresse de livraison</li>
            <li>Historique de commandes (simulé)</li>
            <li>Avis et commentaires</li>
          </ul>
          
          <p><strong>7.2 Utilisation des données</strong></p>
          <p>Vos données sont utilisées uniquement pour :</p>
          <ul>
            <li>Le fonctionnement du site</li>
            <li>L'amélioration de l'expérience utilisateur</li>
            <li>La gestion de votre compte</li>
          </ul>
          
          <p><strong>7.3 Sécurité</strong></p>
          <p>Vos mots de passe sont chiffrés. Nous mettons en œuvre des mesures de sécurité pour protéger vos données.</p>
          
          <p><strong>7.4 Cookies</strong></p>
          <p>Le site utilise des cookies de session pour maintenir votre connexion.</p>
        </div>
      </div>
    </div>

    <!-- Section 8 -->
    <div class="apropos-section">
      <div class="section-content">
        <h2>8. Limitation de Responsabilité</h2>
        <div class="mission-text">
          <p><strong>8.1 Nature du projet</strong></p>
          <p>FoodHub est un projet pédagogique. Le site est fourni "tel quel" sans garantie d'aucune sorte.</p>
          
          <p><strong>8.2 Disponibilité</strong></p>
          <p>Nous ne garantissons pas que le site sera disponible en permanence et sans interruption.</p>
          
          <p><strong>8.3 Contenu utilisateur</strong></p>
          <p>Nous ne sommes pas responsables du contenu publié par les utilisateurs, mais nous nous réservons le droit de le modérer.</p>
        </div>
      </div>
    </div>

    <!-- Section 9 -->
    <div class="apropos-section">
      <div class="section-content">
        <h2>9. Sanctions et Suspensions</h2>
        <div class="mission-text">
          <p><strong>9.1 Violations</strong></p>
          <p>En cas de violation des conditions de service, nous pouvons :</p>
          <ul>
            <li>Supprimer le contenu inapproprié</li>
            <li>Suspendre temporairement votre compte</li>
            <li>Supprimer définitivement votre compte</li>
          </ul>
          
          <p><strong>9.2 Motifs de sanction</strong></p>
          <ul>
            <li>Spam ou publicité non autorisée</li>
            <li>Propos offensants, racistes ou discriminatoires</li>
            <li>Tentative de piratage ou d'accès non autorisé</li>
            <li>Usurpation d'identité</li>
            <li>Publication de fausses informations</li>
          </ul>
        </div>
      </div>
    </div>

    <!-- Section 10 -->
    <div class="apropos-section">
      <div class="section-content">
        <h2>10. Modifications des Conditions</h2>
        <div class="mission-text">
          <p>FoodHub se réserve le droit de modifier ces conditions de service à tout moment.</p>
          <p>Les modifications seront effectives dès leur publication sur cette page.</p>
          <p>La date de dernière mise à jour sera indiquée en haut de la page.</p>
          <p>Il est de votre responsabilité de consulter régulièrement ces conditions.</p>
        </div>
      </div>
    </div>

    <!-- Section 11 -->
    <div class="apropos-section">
      <div class="section-content">
        <h2>11. Contact</h2>
        <div class="developer-info">
          <p>Pour toute question concernant ces conditions de service :</p>
          <p>📧 <strong>Email :</strong> <a href="mailto:mehdiguerbas5@gmail.com">mehdiguerbas5@gmail.com</a></p>
          <p>🏫 <strong>Établissement :</strong> BTS SIO</p>
          <p>👨‍💻 <strong>Développeur :</strong> Mehdi GUERBAS</p>
        </div>
      </div>
    </div>

    <!-- Section Finale -->
    <div class="apropos-section final-section">
      <div class="section-content">
        <h2>12. Acceptation des Conditions</h2>
        <div class="mission-text acceptance-box">
          <p>✅ En utilisant FoodHub, vous reconnaissez avoir lu, compris et accepté l'intégralité de ces conditions de service.</p>
          <p>❌ Si vous n'acceptez pas ces conditions, veuillez ne pas utiliser le site.</p>
        </div>
      </div>
    </div>

    <p><a style="margin-top: 0px;" href="<?= $connected ? 'home.php' : 'index.php' ?>" class="back-link">← Retour</a></p>
  </main>

  <!-- Scripts 3D -->
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
    .tos-intro {
      background: rgba(255, 235, 205, 0.3);
      padding: 1.5rem;
      border-radius: 1rem;
      margin-bottom: 2rem;
      border-left: 4px solid #ff6b6b;
    }

    .tos-date {
      color: #ff6b6b;
      font-weight: 700;
      margin-bottom: 1rem;
    }

    .warning-text {
      background: rgba(255, 193, 7, 0.15);
      padding: 1rem;
      border-radius: 0.8rem;
      border-left: 4px solid #FFC107;
      margin: 1rem 0;
      color: #856404;
      font-weight: 600;
    }

    .mission-text ol {
      padding-left: 2rem;
      margin: 1rem 0;
    }

    .mission-text ol li {
      margin-bottom: 0.5rem;
      color: rgba(53, 53, 53, 0.85);
      list-style-type: decimal;
    }

    .acceptance-box {
      background: rgba(76, 175, 80, 0.1);
      border-left-color: #4CAF50;
    }

    .final-section {
      border: 2px solid #4CAF50;
    }

    .section-content a {
      color: #ff6b6b;
      text-decoration: none;
      font-weight: 600;
      transition: all 0.3s ease;
    }

    .section-content a:hover {
      color: #ff8c42;
      text-decoration: underline;
    }
    .btn-glass {
      justify-content: center;
      font-family: 'HSR', sans-serif;
      font-size: 1.3rem;
      padding: 15px 23px;
      backdrop-filter: blur(15px);
      background: rgba(231, 173, 131, 0.44);
      color: white;
      border: none;
      border-radius: 12px;
      box-shadow: 0 8px 30px rgba(0,0,0,0.25);
      cursor: pointer;
      transition: all 0.3s ease, transform 0.2s;
      text-decoration: none;
      display: inline-block;
      text-align: center;
    }

    .btn-glass:hover {
      color: white;
      transform: translateY(-2px);
      box-shadow: 0 12px 40px rgba(0,0,0,0.35);
      background: rgba(249, 158, 72, 0.55);
    }
    /* Responsive */
    @media (max-width: 768px) {
      .container {
        padding: 1.5rem;
        margin: 60px auto;
      }

      h1 {
        font-size: 2rem;
      }

      .section-content h2 {
        font-size: 1.4rem;
      }
    }
  </style>
</body>
</html>