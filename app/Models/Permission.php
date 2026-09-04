<?php

namespace App\Models;

use App\Models\Concerns\WorkHubModel;
use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    use WorkHubModel;

    protected $guarded = [];
}
