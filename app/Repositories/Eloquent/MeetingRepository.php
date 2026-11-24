<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Data\Meetings\MeetingFilters;
use App\Enums\MeetingParticipantResponse;
use App\Enums\MeetingStatus;
use App\Models\Meeting;
use App\Models\MeetingNote;
use App\Repositories\Contracts\MeetingRepositoryInterface;
use App\Repositories\Contracts\ProjectRepositoryInterface;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

final class MeetingRepository implements MeetingRepositoryInterface
{
    public function __construct(
        private readonly ProjectRepositoryInterface $projectRepository,
    ) {}

    public function listFiltered(int $workspaceId, int $userId, bool $isAdminOrManager, MeetingFilters $filters): Collection
    {
        $query = Meeting::query()
            ->where('workspace_id', $workspaceId)
            ->with(['participants', 'creator', 'project']);

        // Visibility
        if (! $isAdminOrManager) {
            $query->where(function (Builder $q) use ($workspaceId, $userId) {
                // Created by user
                $q->where('created_by', $userId)
                    // Or participant
                    ->orWhereHas('participants', function (Builder $partQuery) use ($userId) {
                        $partQuery->where('users.id', $userId);
                    })
                    // Or project is visible
                    ->orWhere(function (Builder $projMeetingQuery) use ($workspaceId, $userId) {
                        $projMeetingQuery->whereNotNull('project_id')
                            ->whereHas('project', function (Builder $projQuery) use ($workspaceId, $userId) {
                                $this->projectRepository->applyVisibility($projQuery, $workspaceId, $userId);
                            });
                    });
            });
        }

        if ($filters->from !== null) {
            $query->where('starts_at', '>=', $filters->from);
        }

        if ($filters->to !== null) {
            $query->where('starts_at', '<=', $filters->to);
        }

        if ($filters->projectId !== null) {
            $query->where('project_id', $filters->projectId);
        }

        if ($filters->mine) {
            $query->where(function (Builder $q) use ($userId) {
                $q->where('created_by', $userId)
                    ->orWhereHas('participants', function (Builder $partQuery) use ($userId) {
                        $partQuery->where('users.id', $userId);
                    });
            });
        }

        /** @var Collection<int, Meeting> $results */
        $results = $query->orderBy('starts_at', 'asc')->get();

        return $results;
    }

    public function findInWorkspaceOrFail(int $workspaceId, int $meetingId): Meeting
    {
        return Meeting::query()
            ->where('workspace_id', $workspaceId)
            ->with(['participants', 'creator', 'project'])
            ->findOrFail($meetingId);
    }

    public function createMeeting(int $workspaceId, int $createdBy, array $attributes, array $participantIds = []): Meeting
    {
        $attributes['workspace_id'] = $workspaceId;
        $attributes['created_by'] = $createdBy;
        $attributes['status'] = MeetingStatus::Scheduled;

        /** @var Meeting $meeting */
        $meeting = Meeting::create($attributes);

        // Ensure creator is also included or participant list is attached
        $participantData = [];
        $uniqueParticipantIds = array_unique(array_merge($participantIds, [$createdBy]));
        foreach ($uniqueParticipantIds as $pId) {
            $participantData[$pId] = [
                'response' => $pId === $createdBy ? MeetingParticipantResponse::Accepted->value : MeetingParticipantResponse::Pending->value,
            ];
        }

        $meeting->participants()->sync($participantData);
        $meeting->load(['participants', 'creator', 'project']);

        return $meeting;
    }

    public function updateMeeting(Meeting $meeting, array $attributes): Meeting
    {
        $meeting->update($attributes);
        $meeting->load(['participants', 'creator', 'project']);

        return $meeting;
    }

    public function deleteMeeting(Meeting $meeting): void
    {
        $meeting->delete();
    }

    public function cancelMeeting(Meeting $meeting): Meeting
    {
        $meeting->update(['status' => MeetingStatus::Cancelled]);
        $meeting->load(['participants', 'creator', 'project']);

        return $meeting;
    }

    public function setParticipantResponse(Meeting $meeting, int $userId, MeetingParticipantResponse $response): void
    {
        $meeting->participants()->updateExistingPivot($userId, [
            'response' => $response->value,
        ]);
    }

    public function addParticipants(Meeting $meeting, array $userIds): void
    {
        $attachData = [];
        foreach (array_unique($userIds) as $userId) {
            $attachData[$userId] = [
                'response' => MeetingParticipantResponse::Pending->value,
            ];
        }
        $meeting->participants()->syncWithoutDetaching($attachData);
    }

    public function removeParticipant(Meeting $meeting, int $userId): void
    {
        $meeting->participants()->detach($userId);
    }

    public function getNotes(Meeting $meeting): Collection
    {
        /** @var Collection<int, MeetingNote> $notes */
        $notes = $meeting->notes()->with('user')->orderBy('created_at', 'asc')->get();

        return $notes;
    }

    public function findNoteOrFail(Meeting $meeting, int $noteId): MeetingNote
    {
        /** @var MeetingNote $note */
        $note = $meeting->notes()->with('user')->findOrFail($noteId);

        return $note;
    }

    public function createNote(Meeting $meeting, int $userId, string $body): MeetingNote
    {
        /** @var MeetingNote $note */
        $note = $meeting->notes()->create([
            'user_id' => $userId,
            'body' => $body,
        ]);

        $note->load('user');

        return $note;
    }

    public function updateNote(MeetingNote $note, string $body): MeetingNote
    {
        $note->update(['body' => $body]);
        $note->load('user');

        return $note;
    }

    public function deleteNote(MeetingNote $note): void
    {
        $note->delete();
    }

    public function getUpcomingMeetings(int $workspaceId, int $userId, bool $isAdminOrManager, int $limit = 5): Collection
    {
        $now = CarbonImmutable::now();

        $query = Meeting::query()
            ->where('workspace_id', $workspaceId)
            ->where('status', MeetingStatus::Scheduled)
            ->where('starts_at', '>=', $now)
            ->with(['participants', 'creator', 'project']);

        if (! $isAdminOrManager) {
            $query->where(function (Builder $q) use ($workspaceId, $userId) {
                $q->where('created_by', $userId)
                    ->orWhereHas('participants', function (Builder $partQuery) use ($userId) {
                        $partQuery->where('users.id', $userId);
                    })
                    ->orWhere(function (Builder $projMeetingQuery) use ($workspaceId, $userId) {
                        $projMeetingQuery->whereNotNull('project_id')
                            ->whereHas('project', function (Builder $projQuery) use ($workspaceId, $userId) {
                                $this->projectRepository->applyVisibility($projQuery, $workspaceId, $userId);
                            });
                    });
            });
        }

        /** @var Collection<int, Meeting> $results */
        $results = $query->orderBy('starts_at', 'asc')->limit($limit)->get();

        return $results;
    }

    public function getMeetingsNeedingReminder(CarbonImmutable $now): Collection
    {
        $windowEnd = $now->addMinutes(15);

        /** @var Collection<int, Meeting> $results */
        $results = Meeting::query()
            ->withoutGlobalScopes()
            ->where('status', MeetingStatus::Scheduled)
            ->whereNull('reminder_sent_at')
            ->where('starts_at', '>=', $now->subMinute())
            ->where('starts_at', '<=', $windowEnd)
            ->with(['participants', 'workspace'])
            ->get();

        return $results;
    }

    public function markReminderSent(Meeting $meeting, CarbonImmutable $sentAt): void
    {
        $meeting->update(['reminder_sent_at' => $sentAt]);
    }
}
