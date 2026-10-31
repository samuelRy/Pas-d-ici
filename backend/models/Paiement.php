<?php
declare(strict_types=1);

/**
 * Paiement SIMULÉ des abonnements : aucun prestataire externe, aucun argent débité.
 *
 * La plateforme fait exactement ce que ferait un paiement confirmé :
 * elle enregistre l'abonnement et la transaction, calcule la commission et la part du créateur,
 * et crédite le solde du créateur. Brancher un vrai prestataire plus tard ne changerait que
 * l'étape « le paiement est confirmé » dans souscrire().
 *
 * Les tarifs et le taux de commission sont lus dans la table `parametres`.
 */
final class Paiement
{
    /** Formules d'abonnement => durée en mois. */
    public const FORMULES = ['mensuel' => 1, 'annuel' => 12];

    /** Moyens de paiement proposés (valeurs de transactions.moyen_paiement). */
    public const MOYENS = ['mtn_momo', 'moov_money', 'carte'];

    /**
     * Tarifs en FCFA et taux de commission (0.10 = 10 %).
     *
     * @return array{mensuel:int, annuel:int, taux_commission:float}
     */
    public static function tarifs(): array
    {
        $cles = ['prix_abonnement_mensuel', 'prix_abonnement_annuel', 'taux_commission'];
        $lignes = Database::pdo()
            ->query("SELECT cle, valeur FROM parametres WHERE cle IN ('" . implode("','", $cles) . "')")
            ->fetchAll(PDO::FETCH_KEY_PAIR);

        foreach ($cles as $cle) {
            if (!isset($lignes[$cle])) {
                throw new RuntimeException("Paramètre manquant dans la table parametres : $cle (voir database/schema.sql).");
            }
        }

        $mensuel = (int) $lignes['prix_abonnement_mensuel'];
        $annuel = (int) $lignes['prix_abonnement_annuel'];
        $taux = (float) $lignes['taux_commission'];
        if ($mensuel < 1 || $annuel < 1 || $taux < 0 || $taux > 1) {
            throw new RuntimeException('Paramètres de paiement invalides dans la table parametres.');
        }

        return ['mensuel' => $mensuel, 'annuel' => $annuel, 'taux_commission' => $taux];
    }

    /** Abonnement en cours d'un utilisateur auprès d'un créateur, ou null. */
    public static function abonnementActif(int $abonneId, int $createurId): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT id, formule, debut, fin
               FROM abonnements
              WHERE abonne_id = :abonne AND createur_id = :createur AND statut = \'actif\' AND fin > NOW()
              ORDER BY fin DESC
              LIMIT 1'
        );
        $stmt->execute(['abonne' => $abonneId, 'createur' => $createurId]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Crée un abonnement avec son paiement simulé (toujours « confirmé »).
     * Tout se fait dans une seule transaction : si une étape échoue, rien n'est enregistré.
     *
     * @return array{abonnement_id:int, reference:string, formule:string, montant:int, commission:int, part_createur:int, fin:string}
     */
    public static function souscrire(int $abonneId, int $createurId, string $formule, string $moyen): array
    {
        $tarifs = self::tarifs();
        $montant = $tarifs[$formule];
        $commission = (int) round($montant * $tarifs['taux_commission']);
        $part = $montant - $commission;
        $reference = 'SIM-' . strtoupper(bin2hex(random_bytes(6)));

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            // Le verrou sur le créateur empêche deux abonnements simultanés de se doubler.
            $stmt = $pdo->prepare(
                'SELECT u.statut_compte
                   FROM utilisateurs u
                   JOIN createurs c ON c.utilisateur_id = u.id
                  WHERE u.id = :id AND u.role = \'createur\'
                    FOR UPDATE'
            );
            $stmt->execute(['id' => $createurId]);
            if ($stmt->fetchColumn() !== 'actif') {
                throw new ValidationException(['Ce créateur n\'est pas disponible pour un abonnement.'], 404);
            }

            $actif = self::abonnementActif($abonneId, $createurId);
            if ($actif !== null) {
                throw new ValidationException(['Vous êtes déjà abonné à ce créateur jusqu\'au ' . substr((string) $actif['fin'], 0, 10) . '.'], 409);
            }

            $pdo->prepare(
                'INSERT INTO abonnements (abonne_id, createur_id, formule, debut, fin, statut)
                 VALUES (:abonne, :createur, :formule, NOW(), DATE_ADD(NOW(), INTERVAL :mois MONTH), \'actif\')'
            )->execute([
                'abonne'   => $abonneId,
                'createur' => $createurId,
                'formule'  => $formule,
                'mois'     => self::FORMULES[$formule],
            ]);
            $abonnementId = (int) $pdo->lastInsertId();

            $pdo->prepare(
                'INSERT INTO transactions
                    (utilisateur_id, contenu_id, abonnement_id, montant, commission_plateforme, part_createur, moyen_paiement, reference, statut)
                 VALUES (:utilisateur, NULL, :abonnement, :montant, :commission, :part, :moyen, :reference, \'confirme\')'
            )->execute([
                'utilisateur' => $abonneId,
                'abonnement'  => $abonnementId,
                'montant'     => $montant,
                'commission'  => $commission,
                'part'        => $part,
                'moyen'       => $moyen,
                'reference'   => $reference,
            ]);

            // La part du créateur s'ajoute à son solde, qu'il pourra retirer plus tard.
            $pdo->prepare('UPDATE createurs SET solde_disponible = solde_disponible + :part WHERE utilisateur_id = :id')
                ->execute(['part' => $part, 'id' => $createurId]);

            $fin = $pdo->prepare('SELECT fin FROM abonnements WHERE id = :id');
            $fin->execute(['id' => $abonnementId]);
            $finAbonnement = (string) $fin->fetchColumn();

            $pdo->commit();

            return [
                'abonnement_id' => $abonnementId,
                'reference'     => $reference,
                'formule'       => $formule,
                'montant'       => $montant,
                'commission'    => $commission,
                'part_createur' => $part,
                'fin'           => $finAbonnement,
            ];
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
    public static function contenu(int $abonneId, int $contenuId, int $createurId, float $montant, string $moyen): array
    {
        $tarifs = self::tarifs();
        $reference = 'SIM-' . strtoupper(bin2hex(random_bytes(6)));
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT createur_id, prix, mode_monetisation, statut FROM contenus WHERE id = :id FOR UPDATE');
            $stmt->execute(['id' => $contenuId]);
            $contenu = $stmt->fetch();
            if (!$contenu || $contenu['statut'] !== 'publie') throw new ValidationException(['Ce contenu n’est pas disponible à l’achat.'], 404);
            if ($contenu['mode_monetisation'] !== 'unite' || $contenu['prix'] === null) throw new ValidationException(['Ce contenu n’est pas proposé à l’achat à l’unité.'], 422);
            if ((int)$contenu['createur_id'] !== $createurId || (float)$contenu['prix'] !== $montant) throw new ValidationException(['Le prix ou le créateur ne correspond pas au contenu.'], 422);
            if ($abonneId === (int)$contenu['createur_id']) throw new ValidationException(['Vous ne pouvez pas acheter votre propre contenu.'], 403);
            $check = $pdo->prepare("SELECT 1 FROM transactions WHERE utilisateur_id = :user AND contenu_id = :contenu AND statut = 'confirme' LIMIT 1");
            $check->execute(['user'=>$abonneId,'contenu'=>$contenuId]);
            if ($check->fetchColumn()) throw new ValidationException(['Vous avez déjà acheté ce contenu.'], 409);
            $commission = (int) round($montant * $tarifs['taux_commission']);
            $part = (int)$montant - $commission;
            $pdo->prepare("INSERT INTO transactions (utilisateur_id, contenu_id, abonnement_id, montant, commission_plateforme, part_createur, moyen_paiement, reference, statut) VALUES (:utilisateur, :contenu, NULL, :montant, :commission, :part, :moyen, :reference, 'confirme')")
                ->execute(['utilisateur'=>$abonneId,'contenu'=>$contenuId,'montant'=>$montant,'commission'=>$commission,'part'=>$part,'moyen'=>$moyen,'reference'=>$reference]);
            $pdo->prepare('UPDATE createurs SET solde_disponible = solde_disponible + :part WHERE utilisateur_id = :id')->execute(['part'=>$part,'id'=>$createurId]);
            $pdo->commit();
            return ['reference'=>$reference,'montant'=>(int)$montant,'commission'=>$commission,'part_createur'=>$part];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    public static function retrait(int $createurId, float $montant, string $moyen, string $numero): array
    {
        if (!in_array($moyen, ['mtn_momo', 'moov_money'], true)) {
            throw new ValidationException(['Choisissez MTN MoMo ou Moov Money.']);
        }
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT solde_disponible FROM createurs WHERE utilisateur_id = :id FOR UPDATE');
            $stmt->execute(['id' => $createurId]);
            $solde = $stmt->fetchColumn();
            if ($solde === false) throw new ValidationException(['Compte créateur introuvable.'], 404);
            if ((float) $solde < $montant) throw new ValidationException(['Votre solde disponible est insuffisant.'], 422);

            // Le montant est réservé dès la demande pour éviter plusieurs demandes au-delà du solde.
            $pdo->prepare("INSERT INTO retraits (createur_id, montant, operateur, numero_telephone, statut) VALUES (:createur, :montant, :operateur, :numero, 'demande')")
                ->execute(['createur' => $createurId, 'montant' => $montant, 'operateur' => $moyen, 'numero' => $numero]);
            $pdo->prepare('UPDATE createurs SET solde_disponible = solde_disponible - :montant WHERE utilisateur_id = :id')
                ->execute(['montant' => $montant, 'id' => $createurId]);
            $id = (int) $pdo->lastInsertId();
            $pdo->commit();
            return ['retrait_id' => $id, 'montant' => $montant, 'statut' => 'demande'];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    /** Demandes de retrait en attente, réservées à l'administration. */
    public static function retraitsEnAttente(): array
    {
        $stmt = Database::pdo()->query("SELECT r.id, r.createur_id, r.montant, r.operateur, r.numero_telephone, r.demande_le, u.nom AS createur, u.email FROM retraits r JOIN utilisateurs u ON u.id = r.createur_id WHERE r.statut = 'demande' ORDER BY r.demande_le ASC, r.id ASC");
        return array_map(static fn (array $r): array => [
            'id' => (int) $r['id'], 'createur_id' => (int) $r['createur_id'],
            'montant' => (int) $r['montant'], 'operateur' => $r['operateur'],
            'numero_telephone' => $r['numero_telephone'], 'demande_le' => $r['demande_le'],
            'createur' => $r['createur'], 'email' => $r['email'],
        ], $stmt->fetchAll());
    }

    /** Historique des retraits d'un créateur. */
    public static function retraitsCreateur(int $createurId): array
    {
        $stmt = Database::pdo()->prepare('SELECT id, montant, operateur, statut, demande_le, paye_le, motif_refus FROM retraits WHERE createur_id = :id ORDER BY demande_le DESC, id DESC LIMIT 20');
        $stmt->execute(['id' => $createurId]);
        return array_map(static fn (array $r): array => [
            'id' => (int) $r['id'], 'montant' => (int) $r['montant'], 'operateur' => $r['operateur'],
            'statut' => $r['statut'], 'demande_le' => $r['demande_le'], 'paye_le' => $r['paye_le'],
            'motif_refus' => $r['motif_refus'] ?? null,
        ], $stmt->fetchAll());
    }

    /** L'administrateur confirme le paiement ou refuse et rembourse le solde réservé. */
    public static function deciderRetrait(int $retraitId, int $adminId, string $decision, ?string $motif = null): void
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT id, createur_id, montant, statut FROM retraits WHERE id = :id FOR UPDATE');
            $stmt->execute(['id' => $retraitId]);
            $r = $stmt->fetch();
            if (!$r) throw new ValidationException(['Demande de retrait introuvable.'], 404);
            if ($r['statut'] !== 'demande') throw new ValidationException(['Cette demande a déjà été traitée.'], 409);
            if ($decision === 'approuver') {
                $pdo->prepare("UPDATE retraits SET statut = 'paye', paye_le = NOW(), traite_par = :admin WHERE id = :id")
                    ->execute(['admin' => $adminId, 'id' => $retraitId]);
            } elseif ($decision === 'refuser') {
                $pdo->prepare("UPDATE retraits SET statut = 'refuse', traite_par = :admin, motif_refus = :motif WHERE id = :id")
                    ->execute(['admin' => $adminId, 'motif' => $motif, 'id' => $retraitId]);
                $pdo->prepare('UPDATE createurs SET solde_disponible = solde_disponible + :montant WHERE utilisateur_id = :id')
                    ->execute(['montant' => $r['montant'], 'id' => $r['createur_id']]);
            } else {
                throw new ValidationException(['Décision de retrait invalide.']);
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }
}
