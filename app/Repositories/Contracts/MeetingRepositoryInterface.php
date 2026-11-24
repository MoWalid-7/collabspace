<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Data\Meetings\MeetingFilters;
use App\Enums\MeetingParticipantResponse;
use App\Models\Meeting;
use App\Models\MeetingNote;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

interface MeetingRepositoryInterface
{
    /**
     * @return Collection<int, Meeting>
     */
    public function listFiltered(int $workspaceId, int $userId, bool $isAdminOrManager, MeetingFilters $filters): Collection;

    public function findInWorkspaceOrFail(int $workspaceId, int $meetingId): Meeting;

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<int, int>  $participantIds
     */
    public function createMeeting(int $workspaceId, int $createdBy, array $attributes, array $participantIds = []): Meeting;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateMeeting(Meeting $meeting, array $attributes): Meeting;

    public function deleteMeeting(Meeting $meeting): void;

    public function cancelMeeting(Meeting $meeting): Meeting;

    public function setParticipantResponse(Meeting $meeting, int $userId, MeetingParticipantResponse $response): void;

    /**
     * @param  array<int, int>  $userIds
     */
    public function addParticipants(Meeting $meeting, array $userIds): void;

    public function removeParticipant(Meeting $meeting, int $userId): void;

    /**
     * @return Collection<int, MeetingNote>
     */
    public function getNotes(Meeting $meeting): Collection;

    public function findNoteOrFail(Meeting $meeting, int $noteId): MeetingNote;

    public function createNote(Meeting $meeting, int $userId, string $body): MeetingNote;

    public function updateNote(MeetingNote $note, string $body): MeetingNote;

    public function deleteNote(MeetingNote $note): void;

    /**
     * @return Collection<int, Meeting>
     */
    public function getUpcomingMeetings(int $workspaceId, int $userId, bool $isAdminOrManager, int $limit = 5): Collection;

    /**
     * @return Collection<int, Meeting>
     */
    public function getMeetingsNeedingReminder(CarbonImmutable $now): Collection;

    public function markReminderSent(Meeting $meeting, CarbonImmutable $sentAt): void;
}
