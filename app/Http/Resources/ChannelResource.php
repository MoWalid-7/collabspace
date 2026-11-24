<?php

namespace App\Http\Resources;

use App\Models\Channel;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Channel
 */
class ChannelResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Message|null $lastMessage */
        $lastMessage = $this->relationLoaded('last_message_relation')
            ? $this->getRelation('last_message_relation')
            : null;

        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'name' => $this->name,
            'project_id' => $this->project_id,
            'team_id' => $this->team_id,
            'direct_key' => $this->direct_key,
            'created_by' => $this->created_by,
            'unread_count' => (int) ($this->unread_count ?? 0),
            'last_message' => $lastMessage ? [
                'id' => $lastMessage->id,
                'body' => $lastMessage->body,
                'created_at' => $lastMessage->created_at->toISOString(),
                'sender' => [
                    'id' => $lastMessage->user->id,
                    'name' => $lastMessage->user->name,
                    'avatar' => $lastMessage->user->avatar,
                ],
            ] : null,
            'members' => $this->relationLoaded('members') ? $this->members->map(fn ($m) => [
                'id' => $m->id,
                'name' => $m->name,
                'avatar' => $m->avatar,
            ]) : [],
            'project' => $this->project ? [
                'id' => $this->project->id,
                'name' => $this->project->name,
            ] : null,
            'team' => $this->team ? [
                'id' => $this->team->id,
                'name' => $this->team->name,
            ] : null,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
