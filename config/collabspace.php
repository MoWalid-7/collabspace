<?php

use App\Enums\NotificationType;

return [
    'files' => [
        'disk' => 'private',
        'max_upload_size_kb' => 20 * 1024,
        'mime_types' => [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'application/pdf',
            'text/plain',
            'text/csv',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/zip',
            'application/x-7z-compressed',
            'application/x-rar',
            'application/vnd.rar',
        ],
        'extensions' => [
            'jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'txt', 'csv', 'doc', 'docx',
            'xls', 'xlsx', 'zip', '7z', 'rar',
        ],
    ],
    'notification_types' => array_column(NotificationType::cases(), 'value'),
];
