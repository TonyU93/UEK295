<?php

/**
 * Database.php Central mysqli database connection.
 *
 * Establishes a reusable mysqli connection based on the
 * configuration from the .env file. All queries in the project
 * run exclusively via prepared statements SQL injection protection.
 */

declare(strict_types=1);

namespace App;

use mysqli;

final class Database
{
    /** @var mysqli|null a single connection established for all requests */
    private static ?mysqli $connection = null;

    /**
     * brings the mysqli-connection or create it if its need.
     *
     * @throws \RuntimeException if connection failure
     */
    public static function getConnection(): mysqli
    {
        if (self::$connection instanceof mysqli) {
            return self::$connection;
        }

        // throw error messages from mysqli as exceptions. not silent
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

        // UTF-8 as standart for all querries
        self::$connection->set_charset('utf8mb4');

        return self::$connection;
    }

    /**
     * close the connection at the end
     */
    public static function close(): void
    {
        if (self::$connection instanceof mysqli) {
            self::$connection->close();
            self::$connection = null;
        }
    }
}
