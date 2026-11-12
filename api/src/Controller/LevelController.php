<?php

declare(strict_types=1);

namespace App\Controller;

use App\Support\HttpError;
use App\Support\Json;
use App\Support\Validator;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class LevelController
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function index(Request $request, Response $response): Response
    {
        $statement = $this->pdo->query(
            'SELECT l.*, COUNT(u.id) AS user_count
             FROM levels l
             LEFT JOIN users u ON u.level_id = l.id
             GROUP BY l.id
             ORDER BY l.name ASC'
        );

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
                'name' => 'required|string|max:50',
                'description' => 'nullable|string|max:255',
                'is_active' => 'nullable|boolean',
            ],
            [
                'name' => 'Nama level',
                'description' => 'Deskripsi',
                'is_active' => 'Status aktif',
            ],
        );

        if ($errors !== []) {
            throw new HttpError(422, 'Validasi gagal.', $errors);
        }

        $name = trim((string) $body['name']);
        $isActive = array_key_exists('is_active', $body) ? $this->toBool($body['is_active']) : true;

        $this->assertUniqueName($name, 0);

        $statement = $this->pdo->prepare(
            'INSERT INTO levels (name, description, is_active) VALUES (?, ?, ?) RETURNING id'
        );
        $statement->execute([
            $name,
            $this->description($body),
            $isActive ? 1 : 0,
        ]);

        return Json::ok(
            $response,
            $this->present($this->findOrFail((int) $statement->fetchColumn())),
            201,
            'Level berhasil ditambahkan.',
        );
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        $existing = $this->findOrFail($id);
        $body = $this->body($request);

        $errors = Validator::validate(
            $body,
            [
                'name' => 'required|string|max:50',
                'description' => 'nullable|string|max:255',
                'is_active' => 'nullable|boolean',
            ],
            [
                'name' => 'Nama level',
                'description' => 'Deskripsi',
                'is_active' => 'Status aktif',
            ],
        );

        if ($errors !== []) {
            throw new HttpError(422, 'Validasi gagal.', $errors);
        }

        $name = trim((string) $body['name']);
        $isActive = array_key_exists('is_active', $body)
            ? $this->toBool($body['is_active'])
            : (bool) (int) $existing['is_active'];

        $this->assertUniqueName($name, $id);

        $statement = $this->pdo->prepare(
            'UPDATE levels
             SET name = ?, description = ?, is_active = ?, updated_at = now()
             WHERE id = ?'
        );
        $statement->execute([
            $name,
            $this->description($body),
            $isActive ? 1 : 0,
            $id,
        ]);

        return Json::ok($response, $this->present($this->findOrFail($id)), 200, 'Level berhasil diperbarui.');
    }

    public function destroy(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        $this->findOrFail($id);

        $countStatement = $this->pdo->prepare('SELECT COUNT(*) FROM users WHERE level_id = ?');
        $countStatement->execute([$id]);
        $userCount = (int) $countStatement->fetchColumn();

        if ($userCount > 0) {
            throw new HttpError(409, "Level masih digunakan oleh {$userCount} user. Pindahkan atau hapus user tersebut terlebih dahulu.");
        }

        $statement = $this->pdo->prepare('DELETE FROM levels WHERE id = ?');
        $statement->execute([$id]);

        return Json::ok($response, null, 200, 'Level berhasil dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    private function findOrFail(int $id): array
    {
        $statement = $this->pdo->prepare(
            'SELECT l.*, (SELECT COUNT(*) FROM users u WHERE u.level_id = l.id) AS user_count
             FROM levels l
             WHERE l.id = ?'
        );
        $statement->execute([$id]);
        $row = $statement->fetch();

        if ($row === false) {
            throw new HttpError(404, 'Level tidak ditemukan.');
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
            'name' => $row['name'],
            'description' => $row['description'],
            'is_active' => (bool) (int) $row['is_active'],
            'user_count' => (int) ($row['user_count'] ?? 0),
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'],
        ];
    }

    private function assertUniqueName(string $name, int $ignoreId): void
    {
        $statement = $this->pdo->prepare('SELECT id FROM levels WHERE lower(name) = lower(?) AND id <> ? LIMIT 1');
        $statement->execute([$name, $ignoreId]);

        if ($statement->fetchColumn() !== false) {
            throw new HttpError(422, 'Validasi gagal.', ['name' => ['Nama level sudah digunakan.']]);
        }
    }

    /**
     * @param array<string, mixed> $body
     */
    private function description(array $body): ?string
    {
        $description = isset($body['description']) && is_string($body['description'])
            ? trim($body['description'])
            : '';

        return $description === '' ? null : $description;
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
