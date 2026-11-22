<?php

use App\Data\Tasks\MoveTaskData;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Repositories\Contracts\ProjectRepositoryInterface;
use App\Repositories\Contracts\TaskRepositoryInterface;
use App\Services\TaskService;
use Mockery;

it('reorders both task columns when moving a task across statuses', function () {
    $project = new Project;
    $project->setAttribute('id', 7);
    $task = new Task;
    $task->setAttribute('id', 2);
    $task->setAttribute('status', TaskStatus::Todo);
    $task->setRelation('project', $project);

    $tasks = Mockery::mock(TaskRepositoryInterface::class);
    $projects = Mockery::mock(ProjectRepositoryInterface::class);
    $tasks->shouldReceive('findInCurrentWorkspaceOrFail')
        ->twice()
        ->with(2)
        ->andReturn($task, $task);
    $tasks->shouldReceive('taskIdsInColumn')
        ->once()
        ->with($project, TaskStatus::Todo, true)
        ->andReturn([1, 2, 3]);
    $tasks->shouldReceive('taskIdsInColumn')
        ->once()
        ->with($project, TaskStatus::InProgress, true)
        ->andReturn([4]);
    $tasks->shouldReceive('updateTask')
        ->once()
        ->with($task, [
            'status' => TaskStatus::InProgress,
            'completed_at' => null,
        ])
        ->andReturnUsing(function (Task $updatedTask, array $attributes): Task {
            $updatedTask->setAttribute('status', $attributes['status']);

            return $updatedTask;
        });
    $tasks->shouldReceive('setColumnPositions')
        ->once()
        ->with([1, 3]);
    $tasks->shouldReceive('setColumnPositions')
        ->once()
        ->with([4, 2]);

    $movedTask = (new TaskService($tasks, $projects))->move(
        new MoveTaskData(2, TaskStatus::InProgress, 1),
    );

    expect($movedTask->status)->toBe(TaskStatus::InProgress);
});

it('reorders a task within its current column without changing its status', function () {
    $project = new Project;
    $project->setAttribute('id', 7);
    $task = new Task;
    $task->setAttribute('id', 2);
    $task->setAttribute('status', TaskStatus::Todo);
    $task->setRelation('project', $project);

    $tasks = Mockery::mock(TaskRepositoryInterface::class);
    $projects = Mockery::mock(ProjectRepositoryInterface::class);
    $tasks->shouldReceive('findInCurrentWorkspaceOrFail')
        ->twice()
        ->with(2)
        ->andReturn($task, $task);
    $tasks->shouldReceive('taskIdsInColumn')
        ->once()
        ->with($project, TaskStatus::Todo, true)
        ->andReturn([1, 2, 3]);
    $tasks->shouldReceive('setColumnPositions')
        ->once()
        ->with([1, 3, 2]);
    $tasks->shouldNotReceive('updateTask');

    $movedTask = (new TaskService($tasks, $projects))->move(
        new MoveTaskData(2, TaskStatus::Todo, 2),
    );

    expect($movedTask->status)->toBe(TaskStatus::Todo);
});
