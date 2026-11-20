<?php

namespace App\Contracts;

use App\Data\GoogleIdentityData;

interface GoogleIdentityProvider
{
    public function userFromAccessToken(string $accessToken): GoogleIdentityData;
}
