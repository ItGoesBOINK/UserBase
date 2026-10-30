<?php

declare(strict_types=1);

namespace UserBase\Http;

use Throwable;
use UserBase\Http\ExceptionHandler;
use UserBase\Http\RequestPipeline;
use UserBase\Http\RequestContext;

final class Router
{
    /** @var array<string, array<string, Route>> */
    private array $routes = [];

    public function __construct(
        private readonly RequestPipeline $pipeline
    ) {}

    public function Add(
        string $method,
        string $path,
        Route $route
    ): void {
        $method = strtoupper($method);
        $path = $this->NormalizePath($path);

        if (isset($this->routes[$path][$method])) {
            throw new \LogicException(
                "Route already registered: {$method} {$path}"
            );
        }

        $this->routes[$path][$method] = $route;
    }

    public function Dispatch(RequestContext $context): void
    {
        /*
        $method ??= $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $method = strtoupper($method);
        $path ??= $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($path, PHP_URL_PATH);
        */

        $method = strtoupper($context->method);
        $path = parse_url($context->requestURI, PHP_URL_PATH);

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
            //$this->routes[$path][$method]->controller->Execute();
            $this->pipeline->Process(
                $this->routes[$path][$method],
                $context
            );
        } catch (Throwable $e)
        {
            ExceptionHandler::Handle($e);
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