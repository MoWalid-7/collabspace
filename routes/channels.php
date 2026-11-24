<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('channel.{channelId}', function (User $user, int $channelId): bool {
    return $user->channels()->where('channel_id', $channelId)->exists();
});
