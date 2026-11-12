<?php

declare(strict_types=1);

namespace App\Service;

use PDO;

final class PermissionService
{
    /** @var array<string, array<int, int>> */
    private array $cache = [];

    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @return array<int, int>
     */
    public function effectivePageIds(int $userId, int $levelId): array
    {
        $cacheKey = $userId . ':' . $levelId;

        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        $levelStatement = $this->pdo->prepare(
            'SELECT lp.page_id
             FROM level_permissions lp
             JOIN pages p ON p.id = lp.page_id
             WHERE lp.level_id = ? AND p.is_active = 1'
        );
        $levelStatement->execute([$levelId]);
        $levelPageIds = array_map('intval', $levelStatement->fetchAll(PDO::FETCH_COLUMN));

        $overrideStatement = $this->pdo->prepare(
            'SELECT up.page_id, up.effect
             FROM user_permissions up
             JOIN pages p ON p.id = up.page_id
             WHERE up.user_id = ? AND p.is_active = 1'
        );
        $overrideStatement->execute([$userId]);

        $allowed = [];
        $denied = [];

        foreach ($overrideStatement->fetchAll() as $row) {
            if ($row['effect'] === 'allow') {
                $allowed[] = (int) $row['page_id'];
            } else {
                $denied[] = (int) $row['page_id'];
            }
        }

        $effective = array_values(array_diff($levelPageIds, $denied));
        $effective = array_values(array_unique(array_merge($effective, $allowed)));

        $this->cache[$cacheKey] = $effective;

        return $effective;
    }

    /**
     * @return array<int, array{id: int, name: string, path: string, description: ?string, sort_order: int}>
     */
    public function menusForUser(int $userId, int $levelId): array
    {
        $pageIds = $this->effectivePageIds($userId, $levelId);

        if ($pageIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($pageIds), '?'));

        $statement = $this->pdo->prepare(
            "SELECT id, name, path, description, sort_order
             FROM pages
             WHERE id IN ({$placeholders}) AND is_active = 1
             ORDER BY sort_order ASC, name ASC"
        );
        $statement->execute($pageIds);

        return array_map(
            static fn (array $row): array => [
                'id' => (int) $row['id'],
                'name' => $row['name'],
                'path' => $row['path'],
                'description' => $row['description'],
                'sort_order' => (int) $row['sort_order'],
            ],
            $statement->fetchAll(),
        );
    }

    public function hasPath(int $userId, int $levelId, string $path): bool
    {
        foreach ($this->menusForUser($userId, $levelId) as $menu) {
            if ($menu['path'] === $path) {
                return true;
            }
        }

        return false;
    }
}
