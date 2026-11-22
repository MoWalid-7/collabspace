<?php

use App\Http\Controllers\Api\V1\Comments\CommentController;
use App\Http\Controllers\Api\V1\Tasks\TaskBoardController;
use App\Http\Controllers\Api\V1\Tasks\TaskCommentController;
use App\Http\Controllers\Api\V1\Tasks\TaskController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'workspace.current'])->group(function (): void {
    Route::get('/tasks', [TaskController::class, 'index'])->name('tasks.index');

    Route::get('/projects/{project}/tasks/board', [TaskBoardController::class, 'index'])
        ->whereNumber('project')
        ->name('projects.tasks.board');
    Route::post('/projects/{project}/tasks', [TaskController::class, 'store'])
        ->whereNumber('project')
        ->name('projects.tasks.store');

    Route::get('/tasks/{task}', [TaskController::class, 'show'])
        ->whereNumber('task')
        ->name('tasks.show');
    Route::match(['put', 'patch'], '/tasks/{task}', [TaskController::class, 'update'])
        ->whereNumber('task')
        ->name('tasks.update');
    Route::delete('/tasks/{task}', [TaskController::class, 'destroy'])
        ->whereNumber('task')
        ->name('tasks.destroy');
    Route::patch('/tasks/{task}/move', [TaskController::class, 'move'])
        ->whereNumber('task')
        ->name('tasks.move');
    Route::patch('/tasks/{task}/progress', [TaskController::class, 'progress'])
        ->whereNumber('task')
        ->name('tasks.progress');
    Route::post('/tasks/{task}/assignees', [TaskController::class, 'addAssignee'])
        ->whereNumber('task')
        ->name('tasks.assignees.store');
    Route::delete('/tasks/{task}/assignees/{user}', [TaskController::class, 'removeAssignee'])
        ->whereNumber('task')
        ->whereNumber('user')
        ->name('tasks.assignees.destroy');
    Route::get('/tasks/{task}/comments', [TaskCommentController::class, 'index'])
        ->whereNumber('task')
        ->name('tasks.comments.index');
    Route::post('/tasks/{task}/comments', [TaskCommentController::class, 'store'])
        ->whereNumber('task')
        ->name('tasks.comments.store');
    Route::put('/comments/{comment}', [CommentController::class, 'update'])
        ->whereNumber('comment')
        ->name('comments.update');
    Route::delete('/comments/{comment}', [CommentController::class, 'destroy'])
        ->whereNumber('comment')
        ->name('comments.destroy');
});
