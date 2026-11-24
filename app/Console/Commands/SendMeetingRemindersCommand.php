<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\NotificationType;
use App\Repositories\Contracts\MeetingRepositoryInterface;
use App\Services\Contracts\NotificationServiceInterface;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class SendMeetingRemindersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'meetings:send-reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send reminders to participants for upcoming scheduled meetings within 15 minutes';

    public function handle(
        MeetingRepositoryInterface $meetingRepository,
        NotificationServiceInterface $notificationService
    ): int {
        $now = CarbonImmutable::now();
        $meetings = $meetingRepository->getMeetingsNeedingReminder($now);

        foreach ($meetings as $meeting) {
            $participantIds = $meeting->participants->pluck('id')->all();

            if (! empty($participantIds)) {
                $notificationService->notify(
                    type: NotificationType::MeetingReminder,
                    title: 'Upcoming Meeting Reminder',
                    body: "Meeting '{$meeting->title}' starts at {$meeting->starts_at->toTimeString()}.",
                    workspaceId: (int) $meeting->workspace_id,
                    projectId: $meeting->project_id,
                    taskId: null,
                    fileId: null,
                    actorId: (int) $meeting->created_by,
                    recipientIds: array_values($participantIds),
                );
            }

            $meetingRepository->markReminderSent($meeting, $now);
        }

        $this->info("Processed {$meetings->count()} meeting reminders.");

        return self::SUCCESS;
    }
}
