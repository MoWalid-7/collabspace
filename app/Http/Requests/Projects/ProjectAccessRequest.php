<?php

namespace App\Http\Requests\Projects;

use App\Models\Project;
use App\Repositories\Contracts\ProjectRepositoryInterface;
use App\Support\CurrentWorkspace;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class ProjectAccessRequest extends FormRequest
{
    protected function project(): Project
    {
        return app(ProjectRepositoryInterface::class)
            ->findInCurrentWorkspaceOrFail((int) $this->route('project'));
    }

    protected function allows(string $ability): bool
    {
        $user = $this->user();

        return $user !== null && $user->can($ability, $this->project());
    }

    protected function workspaceMemberRule(): mixed
    {
        $workspaceId = app(CurrentWorkspace::class)->id();

        return Rule::exists('workspace_user', 'user_id')
            ->where(fn ($query) => $query->where('workspace_id', $workspaceId));
    }

    protected function workspaceTeamRule(): mixed
    {
        $workspaceId = app(CurrentWorkspace::class)->id();

        return Rule::exists('teams', 'id')
            ->where(fn ($query) => $query->where('workspace_id', $workspaceId));
    }
}
