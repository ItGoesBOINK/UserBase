<?php

declare(strict_types=1);

namespace UserBase\Database;

use PDO;
use UserBase\Config\Configuration;

final class DatabaseConnection
{
    private ?PDO $connection = null;

    public function __construct(private readonly Configuration $config)
    {

    }

    public function GetConnection(): PDO
    {
        if ($this->connection !== null) {
            return $this->connection;
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $this->config->databaseHost,
            $this->config->databasePort,
            $this->config->databaseName,
        );

        $this->connection = new PDO(
            $dsn,
            $this->config->databaseUser,
            $this->config->databasePassword,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );

        return $this->connection;
    }
}

?>