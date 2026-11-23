<?php

namespace App\Services\Contracts;

use App\Data\Files\FileFilters;
use App\Models\File;
use App\Models\FileVersion;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\StreamedResponse;

interface FileServiceInterface
{
    public function list(FileFilters $filters): LengthAwarePaginator;

    public function listForTask(int $taskId, int $perPage): LengthAwarePaginator;

    public function upload(int $projectId, ?int $taskId, UploadedFile $upload, ?string $note, int $actorId): File;

    public function uploadForTask(int $taskId, UploadedFile $upload, ?string $note, int $actorId): File;

    public function addVersion(File $file, UploadedFile $upload, ?string $note, int $actorId): File;

    public function show(int $fileId): File;

    public function rename(int $fileId, string $name): File;

    public function delete(int $fileId): void;

    public function download(int $fileId): StreamedResponse;

    public function downloadVersion(int $fileId, int $versionNumber): StreamedResponse;

    /**
     * @return Collection<int, FileVersion>
     */
    public function versions(int $fileId): Collection;
}
