<?php

declare(strict_types=1);

namespace UserBase\Validation;

final class InputValidator
{
    private const string PATTERN__HEX_TOKEN = '/\A[a-f0-9]{64}\z/';
    private const string PATTERN__USER_NAME = '/\A[a-zA-Z0-9_.-]{8,64}\z/';
    private const int MIN__EMAIL = 5;
    private const int MAX__EMAIL = 256;
    private const int MIN__PASSWORD = 12;
    private const int MAX__PASSWORD = 1024;

    public static function IsValidVerificationToken(string $token): bool
    { return preg_match(self::PATTERN__HEX_TOKEN, $token) === 1; }

    public static function IsValidSessionToken(string $token): bool
    { return preg_match(self::PATTERN__HEX_TOKEN, $token) === 1; }

    public static function IsValidUserName(string $username): bool
    { return preg_match(self::PATTERN__USER_NAME, $username) === 1; }

    public static function IsValidPassword(string $password): bool
    {
        $l = strlen($password);
        return $l >= self::MIN__PASSWORD && $l <= self::MAX__PASSWORD;
    }

    public static function IsValidEmail(string $email): bool
    {
        $l = strlen($email);
        return ($l >= self::MIN__EMAIL && $l <= self::MAX__EMAIL) &&
                filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    public static function NormalizeUserName(string $username): string
    { return strtolower($username); }

    public static function NormalizeEmail(string $email): string
    { return strtolower($email); }

}


?>