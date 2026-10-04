
<?php

function databaseEnvironment(string $name, bool $allowEmpty = false): string
{
    $value = getenv($name);

    if ($value === false || (!$allowEmpty && trim($value) === '')) {
        throw new RuntimeException("Missing required database environment variable: {$name}");
    }

    return $value;
}

define('DB_HOST', databaseEnvironment('DB_HOST'));
define('DB_NAME', databaseEnvironment('DB_NAME'));
define('DB_USER', databaseEnvironment('DB_USER'));
define('DB_PASS', databaseEnvironment('DB_PASS', true));

function db(): PDO
{
    static $pdo;

    if (!$pdo) {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
    }

    return $pdo;
}