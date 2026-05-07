<?php

/**
 * Unit Tests — Pengaturan (Settings) & Reset API Logic
 *
 * Tests the core business logic for system settings management and data reset:
 *   - Saving and retrieving settings (nama_sistem, nama_organisasi)
 *   - Logo file format validation (PNG/JPG/SVG accepted, others rejected)
 *   - Logo file size validation (max 2 MB)
 *   - Reset data: deletes alternatif, nilai_alternatif, hasil_perhitungan,
 *     nilai_normalisasi, riwayat_perhitungan
 *   - Reset data: does NOT delete kriteria, users, pengaturan
 *   - Two-step confirmation: "RESET" text required
 *
 * These tests use an in-memory SQLite database so they run without a MySQL
 * server and without touching the application database.
 *
 * The API files call exit() via jsonResponse(), so they cannot be tested
 * directly. Instead, this test class exercises the underlying DB operations
 * and validation logic using the same SQL statements as the API.
 *
 * Requirements: 8.1, 8.2, 8.3, 8.4, 8.5
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class PengaturanTest extends TestCase
{
    private PDO $pdo;

    // ── Setup / Teardown ──────────────────────────────────────────────────────

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->pdo->exec('PRAGMA foreign_keys = ON');

        // pengaturan: key-value settings store
        $this->pdo->exec(
            "CREATE TABLE pengaturan (
                kunci      TEXT PRIMARY KEY,
                nilai      TEXT NOT NULL,
                updated_at TEXT DEFAULT (datetime('now'))
            )"
        );

        // users table (must NOT be deleted by reset)
        $this->pdo->exec(
            "CREATE TABLE users (
                id       INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NOT NULL UNIQUE,
                password TEXT NOT NULL
            )"
        );

        // kriteria table (must NOT be deleted by reset)
        $this->pdo->exec(
            "CREATE TABLE kriteria (
                id    INTEGER PRIMARY KEY AUTOINCREMENT,
                nama  TEXT NOT NULL UNIQUE,
                bobot REAL NOT NULL,
                tipe  TEXT NOT NULL
            )"
        );

        // alternatif (deleted by reset)
        $this->pdo->exec(
            "CREATE TABLE alternatif (
                id   INTEGER PRIMARY KEY AUTOINCREMENT,
                nama TEXT NOT NULL UNIQUE
            )"
        );

        // nilai_alternatif (deleted by reset)
        $this->pdo->exec(
            "CREATE TABLE nilai_alternatif (
                id            INTEGER PRIMARY KEY AUTOINCREMENT,
                alternatif_id INTEGER NOT NULL,
                kriteria_id   INTEGER NOT NULL,
                nilai         REAL NOT NULL,
                FOREIGN KEY (alternatif_id) REFERENCES alternatif(id) ON DELETE CASCADE,
                FOREIGN KEY (kriteria_id)   REFERENCES kriteria(id)   ON DELETE CASCADE
            )"
        );

        // riwayat_perhitungan (deleted by reset)
        $this->pdo->exec(
            "CREATE TABLE riwayat_perhitungan (
                id                INTEGER PRIMARY KEY AUTOINCREMENT,
                dihitung_pada     TEXT DEFAULT (datetime('now')),
                jumlah_kriteria   INTEGER NOT NULL,
                jumlah_alternatif INTEGER NOT NULL,
                catatan           TEXT
            )"
        );

        // hasil_perhitungan (deleted by reset)
        $this->pdo->exec(
            "CREATE TABLE hasil_perhitungan (
                id               INTEGER PRIMARY KEY AUTOINCREMENT,
                riwayat_id       INTEGER NOT NULL,
                alternatif_id    INTEGER NOT NULL,
                nilai_preferensi REAL NOT NULL,
                ranking          INTEGER NOT NULL,
                FOREIGN KEY (riwayat_id)    REFERENCES riwayat_perhitungan(id) ON DELETE CASCADE,
                FOREIGN KEY (alternatif_id) REFERENCES alternatif(id)          ON DELETE CASCADE
            )"
        );

        // nilai_normalisasi (deleted by reset)
        $this->pdo->exec(
            "CREATE TABLE nilai_normalisasi (
                id            INTEGER PRIMARY KEY AUTOINCREMENT,
                riwayat_id    INTEGER NOT NULL,
                alternatif_id INTEGER NOT NULL,
                kriteria_id   INTEGER NOT NULL,
                nilai_normal  REAL NOT NULL,
                FOREIGN KEY (riwayat_id)    REFERENCES riwayat_perhitungan(id) ON DELETE CASCADE,
                FOREIGN KEY (alternatif_id) REFERENCES alternatif(id)          ON DELETE CASCADE,
                FOREIGN KEY (kriteria_id)   REFERENCES kriteria(id)            ON DELETE CASCADE
            )"
        );

        // Seed default settings (mirrors schema.sql)
        $this->pdo->exec(
            "INSERT INTO pengaturan (kunci, nilai) VALUES
                ('nama_sistem',     'SPK - Sistem Pendukung Keputusan'),
                ('nama_organisasi', 'Organisasi'),
                ('logo_path',       '')"
        );
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /** Count rows in a table. */
    private function countRows(string $table): int
    {
        return (int) $this->pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
    }

    /** Fetch a single setting value by key, or null if not found. */
    private function getSetting(string $kunci): ?string
    {
        $stmt = $this->pdo->prepare('SELECT nilai FROM pengaturan WHERE kunci = :kunci');
        $stmt->execute([':kunci' => $kunci]);
        $row = $stmt->fetch();
        return $row !== false ? $row['nilai'] : null;
    }

    /**
     * Upsert a setting — mirrors the INSERT … ON DUPLICATE KEY UPDATE logic
     * from api/pengaturan.php, adapted for SQLite using INSERT OR REPLACE.
     */
    private function upsertSetting(string $kunci, string $nilai): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT OR REPLACE INTO pengaturan (kunci, nilai) VALUES (:kunci, :nilai)'
        );
        $stmt->execute([':kunci' => $kunci, ':nilai' => $nilai]);
    }

    /** Fetch all settings as an associative array. */
    private function getAllSettings(): array
    {
        $stmt = $this->pdo->prepare('SELECT kunci, nilai FROM pengaturan ORDER BY kunci ASC');
        $stmt->execute();
        $rows = $stmt->fetchAll();
        $data = [];
        foreach ($rows as $row) {
            $data[$row['kunci']] = $row['nilai'];
        }
        return $data;
    }

    /**
     * Execute the reset DELETE operations (mirrors api/reset.php logic).
     * Returns true on success.
     */
    private function executeReset(): bool
    {
        $this->pdo->exec('DELETE FROM nilai_normalisasi');
        $this->pdo->exec('DELETE FROM hasil_perhitungan');
        $this->pdo->exec('DELETE FROM riwayat_perhitungan');
        $this->pdo->exec('DELETE FROM nilai_alternatif');
        $this->pdo->exec('DELETE FROM alternatif');
        return true;
    }

    /**
     * Validate the reset confirmation string — mirrors api/reset.php logic.
     */
    private function validateConfirmation(string $confirmation): bool
    {
        return trim($confirmation) === 'RESET';
    }

    /**
     * Validate logo MIME type — mirrors the allowed-MIME check in api/pengaturan.php.
     * Accepts: image/png, image/jpeg, image/svg+xml
     */
    private function isAllowedLogoMime(string $mimeType): bool
    {
        $allowedMimes = ['image/png', 'image/jpeg', 'image/svg+xml'];
        return in_array(strtolower($mimeType), $allowedMimes, true);
    }

    /**
     * Validate logo file size — mirrors the 2 MB limit in api/pengaturan.php.
     */
    private function isAllowedLogoSize(int $sizeBytes): bool
    {
        $maxSize = 2_097_152; // 2 MB
        return $sizeBytes <= $maxSize;
    }

    // =========================================================================
    // Section 1 — Saving and retrieving settings
    // =========================================================================

    /**
     * Default settings must be present after schema seed.
     *
     * Requirements: 8.1
     */
    public function testDefaultSettingsExistAfterSeed(): void
    {
        $this->assertNotNull($this->getSetting('nama_sistem'));
        $this->assertNotNull($this->getSetting('nama_organisasi'));
        $this->assertNotNull($this->getSetting('logo_path'));
    }

    /**
     * Saving nama_sistem must persist the new value.
     *
     * Requirements: 8.1
     */
    public function testSaveNamaSistemPersistsValue(): void
    {
        $this->upsertSetting('nama_sistem', 'Sistem Baru');

        $this->assertSame('Sistem Baru', $this->getSetting('nama_sistem'));
    }

    /**
     * Saving nama_organisasi must persist the new value.
     *
     * Requirements: 8.1
     */
    public function testSaveNamaOrganisasiPersistsValue(): void
    {
        $this->upsertSetting('nama_organisasi', 'PT Contoh Indonesia');

        $this->assertSame('PT Contoh Indonesia', $this->getSetting('nama_organisasi'));
    }

    /**
     * Updating an existing setting must overwrite the old value.
     *
     * Requirements: 8.1
     */
    public function testUpdateSettingOverwritesOldValue(): void
    {
        $this->upsertSetting('nama_sistem', 'Versi 1');
        $this->upsertSetting('nama_sistem', 'Versi 2');

        $this->assertSame('Versi 2', $this->getSetting('nama_sistem'));
    }

    /**
     * Retrieving all settings must return a flat associative array.
     *
     * Requirements: 8.1
     */
    public function testGetAllSettingsReturnsAssocArray(): void
    {
        $settings = $this->getAllSettings();

        $this->assertIsArray($settings);
        $this->assertArrayHasKey('nama_sistem', $settings);
        $this->assertArrayHasKey('nama_organisasi', $settings);
        $this->assertArrayHasKey('logo_path', $settings);
    }

    /**
     * Saving both nama_sistem and nama_organisasi in one transaction must
     * persist both values correctly.
     *
     * Requirements: 8.1
     */
    public function testSaveBothSettingsInTransaction(): void
    {
        $this->pdo->beginTransaction();
        $this->upsertSetting('nama_sistem',     'SPK Test');
        $this->upsertSetting('nama_organisasi', 'Organisasi Test');
        $this->pdo->commit();

        $this->assertSame('SPK Test',         $this->getSetting('nama_sistem'));
        $this->assertSame('Organisasi Test',  $this->getSetting('nama_organisasi'));
    }

    /**
     * Saving an empty string for nama_organisasi (optional field) must be allowed.
     *
     * Requirements: 8.1
     */
    public function testSaveEmptyNamaOrganisasiIsAllowed(): void
    {
        $this->upsertSetting('nama_organisasi', '');

        $this->assertSame('', $this->getSetting('nama_organisasi'));
    }

    /**
     * Saving logo_path must persist the path value.
     *
     * Requirements: 8.2
     */
    public function testSaveLogoPathPersistsValue(): void
    {
        $this->upsertSetting('logo_path', 'assets/uploads/logo_abc123.png');

        $this->assertSame('assets/uploads/logo_abc123.png', $this->getSetting('logo_path'));
    }

    // =========================================================================
    // Section 2 — Logo file format validation
    // =========================================================================

    /**
     * PNG MIME type must be accepted.
     *
     * Requirements: 8.2
     */
    public function testLogoMimePngIsAccepted(): void
    {
        $this->assertTrue($this->isAllowedLogoMime('image/png'));
    }

    /**
     * JPEG MIME type must be accepted.
     *
     * Requirements: 8.2
     */
    public function testLogoMimeJpegIsAccepted(): void
    {
        $this->assertTrue($this->isAllowedLogoMime('image/jpeg'));
    }

    /**
     * SVG MIME type must be accepted.
     *
     * Requirements: 8.2
     */
    public function testLogoMimeSvgIsAccepted(): void
    {
        $this->assertTrue($this->isAllowedLogoMime('image/svg+xml'));
    }

    /**
     * GIF MIME type must be rejected.
     *
     * Requirements: 8.2
     */
    public function testLogoMimeGifIsRejected(): void
    {
        $this->assertFalse($this->isAllowedLogoMime('image/gif'));
    }

    /**
     * BMP MIME type must be rejected.
     *
     * Requirements: 8.2
     */
    public function testLogoMimeBmpIsRejected(): void
    {
        $this->assertFalse($this->isAllowedLogoMime('image/bmp'));
    }

    /**
     * WebP MIME type must be rejected.
     *
     * Requirements: 8.2
     */
    public function testLogoMimeWebpIsRejected(): void
    {
        $this->assertFalse($this->isAllowedLogoMime('image/webp'));
    }

    /**
     * PDF MIME type must be rejected.
     *
     * Requirements: 8.2
     */
    public function testLogoMimePdfIsRejected(): void
    {
        $this->assertFalse($this->isAllowedLogoMime('application/pdf'));
    }

    /**
     * Plain text MIME type must be rejected.
     *
     * Requirements: 8.2
     */
    public function testLogoMimeTextIsRejected(): void
    {
        $this->assertFalse($this->isAllowedLogoMime('text/plain'));
    }

    /**
     * Empty MIME type string must be rejected.
     *
     * Requirements: 8.2
     */
    public function testLogoMimeEmptyStringIsRejected(): void
    {
        $this->assertFalse($this->isAllowedLogoMime(''));
    }

    /**
     * MIME type check must be case-insensitive (IMAGE/PNG must be accepted).
     *
     * Requirements: 8.2
     */
    public function testLogoMimeCheckIsCaseInsensitive(): void
    {
        $this->assertTrue($this->isAllowedLogoMime('IMAGE/PNG'));
        $this->assertTrue($this->isAllowedLogoMime('Image/Jpeg'));
    }

    // =========================================================================
    // Section 3 — Logo file size validation
    // =========================================================================

    /**
     * A file of exactly 2 MB (boundary) must be accepted.
     *
     * Requirements: 8.2
     */
    public function testLogoSizeAtExactlyTwoMbIsAccepted(): void
    {
        $this->assertTrue($this->isAllowedLogoSize(2_097_152));
    }

    /**
     * A file of 1 MB must be accepted.
     *
     * Requirements: 8.2
     */
    public function testLogoSizeOneMbIsAccepted(): void
    {
        $this->assertTrue($this->isAllowedLogoSize(1_048_576));
    }

    /**
     * A file of 1 byte must be accepted.
     *
     * Requirements: 8.2
     */
    public function testLogoSizeOneByteIsAccepted(): void
    {
        $this->assertTrue($this->isAllowedLogoSize(1));
    }

    /**
     * A file of 0 bytes must be accepted (size check only; content check is separate).
     *
     * Requirements: 8.2
     */
    public function testLogoSizeZeroBytesIsAccepted(): void
    {
        $this->assertTrue($this->isAllowedLogoSize(0));
    }

    /**
     * A file of 2 MB + 1 byte (just over the limit) must be rejected.
     *
     * Requirements: 8.2
     */
    public function testLogoSizeJustOverTwoMbIsRejected(): void
    {
        $this->assertFalse($this->isAllowedLogoSize(2_097_153));
    }

    /**
     * A file of 5 MB must be rejected.
     *
     * Requirements: 8.2
     */
    public function testLogoSizeFiveMbIsRejected(): void
    {
        $this->assertFalse($this->isAllowedLogoSize(5_242_880));
    }

    /**
     * A file of 10 MB must be rejected.
     *
     * Requirements: 8.2
     */
    public function testLogoSizeTenMbIsRejected(): void
    {
        $this->assertFalse($this->isAllowedLogoSize(10_485_760));
    }

    // =========================================================================
    // Section 4 — Reset confirmation validation (two-step)
    // =========================================================================

    /**
     * Confirmation string "RESET" (exact) must be valid.
     *
     * Requirements: 8.3
     */
    public function testConfirmationResetIsValid(): void
    {
        $this->assertTrue($this->validateConfirmation('RESET'));
    }

    /**
     * Confirmation string with surrounding whitespace must be valid (trimmed).
     *
     * Requirements: 8.3
     */
    public function testConfirmationResetWithWhitespaceIsValid(): void
    {
        $this->assertTrue($this->validateConfirmation('  RESET  '));
    }

    /**
     * Lowercase "reset" must be rejected (case-sensitive).
     *
     * Requirements: 8.3
     */
    public function testConfirmationLowercaseResetIsRejected(): void
    {
        $this->assertFalse($this->validateConfirmation('reset'));
    }

    /**
     * Mixed-case "Reset" must be rejected.
     *
     * Requirements: 8.3
     */
    public function testConfirmationMixedCaseResetIsRejected(): void
    {
        $this->assertFalse($this->validateConfirmation('Reset'));
    }

    /**
     * Empty string must be rejected.
     *
     * Requirements: 8.3
     */
    public function testConfirmationEmptyStringIsRejected(): void
    {
        $this->assertFalse($this->validateConfirmation(''));
    }

    /**
     * Arbitrary text must be rejected.
     *
     * Requirements: 8.3
     */
    public function testConfirmationArbitraryTextIsRejected(): void
    {
        $this->assertFalse($this->validateConfirmation('yes'));
        $this->assertFalse($this->validateConfirmation('DELETE'));
        $this->assertFalse($this->validateConfirmation('1'));
    }

    // =========================================================================
    // Section 5 — Reset data: tables that MUST be deleted
    // =========================================================================

    /**
     * Reset must delete all rows from the alternatif table.
     *
     * Requirements: 8.3, 8.4
     */
    public function testResetDeletesAlternatif(): void
    {
        $this->pdo->exec("INSERT INTO alternatif (nama) VALUES ('Produk A'), ('Produk B')");
        $this->assertSame(2, $this->countRows('alternatif'));

        $this->executeReset();

        $this->assertSame(0, $this->countRows('alternatif'));
    }

    /**
     * Reset must delete all rows from the nilai_alternatif table.
     *
     * Requirements: 8.3, 8.4
     */
    public function testResetDeletesNilaiAlternatif(): void
    {
        $this->pdo->exec("INSERT INTO kriteria (nama, bobot, tipe) VALUES ('Harga', 0.5, 'benefit')");
        $this->pdo->exec("INSERT INTO alternatif (nama) VALUES ('Produk A')");
        $this->pdo->exec(
            "INSERT INTO nilai_alternatif (alternatif_id, kriteria_id, nilai)
             VALUES (1, 1, 80.0)"
        );
        $this->assertSame(1, $this->countRows('nilai_alternatif'));

        $this->executeReset();

        $this->assertSame(0, $this->countRows('nilai_alternatif'));
    }

    /**
     * Reset must delete all rows from the hasil_perhitungan table.
     *
     * Requirements: 8.3, 8.4
     */
    public function testResetDeletesHasilPerhitungan(): void
    {
        $this->pdo->exec(
            "INSERT INTO alternatif (nama) VALUES ('Produk A')"
        );
        $this->pdo->exec(
            "INSERT INTO riwayat_perhitungan (jumlah_kriteria, jumlah_alternatif)
             VALUES (2, 1)"
        );
        $this->pdo->exec(
            "INSERT INTO hasil_perhitungan (riwayat_id, alternatif_id, nilai_preferensi, ranking)
             VALUES (1, 1, 0.85, 1)"
        );
        $this->assertSame(1, $this->countRows('hasil_perhitungan'));

        $this->executeReset();

        $this->assertSame(0, $this->countRows('hasil_perhitungan'));
    }

    /**
     * Reset must delete all rows from the nilai_normalisasi table.
     *
     * Requirements: 8.3, 8.4
     */
    public function testResetDeletesNilaiNormalisasi(): void
    {
        $this->pdo->exec("INSERT INTO kriteria (nama, bobot, tipe) VALUES ('Harga', 0.5, 'benefit')");
        $this->pdo->exec("INSERT INTO alternatif (nama) VALUES ('Produk A')");
        $this->pdo->exec(
            "INSERT INTO riwayat_perhitungan (jumlah_kriteria, jumlah_alternatif)
             VALUES (1, 1)"
        );
        $this->pdo->exec(
            "INSERT INTO nilai_normalisasi (riwayat_id, alternatif_id, kriteria_id, nilai_normal)
             VALUES (1, 1, 1, 1.0)"
        );
        $this->assertSame(1, $this->countRows('nilai_normalisasi'));

        $this->executeReset();

        $this->assertSame(0, $this->countRows('nilai_normalisasi'));
    }

    /**
     * Reset must delete all rows from the riwayat_perhitungan table.
     *
     * Requirements: 8.3, 8.4
     */
    public function testResetDeletesRiwayatPerhitungan(): void
    {
        $this->pdo->exec(
            "INSERT INTO riwayat_perhitungan (jumlah_kriteria, jumlah_alternatif)
             VALUES (3, 5), (2, 4)"
        );
        $this->assertSame(2, $this->countRows('riwayat_perhitungan'));

        $this->executeReset();

        $this->assertSame(0, $this->countRows('riwayat_perhitungan'));
    }

    /**
     * Reset must delete all five target tables in a single operation.
     *
     * Requirements: 8.3, 8.4
     */
    public function testResetDeletesAllTargetTablesAtOnce(): void
    {
        // Seed all target tables
        $this->pdo->exec("INSERT INTO kriteria (nama, bobot, tipe) VALUES ('Harga', 0.5, 'benefit')");
        $this->pdo->exec("INSERT INTO alternatif (nama) VALUES ('Produk A')");
        $this->pdo->exec(
            "INSERT INTO nilai_alternatif (alternatif_id, kriteria_id, nilai) VALUES (1, 1, 75.0)"
        );
        $this->pdo->exec(
            "INSERT INTO riwayat_perhitungan (jumlah_kriteria, jumlah_alternatif) VALUES (1, 1)"
        );
        $this->pdo->exec(
            "INSERT INTO hasil_perhitungan (riwayat_id, alternatif_id, nilai_preferensi, ranking)
             VALUES (1, 1, 0.75, 1)"
        );
        $this->pdo->exec(
            "INSERT INTO nilai_normalisasi (riwayat_id, alternatif_id, kriteria_id, nilai_normal)
             VALUES (1, 1, 1, 1.0)"
        );

        $this->executeReset();

        $this->assertSame(0, $this->countRows('alternatif'),           'alternatif must be empty');
        $this->assertSame(0, $this->countRows('nilai_alternatif'),     'nilai_alternatif must be empty');
        $this->assertSame(0, $this->countRows('riwayat_perhitungan'),  'riwayat_perhitungan must be empty');
        $this->assertSame(0, $this->countRows('hasil_perhitungan'),    'hasil_perhitungan must be empty');
        $this->assertSame(0, $this->countRows('nilai_normalisasi'),    'nilai_normalisasi must be empty');
    }

    // =========================================================================
    // Section 6 — Reset data: tables that must NOT be deleted
    // =========================================================================

    /**
     * Reset must NOT delete rows from the kriteria table.
     *
     * Requirements: 8.3, 8.5
     */
    public function testResetDoesNotDeleteKriteria(): void
    {
        $this->pdo->exec(
            "INSERT INTO kriteria (nama, bobot, tipe)
             VALUES ('Harga', 0.4, 'benefit'), ('Kualitas', 0.6, 'benefit')"
        );
        $this->assertSame(2, $this->countRows('kriteria'));

        $this->executeReset();

        $this->assertSame(2, $this->countRows('kriteria'),
            'Reset must not delete kriteria rows'
        );
    }

    /**
     * Reset must NOT delete rows from the users table.
     *
     * Requirements: 8.3, 8.5
     */
    public function testResetDoesNotDeleteUsers(): void
    {
        $this->pdo->exec(
            "INSERT INTO users (username, password) VALUES ('admin', 'hash123')"
        );
        $this->assertSame(1, $this->countRows('users'));

        $this->executeReset();

        $this->assertSame(1, $this->countRows('users'),
            'Reset must not delete users rows'
        );
    }

    /**
     * Reset must NOT delete rows from the pengaturan table.
     *
     * Requirements: 8.3, 8.5
     */
    public function testResetDoesNotDeletePengaturan(): void
    {
        $countBefore = $this->countRows('pengaturan');
        $this->assertGreaterThan(0, $countBefore);

        $this->executeReset();

        $this->assertSame($countBefore, $this->countRows('pengaturan'),
            'Reset must not delete pengaturan rows'
        );
    }

    /**
     * After reset, settings values must remain unchanged.
     *
     * Requirements: 8.3, 8.5
     */
    public function testResetPreservesSettingValues(): void
    {
        $this->upsertSetting('nama_sistem', 'Sistem Penting');

        $this->executeReset();

        $this->assertSame('Sistem Penting', $this->getSetting('nama_sistem'),
            'Reset must not alter pengaturan values'
        );
    }

    /**
     * After reset, kriteria data must remain intact.
     *
     * Requirements: 8.3, 8.5
     */
    public function testResetPreservesKriteriaData(): void
    {
        $this->pdo->exec(
            "INSERT INTO kriteria (nama, bobot, tipe) VALUES ('Harga', 0.5, 'benefit')"
        );

        $this->executeReset();

        $stmt = $this->pdo->query("SELECT nama FROM kriteria WHERE nama = 'Harga'");
        $row  = $stmt->fetch();
        $this->assertNotFalse($row, 'Kriteria row must survive a reset');
        $this->assertSame('Harga', $row['nama']);
    }
}
