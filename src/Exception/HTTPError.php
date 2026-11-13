<?php

declare(strict_types=1);

namespace UserBase\Exception;

enum HTTPErrorType: int
{
    case forbidden = 403;
    case not_found = 404;
    case internal_server_error = 500;
}

class HTTPError
{
    public function __construct(
        public HTTPErrorType $type,
        public readonly string $message
    ) {}

    public function GetErrorCode(): string
    { return $this->type->name; }

    public function GetResponseCode(): int
    { return $this->type->value; }

    /** @var array<string, string> */
    private static array $_errors;

    private static bool $_isInitialized = false;
    private static function Init(): void {

        self::$_errors = [
            HTTPErrorType::forbidden->name =>
                'Access Forbidden',
            HTTPErrorType::not_found->name =>
                'The Requested Resource was not found',
            HTTPErrorType::internal_server_error->name =>
                'An Internal Server Error Occurred',
        ];

        self::$_isInitialized = true;
    }
    public static function Get(HTTPErrorType $type): ?HTTPError {
        if (!self::$_isInitialized) self::Init();
        $n = $type->name;
        if (isset(self::$_errors[$n]))
        { return new HTTPError($type, self::$_errors[$n]); }
        return null;
    }
}

?>