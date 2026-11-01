<?php

declare(strict_types=1);

namespace UserBase\Tests;

use UserBase\Controller\ControllerBase;
use UserBase\Http\RequestContext;

final class TestController extends ControllerBase
{
    public bool $wasExecuted = false;

    #[\Override]
    public function Execute(RequestContext $context): void
    {
        $this->wasExecuted = true;
    }
}


?>