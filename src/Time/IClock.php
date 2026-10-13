<?php

declare(strict_types=1);

namespace UserBase\Time;

use DateTimeImmutable;

interface IClock
{
    public function Now(): DateTimeImmutable;
}


?>