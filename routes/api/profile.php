<?php

use App\Http\Controllers\Api\V1\ActivityController;
use App\Http\Controllers\Api\V1\ProfileController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::get('/profile/stats', [ProfileController::class, 'stats'])
        ->middleware('workspace.current')
        ->name('profile.stats');
    Route::get('/profile/activities', [ActivityController::class, 'profile'])
        ->name('profile.activities.index');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])
        ->name('profile.password.update');
    Route::post('/profile/avatar', [ProfileController::class, 'uploadAvatar'])
        ->name('profile.avatar.store');
});
