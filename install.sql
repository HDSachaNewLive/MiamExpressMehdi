-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1:3306
-- Généré le : mer. 24 déc. 2025 à 02:53
-- Version du serveur : 9.1.0
-- Version de PHP : 8.3.14

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `foodhub_db`
--
CREATE DATABASE IF NOT EXISTS `foodhub_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
USE `foodhub_db`;

-- --------------------------------------------------------

--
-- Structure de la table `admin_actions`
--

DROP TABLE IF EXISTS `admin_actions`;
CREATE TABLE IF NOT EXISTS `admin_actions` (
  `action_id` int NOT NULL AUTO_INCREMENT,
  `admin_id` int NOT NULL,
  `target_user_id` int NOT NULL,
  `action_type` enum('desactiver','activer','supprimer') NOT NULL,
  `raison` text,
  `date_action` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`action_id`),
  KEY `admin_id` (`admin_id`),
  KEY `target_user_id` (`target_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `annonces`
--

DROP TABLE IF EXISTS `annonces`;
CREATE TABLE IF NOT EXISTS `annonces` (
  `annonce_id` int NOT NULL AUTO_INCREMENT,
  `titre` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `date_debut` datetime NOT NULL,
  `date_fin` datetime NOT NULL,
  `actif` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`annonce_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `avis`
--

DROP TABLE IF EXISTS `avis`;
CREATE TABLE IF NOT EXISTS `avis` (
  `avis_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `restaurant_id` int NOT NULL,
  `note` int DEFAULT NULL,
  `commentaire` text,
  `date_avis` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `reponse` text,
  `likes` int NOT NULL DEFAULT '0',
  `dislikes` int NOT NULL DEFAULT '0',
  `image_path` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`avis_id`),
  KEY `user_id` (`user_id`),
  KEY `restaurant_id` (`restaurant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `avis_votes`
--

DROP TABLE IF EXISTS `avis_votes`;
CREATE TABLE IF NOT EXISTS `avis_votes` (
  `vote_id` int NOT NULL AUTO_INCREMENT,
  `avis_id` int NOT NULL,
  `user_id` int NOT NULL,
  `type` enum('like','dislike') NOT NULL,
  `date_vote` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`vote_id`),
  UNIQUE KEY `unique_vote` (`avis_id`,`user_id`),
  KEY `fk_av_vote_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `captcha_logs`
--

DROP TABLE IF EXISTS `captcha_logs`;
CREATE TABLE IF NOT EXISTS `captcha_logs` (
  `log_id` int NOT NULL AUTO_INCREMENT,
  `ip` varchar(50) NOT NULL,
  `success` tinyint(1) NOT NULL,
  `attempt_time` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`log_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `commandes`
--

DROP TABLE IF EXISTS `commandes`;
CREATE TABLE IF NOT EXISTS `commandes` (
  `commande_id` int NOT NULL AUTO_INCREMENT,
  `numero_utilisateur` int DEFAULT NULL,
  `user_id` int NOT NULL,
  `date_commande` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `statut` enum('en_attente','en_preparation','en_livraison','livree','annulee') DEFAULT 'en_attente',
  `montant_total` decimal(65,2) DEFAULT NULL,
  `montant_reduction` decimal(65,2) DEFAULT '0.00',
  `coupon_id` int DEFAULT NULL,
  `mode_paiement` enum('carte','paypal','livraison') DEFAULT 'carte',
  `date_paiement` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`commande_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `commande_plats`
--

DROP TABLE IF EXISTS `commande_plats`;
CREATE TABLE IF NOT EXISTS `commande_plats` (
  `commande_id` int NOT NULL,
  `plat_id` int NOT NULL,
  `quantite` int DEFAULT '1',
  `prix_unitaire` decimal(6,2) DEFAULT NULL,
  PRIMARY KEY (`commande_id`,`plat_id`),
  KEY `plat_id` (`plat_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `coupons`
--

DROP TABLE IF EXISTS `coupons`;
CREATE TABLE IF NOT EXISTS `coupons` (
  `coupon_id` int NOT NULL AUTO_INCREMENT,
  `code_reduction` varchar(50) NOT NULL,
  `type` enum('pourcentage','montant') NOT NULL,
  `valeur` decimal(10,2) NOT NULL,
  `date_debut` datetime NOT NULL,
  `date_fin` datetime NOT NULL,
  `utilisation_max` int DEFAULT NULL,
  `utilisations` int DEFAULT '0',
  `actif` tinyint(1) DEFAULT '1',
  `restaurant_id` int DEFAULT NULL,
  PRIMARY KEY (`coupon_id`),
  UNIQUE KEY `code_reduction` (`code_reduction`),
  KEY `restaurant_id` (`restaurant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `forum_messages`
--

DROP TABLE IF EXISTS `forum_messages`;
CREATE TABLE IF NOT EXISTS `forum_messages` (
  `message_id` int NOT NULL AUTO_INCREMENT,
  `topic_id` int NOT NULL,
  `user_id` int NOT NULL,
  `contenu` text NOT NULL,
  `date_message` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `modifie` tinyint(1) DEFAULT '0',
  `date_modification` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`message_id`),
  KEY `user_id` (`user_id`),
  KEY `idx_message_topic` (`topic_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `forum_topics`
--

DROP TABLE IF EXISTS `forum_topics`;
CREATE TABLE IF NOT EXISTS `forum_topics` (
  `topic_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `titre` varchar(255) NOT NULL,
  `categorie` enum('restaurants','recettes','conseils','general') DEFAULT 'general',
  `epingle` tinyint(1) DEFAULT '0',
  `verrouille` tinyint(1) DEFAULT '0',
  `date_creation` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `derniere_activite` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `nb_reponses` int DEFAULT '0',
  `vues` int DEFAULT '0',
  PRIMARY KEY (`topic_id`),
  KEY `user_id` (`user_id`),
  KEY `idx_topic_categorie` (`categorie`),
  KEY `idx_topic_date` (`derniere_activite`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `messages_admin`
--

DROP TABLE IF EXISTS `messages_admin`;
CREATE TABLE IF NOT EXISTS `messages_admin` (
  `message_id` int NOT NULL AUTO_INCREMENT,
  `nom` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `sujet` varchar(200) NOT NULL,
  `message` text NOT NULL,
  `type_message` enum('general','compte','signalement','technique','suggestion','autre') DEFAULT 'general',
  `lu` tinyint(1) DEFAULT '0',
  `date_envoi` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`message_id`),
  KEY `idx_lu` (`lu`),
  KEY `idx_type` (`type_message`),
  KEY `idx_date` (`date_envoi`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `type` enum('comment','reply') NOT NULL,
  `restaurant_id` int NOT NULL,
  `avis_id` int NOT NULL,
  `message` varchar(255) NOT NULL,
  `is_read` tinyint(1) DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `restaurant_id` (`restaurant_id`),
  KEY `avis_id` (`avis_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `panier`
--

DROP TABLE IF EXISTS `panier`;
CREATE TABLE IF NOT EXISTS `panier` (
  `panier_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `plat_id` int NOT NULL,
  `quantite` int DEFAULT '1',
  `date_ajout` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`panier_id`),
  KEY `user_id` (`user_id`),
  KEY `plat_id` (`plat_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `plats`
--

DROP TABLE IF EXISTS `plats`;
CREATE TABLE IF NOT EXISTS `plats` (
  `plat_id` int NOT NULL AUTO_INCREMENT,
  `restaurant_id` int NOT NULL,
  `nom_plat` varchar(100) NOT NULL,
  `prix` decimal(6,2) NOT NULL,
  `description_plat` text,
  `type_plat` enum('entree','plat','accompagnement','boisson','dessert','sauce') DEFAULT 'plat',
  PRIMARY KEY (`plat_id`),
  KEY `restaurant_id` (`restaurant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `profil_stats`
--

DROP TABLE IF EXISTS `profil_stats`;
CREATE TABLE IF NOT EXISTS `profil_stats` (
  `stat_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `nb_visites` int DEFAULT '0',
  `derniere_visite` datetime DEFAULT NULL,
  PRIMARY KEY (`stat_id`),
  UNIQUE KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `restaurants`
--

DROP TABLE IF EXISTS `restaurants`;
CREATE TABLE IF NOT EXISTS `restaurants` (
  `restaurant_id` int NOT NULL AUTO_INCREMENT,
  `proprietaire_id` int DEFAULT NULL,
  `nom_restaurant` varchar(100) NOT NULL,
  `adresse` varchar(255) DEFAULT NULL,
  `latitude` double DEFAULT NULL,
  `longitude` double DEFAULT NULL,
  `categorie` varchar(50) DEFAULT NULL,
  `description_resto` text,
  `verified` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`restaurant_id`),
  KEY `proprietaire_id` (`proprietaire_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `tentatives_conn`
--

DROP TABLE IF EXISTS `tentatives_conn`;
CREATE TABLE IF NOT EXISTS `tentatives_conn` (
  `id` int NOT NULL AUTO_INCREMENT,
  `ip` varchar(50) NOT NULL,
  `email` varchar(255) NOT NULL,
  `attempt_time` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE IF NOT EXISTS `users` (
  `user_id` int NOT NULL AUTO_INCREMENT,
  `nom_user` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `telephone` varchar(20) DEFAULT NULL,
  `motdepasse` varchar(255) NOT NULL,
  `adresse_livraison` varchar(255) DEFAULT NULL,
  `photo_profil` varchar(255) DEFAULT NULL,
  `description_profil` text,
  `couleur_vanta` varchar(7) DEFAULT '#dba1b2',
  `compte_actif` tinyint(1) DEFAULT '1',
  `date_desactivation` datetime DEFAULT NULL,
  `type_compte` enum('client','proprietaire') DEFAULT 'client',
  `date_creation` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Structure de la table `favoris`
--

CREATE TABLE IF NOT EXISTS `favoris` (
  `favori_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `restaurant_id` int NOT NULL,
  `date_ajout` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`favori_id`),
  UNIQUE KEY `unique_favori` (`user_id`,`restaurant_id`),
  KEY `user_id` (`user_id`),
  KEY `restaurant_id` (`restaurant_id`),
  CONSTRAINT `favoris_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `favoris_ibfk_2` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`restaurant_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- =========================
-- DONNÉES DE DÉMO
-- =========================

-- Insérer un utilisateur admin par défaut (user_id = 1)
-- Mot de passe hashé pour "admin123" (à changer en production)
-- INSERT INTO users (nom_user, email, telephone, motdepasse, adresse_livraison, type_compte) VALUES
-- ('Admin FoodHub', 'admin@foodhub.com', '0123456789', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '1 Rue de la Paix, Paris', 'proprietaire');

-- Insérer des restaurants de démonstration
INSERT INTO restaurants (proprietaire_id, nom_restaurant, adresse, latitude, longitude, categorie, description_resto, verified) VALUES
(1, 'Le Vélo Gourmand', '12 rue du Lac, Paris', 48.8655, 2.3212, 'Français', 'Petits plats faits maison', 1),
(1, 'Sushi Koi', '45 avenue de Tokyo, Paris', 48.8566, 2.3522, 'Japonais', 'Sushis frais', 1),
(1, 'Pasta Bella', '7 rue Roma, Paris', 48.8600, 2.3300, 'Italien', 'Pâtes maison', 1);

-- Insérer des plats de démonstration
INSERT INTO plats (restaurant_id, nom_plat, prix, description_plat, type_plat) VALUES 
(1, 'Planche charcuterie', 12.50, 'Assortiment de charcuterie locale', 'entree'), 
(1, 'Tartine du jour', 8.00, "Tartine selon l'arrivage", 'plat'),
(1, 'Crème brûlée', 6.50, 'Crème brûlée vanille', 'dessert'),
(2, 'Assortiment sushi 8 pcs', 14.00, 'Mix de nigiri et maki', 'plat'),
(2, 'California roll', 9.50, 'Avocat, crabe, concombre', 'plat'),
(2, 'Mochi glacé', 5.00, 'Assortiment de 3 mochis', 'dessert'),
(3, 'Spaghetti Carbonara', 11.00, 'Recette traditionnelle', 'plat'),
(3, 'Lasagne maison', 12.00, 'Viande & béchamel maison', 'plat'),
(3, 'Tiramisu', 6.00, 'Tiramisu fait maison', 'dessert');

-- =========================
-- COUPONS DE DÉMONSTRATION
-- =========================
INSERT INTO coupons (code_reduction, type, valeur, date_debut, date_fin, utilisation_max, restaurant_id, actif) VALUES
('BIENVENUE10', 'pourcentage', 10.00, NOW(), DATE_ADD(NOW(), INTERVAL 30 DAY), NULL, NULL, 1),
('PROMO5EUR', 'montant', 5.00, NOW(), DATE_ADD(NOW(), INTERVAL 15 DAY), 100, NULL, 1),
('SUSHI20', 'pourcentage', 20.00, NOW(), DATE_ADD(NOW(), INTERVAL 7 DAY), 50, 2, 1);

-- =========================
-- ANNONCE DE DÉMONSTRATION
-- =========================
INSERT INTO annonces (titre, message, date_debut, date_fin, actif) VALUES
('Bienvenue sur FoodHub !', 'Profitez de notre offre de bienvenue : 10% de réduction avec le code BIENVENUE10 sur votre première commande !', NOW(), DATE_ADD(NOW(), INTERVAL 30 DAY), 1);

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `forum_messages`
--
ALTER TABLE `forum_messages`
  ADD CONSTRAINT `forum_messages_ibfk_1` FOREIGN KEY (`topic_id`) REFERENCES `forum_topics` (`topic_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `forum_messages_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `forum_topics`
--
ALTER TABLE `forum_topics`
  ADD CONSTRAINT `forum_topics_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `profil_stats`
--
ALTER TABLE `profil_stats`
  ADD CONSTRAINT `profil_stats_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
