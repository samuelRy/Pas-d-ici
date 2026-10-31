<?php
declare(strict_types=1);

final class Auth
{
    private static ?array $current = null;
    private static bool $loaded = false;

    /** Ouvre la session d'un utilisateur (change l'identifiant de session). */
    public static function login(array $user): void
    {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        self::$loaded = false;
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires'  => time() - 42000,
                'path'     => $p['path'],
                'domain'   => $p['domain'],
                'secure'   => $p['secure'],
                'httponly' => $p['httponly'],
                'samesite' => $p['samesite'] ?? 'Lax',
            ]);
        }
        session_destroy();
        self::$loaded = false;
        self::$current = null;
    }

    /** Utilisateur connecté, ou null. */
    public static function user(): ?array
    {
        if (!self::$loaded) {
            $id = $_SESSION['user_id'] ?? null;
            self::$current = is_int($id) ? Utilisateur::findById($id) : null;
            self::$loaded = true;
        }
        return self::$current;
    }

    /** Pour les routes d'administration : renvoie l'administrateur ou répond 401 / 403. */
    public static function requireAdmin(): array
    {
        $user = self::requireUser();
        if ($user['role'] !== 'admin' || $user['statut_compte'] !== 'actif') {
            Response::erreurs(['Accès réservé aux administrateurs.'], 403);
        }
        return $user;
    }

    /** Pour les routes protégées : renvoie l'utilisateur ou répond 401. */
    public static function requireUser(): array
    {
        $user = self::user();
        if ($user === null) {
            Response::erreurs(['Connexion requise.'], 401);
        }
        return $user;
    }
}
