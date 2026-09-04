<?php

namespace App\Models;

use App\Models\Concerns\WorkHubModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkRequest extends Model
{
    use SoftDeletes, WorkHubModel;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['form_data' => 'array', 'submitted_at' => 'datetime', 'resolved_at' => 'datetime', 'sla_due_at' => 'datetime'];
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(ApprovalStep::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
    }

    public function serviceFlow(): BelongsTo
    {
        return $this->belongsTo(ServiceFlow::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
