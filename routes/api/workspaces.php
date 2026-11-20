<?php

use App\Http\Controllers\Api\V1\WorkspaceController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/workspaces', [WorkspaceController::class, 'index'])->name('workspaces.index');
    Route::post('/workspaces', [WorkspaceController::class, 'store'])->name('workspaces.store');

    Route::middleware('workspace.current')->group(function (): void {
        Route::get('/workspaces/{workspace}', [WorkspaceController::class, 'show'])
            ->whereNumber('workspace')
            ->name('workspaces.show');
        Route::put('/workspaces/{workspace}', [WorkspaceController::class, 'update'])
            ->whereNumber('workspace')
            ->name('workspaces.update');
        Route::post('/workspaces/{workspace}/members', [WorkspaceController::class, 'addMember'])
            ->whereNumber('workspace')
            ->name('workspaces.members.store');
        Route::patch('/workspaces/{workspace}/members/{user}', [WorkspaceController::class, 'updateMemberRole'])
            ->whereNumber('workspace')
            ->whereNumber('user')
            ->name('workspaces.members.update');
        Route::delete('/workspaces/{workspace}/members/{user}', [WorkspaceController::class, 'removeMember'])
            ->whereNumber('workspace')
            ->whereNumber('user')
            ->name('workspaces.members.destroy');
    });
});
