<?php

declare(strict_types=1);

namespace UserBase\Http;

enum AuthenticationRequirement
{
    case Public;
    case Required;
    case Optional;
}

?>