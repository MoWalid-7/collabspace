<?php

namespace App\Repositories\Contracts;

use App\Data\Files\FileFilters;
use App\Models\File;
use App\Models\FileVersion;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface FileRepositoryInterface
{
    public function findProjectOrFail(int $projectId): Project;

    public function findTaskOrFail(int $taskId): Task;

    public function findInCurrentWorkspaceOrFail(int $fileId): File;

    public function findForVersioning(File $file): File;

    public function nextVersionNumber(File $file): int;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createFile(array $attributes): File;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createVersion(File $file, array $attributes): FileVersion;

    public function rename(File $file, string $name): File;

    public function softDelete(File $file): void;

    public function paginateForProject(FileFilters $filters): LengthAwarePaginator;

    public function paginateForTask(int $taskId, int $perPage): LengthAwarePaginator;

    /**
     * @return Collection<int, FileVersion>
     */
    public function versions(File $file): Collection;

    public function findVersionForFileOrFail(File $file, int $versionId): FileVersion;

    /**
     * @return Collection<int, File>
     */
    public function recentInWorkspace(int $workspaceId, ?int $projectId, int $limit): Collection;
}
