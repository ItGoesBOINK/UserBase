<?php

declare(strict_types=1);

namespace UserBase\Controller;

final class HealthController extends ControllerBase
{
    #[\Override]
    public function Execute(): void
    {
        $this->Success([
            'status' => 'ok',
            'service' => 'UserBase'
        ]);
    }
}