<?php

namespace App\Models;

use App\Models\Concerns\WorkHubModel;
use Illuminate\Database\Eloquent\Model;

class AppSetting extends Model
{
    use WorkHubModel;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['value' => 'array'];
    }

    public static function getValue(string $key, array $default = []): array
    {
        return static::where('key', $key)->value('value') ?? $default;
    }

    public static function setValue(string $key, array $value, ?string $userId = null): self
    {
        return static::updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'updated_by' => $userId],
        );
    }
}
