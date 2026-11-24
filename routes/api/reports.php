<?php

use App\Http\Controllers\Api\V1\ReportsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'workspace.current'])->group(function (): void {
    Route::get('/reports/overview', [ReportsController::class, 'overview'])->name('reports.overview');
    Route::get('/reports/task-completion-vs-deadline', [ReportsController::class, 'taskCompletionVsDeadline'])
        ->name('reports.task-completion-vs-deadline');
    Route::get('/reports/projects-progress', [ReportsController::class, 'projectsProgress'])
        ->name('reports.projects-progress');
    Route::get('/reports/worker-activity', [ReportsController::class, 'workerActivity'])
        ->name('reports.worker-activity');

    Route::post('/reports', [ReportsController::class, 'store'])->name('reports.store');
    Route::get('/reports', [ReportsController::class, 'index'])->name('reports.index');
    Route::get('/reports/{id}', [ReportsController::class, 'show'])->name('reports.show');
    Route::get('/reports/{id}/download', [ReportsController::class, 'download'])->name('reports.download');
});
