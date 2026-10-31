<?php
declare(strict_types=1);

final class Createur
{
    /** Profil public d’un créateur actif, pour présenter ses offres d’abonnement. */
    public static function profilPublic(int $id): ?array
    {
        $stmt = Database::pdo()->prepare(
            "SELECT u.id, u.nom, c.bio, c.region_origine, u.statut_compte,
                    (SELECT COUNT(*) FROM contenus co WHERE co.createur_id = u.id AND co.statut = 'publie') AS nb_contenus
               FROM utilisateurs u
               JOIN createurs c ON c.utilisateur_id = u.id
              WHERE u.id = :id AND u.role = 'createur' AND u.statut_compte = 'actif'
              LIMIT 1"
        );
        $stmt->execute(['id' => $id]);
        $profil = $stmt->fetch();
        if (!$profil) {
            return null;
        }
        return [
            'id' => (int) $profil['id'],
            'nom' => $profil['nom'],
            'bio' => $profil['bio'],
            'region_origine' => $profil['region_origine'],
            'nb_contenus' => (int) $profil['nb_contenus'],
        ];
    }

    /** Créateurs dont le compte attend la validation de l'équipe. */
    public static function enAttente(): array
    {
        $rows = Database::pdo()->query(
            'SELECT u.id, u.nom, u.email, u.cree_le, c.region_origine, c.justificatif
               FROM utilisateurs u
               JOIN createurs c ON c.utilisateur_id = u.id
              WHERE u.role = \'createur\' AND u.statut_compte = \'en_attente\'
              ORDER BY u.cree_le ASC, u.id ASC'
        )->fetchAll();

        return array_map(static fn (array $r): array => [
            'id'             => (int) $r['id'],
            'nom'            => $r['nom'],
            'email'          => $r['email'],
            'region_origine' => $r['region_origine'],
            'justificatif'   => $r['justificatif'],
            'cree_le'        => $r['cree_le'],
        ], $rows);
    }

    /**
     * valider : le compte devient actif et peut publier.
     * refuser : le compte est suspendu (il ne peut plus se connecter).
     */
    public static function decider(int $id, int $adminId, string $decision): void
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT role, statut_compte FROM utilisateurs WHERE id = :id FOR UPDATE');
            $stmt->execute(['id' => $id]);
            $u = $stmt->fetch();

            if (!$u || $u['role'] !== 'createur') {
                throw new ValidationException(['Créateur introuvable.'], 404);
            }
            if ($u['statut_compte'] !== 'en_attente') {
                throw new ValidationException(['Ce compte a déjà été traité.'], 409);
            }

            if ($decision === 'valider') {
                $pdo->prepare('UPDATE utilisateurs SET statut_compte = \'actif\' WHERE id = :id')->execute(['id' => $id]);
                $pdo->prepare('UPDATE createurs SET valide_par = :admin, valide_le = NOW() WHERE utilisateur_id = :id')
                    ->execute(['admin' => $adminId, 'id' => $id]);
            } else {
                $pdo->prepare('UPDATE utilisateurs SET statut_compte = \'suspendu\' WHERE id = :id')->execute(['id' => $id]);
            }

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
