<?php

namespace App\Services;

use App\Repositories\Contracts\ActivityRepositoryInterface;
use App\Services\Contracts\ActivityServiceInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ActivityService implements ActivityServiceInterface
{
    public function __construct(private ActivityRepositoryInterface $activities) {}

    public function forCauser(int $userId, int $perPage): LengthAwarePaginator
    {
        return $this->activities->paginateForCauser($userId, $perPage);
    }

    public function forProject(int $projectId, int $perPage): LengthAwarePaginator
    {
        return $this->activities->paginateForProject($projectId, $perPage);
    }
}
