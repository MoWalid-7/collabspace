<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\Meetings\CreateMeetingData;
use App\Data\Meetings\MeetingFilters;
use App\Data\Meetings\UpdateMeetingData;
use App\Enums\MeetingParticipantResponse;
use App\Enums\NotificationType;
use App\Events\MeetingCancelled;
use App\Events\MeetingCreated;
use App\Events\MeetingUpdated;
use App\Models\Meeting;
use App\Models\MeetingNote;
use App\Models\User;
use App\Repositories\Contracts\MeetingRepositoryInterface;
use App\Repositories\Contracts\ProjectRepositoryInterface;
use App\Repositories\Contracts\WorkspaceRepositoryInterface;
use App\Services\Contracts\MeetingServiceInterface;
use App\Services\Contracts\NotificationServiceInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException as LaravelValidationException;

final class MeetingService implements MeetingServiceInterface
{
    public function __construct(
        private readonly MeetingRepositoryInterface $meetings,
        private readonly WorkspaceRepositoryInterface $workspaces,
        private readonly ProjectRepositoryInterface $projects,
        private readonly NotificationServiceInterface $notifications,
    ) {}

    public function list(User $user, int $workspaceId, MeetingFilters $filters): Collection
    {
        $role = $this->workspaces->roleForWorkspaceId($workspaceId, $user->id);
        $isAdminOrManager = in_array($role, ['admin', 'manager'], true);

        return $this->meetings->listFiltered($workspaceId, $user->id, $isAdminOrManager, $filters);
    }

    public function find(User $user, int $workspaceId, int $meetingId): Meeting
    {
        $meeting = $this->meetings->findInWorkspaceOrFail($workspaceId, $meetingId);
        $this->ensureCanView($user, $workspaceId, $meeting);

        return $meeting;
    }

    public function create(User $user, int $workspaceId, CreateMeetingData $data): Meeting
    {
        if ($data->endsAt <= $data->startsAt) {
            throw LaravelValidationException::withMessages([
                'ends_at' => ['The ends at must be a date after starts at.'],
            ]);
        }

        // Validate participants belong to workspace
        $workspaceMemberIds = $this->workspaces->memberUserIds($workspaceId);
        $invalidParticipants = array_diff($data->participantIds, $workspaceMemberIds);
        if (! empty($invalidParticipants)) {
            throw LaravelValidationException::withMessages([
                'participant_ids' => ['All participants must be members of the workspace.'],
            ]);
        }

        // If project_id is given, validate project belongs to workspace and is visible/accessible
        if ($data->projectId !== null) {
            $project = $this->projects->findInWorkspaceOrFail($workspaceId, $data->projectId);
            // participants must be project members
            $projectMemberIds = $project->allMembers()->pluck('id')->all();
            $nonProjectParticipants = array_diff($data->participantIds, $projectMemberIds);
            if (! empty($nonProjectParticipants)) {
                throw LaravelValidationException::withMessages([
                    'participant_ids' => ['All participants must be members of the project.'],
                ]);
            }
        }

        $attributes = [
            'title' => $data->title,
            'description' => $data->description,
            'project_id' => $data->projectId,
            'starts_at' => $data->startsAt,
            'ends_at' => $data->endsAt,
            'join_url' => $data->joinUrl,
        ];

        $meeting = $this->meetings->createMeeting($workspaceId, $user->id, $attributes, $data->participantIds);

        // Notify invited participants except creator
        $invitedParticipantIds = array_diff($data->participantIds, [$user->id]);
        if (! empty($invitedParticipantIds)) {
            $this->notifications->notify(
                type: NotificationType::MeetingInvited,
                title: 'Meeting Invitation',
                body: "You have been invited to meeting: {$meeting->title}",
                workspaceId: $workspaceId,
                projectId: $meeting->project_id,
                taskId: null,
                fileId: null,
                actorId: $user->id,
                recipientIds: array_values($invitedParticipantIds),
            );
        }

        event(new MeetingCreated($meeting, $user->id));

        return $meeting;
    }

    public function update(User $user, int $workspaceId, int $meetingId, UpdateMeetingData $data): Meeting
    {
        $meeting = $this->meetings->findInWorkspaceOrFail($workspaceId, $meetingId);
        $this->ensureCanManage($user, $workspaceId, $meeting);

        $startsAt = $data->startsAt ?? $meeting->starts_at;
        $endsAt = $data->endsAt ?? $meeting->ends_at;

        if ($endsAt <= $startsAt) {
            throw LaravelValidationException::withMessages([
                'ends_at' => ['The ends at must be a date after starts at.'],
            ]);
        }

        if ($data->projectId !== null) {
            $this->projects->findInWorkspaceOrFail($workspaceId, $data->projectId);
        }

        $attributes = array_filter([
            'title' => $data->title,
            'description' => $data->description,
            'project_id' => $data->projectId,
            'starts_at' => $data->startsAt,
            'ends_at' => $data->endsAt,
            'join_url' => $data->joinUrl,
        ], fn ($val) => $val !== null);

        $updated = $this->meetings->updateMeeting($meeting, $attributes);

        event(new MeetingUpdated($updated, $user->id));

        return $updated;
    }

    public function delete(User $user, int $workspaceId, int $meetingId): void
    {
        $meeting = $this->meetings->findInWorkspaceOrFail($workspaceId, $meetingId);
        $this->ensureCanManage($user, $workspaceId, $meeting);

        $this->meetings->deleteMeeting($meeting);
    }

    public function cancel(User $user, int $workspaceId, int $meetingId): Meeting
    {
        $meeting = $this->meetings->findInWorkspaceOrFail($workspaceId, $meetingId);
        $this->ensureCanManage($user, $workspaceId, $meeting);

        $cancelled = $this->meetings->cancelMeeting($meeting);

        event(new MeetingCancelled($cancelled, $user->id));

        return $cancelled;
    }

    public function respond(User $user, int $workspaceId, int $meetingId, MeetingParticipantResponse $response): void
    {
        $meeting = $this->meetings->findInWorkspaceOrFail($workspaceId, $meetingId);
        $isParticipant = $meeting->participants->contains('id', $user->id);

        if (! $isParticipant) {
            throw new AuthorizationException('Only participants can respond to a meeting.');
        }

        $this->meetings->setParticipantResponse($meeting, $user->id, $response);
    }

    public function addParticipants(User $user, int $workspaceId, int $meetingId, array $participantIds): void
    {
        $meeting = $this->meetings->findInWorkspaceOrFail($workspaceId, $meetingId);
        $this->ensureCanManage($user, $workspaceId, $meeting);

        $workspaceMemberIds = $this->workspaces->memberUserIds($workspaceId);
        $invalidParticipants = array_diff($participantIds, $workspaceMemberIds);
        if (! empty($invalidParticipants)) {
            throw LaravelValidationException::withMessages([
                'participant_ids' => ['All participants must be members of the workspace.'],
            ]);
        }

        if ($meeting->project_id !== null) {
            $project = $this->projects->findInWorkspaceOrFail($workspaceId, $meeting->project_id);
            $projectMemberIds = $project->allMembers()->pluck('id')->all();
            $nonProjectParticipants = array_diff($participantIds, $projectMemberIds);
            if (! empty($nonProjectParticipants)) {
                throw LaravelValidationException::withMessages([
                    'participant_ids' => ['All participants must be members of the project.'],
                ]);
            }
        }

        $this->meetings->addParticipants($meeting, $participantIds);

        // Notify added participants
        $this->notifications->notify(
            type: NotificationType::MeetingInvited,
            title: 'Meeting Invitation',
            body: "You have been invited to meeting: {$meeting->title}",
            workspaceId: $workspaceId,
            projectId: $meeting->project_id,
            taskId: null,
            fileId: null,
            actorId: $user->id,
            recipientIds: array_values($participantIds),
        );
    }

    public function removeParticipant(User $user, int $workspaceId, int $meetingId, int $participantUserId): void
    {
        $meeting = $this->meetings->findInWorkspaceOrFail($workspaceId, $meetingId);
        $this->ensureCanManage($user, $workspaceId, $meeting);

        $this->meetings->removeParticipant($meeting, $participantUserId);
    }

    public function getJoinUrl(User $user, int $workspaceId, int $meetingId): string
    {
        $meeting = $this->meetings->findInWorkspaceOrFail($workspaceId, $meetingId);
        $this->ensureCanView($user, $workspaceId, $meeting);

        $isParticipant = $meeting->participants->contains('id', $user->id);
        if (! $isParticipant) {
            throw new AuthorizationException('Only participants can join this meeting.');
        }

        if (empty($meeting->join_url)) {
            throw LaravelValidationException::withMessages([
                'join_url' => ['No join URL has been provided for this meeting.'],
            ]);
        }

        return $meeting->join_url;
    }

    public function getNotes(User $user, int $workspaceId, int $meetingId): Collection
    {
        $meeting = $this->meetings->findInWorkspaceOrFail($workspaceId, $meetingId);
        $this->ensureCanView($user, $workspaceId, $meeting);

        return $this->meetings->getNotes($meeting);
    }

    public function createNote(User $user, int $workspaceId, int $meetingId, string $body): MeetingNote
    {
        $meeting = $this->meetings->findInWorkspaceOrFail($workspaceId, $meetingId);
        $this->ensureCanView($user, $workspaceId, $meeting);

        return $this->meetings->createNote($meeting, $user->id, $body);
    }

    public function updateNote(User $user, int $workspaceId, int $meetingId, int $noteId, string $body): MeetingNote
    {
        $meeting = $this->meetings->findInWorkspaceOrFail($workspaceId, $meetingId);
        $note = $this->meetings->findNoteOrFail($meeting, $noteId);

        if ($note->user_id !== $user->id) {
            throw new AuthorizationException('You are not authorized to update this note.');
        }

        return $this->meetings->updateNote($note, $body);
    }

    public function deleteNote(User $user, int $workspaceId, int $meetingId, int $noteId): void
    {
        $meeting = $this->meetings->findInWorkspaceOrFail($workspaceId, $meetingId);
        $note = $this->meetings->findNoteOrFail($meeting, $noteId);

        if ($note->user_id !== $user->id) {
            throw new AuthorizationException('You are not authorized to delete this note.');
        }

        $this->meetings->deleteNote($note);
    }

    public function getUpcomingMeetings(User $user, int $workspaceId, int $limit = 5): Collection
    {
        $role = $this->workspaces->roleForWorkspaceId($workspaceId, $user->id);
        $isAdminOrManager = in_array($role, ['admin', 'manager'], true);

        return $this->meetings->getUpcomingMeetings($workspaceId, $user->id, $isAdminOrManager, $limit);
    }

    private function ensureCanManage(User $user, int $workspaceId, Meeting $meeting): void
    {
        $role = $this->workspaces->roleForWorkspaceId($workspaceId, $user->id);
        $isAdminOrManager = in_array($role, ['admin', 'manager'], true);

        if (! $isAdminOrManager && $meeting->created_by !== $user->id) {
            throw new AuthorizationException('You are not authorized to manage this meeting.');
        }
    }

    private function ensureCanView(User $user, int $workspaceId, Meeting $meeting): void
    {
        $role = $this->workspaces->roleForWorkspaceId($workspaceId, $user->id);
        if (in_array($role, ['admin', 'manager'], true)) {
            return;
        }

        if ($meeting->created_by === $user->id) {
            return;
        }

        if ($meeting->participants->contains('id', $user->id)) {
            return;
        }

        if ($meeting->project_id !== null) {
            $project = $this->projects->findInWorkspaceOrFail($workspaceId, $meeting->project_id);
            if ($project->allMembers()->contains('id', $user->id)) {
                return;
            }
        }

        throw new AuthorizationException('You do not have access to view this meeting.');
    }
}
