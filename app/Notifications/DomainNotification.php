<?php

namespace App\Notifications;

use App\Data\Notifications\NotificationData;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class DomainNotification extends Notification
{
    use Queueable;

    public function __construct(private NotificationData $data) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return $this->data->toArray();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return $this->data->toArray();
    }
}
