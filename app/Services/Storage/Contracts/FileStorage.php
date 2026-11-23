<?php

namespace App\Services\Storage\Contracts;

use App\Data\Files\StoredFile;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\StreamedResponse;

interface FileStorage
{
    public function put(UploadedFile $file, string $directory): StoredFile;

    public function stream(string $disk, string $path, string $downloadName): StreamedResponse;

    public function delete(string $disk, string $path): void;

    public function exists(string $disk, string $path): bool;
}
