<?php

use App\Http\Controllers\Api\V1\TimeTrackingController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'workspace.current'])->group(function (): void {
    Route::post('/tasks/{task}/time/start', [TimeTrackingController::class, 'start'])->name('tasks.time.start');
    Route::post('/time/stop', [TimeTrackingController::class, 'stop'])->name('time.stop');
    Route::get('/time/current', [TimeTrackingController::class, 'current'])->name('time.current');

    Route::get('/time-entries', [TimeTrackingController::class, 'index'])->name('time-entries.index');
    Route::post('/time-entries', [TimeTrackingController::class, 'store'])->name('time-entries.store');
    Route::put('/time-entries/{id}', [TimeTrackingController::class, 'update'])->name('time-entries.update');
    Route::delete('/time-entries/{id}', [TimeTrackingController::class, 'destroy'])->name('time-entries.destroy');
});
