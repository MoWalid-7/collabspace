<?php

namespace App\Services\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ActivityServiceInterface
{
    public function forCauser(int $userId, int $perPage): LengthAwarePaginator;

    public function forProject(int $projectId, int $perPage): LengthAwarePaginator;
}
