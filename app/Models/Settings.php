<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/** Configurari sistem — un singur rand, id = 1 (tabelul settings). */
final class Settings
{
    /** Lista campurilor permise la update (white-list, protectie SQL injection). */
    private const FIELDS = [
        'prag_umiditate_aer',
        'interval_minim_udare',
        'mod_automat',
        'prag_sol_uscat',
        'prag_tds_maxim',
        'prag_temp_apa_min',
        'prag_temp_apa_max',
        'prag_turbiditate_maxim',
    ];

    public static function get(): array
    {
        $row = Database::getInstance()
            ->query('SELECT * FROM settings WHERE id = 1')
            ->fetch();

        // Fallback daca lipseste randul implicit (nu ar trebui dupa 001).
        return $row ?: [
            'id'                    => 1,
            'prag_umiditate_aer'    => 60.0,
            'interval_minim_udare'  => 360,
            'mod_automat'           => 1,
            'prag_sol_uscat'        => 30,
            'prag_tds_maxim'        => 800,
            'prag_temp_apa_min'     => 5,
            'prag_temp_apa_max'     => 40,
            'prag_turbiditate_maxim'=> 50,
            'updated_at'            => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Update generic: accepta orice subset al campurilor din self::FIELDS.
     * Campurile nementionate raman neschimbate.
     */
    public static function update(array $fields): array
    {
        $clean = [];
        foreach (self::FIELDS as $name) {
            if (!array_key_exists($name, $fields)) {
                continue;
            }
            $clean[$name] = self::normalize($name, $fields[$name]);
        }

        if ($clean === []) {
            return self::get();
        }

        $sets = [];
        foreach ($clean as $name => $_) {
            $sets[] = "{$name} = :{$name}";
        }

        Database::getInstance()->query(
            'UPDATE settings SET ' . implode(', ', $sets) . ' WHERE id = 1',
            $clean
        );

        return self::get();
    }

    /** Sanitizare/limite per camp. */
    private static function normalize(string $name, mixed $value): mixed
    {
        return match ($name) {
            'prag_umiditate_aer' => max(0.0, min(100.0, (float) $value)),
            'interval_minim_udare' => max(0, min(10080, (int) $value)), // 0..7 zile
            'mod_automat'        => ((bool) $value) ? 1 : 0,
            'prag_sol_uscat'     => max(0, min(100, (int) $value)),
            'prag_tds_maxim'     => max(0, min(5000, (int) $value)),
            'prag_temp_apa_min'  => max(-20, min(60, (int) $value)),
            'prag_temp_apa_max'  => max(-20, min(80, (int) $value)),
            'prag_turbiditate_maxim' => max(10, min(2000, (int) $value)),
            default              => $value,
        };
    }
}
