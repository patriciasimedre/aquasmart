<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/** Cache pentru rapoartele AI generate (tabelul ai_reports). */
final class AiReport
{
    /** Raport deja generat pentru exact aceeasi perioada (cache). */
    public static function findCached(string $start, string $end): ?array
    {
        $row = Database::getInstance()->query(
            'SELECT * FROM ai_reports
              WHERE perioada_start = :s AND perioada_end = :e
              ORDER BY id DESC LIMIT 1',
            ['s' => $start, 'e' => $end]
        )->fetch();

        return $row ?: null;
    }

    public static function create(string $start, string $end, string $continut, string $model): array
    {
        Database::getInstance()->query(
            'INSERT INTO ai_reports (perioada_start, perioada_end, continut, model)
             VALUES (:s, :e, :c, :m)',
            ['s' => $start, 'e' => $end, 'c' => $continut, 'm' => $model]
        );
        $id = (int) Database::getInstance()->lastInsertId();

        $row = Database::getInstance()
            ->query('SELECT * FROM ai_reports WHERE id = :id', ['id' => $id])
            ->fetch();

        return $row ?: [];
    }

    public static function latest(int $limit = 5): array
    {
        $limit = max(1, min(50, $limit));

        return Database::getInstance()->query(
            "SELECT id, timestamp, perioada_start, perioada_end, model
               FROM ai_reports ORDER BY id DESC LIMIT {$limit}"
        )->fetchAll();
    }
}
