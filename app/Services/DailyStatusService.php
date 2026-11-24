<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\DailyStatus\DailyStatusFilters;
use App\Data\DailyStatus\UpsertDailyStatusData;
use App\Models\DailyStatus;
use App\Models\Project;
use App\Models\User;
use App\Repositories\Contracts\DailyStatusRepositoryInterface;
use App\Repositories\Contracts\ProjectRepositoryInterface;
use App\Repositories\Contracts\WorkspaceRepositoryInterface;
use App\Services\Contracts\DailyStatusServiceInterface;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;

final class DailyStatusService implements DailyStatusServiceInterface
{
    public function __construct(
        private readonly DailyStatusRepositoryInterface $dailyStatuses,
        private readonly WorkspaceRepositoryInterface $workspaces,
        private readonly ProjectRepositoryInterface $projects,
    ) {}

    public function upsert(User $user, int $workspaceId, UpsertDailyStatusData $data): DailyStatus
    {
        if ($data->projectId !== null) {
            $this->projects->findInWorkspaceOrFail($workspaceId, $data->projectId);
        }

        return $this->dailyStatuses->upsertStatus(
            $workspaceId,
            $user->id,
            $data->projectId,
            $data->date,
            $data->doneYesterday,
            $data->planToday,
            $data->blockers,
        );
    }

    public function list(User $user, int $workspaceId, DailyStatusFilters $filters): Collection
    {
        $role = $this->workspaces->roleForWorkspaceId($workspaceId, $user->id);
        $isAdminOrManager = in_array($role, ['admin', 'manager'], true);

        // Members are forced to their own statuses
        $forcedUserId = $isAdminOrManager ? null : $user->id;

        return $this->dailyStatuses->list($workspaceId, $filters, $forcedUserId);
    }

    public function getTeamStatus(User $user, int $workspaceId, int $projectId, string $date): array
    {
        $project = $this->projects->findInWorkspaceOrFail($workspaceId, $projectId);
        $this->ensureCanViewProject($user, $workspaceId, $project);

        return $this->dailyStatuses->getTeamStatusForProject($project, $date);
    }

    public function getBlockers(User $user, int $workspaceId, int $projectId, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        $project = $this->projects->findInWorkspaceOrFail($workspaceId, $projectId);
        $this->ensureCanViewProject($user, $workspaceId, $project);

        return $this->dailyStatuses->getBlockersForProject($project, $from, $to);
    }

    private function ensureCanViewProject(User $user, int $workspaceId, Project $project): void
    {
        $role = $this->workspaces->roleForWorkspaceId($workspaceId, $user->id);
        if (in_array($role, ['admin', 'manager'], true)) {
            return;
        }

        if (! $project->allMembers()->contains('id', $user->id)) {
            throw new AuthorizationException('You are not authorized to view this project.');
        }
    }
}
