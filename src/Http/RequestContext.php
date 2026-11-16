<?php

declare(strict_types=1);

namespace UserBase\Http;

final readonly class RequestContext
{
    public AuthenticationContext $authContext;

    /**
     * @param array<string, string> $headers
     * @param array<string, mixed> $cookies
     */
    public function __construct(
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

    public static function GetActiveContext(): self
    {
        $method  = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri     = $_SERVER['REQUEST_URI']    ?? '/';
        $cookies = $_COOKIE;
        $headers = array_change_key_case(
            getallheaders() ?: [], CASE_LOWER);

        $rawInput = file_get_contents('php://input');

        if ($rawInput === false) {
            $this->Error(
                'invalid_request',
                'Unable to read request body',
                400
            );
        }

        $body = json_decode(
            $rawInput,
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        return new RequestContext(
            $method, $uri, $headers, $cookies, $body, null
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
            $this->method,
            $this->requestURI,
            $this->headers,
            $this->cookies,
            $this->body,
            $auth
        );
    }
}

?>