<?php

declare(strict_types=1);

namespace UserBase\Time;

use DateTimeImmutable;
use DateTimeZone;

final class TimeUtility
{
    public static function TimestampFormat():string
    { return 'Y-m-d H:i:s.u'; }

    public static function UTC():DateTimeZone
    { return new DateTimeZone('UTC'); }

    public static function FormatTimestamp(DateTimeImmutable $timestamp): string
    { return $timestamp
        ->setTimezone(TimeUtility::UTC())
        ->format(TimeUtility::TimestampFormat()); }
}

?>