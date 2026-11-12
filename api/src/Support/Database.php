<?php

declare(strict_types=1);

namespace App\Support;

use PDO;

final class Database
{
    private static ?PDO $connection = null;

    public static function pdo(): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        $dsn = sprintf(
            'pgsql:host=%s;port=%d;dbname=%s',
            Env::get('DB_HOST', '127.0.0.1'),
            Env::int('DB_PORT', 5432),
            Env::get('DB_NAME', 'imc_permission'),
        );

        self::$connection = new PDO(
            $dsn,
            Env::get('DB_USER', 'postgres'),
            Env::get('DB_PASSWORD', ''),
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ],
        );

        return self::$connection;
    }
}
