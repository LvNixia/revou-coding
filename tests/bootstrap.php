<?php

/**
 * PHPUnit Bootstrap File
 *
 * Sets up the test environment before any tests run.
 * - Loads the Composer autoloader (which also loads helper files via autoload.files)
 * - Sets test environment variables
 */

declare(strict_types=1);

// Load Composer autoloader (also loads config/database.php, src/helpers/csrf.php,
// src/helpers/sanitize.php, src/middleware/auth_guard.php via autoload.files)
require_once __DIR__ . '/../vendor/autoload.php';

// ── Test environment variables ────────────────────────────────────────────────

$_ENV['DB_HOST']    = $_ENV['DB_HOST']    ?? '127.0.0.1';
$_ENV['DB_PORT']    = $_ENV['DB_PORT']    ?? '3306';
$_ENV['DB_NAME']    = $_ENV['DB_NAME']    ?? 'spk_test';
$_ENV['DB_USER']    = $_ENV['DB_USER']    ?? 'root';
$_ENV['DB_PASS']    = $_ENV['DB_PASS']    ?? '';
$_ENV['DB_CHARSET'] = $_ENV['DB_CHARSET'] ?? 'utf8mb4';

// Propagate to $_SERVER so getenv() and PDO config can pick them up
foreach ($_ENV as $key => $value) {
    if (!isset($_SERVER[$key])) {
        $_SERVER[$key] = $value;
    }
}
