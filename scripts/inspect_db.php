<?php

declare(strict_types=1);

// ------------------------------------------------------------
//  Inspectie READ-ONLY a bazei de date: cate randuri are fiecare
//  tabel + lista coloanelor. Nu modifica nimic.
//  Rulare:  php scripts/inspect_db.php
// ------------------------------------------------------------

require_once dirname(__DIR__) . '/vendor/autoload.php';

Dotenv\Dotenv::createImmutable(dirname(__DIR__))->safeLoad();

use App\Core\Database;

try {
    $pdo    = Database::getInstance()->getConnection();
    $db     = $pdo->query('SELECT DATABASE()')->fetchColumn();
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);

    fwrite(STDOUT, "Baza: {$db}  —  " . count($tables) . " tabele\n");
    fwrite(STDOUT, str_repeat('=', 60) . "\n");

    foreach ($tables as $t) {
        $count = (int) $pdo->query('SELECT COUNT(*) FROM `' . $t . '`')->fetchColumn();
        $cols  = $pdo->query('SHOW COLUMNS FROM `' . $t . '`')->fetchAll(PDO::FETCH_COLUMN);
        $flag  = $count > 0 ? '  <-- ARE DATE' : '';
        fwrite(STDOUT, sprintf("%-22s %6d randuri%s\n", $t, $count, $flag));
        fwrite(STDOUT, '   coloane: ' . implode(', ', $cols) . "\n");
    }
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'EROARE: ' . $e->getMessage() . "\n");
    exit(1);
}
