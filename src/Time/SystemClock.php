<?php

declare(strict_types=1);

namespace UserBase\Time;

use DateTimeImmutable;
use DateTimeZone;

final class SystemClock implements IClock
{

    private readonly DateTimeZone $utc;

    public function __construct()
    { $this->utc = new DateTimeZone('UTC'); }

    public function Now(): DateTimeImmutable
    { return new DateTimeImmutable('now', $this->utc); }

}

?>