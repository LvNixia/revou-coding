<?php

/**
 * Unit Tests — Bobot (Weight) Validation Helper
 *
 * Tests the validateBobotTotal() function defined in src/helpers/bobot.php.
 *
 * These tests use an in-memory SQLite database so they run without a MySQL
 * server and without touching the application database.
 *
 * Requirements: 2.5
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * @covers validateBobotTotal
 */
class BobotValidationTest extends TestCase
{
    private PDO $pdo;

    // ── Setup / Teardown ──────────────────────────────────────────────────────

    protected function setUp(): void
    {
        // Use an in-memory SQLite database — no MySQL required
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        // Create a minimal kriteria table that mirrors the MySQL schema
        $this->pdo->exec(
            'CREATE TABLE kriteria (
                id    INTEGER PRIMARY KEY AUTOINCREMENT,
                nama  TEXT    NOT NULL UNIQUE,
                bobot REAL    NOT NULL,
                tipe  TEXT    NOT NULL
            )'
        );
    }

    // ── Helper ────────────────────────────────────────────────────────────────

    /**
     * Insert a row into the in-memory kriteria table.
     */
    private function insertKriteria(string $nama, float $bobot, string $tipe = 'benefit'): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO kriteria (nama, bobot, tipe) VALUES (:nama, :bobot, :tipe)'
        );
        $stmt->execute([':nama' => $nama, ':bobot' => $bobot, ':tipe' => $tipe]);
    }

    // ── Tests ─────────────────────────────────────────────────────────────────

    /**
     * When the sum of bobot values equals exactly 1.00, validation must pass.
     *
     * Requirements: 2.5
     */
    public function testExactlyOnePointZeroIsValid(): void
    {
        $this->insertKriteria('Harga',    0.40);
        $this->insertKriteria('Kualitas', 0.35);
        $this->insertKriteria('Jarak',    0.25);

        $result = validateBobotTotal($this->pdo);

        $this->assertTrue(
            $result,
            'validateBobotTotal() must return true when total bobot = 1.00'
        );
    }

    /**
     * When the sum is within the ±0.001 tolerance (e.g. 0.9995), validation must pass.
     *
     * Requirements: 2.5
     */
    public function testWithinToleranceIsValid(): void
    {
        // 0.3333 + 0.3333 + 0.3334 = 1.0000 — but use values that sum to 0.9995
        $this->insertKriteria('A', 0.3332);
        $this->insertKriteria('B', 0.3332);
        $this->insertKriteria('C', 0.3331);
        // total = 0.9995, within ±0.001

        $result = validateBobotTotal($this->pdo);

        $this->assertTrue(
            $result,
            'validateBobotTotal() must return true when total bobot is within ±0.001 of 1.00'
        );
    }

    /**
     * When the sum is outside the tolerance (e.g. 0.80), validation must fail
     * and return the exact required error message.
     *
     * Requirements: 2.5
     */
    public function testBelowOnePointZeroIsInvalid(): void
    {
        $this->insertKriteria('Harga',    0.50);
        $this->insertKriteria('Kualitas', 0.30);
        // total = 0.80

        $result = validateBobotTotal($this->pdo);

        $this->assertSame(
            'Total bobot kriteria harus sama dengan 1.00',
            $result,
            'validateBobotTotal() must return the exact error message when total < 1.00'
        );
    }

    /**
     * When the sum exceeds 1.00 beyond tolerance (e.g. 1.20), validation must fail.
     *
     * Requirements: 2.5
     */
    public function testAboveOnePointZeroIsInvalid(): void
    {
        $this->insertKriteria('Harga',    0.60);
        $this->insertKriteria('Kualitas', 0.60);
        // total = 1.20

        $result = validateBobotTotal($this->pdo);

        $this->assertSame(
            'Total bobot kriteria harus sama dengan 1.00',
            $result,
            'validateBobotTotal() must return the exact error message when total > 1.00'
        );
    }

    /**
     * When there are no kriteria rows (empty table), the sum is 0.00 and
     * validation must fail.
     *
     * Requirements: 2.5
     */
    public function testEmptyTableIsInvalid(): void
    {
        // No rows inserted

        $result = validateBobotTotal($this->pdo);

        $this->assertSame(
            'Total bobot kriteria harus sama dengan 1.00',
            $result,
            'validateBobotTotal() must return the error message when the kriteria table is empty'
        );
    }

    /**
     * When there is exactly one kriteria with bobot = 1.00, validation must pass.
     *
     * Requirements: 2.5
     */
    public function testSingleKriteriaWithFullWeightIsValid(): void
    {
        $this->insertKriteria('Harga', 1.00);

        $result = validateBobotTotal($this->pdo);

        $this->assertTrue(
            $result,
            'validateBobotTotal() must return true when a single kriteria has bobot = 1.00'
        );
    }

    /**
     * The return value must be boolean true (not just truthy) on success.
     *
     * Requirements: 2.5
     */
    public function testReturnTypeIsTrueOnSuccess(): void
    {
        $this->insertKriteria('A', 0.50);
        $this->insertKriteria('B', 0.50);

        $result = validateBobotTotal($this->pdo);

        $this->assertSame(
            true,
            $result,
            'validateBobotTotal() must return exactly true (boolean) on success'
        );
    }

    /**
     * The return value must be a string (not false or null) on failure.
     *
     * Requirements: 2.5
     */
    public function testReturnTypeIsStringOnFailure(): void
    {
        $this->insertKriteria('A', 0.30);

        $result = validateBobotTotal($this->pdo);

        $this->assertIsString(
            $result,
            'validateBobotTotal() must return a string error message on failure'
        );
    }
}
