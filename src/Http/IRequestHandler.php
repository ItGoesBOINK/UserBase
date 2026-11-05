<?php

declare(strict_types=1);

interface IRequestHandler
{
    public function Execute(RequestContext $context): void;
}

?>