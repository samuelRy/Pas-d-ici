<?php
declare(strict_types=1);

// Amorçage du backend : chargement des classes, configuration, session, erreurs.
// Ce dossier ne contient AUCUN code d'affichage : il ne répond qu'en JSON.

define('BACKEND_PATH', __DIR__);

spl_autoload_register(static function (string $class): void {
    if (!preg_match('/^[A-Za-z]+$/', $class)) {
        return;
    }
    foreach (['core', 'models', 'controllers', 'services'] as $dir) {
        $file = BACKEND_PATH . "/$dir/$class.php";
        if (is_file($file)) {
            require $file;
            return;
        }
    }
});

Config::load(require BACKEND_PATH . '/config/config.php');

// Une API ne doit jamais afficher une erreur PHP brute dans sa réponse.
$debug = (bool) Config::get('app.debug', false);
ini_set('display_errors', '0');
error_reporting(E_ALL);

set_exception_handler(static function (Throwable $e) use ($debug): void {
    if ($e instanceof ValidationException) {
        Response::erreurs($e->erreurs, $e->status);
    }
    error_log($e::class . ': ' . $e->getMessage());
    Response::erreurs([$debug ? $e->getMessage() : 'Une erreur est survenue. Réessayez plus tard.', $e->getMessage()], 500);
});

header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

// Session sécurisée
$https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
session_name('danses_sid');
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => $https,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

$router = new Router();
foreach (require BACKEND_PATH . '/config/routes.php' as [$method, $path, $handler]) {
    $router->add($method, $path, $handler);
}
return $router;
