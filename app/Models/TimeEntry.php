<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $workspace_id
 * @property int $user_id
 * @property int $project_id
 * @property int|null $task_id
 * @property CarbonInterface $started_at
 * @property CarbonInterface|null $ended_at
 * @property int|null $duration_seconds
 * @property string|null $note
 * @property CarbonInterface|null $deleted_at
 * @property-read Workspace $workspace
 * @property-read User $user
 * @property-read Project $project
 * @property-read Task|null $task
 */
class TimeEntry extends Model
{
    use BelongsToWorkspace, HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'workspace_id',
        'user_id',
        'project_id',
        'task_id',
        'started_at',
        'ended_at',
        'duration_seconds',
        'note',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'duration_seconds' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }
}
