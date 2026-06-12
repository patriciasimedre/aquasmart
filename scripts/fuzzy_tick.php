<?php

declare(strict_types=1);

// ------------------------------------------------------------
//  Ruleaza o data decizia fuzzy si (daca e cazul) emite comanda.
//  Util pentru:
//   - demo fara ESP32 (declansezi manual o reevaluare)
//   - cron / Azure WebJob periodic, ex. la fiecare 10 min:
//       *_/10 * * * *  php /path/aquasmart/scripts/fuzzy_tick.php
//  Argument optional "dry" = doar preview, fara comanda.
// ------------------------------------------------------------

require_once dirname(__DIR__) . '/vendor/autoload.php';

Dotenv\Dotenv::createImmutable(dirname(__DIR__))->safeLoad();

use App\Services\IrrigationDecision;

$commit = (($argv[1] ?? '') !== 'dry');

try {
    $d = (new IrrigationDecision())->evaluate($commit);

    fwrite(STDOUT, ($commit ? '[commit]' : '[dry-run]') . " {$d['timestamp']}\n");
    fwrite(STDOUT, "  intrari : " . json_encode($d['intrari'], JSON_UNESCAPED_UNICODE) . "\n");
    fwrite(STDOUT, "  fuzzy   : brut {$d['fuzzy']['durata_bruta']}s -> {$d['fuzzy']['durata']}s, "
        . count($d['fuzzy']['reguli']) . " reguli active\n");
    fwrite(STDOUT, "  gate    : " . ($d['gate'] ?? '(niciunul)') . "\n");
    fwrite(STDOUT, "  durata  : {$d['durata_finala']}s · actiune: {$d['actiune']}\n");
    fwrite(STDOUT, "  => {$d['explicatie']}\n");
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'EROARE: ' . $e->getMessage() . "\n");
    exit(1);
}
