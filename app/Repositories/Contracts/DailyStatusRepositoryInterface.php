<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Data\DailyStatus\DailyStatusFilters;
use App\Models\DailyStatus;
use App\Models\Project;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

interface DailyStatusRepositoryInterface
{
    public function upsertStatus(
        int $workspaceId,
        int $userId,
        ?int $projectId,
        string $date,
        string $doneYesterday,
        string $planToday,
        ?string $blockers
    ): DailyStatus;

    /**
     * @return Collection<int, DailyStatus>
     */
    public function list(int $workspaceId, DailyStatusFilters $filters, ?int $forcedUserId = null): Collection;

    /**
     * Returns an array of members with their status for the given project & date.
     * Each item: ['user' => User, 'status' => ?DailyStatus, 'submitted' => bool]
     *
     * @return array<int, array{user: User, status: ?DailyStatus, submitted: bool}>
     */
    public function getTeamStatusForProject(Project $project, string $date): array;

    /**
     * @return Collection<int, DailyStatus>
     */
    public function getBlockersForProject(Project $project, CarbonImmutable $from, CarbonImmutable $to): Collection;

    /**
     * Returns user IDs of workspace members who have not submitted a status for today.
     *
     * @return array<int, int>
     */
    public function getUserIdsMissingDailyStatus(int $workspaceId, string $date): array;
}
