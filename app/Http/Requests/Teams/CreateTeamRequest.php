<?php

namespace App\Http\Requests\Teams;

use App\Models\Team;
use App\Support\CurrentWorkspace;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class CreateTeamRequest extends TeamAccessRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', Team::class);
    }

    public function rules(): array
    {
        $workspaceId = app(CurrentWorkspace::class)->id();

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('teams', 'name')->where('workspace_id', $workspaceId),
            ],
            'description' => ['sometimes', 'nullable', 'string'],
            'lead_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('workspace_user', 'user_id')
                    ->where(fn ($query) => $query->where('workspace_id', $workspaceId)),
            ],
        ];
    }
}
