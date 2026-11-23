<?php

use App\Http\Controllers\Api\V1\ActivityController;
use App\Http\Controllers\Api\V1\Projects\ProjectController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'workspace.current'])->group(function (): void {
    Route::get('/projects', [ProjectController::class, 'index'])->name('projects.index');
    Route::post('/projects', [ProjectController::class, 'store'])->name('projects.store');
    Route::get('/projects/{project}', [ProjectController::class, 'show'])
        ->whereNumber('project')
        ->name('projects.show');
    Route::get('/projects/{project}/activities', [ActivityController::class, 'project'])
        ->whereNumber('project')
        ->name('projects.activities.index');
    Route::match(['put', 'patch'], '/projects/{project}', [ProjectController::class, 'update'])
        ->whereNumber('project')
        ->name('projects.update');
    Route::delete('/projects/{project}', [ProjectController::class, 'destroy'])
        ->whereNumber('project')
        ->name('projects.destroy');

    Route::get('/projects/{project}/members', [ProjectController::class, 'members'])
        ->whereNumber('project')
        ->name('projects.members.index');
    Route::post('/projects/{project}/members', [ProjectController::class, 'addMember'])
        ->whereNumber('project')
        ->name('projects.members.store');
    Route::delete('/projects/{project}/members/{user}', [ProjectController::class, 'removeMember'])
        ->whereNumber('project')
        ->whereNumber('user')
        ->name('projects.members.destroy');

    Route::post('/projects/{project}/teams', [ProjectController::class, 'attachTeam'])
        ->whereNumber('project')
        ->name('projects.teams.store');
    Route::delete('/projects/{project}/teams/{team}', [ProjectController::class, 'detachTeam'])
        ->whereNumber('project')
        ->whereNumber('team')
        ->name('projects.teams.destroy');
});
