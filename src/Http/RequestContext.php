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
        ?AuthenticationContext $auth = null
    ) {
        $this->authContext = $auth ??
            AuthenticationContext::Anonymous();
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
            $auth
        );
    }
}

?>