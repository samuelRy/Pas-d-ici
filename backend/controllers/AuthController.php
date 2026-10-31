<?php
declare(strict_types=1);

final class AuthController
{
    private const MDP_MIN = 8;
    private const MDP_MAX = 72; // limite de l'algorithme bcrypt, en octets

    /** GET /api/session : utilisateur connecté (ou null) et jeton CSRF. */
    public function session(): void
    {
        $user = Auth::user();
        Response::ok([
            'user' => $user ? Utilisateur::toPublic($user) : null,
            'csrf' => Csrf::token(),
        ]);
    }

    /** POST /api/auth/login */
    public function login(): void
    {
        Csrf::check();
        $in = Request::json();

        $email = self::cleanEmail($in['email'] ?? '');
        $mdp = is_string($in['mot_de_passe'] ?? null) ? $in['mot_de_passe'] : '';

        $user = $email !== '' ? Utilisateur::findByEmail($email) : null;

        if ($user !== null) {
            $valide = password_verify($mdp, $user['mot_de_passe']);
        } else {
            // Même durée de calcul si le compte n'existe pas : on ne révèle pas quelles adresses sont inscrites.
            password_hash($mdp, PASSWORD_DEFAULT);
            $valide = false;
        }

        if (!$valide || $user['statut_compte'] === 'suspendu') {
            Response::erreurs(['Adresse e-mail ou mot de passe incorrect.'], 401);
        }

        Auth::login($user);
        Response::ok(['user' => Utilisateur::toPublic($user)]);
    }

    /** POST /api/auth/register */
    public function register(): void
    {
        Csrf::check();
        $in = Request::json();

        $nom = is_string($in['nom'] ?? null) ? trim($in['nom']) : '';
        $email = self::cleanEmail($in['email'] ?? '');
        $mdp = is_string($in['mot_de_passe'] ?? null) ? $in['mot_de_passe'] : '';
        $role = ($in['role'] ?? '') === 'createur' ? 'createur' : 'spectateur';

        $erreurs = [];
        $longueurNom = mb_strlen($nom);
        if ($longueurNom < 2 || $longueurNom > 120) {
            $erreurs[] = 'Le nom doit contenir entre 2 et 120 caractères.';
        }
        if ($email === '') {
            $erreurs[] = 'Saisissez une adresse e-mail valide.';
        }
        if (strlen($mdp) < self::MDP_MIN || strlen($mdp) > self::MDP_MAX) {
            $erreurs[] = 'Le mot de passe doit contenir entre ' . self::MDP_MIN . ' et ' . self::MDP_MAX . ' caractères.';
        }
        if ($erreurs !== []) {
            Response::erreurs($erreurs, 422);
        }

        $dejaPris = 'Un compte existe déjà avec cette adresse e-mail.';
        if (Utilisateur::findByEmail($email) !== null) {
            Response::erreurs([$dejaPris], 409);
        }

        try {
            $id = Utilisateur::create($nom, $email, password_hash($mdp, PASSWORD_DEFAULT), $role);
        } catch (PDOException $e) {
            // 23000 : adresse prise entre-temps (deux inscriptions simultanées)
            if ($e->getCode() !== '23000') {
                throw $e;
            }
            Response::erreurs([$dejaPris], 409);
        }

        $user = Utilisateur::findById($id);
        Auth::login($user);
        Response::ok(['user' => Utilisateur::toPublic($user)], 201);
    }

    /** POST /api/auth/logout */
    public function logout(): void
    {
        Csrf::check();
        Auth::logout();
        Response::ok(null);
    }

    /** Renvoie l'adresse en minuscules si elle est valide, sinon une chaîne vide. */
    private static function cleanEmail(mixed $raw): string
    {
        if (!is_string($raw)) {
            return '';
        }
        $email = mb_strtolower(trim($raw));
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false && strlen($email) <= 190 ? $email : '';
    }
}
