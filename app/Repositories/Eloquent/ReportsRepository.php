<?php

namespace App\Repositories\Eloquent;

use App\Data\Reports\ReportOverviewData;
use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use App\Models\Activity;
use App\Models\Project;
use App\Models\Report;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\Workspace;
use App\Repositories\Contracts\ProjectRepositoryInterface;
use App\Repositories\Contracts\ReportsRepositoryInterface;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class ReportsRepository implements ReportsRepositoryInterface
{
    public function __construct(private ProjectRepositoryInterface $projects) {}

    private function getVisibleProjectIds(int $workspaceId, User $user, ?int $projectId = null): array
    {
        $query = Project::query();
        $this->projects->applyVisibility($query, $workspaceId, (int) $user->getKey());

        if ($projectId !== null) {
            $query->whereKey($projectId);
        }

        return $query->pluck('id')->map(fn ($id): int => (int) $id)->all();
    }

    public function getOverview(
        int $workspaceId,
        User $user,
        CarbonImmutable $from,
        CarbonImmutable $to,
        ?int $projectId = null,
    ): ReportOverviewData {
        $visibleProjectIds = $this->getVisibleProjectIds($workspaceId, $user, $projectId);

        $periodDays = $from->diffInDays($to) + 1;
        $prevTo = $from->subSecond();
        $prevFrom = $from->subDays($periodDays);

        // 1. task_completion_rate: completed / total of tasks created in the period, as a percentage.
        $currentTaskCounts = Task::query()
            ->where('workspace_id', $workspaceId)
            ->whereIn('project_id', $visibleProjectIds)
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('count(*) as total, count(case when status = ? then 1 end) as completed', [TaskStatus::Completed->value])
            ->first();

        $curTotal = (int) ($currentTaskCounts->total ?? 0);
        $curCompleted = (int) ($currentTaskCounts->completed ?? 0);
        $curTaskRate = $curTotal > 0 ? (int) round(($curCompleted / $curTotal) * 100) : 0;

        $prevTaskCounts = Task::query()
            ->where('workspace_id', $workspaceId)
            ->whereIn('project_id', $visibleProjectIds)
            ->whereBetween('created_at', [$prevFrom, $prevTo])
            ->selectRaw('count(*) as total, count(case when status = ? then 1 end) as completed', [TaskStatus::Completed->value])
            ->first();

        $prevTotal = (int) ($prevTaskCounts->total ?? 0);
        $prevCompleted = (int) ($prevTaskCounts->completed ?? 0);
        $prevTaskRate = $prevTotal > 0 ? (int) round(($prevCompleted / $prevTotal) * 100) : 0;

        $taskRateChange = $this->calculateChangePercent($curTaskRate, $prevTaskRate);

        // 2. projects_completion_rate: projects with status done (or completed) / total visible projects.
        // ProjectStatus enum: Draft, Active, OnHold, Completed, Cancelled
        $totalProjects = Project::query()
            ->where('workspace_id', $workspaceId)
            ->whereIn('id', $visibleProjectIds)
            ->count();

        $completedProjects = Project::query()
            ->where('workspace_id', $workspaceId)
            ->whereIn('id', $visibleProjectIds)
            ->where('status', ProjectStatus::Done->value)
            ->count();

        $curProjectsRate = $totalProjects > 0 ? (int) round(($completedProjects / $totalProjects) * 100) : 0;
        // Previous period project completion rate comparison:
        // As project status history is not snapshotted, comparing against 0 baseline yields null or previous completed
        $prevProjectsRate = 0; // Baseline previous is 0
        $projectsRateChange = $this->calculateChangePercent($curProjectsRate, $prevProjectsRate);

        // 3. teams_activity_rate: members with at least one recorded activity in the period / total workspace members.
        $totalMembers = Workspace::query()
            ->findOrFail($workspaceId)
            ->members()
            ->count();

        $activeMemberCount = Activity::query()
            ->where('workspace_id', $workspaceId)
            ->whereBetween('created_at', [$from, $to])
            ->whereNotNull('causer_id')
            ->distinct('causer_id')
            ->count('causer_id');

        $curTeamsRate = $totalMembers > 0 ? (int) round(($activeMemberCount / $totalMembers) * 100) : 0;

        $prevActiveMemberCount = Activity::query()
            ->where('workspace_id', $workspaceId)
            ->whereBetween('created_at', [$prevFrom, $prevTo])
            ->whereNotNull('causer_id')
            ->distinct('causer_id')
            ->count('causer_id');

        $prevTeamsRate = $totalMembers > 0 ? (int) round(($prevActiveMemberCount / $totalMembers) * 100) : 0;
        $teamsRateChange = $this->calculateChangePercent($curTeamsRate, $prevTeamsRate);

        return new ReportOverviewData(
            taskCompletionRate: ['value' => $curTaskRate, 'change_percent' => $taskRateChange],
            projectsCompletionRate: ['value' => $curProjectsRate, 'change_percent' => $projectsRateChange],
            teamsActivityRate: ['value' => $curTeamsRate, 'change_percent' => $teamsRateChange],
        );
    }

    public function getTaskCompletionVsDeadline(
        int $workspaceId,
        User $user,
        string $range,
        ?int $projectId = null,
    ): array {
        $visibleProjectIds = $this->getVisibleProjectIds($workspaceId, $user, $projectId);

        $now = CarbonImmutable::now();

        if ($range === 'week') {
            $start = $now->subDays(6)->startOfDay();
            $end = $now->endOfDay();

            $tasks = Task::query()
                ->where('workspace_id', $workspaceId)
                ->whereIn('project_id', $visibleProjectIds)
                ->where('status', TaskStatus::Completed->value)
                ->whereNotNull('completed_at')
                ->whereBetween('completed_at', [$start, $end])
                ->get(['completed_at', 'due_date']);

            $buckets = [];
            for ($i = 0; $i < 7; $i++) {
                $date = $start->addDays($i);
                $key = $date->toDateString();
                $buckets[$key] = [
                    'label' => $date->format('D'),
                    'on_time' => 0,
                    'late' => 0,
                ];
            }

            foreach ($tasks as $task) {
                if ($task->completed_at === null) {
                    continue;
                }
                $key = $task->completed_at->toDateString();
                if (! isset($buckets[$key])) {
                    continue;
                }

                $isOnTime = true;
                if ($task->due_date !== null) {
                    $dueEnd = Carbon::parse($task->due_date)->endOfDay();
                    $isOnTime = $task->completed_at->lessThanOrEqualTo($dueEnd);
                }

                if ($isOnTime) {
                    $buckets[$key]['on_time']++;
                } else {
                    $buckets[$key]['late']++;
                }
            }

            return array_values($buckets);
        }

        // year: 12 months of current year
        $start = $now->startOfYear();
        $end = $now->endOfYear();

        $tasks = Task::query()
            ->where('workspace_id', $workspaceId)
            ->whereIn('project_id', $visibleProjectIds)
            ->where('status', TaskStatus::Completed->value)
            ->whereNotNull('completed_at')
            ->whereBetween('completed_at', [$start, $end])
            ->get(['completed_at', 'due_date']);

        $buckets = [];
        for ($m = 1; $m <= 12; $m++) {
            $monthDate = $start->month($m);
            $key = $monthDate->format('Y-m');
            $buckets[$key] = [
                'label' => $monthDate->format('M'),
                'on_time' => 0,
                'late' => 0,
            ];
        }

        foreach ($tasks as $task) {
            if ($task->completed_at === null) {
                continue;
            }
            $key = $task->completed_at->format('Y-m');
            if (! isset($buckets[$key])) {
                continue;
            }

            $isOnTime = true;
            if ($task->due_date !== null) {
                $dueEnd = Carbon::parse($task->due_date)->endOfDay();
                $isOnTime = $task->completed_at->lessThanOrEqualTo($dueEnd);
            }

            if ($isOnTime) {
                $buckets[$key]['on_time']++;
            } else {
                $buckets[$key]['late']++;
            }
        }

        return array_values($buckets);
    }

    public function getProjectsProgress(
        int $workspaceId,
        User $user,
        ?int $projectId = null,
    ): array {
        $query = Project::query();
        $this->projects->applyVisibility($query, $workspaceId, (int) $user->getKey());
        if ($projectId !== null) {
            $query->whereKey($projectId);
        }

        $projects = $query
            ->withCount([
                'tasks as tasks_total',
                'tasks as tasks_completed' => fn (Builder $q) => $q->where('status', TaskStatus::Completed->value),
            ])
            ->get();

        $result = [];
        foreach ($projects as $project) {
            $total = (int) $project->tasks_total;
            $completed = (int) $project->tasks_completed;
            $progressPercent = $total > 0 ? (int) round(($completed / $total) * 100) : 0;

            $result[] = [
                'id' => $project->id,
                'name' => $project->name,
                'status' => $project->status->value,
                'deadline' => $project->deadline ? Carbon::parse($project->deadline)->toDateString() : null,
                'tasks_total' => $total,
                'tasks_completed' => $completed,
                'progress_percent' => $progressPercent,
            ];
        }

        return $result;
    }

    public function getWorkerActivity(
        int $workspaceId,
        User $user,
        string $period,
        CarbonImmutable $from,
        CarbonImmutable $to,
        ?int $projectId = null,
    ): array {
        $visibleProjectIds = $this->getVisibleProjectIds($workspaceId, $user, $projectId);

        $workspace = Workspace::query()->findOrFail($workspaceId);
        $members = $workspace->members()->get();

        // Tasks completed per user
        $tasksCompletedByUser = Task::query()
            ->where('workspace_id', $workspaceId)
            ->whereIn('project_id', $visibleProjectIds)
            ->where('status', TaskStatus::Completed->value)
            ->whereBetween('completed_at', [$from, $to])
            ->join('task_assignees', 'tasks.id', '=', 'task_assignees.task_id')
            ->selectRaw('task_assignees.user_id, count(distinct tasks.id) as completed_count')
            ->groupBy('task_assignees.user_id')
            ->pluck('completed_count', 'user_id');

        // Hours logged per user
        $hoursLoggedByUser = TimeEntry::query()
            ->where('workspace_id', $workspaceId)
            ->whereIn('project_id', $visibleProjectIds)
            ->whereBetween('started_at', [$from, $to])
            ->selectRaw('user_id, sum(duration_seconds) as total_seconds')
            ->groupBy('user_id')
            ->pluck('total_seconds', 'user_id');

        // Activities count per user
        $activitiesByUser = Activity::query()
            ->where('workspace_id', $workspaceId)
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('causer_id, count(*) as act_count')
            ->groupBy('causer_id')
            ->pluck('act_count', 'causer_id');

        $membersData = [];
        foreach ($members as $member) {
            $userId = (int) $member->id;
            $seconds = (int) ($hoursLoggedByUser[$userId] ?? 0);
            $membersData[] = [
                'id' => $userId,
                'name' => $member->name,
                'email' => $member->email,
                'tasks_completed' => (int) ($tasksCompletedByUser[$userId] ?? 0),
                'hours_logged' => round($seconds / 3600, 1),
                'activities_count' => (int) ($activitiesByUser[$userId] ?? 0),
            ];
        }

        // Series totals per bucket
        $series = [];
        if ($period === 'weekly') {
            $current = $from->startOfDay();
            while ($current->lessThanOrEqualTo($to)) {
                $bucketEnd = $current->endOfDay();
                $label = $current->format('M d');

                $bCompleted = (int) Task::query()
                    ->where('workspace_id', $workspaceId)
                    ->whereIn('project_id', $visibleProjectIds)
                    ->where('status', TaskStatus::Completed->value)
                    ->whereBetween('completed_at', [$current, $bucketEnd])
                    ->count();

                $bSeconds = (int) TimeEntry::query()
                    ->where('workspace_id', $workspaceId)
                    ->whereIn('project_id', $visibleProjectIds)
                    ->whereBetween('started_at', [$current, $bucketEnd])
                    ->sum('duration_seconds');

                $bActivities = (int) Activity::query()
                    ->where('workspace_id', $workspaceId)
                    ->whereBetween('created_at', [$current, $bucketEnd])
                    ->count();

                $series[] = [
                    'label' => $label,
                    'tasks_completed' => $bCompleted,
                    'hours_logged' => round($bSeconds / 3600, 1),
                    'activities_count' => $bActivities,
                ];

                $current = $current->addDay();
            }
        } else {
            // monthly: bucket by month
            $current = $from->startOfMonth();
            while ($current->lessThanOrEqualTo($to)) {
                $bucketEnd = $current->endOfMonth()->endOfDay();
                $label = $current->format('M Y');

                $bCompleted = (int) Task::query()
                    ->where('workspace_id', $workspaceId)
                    ->whereIn('project_id', $visibleProjectIds)
                    ->where('status', TaskStatus::Completed->value)
                    ->whereBetween('completed_at', [$current, $bucketEnd])
                    ->count();

                $bSeconds = (int) TimeEntry::query()
                    ->where('workspace_id', $workspaceId)
                    ->whereIn('project_id', $visibleProjectIds)
                    ->whereBetween('started_at', [$current, $bucketEnd])
                    ->sum('duration_seconds');

                $bActivities = (int) Activity::query()
                    ->where('workspace_id', $workspaceId)
                    ->whereBetween('created_at', [$current, $bucketEnd])
                    ->count();

                $series[] = [
                    'label' => $label,
                    'tasks_completed' => $bCompleted,
                    'hours_logged' => round($bSeconds / 3600, 1),
                    'activities_count' => $bActivities,
                ];

                $current = $current->addMonth();
            }
        }

        return [
            'members' => $membersData,
            'series' => $series,
        ];
    }

    public function createReport(array $attributes): Report
    {
        return Report::query()->create($attributes);
    }

    public function findInCurrentWorkspaceOrFail(int $id): Report
    {
        return Report::query()->findOrFail($id);
    }

    public function paginateUserReports(int $workspaceId, int $userId, int $perPage = 15): LengthAwarePaginator
    {
        return Report::query()
            ->where('workspace_id', $workspaceId)
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    public function updateReport(Report $report, array $attributes): Report
    {
        $report->update($attributes);

        return $report->fresh() ?? $report;
    }

    public function getTasksForExport(int $workspaceId, CarbonImmutable $from, CarbonImmutable $to, ?int $projectId = null): array
    {
        $query = Task::query()
            ->where('workspace_id', $workspaceId)
            ->whereBetween('created_at', [$from, $to])
            ->with(['project']);

        if ($projectId !== null) {
            $query->where('project_id', $projectId);
        }

        return $query->get()->map(fn (Task $t): array => [
            'id' => $t->id,
            'project' => $t->project->name,
            'title' => $t->title,
            'status' => $t->status->value,
            'priority' => $t->priority->value,
            'due_date' => $t->due_date ? Carbon::parse($t->due_date)->toDateString() : '',
            'created_at' => $t->created_at?->toDateTimeString() ?? '',
        ])->all();
    }

    public function getProjectsForExport(int $workspaceId, ?int $projectId = null): array
    {
        $query = Project::query()
            ->where('workspace_id', $workspaceId)
            ->withCount(['tasks as tasks_total', 'tasks as tasks_completed' => fn (Builder $q) => $q->where('status', TaskStatus::Completed->value)]);

        if ($projectId !== null) {
            $query->where('id', $projectId);
        }

        return $query->get()->map(function (Project $p): array {
            return [
                'id' => $p->id,
                'name' => $p->name,
                'status' => $p->status->value,
                'priority' => $p->priority->value,
                'deadline' => $p->deadline ? Carbon::parse($p->deadline)->toDateString() : '',
                'tasks_total' => (int) $p->tasks_total,
                'tasks_completed' => (int) $p->tasks_completed,
            ];
        })->all();
    }

    public function getTimeEntriesForExport(int $workspaceId, CarbonImmutable $from, CarbonImmutable $to, ?int $projectId = null): array
    {
        $query = TimeEntry::query()
            ->where('workspace_id', $workspaceId)
            ->whereBetween('started_at', [$from, $to])
            ->with(['project', 'task', 'user']);

        if ($projectId !== null) {
            $query->where('project_id', $projectId);
        }

        return $query->get()->map(fn (TimeEntry $te): array => [
            'id' => $te->id,
            'user' => $te->user->name,
            'project' => $te->project->name,
            'task' => $te->task ? $te->task->title : '',
            'started_at' => $te->started_at->toDateTimeString(),
            'ended_at' => $te->ended_at?->toDateTimeString() ?? '',
            'duration_seconds' => $te->duration_seconds ?? 0,
            'note' => $te->note ?? '',
        ])->all();
    }

    public function getWorkersForExport(int $workspaceId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $workspace = Workspace::query()->findOrFail($workspaceId);
        $members = $workspace->members()->get();

        $tasksCompleted = Task::query()
            ->where('workspace_id', $workspaceId)
            ->where('status', TaskStatus::Completed->value)
            ->whereBetween('completed_at', [$from, $to])
            ->join('task_assignees', 'tasks.id', '=', 'task_assignees.task_id')
            ->selectRaw('task_assignees.user_id, count(distinct tasks.id) as completed_count')
            ->groupBy('task_assignees.user_id')
            ->pluck('completed_count', 'user_id');

        $hoursLogged = TimeEntry::query()
            ->where('workspace_id', $workspaceId)
            ->whereBetween('started_at', [$from, $to])
            ->selectRaw('user_id, sum(duration_seconds) as total_seconds')
            ->groupBy('user_id')
            ->pluck('total_seconds', 'user_id');

        $activities = Activity::query()
            ->where('workspace_id', $workspaceId)
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('causer_id, count(*) as act_count')
            ->groupBy('causer_id')
            ->pluck('act_count', 'causer_id');

        return $members->map(fn (User $m): array => [
            'id' => $m->id,
            'name' => $m->name,
            'email' => $m->email,
            'tasks_completed' => (int) ($tasksCompleted[$m->id] ?? 0),
            'hours_logged' => round(((int) ($hoursLogged[$m->id] ?? 0)) / 3600, 1),
            'activities_count' => (int) ($activities[$m->id] ?? 0),
        ])->all();
    }

    private function calculateChangePercent(int $current, int $previous): ?int
    {
        if ($previous === 0) {
            return null;
        }

        return (int) round((($current - $previous) / $previous) * 100);
    }
}
