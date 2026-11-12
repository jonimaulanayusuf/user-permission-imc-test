<?php

declare(strict_types=1);

namespace App\Controller;

use App\Support\HttpError;
use App\Support\Json;
use App\Support\Validator;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class UserController
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function index(Request $request, Response $response): Response
    {
        $query = $request->getQueryParams();
        $search = trim((string) ($query['search'] ?? ''));

        $sql = 'SELECT u.*, l.name AS level_name, l.is_active AS level_active
                FROM users u
                JOIN levels l ON l.id = u.level_id';
        $params = [];

        if ($search !== '') {
            $sql .= ' WHERE lower(u.full_name) LIKE ? OR lower(u.username) LIKE ? OR lower(u.email) LIKE ?';
            $term = '%' . mb_strtolower($search) . '%';
            $params = [$term, $term, $term];
        }

        $sql .= ' ORDER BY u.full_name ASC';

        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        return Json::ok($response, array_map(
            fn (array $row): array => $this->present($row),
            $statement->fetchAll(),
        ));
    }

    public function show(Request $request, Response $response, array $args): Response
    {
        return Json::ok($response, $this->present($this->findOrFail((int) $args['id'])));
    }

    public function store(Request $request, Response $response): Response
    {
        $body = $this->body($request);
        $errors = Validator::validate(
            $body,
            [
                'full_name' => 'required|string|max:100',
                'username' => 'required|string|min:3|max:50|regex:/^[a-zA-Z0-9._-]+$/',
                'email' => 'required|email|max:150',
                'password' => 'required|string|min:8|max:72',
                'level_id' => 'required|integer',
                'is_active' => 'nullable|boolean',
            ],
            [
                'full_name' => 'Nama lengkap',
                'username' => 'Username',
                'email' => 'Email',
                'password' => 'Password',
                'level_id' => 'Level',
                'is_active' => 'Status aktif',
            ],
        );

        if ($errors !== []) {
            throw new HttpError(422, 'Validasi gagal.', $errors);
        }

        $fullName = trim((string) $body['full_name']);
        $username = trim((string) $body['username']);
        $email = mb_strtolower(trim((string) $body['email']));
        $levelId = (int) $body['level_id'];
        $isActive = array_key_exists('is_active', $body) ? $this->toBool($body['is_active']) : true;

        $this->assertLevelExists($levelId, true);
        $this->assertUnique($username, $email, 0);

        $statement = $this->pdo->prepare(
            'INSERT INTO users (full_name, username, email, password_hash, level_id, is_active)
             VALUES (?, ?, ?, ?, ?, ?)
             RETURNING id'
        );
        $statement->execute([
            $fullName,
            $username,
            $email,
            password_hash((string) $body['password'], PASSWORD_DEFAULT),
            $levelId,
            $isActive ? 1 : 0,
        ]);

        $id = (int) $statement->fetchColumn();

        return Json::ok($response, $this->present($this->findOrFail($id)), 201, 'User berhasil ditambahkan.');
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        $existing = $this->findOrFail($id);
        $body = $this->body($request);

        $errors = Validator::validate(
            $body,
            [
                'full_name' => 'required|string|max:100',
                'username' => 'required|string|min:3|max:50|regex:/^[a-zA-Z0-9._-]+$/',
                'email' => 'required|email|max:150',
                'password' => 'nullable|string|min:8|max:72',
                'level_id' => 'required|integer',
                'is_active' => 'nullable|boolean',
            ],
            [
                'full_name' => 'Nama lengkap',
                'username' => 'Username',
                'email' => 'Email',
                'password' => 'Password',
                'level_id' => 'Level',
                'is_active' => 'Status aktif',
            ],
        );

        if ($errors !== []) {
            throw new HttpError(422, 'Validasi gagal.', $errors);
        }

        $fullName = trim((string) $body['full_name']);
        $username = trim((string) $body['username']);
        $email = mb_strtolower(trim((string) $body['email']));
        $levelId = (int) $body['level_id'];
        $isActive = array_key_exists('is_active', $body)
            ? $this->toBool($body['is_active'])
            : (bool) (int) $existing['is_active'];

        $levelChanged = $levelId !== (int) $existing['level_id'];

        $this->assertLevelExists($levelId, $levelChanged);
        $this->assertUnique($username, $email, $id);

        $password = isset($body['password']) && is_string($body['password']) && trim($body['password']) !== ''
            ? (string) $body['password']
            : null;

        if ($password !== null) {
            $statement = $this->pdo->prepare(
                'UPDATE users
                 SET full_name = ?, username = ?, email = ?, password_hash = ?, level_id = ?, is_active = ?, updated_at = now()
                 WHERE id = ?'
            );
            $statement->execute([
                $fullName,
                $username,
                $email,
                password_hash($password, PASSWORD_DEFAULT),
                $levelId,
                $isActive ? 1 : 0,
                $id,
            ]);
        } else {
            $statement = $this->pdo->prepare(
                'UPDATE users
                 SET full_name = ?, username = ?, email = ?, level_id = ?, is_active = ?, updated_at = now()
                 WHERE id = ?'
            );
            $statement->execute([
                $fullName,
                $username,
                $email,
                $levelId,
                $isActive ? 1 : 0,
                $id,
            ]);
        }

        return Json::ok($response, $this->present($this->findOrFail($id)), 200, 'User berhasil diperbarui.');
    }

    public function destroy(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        /** @var array<string, mixed> $currentUser */
        $currentUser = $request->getAttribute('user');

        if ((int) $currentUser['id'] === $id) {
            throw new HttpError(409, 'Tidak dapat menghapus akun yang sedang digunakan.');
        }

        $this->findOrFail($id);

        $statement = $this->pdo->prepare('DELETE FROM users WHERE id = ?');
        $statement->execute([$id]);

        return Json::ok($response, null, 200, 'User berhasil dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    private function findOrFail(int $id): array
    {
        $statement = $this->pdo->prepare(
            'SELECT u.*, l.name AS level_name, l.is_active AS level_active
             FROM users u
             JOIN levels l ON l.id = u.level_id
             WHERE u.id = ?'
        );
        $statement->execute([$id]);
        $row = $statement->fetch();

        if ($row === false) {
            throw new HttpError(404, 'User tidak ditemukan.');
        }

        return $row;
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function present(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'full_name' => $row['full_name'],
            'username' => $row['username'],
            'email' => $row['email'],
            'level' => [
                'id' => (int) $row['level_id'],
                'name' => $row['level_name'],
                'is_active' => (bool) (int) $row['level_active'],
            ],
            'is_active' => (bool) (int) $row['is_active'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'],
        ];
    }

    private function assertLevelExists(int $levelId, bool $mustBeActive): void
    {
        $statement = $this->pdo->prepare('SELECT id, is_active FROM levels WHERE id = ?');
        $statement->execute([$levelId]);
        $level = $statement->fetch();

        if ($level === false) {
            throw new HttpError(422, 'Validasi gagal.', ['level_id' => ['Level tidak ditemukan.']]);
        }

        if ($mustBeActive && !(bool) (int) $level['is_active']) {
            throw new HttpError(422, 'Validasi gagal.', ['level_id' => ['Level tidak aktif.']]);
        }
    }

    private function assertUnique(string $username, string $email, int $ignoreId): void
    {
        $errors = [];

        $usernameStatement = $this->pdo->prepare('SELECT id FROM users WHERE lower(username) = lower(?) AND id <> ? LIMIT 1');
        $usernameStatement->execute([$username, $ignoreId]);

        if ($usernameStatement->fetchColumn() !== false) {
            $errors['username'][] = 'Username sudah digunakan.';
        }

        $emailStatement = $this->pdo->prepare('SELECT id FROM users WHERE lower(email) = lower(?) AND id <> ? LIMIT 1');
        $emailStatement->execute([$email, $ignoreId]);

        if ($emailStatement->fetchColumn() !== false) {
            $errors['email'][] = 'Email sudah digunakan.';
        }

        if ($errors !== []) {
            throw new HttpError(422, 'Validasi gagal.', $errors);
        }
    }

    private function toBool(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
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
