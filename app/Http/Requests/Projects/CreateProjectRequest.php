<?php

namespace App\Http\Requests\Projects;

use App\Enums\ProjectPriority;
use App\Enums\ProjectStatus;
use App\Models\Project;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class CreateProjectRequest extends ProjectAccessRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', Project::class);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'type' => ['required', 'string', 'max:255'],
            'priority' => ['required', Rule::enum(ProjectPriority::class)],
            'status' => ['required', Rule::enum(ProjectStatus::class)],
            'start_date' => ['required', 'date'],
            'deadline' => ['required', 'date', 'after_or_equal:start_date'],
            'member_ids' => ['sometimes', 'array'],
            'member_ids.*' => ['integer', $this->workspaceMemberRule(), 'distinct'],
            'team_ids' => ['sometimes', 'array'],
            'team_ids.*' => ['integer', $this->workspaceTeamRule(), 'distinct'],
        ];
    }
}
