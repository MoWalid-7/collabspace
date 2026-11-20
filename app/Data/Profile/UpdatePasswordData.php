<?php

namespace App\Data\Profile;

readonly class UpdatePasswordData
{
    public function __construct(public string $password) {}
}
