<?php

declare(strict_types=1);

namespace UserBase\Security\RateLimit;

use InvalidArgumentException;

final readonly class RateLimitPolicy
{
    public function __construct(
        public string $key,
        public int $maxAttempts,
        public int $windowSeconds
    ) {
        $matchPatt_validPolicy = '/\A[a-zA-Z0-9][a-zA-Z0-9:_-]{0,99}\z/';

        if (!preg_match($matchPatt_validPolicy, $key)) {
            throw new InvalidArgumentException(
                'Rate-Limit Policy Key must be 1-100 valid ASCII characters'
            );
        }

        if ($maxAttempts < 1) {
            throw new InvalidArgumentException(
                'Rate-Limit Policy maximum attempts must be greater than zero'
            );
        }

        if ($windowSeconds < 1) {
            throw new InvalidArgumentException(
                'Rate-Limit Policy window must be greater than zero'
            );
        }
    }
}

?>