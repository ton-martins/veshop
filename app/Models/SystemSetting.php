<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SystemSetting extends Model
{
    use SoftDeletes;

    public const KEY_BRANDING = 'branding';

    public const KEY_PAYMENT_GATEWAY_CATALOG = 'payment_gateway_catalog';

    protected $fillable = [
        'key',
        'value',
    ];

    protected $casts = [
        'value' => 'array',
    ];

    public static function getValue(string $key, mixed $default = null): mixed
    {
        $setting = static::query()
            ->where('key', $key)
            ->first();

        if (! $setting) {
            return $default;
        }

        return $setting->value ?? $default;
    }

    public static function putValue(string $key, mixed $value): self
    {
        return static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value],
        );
    }
}
