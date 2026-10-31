<?php
declare(strict_types=1);

/** Toutes les réponses de l'API ont la même forme : {ok, data} ou {ok, erreurs}. */
final class Response
{
    public static function json(array $payload, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        exit;
    }

    public static function ok(mixed $data = null, int $status = 200): never
    {
        self::json(['ok' => true, 'data' => $data], $status);
    }

    /** @param string[] $messages */
    public static function erreurs(array $messages, int $status = 422): never
    {
        self::json(['ok' => false, 'erreurs' => array_values($messages)], $status);
    }
}
