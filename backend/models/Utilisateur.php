<?php
declare(strict_types=1);

final class Utilisateur
{
    public static function findByEmail(string $email): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM utilisateurs WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        return $stmt->fetch() ?: null;
    }

    public static function findById(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM utilisateurs WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    /** Champs que l'on peut envoyer au navigateur (jamais le mot de passe haché). */
    public static function toPublic(array $user): array
    {
        return [
            'id'            => (int) $user['id'],
            'nom'           => $user['nom'],
            'email'         => $user['email'],
            'role'          => $user['role'],
            'statut_compte' => $user['statut_compte'],
        ];
    }

    /**
     * Crée un compte. Un créateur est en attente de validation par l'administrateur
     * et reçoit aussi une ligne dans la table createurs.
     *
     * @return int identifiant du nouvel utilisateur
     */
    public static function create(string $nom, string $email, string $motDePasseHash, string $role): int
    {
        $pdo = Database::pdo();
        $statut = $role === 'createur' ? 'en_attente' : 'actif';

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO utilisateurs (nom, email, mot_de_passe, role, statut_compte)
                 VALUES (:nom, :email, :mdp, :role, :statut)'
            );
            $stmt->execute([
                'nom'    => $nom,
                'email'  => $email,
                'mdp'    => $motDePasseHash,
                'role'   => $role,
                'statut' => $statut,
            ]);
            $id = (int) $pdo->lastInsertId();

            if ($role === 'createur') {
                $pdo->prepare('INSERT INTO createurs (utilisateur_id) VALUES (:id)')->execute(['id' => $id]);
            }

            $pdo->commit();
            return $id;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
