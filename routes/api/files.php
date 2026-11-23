<?php

use App\Http\Controllers\Api\V1\Files\FileController;
use App\Http\Controllers\Api\V1\Files\ProjectFileController;
use App\Http\Controllers\Api\V1\Files\TaskFileController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'workspace.current'])->group(function (): void {
    Route::get('/projects/{project}/files', [ProjectFileController::class, 'index'])
        ->whereNumber('project')
        ->name('projects.files.index');
    Route::post('/projects/{project}/files', [ProjectFileController::class, 'store'])
        ->whereNumber('project')
        ->name('projects.files.store');
    Route::get('/tasks/{task}/files', [TaskFileController::class, 'index'])
        ->whereNumber('task')
        ->name('tasks.files.index');
    Route::post('/tasks/{task}/files', [TaskFileController::class, 'store'])
        ->whereNumber('task')
        ->name('tasks.files.store');

    Route::get('/files/{file}', [FileController::class, 'show'])
        ->whereNumber('file')
        ->name('files.show');
    Route::patch('/files/{file}', [FileController::class, 'update'])
        ->whereNumber('file')
        ->name('files.update');
    Route::delete('/files/{file}', [FileController::class, 'destroy'])
        ->whereNumber('file')
        ->name('files.destroy');
    Route::get('/files/{file}/download', [FileController::class, 'download'])
        ->whereNumber('file')
        ->name('files.download');
    Route::get('/files/{file}/versions', [FileController::class, 'versions'])
        ->whereNumber('file')
        ->name('files.versions.index');
    Route::post('/files/{file}/versions', [FileController::class, 'storeVersion'])
        ->whereNumber('file')
        ->name('files.versions.store');
    Route::get('/files/{file}/versions/{version}/download', [FileController::class, 'downloadVersion'])
        ->whereNumber('file')
        ->whereNumber('version')
        ->name('files.versions.download');
});
