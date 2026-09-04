<?php

namespace App\Models;

use App\Models\Concerns\WorkHubModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkflowDefinition extends Model
{
    use SoftDeletes, WorkHubModel;

    protected $guarded = [];

    public function versions(): HasMany
    {
        return $this->hasMany(WorkflowVersion::class);
    }
}
