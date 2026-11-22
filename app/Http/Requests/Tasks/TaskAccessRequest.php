<?php

namespace App\Http\Requests\Tasks;

use App\Models\Project;
use App\Models\Task;
use App\Repositories\Contracts\ProjectRepositoryInterface;
use App\Repositories\Contracts\TaskRepositoryInterface;
use Illuminate\Foundation\Http\FormRequest;

abstract class TaskAccessRequest extends FormRequest
{
    protected function task(): Task
    {
        return app(TaskRepositoryInterface::class)
            ->findInCurrentWorkspaceOrFail((int) $this->route('task'));
    }

    protected function project(): Project
    {
        return app(ProjectRepositoryInterface::class)
            ->findInCurrentWorkspaceOrFail((int) $this->route('project'));
    }

    protected function allowsTask(string $ability): bool
    {
        return $this->user()?->can($ability, $this->task()) ?? false;
    }

    protected function allowsProject(string $ability): bool
    {
        return $this->user()?->can($ability, [Task::class, $this->project()]) ?? false;
    }
}
