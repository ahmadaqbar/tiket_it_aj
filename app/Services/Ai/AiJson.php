<?php

namespace App\Services\Ai;

/**
 * Ekstraksi JSON dari jawaban model (mendukung jawaban yang dibungkus ```json ... ```).
 */
class AiJson
{
    public static function decode(string $text): ?array
    {
        $text = trim($text);
        if (preg_match('/```(?:json)?\s*(\{.*\})\s*```/s', $text, $m)) {
            $text = $m[1];
        } elseif (($start = strpos($text, '{')) !== false && ($end = strrpos($text, '}')) !== false) {
            $text = substr($text, $start, $end - $start + 1);
        }
        $data = json_decode($text, true);

        return is_array($data) ? $data : null;
    }

    /** @return array<int, string> */
    public static function stringList(mixed $value, int $max = 8, int $len = 400): array
    {
        return collect(is_array($value) ? $value : [])
            ->filter(fn ($v) => is_string($v) && trim($v) !== '')
            ->map(fn ($v) => mb_substr(trim($v), 0, $len))
            ->take($max)->values()->all();
    }

    public static function string(mixed $value, int $len = 2000): ?string
    {
        return is_string($value) && trim($value) !== '' ? mb_substr(trim($value), 0, $len) : null;
    }
}
