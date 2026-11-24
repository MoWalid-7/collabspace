<?php

namespace App\Repositories\Eloquent;

use App\Data\Files\FileFilters;
use App\Enums\FileCategory;
use App\Models\File;
use App\Models\FileVersion;
use App\Models\Project;
use App\Models\Task;
use App\Repositories\Contracts\FileRepositoryInterface;
use App\Repositories\Contracts\ProjectRepositoryInterface;
use App\Support\LikePattern;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class FileRepository implements FileRepositoryInterface
{
    public function __construct(private ProjectRepositoryInterface $projects) {}

    public function findProjectOrFail(int $projectId): Project
    {
        return Project::query()->findOrFail($projectId);
    }

    public function findTaskOrFail(int $taskId): Task
    {
        return Task::query()->with('project')->findOrFail($taskId);
    }

    public function findInCurrentWorkspaceOrFail(int $fileId): File
    {
        return File::query()
            ->with(['project', 'task', 'uploader', 'currentVersion.uploader'])
            ->withCount('versions')
            ->findOrFail($fileId);
    }

    public function findForVersioning(File $file): File
    {
        return File::query()
            ->whereKey($file->getKey())
            ->lockForUpdate()
            ->firstOrFail();
    }

    public function nextVersionNumber(File $file): int
    {
        $latestVersion = FileVersion::query()
            ->where('file_id', $file->getKey())
            ->max('version_number');

        return $latestVersion === null ? 1 : (int) $latestVersion + 1;
    }

    public function createFile(array $attributes): File
    {
        return File::query()->create($attributes);
    }

    public function createVersion(File $file, array $attributes): FileVersion
    {
        return $file->versions()->create($attributes);
    }

    public function rename(File $file, string $name): File
    {
        $file->update(['name' => $name]);

        return $this->findInCurrentWorkspaceOrFail((int) $file->getKey());
    }

    public function softDelete(File $file): void
    {
        $file->delete();
    }

    public function paginateForProject(FileFilters $filters): LengthAwarePaginator
    {
        return File::query()
            ->where('project_id', $filters->projectId)
            ->when($filters->search !== null, fn (Builder $query) => $query->where('name', 'like', '%'.$filters->search.'%'))
            ->when($filters->taskId !== null, fn (Builder $query) => $query->where('task_id', $filters->taskId))
            ->when($filters->uploadedBy !== null, fn (Builder $query) => $query->where('uploaded_by', $filters->uploadedBy))
            ->when($filters->type !== null, function (Builder $query) use ($filters): void {
                $query->whereHas('currentVersion', function (Builder $versions) use ($filters): void {
                    if ($filters->type === FileCategory::Other) {
                        $versions->whereNotIn('mime', FileCategory::classifiedMimeTypes());
                    } else {
                        $versions->whereIn('mime', $filters->type->mimeTypes());
                    }
                });
            })
            ->with(['uploader', 'currentVersion'])
            ->withCount('versions')
            ->when($filters->sort === 'oldest', fn (Builder $query) => $query->orderBy('created_at')->orderBy('id'))
            ->when($filters->sort === 'name', fn (Builder $query) => $query->orderBy('name')->orderBy('id'))
            ->when($filters->sort === 'size', fn (Builder $query) => $query
                ->orderByDesc(FileVersion::query()
                    ->select('size')
                    ->whereColumn('file_id', 'files.id')
                    ->orderByDesc('version_number')
                    ->limit(1))
                ->orderByDesc('id'))
            ->when($filters->sort === 'newest', fn (Builder $query) => $query->orderByDesc('created_at')->orderByDesc('id'))
            ->paginate($filters->perPage);
    }

    public function paginateForTask(int $taskId, int $perPage): LengthAwarePaginator
    {
        return File::query()
            ->where('task_id', $taskId)
            ->with(['uploader', 'currentVersion'])
            ->withCount('versions')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function versions(File $file): Collection
    {
        return $file->versions()->with('uploader')->get();
    }

    public function findVersionForFileOrFail(File $file, int $versionNumber): FileVersion
    {
        return FileVersion::query()
            ->where('file_id', $file->getKey())
            ->where('version_number', $versionNumber)
            ->firstOrFail();
    }

    public function recentInWorkspace(
        int $workspaceId,
        int $userId,
        ?int $projectId,
        bool $mine,
        int $limit,
    ): Collection {
        return File::query()
            ->where('workspace_id', $workspaceId)
            ->whereHas('project', fn (Builder $projects) => $this->projects->applyVisibility($projects, $workspaceId, $userId))
            ->when($projectId !== null, fn (Builder $query) => $query->where('project_id', $projectId))
            ->when($mine, fn (Builder $query) => $query->whereHas('task', function (Builder $tasks) use ($userId): void {
                $tasks->whereHas('assignees', fn (Builder $assignees) => $assignees->whereKey($userId));
            }))
            ->with(['uploader:id,name,avatar', 'project:id,name', 'currentVersion'])
            ->withCount('versions')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(max(0, $limit))
            ->get();
    }

    public function searchVisible(string $term, int $limit, int $userId, int $workspaceId): Collection
    {
        return File::query()
            ->where('workspace_id', $workspaceId)
            ->whereHas('project', fn (Builder $projects) => $this->projects->applyVisibility($projects, $workspaceId, $userId))
            ->whereRaw('LOWER(files.name) LIKE ? ESCAPE ?', [LikePattern::contains($term), '\\'])
            ->with(['project:id,name', 'task:id,project_id,title'])
            ->orderBy('name')
            ->orderBy('id')
            ->limit($limit)
            ->get();
    }
}
