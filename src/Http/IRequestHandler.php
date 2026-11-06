<?php

declare(strict_types=1);

namespace UserBase\Http;

interface IRequestHandler
{
    public function Execute(RequestContext $context): void;
}

?>