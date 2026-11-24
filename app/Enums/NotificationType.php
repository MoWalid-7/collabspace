<?php

namespace App\Enums;

enum NotificationType: string
{
    case TaskAssigned = 'task_assigned';
    case TaskInReview = 'task_in_review';
    case TaskCompleted = 'task_completed';
    case CommentAdded = 'comment_added';
    case FileUploaded = 'file_uploaded';
    case ProjectMemberAdded = 'project_member_added';
    case MeetingInvited = 'meeting_invited';
    case MeetingReminder = 'meeting_reminder';
    case DailyStatusReminder = 'daily_status_reminder';
}
