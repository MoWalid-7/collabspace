<?php

namespace App\Services;

use App\Data\TimeTracking\ManualTimeEntryData;
use App\Data\TimeTracking\TimeEntryFilters;
use App\Data\TimeTracking\UpdateTimeEntryData;
use App\Enums\WorkspaceRole;
use App\Models\TimeEntry;
use App\Models\User;
use App\Repositories\Contracts\ProjectRepositoryInterface;
use App\Repositories\Contracts\TaskRepositoryInterface;
use App\Repositories\Contracts\TimeTrackingRepositoryInterface;
use App\Repositories\Contracts\WorkspaceRepositoryInterface;
use App\Services\Contracts\TimeTrackingServiceInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class TimeTrackingService implements TimeTrackingServiceInterface
{
    public function __construct(
        private TimeTrackingRepositoryInterface $timeEntries,
        private TaskRepositoryInterface $tasks,
        private ProjectRepositoryInterface $projects,
        private WorkspaceRepositoryInterface $workspaces,
    ) {}

    public function start(int $taskId, int $userId, int $workspaceId, ?string $note = null): TimeEntry
    {
        $running = $this->timeEntries->findRunningTimer($workspaceId, $userId);
        if ($running !== null) {
            throw new ConflictHttpException('A timer is already running. Please stop the current timer before starting a new one.');
        }

        $task = $this->tasks->findInCurrentWorkspaceOrFail($taskId);
        $project = $task->project;

        if (! $this->projects->isVisibleTo($project, $userId, $workspaceId)) {
            throw new NotFoundHttpException('Task not found in a visible project.');
        }

        return $this->timeEntries->createRunningTimer($workspaceId, $userId, $task, $note);
    }

    public function stop(int $userId, int $workspaceId): TimeEntry
    {
        $running = $this->timeEntries->findRunningTimer($workspaceId, $userId);
        if ($running === null) {
            throw new ConflictHttpException('There is no active timer to stop.');
        }

        return $this->timeEntries->stopRunningTimer($running);
    }

    public function current(int $userId, int $workspaceId): ?TimeEntry
    {
        return $this->timeEntries->findRunningTimer($workspaceId, $userId);
    }

    public function list(User $user, int $workspaceId, TimeEntryFilters $filters): array
    {
        $roleValue = $this->workspaces->roleForWorkspaceId($workspaceId, (int) $user->getKey());
        $role = $roleValue ? WorkspaceRole::tryFrom($roleValue) : null;

        $forcedUserId = null;
        if ($role === null || $role === WorkspaceRole::Member) {
            $forcedUserId = (int) $user->getKey();
        }

        $effectiveFilters = new TimeEntryFilters(
            userId: $forcedUserId ?? $filters->userId,
            projectId: $filters->projectId,
            taskId: $filters->taskId,
            from: $filters->from,
            to: $filters->to,
            page: $filters->page,
            perPage: $filters->perPage,
        );

        return $this->timeEntries->paginate($workspaceId, $user, $effectiveFilters);
    }

    public function createManual(ManualTimeEntryData $data, int $userId, int $workspaceId): TimeEntry
    {
        $startedAt = $data->startedAt;
        $endedAt = $data->endedAt;

        if ($endedAt->lessThanOrEqualTo($startedAt)) {
            throw ValidationException::withMessages([
                'ended_at' => ['The end time must be after the start time.'],
            ]);
        }

        $diffSeconds = (int) abs($endedAt->getTimestamp() - $startedAt->getTimestamp());
        if ($diffSeconds > 86400) {
            throw ValidationException::withMessages([
                'ended_at' => ['A single time entry cannot exceed 24 hours.'],
            ]);
        }

        $task = $this->tasks->findInCurrentWorkspaceOrFail($data->taskId);
        $project = $task->project;

        if (! $this->projects->isVisibleTo($project, $userId, $workspaceId)) {
            throw new NotFoundHttpException('Task not found in a visible project.');
        }

        return $this->timeEntries->createManualEntry(
            $workspaceId,
            $userId,
            $task,
            $startedAt,
            $endedAt,
            $diffSeconds,
            $data->note,
        );
    }

    public function update(int $id, UpdateTimeEntryData $data, User $user, int $workspaceId): TimeEntry
    {
        $entry = $this->timeEntries->findInCurrentWorkspaceOrFail($id);
        $this->ensureCanManageEntry($entry, $user, $workspaceId);

        $startedAt = $data->startedAt ?? $entry->started_at;
        $endedAt = $data->endedAt ?? $entry->ended_at;

        $attributes = [];
        if ($data->note !== null) {
            $attributes['note'] = $data->note;
        }

        if ($endedAt !== null) {
            if ($endedAt->lessThanOrEqualTo($startedAt)) {
                throw ValidationException::withMessages([
                    'ended_at' => ['The end time must be after the start time.'],
                ]);
            }

            $diffSeconds = (int) abs($endedAt->getTimestamp() - $startedAt->getTimestamp());
            if ($diffSeconds > 86400) {
                throw ValidationException::withMessages([
                    'ended_at' => ['A single time entry cannot exceed 24 hours.'],
                ]);
            }

            $attributes['started_at'] = $startedAt;
            $attributes['ended_at'] = $endedAt;
            $attributes['duration_seconds'] = $diffSeconds;
        } elseif ($startedAt !== null) {
            $attributes['started_at'] = $startedAt;
        }

        return $this->timeEntries->updateTimeEntry($entry, $attributes);
    }

    public function delete(int $id, User $user, int $workspaceId): void
    {
        $entry = $this->timeEntries->findInCurrentWorkspaceOrFail($id);
        $this->ensureCanManageEntry($entry, $user, $workspaceId);

        $this->timeEntries->deleteTimeEntry($entry);
    }

    public function profileHoursWorked(int $userId, int $workspaceId): float
    {
        return $this->timeEntries->sumHoursWorkedForUser($workspaceId, $userId);
    }

    private function ensureCanManageEntry(TimeEntry $entry, User $user, int $workspaceId): void
    {
        $roleValue = $this->workspaces->roleForWorkspaceId($workspaceId, (int) $user->getKey());
        $role = $roleValue ? WorkspaceRole::tryFrom($roleValue) : null;

        if ($role === WorkspaceRole::Admin || $role === WorkspaceRole::Manager) {
            return;
        }

        if ($entry->user_id === (int) $user->getKey()) {
            return;
        }

        throw new AuthorizationException('You are not authorized to manage this time entry.');
    }
}
