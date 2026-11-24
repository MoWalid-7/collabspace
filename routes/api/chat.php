<?php

use App\Http\Controllers\Api\V1\ChannelController;
use App\Http\Controllers\Api\V1\MessageController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'workspace.current'])->group(function (): void {
    // Channels
    Route::get('/channels', [ChannelController::class, 'index'])->name('channels.index');
    Route::post('/channels', [ChannelController::class, 'store'])->name('channels.store');
    Route::post('/channels/direct', [ChannelController::class, 'direct'])->name('channels.direct');
    Route::post('/projects/{project}/channel', [ChannelController::class, 'projectChannel'])
        ->whereNumber('project')
        ->name('projects.channel');
    Route::post('/teams/{team}/channel', [ChannelController::class, 'teamChannel'])
        ->whereNumber('team')
        ->name('teams.channel');

    Route::get('/channels/{channel}', [ChannelController::class, 'show'])
        ->whereNumber('channel')
        ->name('channels.show');
    Route::post('/channels/{channel}/join', [ChannelController::class, 'join'])
        ->whereNumber('channel')
        ->name('channels.join');
    Route::post('/channels/{channel}/members', [ChannelController::class, 'addMembers'])
        ->whereNumber('channel')
        ->name('channels.members.store');
    Route::delete('/channels/{channel}/members/{user}', [ChannelController::class, 'removeMember'])
        ->whereNumber('channel')
        ->whereNumber('user')
        ->name('channels.members.destroy');

    Route::get('/channels/{channel}/messages', [ChannelController::class, 'messages'])
        ->whereNumber('channel')
        ->name('channels.messages.index');
    Route::post('/channels/{channel}/messages', [ChannelController::class, 'sendMessage'])
        ->whereNumber('channel')
        ->name('channels.messages.store');
    Route::post('/channels/{channel}/read', [ChannelController::class, 'read'])
        ->whereNumber('channel')
        ->name('channels.read');
    Route::get('/channels/{channel}/search', [ChannelController::class, 'search'])
        ->whereNumber('channel')
        ->name('channels.search');

    // Messages
    Route::get('/messages/{message}/replies', [MessageController::class, 'replies'])
        ->whereNumber('message')
        ->name('messages.replies.index');
    Route::put('/messages/{message}', [MessageController::class, 'update'])
        ->whereNumber('message')
        ->name('messages.update');
    Route::delete('/messages/{message}', [MessageController::class, 'destroy'])
        ->whereNumber('message')
        ->name('messages.destroy');
});
