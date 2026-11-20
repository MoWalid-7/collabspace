<?php

namespace App\Data\Profile;

use Illuminate\Http\UploadedFile;

readonly class UploadAvatarData
{
    public function __construct(public UploadedFile $avatar) {}
}
