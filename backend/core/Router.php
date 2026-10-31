<?php
declare(strict_types=1);

final class Router
{
    /** @var array<int, array{0:string,1:string,2:string}> */
    private array $routes = [];

    public function add(string $method, string $path, string $handler): void
    {
        $this->routes[] = [strtoupper($method), $path, $handler];
    }

    public function dispatch(string $method, string $uri): void
    {
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        if (str_starts_with($path, '/api')) {
            $path = substr($path, 4) ?: '/';
        }
        $path = $path === '/' ? '/' : rtrim($path, '/');
        $method = strtoupper($method);

        $pathExists = false;
        foreach ($this->routes as [$routeMethod, $routePath, $handler]) {
            if ($routePath !== $path) {
                continue;
            }
            $pathExists = true;
            if ($routeMethod === $method) {
                [$class, $action] = explode('@', $handler);
                (new $class())->$action();
                return;
            }
        }

        if ($pathExists) {
            Response::erreurs(['Méthode non autorisée.'], 405);
        }
        Response::erreurs(['Ressource introuvable.'], 404);
    }
}
