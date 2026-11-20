<?php

namespace App\Repositories\Eloquent;

use App\Contracts\GoogleIdentityProvider;
use App\Data\GoogleIdentityData;
use App\Repositories\Contracts\GoogleIdentityRepositoryInterface;

class GoogleIdentityRepository implements GoogleIdentityRepositoryInterface
{
    public function __construct(private GoogleIdentityProvider $googleIdentityProvider) {}

    public function findByAccessToken(string $accessToken): GoogleIdentityData
    {
        return $this->googleIdentityProvider->userFromAccessToken($accessToken);
    }
}
