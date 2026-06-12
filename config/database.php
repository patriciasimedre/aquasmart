<?php

declare(strict_types=1);

// Config conexiune MySQL. Returneaza un array consumat de App\Core\Database.
// Variabilele vin din .env (vlucas/phpdotenv) sau din environment-ul serverului
// (pe Azure App Service le setezi in Configuration -> Application settings).

$env = static function (string $key, ?string $default = null): ?string {
    $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
    if ($value === false || $value === null || $value === '') {
        return $default;
    }
    return (string) $value;
};

$host = $env('DB_HOST', 'localhost');
$port = $env('DB_PORT', '3306');
$name = $env('DB_NAME', 'aquasmart');
$user = $env('DB_USER', 'root');
// README1.md foloseste DB_PASSWORD; .env-ul real/vechi foloseste DB_PASS -> fallback,
// ca sa nu trebuiasca sa editezi .env-ul existent ca sa te conectezi.
$pass = $env('DB_PASSWORD', $env('DB_PASS', ''));

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

// --- TLS pentru Azure Database for MySQL Flexible Server ---
// Azure cere conexiune criptata.
//  • Daca DB_SSL_CA e setat si fisierul exista -> verificare completa a certificatului.
//  • Altfel, daca host-ul e Azure -> TLS fara verificarea stricta a CA
//    (comportament compatibil cu setup-ul anterior, ca sa nu se strice conexiunea).
$constCa     = defined('Pdo\Mysql::ATTR_SSL_CA')
    ? Pdo\Mysql::ATTR_SSL_CA : PDO::MYSQL_ATTR_SSL_CA;
$constVerify = defined('Pdo\Mysql::ATTR_SSL_VERIFY_SERVER_CERT')
    ? Pdo\Mysql::ATTR_SSL_VERIFY_SERVER_CERT : PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT;

$sslCa   = $env('DB_SSL_CA');
$isAzure = str_contains($host, 'azure');

if ($sslCa !== null && is_file($sslCa)) {
    $options[$constCa]     = $sslCa;
    $options[$constVerify] = true;
} elseif ($isAzure) {
    $options[$constCa]     = '';
    $options[$constVerify] = false;
}

return [
    'host'    => $host,
    'port'    => $port,
    'name'    => $name,
    'user'    => $user,
    'pass'    => $pass,
    'charset' => 'utf8mb4',
    'options' => $options,
];
