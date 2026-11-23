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
}
