<?php

declare(strict_types=1);

namespace FeedMySheep;

use PDO;
use Throwable;

final class Database
{
    private ?PDO $connection = null;

    public function __construct(private readonly Config $config)
    {
    }

    public function connection(): PDO
    {
        if ($this->connection instanceof PDO) {
            return $this->connection;
        }

        $host = (string) $this->config->require('database.host');
        $port = (int) $this->config->get('database.port', 3306);
        $name = (string) $this->config->require('database.name');
        $charset = (string) $this->config->get('database.charset', 'utf8mb4');
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $host, $port, $name, $charset);

        $this->connection = new PDO(
            $dsn,
            (string) $this->config->require('database.username'),
            (string) $this->config->require('database.password'),
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_STRINGIFY_FETCHES => false,
            ]
        );

        return $this->connection;
    }

    public function transaction(callable $operation): mixed
    {
        $pdo = $this->connection();
        $pdo->beginTransaction();
        try {
            $result = $operation($pdo);
            $pdo->commit();
            return $result;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }
}

