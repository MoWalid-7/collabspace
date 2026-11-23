<?php

namespace App\Repositories\Contracts;

use App\Models\Activity;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ActivityRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Activity;

    public function paginateForCauser(int $userId, int $perPage): LengthAwarePaginator;

    public function paginateForProject(int $projectId, int $perPage): LengthAwarePaginator;

    public function causerName(int $userId): string;
}
