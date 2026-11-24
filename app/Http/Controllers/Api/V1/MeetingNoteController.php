<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Meetings\MeetingNoteRequest;
use App\Http\Resources\MeetingNoteResource;
use App\Models\User;
use App\Services\Contracts\MeetingServiceInterface;
use App\Support\ApiResponse;
use App\Support\CurrentWorkspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class MeetingNoteController extends Controller
{
    public function __construct(
        private readonly MeetingServiceInterface $meetingService,
        private readonly CurrentWorkspace $currentWorkspace,
    ) {}

    public function index(Request $request, int $meeting): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $workspaceId = $this->currentWorkspace->id();

        $notes = $this->meetingService->getNotes($user, $workspaceId, $meeting);

        return ApiResponse::ok(MeetingNoteResource::collection($notes));
    }

    public function store(MeetingNoteRequest $request, int $meeting): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $workspaceId = $this->currentWorkspace->id();

        $note = $this->meetingService->createNote($user, $workspaceId, $meeting, $request->string('body')->toString());

        return ApiResponse::created(new MeetingNoteResource($note));
    }

    public function update(MeetingNoteRequest $request, int $meeting, int $note): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $workspaceId = $this->currentWorkspace->id();

        $updated = $this->meetingService->updateNote($user, $workspaceId, $meeting, $note, $request->string('body')->toString());

        return ApiResponse::ok(new MeetingNoteResource($updated));
    }

    public function destroy(Request $request, int $meeting, int $note): Response
    {
        /** @var User $user */
        $user = $request->user();
        $workspaceId = $this->currentWorkspace->id();

        $this->meetingService->deleteNote($user, $workspaceId, $meeting, $note);

        return ApiResponse::noContent();
    }
}
