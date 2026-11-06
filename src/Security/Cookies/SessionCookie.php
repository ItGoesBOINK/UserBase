<?php

declare(strict_types=1);

namespace UserBase\Security\Cookies;

use UserBase\Http\RequestContext;
use UserBase\Config\SessionSettings;
use InvalidArgumentException;

final class SessionCookie
{
    public function __construct(
        public readonly bool $isSecure,
        public readonly SessionSettings $settings
    ) {
        if ($settings->lifetimeSeconds <= 0) {
            throw new InvalidArgumentException
            ('Session Cookie life-time must be greater than zero');
        }
    }

    public function GetToken(RequestContext $context): ?string
    { return $context->GetCookie($this->settings->cookieName); }

    public function Set(string $token): bool
    {
        return setcookie(
            $this->settings->cookieName,
            $token,
            $this->GetOptions(time() + $this->settings->lifetimeSeconds)
        );
    }

    public function Clear(): bool
    {
        return setcookie(
            $this->settings->cookieName,
            '',
            $this->GetOptions(time() - 3600)
        );
    }

    /**
     * @return array{
     *      expires: int,
     *      path: string,
     *      secure: bool,
     *      httponly: bool,
     *      samesite: string
     * }
     */
    private function GetOptions(int $expires): array
    {
        return [
            'expires' => $expires,
            'path' => $this->settings->cookiePath,
            'secure' => true, //$this->isSecure,
            'httponly' => true, // $this->settings->httpOnly,
            'samesite' => $this->settings->sameSitePolicy
        ];
    }
}

?>