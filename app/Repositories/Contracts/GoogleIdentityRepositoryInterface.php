<?php

namespace App\Repositories\Contracts;

use App\Data\GoogleIdentityData;

interface GoogleIdentityRepositoryInterface
{
    public function findByAccessToken(string $accessToken): GoogleIdentityData;
}
