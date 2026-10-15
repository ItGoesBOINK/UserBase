<?php

declare(strict_types=1);

namespace UserBase\Config;

use RuntimeException;

final class Configuration
{
    public readonly string $databaseHost;
    public readonly int $databasePort;
    public readonly string $databaseName;
    public readonly string $databaseUser;
    public readonly string $databasePassword;

    public readonly ?string $smtpHost;
    public readonly ?int $smtpPort;
    public readonly ?string $smtpUsername;
    public readonly ?string $smtpPassword;
    public readonly ?string $mailFromAddress;
    public readonly ?string $mailFromName;

    public readonly ?string $verificationUrlBase;

    public function __construct()
    {
        $this->databaseHost = self::Required('MYSQL_HOST');
        $this->databasePort = self::RequiredPort('MYSQL_PORT');
        $this->databaseName = self::Required('MYSQL_DATABASE');
        $this->databaseUser = self::Required('MYSQL_USER');
        $this->databasePassword = self::Required('MYSQL_PASSWORD');

        $this->smtpHost = self::Optional('SMTP_HOST');
        $this->smtpPort = self::OptionalPort('SMTP_PORT');
        $this->smtpUsername = self::Optional('SMTP_USERNAME');
        $this->smtpPassword = self::Optional('SMTP_PASSWORD');
        $this->mailFromAddress = self::Optional('MAIL_FROM_ADDRESS');
        $this->mailFromName = self::Optional('MAIL_FROM_NAME');

        $this->verificationUrlBase = self::Optional('VERIFICATION_URL_BASE');

        $this->ValidateSMTPConfiguration();
    }

    public function IsEmailConfigured(): bool
    {
        return $this->smtpHost !== null &&
               $this->smtpPort !== null &&
               $this->smtpUsername !== null &&
               $this->smtpPassword !== null &&
               $this->mailFromAddress !== null &&
               $this->mailFromName !== null;
    }

    private function ValidateSMTPConfiguration(): void
    {
        $values = [
            $this->smtpHost,
            $this->smtpPort,
            $this->smtpUsername,
            $this->smtpPassword,
            $this->mailFromAddress,
            $this->mailFromName,
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

    private static function Required(string $name): string
    {
        $value = self::Optional($name);

        if ($value === null)
        {
            throw new RuntimeException("Required configuration is missing: {$name}");
        }

        return $value;
    }

    private static function Optional(string $name): ?string
    {
        $value = getenv($name);

        if ($value === false || trim($value) === '' ) {
            return null;
        }

        return trim($value);
    }

    private static function RequiredPort(string $name): int
    {
        $value = self::Required($name);

        if (!ctype_digit($value) || (int) $value < 1 || (int) $value > 65535)
            throw new RuntimeException("Configuration {$name} must be a valid TCP port!");

        return (int) $value;
    }

    private static function OptionalPort(string $name): ?int
    {
        $value = self::Optional($name);

        if ($value === null) return null;

        if (!ctype_digit($value) || (int) $value < 1 || (int) $value > 65535)
            throw new RuntimeException("Configuration {$name} must be a valid TCP port!");

        return (int) $value;
    }


}

?>