<?php

/**
 * Database Seed Script
 *
 * Creates a default admin user for development and testing purposes.
 *
 * Usage (from project root):
 *   php database/seed.php
 *
 * Default credentials:
 *   username : admin
 *   password : admin123  (stored as bcrypt hash — never stored as plaintext)
 *
 * The script is idempotent: running it multiple times will not create
 * duplicate rows. If the admin user already exists the password hash is
 * updated so the known test password is always valid after seeding.
 *
 * Requirements: 1.6
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

// ── Seed data ─────────────────────────────────────────────────────────────────

const SEED_USERNAME = 'admin';
const SEED_PASSWORD = 'admin123';

// ── Hash the password with bcrypt ─────────────────────────────────────────────

$hashedPassword = password_hash(SEED_PASSWORD, PASSWORD_BCRYPT);

if ($hashedPassword === false) {
    fwrite(STDERR, "ERROR: password_hash() failed.\n");
    exit(1);
}

// ── Upsert the admin user ─────────────────────────────────────────────────────

try {
    $pdo = getDbConnection();

    // INSERT … ON DUPLICATE KEY UPDATE keeps the script idempotent.
    // The UNIQUE constraint on `username` triggers the UPDATE branch when the
    // row already exists, refreshing the password hash to the seeded value.
    $stmt = $pdo->prepare(
        'INSERT INTO users (username, password)
         VALUES (:username, :password)
         ON DUPLICATE KEY UPDATE password = VALUES(password)'
    );

    $stmt->execute([
        ':username' => SEED_USERNAME,
        ':password' => $hashedPassword,
    ]);

    $action = $stmt->rowCount() === 1 ? 'created' : 'updated';
    echo "Seed complete: admin user {$action}.\n";
    echo "  username : " . SEED_USERNAME . "\n";
    echo "  password : " . SEED_PASSWORD . "  (stored as bcrypt hash)\n";
} catch (PDOException $e) {
    fwrite(STDERR, "ERROR: " . $e->getMessage() . "\n");
    exit(1);
}
