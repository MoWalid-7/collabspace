<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\NotificationType;
use App\Models\Workspace;
use App\Repositories\Contracts\DailyStatusRepositoryInterface;
use App\Services\Contracts\NotificationServiceInterface;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class SendDailyStatusRemindersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'daily-status:remind';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send daily status reminders to workspace members who have not submitted today';

    public function handle(
        DailyStatusRepositoryInterface $dailyStatusRepository,
        NotificationServiceInterface $notificationService
    ): int {
        $today = CarbonImmutable::today()->toDateString();
        $workspaces = Workspace::all();
        $totalSent = 0;

        foreach ($workspaces as $workspace) {
            $missingUserIds = $dailyStatusRepository->getUserIdsMissingDailyStatus((int) $workspace->id, $today);

            if (! empty($missingUserIds)) {
                $notificationService->notify(
                    type: NotificationType::DailyStatusReminder,
                    title: 'Daily Status Reminder',
                    body: "Don't forget to submit your daily status for today ({$today}).",
                    workspaceId: (int) $workspace->id,
                    projectId: null,
                    taskId: null,
                    fileId: null,
                    actorId: (int) $workspace->owner_id,
                    recipientIds: array_values($missingUserIds),
                );

                $totalSent += count($missingUserIds);
            }
        }

        $this->info("Sent daily status reminders to {$totalSent} members across {$workspaces->count()} workspaces.");

        return self::SUCCESS;
    }
}
