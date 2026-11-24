<?php

namespace App\Services;

use App\Enums\ActivityEvent;
use App\Enums\TaskStatus;

class ActivityDescriber
{
    /**
     * @param  array<string, mixed>  $properties
     */
    public static function describe(ActivityEvent $event, array $properties): string
    {
        $actor = (string) ($properties['actor_name'] ?? 'A member');
        $task = "'".(string) ($properties['task_title'] ?? 'a task')."'";
        $project = "'".(string) ($properties['project_name'] ?? 'a project')."'";
        $file = "'".(string) ($properties['file_name'] ?? 'a file')."'";

        return match ($event) {
            ActivityEvent::TaskCreated => "{$actor} created {$task}",
            ActivityEvent::TaskAssigned => "{$actor} assigned {$task} to ".(string) ($properties['assignee_name'] ?? 'a member'),
            ActivityEvent::TaskStatusChanged => self::statusMessage(
                $actor,
                $task,
                TaskStatus::tryFrom((string) ($properties['status'] ?? '')),
            ),
            ActivityEvent::CommentAdded => "{$actor} commented on {$task}",
            ActivityEvent::FileUploaded => "{$actor} uploaded {$file}",
            ActivityEvent::FileVersionAdded => "{$actor} added a new version of {$file}",
            ActivityEvent::ProjectMemberAdded => "{$actor} added ".(string) ($properties['member_name'] ?? 'a member')." to {$project}",
            ActivityEvent::ProjectCreated => "{$actor} created project {$project}",
            ActivityEvent::MeetingCreated => "{$actor} scheduled a meeting '".(string) ($properties['meeting_title'] ?? 'Meeting')."'",
            ActivityEvent::MeetingUpdated => "{$actor} updated meeting '".(string) ($properties['meeting_title'] ?? 'Meeting')."'",
            ActivityEvent::MeetingCancelled => "{$actor} cancelled meeting '".(string) ($properties['meeting_title'] ?? 'Meeting')."'",
        };
    }

    private static function statusMessage(string $actor, string $task, ?TaskStatus $status): string
    {
        if ($status === null) {
            return "{$actor} changed the status of {$task}";
        }

        return "{$actor} moved {$task} to ".ucwords(str_replace('_', ' ', $status->value));
    }
}
