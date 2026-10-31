<?php
declare(strict_types=1);

/**
 * Protection contre les requêtes envoyées à l'insu de l'utilisateur (CSRF).
 * Le jeton est donné par GET /api/session puis renvoyé dans l'en-tête X-CSRF-Token.
 */
final class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf'];
    }

    public static function check(): void
    {
        $sent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!is_string($sent) || !hash_equals(self::token(), $sent)) {
            Response::erreurs(['Requête refusée (jeton de sécurité invalide).'], 403);
        }
    }
}
