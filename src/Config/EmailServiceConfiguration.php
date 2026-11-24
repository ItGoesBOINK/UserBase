<?php

declare(strict_types=1);

namespace UserBase\Config;

final class EmailServiceConfiguration
{
    public function __construct(
        public readonly ?string $serviceRoute,
        public readonly ?string $confirmationURL,
        public readonly ?string $smtpHost,
        public readonly ?int $smtpPort,
        public readonly ?string $smtpUsername,
        public readonly ?string $smtpPassword,
        public readonly ?string $senderEmailAddress,
        public readonly ?string $senderName,
        public readonly ?int $tokenLifetime
    ) {}

    public function IsConfigured(): bool {
        return $this->serviceRoute !== null &&
               $this->confirmationURL !== null &&
               $this->smtpHost !== null &&
               $this->smtpPort !== null &&
               $this->smtpUsername !== null &&
               $this->smtpPassword !== null &&
               $this->senderEmailAddress !== null &&
               $this->senderName !== null &&
               $this->tokenLifetime !== null;
    }

    public function ValidateConfiguration(): void
    {
        $values = [
            $this->serviceRoute,
            $this->confirmationURL,
            $this->smtpHost,
            $this->smtpPort,
            $this->smtpUsername,
            $this->smtpPassword,
            $this->senderEmailAddress,
            $this->senderName,
            $this->tokenLifetime
        ];

        $configuredCount = count
        (
            array_filter
            (
                $values,
                static fn ($value): bool => $value !== null
            )
        );

        if ($configuredCount > 0 && !$this->IsEmailConfigured()) {
            throw new RuntimeException('SMTP configuration is incomplete.  Configure all required e-mail service settings.');
        }
    }
}

?>