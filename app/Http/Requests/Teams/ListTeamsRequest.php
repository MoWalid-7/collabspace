<?php

namespace App\Http\Requests\Teams;

use App\Models\Team;
use Illuminate\Support\Facades\Gate;

class ListTeamsRequest extends TeamAccessRequest
{
    public function authorize(): bool
    {
        return Gate::allows('viewAny', Team::class);
    }

    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
