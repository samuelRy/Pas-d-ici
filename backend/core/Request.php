<?php
declare(strict_types=1);

final class Request
{
    private const MAX_OCTETS = 65536;

    /** Lit un formulaire envoyé avec des fichiers (multipart/form-data). Les fichiers sont dans $_FILES. */
    public static function form(): array
    {
        $type = $_SERVER['CONTENT_TYPE'] ?? '';
        if (stripos($type, 'multipart/form-data') !== 0) {
            Response::erreurs(['Le formulaire doit être envoyé en multipart/form-data.'], 415);
        }

        // Quand le fichier dépasse la limite de PHP, $_POST et $_FILES arrivent vides.
        if ($_POST === [] && $_FILES === [] && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
            Response::erreurs(['Le fichier dépasse la taille maximale acceptée par le serveur.'], 413);
        }
        return $_POST;
    }

    /** Lit le corps de la requête, qui doit être du JSON. */
    public static function json(): array
    {
        $type = $_SERVER['CONTENT_TYPE'] ?? '';
        if (stripos($type, 'application/json') !== 0) {
            Response::erreurs(['Le contenu doit être envoyé en JSON.'], 415);
        }

        $raw = file_get_contents('php://input', false, null, 0, self::MAX_OCTETS + 1);
        if ($raw === false || strlen($raw) > self::MAX_OCTETS) {
            Response::erreurs(['Requête trop volumineuse.'], 413);
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            Response::erreurs(['Requête invalide.'], 400);
        }
        return $data;
    }
}
