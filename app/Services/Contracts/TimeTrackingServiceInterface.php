<?php

namespace App\Services\Contracts;

use App\Data\TimeTracking\ManualTimeEntryData;
use App\Data\TimeTracking\TimeEntryFilters;
use App\Data\TimeTracking\UpdateTimeEntryData;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface TimeTrackingServiceInterface
{
    public function start(int $taskId, int $userId, int $workspaceId, ?string $note = null): TimeEntry;

    public function stop(int $userId, int $workspaceId): TimeEntry;

    public function current(int $userId, int $workspaceId): ?TimeEntry;

    /**
     * @return array{paginator: LengthAwarePaginator<int, TimeEntry>, total_seconds: int}
     */
    public function list(User $user, int $workspaceId, TimeEntryFilters $filters): array;

    public function createManual(ManualTimeEntryData $data, int $userId, int $workspaceId): TimeEntry;

    public function update(int $id, UpdateTimeEntryData $data, User $user, int $workspaceId): TimeEntry;

    public function delete(int $id, User $user, int $workspaceId): void;

    public function profileHoursWorked(int $userId, int $workspaceId): float;
}
