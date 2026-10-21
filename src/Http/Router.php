<?php

declare(strict_types=1);

namespace UserBase\Http;


final class Router
{
    private array $routes = [];

    public function Add(
        string $method,
        string $path,
        callable $handler
    ): void {
        $method = strtoupper($method);
        $path = $this->NormalizePath($path);

        if (isset($this->routes[$path][$method])) {
            throw new \LogicException(
                "Route already registered: {$method} {$path}"
            );
        }

        $this->routes[$path][$method] = $handler;
    }

    public function Dispatch(?string $method, ?string $path): void
    {
        $method ??= $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri ??= $_SERVER['REQUEST_URI'] ?? '/';

        $method = strtoupper($method);

        $path = parse_url($uri, PHP_URL_PATH);

        if (!is_string($path)) {
            JsonResponse::Error(
                'bad_request',
                'Invalid Request Path',
                400
            );
        }

        $path = $this->NormalizePath($path);

        if (!isset($this->routes[$path])) {
            JsonResponse::Error(
                'not_found',
                'The requested end-point was not found',
                404
            );
        }

        if (!isset($this->routes[$path][$method])) {
            header('Allow: ' . implode(', ', array_keys($this->routes[$path])));

            JsonResponse::Error(
                'method_not_allowed',
                'This end-point does not accept the requested HTTP method',
                405
            );
        }

        try {
            ($this->routes[$path][$method])();
        } catch (Exception $e)
        {
            JsonResponse::Error(
                'internal_server_error',
                'An Internal Server Error occurred',
                500
            );
        }
    }


    private function NormalizePath(string $path): string {
        $path = parse_url($path, PHP_URL_PATH);

        if (!is_string($path) || $path === '')
        {
            return '/';
        }

        return rtrim($path, '/') ?: '/';
    }
}



?>