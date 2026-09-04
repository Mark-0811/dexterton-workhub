<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExternalMapping extends Model
{
    protected $guarded = [];
    public $timestamps = false;

    protected function casts(): array
    {
        return ['errors' => 'array'];
    }
}
