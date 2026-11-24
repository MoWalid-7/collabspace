<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\DailyStatusController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'workspace.current'])->group(function (): void {
    Route::get('/daily-statuses', [DailyStatusController::class, 'index'])->name('daily-statuses.index');
    Route::post('/daily-statuses', [DailyStatusController::class, 'store'])->name('daily-statuses.store');
    Route::get('/projects/{project}/team-status', [DailyStatusController::class, 'teamStatus'])->name('projects.team-status');
    Route::get('/projects/{project}/blockers', [DailyStatusController::class, 'blockers'])->name('projects.blockers');
});
