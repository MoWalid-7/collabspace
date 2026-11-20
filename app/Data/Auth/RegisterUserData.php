<?php

namespace App\Data\Auth;

readonly class RegisterUserData
{
    public function __construct(
        public string $fullName,
        public string $email,
        public string $password,
    ) {}
}
