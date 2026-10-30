<?php

declare(strict_types=1);

namespace UserBase\Http;

final class RequestPipeline
{
    public function Process(
        Route $route,
        RequestContext $context
    ): void {
        $route->controller->Execute($context);
    }
}

?>