<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\MeetingCommentController;
use App\Http\Controllers\Api\V1\MeetingController;
use App\Http\Controllers\Api\V1\MeetingNoteController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'workspace.current'])->group(function (): void {
    Route::get('/meetings', [MeetingController::class, 'index'])->name('meetings.index');
    Route::post('/meetings', [MeetingController::class, 'store'])->name('meetings.store');
    Route::get('/meetings/{meeting}', [MeetingController::class, 'show'])->name('meetings.show');
    Route::put('/meetings/{meeting}', [MeetingController::class, 'update'])->name('meetings.update');
    Route::delete('/meetings/{meeting}', [MeetingController::class, 'destroy'])->name('meetings.destroy');
    Route::post('/meetings/{meeting}/cancel', [MeetingController::class, 'cancel'])->name('meetings.cancel');
    Route::post('/meetings/{meeting}/respond', [MeetingController::class, 'respond'])->name('meetings.respond');
    Route::post('/meetings/{meeting}/participants', [MeetingController::class, 'addParticipants'])->name('meetings.participants.add');
    Route::delete('/meetings/{meeting}/participants/{user}', [MeetingController::class, 'removeParticipant'])->name('meetings.participants.remove');
    Route::post('/meetings/{meeting}/join', [MeetingController::class, 'join'])->name('meetings.join');

    // Notes
    Route::get('/meetings/{meeting}/notes', [MeetingNoteController::class, 'index'])->name('meetings.notes.index');
    Route::post('/meetings/{meeting}/notes', [MeetingNoteController::class, 'store'])->name('meetings.notes.store');
    Route::put('/meetings/{meeting}/notes/{note}', [MeetingNoteController::class, 'update'])->name('meetings.notes.update');
    Route::delete('/meetings/{meeting}/notes/{note}', [MeetingNoteController::class, 'destroy'])->name('meetings.notes.destroy');

    // Comments
    Route::get('/meetings/{meeting}/comments', [MeetingCommentController::class, 'index'])->name('meetings.comments.index');
    Route::post('/meetings/{meeting}/comments', [MeetingCommentController::class, 'store'])->name('meetings.comments.store');
});
