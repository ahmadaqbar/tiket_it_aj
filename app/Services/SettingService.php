<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

class SettingService
{
    private const CACHE_KEY = 'app_settings';

    public function get(string $key, mixed $default = null): mixed
    {
        $all = Cache::rememberForever(self::CACHE_KEY, fn () => Setting::all()
            ->mapWithKeys(fn (Setting $s) => [$s->key => $s->typedValue()])
            ->all());

        return $all[$key] ?? $default;
    }

    public function set(string $key, mixed $value, ?string $type = null): void
    {
        $attributes = ['value' => is_bool($value) ? (int) $value : $value];
        if ($type !== null) {
            $attributes['type'] = $type;
        } elseif (! Setting::where('key', $key)->exists()) {
            $attributes['type'] = is_bool($value) ? 'bool' : (is_int($value) ? 'int' : 'string');
        }
        Setting::updateOrCreate(['key' => $key], $attributes);
        Cache::forget(self::CACHE_KEY);
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
