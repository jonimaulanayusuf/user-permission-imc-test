<?php

declare(strict_types=1);

namespace App\Service;

use App\Support\Env;
use App\Support\HttpError;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

final class JwtService
{
    private readonly string $secret;

    private readonly int $ttl;

    public function __construct()
    {
        $this->secret = (string) Env::get('JWT_SECRET', '');
        $this->ttl = Env::int('JWT_TTL', 28800);
    }

    public function issue(int $userId): string
    {
        $this->assertConfigured();

        $now = time();

        return JWT::encode(
            [
                'sub' => (string) $userId,
                'iat' => $now,
                'exp' => $now + $this->ttl,
            ],
            $this->secret,
            'HS256',
        );
    }

    public function userIdFromToken(string $token): int
    {
        $this->assertConfigured();

        try {
            $payload = JWT::decode($token, new Key($this->secret, 'HS256'));
        } catch (\Throwable) {
            throw new HttpError(401, 'Sesi tidak valid atau sudah kedaluwarsa. Silakan login kembali.');
        }

        $subject = $payload->sub ?? null;

        if (!is_string($subject) && !is_int($subject)) {
            throw new HttpError(401, 'Token tidak valid.');
        }

        return (int) $subject;
    }

    private function assertConfigured(): void
    {
        if ($this->secret === '' || strlen($this->secret) < 32) {
            throw new HttpError(500, 'JWT_SECRET belum dikonfigurasi dengan benar. Isi minimal 32 karakter di file .env.');
        }
    }
}
