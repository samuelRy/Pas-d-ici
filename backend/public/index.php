<?php
declare(strict_types=1);

// Point d'entrée unique de l'API : toutes les adresses en /api/... arrivent ici.

$router = require dirname(__DIR__) . '/bootstrap.php';
$router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $_SERVER['REQUEST_URI'] ?? '/');
