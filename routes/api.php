<?php

use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    require __DIR__.'/api/auth.php';
    require __DIR__.'/api/workspaces.php';
    require __DIR__.'/api/profile.php';
    require __DIR__.'/api/teams.php';
    require __DIR__.'/api/projects.php';
    require __DIR__.'/api/tasks.php';
    require __DIR__.'/api/files.php';
    require __DIR__.'/api/notifications.php';
});
