<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Meetings\CreateMeetingRequest;
use App\Http\Requests\Meetings\GetMeetingsRequest;
use App\Http\Requests\Meetings\ManageParticipantsRequest;
use App\Http\Requests\Meetings\RespondMeetingRequest;
use App\Http\Requests\Meetings\UpdateMeetingRequest;
use App\Http\Resources\MeetingResource;
use App\Models\User;
use App\Services\Contracts\MeetingServiceInterface;
use App\Support\ApiResponse;
use App\Support\CurrentWorkspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class MeetingController extends Controller
{
    public function __construct(
        private readonly MeetingServiceInterface $meetingService,
        private readonly CurrentWorkspace $currentWorkspace,
    ) {}

    public function index(GetMeetingsRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $workspaceId = $this->currentWorkspace->id();

        $meetings = $this->meetingService->list($user, $workspaceId, $request->toFilters());

        return ApiResponse::ok(MeetingResource::collection($meetings));
    }

    public function store(CreateMeetingRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $workspaceId = $this->currentWorkspace->id();

        $meeting = $this->meetingService->create($user, $workspaceId, $request->toDto());

        return ApiResponse::created(new MeetingResource($meeting));
    }

    public function show(Request $request, int $meeting): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $workspaceId = $this->currentWorkspace->id();

        $found = $this->meetingService->find($user, $workspaceId, $meeting);

        return ApiResponse::ok(new MeetingResource($found));
    }

    public function update(UpdateMeetingRequest $request, int $meeting): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $workspaceId = $this->currentWorkspace->id();

        $updated = $this->meetingService->update($user, $workspaceId, $meeting, $request->toDto());

        return ApiResponse::ok(new MeetingResource($updated));
    }

    public function destroy(Request $request, int $meeting): Response
    {
        /** @var User $user */
        $user = $request->user();
        $workspaceId = $this->currentWorkspace->id();

        $this->meetingService->delete($user, $workspaceId, $meeting);

        return ApiResponse::noContent();
    }

    public function cancel(Request $request, int $meeting): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $workspaceId = $this->currentWorkspace->id();

        $cancelled = $this->meetingService->cancel($user, $workspaceId, $meeting);

        return ApiResponse::ok(new MeetingResource($cancelled));
    }

    public function respond(RespondMeetingRequest $request, int $meeting): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $workspaceId = $this->currentWorkspace->id();

        $this->meetingService->respond($user, $workspaceId, $meeting, $request->getResponseEnum());

        return ApiResponse::ok(['message' => 'Response recorded successfully.']);
    }

    public function addParticipants(ManageParticipantsRequest $request, int $meeting): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $workspaceId = $this->currentWorkspace->id();

        $this->meetingService->addParticipants($user, $workspaceId, $meeting, $request->participantIds());

        return ApiResponse::ok(['message' => 'Participants added successfully.']);
    }

    public function removeParticipant(Request $request, int $meeting, int $user): Response
    {
        /** @var User $currentUser */
        $currentUser = $request->user();
        $workspaceId = $this->currentWorkspace->id();

        $this->meetingService->removeParticipant($currentUser, $workspaceId, $meeting, $user);

        return ApiResponse::noContent();
    }

    public function join(Request $request, int $meeting): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $workspaceId = $this->currentWorkspace->id();

        $joinUrl = $this->meetingService->getJoinUrl($user, $workspaceId, $meeting);

        return ApiResponse::ok(['join_url' => $joinUrl]);
    }
}
