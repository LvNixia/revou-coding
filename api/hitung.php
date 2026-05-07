<?php

/**
 * Hitung (Calculation) API Endpoint
 *
 * Entry point for triggering the SAW calculation.
 * Handles all pre-calculation validation and the full SAW engine integration:
 *   1. Session authentication
 *   2. CSRF token validation
 *   3. Payload size guard
 *   4. Total bobot = 1.00 validation (Requirement 2.5)
 *   5. Check kriteria and alternatif exist (Requirement 4.5)
 *   6. Fetch kriteria and alternatif with their nilai from DB
 *   7. Run SAW normalization, preference calculation, and ranking via SPKEngine
 *   8. Persist results to riwayat_perhitungan, hasil_perhitungan, nilai_normalisasi
 *   9. Return full results as JSON
 *
 * Method:
 *   POST — trigger calculation
 *
 * Auth:   requireAuth()       — HTTP 401 if session is invalid
 * CSRF:   validateCsrfToken() — HTTP 403 for POST with bad token
 *
 * Response format:
 *   { "success": true,  "data": { "riwayat_id": 1, "dihitung_pada": "...", ... } }
 *   { "success": false, "message": "Pesan error" }
 *
 * HTTP status codes:
 *   200 — calculation succeeded
 *   400 — validation failure (total bobot ≠ 1.00, insufficient data, Cost value = 0)
 *   401 — unauthenticated
 *   403 — CSRF token invalid
 *   405 — method not allowed
 *   413 — payload too large
 *   500 — server error
 *
 * Requirements: 2.5, 4.4, 4.5, 4.6, 4.7
 */

declare(strict_types=1);

// ── Bootstrap ────────────────────────────────────────────────────────────────

require_once __DIR__ . '/../src/middleware/auth_guard.php';
require_once __DIR__ . '/../src/helpers/csrf.php';
require_once __DIR__ . '/../src/helpers/bobot.php';
require_once __DIR__ . '/../config/database.php';

// SPKEngine uses a namespace — load via Composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

use Spk\Engine\SPKEngine;

// Enforce authentication — returns HTTP 401 for API requests without a valid session
requireAuth();

// ── Helpers ───────────────────────────────────────────────────────────────────

/**
 * Send a JSON response and terminate execution.
 *
 * @param bool        $success Whether the operation succeeded
 * @param string|null $message Human-readable message (used on error)
 * @param int         $status  HTTP status code
 * @param mixed       $data    Payload for the "data" key (success responses)
 */
function jsonResponse(bool $success, ?string $message, int $status = 200, mixed $data = null): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');

    if ($success) {
        $body = ['success' => true];
        if ($data !== null) {
            $body['data'] = $data;
        }
    } else {
        $body = ['success' => false, 'message' => $message ?? 'Terjadi kesalahan.'];
    }

    echo json_encode($body);
    exit;
}

/**
 * Log a PDOException to the error log file and return a generic 500 response.
 */
function handleDbError(PDOException $e, string $context): never
{
    $entry = sprintf(
        "[%s] %s PDOException: %s in %s:%d\n",
        date('Y-m-d H:i:s'),
        $context,
        $e->getMessage(),
        $e->getFile(),
        $e->getLine()
    );
    @error_log($entry, 3, __DIR__ . '/../logs/error.log');

    jsonResponse(false, 'Terjadi kesalahan server. Silakan coba lagi.', 500);
}

// ── Only POST is accepted ─────────────────────────────────────────────────────

$method = strtoupper($_SERVER['REQUEST_METHOD']);

if ($method !== 'POST') {
    jsonResponse(false, 'Method not allowed', 405);
}

// ── Payload size guard (max 1 MB) ─────────────────────────────────────────────

$contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
if ($contentLength > 1_048_576) {
    jsonResponse(false, 'Payload terlalu besar', 413);
}

// ── Parse request body (JSON or form-encoded) ─────────────────────────────────

$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
$input       = [];

if (str_contains($contentType, 'application/json')) {
    $raw   = file_get_contents('php://input');
    $input = (array) (json_decode($raw, true) ?? []);
} else {
    $input = $_POST;
}

// ── CSRF validation ───────────────────────────────────────────────────────────

$csrfToken = (string) ($input['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if (!validateCsrfToken($csrfToken)) {
    jsonResponse(false, 'Token CSRF tidak valid', 403);
}

// ── Database connection ───────────────────────────────────────────────────────

try {
    $pdo = getDbConnection();
} catch (PDOException $e) {
    handleDbError($e, 'api/hitung.php bootstrap');
}

// ── Validation: total bobot must equal 1.00 (Requirement 2.5) ────────────────

try {
    $bobotResult = validateBobotTotal($pdo);
    if ($bobotResult !== true) {
        // $bobotResult is the error message string
        jsonResponse(false, $bobotResult, 400);
    }
} catch (PDOException $e) {
    handleDbError($e, 'api/hitung.php validateBobotTotal');
}

// ── Validation: at least one kriteria and one alternatif must exist ───────────

try {
    $stmtKriteria = $pdo->prepare('SELECT COUNT(*) AS cnt FROM kriteria');
    $stmtKriteria->execute();
    $kriteriaCount = (int) $stmtKriteria->fetch()['cnt'];

    $stmtAlternatif = $pdo->prepare('SELECT COUNT(*) AS cnt FROM alternatif');
    $stmtAlternatif->execute();
    $alternatifCount = (int) $stmtAlternatif->fetch()['cnt'];

    if ($kriteriaCount === 0 || $alternatifCount === 0) {
        jsonResponse(false, 'Data tidak cukup untuk melakukan perhitungan', 400);
    }
} catch (PDOException $e) {
    handleDbError($e, 'api/hitung.php data check');
}

// ── Fetch all kriteria (id, nama, bobot, tipe) ────────────────────────────────

try {
    $stmtK = $pdo->prepare('SELECT id, nama, bobot, tipe FROM kriteria ORDER BY id');
    $stmtK->execute();
    $kriteriaRows = $stmtK->fetchAll();
} catch (PDOException $e) {
    handleDbError($e, 'api/hitung.php fetch kriteria');
}

// Build lookup maps
$kriteriaMap = [];   // [id => ['nama' => ..., 'bobot' => ..., 'tipe' => ...]]
$weights     = [];   // [id => bobot]
foreach ($kriteriaRows as $row) {
    $kid = (int) $row['id'];
    $kriteriaMap[$kid] = [
        'nama'  => $row['nama'],
        'bobot' => (float) $row['bobot'],
        'tipe'  => $row['tipe'],
    ];
    $weights[$kid] = (float) $row['bobot'];
}

// ── Fetch all alternatif with their nilai ─────────────────────────────────────

try {
    $stmtA = $pdo->prepare('SELECT id, nama FROM alternatif ORDER BY id');
    $stmtA->execute();
    $alternatifRows = $stmtA->fetchAll();

    $stmtN = $pdo->prepare(
        'SELECT alternatif_id, kriteria_id, nilai FROM nilai_alternatif'
    );
    $stmtN->execute();
    $nilaiRows = $stmtN->fetchAll();
} catch (PDOException $e) {
    handleDbError($e, 'api/hitung.php fetch alternatif');
}

// Build alternatif lookup: [alt_id => ['nama' => ..., 'nilai' => [krit_id => nilai]]]
$alternatifMap = [];
foreach ($alternatifRows as $row) {
    $aid = (int) $row['id'];
    $alternatifMap[$aid] = [
        'nama'  => $row['nama'],
        'nilai' => [],
    ];
}
foreach ($nilaiRows as $row) {
    $aid = (int) $row['alternatif_id'];
    $kid = (int) $row['kriteria_id'];
    if (isset($alternatifMap[$aid])) {
        $alternatifMap[$aid]['nilai'][$kid] = (float) $row['nilai'];
    }
}

// ── Build per-kriteria value arrays and run normalization ─────────────────────

$engine          = new SPKEngine();
$normalizedMatrix = [];  // [kriteria_id => [alt_id => normalized_value]]

try {
    foreach ($kriteriaMap as $kid => $krit) {
        // Build [alt_id => nilai] for this kriteria
        $valuesForKriteria = [];
        foreach ($alternatifMap as $aid => $alt) {
            // Use 0.0 as default if the value is missing (will trigger InvalidArgumentException for Cost)
            $valuesForKriteria[$aid] = $alt['nilai'][$kid] ?? 0.0;
        }

        // Normalize — may throw InvalidArgumentException for Cost with zero value
        $normalizedMatrix[$kid] = $engine->normalize($valuesForKriteria, $krit['tipe']);
    }
} catch (\InvalidArgumentException $e) {
    jsonResponse(false, $e->getMessage(), 400);
}

// ── Calculate preference values ───────────────────────────────────────────────

$preferences = $engine->calculatePreference($normalizedMatrix, $weights);

// ── Rank alternatives ─────────────────────────────────────────────────────────

$rankingRaw = $engine->rank($preferences);

// Enrich ranking with alternative names
$ranking = [];
foreach ($rankingRaw as $entry) {
    $aid = (int) $entry['id'];
    $ranking[] = [
        'id'         => $aid,
        'nama'       => $alternatifMap[$aid]['nama'] ?? '',
        'preference' => $entry['preference'],
        'rank'       => $entry['rank'],
    ];
}

// ── Persist results to database in a transaction ──────────────────────────────

try {
    $pdo->beginTransaction();

    // 1. Insert riwayat_perhitungan
    $stmtRiwayat = $pdo->prepare(
        'INSERT INTO riwayat_perhitungan (jumlah_kriteria, jumlah_alternatif)
         VALUES (:jk, :ja)'
    );
    $stmtRiwayat->execute([
        ':jk' => $kriteriaCount,
        ':ja' => $alternatifCount,
    ]);
    $riwayatId = (int) $pdo->lastInsertId();

    // Fetch the auto-generated timestamp
    $stmtTs = $pdo->prepare(
        'SELECT dihitung_pada FROM riwayat_perhitungan WHERE id = :id'
    );
    $stmtTs->execute([':id' => $riwayatId]);
    $dihitungPada = (string) $stmtTs->fetch()['dihitung_pada'];

    // 2. Insert hasil_perhitungan (one row per alternatif)
    $stmtHasil = $pdo->prepare(
        'INSERT INTO hasil_perhitungan (riwayat_id, alternatif_id, nilai_preferensi, ranking)
         VALUES (:rid, :aid, :np, :rank)'
    );
    foreach ($ranking as $entry) {
        $stmtHasil->execute([
            ':rid'  => $riwayatId,
            ':aid'  => $entry['id'],
            ':np'   => $entry['preference'],
            ':rank' => $entry['rank'],
        ]);
    }

    // 3. Insert nilai_normalisasi (one row per alt × kriteria)
    $stmtNorm = $pdo->prepare(
        'INSERT INTO nilai_normalisasi (riwayat_id, alternatif_id, kriteria_id, nilai_normal)
         VALUES (:rid, :aid, :kid, :nn)'
    );
    foreach ($normalizedMatrix as $kid => $altNormals) {
        foreach ($altNormals as $aid => $normalVal) {
            $stmtNorm->execute([
                ':rid' => $riwayatId,
                ':aid' => $aid,
                ':kid' => $kid,
                ':nn'  => $normalVal,
            ]);
        }
    }

    $pdo->commit();
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    handleDbError($e, 'api/hitung.php persist results');
}

// ── Build normalisasi response structure [kriteria_id => [alt_id => value]] ───

$normalisasiResponse = [];
foreach ($normalizedMatrix as $kid => $altNormals) {
    $normalisasiResponse[(string) $kid] = [];
    foreach ($altNormals as $aid => $val) {
        $normalisasiResponse[(string) $kid][(string) $aid] = $val;
    }
}

// ── Return success response ───────────────────────────────────────────────────

jsonResponse(true, null, 200, [
    'riwayat_id'        => $riwayatId,
    'dihitung_pada'     => $dihitungPada,
    'jumlah_kriteria'   => $kriteriaCount,
    'jumlah_alternatif' => $alternatifCount,
    'ranking'           => $ranking,
    'normalisasi'       => $normalisasiResponse,
]);
