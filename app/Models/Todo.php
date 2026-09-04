<?php

namespace App\Models;

use App\Models\Concerns\WorkHubModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Todo extends Model
{
    use SoftDeletes, WorkHubModel;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['recurrence' => 'array', 'due_at' => 'datetime', 'completed_at' => 'datetime'];
    }
}
