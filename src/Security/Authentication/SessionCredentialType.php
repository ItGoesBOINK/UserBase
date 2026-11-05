<?php

declare(strict_types=1);

namespace UserBase\Security\Authentication;

enum SessionCredentialType
{
    case Cookie;
    case Bearer;
}


?>