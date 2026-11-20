<?php

declare(strict_types=1);

namespace UserBase\Controller;

use UserBase\Http\RequestContext;

final class PasswordRequestController extends ControllerBase
{
    public function __construct() {}

    #[\Override]
    public function Execute(RequestContext $context): void {}

}