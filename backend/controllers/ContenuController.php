<?php
declare(strict_types=1);

final class ContenuController
{
    private const MODES = ['gratuit', 'unite', 'abonnement'];

    /** POST /api/contenus (multipart/form-data) : vidéo, galerie photo ou article culturel. */
    public function creer(): void
    {
        Csrf::check();
        $user = self::createur(true);
        $in = Request::form();
        $erreurs = [];
        $type = is_string($in['type'] ?? null) ? $in['type'] : 'video';
        if (!in_array($type, Contenu::TYPES, true)) $erreurs[] = 'Choisissez un type de contenu valide.';
        $titre = is_string($in['titre'] ?? null) ? trim($in['titre']) : '';
        if (mb_strlen($titre) < 3 || mb_strlen($titre) > 200) $erreurs[] = 'Le titre doit contenir entre 3 et 200 caractères.';
        $danseId = filter_var($in['danse_id'] ?? null, FILTER_VALIDATE_INT);
        if ($danseId === false || !Danse::exists((int) $danseId)) $erreurs[] = 'Choisissez la danse concernée.';
        $contexte = is_string($in['contexte_culturel'] ?? null) ? trim($in['contexte_culturel']) : '';
        if (mb_strlen($contexte) > 5000) $erreurs[] = 'Le contexte culturel est limité à 5000 caractères.';
        $mode = is_string($in['mode_monetisation'] ?? null) ? $in['mode_monetisation'] : 'gratuit';
        if (!in_array($mode, self::MODES, true)) $erreurs[] = 'Choisissez un mode de monétisation.';
        $prix = null;
        if ($mode === 'unite') {
            $raw = is_string($in['prix'] ?? null) ? trim($in['prix']) : '';
            if (!ctype_digit($raw) || (int) $raw < 1 || (int) $raw > 10000000) $erreurs[] = 'Indiquez un prix en FCFA (nombre entier positif).';
            else $prix = (int) $raw;
        }
        $chapeau = is_string($in['chapeau'] ?? null) ? trim($in['chapeau']) : '';
        $corps = is_string($in['corps'] ?? null) ? trim($in['corps']) : '';
        $sources = is_string($in['sources'] ?? null) ? trim($in['sources']) : '';
        $photosGratuites = filter_var($in['photos_gratuites'] ?? 1, FILTER_VALIDATE_INT);
        if ($photosGratuites === false || $photosGratuites < 0 || $photosGratuites > 20) $erreurs[] = 'Le nombre de photos visibles gratuitement doit être compris entre 0 et 20.';
        if ($type === 'texte') {
            if (mb_strlen($chapeau) < 10 || mb_strlen($chapeau) > 1500) $erreurs[] = 'Le chapeau doit contenir entre 10 et 1500 caractères.';
            if (mb_strlen($corps) < 50 || mb_strlen($corps) > 50000) $erreurs[] = 'Le corps de l’article doit contenir entre 50 et 50000 caractères.';
            if (mb_strlen($sources) > 5000) $erreurs[] = 'Les sources sont limitées à 5000 caractères.';
        }
        if ($erreurs !== []) throw new ValidationException($erreurs);

        $cheminVideo = null; $cheminsPhotos = [];
        try {
            if ($type === 'video') {
                $cheminVideo = StockageService::enregistrerVideo($_FILES['fichier'] ?? null);
            } elseif ($type === 'photo') {
                $uploads = $_FILES['fichiers'] ?? $_FILES['fichier'] ?? null;
                if (!is_array($uploads) || !isset($uploads['name'])) throw new ValidationException(['Choisissez au moins une photo.']);
                if (is_array($uploads['name'])) {
                    $indices = array_keys(array_filter($uploads['name'], static fn ($name) => $name !== ''));
                    if (count($indices) < 1 || count($indices) > 20) throw new ValidationException(['Choisissez entre 1 et 20 photos.']);
                    foreach ($indices as $i) {
                        $cheminsPhotos[] = StockageService::enregistrerImage(['name' => $uploads['name'][$i], 'type' => $uploads['type'][$i] ?? '', 'tmp_name' => $uploads['tmp_name'][$i], 'error' => $uploads['error'][$i], 'size' => $uploads['size'][$i]]);
                    }
                } else {
                    $cheminsPhotos[] = StockageService::enregistrerImage($uploads);
                }
            }
            $donnees = ['danse_id' => (int) $danseId, 'titre' => $titre, 'contexte' => $contexte !== '' ? $contexte : null, 'mode' => $mode, 'prix' => $prix];
            if ($type === 'video') $id = Contenu::creerVideo((int) $user['id'], $donnees, (string) $cheminVideo);
            elseif ($type === 'photo') $id = Contenu::creerPhotos((int) $user['id'], $donnees, $cheminsPhotos, (int) $photosGratuites);
            else $id = Contenu::creerArticle((int) $user['id'], $donnees, $chapeau, $corps, $sources !== '' ? $sources : null);
        } catch (Throwable $e) {
            if ($cheminVideo !== null) StockageService::supprimer($cheminVideo);
            foreach ($cheminsPhotos as $path) StockageService::supprimer($path);
            throw $e;
        }
        Response::ok(['id' => $id, 'statut' => 'en_validation'], 201);
    }

    /** GET /api/contenus?danse_id=&type=&q=&limite=&decalage= : contenus publiés (public). */
    public function liste(): void
    {
        $danseId = null;
        if (isset($_GET['danse_id']) && $_GET['danse_id'] !== '') {
            $danseId = filter_var($_GET['danse_id'], FILTER_VALIDATE_INT);
            if ($danseId === false) {
                throw new ValidationException(['Danse invalide.'], 400);
            }
        }

        $type = null;
        if (isset($_GET['type']) && $_GET['type'] !== '') {
            if (!is_string($_GET['type']) || !in_array($_GET['type'], Contenu::TYPES, true)) {
                throw new ValidationException(['Type de contenu invalide.'], 400);
            }
            $type = $_GET['type'];
        }

        $recherche = is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';
        $recherche = mb_substr($recherche, 0, 100);

        $limite = filter_var($_GET['limite'] ?? 20, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 50]]);
        $decalage = filter_var($_GET['decalage'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        if ($limite === false || $decalage === false) {
            throw new ValidationException(['Pagination invalide.'], 400);
        }

        $resultat = Contenu::publies($danseId, $type, $recherche, $limite, $decalage);
        Response::ok($resultat + ['limite' => $limite, 'decalage' => $decalage]);
    }

    /** GET /api/contenus/detail?id= : fiche d'un contenu (public s'il est publié). */
    public function detail(): void
    {
        $user = Auth::user();
        $contenu = self::contenuVisible($user);

        // Une vue est comptée une fois par session, jamais pour le créateur ni l'administrateur.
        $id = (int) $contenu['id'];
        if ($contenu['statut'] === 'publie' && !Contenu::estProprietaireOuAdmin($user, $contenu)) {
            $deja = $_SESSION['vues'] ?? [];
            if (!in_array($id, $deja, true)) {
                Contenu::compterVue($id);
                $deja[] = $id;
                $_SESSION['vues'] = array_slice($deja, -200);
            }
        }

        $article = $contenu['type'] === 'texte' ? Contenu::article($id) : null;
        $peutLire = Contenu::peutLire($user, $contenu);
        Response::ok([
            'id'                => $id,
            'createur_id'       => (int) $contenu['createur_id'],
            'type'              => $contenu['type'],
            'titre'             => $contenu['titre'],
            'danse'             => $contenu['danse'],
            'region_origine'    => $contenu['region_origine'],
            'createur'          => $contenu['createur'],
            'contexte_culturel' => $contenu['contexte_culturel'],
            'mode_monetisation' => $contenu['mode_monetisation'],
            'prix'              => $contenu['prix'] !== null ? (int) $contenu['prix'] : null,
            'statut'            => $contenu['statut'],
            'publie_le'         => $contenu['publie_le'],
            'peut_lire'         => $peutLire,
            'chapeau'           => $article['chapeau'] ?? null,
            'corps'             => ($peutLire && $article !== null) ? $article['corps'] : null,
            'sources'           => ($peutLire && $article !== null) ? $article['sources'] : null,
            'temps_lecture_min' => $article['temps_lecture_min'] ?? null,
            'medias' => $contenu['type'] === 'photo' ? Contenu::medias($id) : [],
        ]);
    }

    /** GET /api/contenus/media?id= : le fichier, seulement si l'utilisateur a le droit de le lire. */
    public function media(): void
    {
        $user = Auth::user();
        $contenu = self::contenuVisible($user);

        $mediaId = isset($_GET['media_id']) ? filter_var($_GET['media_id'], FILTER_VALIDATE_INT) : null;
        if ($mediaId === false) Response::erreurs(['Photo invalide.'], 400);
        if (!Contenu::peutLire($user, $contenu) && ($mediaId === null || !Contenu::estApercuGratuit((int) $contenu['id'], (int) $mediaId))) {
            Response::erreurs(['Ce contenu est payant : accès réservé.'], 403);
        }

        $chemin = Contenu::cheminMedia((int) $contenu['id'], $mediaId === null ? null : (int) $mediaId);
        if ($chemin === null) {
            Response::erreurs(['Fichier introuvable.'], 404);
        }
        StockageService::envoyer($chemin);
    }

    /** Contenu demandé par ?id=, ou réponse 404 (on ne révèle pas l'existence d'un contenu non publié). */
    private static function contenuVisible(?array $user): array
    {
        $id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
        $contenu = $id === false ? null : Contenu::trouver($id);

        if ($contenu === null || !Contenu::estVisible($user, $contenu)) {
            Response::erreurs(['Contenu introuvable.'], 404);
        }
        return $contenu;
    }

    /** GET /api/mes-contenus : contenus du créateur connecté, tous statuts confondus. */
    public function mesContenus(): void
    {
        $user = self::createur(false);
        Response::ok(Contenu::parCreateur((int) $user['id']));
    }

    /**
     * Vérifie que l'utilisateur est un créateur.
     * Pour publier ($valide = true), son compte doit en plus avoir été validé par l'équipe.
     */
    private static function createur(bool $valide): array
    {
        $user = Auth::requireUser();

        if ($user['role'] !== 'createur') {
            Response::erreurs(['Seuls les comptes créateurs peuvent faire cela.'], 403);
        }
        if ($user['statut_compte'] === 'suspendu') {
            Response::erreurs(['Votre compte est suspendu.'], 403);
        }
        if ($valide && $user['statut_compte'] !== 'actif') {
            Response::erreurs(['Votre compte créateur est en cours de vérification : vous pourrez publier dès sa validation.'], 403);
        }
        return $user;
    }
}
