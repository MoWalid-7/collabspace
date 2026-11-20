<?php

use App\Http\Controllers\Api\V1\Teams\TeamController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'workspace.current'])->group(function (): void {
    Route::get('/teams', [TeamController::class, 'index'])->name('teams.index');
    Route::post('/teams', [TeamController::class, 'store'])->name('teams.store');
    Route::get('/teams/{team}', [TeamController::class, 'show'])
        ->whereNumber('team')
        ->name('teams.show');
    Route::put('/teams/{team}', [TeamController::class, 'update'])
        ->whereNumber('team')
        ->name('teams.update');
    Route::delete('/teams/{team}', [TeamController::class, 'destroy'])
        ->whereNumber('team')
        ->name('teams.destroy');

    Route::post('/teams/{team}/members', [TeamController::class, 'addMember'])
        ->whereNumber('team')
        ->name('teams.members.store');
    Route::patch('/teams/{team}/members/{user}', [TeamController::class, 'updateMember'])
        ->whereNumber('team')
        ->whereNumber('user')
        ->name('teams.members.update');
    Route::delete('/teams/{team}/members/{user}', [TeamController::class, 'removeMember'])
        ->whereNumber('team')
        ->whereNumber('user')
        ->name('teams.members.destroy');
});
