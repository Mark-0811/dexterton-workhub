<?php

namespace App\Models;

use App\Models\Concerns\WorkHubModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MigrationBatch extends Model
{
    use WorkHubModel;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['options' => 'array', 'totals' => 'array', 'started_at' => 'datetime', 'finished_at' => 'datetime'];
    }

    public function rows(): HasMany
    {
        return $this->hasMany(OdooImportRow::class);
    }
}
