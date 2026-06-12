<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/** Coada de comenzi pentru ESP32 (tabelul commands). */
final class Command
{
    /** Emite o comanda (din dashboard). Returneaza randul creat. */
    public static function create(string $tip, ?int $durata): array
    {
        Database::getInstance()->query(
            'INSERT INTO commands (tip, durata, status) VALUES (:tip, :durata, :st)',
            [
                'tip'    => $tip,
                'durata' => $tip === 'udare' ? $durata : null,
                'st'     => 'pending',
            ]
        );
        $id = (int) Database::getInstance()->lastInsertId();

        return self::find($id);
    }

    public static function find(int $id): array
    {
        $row = Database::getInstance()
            ->query('SELECT * FROM commands WHERE id = :id', ['id' => $id])
            ->fetch();

        return $row ?: [];
    }

    /**
     * Urmatoarea comanda pentru ESP32. Atomic: marcam oldest pending ca executing,
     * apoi returnam doar comanda nou-revendicata (NU re-livram comenzi deja
     * executing din runde anterioare — altfel ESP32 resetează pumpOffAt la fiecare
     * poll si pompa ruleaza la nesfarsit).
     *
     * Tradeoff: daca livrarea pica intre claim si raspuns (HTTP error), comanda
     * ramane „executing" si NU se mai re-livreaza. Userul vede „Udare in curs" si
     * apasa „Oprește" pentru curatare. Acceptabil pentru un sistem manual.
     */
    public static function claimNext(): ?array
    {
        $db = Database::getInstance();
        $stmt = $db->query(
            "UPDATE commands
                SET status = 'executing'
              WHERE status = 'pending'
              ORDER BY id ASC
              LIMIT 1"
        );

        // Daca n-am revendicat nimic, NU returnam un executing vechi.
        if ($stmt->rowCount() === 0) {
            return null;
        }

        // Comanda nou-revendicata are id-ul cel mai mare dintre executing.
        $row = $db->query(
            "SELECT * FROM commands
              WHERE status = 'executing'
              ORDER BY id DESC
              LIMIT 1"
        )->fetch();

        return $row ?: null;
    }

    /**
     * Marcheaza comanda ca terminata. Daca ESP32 a refuzat-o (ex. rezervor gol),
     * stocheaza motivul in refused_reason — folosit la afisare in dashboard si la
     * decizia de a NU crea un irrigation_event pentru o udare care n-a avut loc.
     */
    public static function acknowledge(int $id, ?string $refusedReason = null): bool
    {
        $stmt = Database::getInstance()->query(
            "UPDATE commands
                SET status = 'done', ack_at = NOW(), refused_reason = :reason
              WHERE id = :id AND status <> 'done'",
            ['id' => $id, 'reason' => $refusedReason]
        );

        return $stmt->rowCount() > 0;
    }

    /**
     * Anuleaza imediat toate udarile pending/executing (cand userul apasa „Oprește").
     * Le marcam ca done cu motiv 'cancelled' ca sa nu apara false udari in istoric
     * si ca sa NU mai fie returnate de claimNext.
     * Returneaza numarul de comenzi anulate.
     */
    public static function cancelOpenWaterings(): int
    {
        $stmt = Database::getInstance()->query(
            "UPDATE commands
                SET status = 'done', ack_at = NOW(), refused_reason = 'cancelled'
              WHERE tip = 'udare' AND status IN ('pending', 'executing')"
        );

        return $stmt->rowCount();
    }

    /** Exista deja o comanda de udare in asteptare/executie? (anti-dublare fuzzy) */
    public static function pendingWateringExists(): bool
    {
        $n = (int) Database::getInstance()->query(
            "SELECT COUNT(*) FROM commands
              WHERE tip = 'udare' AND status IN ('pending', 'executing')"
        )->fetchColumn();

        return $n > 0;
    }

    /** Comanda deschisa curenta (pending sau executing), sau null. */
    public static function currentOpen(): ?array
    {
        $row = Database::getInstance()->query(
            "SELECT * FROM commands
              WHERE status IN ('pending', 'executing')
              ORDER BY id DESC LIMIT 1"
        )->fetch();

        return $row ?: null;
    }

    public static function recent(int $limit = 10): array
    {
        $limit = max(1, min(100, $limit));

        return Database::getInstance()->query(
            "SELECT * FROM commands ORDER BY id DESC LIMIT {$limit}"
        )->fetchAll();
    }

    /**
     * Ultima comanda refuzata local de ESP32 in ultimele $minutes minute
     * — folosit ca toast in dashboard ("Pompa nu a pornit: rezervor gol").
     */
    public static function lastRefused(int $minutes = 10): ?array
    {
        $row = Database::getInstance()->query(
            "SELECT id, tip, durata, refused_reason, ack_at
               FROM commands
              WHERE refused_reason IS NOT NULL
                AND ack_at >= NOW() - INTERVAL :m MINUTE
              ORDER BY ack_at DESC
              LIMIT 1",
            ['m' => $minutes]
        )->fetch();

        return $row ?: null;
    }
}
