<?php
declare(strict_types=1);

final class Contenu
{
    /**
     * Crée une vidéo en attente de validation, avec son fichier, dans une même transaction.
     *
     * @param array{danse_id:int,titre:string,contexte:?string,mode:string,prix:?int} $d
     * @return int identifiant du contenu
     */
    public static function creerVideo(int $createurId, array $d, string $cheminFichier): int
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $pdo->prepare(
                'INSERT INTO contenus (createur_id, danse_id, type, titre, contexte_culturel, mode_monetisation, prix, statut)
                 VALUES (:createur, :danse, \'video\', :titre, :contexte, :mode, :prix, \'en_validation\')'
            )->execute([
                'createur' => $createurId,
                'danse'    => $d['danse_id'],
                'titre'    => $d['titre'],
                'contexte' => $d['contexte'],
                'mode'     => $d['mode'],
                'prix'     => $d['prix'],
            ]);
            $id = (int) $pdo->lastInsertId();

            $pdo->prepare(
                'INSERT INTO medias (contenu_id, chemin_fichier, ordre, est_couverture, est_apercu_gratuit)
                 VALUES (:contenu, :chemin, 1, 0, 0)'
            )->execute(['contenu' => $id, 'chemin' => $cheminFichier]);

            $pdo->commit();
            return $id;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /** Crée une galerie photo : la première image est la couverture. */
    public static function creerPhotos(int $createurId, array $d, array $chemins, int $photosGratuites = 1): int
    {
        if ($chemins === []) throw new ValidationException(['Choisissez au moins une photo.']);
        $pdo = Database::pdo(); $pdo->beginTransaction();
        try {
            $pdo->prepare("INSERT INTO contenus (createur_id, danse_id, type, titre, contexte_culturel, mode_monetisation, prix, statut) VALUES (:createur, :danse, 'photo', :titre, :contexte, :mode, :prix, 'en_validation')")
                ->execute(['createur'=>$createurId,'danse'=>$d['danse_id'],'titre'=>$d['titre'],'contexte'=>$d['contexte'],'mode'=>$d['mode'],'prix'=>$d['prix']]);
            $id=(int)$pdo->lastInsertId();
            $stmt=$pdo->prepare('INSERT INTO medias (contenu_id, chemin_fichier, ordre, est_couverture, est_apercu_gratuit) VALUES (:id, :chemin, :ordre, :couverture, :apercu)');
            foreach (array_values($chemins) as $i=>$path) $stmt->execute(['id'=>$id,'chemin'=>$path,'ordre'=>$i+1,'couverture'=>$i===0?1:0,'apercu'=>$i < $photosGratuites ? 1 : 0]);
            $pdo->commit(); return $id;
        } catch (Throwable $e) { if($pdo->inTransaction())$pdo->rollBack(); throw $e; }
    }

    /** Crée un article culturel et estime le temps de lecture. */
    public static function creerArticle(int $createurId, array $d, string $chapeau, string $corps, ?string $sources): int
    {
        $pdo=Database::pdo(); $pdo->beginTransaction();
        try {
            $pdo->prepare("INSERT INTO contenus (createur_id, danse_id, type, titre, contexte_culturel, mode_monetisation, prix, statut) VALUES (:createur, :danse, 'texte', :titre, :contexte, :mode, :prix, 'en_validation')")
                ->execute(['createur'=>$createurId,'danse'=>$d['danse_id'],'titre'=>$d['titre'],'contexte'=>$d['contexte'],'mode'=>$d['mode'],'prix'=>$d['prix']]);
            $id=(int)$pdo->lastInsertId();
            $mots=preg_match_all('/[\p{L}\p{N}]+/u', strip_tags($corps));
            $pdo->prepare('INSERT INTO articles (contenu_id, chapeau, corps, sources, temps_lecture_min) VALUES (:id, :chapeau, :corps, :sources, :temps)')
                ->execute(['id'=>$id,'chapeau'=>$chapeau,'corps'=>$corps,'sources'=>$sources,'temps'=>max(1,(int)ceil(($mots?:0)/200))]);
            $pdo->commit(); return $id;
        } catch (Throwable $e) { if($pdo->inTransaction())$pdo->rollBack(); throw $e; }
    }

    public static function article(int $id): ?array
    {
        $stmt=Database::pdo()->prepare('SELECT chapeau, corps, sources, temps_lecture_min FROM articles WHERE contenu_id = :id LIMIT 1');
        $stmt->execute(['id'=>$id]); return $stmt->fetch() ?: null;
    }

    /** Contenus d'un créateur, du plus récent au plus ancien. */
    public static function parCreateur(int $createurId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT c.id, c.type, c.titre, c.mode_monetisation, c.prix, c.statut, c.motif_refus, c.nb_vues, c.cree_le, d.nom AS danse
               FROM contenus c
               JOIN danses d ON d.id = c.danse_id
              WHERE c.createur_id = :id
              ORDER BY c.cree_le DESC, c.id DESC'
        );
        $stmt->execute(['id' => $createurId]);

        return array_map(static fn (array $r): array => [
            'id'                => (int) $r['id'],
            'type'              => $r['type'],
            'titre'             => $r['titre'],
            'danse'             => $r['danse'],
            'mode_monetisation' => $r['mode_monetisation'],
            'prix'              => $r['prix'] !== null ? (int) $r['prix'] : null,
            'statut'            => $r['statut'],
            'motif_refus'       => $r['motif_refus'],
            'nb_vues'           => (int) $r['nb_vues'],
            'cree_le'           => $r['cree_le'],
        ], $stmt->fetchAll());
    }

    /** Contenus à valider, du plus ancien au plus récent. */
    public static function enValidation(): array
    {
        $rows = Database::pdo()->query(
            'SELECT c.id, c.type, c.titre, c.contexte_culturel, c.mode_monetisation, c.prix, c.cree_le, a.chapeau, a.corps AS article_corps, a.sources,
                    d.nom AS danse, u.nom AS createur
               FROM contenus c
               JOIN danses d ON d.id = c.danse_id
               JOIN utilisateurs u ON u.id = c.createur_id
          LEFT JOIN articles a ON a.contenu_id = c.id
              WHERE c.statut = \'en_validation\'
              ORDER BY c.cree_le ASC, c.id ASC'
        )->fetchAll();

        return array_map(static fn (array $r): array => [
            'id'                => (int) $r['id'],
            'type'              => $r['type'],
            'titre'             => $r['titre'],
            'danse'             => $r['danse'],
            'createur'          => $r['createur'],
            'contexte_culturel' => $r['contexte_culturel'],
            'chapeau' => $r['chapeau'] ?? null,
            'article_corps' => $r['article_corps'] ?? null,
            'sources' => $r['sources'] ?? null,
            'mode_monetisation' => $r['mode_monetisation'],
            'prix'              => $r['prix'] !== null ? (int) $r['prix'] : null,
            'cree_le'           => $r['cree_le'],
        ], $rows);
    }

    /** Chemin du premier fichier d'un contenu (relatif à storage/uploads), ou null. */
    public static function cheminMedia(int $contenuId, ?int $mediaId = null): ?string
    {
        if ($mediaId !== null) {
            $stmt = Database::pdo()->prepare('SELECT chemin_fichier FROM medias WHERE contenu_id = :id AND id = :media LIMIT 1');
            $stmt->execute(['id' => $contenuId, 'media' => $mediaId]);
        } else {
            $stmt = Database::pdo()->prepare('SELECT chemin_fichier FROM medias WHERE contenu_id = :id ORDER BY ordre, id LIMIT 1');
            $stmt->execute(['id' => $contenuId]);
        }
        $chemin = $stmt->fetchColumn();
        return $chemin === false ? null : (string) $chemin;
    }

    public static function medias(int $contenuId): array
    {
        $stmt = Database::pdo()->prepare('SELECT id, ordre, est_couverture, est_apercu_gratuit FROM medias WHERE contenu_id = :id ORDER BY ordre, id');
        $stmt->execute(['id' => $contenuId]);
        return array_map(static fn(array $r): array => ['id'=>(int)$r['id'], 'ordre'=>(int)$r['ordre'], 'couverture'=>(bool)$r['est_couverture'], 'apercu_gratuit'=>(bool)$r['est_apercu_gratuit']], $stmt->fetchAll());
    }

    public static function estApercuGratuit(int $contenuId, int $mediaId): bool
    {
        $stmt = Database::pdo()->prepare('SELECT est_apercu_gratuit FROM medias WHERE contenu_id = :id AND id = :media LIMIT 1');
        $stmt->execute(['id' => $contenuId, 'media' => $mediaId]);
        return (bool) $stmt->fetchColumn();
    }

    /**
     * Applique une décision de modération.
     * approuver / refuser : seulement sur un contenu « en validation ».
     * remettre_en_attente : annule une décision, sauf si le contenu a déjà été acheté.
     */
    public static function moderer(int $id, int $adminId, string $decision, ?string $motif): void
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT statut FROM contenus WHERE id = :id FOR UPDATE');
            $stmt->execute(['id' => $id]);
            $statut = $stmt->fetchColumn();

            if ($statut === false) {
                throw new ValidationException(['Contenu introuvable.'], 404);
            }

            if ($decision === 'approuver' || $decision === 'refuser') {
                if ($statut !== 'en_validation') {
                    throw new ValidationException(['Ce contenu a déjà été traité.'], 409);
                }
                $pdo->prepare(
                    'UPDATE contenus
                        SET statut = :statut, motif_refus = :motif, modere_par = :admin,
                            publie_le = IF(:publie = 1, NOW(), NULL)
                      WHERE id = :id'
                )->execute([
                    'statut' => $decision === 'approuver' ? 'publie' : 'refuse',
                    'motif'  => $decision === 'refuser' ? $motif : null,
                    'admin'  => $adminId,
                    'publie' => $decision === 'approuver' ? 1 : 0,
                    'id'     => $id,
                ]);
            } else { // remettre_en_attente
                if (!in_array($statut, ['publie', 'refuse'], true)) {
                    throw new ValidationException(['Ce contenu est déjà en attente de validation.'], 409);
                }
                $achats = $pdo->prepare('SELECT COUNT(*) FROM transactions WHERE contenu_id = :id');
                $achats->execute(['id' => $id]);
                if ((int) $achats->fetchColumn() > 0) {
                    throw new ValidationException(['Ce contenu a déjà été acheté : sa décision ne peut plus être annulée.'], 409);
                }
                $pdo->prepare(
                    'UPDATE contenus SET statut = \'en_validation\', motif_refus = NULL, modere_par = NULL, publie_le = NULL WHERE id = :id'
                )->execute(['id' => $id]);
            }

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /** Types de contenu acceptés comme filtre. */
    public const TYPES = ['video', 'photo', 'texte'];

    /**
     * Contenus publiés, du plus récent au plus ancien, avec filtres facultatifs.
     *
     * @return array{items: array, total: int}
     */
    public static function publies(?int $danseId, ?string $type, string $recherche, int $limite, int $decalage): array
    {
        $conditions = ['c.statut = \'publie\''];
        $params = [];

        if ($danseId !== null) {
            $conditions[] = 'c.danse_id = :danse';
            $params['danse'] = $danseId;
        }
        if ($type !== null) {
            $conditions[] = 'c.type = :type';
            $params['type'] = $type;
        }
        if ($recherche !== '') {
            // % et _ saisis par l'utilisateur sont pris pour des caractères ordinaires
            $motif = '%' . addcslashes($recherche, '%_\\') . '%';
            $conditions[] = '(c.titre LIKE :q1 OR u.nom LIKE :q2)';
            $params['q1'] = $motif;
            $params['q2'] = $motif;
        }
        $where = implode(' AND ', $conditions);
        $pdo = Database::pdo();

        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM contenus c JOIN utilisateurs u ON u.id = c.createur_id WHERE ' . $where
        );
        foreach ($params as $nom => $valeur) {
            $stmt->bindValue(':' . $nom, $valeur, is_int($valeur) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();
        $total = (int) $stmt->fetchColumn();

        $stmt = $pdo->prepare(
            'SELECT c.id, c.type, c.titre, c.mode_monetisation, c.prix, c.publie_le, d.nom AS danse, u.nom AS createur
               FROM contenus c
               JOIN danses d ON d.id = c.danse_id
               JOIN utilisateurs u ON u.id = c.createur_id
              WHERE ' . $where . '
              ORDER BY c.publie_le DESC, c.id DESC
              LIMIT :limite OFFSET :decalage'
        );
        foreach ($params as $nom => $valeur) {
            $stmt->bindValue(':' . $nom, $valeur, is_int($valeur) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->bindValue(':decalage', $decalage, PDO::PARAM_INT);
        $stmt->execute();

        $items = array_map(static fn (array $r): array => [
            'id'                => (int) $r['id'],
            'type'              => $r['type'],
            'titre'             => $r['titre'],
            'danse'             => $r['danse'],
            'createur'          => $r['createur'],
            'mode_monetisation' => $r['mode_monetisation'],
            'prix'              => $r['prix'] !== null ? (int) $r['prix'] : null,
            'publie_le'         => $r['publie_le'],
        ], $stmt->fetchAll());

        return ['items' => $items, 'total' => $total];
    }

    /** Un contenu avec ses informations d'affichage (quel que soit son statut), ou null. */
    public static function trouver(int $id): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT c.id, c.createur_id, c.type, c.titre, c.contexte_culturel, c.mode_monetisation, c.prix,
                    c.statut, c.nb_vues, c.publie_le, d.nom AS danse, d.region_origine, u.nom AS createur
               FROM contenus c
               JOIN danses d ON d.id = c.danse_id
               JOIN utilisateurs u ON u.id = c.createur_id
              WHERE c.id = :id
              LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    /** Qui peut voir la fiche : tout le monde si publié ; sinon l'administrateur et le créateur. */
    public static function estVisible(?array $user, array $contenu): bool
    {
        return $contenu['statut'] === 'publie' || self::estProprietaireOuAdmin($user, $contenu);
    }

    /** Détermine l'accès au média en fonction de sa publication et de la monétisation. */
    public static function peutLire(?array $user, array $contenu): bool
    {
        if (self::estProprietaireOuAdmin($user, $contenu)) {
            return true;
        }
        if ($contenu['statut'] !== 'publie') {

            return false;
        }
        if ($contenu['mode_monetisation'] === 'gratuit') {
            return true;
        }


        if ($user === null || $user['statut_compte'] !== 'actif') {
            return false;
        }

        if ($contenu['mode_monetisation'] === 'abonnement') {
            $stmt = Database::pdo()->prepare(
                "SELECT 1 FROM abonnements
                 WHERE abonne_id = :abonne AND createur_id = :createur
                   AND statut = 'actif' AND fin > NOW() LIMIT 1"
            );
            $stmt->execute(['abonne' => (int) $user['id'], 'createur' => (int) $contenu['createur_id']]);
            return (bool) $stmt->fetchColumn();
        }
        if ($contenu['mode_monetisation'] === 'unite') {
            $stmt = Database::pdo()->prepare(
                "SELECT 1 FROM transactions
                 WHERE utilisateur_id = :abonne AND contenu_id = :contenu
                   AND statut = 'confirme' LIMIT 1"
            );
            $stmt->execute(['abonne' => (int) $user['id'], 'contenu' => (int) $contenu['id']]);
            return (bool) $stmt->fetchColumn();
        }
        return false;
    }


    public static function estProprietaireOuAdmin(?array $user, array $contenu): bool
    {

        if ($user === null || $user['statut_compte'] !== 'actif') {
            return false;
        }
        return $user['role'] === 'admin' || (int) $user['id'] === (int) $contenu['createur_id'];
    }


    public static function compterVue(int $id): void
    {
        Database::pdo()
            ->prepare('UPDATE contenus SET nb_vues = nb_vues + 1 WHERE id = :id AND statut = \'publie\'')
            ->execute(['id' => $id]);
    }


    
}
