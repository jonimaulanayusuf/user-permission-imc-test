<?php

declare(strict_types=1);

namespace App\Support;

use Psr\Http\Message\ResponseInterface as Response;

final class Json
{
    public static function ok(Response $response, mixed $data = null, int $status = 200, ?string $message = null): Response
    {
        $payload = ['success' => true];

        if ($message !== null) {
            $payload['message'] = $message;
        }

        $payload['data'] = $data;

        return self::write($response, $payload, $status);
    }

    /**
     * @param array<string, array<int, string>> $errors
     */
    public static function error(Response $response, int $status, string $message, array $errors = []): Response
    {
        $payload = [
            'success' => false,
            'message' => $message,
        ];

        if ($errors !== []) {
            $payload['errors'] = $errors;
        }

        return self::write($response, $payload, $status);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function write(Response $response, array $payload, int $status): Response
    {
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $response->getBody()->write(
            $json === false ? '{"success":false,"message":"Gagal membuat response JSON."}' : $json
        );

        return $response
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withStatus($status);
    }
}
