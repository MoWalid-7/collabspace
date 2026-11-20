<?php

namespace App\Services;

use App\Contracts\GoogleIdentityProvider;
use App\Data\GoogleIdentityData;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use LogicException;

class SocialiteGoogleIdentityProvider implements GoogleIdentityProvider
{
    public function userFromAccessToken(string $accessToken): GoogleIdentityData
    {
        $provider = Socialite::driver('google');

        if (! $provider instanceof AbstractProvider) {
            throw new LogicException('The configured Google Socialite driver is not an OAuth 2 provider.');
        }

        $googleUser = $provider->userFromToken($accessToken);
        $id = $googleUser->getId();
        $email = $googleUser->getEmail();
        $name = $googleUser->getName();

        if (
            $id === ''
            || ! is_string($email) || filter_var($email, FILTER_VALIDATE_EMAIL) === false
            || ! is_string($name) || $name === ''
        ) {
            throw ValidationException::withMessages([
                'access_token' => ['Google did not provide a complete user identity.'],
            ]);
        }

        return new GoogleIdentityData($id, $email, $name);
    }
}
