<?php

namespace App\Models;

use App\Models\Concerns\WorkHubModel;
use Illuminate\Database\Eloquent\Model;

class OdooConnection extends Model
{
    use WorkHubModel;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
            'last_result' => 'array',
            'last_tested_at' => 'datetime',
            'last_synced_at' => 'datetime',
        ];
    }
}
