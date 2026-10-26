<?php

declare(strict_types=1);

namespace Korrichess\Api\Core;

final class Router
{
    /** @var array<string, array<string, callable>> */
    private array $routes = [];

    public function get(string $path, callable $handler): void
    {
        $this->add('GET', $path, $handler);
    }

    public function post(string $path, callable $handler): void
    {
        $this->add('POST', $path, $handler);
    }

    public function put(string $path, callable $handler): void
    {
        $this->add('PUT', $path, $handler);
    }

    public function patch(string $path, callable $handler): void
    {
        $this->add('PATCH', $path, $handler);
    }

    public function delete(string $path, callable $handler): void
    {
        $this->add('DELETE', $path, $handler);
    }

    private function add(string $method, string $path, callable $handler): void
    {
        $this->routes[$method][$path] = $handler;
    }

    public function route(string $method, string $path): ?callable
    {
        if ($path !== '/' && str_ends_with($path, '/')) {
            $path = rtrim($path, '/');
        }
        foreach ($this->routes[$method] ?? [] as $routePath => $handler) {
            if ($routePath === $path) {
                return $handler;
            }
            $params = self::match($routePath, $path);
            if ($params !== null) {
                return static function (Request $request) use ($handler, $params): array {
                    $request->pathParams = array_merge($request->pathParams, $params);
                    return $handler($request);
                };
            }
        }
        return null;
    }

    /** @return array<string,string>|null */
    public static function match(string $routePath, string $path): ?array
    {
        $routeParts = explode('/', trim($routePath, '/'));
        $pathParts = explode('/', trim($path, '/'));
        if (count($routeParts) !== count($pathParts)) {
            return null;
        }
        $params = [];
        foreach ($routeParts as $i => $part) {
            if (str_starts_with($part, '{') && str_ends_with($part, '}')) {
                $params[trim($part, '{}')] = $pathParts[$i];
                continue;
            }
            if ($part !== $pathParts[$i]) {
                return null;
            }
        }
        return $params;
    }
}
