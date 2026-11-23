<?php

namespace App\Http\Requests\Files;

use App\Enums\FileCategory;
use App\Models\File;
use App\Repositories\Contracts\FileRepositoryInterface;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListProjectFilesRequest extends FormRequest
{
    public function authorize(): bool
    {
        $project = app(FileRepositoryInterface::class)
            ->findProjectOrFail((int) $this->route('project'));

        return $this->user()?->can('viewAny', [File::class, $project]) ?? false;
    }

    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'string', 'max:255'],
            'type' => ['sometimes', Rule::enum(FileCategory::class)],
            'task_id' => ['sometimes', 'integer'],
            'uploaded_by' => ['sometimes', 'integer'],
            'sort' => ['sometimes', Rule::in(['newest', 'oldest', 'name', 'size'])],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
