<?php

namespace App\Data\Auth;

readonly class ForgotPasswordData
{
    public function __construct(public string $email) {}
}
