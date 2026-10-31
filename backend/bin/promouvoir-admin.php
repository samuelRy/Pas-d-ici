<?php
declare(strict_types=1);

/*
 * Donne le rôle administrateur à un compte existant. À lancer en ligne de commande uniquement :
 *   php backend/bin/promouvoir-admin.php adresse@exemple.com
 * (Créer d'abord le compte par la page d'inscription.)
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

define('BACKEND_PATH', dirname(__DIR__));

spl_autoload_register(static function (string $class): void {
    if (!preg_match('/^[A-Za-z]+$/', $class)) {
        return;
    }
    foreach (['core', 'models'] as $dir) {
        $file = BACKEND_PATH . "/$dir/$class.php";
        if (is_file($file)) {
            require $file;
            return;
        }
    }
});

Config::load(require BACKEND_PATH . '/config/config.php');

$email = mb_strtolower(trim($argv[1] ?? ''));
if ($email === '') {
    fwrite(STDERR, "Usage : php backend/bin/promouvoir-admin.php adresse@exemple.com\n");
    exit(1);
}

$user = Utilisateur::findByEmail($email);
if ($user === null) {
    fwrite(STDERR, "Aucun compte avec l'adresse $email. Créez-le d'abord par la page d'inscription.\n");
    exit(1);
}

Database::pdo()
    ->prepare("UPDATE utilisateurs SET role = 'admin', statut_compte = 'actif' WHERE id = :id")
    ->execute(['id' => $user['id']]);

echo "{$user['nom']} ({$email}) est maintenant administrateur.\n";
