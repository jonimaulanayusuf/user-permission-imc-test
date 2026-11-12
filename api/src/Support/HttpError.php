<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;

final class HttpError extends RuntimeException
{
    /**
     * @param array<string, array<int, string>> $errors
     */
    public function __construct(
        public readonly int $status,
        string $message,
        public readonly array $errors = [],
    ) {
        parent::__construct($message);
    }
}
