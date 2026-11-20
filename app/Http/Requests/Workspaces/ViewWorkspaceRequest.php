<?php

namespace App\Http\Requests\Workspaces;

use App\Support\CurrentWorkspace;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ViewWorkspaceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $workspace = app(CurrentWorkspace::class)->get();

        return $workspace !== null && Gate::allows('view', $workspace);
    }

    public function rules(): array
    {
        return [];
    }
}
