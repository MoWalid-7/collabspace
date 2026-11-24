<?php

namespace App\Services;

use App\Data\Search\SearchQuery;
use App\Models\File;
use App\Models\Project;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use App\Repositories\Contracts\FileRepositoryInterface;
use App\Repositories\Contracts\ProjectRepositoryInterface;
use App\Repositories\Contracts\TaskRepositoryInterface;
use App\Repositories\Contracts\TeamRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use LogicException;

class SearchService
{
    public function __construct(
        private ProjectRepositoryInterface $projects,
        private TaskRepositoryInterface $tasks,
        private FileRepositoryInterface $files,
        private TeamRepositoryInterface $teams,
        private UserRepositoryInterface $users,
    ) {}

    /**
     * @return array{
     *     projects: list<array<string, mixed>>,
     *     tasks: list<array<string, mixed>>,
     *     files: list<array<string, mixed>>,
     *     teams: list<array<string, mixed>>,
     *     members: list<array<string, mixed>>
     * }
     */
    public function search(SearchQuery $query, int $userId, int $workspaceId): array
    {
        $results = [
            'projects' => [],
            'tasks' => [],
            'files' => [],
            'teams' => [],
            'members' => [],
        ];

        foreach ($query->types as $type) {
            $results[$type] = match ($type) {
                'projects' => $this->projects->searchVisible($query->term, $query->limit, $userId, $workspaceId)
                    ->map(fn (Project $project): array => [
                        'id' => (int) $project->getKey(),
                        'type' => 'project',
                        'title' => $project->name,
                        'subtitle' => $project->type,
                    ])->all(),
                'tasks' => $this->tasks->searchVisible($query->term, $query->limit, $userId, $workspaceId)
                    ->map(fn (Task $task): array => [
                        'id' => (int) $task->getKey(),
                        'type' => 'task',
                        'title' => $task->title,
                        'subtitle' => $task->project->name,
                        'project_id' => (int) $task->project_id,
                    ])->all(),
                'files' => $this->files->searchVisible($query->term, $query->limit, $userId, $workspaceId)
                    ->map(fn (File $file): array => [
                        'id' => (int) $file->getKey(),
                        'type' => 'file',
                        'title' => $file->name,
                        'subtitle' => $file->project->name,
                        'project_id' => (int) $file->project_id,
                        'task_id' => $file->task_id === null ? null : (int) $file->task_id,
                    ])->all(),
                'teams' => $this->teams->searchInWorkspace($query->term, $query->limit, $workspaceId)
                    ->map(fn (Team $team): array => [
                        'id' => (int) $team->getKey(),
                        'type' => 'team',
                        'title' => $team->name,
                        'subtitle' => $team->description,
                    ])->all(),
                'members' => $this->users->searchWorkspaceMembers(
                    $workspaceId,
                    $query->term,
                    $query->limit,
                )->map(fn (User $user): array => [
                    'id' => (int) $user->getKey(),
                    'type' => 'member',
                    'title' => $user->name,
                    'subtitle' => $user->job_title,
                ])->all(),
                default => throw new LogicException("Unsupported search type [{$type}]."),
            };
        }

        return $results;
    }
}
