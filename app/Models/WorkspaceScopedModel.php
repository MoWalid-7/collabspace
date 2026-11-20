<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;

abstract class WorkspaceScopedModel extends Model
{
    use BelongsToWorkspace;
}
