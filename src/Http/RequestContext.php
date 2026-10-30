<?php

declare(strict_types=1);

namespace UserBase\Http;

final readonly class RequestContext
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        public string $method,
        public string $requestURI,
        public array $headers
    ) {}

    public function GetHeader(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }
}

?>