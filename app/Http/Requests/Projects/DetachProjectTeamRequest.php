<?php

namespace App\Http\Requests\Projects;

class DetachProjectTeamRequest extends ProjectTeamRequest
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

    /**
     * @return array<string, mixed>
     */
    public function validationData(): array
    {
        return [
            ...parent::validationData(),
            'team_id' => $this->route('team'),
        ];
    }
}
