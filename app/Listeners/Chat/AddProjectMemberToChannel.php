<?php

namespace App\Listeners\Chat;

use App\Events\ProjectMemberAdded;
use App\Repositories\Contracts\ChatRepositoryInterface;
use Illuminate\Contracts\Queue\ShouldQueue;

class AddProjectMemberToChannel implements ShouldQueue
{
    public function __construct(private ChatRepositoryInterface $chat) {}

    public function handle(ProjectMemberAdded $event): void
    {
        $channel = $this->chat->findProjectChannel($event->project->workspace_id, $event->project->id);

        if ($channel !== null) {
            $this->chat->addMember($channel, $event->member->id);
        }
    }
}
