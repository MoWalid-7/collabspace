<?php

namespace App\Data;

use Carbon\CarbonImmutable;

readonly class IssuedApiTokenData
{
    public function __construct(
        public string $plainTextToken,
        public CarbonImmutable $expiresAt,
    ) {}
}
