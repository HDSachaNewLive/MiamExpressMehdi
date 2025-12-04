# FoodHub - Plateforme de commande de repas en ligne
FoodHub - La plateforme qui simplifie la commande de repas en ligne.

## 📋 Table des matières
- [Description](#description)
- [Fonctionnalités principales](#fonctionnalités-principales)
- [Prérequis](#prérequis)
- [Installation](#installation)
- [Configuration](#configuration)
- [Structure du projet](#structure-du-projet)
- [Guide d'utilisation](#guide-dutilisation)
- [Crédits](#crédits)

---

## 📖 Description

**FoodHub** est une plateforme web de commande et livraison de repas en ligne développée dans le cadre d'un projet BTS SIO. Elle permet aux utilisateurs de commander des plats auprès de plusieurs restaurants, de gérer leur panier, de suivre leurs commandes et de laisser des avis. Les propriétaires de restaurants peuvent gérer leurs établissements et consulter des statistiques détaillées.

**Technologies utilisées :**
- PHP 7.4+
- MySQL
- HTML5 / CSS3
- JavaScript (Vanilla)
- Vanta.js (effets 3D)
- Leaflet.js (cartes)
- Chart.js (statistiques)

---

## Fonctionnalités principales

### Pour les clients :
- 🔐 Inscription et connexion sécurisées (avec reCAPTCHA)
- 🍽️ Navigation et recherche de restaurants par catégorie
- 🛒 Gestion du panier multi-restaurants
- 🎟️ Application de codes promo
- 📦 Suivi de commandes en temps réel
- ⭐ Système d'avis et de notation avec photos
- 💬 Forum de discussion communautaire
- 🎲 Fonction "Surprise" pour découvrir des plats aléatoires en fonction de son budget
- 📄 Export de factures en PDF

### Pour les propriétaires :
- 🏪 Ajout et modification de restaurants
- 🍕 Gestion complète du menu (plats par catégories)
- 📊 Statistiques de ventes détaillées
- 💬 Réponse aux avis clients
- 🔔 Notifications en temps réel

### Pour l'administrateur/Super-admin :
- ✅ Validation des nouveaux restaurants
- 🎟️ Gestion des coupons de réduction
- 📢 Création d'annonces pour les utilisateurs

---

## 🔧 Prérequis

Avant de commencer l'installation, assurez-vous d'avoir :

1. **WAMP Server 3.4.0** (Windows Apache MySQL PHP) version 3.2.0 ou supérieure
   - Téléchargement : [https://wampserver.aviatechno.net]
     **IMPORTANT**; choississez la version qui se trouve sur cette page: Installers Wampserver full install version
   - Nécessite Visual C++ Redistributable packages (voir l'onglet )

2. **Configuration minimale requise :**
   - Windows 7/8/10/11
   - PHP 7.4 ou supérieur (inclus dans WAMP)
   - MySQL 5.7 ou supérieur (inclus dans WAMP)
   - 2 Go de RAM minimum
   - 500 Mo d'espace disque (pour le site), ~1 Go pour WAMP

3. **Navigateur web moderne :**
   - Chrome, Firefox, Edge ou Safari (version récente)

---

## 📥 Installation

### Étape 1 : Installation de WAMP Server

1. Téléchargez WAMP Server depuis le site officiel : [https://www.wampserver.com/en/](https://www.wampserver.com/en/)
2. Lancez l'installateur et suivez les instructions
3. Choisissez le répertoire d'installation (par défaut `C:\wamp64`)
4. Une fois installé, lancez WAMP Server
5. Attendez que l'icône WAMP dans la barre des tâches devienne **verte** (cela peut prendre quelques minutes)

**Note :** Si l'icône reste orange ou rouge, vérifiez que :
- Les ports 80 et 3306 ne sont pas utilisés par d'autres applications (Skype, IIS, etc.)
- Votre antivirus n'interfère pas avec WAMP

---

### Étape 2 : Téléchargement du projet

1. Téléchargez ce dépôt GitHub en ZIP.
   
2. Si vous avez téléchargé le ZIP, extrayez-le

3. Copiez le dossier `foodhub` dans le répertoire `www` de WAMP :
   ```
   C:\wamp64\www\foodhub\
   ```

---

### Étape 3 : Structure des fichiers

Assurez-vous que votre dossier `foodhub` contient la structure suivante :

```
foodhub/
│
├── assets/                          # Fichiers CSS, JS et médias
│   ├── style.css
│   ├── apropos.css
│   ├── barre_annonces.css
│   ├── recommandation.css
│   ├── statistiques_vendeur.css
│   ├── surprise.css
│   ├── 3d-flip.js
│   ├── surprise_plats.js
│   ├── update_vote.js
│   ├── [fichiers audio .mp3/.flac/.wav]
│   ├── fond WiiU.webm
│   └── [autres fichiers médias]
│
├── db/                              # Configuration base de données
│   └── config.php
│
├── uploads/                         # Dossier pour les images uploadées
│   └── avis/
│       └── [images d'exemple]
│
├── [tous les fichiers PHP à la racine]
│   ├── index.php
│   ├── login.php
│   ├── register.php
│   ├── home.php
│   ├── restaurants.php
│   ├── menu.php
│   ├── panier.php
│   ├── checkout.php
│   ├── suivi_commande.php
│   ├── profile.php
│   ├── profile_proprio.php
│   ├── forum.php
│   ├── notifications.php
│   ├── apropos.php
│   ├── admin_coupons.php
│   ├── admin_annonces.php
│   └── [autres fichiers PHP]
│
├── install.sql                      # Script SQL d'installation
└── README.md                        # Ce fichier
```

---

### Étape 4 : Configuration de la base de données
AVANT **TOUTE** ETAPE QUI SUIT, N'OUBLIEZ PAS DE LANCER WAMP!

1. **Accédez à phpMyAdmin :**
   - Cliquez sur l'icône WAMP dans la barre des tâches
   - Sélectionnez "phpMyAdmin"
   - Ou allez directement à : [http://localhost/phpmyadmin](http://localhost/phpmyadmin)

2. **Créez la base de données :**
   - Cliquez sur l'onglet "SQL" en haut
   - Ouvrez le fichier `install.sql` avec un éditeur de texte (Notepad++, VSCode, etc.)
   - Copiez **tout** le contenu du fichier
   - Collez-le dans la zone de texte de phpMyAdmin
   - Cliquez sur "Exécuter"

3. **Vérification :**
   - Dans le panneau de gauche, vous devriez voir la base `foodhub_db`
   - Cliquez dessus pour voir toutes les tables créées

---

### Étape 5 : Configuration du fichier config.php

Le fichier `db/config.php` est déjà configuré pour WAMP par défaut :

```php
<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "foodhub_db";
```

**Si vous avez modifié les paramètres MySQL de WAMP**, ajustez ces valeurs en conséquence.

---

### Étape 6 : Vérification des permissions

Assurez-vous que le dossier `uploads/avis/` a les permissions d'écriture :
1. Faites un clic droit sur le dossier `uploads`
2. Propriétés → Sécurité
3. Vérifiez que "Utilisateurs" a les droits "Écrire"

---

### Étape 7 : Accès au site

1. Ouvrez votre navigateur web
2. Accédez à : localhost/foodhub/index.php (par défaut) OU, si vous avez activé l'option "Autoriser les liens sur les projets page d'accueil" dans les paramètres de WAMP :
   [http://localhost/foodhub](http://localhost/foodhub)
3. Vous devriez voir la page d'accueil de FoodHub (index.php)

---

## ⚙️ Configuration

### Compte Administrateur/Super-Admin

**⚠️ IMPORTANT :** Le premier utilisateur créé avec `user_id = 1` est automatiquement l'administrateur/super-admin du site.

**Pour créer le compte administrateur :**

1. Allez sur [http://localhost/foodhub](http://localhost/foodhub)
2. Cliquez sur "S'inscrire"
3. Remplissez le formulaire avec l'email : **email@example.com** (VEILLEZ A BIEN GARDER CET EMAIL PRECIEUSEMENT ET NE PAS L'OUBLIER/SUPPRIMER LE COMPTE!!!)
4. Choisissez "Propriétaire" comme type de compte
5. Une fois inscrit, ce compte aura les droits de super-administrateur

**Privilèges du super-administrateur :**
- Validation des nouveaux restaurants (notifications.php)
- Gestion des coupons de réduction (admin_coupons.php)
- Gestion des annonces (admin_annonces.php)
- Suppression de tous les commentaires

---

### Configuration reCAPTCHA (Optionnel)

Le site utilise Google reCAPTCHA v2 pour la sécurité. Les clés par défaut sont déjà configurées, mais vous pouvez les remplacer :

1. Allez sur [https://www.google.com/recaptcha/admin](https://www.google.com/recaptcha/admin)
2. Créez une nouvelle clé reCAPTCHA v2
3. Remplacez les clés dans `config_recaptcha.php` :
   ```php
   define('RECAPTCHA_SITE_KEY', 'votre_clé_site');
   define('RECAPTCHA_SECRET_KEY', 'votre_clé_secrète');
   ```

---

### Ajout de restaurants de démonstration

Le script SQL `install.sql` crée automatiquement 3 restaurants de démonstration :
- Le Vélo Gourmand (Français)
- Sushi Koi (Japonais)
- Pasta Bella (Italien)

**Pour ajouter vos propres restaurants :**
1. Créez un compte "Propriétaire"
2. Allez dans "Ajouter un restaurant"
3. Remplissez le formulaire
4. **Attendez la validation de l'administrateur** (compte user_id = 1)

---

## 📚 Guide d'utilisation

### Pour les nouveaux utilisateurs (Clients)

1. **Inscription :**
   - Cliquez sur "S'inscrire" sur la page d'accueil
   - Choisissez "Client" comme type de compte
   - Remplissez vos informations

2. **Parcourir les restaurants :**
   - Utilisez la carte interactive ou la liste
   - Filtrez par propriétaire ou catégorie

3. **Commander :**
   - Cliquez sur "Voir le menu" d'un restaurant
   - Ajoutez des plats au panier
   - Validez votre commande

4. **Utiliser un coupon :**
   - Dans le panier, entrez un code promo
   - Les codes sont visibles dans la section "Coupons" (si disponibles)

5. **Suivre votre commande :**
   - Menu → "Suivi des commandes"
   - Les statuts évoluent automatiquement : En attente → En préparation → En livraison → Livrée

6. **Laisser un avis :**
   - Allez sur la page du restaurant
   - Rédigez votre avis et ajoutez une note
   - Vous pouvez joindre une photo

---

### Pour les propriétaires de restaurants

1. **Inscription :**
   - Créez un compte en choisissant "Propriétaire"

2. **Ajouter un restaurant :**
   - Menu → "Ajouter un restaurant"
   - Remplissez les informations (nom, adresse, latitude, longitude)
   - Ajoutez vos plats avec leurs types (entrée, plat, dessert, etc.)
   - **Attendez la validation de l'administrateur**

3. **Gérer votre restaurant :**
   - Menu → "Profil" → "Mes restaurants"
   - Modifiez les informations ou ajoutez/supprimez des plats

4. **Consulter les statistiques :**
   - Menu → "Profil" → "Voir les statistiques"
   - Visualisez votre chiffre d'affaires, plats les plus vendus, etc.

5. **Répondre aux avis :**
   - Allez sur la page de votre restaurant
   - Cliquez sur "Répondre" sous un avis client

---

### Pour l'administrateur (user_id = 1) UNIQUEMENT

1. **Valider les restaurants :**
   - Menu → "Notifications"
   - Section "Vérification des restaurants"
   - Cliquez sur "Accepter" ou "Refuser"

2. **Gérer les coupons :**
   - Menu → "Coupons"
   - Créez des codes promo avec pourcentage ou montant fixe
   - Définissez la période de validité et les restrictions

3. **Créer des annonces :**
   - Menu → "Annonces"
   - Rédigez une annonce avec dates de début/fin
   - Les utilisateurs la verront sur la page d'accueil

---

## 🐛 Dépannage

### Problème : Page blanche après installation
**Solution :** 
- Vérifiez que WAMP est démarré (icône verte)
- Consultez les logs d'erreurs PHP : Clic droit sur l'icône WAMP → PHP → php_error.log

### Problème : Erreur "Base de données introuvable"
**Solution :**
- Vérifiez que le script SQL a été exécuté correctement dans phpMyAdmin
- Vérifiez les paramètres dans `db/config.php`

### Problème : Images des avis ne s'affichent pas
**Solution :**
- Vérifiez que le dossier `uploads/avis/` existe et a les permissions d'écriture
- Vérifiez que la limite d'upload PHP est suffisante (5 Mo par défaut)

### Problème : Les notifications de nouveaux restaurants ne fonctionnent pas
**Solution :**
- Assurez-vous que le compte administrateur a `user_id = 1`
- Vérifiez que l'email dans le code correspond à l'e-mail de celui ayant l'user_id = 1

### Problème : Port 80 déjà utilisé
**Solution :**
- Fermez Skype ou autres applications utilisant le port 80
- Ou modifiez le port dans la configuration Apache de WAMP

---

## Crédits:

**Développeur :** Mehdi  
**Projet :** BTS SIO - Application web de commande de repas (et +)

**Remerciements :**
- M. Jallon (Le GOAT)
- M. Bourdon (sudo rm -rf /*)
- N.Kannan (idées et suggestions)
- YanisCDN (Testeur originel)
- Stelle.
- O.D Eymen (soutien)

**Testeur(s) :**
- M. Jallon (Le GOAT)
- M. Bourdon (sudo rm -rf /*)
- A.Chevalier (tests bonus)
- YanisCDN (Testeur originel)


**Technologies tierces :**
- [Vanta.js](https://www.vantajs.com/) - Effets de fonds 3D animés
- [Leaflet.js](https://leafletjs.com/) - Cartographie interactive
- [Chart.js](https://www.chartjs.org/) - Graphiques statistiques
- [Google reCAPTCHA (v2)](https://www.google.com/recaptcha/) - Protection anti-spam

**Musiques et sons :**
- Nintendo eShop OST/Nintendo 3DS/WiiU System Music (téléchargé depuis Youtube) / Animal Crossing OST (usage éducatif uniquement)

---

## Licence

Ce projet est réalisé dans un cadre éducatif (BTS SIO). Tous droits réservés.

**Utilisation à des fins éducatives uniquement.**

---

## Contact

Pour toute question ou suggestion :  
Email : mehdiguerbas5@gmail.com

---

**Merci d'utiliser FoodHub ! Bon appétit ! 🍣**
