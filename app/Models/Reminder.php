<?php

namespace App\Models;

use App\Models\Concerns\WorkHubModel;
use Illuminate\Database\Eloquent\Model;

class Reminder extends Model
{
    use WorkHubModel;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['remind_at' => 'datetime', 'sent_at' => 'datetime'];
    }
}
