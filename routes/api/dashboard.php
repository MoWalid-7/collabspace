<?php

use App\Http\Controllers\Api\V1\DashboardController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'workspace.current'])
    ->prefix('dashboard')
    ->name('dashboard.')
    ->group(function (): void {
        Route::get('/stats', [DashboardController::class, 'stats'])->name('stats');
        Route::get('/project-overview', [DashboardController::class, 'projectOverview'])->name('project-overview');
        Route::get('/deadlines', [DashboardController::class, 'deadlines'])->name('deadlines');
        Route::get('/teams', [DashboardController::class, 'teams'])->name('teams');
        Route::get('/recent-files', [DashboardController::class, 'recentFiles'])->name('recent-files');
    });
