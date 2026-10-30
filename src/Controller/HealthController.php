<?php

declare(strict_types=1);

namespace UserBase\Controller;
use UserBase\Http\RequestContext;

final class HealthController extends ControllerBase
{
    #[\Override]
    public function Execute(RequestContext $context): void
    {
        $this->Success([
            'status' => 'ok',
            'service' => 'UserBase'
        ]);
    }
}