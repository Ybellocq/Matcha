<?php
declare(strict_types=1);

namespace App;

use PDO;
use PDOException;

final class Database
{
    private static ?PDO $connection = null;

    public static function getConnection(): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        $host = env('DB_HOST', 'localhost');
        $port = env('DB_PORT', '5432');
        $name = env('DB_NAME', 'matcha');
        $user = env('DB_USER', 'matcha');
        $pass = env('DB_PASS', 'matcha');

        $dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s', $host, $port, $name);

        self::$connection = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        return self::$connection;
    }
}
