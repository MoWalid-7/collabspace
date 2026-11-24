<?php

namespace Database\Factories;

use App\Enums\ChannelType;
use App\Models\Channel;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Channel>
 */
class ChannelFactory extends Factory
{
    protected $model = Channel::class;

    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'type' => ChannelType::Public,
            'name' => fake()->slug(2),
            'project_id' => null,
            'team_id' => null,
            'direct_key' => null,
            'created_by' => User::factory(),
        ];
    }
}
