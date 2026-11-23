<?php

namespace App\Http\Requests\Files;

use App\Models\File;
use App\Repositories\Contracts\FileRepositoryInterface;

class UploadProjectFileRequest extends FileUploadRequest
{
    public function authorize(): bool
    {
        $project = app(FileRepositoryInterface::class)
            ->findProjectOrFail((int) $this->route('project'));

        return $this->user()?->can('upload', [File::class, $project]) ?? false;
    }

    public function rules(): array
    {
        return [
            ...parent::rules(),
            'task_id' => ['sometimes', 'nullable', 'integer'],
            'note' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
