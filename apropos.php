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
  <link rel="icon" type="image/x-icon" href="FoodHubLogo.ico">
  <meta charset="utf-8">
  <title>À propos - FoodHub</title>
  <link rel="stylesheet" href="assets/style.css">
  <link rel="stylesheet" href="assets/apropos.css">
  <?php include 'sidebar.php'; ?>
</head>
<body>
  <audio id="player" autoplay loop> <source src="https://raw.githubusercontent.com/HDSachaNewLive/foodhub-assets/main/Nintendo 3DS Internet Settings Theme (High Quality, 2022 Remastered).mp3" type="audio/mpeg"> </audio>
  <?php include "slider_son.php"; ?>
  <main class="container">
    <h1>À propos de FoodHub</h1>
    
    <!--section a propos -->
    <div class="apropos-section">
      <div class="section-content">
        <h2>Notre Mission</h2>
        <div class="mission-text">
          <p><strong>FoodHub</strong>, c'est une plateforme qui simplifie la commande de repas en ligne.</p>
          <p>L'objectif : connecter les utilisateurs avec les meilleurs restaurants autour d'eux, tout en offrant une expérience fluide, rapide et agréable.</p>
          <p>Ce projet/site a été conçu dans le cadre d'un  projet pour mon <strong>BTS SIO</strong> pour démontrer l'intégration de plusieurs technologies web (PHP, MySQL, JavaScript, CSS, HTML).</p>
        
        <p style="margin-top: 8px;">Date de création/1er fonctionnement du site: <i>26/10/2025</i></p>
        
        <p>Date de mise en ligne publique officielle :</p>
        <p><strong>Mardi 19 Mai 2026, 2:09 <i>(UTC+2, Paris)</i>.</strong></p>
        </div>
        
        <div class="developer-info">
          <h3>Développeur</h3>
          <p>Projet réalisé par <strong>Mehdi</strong>, étudiant en BTS SIO.</p>
          <p>Mentions/Remerciements :
            <ul>
              <li><strong>Etowaru (pour les idées proposées)</strong></li>
              <li><strong>M. Jallon (A.K.A Jallon Le GOAT)</strong></li>
              <li><strong>M. Bourdon (sudo rm -rf /*)</strong></li>
              <li><strong>N. KANNAN</strong></li>
            </ul>
          </p>
          <p>📧 <a href="mailto:mehdiguerbas5@gmail.com" class="contact-link">mehdiguerbas5@gmail.com</a></p>
        </div>
        <div class="tester-info">
          <h3>Testeur(s)</h3>
          <p>Tests réalisés par:</p>
          <ul>
              <li><strong>Etowaru - Konosekai DrainWorld</strong></li>
              <li><strong>M. Jallon (A.K.A Jallon Le GOAT)</strong></li>
              <li><strong>M. Bourdon (sudo rm -rf /*)</strong></li>
              <li><strong>YanisCDN</strong></li>
              
          </ul>
          <p>Mentions/Remerciements :
            <p><strong>Zacharie S. (A.K.A Etowaru, Le Testeur Originel)</strong></p>
          </p>
        </div>
      </div>
    </div>

    <!--FAQ -->
    <div class="faq-section" id="faq">
      <div class="section-content">
        <h2>❓ Foire Aux Questions FAQ</h2>
        
        <div class="faq-container">

          <!-- COMMANDES -->
          <div class="faq-item">
            <h3>Comment passer une commande ?</h3>
            <p>Connectez-vous / créez un compte, allez sur la page d'un restaurant, ajoutez vos plats au panier, validez la commande et choisissez votre mode de paiement (carte simulée ou paiement à la livraison).</p>
          </div>

          <div class="faq-item">
            <h3>Puis-je commander dans plusieurs restaurants à la fois ?</h3>
            <p>Oui, FoodHub permet de combiner des plats de plusieurs restaurants dans une seule commande. Votre panier est sauvegardé tant que vous ne passez pas commande ou que vous ne le videz pas.</p>
          </div>

          <div class="faq-item">
            <h3>Les paiements sont-ils réels ?</h3>
            <p>Non. Les paiements sont entièrement simulés dans le cadre du projet. Aucune transaction financière réelle n'est effectuée sur FoodHub.</p>
          </div>

          <div class="faq-item">
            <h3>Comment suivre ma commande ?</h3>
            <p>Rendez-vous dans <strong>Menu → Suivi des commandes</strong>. Les statuts évoluent automatiquement et en temps réel :</p>
            <ul>
              <li>⏳ En attente</li>
              <li>👨‍🍳 En préparation</li>
              <li>🚚 En livraison</li>
              <li>✅ Livrée</li>
            </ul>
          </div>

          <div class="faq-item">
            <h3>Puis-je annuler ma commande ?</h3>
            <p>Oui, vous pouvez annuler une commande tant qu'elle est au statut <strong>En attente</strong> ou <strong>En préparation</strong>. Une fois en livraison, l'annulation n'est plus possible.</p>
          </div>

          <div class="faq-item">
            <h3>Puis-je repasser une ancienne commande ?</h3>
            <p>Oui ! Depuis la page de détail d'une commande livrée, un bouton <strong>« Commander à nouveau »</strong> remet automatiquement tous les articles dans votre panier.</p>
          </div>

          <div class="faq-item">
            <h3>Comment obtenir une facture ?</h3>
            <p>Sur la page de détail de n'importe quelle commande, un bouton <strong>« Télécharger la facture (PDF) »</strong> vous permet d'exporter et d'imprimer votre facture.</p>
          </div>

          <!-- COMPTE -->
          <div class="faq-item">
            <h3>Comment créer un compte ?</h3>
            <p>Cliquez sur <strong>« S'inscrire »</strong> depuis la page d'accueil. Vous pouvez vous inscrire manuellement ou via votre compte Google en un clic.</p>
          </div>

          <div class="faq-item">
            <h3>Quelle est la différence entre un compte Client et un compte Propriétaire ?</h3>
            <p>
              <strong>Client :</strong> peut parcourir les restaurants, commander des plats, laisser des avis et participer au forum.<br>
              <strong>Propriétaire :</strong> peut en plus créer et gérer des restaurants, gérer son menu par catégories de plats, et consulter des statistiques de ventes détaillées (chiffre d'affaires, plats les plus vendus, etc.).<br>
              <em>Note : le type de compte ne peut pas être changé après la création du compte.</em>
            </p>
          </div>

          <div class="faq-item">
            <h3>Puis-je me connecter avec Google ?</h3>
            <p>Oui, FoodHub supporte la connexion via Google OAuth 2.0. Si vous n'avez pas encore de compte, un profil est automatiquement créé à votre première connexion Google. Vous pourrez ensuite le compléter (type de compte, adresse, etc.).</p>
            <em>Note : la connexion avec Google est actuellement indisponible depuis quelque temps, veuillez nous excuser de ce désagrément.</em>
          </div>

          <div class="faq-item">
            <h3>Comment modifier mon profil ?</h3>
            <p>Accédez à votre profil via le menu latéral ou la page principale. Vous pouvez y modifier votre nom, email, téléphone, adresse de livraison, photo de profil, description, et même la couleur du fond de votre profil public.</p>
          </div>

          <div class="faq-item">
            <h3>Comment supprimer mon compte ?</h3>
            <p>Depuis votre profil, faites défiler jusqu'en bas et cliquez sur <strong>« Supprimer mon compte »</strong>. <strong>Cette action est irréversible</strong> et supprimera toutes vos données (commandes, avis, restaurants (si vous êtes propriétaire), etc). Seuls les messages de forums sont conservés.</p>
          </div>

          <!-- RESTAURANTS & AVIS -->
          <div class="faq-item">
            <h3>Comment laisser un avis ?</h3>
            <p>Rendez-vous sur la page du restaurant concerné, descendez jusqu'à la section <strong>« Avis »</strong>, rédigez votre commentaire, choisissez une note de 1 à 5 étoiles, et cliquez sur <strong>« Publier »</strong>. Vous pouvez également joindre une photo.
            <ul>
              <li>Note : vous devez être connecté(e) pour publier un avis.</li>
            </ul>
            </p>
          </div>

          <div class="faq-item">
            <h3>Comment ajouter un restaurant en favori ?</h3>
            <p>Sur la page des restaurants ou sur la fiche d'un restaurant, cliquez sur le bouton 🤍 pour l'ajouter à vos favoris. Retrouvez tous vos favoris dans <strong>Menu (barre latérale) → Mes Favoris</strong>.</p>
          </div>

          <div class="faq-item">
            <h3>Comment devenir propriétaire de restaurant / ajouter un restaurant ?</h3>
            <p>Créez un compte en choisissant le type <strong>« Propriétaire »</strong>, puis ajoutez votre restaurant via votre profil ou la page d'accueil.
              <ul>
                <li>Vous devez être connecté(e) pour ajouter un restaurant.</li>
                <li>Une fois soumis, votre restaurant sera en attente de validation par l'administrateur avant d'être visible publiquement.</li>
              </ul>
            </p>
          </div>

          <!-- FONCTIONS SPÉCIALES -->
          <div class="faq-item">
            <h3>C'est quoi la fonction « Surprise » ?</h3>
            <p>Disponible sur la page d'accueil, la fonction <strong>🎲 Surprenez-moi !</strong> sélectionne automatiquement des plats aléatoires en respectant un budget minimum et maximum que vous définissez. Pratique quand vous n'avez pas d'idée !</p>
          </div>

          <div class="faq-item">
            <h3>Comment utiliser un code de réduction ?</h3>
            <p>Dans le <strong>Panier</strong>, une section <strong>« Code de réduction »</strong> vous permet de saisir un code promo. Les codes peuvent offrir un pourcentage ou un montant fixe de réduction, et peuvent être limités à un restaurant ou une période spécifique. Les codes actifs sont parfois annoncés sur la page d'accueil.</p>
          </div>

          <div class="faq-item">
            <h3>Comment fonctionne le forum ?</h3>
            <p>Accessible via <strong>Menu → Discussion</strong>, le forum permet de créer et de participer à des discussions par catégorie (Restaurants, Recettes, Conseils, Général). Les messages s'affichent en temps réel sans recharger la page.</p>
          </div>

          <div class="faq-item">
            <h3>Comment fonctionne le système de notifications ?</h3>
            <p>Vous recevez des notifications dans <strong>Menu → Notifications</strong> lorsque :
              <ul>
                <li>Un propriétaire répond à votre avis.</li>
                <li>Un utilisateur commente sur votre restaurant (si vous êtes propriétaire).</li>
                <li>L'administrateur répond à un message de contact.</li>
              </ul>
            </p>
          </div>

          <!-- TECHNIQUE -->
          <div class="faq-item">
            <h3>FoodHub fonctionne-t-il sur mobile ?</h3>
            <p>FoodHub est conçu pour fonctionner sur navigateur web moderne (Chrome, Firefox, Edge, Safari). Une navigation mobile est possible, bien que l'expérience soit optimisée pour un écran d'ordinateur.</p>
          </div>

          <div class="faq-item">
            <h3>Mes données sont-elles sécurisées ?</h3>
            <p>Vos mots de passe sont chiffrés (hashés). Le site utilise un reCAPTCHA pour prévenir les robots, une protection contre la brute force sur la connexion, et une validation CSRF pour les connexions Google. Consulter les <a href="tos.php">Conditions de Service</a> pour plus de détails.</p>
          </div>

          <div class="faq-item">
            <h3>Comment contacter l'administrateur ?</h3>
            <p>Utilisez le formulaire disponible sur la page <a href="contact_admin.php">Contact</a> pour envoyer un message concernant un problème de compte, un signalement, une suggestion ou toute autre question.</p>
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
  window.vantaEffect = VANTA.WAVES({
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