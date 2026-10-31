-- ============================================================
-- Plateforme de valorisation et de monétisation des danses
-- traditionnelles béninoises : schéma de base de données (MVP)
-- Cible : MySQL 8.0.16+ ou MariaDB 10.5+ (moteur InnoDB)
-- ============================================================

SET NAMES utf8mb4;

CREATE DATABASE IF NOT EXISTS danses_benin
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
-- Si la base existait déjà avec un autre jeu de caractères, on la corrige
-- (les tables créées ensuite héritent de ce réglage).
ALTER DATABASE danses_benin
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
USE danses_benin;

-- ------------------------------------------------------------
-- Comptes : spectateurs, créateurs et administrateurs
-- ------------------------------------------------------------
CREATE TABLE utilisateurs (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nom              VARCHAR(120) NOT NULL,
  email            VARCHAR(190) NOT NULL,
  mot_de_passe     VARCHAR(255) NOT NULL COMMENT 'Hash (password_hash), jamais en clair',
  role             ENUM('spectateur', 'createur', 'admin') NOT NULL DEFAULT 'spectateur',
  statut_compte    ENUM('en_attente', 'actif', 'suspendu') NOT NULL DEFAULT 'actif',
  cree_le          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_utilisateurs_email (email)
) ENGINE=InnoDB;

-- Informations propres aux créateurs (un créateur = un utilisateur)
CREATE TABLE createurs (
  utilisateur_id   INT UNSIGNED NOT NULL,
  bio              TEXT NULL,
  region_origine   VARCHAR(120) NULL,
  justificatif     VARCHAR(255) NULL COMMENT 'Lien ou pièce fournie pour la validation',
  solde_disponible DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  valide_par       INT UNSIGNED NULL,
  valide_le        DATETIME NULL,
  PRIMARY KEY (utilisateur_id),
  CONSTRAINT fk_createurs_utilisateur FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs (id) ON DELETE CASCADE,
  CONSTRAINT fk_createurs_valide_par  FOREIGN KEY (valide_par)     REFERENCES utilisateurs (id) ON DELETE SET NULL,
  CONSTRAINT ck_createurs_solde CHECK (solde_disponible >= 0)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Danses du corpus du MVP
-- ------------------------------------------------------------
CREATE TABLE danses (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nom              VARCHAR(80) NOT NULL,
  region_origine   VARCHAR(120) NULL,
  contexte         TEXT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_danses_nom (nom)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Contenus : champs communs à la photo, la vidéo et le texte
-- ------------------------------------------------------------
CREATE TABLE contenus (
  id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  createur_id        INT UNSIGNED NOT NULL,
  danse_id           INT UNSIGNED NOT NULL,
  type               ENUM('photo', 'video', 'texte') NOT NULL,
  titre              VARCHAR(200) NOT NULL,
  contexte_culturel  TEXT NULL,
  mode_monetisation  ENUM('gratuit', 'unite', 'abonnement') NOT NULL DEFAULT 'gratuit',
  prix               DECIMAL(10,2) NULL COMMENT 'Obligatoire si mode_monetisation = unite',
  statut             ENUM('brouillon', 'en_validation', 'publie', 'refuse') NOT NULL DEFAULT 'en_validation',
  motif_refus        VARCHAR(255) NULL,
  modere_par         INT UNSIGNED NULL,
  nb_vues            INT UNSIGNED NOT NULL DEFAULT 0,
  cree_le            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  publie_le          DATETIME NULL,
  PRIMARY KEY (id),
  KEY idx_contenus_danse_type (danse_id, type, statut),
  KEY idx_contenus_createur (createur_id),
  CONSTRAINT fk_contenus_createur  FOREIGN KEY (createur_id) REFERENCES createurs (utilisateur_id) ON DELETE CASCADE,
  CONSTRAINT fk_contenus_danse     FOREIGN KEY (danse_id)    REFERENCES danses (id),
  CONSTRAINT fk_contenus_modere_par FOREIGN KEY (modere_par) REFERENCES utilisateurs (id) ON DELETE SET NULL,
  CONSTRAINT ck_contenus_prix CHECK (mode_monetisation <> 'unite' OR (prix IS NOT NULL AND prix > 0))
) ENGINE=InnoDB;

-- Fichiers d'un contenu photo (galerie) ou vidéo
CREATE TABLE medias (
  id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  contenu_id         INT UNSIGNED NOT NULL,
  chemin_fichier     VARCHAR(255) NOT NULL COMMENT 'Chemin dans storage/uploads, hors du dossier public',
  ordre              SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  est_couverture     TINYINT(1) NOT NULL DEFAULT 0,
  est_apercu_gratuit TINYINT(1) NOT NULL DEFAULT 0,
  duree_secondes     INT UNSIGNED NULL COMMENT 'Pour les vidéos uniquement',
  PRIMARY KEY (id),
  KEY idx_medias_contenu (contenu_id, ordre),
  CONSTRAINT fk_medias_contenu FOREIGN KEY (contenu_id) REFERENCES contenus (id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Partie propre aux contenus de type texte
CREATE TABLE articles (
  contenu_id         INT UNSIGNED NOT NULL,
  chapeau            TEXT NOT NULL,
  corps              MEDIUMTEXT NOT NULL,
  sources            TEXT NULL,
  temps_lecture_min  SMALLINT UNSIGNED NULL,
  PRIMARY KEY (contenu_id),
  CONSTRAINT fk_articles_contenu FOREIGN KEY (contenu_id) REFERENCES contenus (id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Abonnements, paiements et retraits
-- ------------------------------------------------------------
CREATE TABLE abonnements (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  abonne_id        INT UNSIGNED NOT NULL,
  createur_id      INT UNSIGNED NOT NULL,
  formule          ENUM('mensuel', 'annuel') NOT NULL,
  debut            DATETIME NOT NULL,
  fin              DATETIME NOT NULL,
  statut           ENUM('actif', 'expire', 'resilie') NOT NULL DEFAULT 'actif',
  PRIMARY KEY (id),
  KEY idx_abonnements_abonne (abonne_id, statut),
  KEY idx_abonnements_createur (createur_id),
  CONSTRAINT fk_abonnements_abonne   FOREIGN KEY (abonne_id)   REFERENCES utilisateurs (id) ON DELETE CASCADE,
  CONSTRAINT fk_abonnements_createur FOREIGN KEY (createur_id) REFERENCES createurs (utilisateur_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Un paiement concerne soit un contenu acheté à l'unité, soit un abonnement
CREATE TABLE transactions (
  id                    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  utilisateur_id        INT UNSIGNED NOT NULL,
  contenu_id            INT UNSIGNED NULL,
  abonnement_id         INT UNSIGNED NULL,
  montant               DECIMAL(12,2) NOT NULL,
  commission_plateforme DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  part_createur         DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  moyen_paiement        ENUM('mtn_momo', 'moov_money', 'carte') NOT NULL,
  reference             VARCHAR(40) NOT NULL COMMENT 'Référence interne générée par la plateforme (paiement simulé)',
  statut                ENUM('en_attente', 'confirme', 'echoue') NOT NULL DEFAULT 'en_attente',
  cree_le               DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_transactions_reference (reference),
  KEY idx_transactions_utilisateur (utilisateur_id),
  CONSTRAINT fk_transactions_utilisateur FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs (id),
  CONSTRAINT fk_transactions_contenu     FOREIGN KEY (contenu_id)     REFERENCES contenus (id),
  CONSTRAINT fk_transactions_abonnement  FOREIGN KEY (abonnement_id)  REFERENCES abonnements (id),
  CONSTRAINT ck_transactions_objet CHECK ((contenu_id IS NULL) <> (abonnement_id IS NULL)),
  CONSTRAINT ck_transactions_montant CHECK (montant > 0 AND commission_plateforme >= 0 AND part_createur >= 0)
) ENGINE=InnoDB;

CREATE TABLE retraits (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  createur_id      INT UNSIGNED NOT NULL,
  montant          DECIMAL(12,2) NOT NULL,
  operateur        ENUM('mtn_momo', 'moov_money') NOT NULL,
  numero_telephone VARCHAR(30) NOT NULL,
  statut           ENUM('demande', 'paye', 'refuse') NOT NULL DEFAULT 'demande',
  traite_par       INT UNSIGNED NULL,
  motif_refus      VARCHAR(255) NULL,
  demande_le       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  paye_le          DATETIME NULL,
  PRIMARY KEY (id),
  KEY idx_retraits_statut (statut),
  CONSTRAINT fk_retraits_createur FOREIGN KEY (createur_id) REFERENCES createurs (utilisateur_id),
  CONSTRAINT fk_retraits_traite_par FOREIGN KEY (traite_par) REFERENCES utilisateurs (id) ON DELETE SET NULL,
  CONSTRAINT ck_retraits_montant CHECK (montant > 0)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Interactions et réglages
-- ------------------------------------------------------------
CREATE TABLE commentaires (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  contenu_id       INT UNSIGNED NOT NULL,
  utilisateur_id   INT UNSIGNED NOT NULL,
  note             TINYINT UNSIGNED NULL,
  texte            TEXT NULL,
  cree_le          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_commentaires_contenu (contenu_id),
  CONSTRAINT fk_commentaires_contenu     FOREIGN KEY (contenu_id)     REFERENCES contenus (id) ON DELETE CASCADE,
  CONSTRAINT fk_commentaires_utilisateur FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs (id) ON DELETE CASCADE,
  CONSTRAINT ck_commentaires_note CHECK (note IS NULL OR note BETWEEN 1 AND 5)
) ENGINE=InnoDB;

CREATE TABLE parametres (
  cle              VARCHAR(60) NOT NULL,
  valeur           VARCHAR(255) NOT NULL,
  PRIMARY KEY (cle)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Données initiales
-- Paiement SIMULÉ : aucun prestataire externe, aucun argent réel.
-- Tarifs en FCFA ; taux_commission = part de la plateforme (0.10 = 10 %).
-- Ces valeurs se changent ici, sans modifier le code.
-- ------------------------------------------------------------
INSERT INTO parametres (cle, valeur) VALUES
  ('prix_abonnement_mensuel', '2500'),
  ('prix_abonnement_annuel',  '25000'),
  ('taux_commission',         '0.10');

INSERT INTO danses (nom, region_origine) VALUES
  ('Tèkè',         'Borgou / Alibori (Bariba)'),
  ('Agbadja',      'Aire Adja-Tado'),
  ('Guèlèdè',      'Aire Yoruba / Nago'),
  ('Massè Gohoun', NULL);
