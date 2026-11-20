<?php

namespace App\Http\Requests\Teams;

class ShowTeamRequest extends TeamAccessRequest
{
    public function authorize(): bool
    {
        return $this->allows('view');
    }

    public function rules(): array
    {
        return [];
    }
}
