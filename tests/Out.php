<?php

declare(strict_types=1);

namespace UserBase\Tests;

final class Out
{
    public const RESET = "\033[0m";

    public const BOLD = "\033[1m";
    public const DIM = "\033[2m";
    public const UNDERLINE = "\033[4m";

    public const RED = "\033[0;31m";
    public const GREEN = "\033[0;32m";
    public const YELLOW = "\033[0;33m";
    public const BLUE = "\033[0;34m";
    public const MAGENTA = "\033[0;35m";
    public const CYAN = "\033[0;36m";
    public const WHITE = "\033[0;37m";

    public static function Label(
        string $color,
        string $label,
        string $desc
    ): void {
        printf(self::GetLabel($color, $label, $desc));
    }

    public static function GetLabel(
        string $color,
        string $label,
        string $desc
    ): string {
        return sprintf(
            "%s%s%s %s\n",
            $color,
            $label,
            self::RESET,
            $desc
        );
    }

    public static function GetColorText(string $color, string $text): string
    { return sprintf("%s%s%s", $color, $text, self::RESET); }

    public static function ColorText(string $color, string $text): void
    { printf(self::GetColorText($color, $text)); }

    public static function GetColorLine(string $color, string $text): string
    { return sprintf("%s%s%s\n", $color, $text, self::RESET); }

    public static function ColorLine(string $color, string $text): void
    { printf(self::GetColorLine($color, $text)); }

    public static function Info(string $message): void
    { self::Label(self::CYAN, '[INFO]:', $message); }

    public static function Pass(string $message): void
    { self::Label(self::GREEN, '[PASS]:', $message); }

    public static function GetPass(string $message): string
    { return self::GetLabel(self::GREEN, '[PASS]:', $message); }

    public static function Warn(string $message): void
    { self::Label(self::YELLOW, '[WARN]:', $message); }

    public static function Fail(string $message): void
    { self::Label(self::RED, '[FAIL]:', $message); }

    public static function GetFail(string $message): string
    { return self::GetLabel(self::RED, '[FAIL]:', $message); }
}


?>