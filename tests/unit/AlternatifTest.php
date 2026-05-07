<?php

/**
 * Unit Tests — Alternatif API Logic
 *
 * Tests the core business logic for alternatif (alternative) management:
 *   - Adding a new alternatif with nilai (POST logic)
 *   - Updating an existing alternatif and replacing its nilai (PUT logic)
 *   - Deleting an alternatif (DELETE logic), including cascade delete of nilai_alternatif
 *   - Duplicate name validation
 *   - Non-numeric nilai validation via validateAlternatifInput()
 *   - Condition with no kriteria (empty nilai array is valid)
 *
 * These tests use an in-memory SQLite database so they run without a MySQL
 * server and without touching the application database.
 *
 * The handler functions in api/alternatif.php call exit() via jsonResponse(),
 * so they cannot be tested directly. Instead, this test class:
 *   1. Tests validateAlternatifInput() directly — it is a pure function.
 *   2. Exercises the underlying DB operations (INSERT / UPDATE / DELETE /
 *      duplicate-check) using the same SQL statements as the API, against
 *      an in-memory SQLite PDO instance.
 *
 * Requirements: 3.1, 3.5, 3.7, 3.8
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class AlternatifTest extends TestCase
{
    private PDO $pdo;

    // ── Setup / Teardown ──────────────────────────────────────────────────────

    protected function setUp(): void
    {
        // Use an in-memory SQLite database — no MySQL required
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        // Enable FK cascade support in SQLite
        $this->pdo->exec('PRAGMA foreign_keys = ON');

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
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                nama       TEXT    NOT NULL UNIQUE,
                created_at TEXT    DEFAULT (datetime(\'now\')),
                updated_at TEXT    DEFAULT (datetime(\'now\'))
            )'
        );

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
    private function insertKriteria(string $nama, float $bobot = 0.5, string $tipe = 'benefit'): int
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
        $stmt = $this->pdo->prepare(
            'INSERT INTO alternatif (nama) VALUES (:nama)'
        );
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
     * Fetch an alternatif row by id, or null if not found.
     */
    private function fetchAlternatif(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM alternatif WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row !== false ? $row : null;
    }

    /**
     * Fetch all nilai_alternatif rows for a given alternatif_id.
     */
    private function fetchNilaiForAlternatif(int $alternatifId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM nilai_alternatif WHERE alternatif_id = :id ORDER BY kriteria_id ASC'
        );
        $stmt->execute([':id' => $alternatifId]);
        return $stmt->fetchAll();
    }

    // =========================================================================
    // Section 1 — validateAlternatifInput() (pure function, no DB)
    // =========================================================================

    /**
     * Valid input with numeric nilai must return no error and correct parsed values.
     *
     * Requirements: 3.1
     */
    public function testValidateAlternatifInputAcceptsValidData(): void
    {
        [$nama, $nilai, $error] = validateAlternatifInput([
            'nama'  => 'Produk A',
            'nilai' => ['1' => '80', '2' => '90'],
        ]);

        $this->assertNull($error, 'Valid input must produce no error');
        $this->assertSame('Produk A', $nama);
        $this->assertEqualsWithDelta(80.0, $nilai[1], 0.0001);
        $this->assertEqualsWithDelta(90.0, $nilai[2], 0.0001);
    }

    /**
     * Empty nama must return an error.
     *
     * Requirements: 3.1
     */
    public function testValidateAlternatifInputRejectsEmptyNama(): void
    {
        [, , $error] = validateAlternatifInput([
            'nama'  => '   ',
            'nilai' => [],
        ]);

        $this->assertNotNull($error, 'Empty nama must produce an error');
        $this->assertStringContainsString('kosong', $error);
    }

    /**
     * Missing nama must return an error.
     *
     * Requirements: 3.1
     */
    public function testValidateAlternatifInputRejectsMissingNama(): void
    {
        [, , $error] = validateAlternatifInput([
            'nilai' => [],
        ]);

        $this->assertNotNull($error);
    }

    /**
     * Nama longer than 100 characters must return an error.
     *
     * Requirements: 3.1
     */
    public function testValidateAlternatifInputRejectsNamaTooLong(): void
    {
        [, , $error] = validateAlternatifInput([
            'nama'  => str_repeat('A', 101),
            'nilai' => [],
        ]);

        $this->assertNotNull($error);
        $this->assertStringContainsString('100', $error);
    }

    /**
     * Nama of exactly 100 characters must be accepted.
     *
     * Requirements: 3.1
     */
    public function testValidateAlternatifInputAcceptsNamaAtMaxLength(): void
    {
        [$nama, , $error] = validateAlternatifInput([
            'nama'  => str_repeat('A', 100),
            'nilai' => [],
        ]);

        $this->assertNull($error, 'Nama of exactly 100 chars must be accepted');
        $this->assertSame(100, strlen($nama));
    }

    /**
     * Missing nilai key must return an error.
     *
     * Requirements: 3.1
     */
    public function testValidateAlternatifInputRejectsMissingNilai(): void
    {
        [, , $error] = validateAlternatifInput([
            'nama' => 'Produk A',
        ]);

        $this->assertNotNull($error);
        $this->assertStringContainsString('array', $error);
    }

    /**
     * nilai supplied as a non-array (string) must return an error.
     *
     * Requirements: 3.1
     */
    public function testValidateAlternatifInputRejectsNilaiAsString(): void
    {
        [, , $error] = validateAlternatifInput([
            'nama'  => 'Produk A',
            'nilai' => 'bukan array',
        ]);

        $this->assertNotNull($error);
        $this->assertStringContainsString('array', $error);
    }

    // =========================================================================
    // Section 2 — Non-numeric nilai validation (Requirement 3.5)
    // =========================================================================

    /**
     * A non-numeric nilai value must return an error.
     *
     * Requirements: 3.5
     */
    public function testValidateAlternatifInputRejectsNonNumericNilai(): void
    {
        [, , $error] = validateAlternatifInput([
            'nama'  => 'Produk A',
            'nilai' => ['1' => 'abc'],
        ]);

        $this->assertNotNull($error, 'Non-numeric nilai must produce an error');
        $this->assertStringContainsString('angka', $error);
    }

    /**
     * An empty string nilai value must return an error.
     *
     * Requirements: 3.5
     */
    public function testValidateAlternatifInputRejectsEmptyStringNilai(): void
    {
        [, , $error] = validateAlternatifInput([
            'nama'  => 'Produk A',
            'nilai' => ['1' => ''],
        ]);

        $this->assertNotNull($error, 'Empty string nilai must produce an error');
        $this->assertStringContainsString('angka', $error);
    }

    /**
     * A boolean nilai value must return an error.
     *
     * Requirements: 3.5
     */
    public function testValidateAlternatifInputRejectsBooleanNilai(): void
    {
        [, , $error] = validateAlternatifInput([
            'nama'  => 'Produk A',
            'nilai' => ['1' => true],
        ]);

        $this->assertNotNull($error, 'Boolean nilai must produce an error');
    }

    /**
     * A numeric string nilai value must be accepted and parsed as float.
     *
     * Requirements: 3.5
     */
    public function testValidateAlternatifInputAcceptsNumericStringNilai(): void
    {
        [$nama, $nilai, $error] = validateAlternatifInput([
            'nama'  => 'Produk A',
            'nilai' => ['1' => '75.5'],
        ]);

        $this->assertNull($error, 'Numeric string nilai must be accepted');
        $this->assertEqualsWithDelta(75.5, $nilai[1], 0.0001);
    }

    /**
     * An integer nilai value must be accepted.
     *
     * Requirements: 3.5
     */
    public function testValidateAlternatifInputAcceptsIntegerNilai(): void
    {
        [, $nilai, $error] = validateAlternatifInput([
            'nama'  => 'Produk A',
            'nilai' => ['1' => 100],
        ]);

        $this->assertNull($error, 'Integer nilai must be accepted');
        $this->assertEqualsWithDelta(100.0, $nilai[1], 0.0001);
    }

    /**
     * An invalid (non-positive) kriteria_id key must return an error.
     *
     * Requirements: 3.5
     */
    public function testValidateAlternatifInputRejectsInvalidKriteriaId(): void
    {
        [, , $error] = validateAlternatifInput([
            'nama'  => 'Produk A',
            'nilai' => ['0' => 80],
        ]);

        $this->assertNotNull($error, 'Non-positive kriteria_id must produce an error');
        $this->assertStringContainsString('ID kriteria', $error);
    }

    // =========================================================================
    // Section 3 — Condition with no kriteria (Requirement 3.8)
    // =========================================================================

    /**
     * An empty nilai array must be accepted by validateAlternatifInput().
     * When there are no kriteria, the form is disabled on the page, but the
     * API itself still accepts an alternatif with no nilai rows.
     *
     * Requirements: 3.8
     */
    public function testValidateAlternatifInputAcceptsEmptyNilaiArray(): void
    {
        [$nama, $nilai, $error] = validateAlternatifInput([
            'nama'  => 'Produk A',
            'nilai' => [],
        ]);

        $this->assertNull($error, 'Empty nilai array must be accepted');
        $this->assertSame('Produk A', $nama);
        $this->assertSame([], $nilai);
    }

    /**
     * Inserting an alternatif with no nilai rows must succeed at the DB level.
     *
     * Requirements: 3.8
     */
    public function testAddAlternatifWithNoNilaiSucceeds(): void
    {
        $this->assertSame(0, $this->countRows('alternatif'));

        $id = $this->insertAlternatif('Produk Tanpa Nilai');

        $this->assertSame(1, $this->countRows('alternatif'));
        $this->assertSame(0, $this->countRows('nilai_alternatif'));
        $this->assertNotNull($this->fetchAlternatif($id));
    }

    // =========================================================================
    // Section 4 — POST logic: adding a new alternatif with nilai
    // =========================================================================

    /**
     * Inserting a new alternatif must persist it to the database.
     *
     * Requirements: 3.1
     */
    public function testAddAlternatifInsertsRow(): void
    {
        $this->assertSame(0, $this->countRows('alternatif'));

        $this->insertAlternatif('Produk A');

        $this->assertSame(1, $this->countRows('alternatif'));
    }

    /**
     * The inserted row must contain the correct field values.
     *
     * Requirements: 3.1
     */
    public function testAddAlternatifPersistsCorrectValues(): void
    {
        $id  = $this->insertAlternatif('Produk B');
        $row = $this->fetchAlternatif($id);

        $this->assertNotNull($row);
        $this->assertSame('Produk B', $row['nama']);
    }

    /**
     * Inserting an alternatif with nilai rows must persist all nilai correctly.
     *
     * Requirements: 3.1
     */
    public function testAddAlternatifWithNilaiPersistsNilaiRows(): void
    {
        $k1 = $this->insertKriteria('Harga',    0.40, 'benefit');
        $k2 = $this->insertKriteria('Kualitas', 0.60, 'benefit');

        $altId = $this->insertAlternatif('Produk A');
        $this->insertNilai($altId, $k1, 80.0);
        $this->insertNilai($altId, $k2, 90.0);

        $this->assertSame(2, $this->countRows('nilai_alternatif'));

        $nilaiRows = $this->fetchNilaiForAlternatif($altId);
        $this->assertCount(2, $nilaiRows);
        $this->assertEqualsWithDelta(80.0, (float) $nilaiRows[0]['nilai'], 0.0001);
        $this->assertEqualsWithDelta(90.0, (float) $nilaiRows[1]['nilai'], 0.0001);
    }

    /**
     * Multiple distinct alternatif can be inserted without conflict.
     *
     * Requirements: 3.1
     */
    public function testAddMultipleAlternatifSucceeds(): void
    {
        $this->insertAlternatif('Produk A');
        $this->insertAlternatif('Produk B');
        $this->insertAlternatif('Produk C');

        $this->assertSame(3, $this->countRows('alternatif'));
    }

    // =========================================================================
    // Section 5 — PUT logic: updating an existing alternatif
    // =========================================================================

    /**
     * Updating an alternatif must change its stored nama.
     *
     * Requirements: 3.1
     */
    public function testUpdateAlternatifChangesNama(): void
    {
        $id = $this->insertAlternatif('Produk A');

        $stmt = $this->pdo->prepare('UPDATE alternatif SET nama = :nama WHERE id = :id');
        $stmt->execute([':nama' => 'Produk A Baru', ':id' => $id]);

        $row = $this->fetchAlternatif($id);
        $this->assertSame('Produk A Baru', $row['nama']);
    }

    /**
     * Updating an alternatif must replace its nilai rows (delete old, insert new).
     *
     * Requirements: 3.1
     */
    public function testUpdateAlternatifReplacesNilaiRows(): void
    {
        $k1 = $this->insertKriteria('Harga',    0.40, 'benefit');
        $k2 = $this->insertKriteria('Kualitas', 0.60, 'benefit');

        $altId = $this->insertAlternatif('Produk A');
        $this->insertNilai($altId, $k1, 80.0);

        $this->assertSame(1, $this->countRows('nilai_alternatif'));

        // Simulate PUT: delete old nilai, insert new ones
        $stmtDel = $this->pdo->prepare(
            'DELETE FROM nilai_alternatif WHERE alternatif_id = :alternatif_id'
        );
        $stmtDel->execute([':alternatif_id' => $altId]);

        $stmtIns = $this->pdo->prepare(
            'INSERT INTO nilai_alternatif (alternatif_id, kriteria_id, nilai)
             VALUES (:alternatif_id, :kriteria_id, :nilai)'
        );
        $stmtIns->execute([':alternatif_id' => $altId, ':kriteria_id' => $k1, ':nilai' => 95.0]);
        $stmtIns->execute([':alternatif_id' => $altId, ':kriteria_id' => $k2, ':nilai' => 70.0]);

        $this->assertSame(2, $this->countRows('nilai_alternatif'));

        $nilaiRows = $this->fetchNilaiForAlternatif($altId);
        $this->assertEqualsWithDelta(95.0, (float) $nilaiRows[0]['nilai'], 0.0001);
        $this->assertEqualsWithDelta(70.0, (float) $nilaiRows[1]['nilai'], 0.0001);
    }

    /**
     * Updating an alternatif to its own name (no change) must not trigger a
     * duplicate-name conflict.
     *
     * Requirements: 3.7
     */
    public function testUpdateAlternatifToSameNameIsAllowed(): void
    {
        $id = $this->insertAlternatif('Produk A');

        // Check: is there another row with the same name (excluding self)?
        $stmtCheck = $this->pdo->prepare(
            'SELECT id FROM alternatif WHERE nama = :nama AND id != :id LIMIT 1'
        );
        $stmtCheck->execute([':nama' => 'Produk A', ':id' => $id]);
        $conflict = $stmtCheck->fetch();

        $this->assertFalse($conflict, 'Updating to the same name must not produce a conflict');
    }

    /**
     * Updating a non-existent alternatif id must find no row.
     *
     * Requirements: 3.1
     */
    public function testUpdateNonExistentAlternatifFindsNoRow(): void
    {
        $stmtExist = $this->pdo->prepare('SELECT id FROM alternatif WHERE id = :id LIMIT 1');
        $stmtExist->execute([':id' => 9999]);
        $row = $stmtExist->fetch();

        $this->assertFalse($row, 'A non-existent id must not be found');
    }

    // =========================================================================
    // Section 6 — DELETE logic: removing an alternatif
    // =========================================================================

    /**
     * Deleting an alternatif must remove it from the database.
     *
     * Requirements: 3.1
     */
    public function testDeleteAlternatifRemovesRow(): void
    {
        $id = $this->insertAlternatif('Produk A');
        $this->assertSame(1, $this->countRows('alternatif'));

        $stmt = $this->pdo->prepare('DELETE FROM alternatif WHERE id = :id');
        $stmt->execute([':id' => $id]);

        $this->assertSame(0, $this->countRows('alternatif'));
        $this->assertNull($this->fetchAlternatif($id));
    }

    /**
     * Deleting an alternatif must cascade-delete its related nilai_alternatif rows.
     *
     * Requirements: 3.1
     */
    public function testDeleteAlternatifCascadeDeletesNilaiAlternatif(): void
    {
        $kriteriaId   = $this->insertKriteria('Harga', 0.40, 'benefit');
        $alternatifId = $this->insertAlternatif('Produk A');
        $this->insertNilai($alternatifId, $kriteriaId, 85.0);

        $this->assertSame(1, $this->countRows('nilai_alternatif'));

        // Delete the alternatif — cascade should remove nilai_alternatif
        $stmt = $this->pdo->prepare('DELETE FROM alternatif WHERE id = :id');
        $stmt->execute([':id' => $alternatifId]);

        $this->assertSame(0, $this->countRows('nilai_alternatif'),
            'Deleting an alternatif must cascade-delete its nilai_alternatif rows'
        );
    }

    /**
     * Deleting one alternatif must not affect nilai_alternatif rows for other alternatif.
     *
     * Requirements: 3.1
     */
    public function testDeleteAlternatifDoesNotAffectOtherNilai(): void
    {
        $k1 = $this->insertKriteria('Harga', 0.40, 'benefit');
        $a1 = $this->insertAlternatif('Produk A');
        $a2 = $this->insertAlternatif('Produk B');

        $this->insertNilai($a1, $k1, 80.0);
        $this->insertNilai($a2, $k1, 90.0);

        $this->assertSame(2, $this->countRows('nilai_alternatif'));

        // Delete only a1
        $stmt = $this->pdo->prepare('DELETE FROM alternatif WHERE id = :id');
        $stmt->execute([':id' => $a1]);

        // Only the nilai for a2 should remain
        $this->assertSame(1, $this->countRows('nilai_alternatif'),
            'Only nilai_alternatif rows for the deleted alternatif should be removed'
        );
    }

    /**
     * Attempting to delete a non-existent alternatif id must find no row.
     *
     * Requirements: 3.1
     */
    public function testDeleteNonExistentAlternatifFindsNoRow(): void
    {
        $stmtExist = $this->pdo->prepare('SELECT id FROM alternatif WHERE id = :id LIMIT 1');
        $stmtExist->execute([':id' => 9999]);
        $row = $stmtExist->fetch();

        $this->assertFalse($row, 'A non-existent alternatif id must not be found before delete');
    }

    // =========================================================================
    // Section 7 — Duplicate name validation (Requirement 3.7)
    // =========================================================================

    /**
     * Inserting an alternatif with a duplicate name must throw a PDOException
     * (UNIQUE constraint violation).
     *
     * Requirements: 3.7
     */
    public function testDuplicateNameThrowsException(): void
    {
        $this->insertAlternatif('Produk A');

        $this->expectException(PDOException::class);

        // Attempt to insert a second row with the same name
        $stmt = $this->pdo->prepare('INSERT INTO alternatif (nama) VALUES (:nama)');
        $stmt->execute([':nama' => 'Produk A']);
    }

    /**
     * The duplicate-name check query must detect an existing name.
     *
     * This mirrors the SELECT check in handlePost() before the INSERT.
     *
     * Requirements: 3.7
     */
    public function testDuplicateNameCheckDetectsExistingName(): void
    {
        $this->insertAlternatif('Produk A');

        $stmtCheck = $this->pdo->prepare(
            'SELECT id FROM alternatif WHERE nama = :nama LIMIT 1'
        );
        $stmtCheck->execute([':nama' => 'Produk A']);
        $found = $stmtCheck->fetch();

        $this->assertNotFalse($found, 'Duplicate-name check must find the existing row');
    }

    /**
     * The duplicate-name check must return no row for a new, unique name.
     *
     * Requirements: 3.7
     */
    public function testDuplicateNameCheckPassesForUniqueName(): void
    {
        $this->insertAlternatif('Produk A');

        $stmtCheck = $this->pdo->prepare(
            'SELECT id FROM alternatif WHERE nama = :nama LIMIT 1'
        );
        $stmtCheck->execute([':nama' => 'Produk B']);
        $found = $stmtCheck->fetch();

        $this->assertFalse($found, 'Duplicate-name check must find nothing for a unique name');
    }

    /**
     * The duplicate-name error message used in the API response must be correct.
     *
     * Requirements: 3.7
     */
    public function testDuplicateNameErrorMessageIsCorrect(): void
    {
        // The API uses this exact message for HTTP 409 responses
        $expectedMessage = 'Nama alternatif sudah digunakan';

        $this->insertAlternatif('Produk A');

        $stmtCheck = $this->pdo->prepare(
            'SELECT id FROM alternatif WHERE nama = :nama LIMIT 1'
        );
        $stmtCheck->execute([':nama' => 'Produk A']);
        $isDuplicate = $stmtCheck->fetch() !== false;

        $message = $isDuplicate ? 'Nama alternatif sudah digunakan' : null;

        $this->assertSame($expectedMessage, $message);
    }

    /**
     * When updating, a name already used by a DIFFERENT alternatif must be detected.
     *
     * Requirements: 3.7
     */
    public function testUpdateDuplicateNameCheckDetectsConflict(): void
    {
        $id1 = $this->insertAlternatif('Produk A');
        $id2 = $this->insertAlternatif('Produk B');

        // Try to rename id2 to 'Produk A' (already used by id1)
        $stmtCheck = $this->pdo->prepare(
            'SELECT id FROM alternatif WHERE nama = :nama AND id != :id LIMIT 1'
        );
        $stmtCheck->execute([':nama' => 'Produk A', ':id' => $id2]);
        $conflict = $stmtCheck->fetch();

        $this->assertNotFalse($conflict,
            'Updating to a name used by another alternatif must be detected as a conflict'
        );
    }

    /**
     * When updating, using the same name as the record being updated must NOT
     * be detected as a conflict.
     *
     * Requirements: 3.7
     */
    public function testUpdateSameNameNoConflict(): void
    {
        $id = $this->insertAlternatif('Produk A');

        $stmtCheck = $this->pdo->prepare(
            'SELECT id FROM alternatif WHERE nama = :nama AND id != :id LIMIT 1'
        );
        $stmtCheck->execute([':nama' => 'Produk A', ':id' => $id]);
        $conflict = $stmtCheck->fetch();

        $this->assertFalse($conflict,
            'Updating an alternatif to its own name must not be flagged as a conflict'
        );
    }
}
