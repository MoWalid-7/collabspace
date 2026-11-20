<?php

namespace App\Http\Requests\Teams;

use App\Support\CurrentWorkspace;
use Illuminate\Validation\Rule;

class UpdateTeamRequest extends TeamAccessRequest
{
    public function authorize(): bool
    {
        return $this->allows('update');
    }

    public function rules(): array
    {
        $workspaceId = app(CurrentWorkspace::class)->id();

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('teams', 'name')
                    ->where('workspace_id', $workspaceId)
                    ->ignore($this->route('team')),
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
