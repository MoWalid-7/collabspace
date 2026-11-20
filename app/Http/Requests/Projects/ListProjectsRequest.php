<?php

namespace App\Http\Requests\Projects;

use App\Enums\ProjectPriority;
use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Support\CurrentWorkspace;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ListProjectsRequest extends ProjectAccessRequest
{
    public function authorize(): bool
    {
        return Gate::allows('viewAny', Project::class);
    }

    public function rules(): array
    {
        $workspaceId = app(CurrentWorkspace::class)->id();

        return [
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'nullable', Rule::enum(ProjectStatus::class)],
            'priority' => ['sometimes', 'nullable', Rule::enum(ProjectPriority::class)],
            'team_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('teams', 'id')
                    ->where(fn ($query) => $query->where('workspace_id', $workspaceId)),
            ],
            'sort' => ['sometimes', 'in:newest,oldest,deadline'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
