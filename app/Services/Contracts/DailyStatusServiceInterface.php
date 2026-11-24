<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\Data\DailyStatus\DailyStatusFilters;
use App\Data\DailyStatus\UpsertDailyStatusData;
use App\Models\DailyStatus;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

interface DailyStatusServiceInterface
{
    public function upsert(User $user, int $workspaceId, UpsertDailyStatusData $data): DailyStatus;

    /**
     * @return Collection<int, DailyStatus>
     */
    public function list(User $user, int $workspaceId, DailyStatusFilters $filters): Collection;

    /**
     * @return array<int, array{user: User, status: ?DailyStatus, submitted: bool}>
     */
    public function getTeamStatus(User $user, int $workspaceId, int $projectId, string $date): array;

    /**
     * @return Collection<int, DailyStatus>
     */
    public function getBlockers(User $user, int $workspaceId, int $projectId, CarbonImmutable $from, CarbonImmutable $to): Collection;
}
