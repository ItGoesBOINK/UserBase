<?php

declare(strict_types=1);

namespace UserBase\Tests;

use DateTimeImmutable;
use InvalidArgumentException;
use UserBase\Time\IClock;

final class TestClock implements IClock
{
    public function __construct(
        private DateTimeImmutable $currentTime
    ){}

    public function Now(): DateTimeImmutable
        { return $this->currentTime; }

    public function SetTime(DateTimeImmutable $time): void
        { $this->currentTime = $time; }

    public function Advance(string $interval): void
    {
        $newTime = $this->currentTime->modify($interval);

        if ($newTime === false)
            throw new InvalidArgumentException
                ("Invalid time interval: {$interval}");

        $this->currentTime = $newTime;
    }
}

?>