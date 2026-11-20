<?php

namespace App\Http\Requests\Workspaces;

use App\Support\CurrentWorkspace;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class RemoveWorkspaceMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        $workspace = app(CurrentWorkspace::class)->get();

        return $workspace !== null && Gate::allows('manageMembers', $workspace);
    }

    public function rules(): array
    {
        return [];
    }
}
