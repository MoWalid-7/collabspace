<?php

namespace App\Repositories\Eloquent;

use App\Data\TimeTracking\TimeEntryFilters;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use App\Repositories\Contracts\ProjectRepositoryInterface;
use App\Repositories\Contracts\TimeTrackingRepositoryInterface;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

class TimeTrackingRepository implements TimeTrackingRepositoryInterface
{
    public function __construct(private ProjectRepositoryInterface $projects) {}

    public function findRunningTimer(int $workspaceId, int $userId): ?TimeEntry
    {
        return TimeEntry::query()
            ->where('workspace_id', $workspaceId)
            ->where('user_id', $userId)
            ->whereNull('ended_at')
            ->with(['project', 'task'])
            ->first();
    }

    public function createRunningTimer(
        int $workspaceId,
        int $userId,
        Task $task,
        ?string $note = null,
    ): TimeEntry {
        return TimeEntry::query()->create([
            'workspace_id' => $workspaceId,
            'user_id' => $userId,
            'project_id' => $task->project_id,
            'task_id' => $task->id,
            'started_at' => now(),
            'ended_at' => null,
            'duration_seconds' => null,
            'note' => $note,
        ])->load(['project', 'task', 'user']);
    }

    public function stopRunningTimer(TimeEntry $entry): TimeEntry
    {
        $endedAt = now();
        $startedAt = $entry->started_at;
        $durationSeconds = max(0, (int) abs($endedAt->getTimestamp() - $startedAt->getTimestamp()));

        $entry->update([
            'ended_at' => $endedAt,
            'duration_seconds' => $durationSeconds,
        ]);

        return $entry->fresh(['project', 'task', 'user']) ?? $entry;
    }

    public function createManualEntry(
        int $workspaceId,
        int $userId,
        Task $task,
        CarbonInterface $startedAt,
        CarbonInterface $endedAt,
        int $durationSeconds,
        ?string $note = null,
    ): TimeEntry {
        return TimeEntry::query()->create([
            'workspace_id' => $workspaceId,
            'user_id' => $userId,
            'project_id' => $task->project_id,
            'task_id' => $task->id,
            'started_at' => $startedAt,
            'ended_at' => $endedAt,
            'duration_seconds' => $durationSeconds,
            'note' => $note,
        ])->load(['project', 'task', 'user']);
    }

    public function findInCurrentWorkspaceOrFail(int $id): TimeEntry
    {
        return TimeEntry::query()
            ->with(['project', 'task', 'user'])
            ->findOrFail($id);
    }

    public function updateTimeEntry(
        TimeEntry $entry,
        array $attributes,
    ): TimeEntry {
        $entry->update($attributes);

        return $entry->fresh(['project', 'task', 'user']) ?? $entry;
    }

    public function deleteTimeEntry(TimeEntry $entry): void
    {
        $entry->delete();
    }

    public function paginate(
        int $workspaceId,
        User $user,
        TimeEntryFilters $filters,
    ): array {
        $query = TimeEntry::query()
            ->where('workspace_id', $workspaceId)
            ->whereHas('project', fn (Builder $q) => $this->projects->applyVisibility($q, $workspaceId, (int) $user->getKey()));

        if ($filters->userId !== null) {
            $query->where('user_id', $filters->userId);
        }

        if ($filters->projectId !== null) {
            $query->where('project_id', $filters->projectId);
        }

        if ($filters->taskId !== null) {
            $query->where('task_id', $filters->taskId);
        }

        if ($filters->from !== null) {
            $query->where('started_at', '>=', $filters->from);
        }

        if ($filters->to !== null) {
            $query->where('started_at', '<=', $filters->to);
        }

        $totalSeconds = (int) (clone $query)->sum('duration_seconds');

        $paginator = $query->with(['project', 'task', 'user'])
            ->orderByDesc('started_at')
            ->paginate($filters->perPage, ['*'], 'page', $filters->page);

        return [
            'paginator' => $paginator,
            'total_seconds' => $totalSeconds,
        ];
    }

    public function sumHoursWorkedForUser(int $workspaceId, int $userId): float
    {
        $totalSeconds = (int) TimeEntry::query()
            ->where('workspace_id', $workspaceId)
            ->where('user_id', $userId)
            ->sum('duration_seconds');

        return round($totalSeconds / 3600, 1);
    }
}
