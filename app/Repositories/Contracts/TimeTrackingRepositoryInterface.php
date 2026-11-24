<?php

namespace App\Repositories\Contracts;

use App\Data\TimeTracking\TimeEntryFilters;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface TimeTrackingRepositoryInterface
{
    public function findRunningTimer(int $workspaceId, int $userId): ?TimeEntry;

    public function createRunningTimer(
        int $workspaceId,
        int $userId,
        Task $task,
        ?string $note = null,
    ): TimeEntry;

    public function stopRunningTimer(TimeEntry $entry): TimeEntry;

    public function createManualEntry(
        int $workspaceId,
        int $userId,
        Task $task,
        CarbonInterface $startedAt,
        CarbonInterface $endedAt,
        int $durationSeconds,
        ?string $note = null,
    ): TimeEntry;

    public function findInCurrentWorkspaceOrFail(int $id): TimeEntry;

    public function updateTimeEntry(
        TimeEntry $entry,
        array $attributes,
    ): TimeEntry;

    public function deleteTimeEntry(TimeEntry $entry): void;

    /**
     * @return array{paginator: LengthAwarePaginator<int, TimeEntry>, total_seconds: int}
     */
    public function paginate(
        int $workspaceId,
        User $user,
        TimeEntryFilters $filters,
    ): array;

    public function sumHoursWorkedForUser(int $workspaceId, int $userId): float;
}
