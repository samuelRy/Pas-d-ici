<?php
declare(strict_types=1);

// Réglages lus dans les variables d'environnement.
// Ne jamais écrire de mot de passe réel dans ce fichier ni le publier.

return [
    'app' => [
        'debug' => filter_var(getenv('APP_DEBUG') ?: 'false', FILTER_VALIDATE_BOOLEAN),
    ],
    'upload' => [
        // Taille maximale d'une vidéo, en Mo (PHP doit aussi l'autoriser : voir le README).
        'video_max_mo' => (int) (getenv('UPLOAD_VIDEO_MAX_MO') ?: 200),
    ],
    'db' => [
        'host' => getenv('DB_HOST') ?: '127.0.0.1',
        'port' => getenv('DB_PORT') ?: '3306',
        'name' => getenv('DB_NAME') ?: 'danses_benin',
        'user' => getenv('DB_USER') ?: 'root',
        'pass' => getenv('DB_PASS') ?: '',
    ],
];
