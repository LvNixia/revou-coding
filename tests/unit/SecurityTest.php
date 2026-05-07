<?php

/**
 * Unit Tests — SQL Injection Prevention
 *
 * Verifies that SQL injection attempts are stored as literal strings and do
 * not execute arbitrary SQL when prepared statements are used.
 *
 * These tests use an in-memory SQLite database so they run without a MySQL
 * server and without touching the application database.
 *
 * Requirements: 7.1, 7.5
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class SecurityTest extends TestCase
{
    private PDO $pdo;

    // ── Setup / Teardown ──────────────────────────────────────────────────────

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        // Mirror the kriteria table from the application schema
        $this->pdo->exec(
            'CREATE TABLE kriteria (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                nama       TEXT    NOT NULL UNIQUE,
                bobot      REAL    NOT NULL,
                tipe       TEXT    NOT NULL,
                created_at TEXT    DEFAULT (datetime(\'now\')),
                updated_at TEXT    DEFAULT (datetime(\'now\'))
            )'
        );
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Insert a kriteria row using a prepared statement (mirrors application code).
     */
    private function insertKriteria(string $nama, float $bobot = 0.5, string $tipe = 'benefit'): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO kriteria (nama, bobot, tipe) VALUES (:nama, :bobot, :tipe)'
        );
        $stmt->execute([':nama' => $nama, ':bobot' => $bobot, ':tipe' => $tipe]);
    }

    /**
     * Count rows in a table.
     */
    private function countRows(string $table): int
    {
        return (int) $this->pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
    }

    /**
     * Fetch a kriteria row by exact nama, or null if not found.
     */
    private function fetchByNama(string $nama): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM kriteria WHERE nama = :nama LIMIT 1');
        $stmt->execute([':nama' => $nama]);
        $row = $stmt->fetch();
        return $row !== false ? $row : null;
    }

    // =========================================================================
    // Section 1 — SQL injection via nama field
    // =========================================================================

    /**
     * A classic DROP TABLE injection in the nama field must be stored as a
     * literal string and must NOT drop the kriteria table.
     *
     * Requirements: 7.1
     */
    public function testDropTableInjectionStoredAsLiteralString(): void
    {
        $injection = "'; DROP TABLE kriteria; --";

        $this->insertKriteria($injection);

        // The table must still exist and contain exactly one row
        $this->assertSame(1, $this->countRows('kriteria'),
            'kriteria table must still exist with 1 row after injection attempt');

        // The injected string must be stored verbatim
        $row = $this->fetchByNama($injection);
        $this->assertNotNull($row,
            'The injection string must be stored as a literal value');
        $this->assertSame($injection, $row['nama'],
            'The stored nama must equal the literal injection string');
    }

    /**
     * A UNION-based injection attempt in the nama field must be stored as a
     * literal string and must not leak data from other queries.
     *
     * Requirements: 7.1
     */
    public function testUnionInjectionStoredAsLiteralString(): void
    {
        $injection = "' UNION SELECT 1,2,3,4,5 --";

        $this->insertKriteria($injection);

        $this->assertSame(1, $this->countRows('kriteria'),
            'Table must contain exactly 1 row after UNION injection attempt');

        $row = $this->fetchByNama($injection);
        $this->assertNotNull($row,
            'UNION injection string must be stored as a literal value');
        $this->assertSame($injection, $row['nama']);
    }

    /**
     * An OR-based injection attempt (always-true condition) in the nama field
     * must be stored as a literal string.
     *
     * Requirements: 7.1
     */
    public function testOrInjectionStoredAsLiteralString(): void
    {
        $injection = "' OR '1'='1";

        $this->insertKriteria($injection);

        $this->assertSame(1, $this->countRows('kriteria'),
            'Table must contain exactly 1 row after OR injection attempt');

        $row = $this->fetchByNama($injection);
        $this->assertNotNull($row,
            'OR injection string must be stored as a literal value');
        $this->assertSame($injection, $row['nama']);
    }

    /**
     * Multiple injection attempts can coexist as distinct literal rows.
     *
     * Requirements: 7.1
     */
    public function testMultipleInjectionAttemptsStoredSafely(): void
    {
        $payloads = [
            "'; DROP TABLE kriteria; --",
            "' OR 1=1 --",
            "'; INSERT INTO kriteria (nama,bobot,tipe) VALUES ('evil',1,'benefit'); --",
        ];

        foreach ($payloads as $payload) {
            $this->insertKriteria($payload);
        }

        // All three rows must exist — no SQL was executed
        $this->assertSame(3, $this->countRows('kriteria'),
            'All injection payloads must be stored as literal rows');

        foreach ($payloads as $payload) {
            $row = $this->fetchByNama($payload);
            $this->assertNotNull($row,
                "Payload must be stored verbatim: {$payload}");
            $this->assertSame($payload, $row['nama']);
        }
    }

    /**
     * A SELECT query using a prepared statement must not return extra rows
     * when the parameter contains an injection attempt.
     *
     * Requirements: 7.1
     */
    public function testPreparedSelectNotAffectedByInjection(): void
    {
        // Insert a legitimate row
        $this->insertKriteria('Harga', 0.40, 'benefit');

        // Attempt to inject an always-true condition into a SELECT
        $injection = "Harga' OR '1'='1";

        $stmt = $this->pdo->prepare(
            'SELECT * FROM kriteria WHERE nama = :nama'
        );
        $stmt->execute([':nama' => $injection]);
        $rows = $stmt->fetchAll();

        // The injection must NOT match the legitimate row — it should return 0 rows
        $this->assertCount(0, $rows,
            'Prepared SELECT must not return rows for an injection attempt that does not literally match');
    }

    /**
     * A DELETE query using a prepared statement must not delete all rows when
     * the parameter contains an injection attempt.
     *
     * Requirements: 7.1
     */
    public function testPreparedDeleteNotAffectedByInjection(): void
    {
        $this->insertKriteria('Harga',    0.40, 'benefit');
        $this->insertKriteria('Kualitas', 0.60, 'benefit');

        $this->assertSame(2, $this->countRows('kriteria'));

        // Attempt to inject an always-true condition into a DELETE
        $injection = "Harga' OR '1'='1";

        $stmt = $this->pdo->prepare('DELETE FROM kriteria WHERE nama = :nama');
        $stmt->execute([':nama' => $injection]);

        // Both rows must still exist — the injection did not delete everything
        $this->assertSame(2, $this->countRows('kriteria'),
            'Prepared DELETE must not delete rows when injection is used as parameter');
    }

    // =========================================================================
    // Section 2 — Numeric field injection prevention
    // =========================================================================

    /**
     * A non-numeric value in the bobot field must cause a PDO error or be
     * rejected before reaching the database.
     *
     * This test verifies that the application's sanitizeNumeric() helper
     * correctly rejects injection attempts in numeric fields.
     *
     * Requirements: 7.1
     */
    public function testSanitizeNumericRejectsSqlInjectionPayload(): void
    {
        $injection = "0.5; DROP TABLE kriteria; --";

        $result = sanitizeNumeric($injection);

        $this->assertFalse($result,
            'sanitizeNumeric() must reject SQL injection payloads in numeric fields');
    }
}
