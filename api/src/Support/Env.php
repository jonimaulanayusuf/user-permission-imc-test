<?php

declare(strict_types=1);

namespace App\Support;

final class Env
{
    public static function get(string $key, ?string $default = null): ?string
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        if ($value === false || $value === null) {
            return $default;
        }

        $value = (string) $value;

        return $value === '' ? $default : $value;
    }

    public static function int(string $key, int $default): int
    {
        $value = self::get($key);

        return $value !== null && is_numeric($value) ? (int) $value : $default;
    }
}
