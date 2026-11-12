<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\JwtService;
use App\Service\PermissionService;
use App\Support\HttpError;
use App\Support\Json;
use App\Support\Validator;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class AuthController
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly PermissionService $permissions,
        private readonly JwtService $jwt,
    ) {
    }

    public function login(Request $request, Response $response): Response
    {
        $body = $this->body($request);
        $errors = Validator::validate(
            $body,
            [
                'username' => 'required|string|max:150',
                'password' => 'required|string|max:72',
            ],
            [
                'username' => 'Username/email',
                'password' => 'Password',
            ],
        );

        if ($errors !== []) {
            throw new HttpError(422, 'Validasi gagal.', $errors);
        }

        $identifier = trim((string) $body['username']);

        $statement = $this->pdo->prepare(
            'SELECT u.id, u.full_name, u.username, u.email, u.password_hash, u.level_id, u.is_active,
                    l.name AS level_name, l.is_active AS level_active
             FROM users u
             JOIN levels l ON l.id = u.level_id
             WHERE lower(u.username) = lower(?) OR lower(u.email) = lower(?)
             LIMIT 1'
        );
        $statement->execute([$identifier, $identifier]);
        $user = $statement->fetch();

        if ($user === false || !password_verify((string) $body['password'], $user['password_hash'])) {
            throw new HttpError(401, 'Username/email atau password salah.');
        }

        if (!(bool) (int) $user['is_active']) {
            throw new HttpError(403, 'Akun tidak aktif. Hubungi administrator.');
        }

        $payload = $this->sessionPayload($user);
        $payload['token'] = $this->jwt->issue((int) $user['id']);

        return Json::ok($response, $payload, 200, 'Login berhasil.');
    }

    public function me(Request $request, Response $response): Response
    {
        /** @var array<string, mixed> $user */
        $user = $request->getAttribute('user');

        return Json::ok($response, $this->sessionPayload($user));
    }

    /**
     * @param array<string, mixed> $user
     *
     * @return array<string, mixed>
     */
    private function sessionPayload(array $user): array
    {
        $normalizedUser = [
            'id' => (int) $user['id'],
            'full_name' => $user['full_name'],
            'username' => $user['username'],
            'email' => $user['email'],
            'level' => [
                'id' => (int) $user['level_id'],
                'name' => $user['level_name'],
                'is_active' => (bool) (int) $user['level_active'],
            ],
            'is_active' => (bool) (int) $user['is_active'],
        ];

        $menus = $this->permissions->menusForUser($normalizedUser['id'], $normalizedUser['level']['id']);

        return [
            'user' => $normalizedUser,
            'permissions' => array_map(static fn (array $menu): string => $menu['path'], $menus),
            'menus' => $menus,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function body(Request $request): array
    {
        $parsedBody = $request->getParsedBody();

        return is_array($parsedBody) ? $parsedBody : [];
    }
}
