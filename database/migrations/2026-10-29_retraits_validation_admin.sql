-- À exécuter une seule fois sur une base déjà installée.

USE danses_benin;
ALTER TABLE retraits
  MODIFY statut ENUM('demande', 'paye', 'refuse') NOT NULL DEFAULT 'demande',
  ADD COLUMN traite_par INT UNSIGNED NULL AFTER paye_le,
  ADD COLUMN motif_refus VARCHAR(255) NULL AFTER traite_par,
  ADD CONSTRAINT fk_retraits_traite_par FOREIGN KEY (traite_par) REFERENCES utilisateurs (id) ON DELETE SET NULL;
