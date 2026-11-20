<?php

namespace App\Http\Requests\Projects;

class AttachProjectTeamRequest extends ProjectTeamRequest
{
    public function authorize(): bool
    {
        return $this->allows('manageTeams');
    }

    public function rules(): array
    {
        return [
            'team_id' => ['required', 'integer', $this->workspaceTeamRule()],
        ];
    }
}
