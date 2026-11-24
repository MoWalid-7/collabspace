<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\Data\Meetings\CreateMeetingData;
use App\Data\Meetings\MeetingFilters;
use App\Data\Meetings\UpdateMeetingData;
use App\Enums\MeetingParticipantResponse;
use App\Models\Meeting;
use App\Models\MeetingNote;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

interface MeetingServiceInterface
{
    /**
     * @return Collection<int, Meeting>
     */
    public function list(User $user, int $workspaceId, MeetingFilters $filters): Collection;

    public function find(User $user, int $workspaceId, int $meetingId): Meeting;

    public function create(User $user, int $workspaceId, CreateMeetingData $data): Meeting;

    public function update(User $user, int $workspaceId, int $meetingId, UpdateMeetingData $data): Meeting;

    public function delete(User $user, int $workspaceId, int $meetingId): void;

    public function cancel(User $user, int $workspaceId, int $meetingId): Meeting;

    public function respond(User $user, int $workspaceId, int $meetingId, MeetingParticipantResponse $response): void;

    /**
     * @param  array<int, int>  $participantIds
     */
    public function addParticipants(User $user, int $workspaceId, int $meetingId, array $participantIds): void;

    public function removeParticipant(User $user, int $workspaceId, int $meetingId, int $participantUserId): void;

    public function getJoinUrl(User $user, int $workspaceId, int $meetingId): string;

    /**
     * @return Collection<int, MeetingNote>
     */
    public function getNotes(User $user, int $workspaceId, int $meetingId): Collection;

    public function createNote(User $user, int $workspaceId, int $meetingId, string $body): MeetingNote;

    public function updateNote(User $user, int $workspaceId, int $meetingId, int $noteId, string $body): MeetingNote;

    public function deleteNote(User $user, int $workspaceId, int $meetingId, int $noteId): void;

    /**
     * @return Collection<int, Meeting>
     */
    public function getUpcomingMeetings(User $user, int $workspaceId, int $limit = 5): Collection;
}
