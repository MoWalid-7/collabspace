<?php

namespace App\Services\Contracts;

use App\Data\Teams\CreateTeamData;
use App\Data\Teams\TeamFilters;
use App\Data\Teams\TeamMemberData;
use App\Data\Teams\UpdateTeamData;
use App\Models\Team;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface TeamServiceInterface
{
    public function list(TeamFilters $filters): LengthAwarePaginator;

    public function create(CreateTeamData $data): Team;

    public function show(int $teamId): Team;

    public function update(UpdateTeamData $data): Team;

    public function delete(int $teamId): void;

    public function addMember(TeamMemberData $data): User;

    public function updateMember(TeamMemberData $data): User;

    public function removeMember(TeamMemberData $data): void;
}
