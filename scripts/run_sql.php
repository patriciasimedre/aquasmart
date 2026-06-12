<?php

declare(strict_types=1);

// ------------------------------------------------------------
//  Runner pentru fisiere .sql (migratii), prin conexiunea PDO.
//  Nu depinde de clientul mysql (lipseste des pe macOS).
//  Rulare:  php scripts/run_sql.php migrations/001_create_tables.sql
// ------------------------------------------------------------

require_once dirname(__DIR__) . '/vendor/autoload.php';

Dotenv\Dotenv::createImmutable(dirname(__DIR__))->safeLoad();

use App\Core\Database;

$file = $argv[1] ?? null;
if ($file === null || !is_file($file)) {
    fwrite(STDERR, "Utilizare: php scripts/run_sql.php <fisier.sql>\n");
    exit(2);
}

// Elimina liniile de comentariu (-- ...) si liniile goale, apoi
// imparte in instructiuni dupa ';'. Fisierele noastre nu au ';'
// in interiorul sirurilor sau al comentariilor.
$lines = preg_split('/\R/', (string) file_get_contents($file));
$kept  = [];
foreach ($lines as $line) {
    $trimmed = ltrim($line);
    if ($trimmed === '' || str_starts_with($trimmed, '--')) {
        continue;
    }
    $kept[] = $line;
}
$statements = array_filter(
    array_map('trim', explode(';', implode("\n", $kept))),
    static fn (string $s): bool => $s !== ''
);

$pdo = Database::getInstance()->getConnection();

$n = 0;
foreach ($statements as $stmt) {
    $label = preg_replace('/\s+/', ' ', mb_substr($stmt, 0, 70));
    try {
        $pdo->exec($stmt);
        fwrite(STDOUT, sprintf("OK   [%2d] %s\n", ++$n, $label));
    } catch (Throwable $e) {
        // CREATE DATABASE / USE pot esua daca esti deja conectat la baza
        // sau nu ai privilegiul — sunt redundante in acest context.
        if (preg_match('/^\s*(CREATE\s+DATABASE|USE)\b/i', $stmt)) {
            fwrite(STDOUT, sprintf("SKIP [%2d] %s  (%s)\n", ++$n, $label, $e->getMessage()));
            continue;
        }
        fwrite(STDERR, "EROARE la: {$label}\n  -> " . $e->getMessage() . "\n");
        exit(1);
    }
}

fwrite(STDOUT, "Gata: {$n} instructiuni din " . basename($file) . "\n");
exit(0);
