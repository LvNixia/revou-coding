<?php

/**
 * Database configuration and PDO connection factory.
 *
 * Reads credentials from environment variables with fallback defaults.
 * Usage:
 *   $pdo = getDbConnection();
 */

declare(strict_types=1);

/**
 * Returns a configured PDO instance connected to the MySQL database.
 *
 * Environment variables (with defaults):
 *   DB_HOST     — hostname or IP (default: 127.0.0.1)
 *   DB_PORT     — port number   (default: 3306)
 *   DB_NAME     — database name (default: spk_db)
 *   DB_USER     — username      (default: root)
 *   DB_PASS     — password      (default: empty string)
 *   DB_CHARSET  — charset       (default: utf8mb4)
 *
 * @throws PDOException if the connection cannot be established.
 */
function getDbConnection(): PDO
{
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    $host    = $_ENV['DB_HOST']    ?? getenv('DB_HOST')    ?: '127.0.0.1';
    $port    = $_ENV['DB_PORT']    ?? getenv('DB_PORT')    ?: '3306';
    $dbname  = $_ENV['DB_NAME']    ?? getenv('DB_NAME')    ?: 'spk-revou';
    $user    = $_ENV['DB_USER']    ?? getenv('DB_USER')    ?: 'root';
    $pass    = $_ENV['DB_PASS']    ?? getenv('DB_PASS')    ?: '';
    $charset = $_ENV['DB_CHARSET'] ?? getenv('DB_CHARSET') ?: 'utf8mb4';

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
        $host,
        $port,
        $dbname,
        $charset
    );

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        // charset is already set in the DSN; no INIT_COMMAND needed
    ];

    $pdo = new PDO($dsn, $user, $pass, $options);

    return $pdo;
}
