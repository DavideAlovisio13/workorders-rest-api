<?php

declare(strict_types=1);

namespace WorkOrders\Support;

use PDO;

/** PDO/SQLite wrapper that owns the (idempotent) schema. */
final class Database
{
    private PDO $pdo;

    public function __construct(string $dbPath)
    {
        $dir = \dirname($dbPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $this->pdo = new PDO('sqlite:' . $dbPath);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->pdo->exec('PRAGMA journal_mode = WAL;');
        $this->migrate();
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    private function migrate(): void
    {
        $this->pdo->exec(<<<'SQL'
            CREATE TABLE IF NOT EXISTS work_orders (
                id              INTEGER PRIMARY KEY AUTOINCREMENT,
                code            TEXT NOT NULL UNIQUE,
                title           TEXT NOT NULL,
                customer        TEXT NOT NULL,
                status          TEXT NOT NULL DEFAULT 'open',
                priority        TEXT NOT NULL DEFAULT 'medium',
                assignee        TEXT,
                hours_estimated REAL NOT NULL DEFAULT 0,
                hours_spent     REAL NOT NULL DEFAULT 0,
                due_date        TEXT,
                created_at      TEXT NOT NULL,
                updated_at      TEXT NOT NULL
            );
        SQL);
    }
}
