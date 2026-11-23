<?php

namespace App\Http\Requests\Files;

use App\Models\File;
use App\Repositories\Contracts\TaskRepositoryInterface;
use Illuminate\Foundation\Http\FormRequest;

abstract class TaskFileAccessRequest extends FormRequest
{
    abstract protected function ability(): string;

    public function authorize(): bool
    {
        $task = app(TaskRepositoryInterface::class)
            ->findInCurrentWorkspaceOrFail((int) $this->route('task'));

        return $this->user()?->can($this->ability(), [File::class, $task->project]) ?? false;
    }
}
