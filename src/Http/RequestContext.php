<?php

declare(strict_types=1);

namespace UserBase\Http;

use JsonException;
use InvalidArgumentException;

final readonly class RequestContext
{
    public AuthenticationContext $authContext;

    /**
     * @param array<string, string> $headers
     * @param array<string, mixed> $cookies
     */
    public function __construct(
        public string $clientIP,
        public string $method,
        public string $requestURI,
        public array $headers,
        public array $cookies,
        public array $body = [],
        ?AuthenticationContext $auth = null
    ) {
        $this->authContext = $auth ??
            AuthenticationContext::Anonymous();
    }

    public static function GetContext(
        ?string $clientIP = null,
        ?string $method = null,
        ?string $requestURI = null,
        ?array $headers = null,
        ?array $cookies = null,
        ?array $body = null,
        ?AuthenticationContext $auth = null
    ): self
    {
        $ip      = $clientIP   ?? $_SERVER['REMOTE_ADDR']    ?? '<NONE>';
        $method  = $method     ?? $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri     = $requestURI ?? $_SERVER['REQUEST_URI']    ?? '/';
        $cookies = $cookies    ?? $_COOKIE;
        $headers = self::CollectHeaders($headers);

        $rawInput = file_get_contents('php://input');

        if ($rawInput === false) {
            throw new InvalidArgumentException('Unable to read request body');
        }

        $body = [];

        if (trim($rawInput) !== '') {
            try {
                $body = json_decode(
                    $rawInput,
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                );
            } catch (JsonException $e) {
                throw new InvalidArgumentException('Request body must contain valid JSON');
            }
        }

        if (!is_array($body)) {
            throw new InvalidArgumentException('Request body must contain a valid JSON object or array');
        }

        return new RequestContext(
            $ip, $method, $uri, $headers, $cookies, $body, null
        );
    }

    public static function GetActiveContext(): self
    {
        $ip      = $_SERVER['REMOTE_ADDR'] ?? '<NONE>';
        $method  = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri     = $_SERVER['REQUEST_URI']    ?? '/';
        $cookies = $_COOKIE;
        $headers = self::CollectHeaders();

        $rawInput = file_get_contents('php://input');

        if ($rawInput === false) {
            throw new InvalidArgumentException('Unable to read request body');
        }

        $body = [];

        if (trim($rawInput) !== '') {
            try {
                $body = json_decode(
                    $rawInput,
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                );
            } catch (JsonException $e) {
                throw new InvalidArgumentException('Request body must contain valid JSON');
            }
        }

        if (!is_array($body)) {
            throw new InvalidArgumentException('Request body must contain a valid JSON object or array');
        }

        return new RequestContext(
            $ip, $method, $uri, $headers, $cookies, $body, null
        );
    }

    public function GetHeader(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    public function GetCookie(string $name): ?string
    {
        $value = $this->cookies[$name] ?? null;
        return is_string($value) ? $value : null;
    }

    public function WithAuthentication(
        AuthenticationContext $auth
    ): self {
        return new self(
            $this->clientIP,
            $this->method,
            $this->requestURI,
            $this->headers,
            $this->cookies,
            $this->body,
            $auth
        );
    }

    private static function CollectHeaders(?array $headers = null): array
    {
        $headers = $headers ?? (
            function_exists('getallheaders')
                ? getallheaders()
                : self::RetrieveHeadersFromServer()
        );

        return array_change_key_case($headers ?: [], CASE_LOWER);
    }

    private static function RetrieveHeadersFromServer(): array
    {
        $headers = [];

        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = str_replace('_', '-', substr($key, 3));
                $headers[$name] = $value;
            }
        }

        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['Content-Type'] = $_SERVER['CONTENT_TYPE'];
        }

        if (isset($_SERVER['CONTENT_LENGTH'])) {
            $headers['Content-Length'] = $_SERVER['CONTENT_LENGTH'];
        }

        return $headers;
    }
}

?>