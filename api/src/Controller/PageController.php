<?php

declare(strict_types=1);

namespace App\Controller;

use App\Support\HttpError;
use App\Support\Json;
use App\Support\Validator;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class PageController
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function index(Request $request, Response $response): Response
    {
        $statement = $this->pdo->query(
            'SELECT * FROM pages ORDER BY sort_order ASC, name ASC'
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
                'name' => 'required|string|max:100',
                'path' => 'required|string|max:150',
                'description' => 'nullable|string|max:255',
                'sort_order' => 'nullable|integer',
                'is_active' => 'nullable|boolean',
            ],
            [
                'name' => 'Nama page',
                'path' => 'Path',
                'description' => 'Deskripsi',
                'sort_order' => 'Urutan',
                'is_active' => 'Status aktif',
            ],
        );

        if ($errors !== []) {
            throw new HttpError(422, 'Validasi gagal.', $errors);
        }

        $name = trim((string) $body['name']);
        $path = $this->normalizePath((string) $body['path']);
        $sortOrder = isset($body['sort_order']) ? (int) $body['sort_order'] : 0;
        $isActive = array_key_exists('is_active', $body) ? $this->toBool($body['is_active']) : true;

        $this->assertUniquePath($path, 0);

        $statement = $this->pdo->prepare(
            'INSERT INTO pages (name, path, description, sort_order, is_active)
             VALUES (?, ?, ?, ?, ?)
             RETURNING id'
        );
        $statement->execute([
            $name,
            $path,
            $this->description($body),
            $sortOrder,
            $isActive ? 1 : 0,
        ]);

        return Json::ok(
            $response,
            $this->present($this->findOrFail((int) $statement->fetchColumn())),
            201,
            'Page berhasil ditambahkan.',
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
                'name' => 'required|string|max:100',
                'path' => 'required|string|max:150',
                'description' => 'nullable|string|max:255',
                'sort_order' => 'nullable|integer',
                'is_active' => 'nullable|boolean',
            ],
            [
                'name' => 'Nama page',
                'path' => 'Path',
                'description' => 'Deskripsi',
                'sort_order' => 'Urutan',
                'is_active' => 'Status aktif',
            ],
        );

        if ($errors !== []) {
            throw new HttpError(422, 'Validasi gagal.', $errors);
        }

        $name = trim((string) $body['name']);
        $path = $this->normalizePath((string) $body['path']);
        $sortOrder = isset($body['sort_order']) ? (int) $body['sort_order'] : (int) $existing['sort_order'];
        $isActive = array_key_exists('is_active', $body)
            ? $this->toBool($body['is_active'])
            : (bool) (int) $existing['is_active'];

        $this->assertUniquePath($path, $id);

        $statement = $this->pdo->prepare(
            'UPDATE pages
             SET name = ?, path = ?, description = ?, sort_order = ?, is_active = ?, updated_at = now()
             WHERE id = ?'
        );
        $statement->execute([
            $name,
            $path,
            $this->description($body),
            $sortOrder,
            $isActive ? 1 : 0,
            $id,
        ]);

        return Json::ok($response, $this->present($this->findOrFail($id)), 200, 'Page berhasil diperbarui.');
    }

    public function destroy(Request $request, Response $response, array $args): Response
    {
        $id = (int) $args['id'];
        $this->findOrFail($id);

        $statement = $this->pdo->prepare('DELETE FROM pages WHERE id = ?');
        $statement->execute([$id]);

        return Json::ok($response, null, 200, 'Page berhasil dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    private function findOrFail(int $id): array
    {
        $statement = $this->pdo->prepare('SELECT * FROM pages WHERE id = ?');
        $statement->execute([$id]);
        $row = $statement->fetch();

        if ($row === false) {
            throw new HttpError(404, 'Page tidak ditemukan.');
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
            'path' => $row['path'],
            'description' => $row['description'],
            'sort_order' => (int) $row['sort_order'],
            'is_active' => (bool) (int) $row['is_active'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'],
        ];
    }

    private function normalizePath(string $path): string
    {
        $path = '/' . trim(mb_strtolower(trim($path)), '/');

        if ($path === '/' || !preg_match('#^/[a-z0-9][a-z0-9\-/]*$#', $path) || str_contains($path, '//')) {
            throw new HttpError(422, 'Validasi gagal.', [
                'path' => ['Path harus diawali "/" dan hanya boleh berisi huruf kecil, angka, tanda hubung, atau garis miring.'],
            ]);
        }

        return $path;
    }

    private function assertUniquePath(string $path, int $ignoreId): void
    {
        $statement = $this->pdo->prepare('SELECT id FROM pages WHERE lower(path) = lower(?) AND id <> ? LIMIT 1');
        $statement->execute([$path, $ignoreId]);

        if ($statement->fetchColumn() !== false) {
            throw new HttpError(422, 'Validasi gagal.', ['path' => ['Path sudah digunakan.']]);
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
