<?php

declare(strict_types=1);

namespace UserBase\Config;

use RuntimeException;

final class Configuration
{
    public readonly string $appURL;
    public readonly string $uiURL;
    public readonly string $apiRoot;
    public readonly string $csrfKey;

    public readonly bool $allowAuthCookie;
    public readonly bool $allowAuthBearer;
    public readonly bool $allowMultipleSessions;

    public readonly string $databaseHost;
    public readonly int $databasePort;
    public readonly string $databaseName;
    public readonly string $databaseUser;
    public readonly string $databasePassword;

    public readonly string $rateLimitKey;
    public readonly int $rateLimitCleanup_CRONBatchSize;
    public readonly int $rateLimitMaxRetentionHours;

    public readonly SessionSettings $session;

    /*
    public readonly ?string $smtpHost;
    public readonly ?int $smtpPort;
    public readonly ?string $smtpUsername;
    public readonly ?string $smtpPassword;
    public readonly ?string $mailFromAddress;
    public readonly ?string $mailFromName;
    */

    public readonly EmailServiceConfiguration $accountVerificationEmailConfig;
    public readonly EmailServiceConfiguration $passwordResetEmailConfig;

    public readonly ?string $verificationUrlBase;
    public readonly ?string $verificationRoute;
    public readonly ?string $verificationPage;

    public readonly array $corsAllowedOrigins;

    public function __construct()
    {
        $this->appURL = self::Required('APP_URL');
        $this->uiURL = self::Required('UI_URL');
        $this->apiRoot = self::Required('API_ROOT');

        $this->allowAuthCookie = self::RequiredBool("ALLOW_AUTH_COOKIE");
        $this->allowAuthBearer = self::RequiredBool("ALLOW_AUTH_BEARER");
        $this->allowMultipleSessions = self::RequiredBool("ALLOW_MULTIPLE_SESSIONS");


        $this->csrfKey = self::Required('CSRF_KEY');

        $this->databaseHost = self::Required('MYSQL_HOST');
        $this->databasePort = self::RequiredPort('MYSQL_PORT');
        $this->databaseName = self::Required('MYSQL_DATABASE');
        $this->databaseUser = self::Required('MYSQL_USER');
        $this->databasePassword = self::Required('MYSQL_PASSWORD');

        $this->rateLimitKey = self::Required('RATELIMIT_KEY');
        $this->rateLimitCleanup_CRONBatchSize =
            self::RequiredInt("RATELIMIT_CRON_BATCH_SIZE");
        $this->rateLimitMaxRetentionHours =
            max(24, self::RequiredInt("RATELIMIT_MAX_RETENTION_HOURS"));

        /*
        $this->smtpHost = self::Optional('SMTP_HOST');
        $this->smtpPort = self::OptionalPort('SMTP_PORT');
        $this->smtpUsername = self::Optional('SMTP_USERNAME');
        $this->smtpPassword = self::Optional('SMTP_PASSWORD');
        $this->mailFromAddress = self::Optional('MAIL_FROM_ADDRESS');
        $this->mailFromName = self::Optional('MAIL_FROM_NAME');
        */

        $this->accountVerificationEmailConfig = new EmailServiceConfiguration(
            self::Required('ACCOUNT_VERIFICATION__SERVICE_ROUTE'),
            self::Required('ACCOUNT_VERIFICATION__CONFIRMATION_URL'),
            self::Required('ACCOUNT_VERIFICATION__SMTP_HOST'),
            self::RequiredPort('ACCOUNT_VERIFICATION__SMTP_PORT'),
            self::Required('ACCOUNT_VERIFICATION__SMTP_USERNAME'),
            self::Required('ACCOUNT_VERIFICATION__SMTP_PASSWORD'),
            self::Required('ACCOUNT_VERIFICATION__SENDER_ADDRESS'),
            self::Required('ACCOUNT_VERIFICATION__SENDER_NAME'),
            self::RequiredInt('ACCOUNT_VERIFICATION__TOKEN_LIFETIME')
        );

        $this->passwordResetEmailConfig = new EmailServiceConfiguration(
            self::Required('PASSWORD_RESET__SERVICE_ROUTE'),
            self::Required('PASSWORD_RESET__CONFIRMATION_URL'),
            self::Required('PASSWORD_RESET__SMTP_HOST'),
            self::RequiredPort('PASSWORD_RESET__SMTP_PORT'),
            self::Required('PASSWORD_RESET__SMTP_USERNAME'),
            self::Required('PASSWORD_RESET__SMTP_PASSWORD'),
            self::Required('PASSWORD_RESET__SENDER_ADDRESS'),
            self::Required('PASSWORD_RESET__SENDER_NAME'),
            self::RequiredInt('PASSWORD_RESET__TOKEN_LIFETIME')
        );

        $this->session = new SessionSettings(
            self::Required('SESSION_COOKIE_NAME'),
            self::Required('SESSION_COOKIE_PATH'),
            self::Required('SESSION_COOKIE_SAME_SITE'),
            self::RequiredBool('SESSION_COOKIE_HTTP_ONLY'),
            self::RequiredInt('SESSION_LIFETIME')
        );

        // $this->verificationUrlBase = self::Optional('VERIFICATION_URL_BASE');

        $this->corsAllowedOrigins = self::OptionalList('CORS_ALLOWED_ORIGINS');

        $this->ValidateSMTPConfiguration();
    }

    private const string HTTPS = 'HTTPS';
    private const string off = 'off';



    public function GetAPIRootURL(): string
    { return $this->appURL . '/' ; }

    private function GetRouteURL(EmailServiceConfiguration $emailConfig) : string
    { return $this->GetAPIRootURL() . $emailConfig->serviceRoute; }

    private function GetConfirmationURL(EmailServiceConfiguration $emailConfig) : string
    { return $this->uiURL . '/' . $emailConfig->confirmationURL; }



    public function GetEmailVerificationRouteURL(): string
    { return $this->GetRouteURL($this->accountVerificationEmailConfig); }

    public function GetEmailVerificationPageURL(): string
    { return $this->GetConfirmationURL($this->accountVerificationEmailConfig); }

    public function GetPasswordResetRouteURL(): string
    { return $this->GetRouteURL($this->passwordResetEmailConfig); }

    public function GetPasswordResetPageURL(): string
    { return $this->GetConfirmationURL($this->passwordResetEmailConfig); }



    public function HTTPS(): string { return $_SERVER[self::HTTPS] ?? ''; }

    public function HasHttpsProp(): bool
    { return isset($_SERVER[self::HTTPS]); }

    public function HttpsIsNotOff(): bool
    { return $this->HTTPS() !== self::off; }

    public function IsSecure(): bool
    {
        return ($this->HasHttpsProp() &&
               $this->HttpsIsNotOff()) ||
               (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] === '443');
    }

    public function IsEmailConfigured(): bool
    {
        return $this->IsAccountVerificationEmailConfigured() &&
               $this->IsPasswordResetEmailConfigured();
    }

    public function IsAccountVerificationEmailConfigured(): bool
    { return $this->accountVerificationEmailConfig->IsConfigured(); }

    public function IsPasswordResetEmailConfigured(): bool
    { return $this->passwordResetEmailConfig->IsConfigured(); }

    private function ValidateSMTPConfiguration(): void
    {
        $this->accountVerificationEmailConfig->ValidateConfiguration();
        $this->passwordResetEmailConfig->ValidateConfiguration();
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

    private static function RequiredBool(string $name): bool
    {
        $val = self::Required($name);
        $result = filter_var(
            $val,
            FILTER_VALIDATE_BOOLEAN,
            FILTER_NULL_ON_FAILURE
        );
        if ($result === null) {
            throw new RuntimeException(
                "Configuration {$name} must be a valid boolean"
            );
        }

        return $result;
    }

    private static function RequiredInt(string $name): int
    {
        $val = self::Required($name);
        if (!ctype_digit($val)) {
            throw new RuntimeException(
                "Configuration {$name} must be a valid integer"
            );
        }

        return (int) $val;
    }

    private static function Optional(string $name): ?string
    {
        //$value = getenv($name);
        $value = $_ENV[$name] ?? null;

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

    private static function OptionalList(string $name): array
    {
        $value = self::Optional($name);

        if ($value === null) { return []; }

        $items = array_map('trim', explode(',', $value));

        return array_values(array_unique(
            array_filter(
                $items,
                static fn (string $item): bool => $item !== ''
            )
        ));
    }


}

?>