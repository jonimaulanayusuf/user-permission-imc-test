<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Service\JwtService;
use App\Service\PermissionService;
use App\Support\HttpError;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

final class AuthMiddleware
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly JwtService $jwt,
        private readonly PermissionService $permissions,
        private readonly ?string $requiredPage = null,
    ) {
    }

    public function __invoke(Request $request, RequestHandler $handler): Response
    {
        $authorization = $request->getHeaderLine('Authorization');

        if (!preg_match('/^Bearer\s+(.+)$/i', $authorization, $matches)) {
            throw new HttpError(401, 'Token autentikasi tidak ditemukan.');
        }

        $userId = $this->jwt->userIdFromToken(trim($matches[1]));

        $statement = $this->pdo->prepare(
            'SELECT u.id, u.full_name, u.username, u.email, u.level_id, u.is_active,
                    l.name AS level_name, l.is_active AS level_active
             FROM users u
             JOIN levels l ON l.id = u.level_id
             WHERE u.id = ?'
        );
        $statement->execute([$userId]);
        $user = $statement->fetch();

        if ($user === false) {
            throw new HttpError(401, 'User tidak ditemukan.');
        }

        if (!(bool) (int) $user['is_active']) {
            throw new HttpError(403, 'Akun tidak aktif. Hubungi administrator.');
        }

        $request = $request->withAttribute('user', [
            'id' => (int) $user['id'],
            'full_name' => $user['full_name'],
            'username' => $user['username'],
            'email' => $user['email'],
            'level_id' => (int) $user['level_id'],
            'level_name' => $user['level_name'],
            'level_active' => (bool) (int) $user['level_active'],
            'is_active' => (bool) (int) $user['is_active'],
        ]);

        if ($this->requiredPage !== null
            && !$this->permissions->hasPath((int) $user['id'], (int) $user['level_id'], $this->requiredPage)
        ) {
            throw new HttpError(403, 'Anda tidak memiliki akses ke halaman ini.');
        }

        return $handler->handle($request);
    }
}
