<?php

namespace App\Http\Requests\Teams;

use App\Models\Team;
use App\Repositories\Contracts\TeamRepositoryInterface;
use Illuminate\Foundation\Http\FormRequest;

abstract class TeamAccessRequest extends FormRequest
{
    protected function team(): Team
    {
        return app(TeamRepositoryInterface::class)
            ->findInCurrentWorkspaceOrFail((int) $this->route('team'));
    }

    protected function allows(string $ability): bool
    {
        $user = $this->user();

        return $user !== null && $user->can($ability, $this->team());
    }
}
