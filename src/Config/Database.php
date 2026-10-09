<?php

declare(strict_types=1);

namespace App\Config;

use mysqli;

final class Database
{
    private static ?mysqli $connection = null;

    /**
     * Get or initialize database connection.
     */
    public static function getConnection(): mysqli
    {
        if (self::$connection instanceof mysqli) {
            return self::$connection;
        }

        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

        try {
            self::$connection = new mysqli(
                Env::get('DB_HOST', 'localhost'),
                Env::get('DB_USER', 'root'),
                Env::get('DB_PASS', ''),
                Env::get('DB_NAME', 'shop')
            );
        } catch (\mysqli_sql_exception $e) {
            throw new \RuntimeException('DB connection failure: ' . $e->getMessage(), 0, $e);
        }

        self::$connection->set_charset('utf8mb4');

        return self::$connection;
    }

    /**
     * Close database connection.
     */
    public static function close(): void
    {
        if (self::$connection instanceof mysqli) {
            self::$connection->close();
            self::$connection = null;
        }
    }
}