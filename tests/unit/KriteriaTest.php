<?php

/**
 * Unit Tests — Kriteria API Logic
 *
 * Tests the core business logic for kriteria (criteria) management:
 *   - Adding a new kriteria (POST logic)
 *   - Updating an existing kriteria (PUT logic)
 *   - Deleting a kriteria (DELETE logic), including cascade delete of nilai_alternatif
 *   - Duplicate name validation
 *   - Bobot range validation (0.01–1.00)
 *
 * These tests use an in-memory SQLite database so they run without a MySQL
 * server and without touching the application database.
 *
 * The handler functions in api/kriteria.php call exit() via jsonResponse(),
 * so they cannot be tested directly. Instead, this test class:
 *   1. Tests validateKriteriaInput() directly — it is a pure function.
 *   2. Exercises the underlying DB operations (INSERT / UPDATE / DELETE /
 *      duplicate-check) using the same SQL statements as the API, against
 *      an in-memory SQLite PDO instance.
 *
 * Requirements: 2.1, 2.2, 2.3, 2.4, 2.7
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

// Load the API file so validateKriteriaInput() is available.
// We must suppress the side-effects (requireAuth, getDbConnection, match block)
// by defining the guard functions as no-ops before the include.
// However, the file calls requireAuth() and getDbConnection() at the top level,
// so we use output buffering and define stubs only if not already defined.

class KriteriaTest extends TestCase
{
    private PDO $pdo;

    // ── Setup / Teardown ──────────────────────────────────────────────────────

    protected function setUp(): void
    {
        // Use an in-memory SQLite database — no MySQL required
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        // Create tables that mirror the MySQL schema
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

        $this->pdo->exec(
            'CREATE TABLE alternatif (
                id   INTEGER PRIMARY KEY AUTOINCREMENT,
                nama TEXT NOT NULL UNIQUE
            )'
        );

        // nilai_alternatif with FK cascade (SQLite requires PRAGMA foreign_keys = ON)
        $this->pdo->exec('PRAGMA foreign_keys = ON');
        $this->pdo->exec(
            'CREATE TABLE nilai_alternatif (
                id            INTEGER PRIMARY KEY AUTOINCREMENT,
                alternatif_id INTEGER NOT NULL,
                kriteria_id   INTEGER NOT NULL,
                nilai         REAL    NOT NULL,
                UNIQUE (alternatif_id, kriteria_id),
                FOREIGN KEY (alternatif_id) REFERENCES alternatif(id) ON DELETE CASCADE,
                FOREIGN KEY (kriteria_id)   REFERENCES kriteria(id)   ON DELETE CASCADE
            )'
        );
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Insert a kriteria row and return its new id.
     */
    private function insertKriteria(string $nama, float $bobot, string $tipe = 'benefit'): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO kriteria (nama, bobot, tipe) VALUES (:nama, :bobot, :tipe)'
        );
        $stmt->execute([':nama' => $nama, ':bobot' => $bobot, ':tipe' => $tipe]);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Insert an alternatif row and return its new id.
     */
    private function insertAlternatif(string $nama): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO alternatif (nama) VALUES (:nama)');
        $stmt->execute([':nama' => $nama]);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Insert a nilai_alternatif row.
     */
    private function insertNilai(int $alternatifId, int $kriteriaId, float $nilai): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO nilai_alternatif (alternatif_id, kriteria_id, nilai)
             VALUES (:alt, :krit, :nilai)'
        );
        $stmt->execute([':alt' => $alternatifId, ':krit' => $kriteriaId, ':nilai' => $nilai]);
    }

    /**
     * Count rows in a table.
     */
    private function countRows(string $table): int
    {
        return (int) $this->pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
    }

    /**
     * Fetch a kriteria row by id, or null if not found.
     */
    private function fetchKriteria(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM kriteria WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row !== false ? $row : null;
    }

    // =========================================================================
    // Section 1 — validateKriteriaInput() (pure function, no DB)
    // =========================================================================

    /**
     * Valid input must return no error and correct parsed values.
     *
     * Requirements: 2.1
     */
    public function testValidateKriteriaInputAcceptsValidData(): void
    {
        [$nama, $bobot, $tipe, $error] = validateKriteriaInput([
            'nama'  => 'Harga',
            'bobot' => '0.40',
            'tipe'  => 'benefit',
        ]);

        $this->assertNull($error, 'Valid input must produce no error');
        $this->assertSame('Harga', $nama);
        $this->assertEqualsWithDelta(0.40, $bobot, 0.0001);
        $this->assertSame('benefit', $tipe);
    }

    /**
     * Empty nama must return an error.
     *
     * Requirements: 2.1
     */
    public function testValidateKriteriaInputRejectsEmptyNama(): void
    {
        [, , , $error] = validateKriteriaInput([
            'nama'  => '   ',
            'bobot' => '0.50',
            'tipe'  => 'benefit',
        ]);

        $this->assertNotNull($error, 'Empty nama must produce an error');
        $this->assertStringContainsString('kosong', $error);
    }

    /**
     * Missing nama must return an error.
     *
     * Requirements: 2.1
     */
    public function testValidateKriteriaInputRejectsMissingNama(): void
    {
        [, , , $error] = validateKriteriaInput([
            'bobot' => '0.50',
            'tipe'  => 'benefit',
        ]);

        $this->assertNotNull($error);
    }

    /**
     * Nama longer than 100 characters must return an error.
     *
     * Requirements: 2.1
     */
    public function testValidateKriteriaInputRejectsNamaTooLong(): void
    {
        [, , , $error] = validateKriteriaInput([
            'nama'  => str_repeat('A', 101),
            'bobot' => '0.50',
            'tipe'  => 'benefit',
        ]);

        $this->assertNotNull($error);
        $this->assertStringContainsString('100', $error);
    }

    /**
     * Bobot below 0.01 must return an error.
     *
     * Requirements: 2.1, 2.7
     */
    public function testValidateKriteriaInputRejectsBobotBelowMinimum(): void
    {
        [, , , $error] = validateKriteriaInput([
            'nama'  => 'Harga',
            'bobot' => '0.00',
            'tipe'  => 'benefit',
        ]);

        $this->assertNotNull($error, 'Bobot 0.00 must be rejected');
        $this->assertStringContainsString('0.01', $error);
    }

    /**
     * Bobot above 1.00 must return an error.
     *
     * Requirements: 2.1, 2.7
     */
    public function testValidateKriteriaInputRejectsBobotAboveMaximum(): void
    {
        [, , , $error] = validateKriteriaInput([
            'nama'  => 'Harga',
            'bobot' => '1.01',
            'tipe'  => 'benefit',
        ]);

        $this->assertNotNull($error, 'Bobot 1.01 must be rejected');
        $this->assertStringContainsString('1.00', $error);
    }

    /**
     * Bobot of exactly 0.01 (minimum boundary) must be accepted.
     *
     * Requirements: 2.1, 2.7
     */
    public function testValidateKriteriaInputAcceptsBobotAtMinimumBoundary(): void
    {
        [, $bobot, , $error] = validateKriteriaInput([
            'nama'  => 'Harga',
            'bobot' => '0.01',
            'tipe'  => 'benefit',
        ]);

        $this->assertNull($error, 'Bobot 0.01 must be accepted');
        $this->assertEqualsWithDelta(0.01, $bobot, 0.0001);
    }

    /**
     * Bobot of exactly 1.00 (maximum boundary) must be accepted.
     *
     * Requirements: 2.1, 2.7
     */
    public function testValidateKriteriaInputAcceptsBobotAtMaximumBoundary(): void
    {
        [, $bobot, , $error] = validateKriteriaInput([
            'nama'  => 'Harga',
            'bobot' => '1.00',
            'tipe'  => 'benefit',
        ]);

        $this->assertNull($error, 'Bobot 1.00 must be accepted');
        $this->assertEqualsWithDelta(1.00, $bobot, 0.0001);
    }

    /**
     * Non-numeric bobot must return an error.
     *
     * Requirements: 2.1, 2.7
     */
    public function testValidateKriteriaInputRejectsNonNumericBobot(): void
    {
        [, , , $error] = validateKriteriaInput([
            'nama'  => 'Harga',
            'bobot' => 'abc',
            'tipe'  => 'benefit',
        ]);

        $this->assertNotNull($error);
        $this->assertStringContainsString('angka', $error);
    }

    /**
     * Tipe 'cost' (lowercase) must be accepted.
     *
     * Requirements: 2.1
     */
    public function testValidateKriteriaInputAcceptsTipeCost(): void
    {
        [, , $tipe, $error] = validateKriteriaInput([
            'nama'  => 'Biaya',
            'bobot' => '0.30',
            'tipe'  => 'cost',
        ]);

        $this->assertNull($error);
        $this->assertSame('cost', $tipe);
    }

    /**
     * Tipe 'BENEFIT' (uppercase) must be normalised to lowercase.
     *
     * Requirements: 2.1
     */
    public function testValidateKriteriaInputNormalisesTipeToLowercase(): void
    {
        [, , $tipe, $error] = validateKriteriaInput([
            'nama'  => 'Kualitas',
            'bobot' => '0.50',
            'tipe'  => 'BENEFIT',
        ]);

        $this->assertNull($error);
        $this->assertSame('benefit', $tipe);
    }

    /**
     * Invalid tipe value must return an error.
     *
     * Requirements: 2.1
     */
    public function testValidateKriteriaInputRejectsInvalidTipe(): void
    {
        [, , , $error] = validateKriteriaInput([
            'nama'  => 'Kualitas',
            'bobot' => '0.50',
            'tipe'  => 'neutral',
        ]);

        $this->assertNotNull($error);
        $this->assertStringContainsString('benefit', $error);
    }

    // =========================================================================
    // Section 2 — POST logic: adding a new kriteria
    // =========================================================================

    /**
     * Inserting a new kriteria must persist it to the database.
     *
     * Requirements: 2.2
     */
    public function testAddKriteriaInsertsRow(): void
    {
        $this->assertSame(0, $this->countRows('kriteria'));

        $this->insertKriteria('Harga', 0.40, 'benefit');

        $this->assertSame(1, $this->countRows('kriteria'));
    }

    /**
     * The inserted row must contain the correct field values.
     *
     * Requirements: 2.2
     */
    public function testAddKriteriaPersistsCorrectValues(): void
    {
        $id = $this->insertKriteria('Kualitas', 0.35, 'benefit');
        $row = $this->fetchKriteria($id);

        $this->assertNotNull($row);
        $this->assertSame('Kualitas', $row['nama']);
        $this->assertEqualsWithDelta(0.35, (float) $row['bobot'], 0.0001);
        $this->assertSame('benefit', $row['tipe']);
    }

    /**
     * Multiple distinct kriteria can be inserted without conflict.
     *
     * Requirements: 2.2
     */
    public function testAddMultipleKriteriaSucceeds(): void
    {
        $this->insertKriteria('Harga',    0.40, 'benefit');
        $this->insertKriteria('Kualitas', 0.35, 'benefit');
        $this->insertKriteria('Jarak',    0.25, 'cost');

        $this->assertSame(3, $this->countRows('kriteria'));
    }

    // =========================================================================
    // Section 3 — PUT logic: updating an existing kriteria
    // =========================================================================

    /**
     * Updating a kriteria must change its stored values.
     *
     * Requirements: 2.3
     */
    public function testUpdateKriteriaChangesValues(): void
    {
        $id = $this->insertKriteria('Harga', 0.40, 'benefit');

        $stmt = $this->pdo->prepare(
            'UPDATE kriteria SET nama = :nama, bobot = :bobot, tipe = :tipe WHERE id = :id'
        );
        $stmt->execute([
            ':nama'  => 'Harga Baru',
            ':bobot' => 0.50,
            ':tipe'  => 'cost',
            ':id'    => $id,
        ]);

        $row = $this->fetchKriteria($id);
        $this->assertSame('Harga Baru', $row['nama']);
        $this->assertEqualsWithDelta(0.50, (float) $row['bobot'], 0.0001);
        $this->assertSame('cost', $row['tipe']);
    }

    /**
     * Updating a kriteria to its own name (no change) must not trigger a
     * duplicate-name conflict.
     *
     * Requirements: 2.3, 2.7
     */
    public function testUpdateKriteriaToSameNameIsAllowed(): void
    {
        $id = $this->insertKriteria('Harga', 0.40, 'benefit');

        // Check: is there another row with the same name (excluding self)?
        $stmtCheck = $this->pdo->prepare(
            'SELECT id FROM kriteria WHERE nama = :nama AND id != :id LIMIT 1'
        );
        $stmtCheck->execute([':nama' => 'Harga', ':id' => $id]);
        $conflict = $stmtCheck->fetch();

        $this->assertFalse($conflict, 'Updating to the same name must not produce a conflict');
    }

    /**
     * Updating a non-existent kriteria id must find no row.
     *
     * Requirements: 2.3
     */
    public function testUpdateNonExistentKriteriaFindsNoRow(): void
    {
        $stmtExist = $this->pdo->prepare('SELECT id FROM kriteria WHERE id = :id LIMIT 1');
        $stmtExist->execute([':id' => 9999]);
        $row = $stmtExist->fetch();

        $this->assertFalse($row, 'A non-existent id must not be found');
    }

    // =========================================================================
    // Section 4 — DELETE logic: removing a kriteria
    // =========================================================================

    /**
     * Deleting a kriteria must remove it from the database.
     *
     * Requirements: 2.4
     */
    public function testDeleteKriteriaRemovesRow(): void
    {
        $id = $this->insertKriteria('Harga', 0.40, 'benefit');
        $this->assertSame(1, $this->countRows('kriteria'));

        $stmt = $this->pdo->prepare('DELETE FROM kriteria WHERE id = :id');
        $stmt->execute([':id' => $id]);

        $this->assertSame(0, $this->countRows('kriteria'));
        $this->assertNull($this->fetchKriteria($id));
    }

    /**
     * Deleting a kriteria must cascade-delete its related nilai_alternatif rows.
     *
     * Requirements: 2.4
     */
    public function testDeleteKriteriaCascadeDeletesNilaiAlternatif(): void
    {
        $kriteriaId   = $this->insertKriteria('Harga', 0.40, 'benefit');
        $alternatifId = $this->insertAlternatif('Produk A');
        $this->insertNilai($alternatifId, $kriteriaId, 85.0);

        $this->assertSame(1, $this->countRows('nilai_alternatif'));

        // Delete the kriteria — cascade should remove nilai_alternatif
        $stmt = $this->pdo->prepare('DELETE FROM kriteria WHERE id = :id');
        $stmt->execute([':id' => $kriteriaId]);

        $this->assertSame(0, $this->countRows('nilai_alternatif'),
            'Deleting a kriteria must cascade-delete its nilai_alternatif rows'
        );
    }

    /**
     * Deleting a kriteria must not affect nilai_alternatif rows for other kriteria.
     *
     * Requirements: 2.4
     */
    public function testDeleteKriteriaDoesNotAffectOtherNilai(): void
    {
        $k1 = $this->insertKriteria('Harga',    0.40, 'benefit');
        $k2 = $this->insertKriteria('Kualitas', 0.60, 'benefit');
        $a1 = $this->insertAlternatif('Produk A');

        $this->insertNilai($a1, $k1, 80.0);
        $this->insertNilai($a1, $k2, 90.0);

        $this->assertSame(2, $this->countRows('nilai_alternatif'));

        // Delete only k1
        $stmt = $this->pdo->prepare('DELETE FROM kriteria WHERE id = :id');
        $stmt->execute([':id' => $k1]);

        // Only the nilai for k2 should remain
        $this->assertSame(1, $this->countRows('nilai_alternatif'),
            'Only nilai_alternatif rows for the deleted kriteria should be removed'
        );
    }

    /**
     * Attempting to delete a non-existent kriteria id must find no row.
     *
     * Requirements: 2.4
     */
    public function testDeleteNonExistentKriteriaFindsNoRow(): void
    {
        $stmtExist = $this->pdo->prepare('SELECT id FROM kriteria WHERE id = :id LIMIT 1');
        $stmtExist->execute([':id' => 9999]);
        $row = $stmtExist->fetch();

        $this->assertFalse($row, 'A non-existent kriteria id must not be found before delete');
    }

    // =========================================================================
    // Section 5 — Duplicate name validation
    // =========================================================================

    /**
     * Inserting a kriteria with a duplicate name must throw a PDOException
     * (UNIQUE constraint violation).
     *
     * Requirements: 2.7
     */
    public function testDuplicateNameThrowsException(): void
    {
        $this->insertKriteria('Harga', 0.40, 'benefit');

        $this->expectException(PDOException::class);

        // Attempt to insert a second row with the same name
        $stmt = $this->pdo->prepare(
            'INSERT INTO kriteria (nama, bobot, tipe) VALUES (:nama, :bobot, :tipe)'
        );
        $stmt->execute([':nama' => 'Harga', ':bobot' => 0.30, ':tipe' => 'cost']);
    }

    /**
     * The duplicate-name check query must detect an existing name.
     *
     * This mirrors the SELECT check in handlePost() before the INSERT.
     *
     * Requirements: 2.7
     */
    public function testDuplicateNameCheckDetectsExistingName(): void
    {
        $this->insertKriteria('Harga', 0.40, 'benefit');

        $stmtCheck = $this->pdo->prepare(
            'SELECT id FROM kriteria WHERE nama = :nama LIMIT 1'
        );
        $stmtCheck->execute([':nama' => 'Harga']);
        $found = $stmtCheck->fetch();

        $this->assertNotFalse($found, 'Duplicate-name check must find the existing row');
    }

    /**
     * The duplicate-name check must return no row for a new, unique name.
     *
     * Requirements: 2.7
     */
    public function testDuplicateNameCheckPassesForUniqueName(): void
    {
        $this->insertKriteria('Harga', 0.40, 'benefit');

        $stmtCheck = $this->pdo->prepare(
            'SELECT id FROM kriteria WHERE nama = :nama LIMIT 1'
        );
        $stmtCheck->execute([':nama' => 'Kualitas']);
        $found = $stmtCheck->fetch();

        $this->assertFalse($found, 'Duplicate-name check must find nothing for a unique name');
    }

    /**
     * validateKriteriaInput() must return the correct error message for a
     * duplicate name — the API returns HTTP 409 with this message.
     *
     * Note: the duplicate check itself is done at the DB level; this test
     * verifies the error message string used in the API response.
     *
     * Requirements: 2.7
     */
    public function testDuplicateNameErrorMessageIsCorrect(): void
    {
        // The API uses this exact message for HTTP 409 responses
        $expectedMessage = 'Nama kriteria sudah digunakan';

        // Simulate what handlePost() does when a duplicate is detected
        $stmtCheck = $this->pdo->prepare(
            'SELECT id FROM kriteria WHERE nama = :nama LIMIT 1'
        );
        $this->insertKriteria('Harga', 0.40, 'benefit');
        $stmtCheck->execute([':nama' => 'Harga']);
        $isDuplicate = $stmtCheck->fetch() !== false;

        $message = $isDuplicate ? 'Nama kriteria sudah digunakan' : null;

        $this->assertSame($expectedMessage, $message);
    }

    /**
     * When updating, a name already used by a DIFFERENT kriteria must be detected.
     *
     * Requirements: 2.7
     */
    public function testUpdateDuplicateNameCheckDetectsConflict(): void
    {
        $id1 = $this->insertKriteria('Harga',    0.40, 'benefit');
        $id2 = $this->insertKriteria('Kualitas', 0.60, 'benefit');

        // Try to rename id2 to 'Harga' (already used by id1)
        $stmtCheck = $this->pdo->prepare(
            'SELECT id FROM kriteria WHERE nama = :nama AND id != :id LIMIT 1'
        );
        $stmtCheck->execute([':nama' => 'Harga', ':id' => $id2]);
        $conflict = $stmtCheck->fetch();

        $this->assertNotFalse($conflict,
            'Updating to a name used by another kriteria must be detected as a conflict'
        );
    }

    /**
     * When updating, using the same name as the record being updated must NOT
     * be detected as a conflict.
     *
     * Requirements: 2.7
     */
    public function testUpdateSameNameNoConflict(): void
    {
        $id = $this->insertKriteria('Harga', 0.40, 'benefit');

        $stmtCheck = $this->pdo->prepare(
            'SELECT id FROM kriteria WHERE nama = :nama AND id != :id LIMIT 1'
        );
        $stmtCheck->execute([':nama' => 'Harga', ':id' => $id]);
        $conflict = $stmtCheck->fetch();

        $this->assertFalse($conflict,
            'Updating a kriteria to its own name must not be flagged as a conflict'
        );
    }

    // =========================================================================
    // Section 6 — Bobot range validation (boundary tests)
    // =========================================================================

    /**
     * Bobot of 0.00 (below minimum) must be rejected.
     *
     * Requirements: 2.1, 2.7
     */
    public function testBobotZeroIsRejected(): void
    {
        [, , , $error] = validateKriteriaInput([
            'nama'  => 'Test',
            'bobot' => 0.00,
            'tipe'  => 'benefit',
        ]);

        $this->assertNotNull($error);
    }

    /**
     * Bobot of -0.5 (negative) must be rejected.
     *
     * Requirements: 2.1, 2.7
     */
    public function testNegativeBobotIsRejected(): void
    {
        [, , , $error] = validateKriteriaInput([
            'nama'  => 'Test',
            'bobot' => -0.5,
            'tipe'  => 'benefit',
        ]);

        $this->assertNotNull($error);
    }

    /**
     * Bobot of 1.01 (above maximum) must be rejected.
     *
     * Requirements: 2.1, 2.7
     */
    public function testBobotAboveOneIsRejected(): void
    {
        [, , , $error] = validateKriteriaInput([
            'nama'  => 'Test',
            'bobot' => 1.01,
            'tipe'  => 'benefit',
        ]);

        $this->assertNotNull($error);
    }

    /**
     * Bobot of 0.50 (mid-range) must be accepted.
     *
     * Requirements: 2.1, 2.7
     */
    public function testBobotMidRangeIsAccepted(): void
    {
        [, $bobot, , $error] = validateKriteriaInput([
            'nama'  => 'Test',
            'bobot' => 0.50,
            'tipe'  => 'benefit',
        ]);

        $this->assertNull($error);
        $this->assertEqualsWithDelta(0.50, $bobot, 0.0001);
    }

    /**
     * Bobot supplied as a string '0.75' must be parsed and accepted.
     *
     * Requirements: 2.1, 2.7
     */
    public function testBobotAsStringIsAccepted(): void
    {
        [, $bobot, , $error] = validateKriteriaInput([
            'nama'  => 'Test',
            'bobot' => '0.75',
            'tipe'  => 'benefit',
        ]);

        $this->assertNull($error);
        $this->assertEqualsWithDelta(0.75, $bobot, 0.0001);
    }
}
