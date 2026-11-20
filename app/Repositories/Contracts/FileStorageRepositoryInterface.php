<?php

namespace App\Repositories\Contracts;

use Illuminate\Http\UploadedFile;

interface FileStorageRepositoryInterface
{
    public function storeAvatar(UploadedFile $avatar): string;

    public function delete(?string $path): void;
}
