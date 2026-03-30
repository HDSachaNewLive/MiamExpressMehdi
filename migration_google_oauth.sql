-- ============================================================
-- Migration Google OAuth — FoodHub
-- À exécuter UNE SEULE FOIS dans phpMyAdmin ou via MySQL CLI
-- ============================================================

USE `foodhub_db`;

-- Ajouter la colonne google_id (identifiant unique renvoyé par Google)
ALTER TABLE `users`
    ADD COLUMN `google_id` VARCHAR(100) DEFAULT NULL AFTER `motdepasse`,
    ADD UNIQUE KEY `uq_google_id` (`google_id`);

-- Ajouter la colonne google_photo (URL de la photo de profil Google)
ALTER TABLE `users`
    ADD COLUMN `google_photo` VARCHAR(512) DEFAULT NULL AFTER `google_id`;

-- ============================================================
-- Vérification (optionnel)
-- ============================================================
-- DESCRIBE `users`;
