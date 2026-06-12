<?php

declare(strict_types=1);

// ------------------------------------------------------------
//  Test rapid al conexiunii la baza de date.
//  Rulare:  php scripts/test_db_connection.php
//      sau: composer test-db
// ------------------------------------------------------------

require_once dirname(__DIR__) . '/vendor/autoload.php';

Dotenv\Dotenv::createImmutable(dirname(__DIR__))->safeLoad();

use App\Core\Database;

try {
    $pdo  = Database::getInstance()->getConnection();
    $info = $pdo->query('SELECT VERSION() AS v, NOW() AS now, DATABASE() AS db')->fetch();

    fwrite(STDOUT, "OK  Conectat la MySQL\n");
    fwrite(STDOUT, "    Versiune : {$info['v']}\n");
    fwrite(STDOUT, '    Baza     : ' . ($info['db'] ?? '(neselectata)') . "\n");
    fwrite(STDOUT, "    Ora      : {$info['now']}\n");

    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    fwrite(STDOUT, '    Tabele   : ' . (
        count($tables) ? implode(', ', $tables) : '(niciun tabel — ruleaza migrations/001_create_tables.sql)'
    ) . "\n");

    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'EROARE conexiune: ' . $e->getMessage() . "\n");
    fwrite(STDERR, "Verifica: .env (DB_HOST/DB_USER/DB_PASSWORD), firewall Azure, certificat DB_SSL_CA.\n");
    exit(1);
}
