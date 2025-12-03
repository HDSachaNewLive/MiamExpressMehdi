CREATE DATABASE IF NOT EXISTS foodhub_db;
USE foodhub_db;

-- =========================
-- TABLE UTILISATEURS
-- =========================
CREATE TABLE IF NOT EXISTS users (
  user_id INT AUTO_INCREMENT PRIMARY KEY,
  nom_user VARCHAR(100) NOT NULL,
  email VARCHAR(100) UNIQUE NOT NULL,
  telephone VARCHAR(20),
  motdepasse VARCHAR(255) NOT NULL,
  adresse_livraison VARCHAR(255),
  type_compte ENUM('client','proprietaire') DEFAULT 'client',
  niveau INT DEFAULT 1,
  points_experience INT DEFAULT 0,
  date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================
-- TABLE RESTAURANTS
-- =========================
CREATE TABLE IF NOT EXISTS restaurants (
  restaurant_id INT AUTO_INCREMENT PRIMARY KEY,
  proprietaire_id INT,
  nom_restaurant VARCHAR(100) NOT NULL,
  adresse VARCHAR(255),
  latitude DOUBLE,
  longitude DOUBLE,
  categorie VARCHAR(50),
  description_resto TEXT,
  verified TINYINT(1) NOT NULL DEFAULT 0,
  FOREIGN KEY (proprietaire_id) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================
-- TABLE PLATS
-- =========================
CREATE TABLE IF NOT EXISTS plats (
  plat_id INT AUTO_INCREMENT PRIMARY KEY,
  restaurant_id INT NOT NULL,
  nom_plat VARCHAR(100) NOT NULL,
  prix DECIMAL(6,2) NOT NULL,
  description_plat TEXT,
  type_plat ENUM('entree', 'plat', 'accompagnement', 'boisson', 'dessert', 'sauce') DEFAULT 'plat',
  FOREIGN KEY (restaurant_id) REFERENCES restaurants(restaurant_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================
-- TABLE PANIER
-- =========================
CREATE TABLE IF NOT EXISTS panier (
  panier_id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  plat_id INT NOT NULL,
  quantite INT DEFAULT 1,
  date_ajout TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
  FOREIGN KEY (plat_id) REFERENCES plats(plat_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================
-- TABLE COUPONS (PROMOS)
-- =========================
CREATE TABLE IF NOT EXISTS coupons (
  coupon_id INT AUTO_INCREMENT PRIMARY KEY,
  code_reduction VARCHAR(100) UNIQUE NOT NULL,
  type ENUM('pourcentage', 'montant') NOT NULL,
  valeur DECIMAL(5,2) NOT NULL,
  date_debut DATETIME NOT NULL,
  date_fin DATETIME NOT NULL,
  utilisation_max INT DEFAULT NULL,
  utilisations INT DEFAULT 0,
  restaurant_id INT DEFAULT NULL,
  actif TINYINT(1) DEFAULT 1,
  date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (restaurant_id) REFERENCES restaurants(restaurant_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================
-- TABLE COMMANDES
-- =========================
CREATE TABLE IF NOT EXISTS commandes (
  commande_id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  numero_utilisateur INT NOT NULL,
  date_commande TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  statut ENUM('en_attente', 'en_preparation', 'en_livraison', 'livree', 'annulee') DEFAULT 'en_attente',
  montant_total DECIMAL(8,2),
  montant_reduction DECIMAL(8,2) DEFAULT 0,
  coupon_id INT DEFAULT NULL,
  mode_paiement ENUM('carte', 'paypal', 'livraison') DEFAULT 'carte',
  date_paiement TIMESTAMP NULL,
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
  FOREIGN KEY (coupon_id) REFERENCES coupons(coupon_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================
-- TABLE LIGNES DE COMMANDES (panier)
-- =========================
CREATE TABLE IF NOT EXISTS commande_plats (
  commande_id INT,
  plat_id INT,
  quantite INT DEFAULT 1,
  prix_unitaire DECIMAL(6,2),
  PRIMARY KEY (commande_id, plat_id),
  FOREIGN KEY (commande_id) REFERENCES commandes(commande_id) ON DELETE CASCADE,
  FOREIGN KEY (plat_id) REFERENCES plats(plat_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================
-- TABLE AVIS
-- =========================
CREATE TABLE IF NOT EXISTS avis (
  avis_id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  restaurant_id INT NOT NULL,
  note INT,
  commentaire TEXT,
  date_avis TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  reponse TEXT DEFAULT NULL,
  likes INT NOT NULL DEFAULT 0,
  dislikes INT NOT NULL DEFAULT 0,
  modifie TINYINT(1) DEFAULT 0,
  image_path VARCHAR(255) DEFAULT NULL,
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
  FOREIGN KEY (restaurant_id) REFERENCES restaurants(restaurant_id) ON DELETE CASCADE,
  CONSTRAINT chk_note CHECK (note BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================
-- TABLE DES NOTIFS
-- =========================
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type ENUM('comment', 'reply') NOT NULL,
    restaurant_id INT NOT NULL,
    avis_id INT NOT NULL,
    message VARCHAR(255) NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (restaurant_id) REFERENCES restaurants(restaurant_id) ON DELETE CASCADE,
    FOREIGN KEY (avis_id) REFERENCES avis(avis_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================
-- TABLE DES LIKES/VOTES
-- =========================
CREATE TABLE IF NOT EXISTS avis_votes (
  vote_id INT AUTO_INCREMENT PRIMARY KEY,
  avis_id INT NOT NULL,
  user_id INT NOT NULL,
  type ENUM('like','dislike') NOT NULL,
  date_vote TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_vote (avis_id, user_id),
  CONSTRAINT fk_av_vote_avis FOREIGN KEY (avis_id) REFERENCES avis(avis_id) ON DELETE CASCADE,
  CONSTRAINT fk_av_vote_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================
-- TABLE ANNONCES
-- =========================
CREATE TABLE IF NOT EXISTS annonces (
  annonce_id INT AUTO_INCREMENT PRIMARY KEY,
  titre VARCHAR(100) NOT NULL,
  message TEXT NOT NULL,
  date_debut DATETIME NOT NULL,
  date_fin DATETIME NOT NULL,
  actif TINYINT(1) DEFAULT 1,
  date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================
-- TABLE FORUM TOPICS
-- =========================
CREATE TABLE IF NOT EXISTS forum_topics (
  topic_id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  titre VARCHAR(200) NOT NULL,
  categorie ENUM('restaurants', 'recettes', 'conseils', 'general') DEFAULT 'general',
  date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  derniere_activite TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  nb_reponses INT DEFAULT 0,
  vues INT DEFAULT 0,
  epingle TINYINT(1) DEFAULT 0,
  verrouille TINYINT(1) DEFAULT 0,
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================
-- TABLE FORUM MESSAGES
-- =========================
CREATE TABLE IF NOT EXISTS forum_messages (
  message_id INT AUTO_INCREMENT PRIMARY KEY,
  topic_id INT NOT NULL,
  user_id INT NOT NULL,
  contenu TEXT NOT NULL,
  date_message TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  modifie TINYINT(1) DEFAULT 0,
  FOREIGN KEY (topic_id) REFERENCES forum_topics(topic_id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================
-- TABLE TENTATIVES CONNEXION (SÉCURITÉ)
-- =========================
CREATE TABLE IF NOT EXISTS tentatives_conn (
  id INT AUTO_INCREMENT PRIMARY KEY,
  ip VARCHAR(45) NOT NULL,
  email VARCHAR(100) DEFAULT NULL,
  attempt_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_ip_time (ip, attempt_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================
-- DONNÉES DE DÉMO
-- =========================

-- Insérer un utilisateur admin par défaut (user_id = 1)
-- INSERT INTO users (nom_user, email, telephone, motdepasse, adresse_livraison, type_compte) VALUES
-- ('NOM_EXEMPLE', 'EXEMPLE@gmail/yahoo/hotmail.com', '0123456789', '1234EXEMPLE', 'EXEMPLE_ADDRESSE', 'proprietaire');

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

-- FIN DU SCRIPT
