<?php

declare(strict_types=1);

namespace UserBase\Controller;

final class HealthController extends ControllerBase
{
    public function GetStatus(): void
    {
        $this->Success([
            'status' => 'ok',
            'service' => 'UserBase'
        ]);
    }
}