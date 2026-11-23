<?php

namespace App\Services;

use App\Data\Files\FileFilters;
use App\Events\FileUploaded;
use App\Events\FileVersionAdded;
use App\Models\File;
use App\Models\FileVersion;
use App\Repositories\Contracts\FileRepositoryInterface;
use App\Services\Contracts\FileServiceInterface;
use App\Services\Storage\Contracts\FileStorage;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class FileService implements FileServiceInterface
{
    public function __construct(
        private FileRepositoryInterface $files,
        private FileStorage $storage,
    ) {}

    public function list(FileFilters $filters): LengthAwarePaginator
    {
        return $this->files->paginateForProject($filters);
    }

    public function listForTask(int $taskId, int $perPage): LengthAwarePaginator
    {
        $this->files->findTaskOrFail($taskId);

        return $this->files->paginateForTask($taskId, $perPage);
    }

    public function upload(
        int $projectId,
        ?int $taskId,
        UploadedFile $upload,
        ?string $note,
        int $actorId,
    ): File {
        $project = $this->files->findProjectOrFail($projectId);
        $task = $taskId === null ? null : $this->files->findTaskOrFail($taskId);

        if ($task !== null && (int) $task->project_id !== $projectId) {
            throw ValidationException::withMessages([
                'task_id' => ['The selected task must belong to this project.'],
            ]);
        }

        $storedFile = $this->storage->put(
            $upload,
            "workspaces/{$project->workspace_id}/projects/{$project->getKey()}/files",
        );

        try {
            [$file, $version] = DB::transaction(function () use ($project, $taskId, $upload, $actorId, $note, $storedFile): array {
                $file = $this->files->createFile([
                    'workspace_id' => $project->workspace_id,
                    'project_id' => $project->getKey(),
                    'task_id' => $taskId,
                    'uploaded_by' => $actorId,
                    'name' => $upload->getClientOriginalName(),
                ]);
                $version = $this->files->createVersion($file, [
                    'version_number' => 1,
                    'disk' => $storedFile->disk,
                    'path' => $storedFile->path,
                    'original_name' => $upload->getClientOriginalName(),
                    'mime' => $storedFile->mime,
                    'size' => $storedFile->size,
                    'checksum' => $storedFile->checksum,
                    'uploaded_by' => $actorId,
                    'note' => $note,
                ]);

                return [$file, $version];
            });
        } catch (Throwable $exception) {
            try {
                $this->storage->delete($storedFile->disk, $storedFile->path);
            } catch (Throwable $storageException) {
                report($storageException);
            }

            throw $exception;
        }

        $file = $this->files->findInCurrentWorkspaceOrFail((int) $file->getKey());
        FileUploaded::dispatch($file, $version, $actorId, $task?->title);

        return $file;
    }

    public function uploadForTask(
        int $taskId,
        UploadedFile $upload,
        ?string $note,
        int $actorId,
    ): File {
        $task = $this->files->findTaskOrFail($taskId);

        return $this->upload((int) $task->project_id, $taskId, $upload, $note, $actorId);
    }

    public function addVersion(File $file, UploadedFile $upload, ?string $note, int $actorId): File
    {
        $storedFile = $this->storage->put(
            $upload,
            "workspaces/{$file->workspace_id}/projects/{$file->project_id}/files",
        );

        try {
            [$lockedFile, $version] = DB::transaction(function () use ($file, $upload, $note, $actorId, $storedFile): array {
                $lockedFile = $this->files->findForVersioning($file);
                $version = $this->files->createVersion($lockedFile, [
                    'version_number' => $this->files->nextVersionNumber($lockedFile),
                    'disk' => $storedFile->disk,
                    'path' => $storedFile->path,
                    'original_name' => $upload->getClientOriginalName(),
                    'mime' => $storedFile->mime,
                    'size' => $storedFile->size,
                    'checksum' => $storedFile->checksum,
                    'uploaded_by' => $actorId,
                    'note' => $note,
                ]);

                return [$lockedFile, $version];
            });
        } catch (Throwable $exception) {
            try {
                $this->storage->delete($storedFile->disk, $storedFile->path);
            } catch (Throwable $storageException) {
                report($storageException);
            }

            throw $exception;
        }

        $updatedFile = $this->files->findInCurrentWorkspaceOrFail((int) $lockedFile->getKey());
        FileVersionAdded::dispatch($updatedFile, $version, $actorId);

        return $updatedFile;
    }

    public function show(int $fileId): File
    {
        return $this->files->findInCurrentWorkspaceOrFail($fileId);
    }

    public function rename(int $fileId, string $name): File
    {
        return $this->files->rename($this->files->findInCurrentWorkspaceOrFail($fileId), $name);
    }

    public function delete(int $fileId): void
    {
        $this->files->softDelete($this->files->findInCurrentWorkspaceOrFail($fileId));
    }

    public function download(int $fileId): StreamedResponse
    {
        $file = $this->files->findInCurrentWorkspaceOrFail($fileId);
        $version = $file->currentVersion;

        if ($version === null) {
            abort(404);
        }

        return $this->stream($version);
    }

    public function downloadVersion(int $fileId, int $versionNumber): StreamedResponse
    {
        $file = $this->files->findInCurrentWorkspaceOrFail($fileId);

        return $this->stream($this->files->findVersionForFileOrFail($file, $versionNumber));
    }

    /**
     * @return Collection<int, FileVersion>
     */
    public function versions(int $fileId): Collection
    {
        return $this->files->versions($this->files->findInCurrentWorkspaceOrFail($fileId));
    }

    private function stream(FileVersion $version): StreamedResponse
    {
        return $this->storage->stream($version->disk, $version->path, $version->original_name);
    }
}
