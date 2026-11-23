<?php

namespace App\Http\Requests\Files;

use App\Models\File;
use App\Repositories\Contracts\TaskRepositoryInterface;

class UploadTaskFileRequest extends FileUploadRequest
{
    protected function ability(): string
    {
        return 'upload';
    }

    public function authorize(): bool
    {
        $task = app(TaskRepositoryInterface::class)
            ->findInCurrentWorkspaceOrFail((int) $this->route('task'));

        return $this->user()?->can($this->ability(), [File::class, $task->project]) ?? false;
    }

    public function rules(): array
    {
        return [
            ...parent::rules(),
            'note' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
