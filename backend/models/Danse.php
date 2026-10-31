<?php
declare(strict_types=1);

final class Danse
{
    public static function exists(int $id): bool
    {
        $stmt = Database::pdo()->prepare('SELECT 1 FROM danses WHERE id = :id');
        $stmt->execute(['id' => $id]);
        return $stmt->fetchColumn() !== false;
    }

    /** @return array<int, array{id:int, nom:string, region_origine:?string}> */
    public static function all(): array
    {
        $rows = Database::pdo()
            ->query('SELECT id, nom, region_origine FROM danses ORDER BY nom')
            ->fetchAll();

        return array_map(static fn (array $r): array => [
            'id'             => (int) $r['id'],
            'nom'            => $r['nom'],
            'region_origine' => $r['region_origine'],
        ], $rows);
    }
}
