-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: mysql-foodhub-sio.alwaysdata.net
-- Generation Time: Jul 11, 2026 at 04:34 PM
-- Server version: 11.4.12-MariaDB
-- PHP Version: 8.4.21

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `foodhub-sio_db`
--
CREATE DATABASE IF NOT EXISTS `foodhub-sio_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `foodhub-sio_db`;

-- --------------------------------------------------------

--
-- Table structure for table `admin_actions`
--

DROP TABLE IF EXISTS `admin_actions`;
CREATE TABLE `admin_actions` (
  `action_id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `target_user_id` int(11) NOT NULL,
  `action_type` enum('desactiver','activer','supprimer') NOT NULL,
  `raison` text DEFAULT NULL,
  `date_action` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `annonces`
--

DROP TABLE IF EXISTS `annonces`;
CREATE TABLE `annonces` (
  `annonce_id` int(11) NOT NULL,
  `titre` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `date_debut` datetime NOT NULL,
  `date_fin` datetime NOT NULL,
  `actif` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `avis`
--

DROP TABLE IF EXISTS `avis`;
CREATE TABLE `avis` (
  `avis_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `restaurant_id` int(11) NOT NULL,
  `note` int(11) DEFAULT NULL,
  `commentaire` text DEFAULT NULL,
  `date_avis` timestamp NULL DEFAULT current_timestamp(),
  `reponse` text DEFAULT NULL,
  `likes` int(11) NOT NULL DEFAULT 0,
  `dislikes` int(11) NOT NULL DEFAULT 0,
  `image_path` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `avis_votes`
--

DROP TABLE IF EXISTS `avis_votes`;
CREATE TABLE `avis_votes` (
  `vote_id` int(11) NOT NULL,
  `avis_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `type` enum('like','dislike') NOT NULL,
  `date_vote` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `bridge_messages`
--

DROP TABLE IF EXISTS `bridge_messages`;
CREATE TABLE `bridge_messages` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `direction` enum('in','out') NOT NULL DEFAULT 'in',
  `local_site` varchar(80) NOT NULL,
  `remote_site` varchar(120) DEFAULT NULL,
  `source_user` varchar(80) DEFAULT NULL,
  `message` text NOT NULL,
  `remote_ip` varchar(80) DEFAULT NULL,
  `remote_response_code` int(11) DEFAULT NULL,
  `remote_response` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `captcha_logs`
--

DROP TABLE IF EXISTS `captcha_logs`;
CREATE TABLE `captcha_logs` (
  `log_id` int(11) NOT NULL,
  `ip` varchar(50) NOT NULL,
  `success` tinyint(1) NOT NULL,
  `attempt_time` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `commandes`
--

DROP TABLE IF EXISTS `commandes`;
CREATE TABLE `commandes` (
  `commande_id` int(11) NOT NULL,
  `numero_utilisateur` int(11) DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `date_commande` timestamp NULL DEFAULT current_timestamp(),
  `statut` enum('en_attente','en_preparation','en_livraison','livree','annulee') DEFAULT 'en_attente',
  `montant_total` decimal(65,2) DEFAULT NULL,
  `montant_reduction` decimal(65,2) DEFAULT 0.00,
  `coupon_id` int(11) DEFAULT NULL,
  `mode_paiement` enum('carte','paypal','livraison') DEFAULT 'carte',
  `date_paiement` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `commande_plats`
--

DROP TABLE IF EXISTS `commande_plats`;
CREATE TABLE `commande_plats` (
  `commande_id` int(11) NOT NULL,
  `plat_id` int(11) NOT NULL,
  `quantite` int(11) DEFAULT 1,
  `prix_unitaire` decimal(6,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `coupons`
--

DROP TABLE IF EXISTS `coupons`;
CREATE TABLE `coupons` (
  `coupon_id` int(11) NOT NULL,
  `code_reduction` varchar(50) NOT NULL,
  `type` enum('pourcentage','montant') NOT NULL,
  `valeur` decimal(10,2) NOT NULL,
  `date_debut` datetime NOT NULL,
  `date_fin` datetime NOT NULL,
  `utilisation_max` int(11) DEFAULT NULL,
  `utilisations` int(11) DEFAULT 0,
  `actif` tinyint(1) DEFAULT 1,
  `restaurant_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `email_tokens`
--

DROP TABLE IF EXISTS `email_tokens`;
CREATE TABLE `email_tokens` (
  `token_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `token` varchar(64) NOT NULL,
  `type` enum('verify','reset') NOT NULL DEFAULT 'verify',
  `new_email` varchar(100) DEFAULT NULL COMMENT 'Utilisé pour changement d email en attente',
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `favoris`
--

DROP TABLE IF EXISTS `favoris`;
CREATE TABLE `favoris` (
  `favori_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `restaurant_id` int(11) NOT NULL,
  `date_ajout` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `forum_messages`
--

DROP TABLE IF EXISTS `forum_messages`;
CREATE TABLE `forum_messages` (
  `message_id` int(11) NOT NULL,
  `topic_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `contenu` text NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `date_message` timestamp NULL DEFAULT current_timestamp(),
  `modifie` tinyint(1) DEFAULT 0,
  `date_modification` timestamp NULL DEFAULT NULL,
  `auteur_supprime` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `forum_notifs`
--

DROP TABLE IF EXISTS `forum_notifs`;
CREATE TABLE `forum_notifs` (
  `notif_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL COMMENT 'destinataire',
  `topic_id` int(11) NOT NULL,
  `message_id` int(11) NOT NULL COMMENT 'dernier message déclencheur',
  `topic_titre` varchar(255) NOT NULL,
  `auteur_nom` varchar(100) NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `is_reply` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `forum_topics`
--

DROP TABLE IF EXISTS `forum_topics`;
CREATE TABLE `forum_topics` (
  `topic_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `titre` varchar(255) NOT NULL,
  `categorie` enum('restaurants','recettes','conseils','general') DEFAULT 'general',
  `epingle` tinyint(1) DEFAULT 0,
  `verrouille` tinyint(1) DEFAULT 0,
  `date_creation` timestamp NULL DEFAULT current_timestamp(),
  `derniere_activite` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `nb_reponses` int(11) DEFAULT 0,
  `vues` int(11) DEFAULT 0,
  `auteur_supprime` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `messages_admin`
--

DROP TABLE IF EXISTS `messages_admin`;
CREATE TABLE `messages_admin` (
  `message_id` int(11) NOT NULL,
  `nom` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `sujet` varchar(200) NOT NULL,
  `message` text NOT NULL,
  `type_message` enum('general','compte','signalement','technique','suggestion','autre') DEFAULT 'general',
  `lu` tinyint(1) DEFAULT 0,
  `reponse_admin` text DEFAULT NULL,
  `date_reponse` datetime DEFAULT NULL,
  `date_envoi` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `type` enum('comment','reply') NOT NULL,
  `restaurant_id` int(11) NOT NULL,
  `avis_id` int(11) NOT NULL,
  `message` varchar(255) NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notify_list`
--

DROP TABLE IF EXISTS `notify_list`;
CREATE TABLE `notify_list` (
  `id` int(11) NOT NULL,
  `nom` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `actif` tinyint(1) NOT NULL DEFAULT 1,
  `date_ajout` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notify_log`
--

DROP TABLE IF EXISTS `notify_log`;
CREATE TABLE `notify_log` (
  `log_id` int(11) NOT NULL,
  `date_envoi` datetime NOT NULL DEFAULT current_timestamp(),
  `nb_envoyes` int(11) NOT NULL DEFAULT 0,
  `statut` enum('ok','erreur') NOT NULL DEFAULT 'ok',
  `detail` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `panier`
--

DROP TABLE IF EXISTS `panier`;
CREATE TABLE `panier` (
  `panier_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `plat_id` int(11) NOT NULL,
  `quantite` int(11) DEFAULT 1,
  `date_ajout` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `parametres_stream`
--

DROP TABLE IF EXISTS `parametres_stream`;
CREATE TABLE `parametres_stream` (
  `cle` varchar(100) NOT NULL,
  `valeur` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `plats`
--

DROP TABLE IF EXISTS `plats`;
CREATE TABLE `plats` (
  `plat_id` int(11) NOT NULL,
  `restaurant_id` int(11) NOT NULL,
  `nom_plat` varchar(100) NOT NULL,
  `prix` decimal(6,2) NOT NULL,
  `image_path` varchar(512) DEFAULT NULL,
  `description_plat` text DEFAULT NULL,
  `type_plat` enum('entree','plat','accompagnement','boisson','dessert','sauce') DEFAULT 'plat'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `profil_stats`
--

DROP TABLE IF EXISTS `profil_stats`;
CREATE TABLE `profil_stats` (
  `stat_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `nb_visites` int(11) DEFAULT 0,
  `derniere_visite` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `restaurants`
--

DROP TABLE IF EXISTS `restaurants`;
CREATE TABLE `restaurants` (
  `restaurant_id` int(11) NOT NULL,
  `proprietaire_id` int(11) DEFAULT NULL,
  `nom_restaurant` varchar(100) NOT NULL,
  `adresse` varchar(255) DEFAULT NULL,
  `latitude` double DEFAULT NULL,
  `longitude` double DEFAULT NULL,
  `categorie` varchar(50) DEFAULT NULL,
  `description_resto` text DEFAULT NULL,
  `verified` tinyint(1) NOT NULL DEFAULT 0,
  `ouvert` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sessions_actives`
--

DROP TABLE IF EXISTS `sessions_actives`;
CREATE TABLE `sessions_actives` (
  `session_id` varchar(128) NOT NULL,
  `user_id` int(11) NOT NULL,
  `derniere_activite` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tentatives_conn`
--

DROP TABLE IF EXISTS `tentatives_conn`;
CREATE TABLE `tentatives_conn` (
  `id` int(11) NOT NULL,
  `ip` varchar(50) NOT NULL,
  `email` varchar(255) NOT NULL,
  `attempt_time` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `nom_user` varchar(45) NOT NULL,
  `email` varchar(100) NOT NULL,
  `email_fictif` tinyint(1) NOT NULL DEFAULT 0,
  `email_verifie` tinyint(1) NOT NULL DEFAULT 0,
  `email_verifie_at` datetime DEFAULT NULL,
  `telephone` varchar(20) DEFAULT NULL,
  `motdepasse` varchar(255) NOT NULL,
  `google_id` varchar(100) DEFAULT NULL,
  `google_photo` varchar(512) DEFAULT NULL,
  `adresse_livraison` varchar(255) DEFAULT NULL,
  `photo_profil` varchar(255) DEFAULT NULL,
  `description_profil` text DEFAULT NULL,
  `couleur_vanta` varchar(7) DEFAULT '#dba1b2',
  `compte_actif` tinyint(1) DEFAULT 1,
  `date_desactivation` datetime DEFAULT NULL,
  `type_compte` enum('client','proprietaire') DEFAULT 'client',
  `date_creation` timestamp NULL DEFAULT current_timestamp(),
  `derniere_connexion` datetime DEFAULT NULL,
  `role` varchar(32) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_preferences`
--

DROP TABLE IF EXISTS `user_preferences`;
CREATE TABLE `user_preferences` (
  `pref_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `notif_forum_actif` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1 = recevoir les notifs forum en temps reel',
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `reduire_animations` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = désactiver Vanta animé',
  `profil_prive` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = profil public masqué aux autres'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin_actions`
--
ALTER TABLE `admin_actions`
  ADD PRIMARY KEY (`action_id`),
  ADD KEY `admin_id` (`admin_id`),
  ADD KEY `target_user_id` (`target_user_id`);

--
-- Indexes for table `annonces`
--
ALTER TABLE `annonces`
  ADD PRIMARY KEY (`annonce_id`);

--
-- Indexes for table `avis`
--
ALTER TABLE `avis`
  ADD PRIMARY KEY (`avis_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `restaurant_id` (`restaurant_id`);

--
-- Indexes for table `avis_votes`
--
ALTER TABLE `avis_votes`
  ADD PRIMARY KEY (`vote_id`),
  ADD UNIQUE KEY `unique_vote` (`avis_id`,`user_id`),
  ADD KEY `fk_av_vote_user` (`user_id`);

--
-- Indexes for table `bridge_messages`
--
ALTER TABLE `bridge_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_bridge_messages_created_at` (`created_at`),
  ADD KEY `idx_bridge_messages_direction` (`direction`);

--
-- Indexes for table `captcha_logs`
--
ALTER TABLE `captcha_logs`
  ADD PRIMARY KEY (`log_id`);

--
-- Indexes for table `commandes`
--
ALTER TABLE `commandes`
  ADD PRIMARY KEY (`commande_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `commande_plats`
--
ALTER TABLE `commande_plats`
  ADD PRIMARY KEY (`commande_id`,`plat_id`),
  ADD KEY `plat_id` (`plat_id`);

--
-- Indexes for table `coupons`
--
ALTER TABLE `coupons`
  ADD PRIMARY KEY (`coupon_id`),
  ADD UNIQUE KEY `code_reduction` (`code_reduction`),
  ADD KEY `restaurant_id` (`restaurant_id`);

--
-- Indexes for table `email_tokens`
--
ALTER TABLE `email_tokens`
  ADD PRIMARY KEY (`token_id`),
  ADD UNIQUE KEY `uq_token` (`token`),
  ADD KEY `fk_et_user` (`user_id`);

--
-- Indexes for table `favoris`
--
ALTER TABLE `favoris`
  ADD PRIMARY KEY (`favori_id`),
  ADD UNIQUE KEY `unique_favori` (`user_id`,`restaurant_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `restaurant_id` (`restaurant_id`);

--
-- Indexes for table `forum_messages`
--
ALTER TABLE `forum_messages`
  ADD PRIMARY KEY (`message_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_message_topic` (`topic_id`),
  ADD KEY `idx_message_parent` (`parent_id`);

--
-- Indexes for table `forum_notifs`
--
ALTER TABLE `forum_notifs`
  ADD PRIMARY KEY (`notif_id`),
  ADD KEY `fk_fn_user` (`user_id`),
  ADD KEY `fk_fn_topic` (`topic_id`),
  ADD KEY `fk_fn_message` (`message_id`),
  ADD KEY `idx_fn_user_unread` (`user_id`,`is_read`);

--
-- Indexes for table `forum_topics`
--
ALTER TABLE `forum_topics`
  ADD PRIMARY KEY (`topic_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_topic_categorie` (`categorie`),
  ADD KEY `idx_topic_date` (`derniere_activite`);

--
-- Indexes for table `messages_admin`
--
ALTER TABLE `messages_admin`
  ADD PRIMARY KEY (`message_id`),
  ADD KEY `idx_lu` (`lu`),
  ADD KEY `idx_type` (`type_message`),
  ADD KEY `idx_date` (`date_envoi`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `restaurant_id` (`restaurant_id`),
  ADD KEY `avis_id` (`avis_id`);

--
-- Indexes for table `notify_list`
--
ALTER TABLE `notify_list`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_notify_email` (`email`);

--
-- Indexes for table `notify_log`
--
ALTER TABLE `notify_log`
  ADD PRIMARY KEY (`log_id`);

--
-- Indexes for table `panier`
--
ALTER TABLE `panier`
  ADD PRIMARY KEY (`panier_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `plat_id` (`plat_id`);

--
-- Indexes for table `parametres_stream`
--
ALTER TABLE `parametres_stream`
  ADD PRIMARY KEY (`cle`);

--
-- Indexes for table `plats`
--
ALTER TABLE `plats`
  ADD PRIMARY KEY (`plat_id`),
  ADD KEY `restaurant_id` (`restaurant_id`);

--
-- Indexes for table `profil_stats`
--
ALTER TABLE `profil_stats`
  ADD PRIMARY KEY (`stat_id`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- Indexes for table `restaurants`
--
ALTER TABLE `restaurants`
  ADD PRIMARY KEY (`restaurant_id`),
  ADD KEY `proprietaire_id` (`proprietaire_id`);

--
-- Indexes for table `sessions_actives`
--
ALTER TABLE `sessions_actives`
  ADD PRIMARY KEY (`session_id`),
  ADD KEY `fk_sa_user` (`user_id`),
  ADD KEY `idx_sa_activite` (`derniere_activite`);

--
-- Indexes for table `tentatives_conn`
--
ALTER TABLE `tentatives_conn`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `uq_google_id` (`google_id`);

--
-- Indexes for table `user_preferences`
--
ALTER TABLE `user_preferences`
  ADD PRIMARY KEY (`pref_id`),
  ADD UNIQUE KEY `uq_pref_user` (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin_actions`
--
ALTER TABLE `admin_actions`
  MODIFY `action_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `annonces`
--
ALTER TABLE `annonces`
  MODIFY `annonce_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `avis`
--
ALTER TABLE `avis`
  MODIFY `avis_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `avis_votes`
--
ALTER TABLE `avis_votes`
  MODIFY `vote_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `bridge_messages`
--
ALTER TABLE `bridge_messages`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `captcha_logs`
--
ALTER TABLE `captcha_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `commandes`
--
ALTER TABLE `commandes`
  MODIFY `commande_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `coupons`
--
ALTER TABLE `coupons`
  MODIFY `coupon_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `email_tokens`
--
ALTER TABLE `email_tokens`
  MODIFY `token_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `favoris`
--
ALTER TABLE `favoris`
  MODIFY `favori_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `forum_messages`
--
ALTER TABLE `forum_messages`
  MODIFY `message_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `forum_notifs`
--
ALTER TABLE `forum_notifs`
  MODIFY `notif_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `forum_topics`
--
ALTER TABLE `forum_topics`
  MODIFY `topic_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `messages_admin`
--
ALTER TABLE `messages_admin`
  MODIFY `message_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notify_list`
--
ALTER TABLE `notify_list`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notify_log`
--
ALTER TABLE `notify_log`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `panier`
--
ALTER TABLE `panier`
  MODIFY `panier_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `plats`
--
ALTER TABLE `plats`
  MODIFY `plat_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `profil_stats`
--
ALTER TABLE `profil_stats`
  MODIFY `stat_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `restaurants`
--
ALTER TABLE `restaurants`
  MODIFY `restaurant_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tentatives_conn`
--
ALTER TABLE `tentatives_conn`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_preferences`
--
ALTER TABLE `user_preferences`
  MODIFY `pref_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `email_tokens`
--
ALTER TABLE `email_tokens`
  ADD CONSTRAINT `fk_et_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `favoris`
--
ALTER TABLE `favoris`
  ADD CONSTRAINT `favoris_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `favoris_ibfk_2` FOREIGN KEY (`restaurant_id`) REFERENCES `restaurants` (`restaurant_id`) ON DELETE CASCADE;

--
-- Constraints for table `forum_messages`
--
ALTER TABLE `forum_messages`
  ADD CONSTRAINT `fk_fm_parent` FOREIGN KEY (`parent_id`) REFERENCES `forum_messages` (`message_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `forum_messages_ibfk_1` FOREIGN KEY (`topic_id`) REFERENCES `forum_topics` (`topic_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `forum_messages_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `forum_notifs`
--
ALTER TABLE `forum_notifs`
  ADD CONSTRAINT `fk_fn_msg` FOREIGN KEY (`message_id`) REFERENCES `forum_messages` (`message_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_fn_topic` FOREIGN KEY (`topic_id`) REFERENCES `forum_topics` (`topic_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_fn_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `forum_topics`
--
ALTER TABLE `forum_topics`
  ADD CONSTRAINT `forum_topics_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `profil_stats`
--
ALTER TABLE `profil_stats`
  ADD CONSTRAINT `profil_stats_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `user_preferences`
--
ALTER TABLE `user_preferences`
  ADD CONSTRAINT `fk_pref_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

-- =========================
-- DONNÉES DE DÉMO
-- =========================

-- Insérer des restaurants de démonstration
INSERT INTO restaurants (proprietaire_id, restaurant_id, nom_restaurant, adresse, latitude, longitude, categorie, description_resto, verified) VALUES
(1, 1, 'Le Vélo Gourmand', '12 rue du Lac, Paris', 48.8655, 2.3212, 'Français', 'Petits plats faits maison', 1),
(1, 2, 'Sushi Koi', '45 avenue de Tokyo, Paris', 48.8566, 2.3522, 'Japonais', 'Sushis frais', 1),
(1, 3, 'Pasta Bella', '7 rue Roma, Paris', 48.8600, 2.3300, 'Italien', 'Pâtes maison', 1),
(1, 4, 'O\'TACOS', 'à côté de PAC CITY', 23, 23, 'Tacos (Français)', 'Tous nos Otacos et nos Obowls sont composés de frites et de notre sauce fromagère unique O\'TACOS®.\r\n\r\nO\'TACOS Propose des tacos customisés.\r\n\r\nO\'Tacos est une chaîne de restauration rapide qui propose un menu de tacos français. Dans les enseignes O\'Tacos, le client choisit ses ingrédients (viandes, sauces et suppléments), parmi une quarantaine d\'ingrédients dont deux sont des légumes. Les « Otacos » sont disponibles dans plusieurs tailles jusqu\'au Gigatacos, plus de deux kilogrammes. L\'enseigne utilise des viandes halal qui permet d\'accueillir un public de confession musulmane.', 1),
(1, 5, 'Pizza Time', '12 Rue du Général Leclerc', 48.8655, 2.3212, 'Pizzas', 'Pizza Time', 1),
(1, 6, 'Boulangerie du Coin', 'PAC CITY', 48.9833105, 2.2316699, 'Boulangerie Japonaise', 'On vend des patisseries asiates.', 1),
(1, 7, 'Secret Taste', '276 avenue astrid briand , les pavillions sous-bois', 48.910839, 2.514442, '', 'fast-food mais pas comme les autres SIUUUUU MWAAAAAAHHHHH', 1);

-- Insérer des plats de démonstration
INSERT INTO plats (restaurant_id, nom_plat, prix, description_plat, type_plat) VALUES 
(1, 'Planche charcuterie', 12.50, 'Assortiment de charcuterie locale', 'entree'), 
(1, 'Tartine du jour', 8.00, 'Tartine selon l\'arrivage', 'plat'),
(1, 'Crème brûlée', 6.50, 'Crème brûlée vanille', 'dessert'),
(2, 'Assortiment sushi 8 pcs', 14.00, 'Mix de nigiri et maki', 'plat'),
(2, 'California roll', 9.50, 'Avocat, crabe, concombre', 'plat'),
(2, 'Mochi glacé', 5.00, 'Assortiment de 3 mochis', 'dessert'),
(3, 'Spaghetti Carbonara', 11.00, 'Recette traditionnelle', 'plat'),
(3, 'Lasagne maison', 12.00, 'Viande & béchamel maison', 'plat'),
(3, 'Tiramisu', 6.00, 'Tiramisu fait maison', 'dessert'),
(4, 'Tacos M - 1 viande', 8.90, 'Tacos garni d\'une viande.', 'plat'),
(4, 'Tacos L - 2 viandes', 11.80, 'Tacos garni de 2 viandes', 'plat'),
(4, 'Tacos XL - 2 Tortillas, 3 Viandes', 14.70, 'Tacos avec DOUBLE TORTILLA, + 3 viandes.', 'plat'),
(4, 'GIGA TACOS', 21.30, '#SITUFINISCESTGRATUIT\r\n2,5 KG/Viandes. LA TAILLE D\'UN PLATEAU!!', 'plat'),
(4, 'EAU PLATE', 420.00, 'LODIBIDON', 'boisson'),
(5, 'Pizza Time', 15.00, 'Pizza spéciale Chef', 'plat'),
(5, 'Pizza Campionne', 13.00, 'Champignons, viande hachée + mozza', 'plat'),
(5, 'Pizza Pêcheur', 13.00, 'Oeuf coulant au centre, thon, etc.', 'plat'),
(5, 'Mozza Sticks', 5.00, 'Batonnêts de Mozzarella panés et frits au four', 'accompagnement'),
(6, 'CHICKEN BURGERR', 0.67, 'CHICKEN BURGERRRR', 'accompagnement'),
(7, 'Pâtes foréstières', 10.00, "C\'est doux", 'plat');


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