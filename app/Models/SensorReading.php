<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/** Citiri senzori (tabelul sensor_readings). */
final class SensorReading
{
    /** Ultima citire sau null daca nu exista. */
    public static function latest(): ?array
    {
        $row = Database::getInstance()
            ->query('SELECT * FROM sensor_readings ORDER BY timestamp DESC LIMIT 1')
            ->fetch();

        return $row ?: null;
    }

    /**
     * Istoric pentru grafice. $range: 24h | 7d | 30d.
     * $interval vine dintr-un whitelist fix (nu input liber) -> sigur la concatenare.
     */
    public static function history(string $range): array
    {
        $interval = match ($range) {
            '7d'    => '7 DAY',
            '30d'   => '30 DAY',
            default => '1 DAY',
        };

        return Database::getInstance()->query(
            "SELECT id, timestamp, temp_apa, temp_aer, umiditate_aer, umiditate_sol,
                    tds, turbiditate, ploaie, nivel_sus, nivel_jos, nivel_cm
               FROM sensor_readings
              WHERE timestamp >= NOW() - INTERVAL {$interval}
              ORDER BY timestamp ASC
              LIMIT 1000"
        )->fetchAll();
    }

    /** Inserare citire (folosit de ESP32). Returneaza id-ul. */
    public static function create(array $d): int
    {
        Database::getInstance()->query(
            'INSERT INTO sensor_readings
                (temp_apa, temp_aer, umiditate_aer, umiditate_sol, tds, turbiditate, ploaie,
                 nivel_sus, nivel_jos, nivel_cm)
             VALUES (:temp_apa, :temp_aer, :umiditate_aer, :umiditate_sol, :tds, :turbiditate, :ploaie,
                     :nivel_sus, :nivel_jos, :nivel_cm)',
            [
                'temp_apa'      => $d['temp_apa']      ?? null,
                'temp_aer'      => $d['temp_aer']      ?? null,
                'umiditate_aer' => $d['umiditate_aer'] ?? null,
                'umiditate_sol' => $d['umiditate_sol'] ?? null,
                'tds'           => $d['tds']           ?? null,
                'turbiditate'   => $d['turbiditate']   ?? null,
                'ploaie'        => !empty($d['ploaie']) ? 1 : 0,
                // Float switches sunt legacy; coloane NOT NULL DEFAULT 0 in migrarea 001.
                // Trimit 0 cand firmware-ul nu le mai populeaza (sursa nivelului = nivel_cm).
                'nivel_sus'     => !empty($d['nivel_sus']) ? 1 : 0,
                'nivel_jos'     => !empty($d['nivel_jos']) ? 1 : 0,
                'nivel_cm'      => isset($d['nivel_cm']) ? (float) $d['nivel_cm'] : null,
            ]
        );

        return (int) Database::getInstance()->lastInsertId();
    }

    /**
     * Nivel descriptiv dintr-o citire — preferinta nivel_cm (HC-SR04);
     * fallback pe vechile float switches daca exista in randuri vechi.
     */
    public static function nivelFromRow(array $row): ?string
    {
        if (isset($row['nivel_cm']) && $row['nivel_cm'] !== null) {
            return self::nivel_crisp((float) $row['nivel_cm']);
        }
        if (isset($row['nivel_sus']) || isset($row['nivel_jos'])) {
            return self::nivel($row['nivel_sus'] ?? null, $row['nivel_jos'] ?? null);
        }
        return null;
    }

    /**
     * Distanta in cm de la senzor (HC-SR04 montat in capac) la suprafata apei
     * -> categorie nivel.
     *
     * Calibrare actuala (galeata 15L, senzor la ~30cm de fund, util ~14L):
     *   distanta > 20  -> GOL     (sub ~3L apa, prea putin pentru udare)
     *   distanta 8..20 -> PARTIAL (~3..10L apa)
     *   distanta < 8   -> PLIN    (peste ~10L apa)
     */
    public static function nivel_crisp(float $cm): string
    {
        if ($cm > 20.0) {
            return 'gol';
        }
        if ($cm > 8.0) {
            return 'partial';
        }
        return 'plin';
    }

    /** Nivel descriptiv din cele doua float switch-uri (legacy, pre-HC-SR04). */
    public static function nivel(?int $sus, ?int $jos): string
    {
        if ((int) $sus === 1) {
            return 'plin';
        }
        if ((int) $jos === 1) {
            return 'partial';
        }
        return 'gol';
    }
}
