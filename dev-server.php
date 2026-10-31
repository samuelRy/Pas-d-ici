<?php
declare(strict_types=1);

/*
 * Aide pour le développement local uniquement.
 * Sert le frontend et envoie les adresses /api/... vers le backend, comme le ferait
 * un vrai serveur web. Lancer :  php -S localhost:8000 dev-server.php
 */

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

if ($path === '/api' || str_starts_with($path, '/api/')) {
    require __DIR__ . '/backend/public/index.php';
    return true;
}

$root = realpath(__DIR__ . '/frontend');
$file = realpath($root . ($path === '/' ? '/index.html' : $path));

// realpath + préfixe : impossible de sortir du dossier frontend (../)
if ($file === false || !str_starts_with($file, $root . DIRECTORY_SEPARATOR) || !is_file($file)) {
    http_response_code(404);
    echo 'Page introuvable';
    return true;
}

$types = [
    'html' => 'text/html; charset=utf-8',
    'css'  => 'text/css; charset=utf-8',
    'js'   => 'text/javascript; charset=utf-8',
    'svg'  => 'image/svg+xml',
    'png'  => 'image/png',
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'ico'  => 'image/x-icon',
];
$extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
if (!isset($types[$extension])) {
    http_response_code(404);
    return true;
}

header('Content-Type: ' . $types[$extension]);
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' https://fonts.googleapis.com; font-src https://fonts.gstatic.com; img-src 'self' data: blob:; connect-src 'self'; form-action 'self'; frame-ancestors 'none'");
readfile($file);
return true;
