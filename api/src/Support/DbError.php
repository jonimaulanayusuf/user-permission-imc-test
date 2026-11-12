<?php

declare(strict_types=1);

namespace App\Support;

use PDOException;

final class DbError
{
    public static function isUniqueViolation(PDOException $exception): bool
    {
        return (string) $exception->getCode() === '23505';
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function uniqueFieldErrors(string $message): array
    {
        $map = [
            'ux_users_username_lower' => ['username' => ['Username sudah digunakan.']],
            'ux_users_email_lower' => ['email' => ['Email sudah digunakan.']],
            'ux_levels_name_lower' => ['name' => ['Nama level sudah digunakan.']],
            'ux_pages_path_lower' => ['path' => ['Path sudah digunakan.']],
        ];

        foreach ($map as $constraint => $errors) {
            if (str_contains($message, $constraint)) {
                return $errors;
            }
        }

        return [];
    }
}
