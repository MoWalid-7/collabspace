<?php

namespace App\Repositories\Eloquent;

use App\Models\Activity;
use App\Models\User;
use App\Repositories\Contracts\ActivityRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ActivityRepository implements ActivityRepositoryInterface
{
    public function create(array $attributes): Activity
    {
        return Activity::withoutGlobalScopes()->create($attributes);
    }

    public function paginateForCauser(int $userId, int $perPage): LengthAwarePaginator
    {
        return Activity::withoutGlobalScopes()
            ->where('causer_id', $userId)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function paginateForProject(int $projectId, int $perPage): LengthAwarePaginator
    {
        return Activity::query()
            ->where('project_id', $projectId)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function causerName(int $userId): string
    {
        return User::query()->whereKey($userId)->value('name') ?? 'Former member';
    }
}
