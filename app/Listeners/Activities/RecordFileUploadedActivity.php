<?php

namespace App\Listeners\Activities;

use App\Enums\ActivityEvent;
use App\Events\FileUploaded;
use App\Models\File;
use App\Services\ActivityLogger;
use Illuminate\Contracts\Queue\ShouldQueue;

class RecordFileUploadedActivity implements ShouldQueue
{
    public function __construct(private ActivityLogger $logger) {}

    public function handle(FileUploaded $event): void
    {
        $this->logger->record(
            ActivityEvent::FileUploaded,
            (int) $event->file->workspace_id,
            (int) $event->file->project_id,
            $event->actorId,
            File::class,
            (int) $event->file->getKey(),
            ['file_name' => $event->file->name],
        );
    }
}
