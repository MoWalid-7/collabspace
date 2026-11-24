<?php

namespace App\Models;

use App\Enums\ProjectPriority;
use App\Enums\ProjectStatus;
use App\Models\Concerns\BelongsToWorkspace;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property string $type
 * @property ProjectPriority $priority
 * @property ProjectStatus $status
 * @property Carbon|null $start_date
 * @property Carbon|null $deadline
 * @property int $created_by
 * @property int|null $tasks_total
 * @property int|null $tasks_completed
 */
#[Fillable([
    'workspace_id',
    'name',
    'description',
    'type',
    'priority',
    'status',
    'start_date',
    'deadline',
    'created_by',
])]
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use BelongsToWorkspace, HasFactory, SoftDeletes;

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_user', 'project_id', 'user_id')
            ->withPivot('role_title');
    }

    /**
     * @return BelongsToMany<Team, $this>
     */
    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'project_team', 'project_id', 'team_id');
    }

    /**
     * @return HasMany<Task, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    protected function casts(): array
    {
        return [
            'priority' => ProjectPriority::class,
            'status' => ProjectStatus::class,
            'start_date' => 'date',
            'deadline' => 'date',
        ];
    }
}
