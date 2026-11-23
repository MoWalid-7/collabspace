<?php

namespace App\Services\Storage;

use App\Data\Files\StoredFile;
use App\Services\Storage\Contracts\FileStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LocalFileStorage implements FileStorage
{
    public function put(UploadedFile $file, string $directory): StoredFile
    {
        $disk = (string) config('collabspace.files.disk');
        $path = Storage::disk($disk)->putFileAs($directory, $file, (string) Str::uuid());

        if ($path === false) {
            throw new RuntimeException('The uploaded file could not be stored.');
        }

        $checksum = hash_file('sha256', $file->getRealPath());

        if ($checksum === false) {
            Storage::disk($disk)->delete($path);

            throw new RuntimeException('The uploaded file checksum could not be calculated.');
        }

        return new StoredFile(
            disk: $disk,
            path: $path,
            size: (int) $file->getSize(),
            mime: $file->getMimeType() ?: 'application/octet-stream',
            checksum: $checksum,
        );
    }

    public function stream(string $disk, string $path, string $downloadName): StreamedResponse
    {
        return Storage::disk($disk)->download($path, $downloadName);
    }

    public function delete(string $disk, string $path): void
    {
        if (! Storage::disk($disk)->delete($path)) {
            throw new RuntimeException('The stored file could not be deleted.');
        }
    }

    public function exists(string $disk, string $path): bool
    {
        return Storage::disk($disk)->exists($path);
    }
}
