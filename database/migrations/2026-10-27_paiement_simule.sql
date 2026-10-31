-- ============================================================
-- Migration : paiement simulé (sans prestataire externe)
-- À exécuter UNE SEULE FOIS sur une base créée avec l'ancien schema.sql.
-- (Une base créée avec le nouveau schema.sql n'en a pas besoin.)
--   mysql -u root -p danses_benin < database/migrations/2026-10-27_paiement_simule.sql
-- ============================================================

USE danses_benin;

-- 1. La référence d'une transaction est générée par la plateforme.
ALTER TABLE transactions
  DROP INDEX uq_transactions_reference,
  CHANGE reference_agregateur reference VARCHAR(40) NULL
    COMMENT 'Référence interne générée par la plateforme (paiement simulé)';

UPDATE transactions SET reference = CONCAT('SIM-', id) WHERE reference IS NULL;

ALTER TABLE transactions
  MODIFY reference VARCHAR(40) NOT NULL
    COMMENT 'Référence interne générée par la plateforme (paiement simulé)',
  ADD UNIQUE KEY uq_transactions_reference (reference);

-- 2. Tarifs des abonnements (FCFA) et commission de la plateforme (0.10 = 10 %).
--    Si une valeur existe déjà, elle est conservée.
INSERT INTO parametres (cle, valeur) VALUES
  ('prix_abonnement_mensuel', '2500'),
  ('prix_abonnement_annuel',  '25000'),
  ('taux_commission',         '0.10')
ON DUPLICATE KEY UPDATE cle = cle;
