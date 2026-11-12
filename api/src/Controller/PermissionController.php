<?php

declare(strict_types=1);

namespace App\Controller;

use App\Support\HttpError;
use App\Support\Json;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Throwable;

final class PermissionController
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function index(Request $request, Response $response): Response
    {
        $levels = $this->pdo->query(
            'SELECT id, name, description, is_active FROM levels ORDER BY name ASC'
        )->fetchAll();

        $pages = $this->pdo->query(
            'SELECT id, name, path, description, sort_order, is_active
             FROM pages ORDER BY sort_order ASC, name ASC'
        )->fetchAll();

        $assignments = $this->pdo->query(
            'SELECT level_id, page_id FROM level_permissions ORDER BY level_id ASC, page_id ASC'
        )->fetchAll();

        return Json::ok($response, [
            'levels' => array_map(
                static fn (array $row): array => [
                    'id' => (int) $row['id'],
                    'name' => $row['name'],
                    'description' => $row['description'],
                    'is_active' => (bool) (int) $row['is_active'],
                ],
                $levels,
            ),
            'pages' => array_map(fn (array $row): array => $this->presentPage($row), $pages),
            'level_permissions' => array_map(
                static fn (array $row): array => [
                    'level_id' => (int) $row['level_id'],
                    'page_id' => (int) $row['page_id'],
                ],
                $assignments,
            ),
        ]);
    }

    public function updateLevel(Request $request, Response $response, array $args): Response
    {
        $levelId = (int) $args['id'];
        $this->assertLevelExists($levelId);

        $body = $this->body($request);

        if (!array_key_exists('page_ids', $body) || !is_array($body['page_ids'])) {
            throw new HttpError(422, 'Validasi gagal.', ['page_ids' => ['Daftar page wajib dikirim.']]);
        }

        $pageIds = [];

        foreach ($body['page_ids'] as $value) {
            if (!is_int($value) && !(is_string($value) && ctype_digit($value))) {
                throw new HttpError(422, 'Validasi gagal.', ['page_ids' => ['ID page tidak valid.']]);
            }

            $pageIds[] = (int) $value;
        }

        $pageIds = array_values(array_unique($pageIds));
        $this->assertPagesExist($pageIds);

        $this->pdo->beginTransaction();

        try {
            $deleteStatement = $this->pdo->prepare('DELETE FROM level_permissions WHERE level_id = ?');
            $deleteStatement->execute([$levelId]);

            $insertStatement = $this->pdo->prepare(
                'INSERT INTO level_permissions (level_id, page_id) VALUES (?, ?)'
            );

            foreach ($pageIds as $pageId) {
                $insertStatement->execute([$levelId, $pageId]);
            }

            $this->pdo->commit();
        } catch (Throwable $exception) {
            $this->pdo->rollBack();

            throw $exception;
        }

        return Json::ok(
            $response,
            ['level_id' => $levelId, 'page_ids' => $pageIds],
            200,
            'Permission level berhasil disimpan.',
        );
    }

    public function userDetail(Request $request, Response $response, array $args): Response
    {
        return Json::ok($response, $this->buildUserDetail((int) $args['id']));
    }

    public function updateUser(Request $request, Response $response, array $args): Response
    {
        $userId = (int) $args['id'];
        $user = $this->findUserOrFail($userId);

        $body = $this->body($request);

        if (!array_key_exists('overrides', $body) || !is_array($body['overrides'])) {
            throw new HttpError(422, 'Validasi gagal.', ['overrides' => ['Daftar override wajib dikirim.']]);
        }

        $overrides = [];
        $seenPageIds = [];

        foreach ($body['overrides'] as $index => $item) {
            if (!is_array($item)) {
                throw new HttpError(422, 'Validasi gagal.', ['overrides' => ['Format override tidak valid.']]);
            }

            $pageId = $item['page_id'] ?? null;
            $effect = $item['effect'] ?? null;

            if (!is_int($pageId) && !(is_string($pageId) && ctype_digit($pageId))) {
                throw new HttpError(422, 'Validasi gagal.', ['overrides' => ['ID page tidak valid.']]);
            }

            $pageId = (int) $pageId;

            if (!in_array($effect, ['allow', 'deny'], true)) {
                throw new HttpError(422, 'Validasi gagal.', ['overrides' => ['Effect harus allow atau deny.']]);
            }

            if (in_array($pageId, $seenPageIds, true)) {
                throw new HttpError(422, 'Validasi gagal.', ['overrides' => ['Page tidak boleh duplikat.']]);
            }

            $seenPageIds[] = $pageId;
            $overrides[] = ['page_id' => $pageId, 'effect' => $effect];
        }

        $this->assertPagesExist($seenPageIds);

        $this->pdo->beginTransaction();

        try {
            $deleteStatement = $this->pdo->prepare('DELETE FROM user_permissions WHERE user_id = ?');
            $deleteStatement->execute([$userId]);

            $insertStatement = $this->pdo->prepare(
                'INSERT INTO user_permissions (user_id, page_id, effect) VALUES (?, ?, ?)'
            );

            foreach ($overrides as $override) {
                $insertStatement->execute([$userId, $override['page_id'], $override['effect']]);
            }

            $this->pdo->commit();
        } catch (Throwable $exception) {
            $this->pdo->rollBack();

            throw $exception;
        }

        return Json::ok(
            $response,
            $this->buildUserDetail($userId),
            200,
            'Override permission user berhasil disimpan.',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildUserDetail(int $userId): array
    {
        $user = $this->findUserOrFail($userId);

        $pages = $this->pdo->query(
            'SELECT id, name, path, description, sort_order, is_active
             FROM pages ORDER BY sort_order ASC, name ASC'
        )->fetchAll();

        $levelStatement = $this->pdo->prepare(
            'SELECT page_id FROM level_permissions WHERE level_id = ? ORDER BY page_id ASC'
        );
        $levelStatement->execute([(int) $user['level_id']]);
        $levelPageIds = array_map('intval', $levelStatement->fetchAll(PDO::FETCH_COLUMN));

        $overrideStatement = $this->pdo->prepare(
            'SELECT page_id, effect FROM user_permissions WHERE user_id = ? ORDER BY page_id ASC'
        );
        $overrideStatement->execute([$userId]);
        $overrides = array_map(
            static fn (array $row): array => [
                'page_id' => (int) $row['page_id'],
                'effect' => $row['effect'],
            ],
            $overrideStatement->fetchAll(),
        );

        $activePageIds = array_map(
            static fn (array $row): int => (int) $row['id'],
            array_filter($pages, static fn (array $row): bool => (bool) (int) $row['is_active']),
        );

        $basePageIds = array_values(array_intersect($levelPageIds, $activePageIds));
        $allowed = [];
        $denied = [];

        foreach ($overrides as $override) {
            if ($override['effect'] === 'allow') {
                $allowed[] = $override['page_id'];
            } else {
                $denied[] = $override['page_id'];
            }
        }

        $effectivePageIds = array_values(array_diff($basePageIds, $denied));
        $effectivePageIds = array_values(array_unique([
            ...$effectivePageIds,
            ...array_values(array_intersect($allowed, $activePageIds)),
        ]));
        sort($effectivePageIds);

        return [
            'user' => [
                'id' => (int) $user['id'],
                'full_name' => $user['full_name'],
                'username' => $user['username'],
                'level_id' => (int) $user['level_id'],
                'level_name' => $user['level_name'],
            ],
            'pages' => array_map(fn (array $row): array => $this->presentPage($row), $pages),
            'level_page_ids' => $levelPageIds,
            'overrides' => $overrides,
            'effective_page_ids' => $effectivePageIds,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function findUserOrFail(int $userId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT u.id, u.full_name, u.username, u.level_id, l.name AS level_name
             FROM users u
             JOIN levels l ON l.id = u.level_id
             WHERE u.id = ?'
        );
        $statement->execute([$userId]);
        $user = $statement->fetch();

        if ($user === false) {
            throw new HttpError(404, 'User tidak ditemukan.');
        }

        return $user;
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function presentPage(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'name' => $row['name'],
            'path' => $row['path'],
            'description' => $row['description'],
            'sort_order' => (int) $row['sort_order'],
            'is_active' => (bool) (int) $row['is_active'],
        ];
    }

    private function assertLevelExists(int $levelId): void
    {
        $statement = $this->pdo->prepare('SELECT id FROM levels WHERE id = ?');
        $statement->execute([$levelId]);

        if ($statement->fetchColumn() === false) {
            throw new HttpError(404, 'Level tidak ditemukan.');
        }
    }

    /**
     * @param array<int, int> $pageIds
     */
    private function assertPagesExist(array $pageIds): void
    {
        if ($pageIds === []) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($pageIds), '?'));
        $statement = $this->pdo->prepare("SELECT id FROM pages WHERE id IN ({$placeholders})");
        $statement->execute($pageIds);
        $found = array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
        $missing = array_values(array_diff($pageIds, $found));

        if ($missing !== []) {
            throw new HttpError(422, 'Validasi gagal.', [
                'page_ids' => ['Page tidak ditemukan: ' . implode(', ', $missing) . '.'],
            ]);
        }
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
