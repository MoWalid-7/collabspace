<?php

namespace App\Repositories\Eloquent;

use App\Repositories\Contracts\FileStorageRepositoryInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class LocalFileStorageRepository implements FileStorageRepositoryInterface
{
    public function storeAvatar(UploadedFile $avatar): string
    {
        $path = $avatar->store('avatars', 'public');

        if ($path === false) {
            throw new RuntimeException('The avatar could not be stored.');
        }

        return $path;
    }

    public function delete(?string $path): void
    {
        if ($path !== null && ! Storage::disk('public')->delete($path)) {
            throw new RuntimeException('The stored file could not be deleted.');
        }
    }
}
