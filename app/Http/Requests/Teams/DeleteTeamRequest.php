<?php

namespace App\Http\Requests\Teams;

class DeleteTeamRequest extends TeamAccessRequest
{
    public function authorize(): bool
    {
        return $this->allows('delete');
    }

    public function rules(): array
    {
        return [];
    }
}
