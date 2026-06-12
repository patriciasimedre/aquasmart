<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use RuntimeException;

/** Profiluri de plante (tabela plant_profiles). */
final class PlantProfile
{
    /** Toate profilurile (predefinite primele, Custom la sfarsit). */
    public static function all(): array
    {
        return Database::getInstance()->query(
            'SELECT * FROM plant_profiles ORDER BY is_custom ASC, id ASC'
        )->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $row = Database::getInstance()
            ->query('SELECT * FROM plant_profiles WHERE id = :id', ['id' => $id])
            ->fetch();
        return $row ?: null;
    }

    /** Profilul curent activ (is_active = 1) sau null. */
    public static function active(): ?array
    {
        $row = Database::getInstance()
            ->query('SELECT * FROM plant_profiles WHERE is_active = 1 ORDER BY id ASC LIMIT 1')
            ->fetch();
        return $row ?: null;
    }

    /**
     * Activeaza un profil: marcheaza is_active=1 pe el (0 pe restul) si
     * copiaza valorile lui in tabela settings.
     */
    public static function activate(int $id): array
    {
        $row = self::find($id);
        if ($row === null) {
            throw new RuntimeException('Profil inexistent.');
        }

        $db = Database::getInstance();
        $db->query('UPDATE plant_profiles SET is_active = 0');
        $db->query('UPDATE plant_profiles SET is_active = 1 WHERE id = :id', ['id' => $id]);

        // Propagam valorile profilului in setarile sistemului.
        Settings::update([
            'prag_sol_uscat'       => (int) $row['sol_min'],
            'prag_tds_maxim'       => (int) $row['tds_max'],
            'interval_minim_udare' => (int) $row['interval_min'],
        ]);

        $row['is_active'] = 1;
        return $row;
    }
}
