<?php

namespace App\Enums;

enum ActivityEvent: string
{
    case TaskCreated = 'task_created';
    case TaskAssigned = 'task_assigned';
    case TaskStatusChanged = 'task_status_changed';
    case CommentAdded = 'comment_added';
    case FileUploaded = 'file_uploaded';
    case FileVersionAdded = 'file_version_added';
    case ProjectMemberAdded = 'project_member_added';
    case ProjectCreated = 'project_created';
    case MeetingCreated = 'meeting_created';
    case MeetingUpdated = 'meeting_updated';
    case MeetingCancelled = 'meeting_cancelled';
}
