<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Data\DailyStatus\DailyStatusFilters;
use App\Models\DailyStatus;
use App\Models\Project;
use App\Models\User;
use App\Repositories\Contracts\DailyStatusRepositoryInterface;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

final class DailyStatusRepository implements DailyStatusRepositoryInterface
{
    public function upsertStatus(
        int $workspaceId,
        int $userId,
        ?int $projectId,
        string $date,
        string $doneYesterday,
        string $planToday,
        ?string $blockers
    ): DailyStatus {
        $attributes = [
            'workspace_id' => $workspaceId,
            'user_id' => $userId,
            'project_id' => $projectId,
            'date' => $date,
        ];

        $values = [
            'done_yesterday' => $doneYesterday,
            'plan_today' => $planToday,
            'blockers' => $blockers,
        ];

        /** @var DailyStatus $status */
        $status = DailyStatus::query()->updateOrCreate($attributes, $values);
        $status->load(['user', 'project']);

        return $status;
    }

    public function list(int $workspaceId, DailyStatusFilters $filters, ?int $forcedUserId = null): Collection
    {
        $query = DailyStatus::query()
            ->where('workspace_id', $workspaceId)
            ->with(['user', 'project']);

        if ($forcedUserId !== null) {
            $query->where('user_id', $forcedUserId);
        } elseif ($filters->userId !== null) {
            $query->where('user_id', $filters->userId);
        }

        if ($filters->date !== null) {
            $query->where('date', $filters->date);
        }

        if ($filters->projectId !== null) {
            $query->where('project_id', $filters->projectId);
        }

        /** @var Collection<int, DailyStatus> $results */
        $results = $query->orderBy('date', 'desc')->orderBy('created_at', 'desc')->get();

        return $results;
    }

    public function getTeamStatusForProject(Project $project, string $date): array
    {
        // Get all members of the project (direct or via team)
        $members = $project->allMembers();

        $statuses = DailyStatus::query()
            ->where('project_id', $project->id)
            ->where('date', $date)
            ->with(['user'])
            ->get()
            ->keyBy('user_id');

        $result = [];
        foreach ($members as $member) {
            /** @var User $member */
            $status = $statuses->get($member->id);
            $result[] = [
                'user' => $member,
                'status' => $status,
                'submitted' => $status !== null,
            ];
        }

        return $result;
    }

    public function getBlockersForProject(Project $project, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        /** @var Collection<int, DailyStatus> $results */
        $results = DailyStatus::query()
            ->where('project_id', $project->id)
            ->where('date', '>=', $from->toDateString())
            ->where('date', '<=', $to->toDateString())
            ->whereNotNull('blockers')
            ->where('blockers', '!=', '')
            ->with('user')
            ->orderBy('date', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        return $results;
    }

    public function getUserIdsMissingDailyStatus(int $workspaceId, string $date): array
    {
        // Workspace members who do not have any daily status recorded for this date in this workspace
        $submittedUserIds = DailyStatus::query()
            ->withoutGlobalScopes()
            ->where('workspace_id', $workspaceId)
            ->where('date', $date)
            ->pluck('user_id')
            ->all();

        return User::query()
            ->whereHas('workspaces', function (Builder $q) use ($workspaceId) {
                $q->where('workspaces.id', $workspaceId);
            })
            ->whereNotIn('id', $submittedUserIds)
            ->pluck('id')
            ->all();
    }
}
