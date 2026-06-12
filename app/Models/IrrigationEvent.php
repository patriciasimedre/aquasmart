<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/** Evenimente de udare (tabelul irrigation_events). */
final class IrrigationEvent
{
    public static function recent(int $limit = 20): array
    {
        $limit = max(1, min(200, $limit));

        return Database::getInstance()->query(
            "SELECT * FROM irrigation_events
              ORDER BY timestamp_start DESC
              LIMIT {$limit}"
        )->fetchAll();
    }

    public static function last(): ?array
    {
        $row = Database::getInstance()
            ->query('SELECT * FROM irrigation_events ORDER BY timestamp_start DESC LIMIT 1')
            ->fetch();

        return $row ?: null;
    }

    /** Numar de udari intr-un interval (whitelist), pt indicatorul "ultima udare". */
    public static function inRange(string $range): array
    {
        $interval = match ($range) {
            '7d'    => '7 DAY',
            '30d'   => '30 DAY',
            default => '1 DAY',
        };

        return Database::getInstance()->query(
            "SELECT * FROM irrigation_events
              WHERE timestamp_start >= NOW() - INTERVAL {$interval}
              ORDER BY timestamp_start DESC
              LIMIT 500"
        )->fetchAll();
    }

    public static function create(int $durata, string $motiv, ?string $nivelInainte, ?float $tempAer): int
    {
        Database::getInstance()->query(
            'INSERT INTO irrigation_events
                (durata_secunde, motiv, nivel_inainte, temp_aer_inainte)
             VALUES (:durata, :motiv, :nivel, :temp)',
            [
                'durata' => $durata,
                'motiv'  => $motiv,
                'nivel'  => $nivelInainte,
                'temp'   => $tempAer,
            ]
        );

        return (int) Database::getInstance()->lastInsertId();
    }
}
