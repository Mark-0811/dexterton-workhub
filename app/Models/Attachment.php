<?php

namespace App\Models;

use App\Models\Concerns\WorkHubModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Attachment extends Model
{
    use SoftDeletes, WorkHubModel;

    protected $guarded = [];
}
