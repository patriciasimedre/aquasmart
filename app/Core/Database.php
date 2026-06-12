<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use PDOStatement;
use RuntimeException;

/**
 * Conexiune MySQL (PDO) ca singleton, cu reincercari.
 *
 * Configurarea (host, user, parola, optiuni TLS pentru Azure) vine din
 * config/database.php. Folosire:
 *
 *   $pdo = Database::getInstance()->getConnection();
 *   $rows = Database::getInstance()->query('SELECT * FROM settings')->fetchAll();
 */
final class Database
{
    private static ?Database $instance = null;
    private PDO $pdo;

    private function __construct()
    {
        /** @var array{host:string,port:string,name:string,user:string,pass:string,charset:string,options:array} $config */
        $config = require dirname(__DIR__, 2) . '/config/database.php';

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $config['host'],
            $config['port'],
            $config['name'],
            $config['charset']
        );

        $maxAttempts = 3;
        for ($attempt = 1; ; $attempt++) {
            try {
                $this->pdo = new PDO($dsn, $config['user'], $config['pass'], $config['options']);
                return;
            } catch (PDOException $e) {
                if ($attempt >= $maxAttempts) {
                    throw new RuntimeException(
                        "Conexiune MySQL esuata dupa {$maxAttempts} incercari: " . $e->getMessage(),
                        (int) $e->getCode(),
                        $e
                    );
                }
                usleep(500_000); // 500 ms intre reincercari
            }
        }
    }

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    public function getConnection(): PDO
    {
        return $this->pdo;
    }

    /** Prepare + execute intr-un singur apel. */
    public function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function lastInsertId(): string
    {
        return $this->pdo->lastInsertId();
    }

    public function beginTransaction(): bool
    {
        return $this->pdo->beginTransaction();
    }

    public function commit(): bool
    {
        return $this->pdo->commit();
    }

    public function rollBack(): bool
    {
        return $this->pdo->rollBack();
    }

    private function __clone() {}

    public function __wakeup()
    {
        throw new RuntimeException('Singleton-ul Database nu poate fi deserializat.');
    }
}
