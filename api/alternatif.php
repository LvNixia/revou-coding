<?php

/**
 * Alternatif (Alternative) API Endpoint
 *
 * Full CRUD for the `alternatif` table, including nested `nilai_alternatif` rows.
 *
 * Methods:
 *   GET    — return all alternatives with their nilai per kriteria as a nested array
 *   POST   — add a new alternative with nilai (nama, nilai: {kriteria_id: value, ...})
 *   PUT    — update an existing alternative and replace its nilai rows
 *   DELETE — delete an alternative (cascade deletes nilai_alternatif via FK)
 *
 * Auth:   requireAuth()       — HTTP 401 if session is invalid
 * CSRF:   validateCsrfToken() — HTTP 403 for POST/PUT/DELETE with bad token
 *
 * Validation rules:
 *   - nama  : non-empty string, unique in the table
 *   - nilai : associative array of kriteria_id => numeric value
 *
 * Request body (POST/PUT):
 *   { "nama": "...", "nilai": { "1": 80, "2": 90 }, "csrf_token": "..." }
 *
 * Request body (DELETE):
 *   { "id": 1, "csrf_token": "..." }
 *
 * Response format:
 *   { "success": true,  "data": { ... } }
 *   { "success": false, "message": "Pesan error" }
 *
 * HTTP status codes:
 *   200 — success
 *   400 — bad input / validation failure
 *   401 — unauthenticated
 *   403 — CSRF token invalid
 *   404 — resource not found
 *   405 — method not allowed
 *   409 — duplicate name
 *   413 — payload too large
 *   500 — server error
 *
 * Requirements: 3.1, 3.2, 3.3, 3.4, 3.5, 3.6, 3.7, 7.1, 7.3
 */

declare(strict_types=1);

// ── Bootstrap ────────────────────────────────────────────────────────────────

require_once __DIR__ . '/../src/middleware/auth_guard.php';
require_once __DIR__ . '/../src/helpers/csrf.php';
require_once __DIR__ . '/../src/helpers/sanitize.php';
require_once __DIR__ . '/../src/helpers/alternatif.php';
require_once __DIR__ . '/../config/database.php';

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

// ── Payload size guard (max 1 MB) ─────────────────────────────────────────────

$contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
if ($contentLength > 1_048_576) {
    jsonResponse(false, 'Payload terlalu besar', 413);
}

// ── Parse request body (JSON or form-encoded) ─────────────────────────────────

$method      = strtoupper($_SERVER['REQUEST_METHOD']);
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
$input       = [];

if (in_array($method, ['POST', 'PUT', 'DELETE'], true)) {
    if (str_contains($contentType, 'application/json')) {
        $raw   = file_get_contents('php://input');
        $input = (array) (json_decode($raw, true) ?? []);
    } else {
        if ($method === 'POST') {
            $input = $_POST;
        } else {
            parse_str(file_get_contents('php://input'), $input);
        }
    }
}

// ── CSRF validation for write operations ──────────────────────────────────────

if (in_array($method, ['POST', 'PUT', 'DELETE'], true)) {
    $csrfToken = (string) ($input['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!validateCsrfToken($csrfToken)) {
        jsonResponse(false, 'Token CSRF tidak valid', 403);
    }
}

// ── Route to handler ──────────────────────────────────────────────────────────

try {
    $pdo = getDbConnection();
} catch (PDOException $e) {
    handleDbError($e, 'api/alternatif.php bootstrap');
}

match ($method) {
    'GET'    => handleGet($pdo),
    'POST'   => handlePost($pdo, $input),
    'PUT'    => handlePut($pdo, $input),
    'DELETE' => handleDelete($pdo, $input),
    default  => jsonResponse(false, 'Method not allowed', 405),
};

// ── GET — return all alternatives with nested nilai ───────────────────────────

function handleGet(PDO $pdo): never
{
    try {
        // Fetch all alternatif rows
        $stmtAlt = $pdo->prepare(
            'SELECT id, nama, created_at, updated_at
               FROM alternatif
              ORDER BY id ASC'
        );
        $stmtAlt->execute();
        $altRows = $stmtAlt->fetchAll();

        // Fetch all nilai_alternatif rows in one query for efficiency
        $stmtNilai = $pdo->prepare(
            'SELECT alternatif_id, kriteria_id, nilai
               FROM nilai_alternatif
              ORDER BY alternatif_id ASC, kriteria_id ASC'
        );
        $stmtNilai->execute();
        $nilaiRows = $stmtNilai->fetchAll();

        // Index nilai by alternatif_id for O(1) lookup
        $nilaiByAlt = [];
        foreach ($nilaiRows as $row) {
            $altId  = (int) $row['alternatif_id'];
            $kritId = (int) $row['kriteria_id'];
            $nilaiByAlt[$altId][$kritId] = (float) $row['nilai'];
        }

        // Build response array
        $alternatif = array_map(static function (array $row) use ($nilaiByAlt): array {
            $id = (int) $row['id'];
            return [
                'id'         => $id,
                'nama'       => $row['nama'],
                'nilai'      => $nilaiByAlt[$id] ?? (object) [],
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at'],
            ];
        }, $altRows);

        jsonResponse(true, null, 200, $alternatif);
    } catch (PDOException $e) {
        handleDbError($e, 'api/alternatif.php GET');
    }
}

// ── POST — add a new alternative with nilai ───────────────────────────────────

function handlePost(PDO $pdo, array $input): never
{
    [$nama, $nilai, $error] = validateAlternatifInput($input);
    if ($error !== null) {
        jsonResponse(false, $error, 400);
    }

    try {
        // Check for duplicate name
        $stmtCheck = $pdo->prepare('SELECT id FROM alternatif WHERE nama = :nama LIMIT 1');
        $stmtCheck->execute([':nama' => $nama]);
        if ($stmtCheck->fetch() !== false) {
            jsonResponse(false, 'Nama alternatif sudah digunakan', 409);
        }

        $pdo->beginTransaction();

        // Insert the alternatif row
        $stmtAlt = $pdo->prepare('INSERT INTO alternatif (nama) VALUES (:nama)');
        $stmtAlt->execute([':nama' => $nama]);
        $newId = (int) $pdo->lastInsertId();

        // Insert nilai_alternatif rows
        if (!empty($nilai)) {
            $stmtNilai = $pdo->prepare(
                'INSERT INTO nilai_alternatif (alternatif_id, kriteria_id, nilai)
                 VALUES (:alternatif_id, :kriteria_id, :nilai)'
            );
            foreach ($nilai as $kriteriaId => $nilaiVal) {
                $stmtNilai->execute([
                    ':alternatif_id' => $newId,
                    ':kriteria_id'   => $kriteriaId,
                    ':nilai'         => $nilaiVal,
                ]);
            }
        }

        $pdo->commit();

        // Fetch the newly created row to return complete data
        $stmtFetch = $pdo->prepare(
            'SELECT id, nama, created_at, updated_at FROM alternatif WHERE id = :id'
        );
        $stmtFetch->execute([':id' => $newId]);
        $row = $stmtFetch->fetch();

        $data = [
            'id'         => (int) $row['id'],
            'nama'       =>       $row['nama'],
            'nilai'      =>       $nilai,
            'created_at' =>       $row['created_at'],
            'updated_at' =>       $row['updated_at'],
        ];

        jsonResponse(true, null, 200, $data);
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($e->getCode() === '23000') {
            jsonResponse(false, 'Nama alternatif sudah digunakan', 409);
        }
        handleDbError($e, 'api/alternatif.php POST');
    }
}

// ── PUT — update an existing alternative and replace its nilai ────────────────

function handlePut(PDO $pdo, array $input): never
{
    $id = filter_var($input['id'] ?? null, FILTER_VALIDATE_INT);
    if ($id === false || $id === null || $id <= 0) {
        jsonResponse(false, 'ID alternatif tidak valid', 400);
    }

    [$nama, $nilai, $error] = validateAlternatifInput($input);
    if ($error !== null) {
        jsonResponse(false, $error, 400);
    }

    try {
        // Ensure the record exists
        $stmtExist = $pdo->prepare('SELECT id FROM alternatif WHERE id = :id LIMIT 1');
        $stmtExist->execute([':id' => $id]);
        if ($stmtExist->fetch() === false) {
            jsonResponse(false, 'Alternatif tidak ditemukan', 404);
        }

        // Check for duplicate name (excluding the current record)
        $stmtCheck = $pdo->prepare(
            'SELECT id FROM alternatif WHERE nama = :nama AND id != :id LIMIT 1'
        );
        $stmtCheck->execute([':nama' => $nama, ':id' => $id]);
        if ($stmtCheck->fetch() !== false) {
            jsonResponse(false, 'Nama alternatif sudah digunakan', 409);
        }

        $pdo->beginTransaction();

        // Update the alternatif row
        $stmtAlt = $pdo->prepare(
            'UPDATE alternatif SET nama = :nama WHERE id = :id'
        );
        $stmtAlt->execute([':nama' => $nama, ':id' => $id]);

        // Replace all nilai_alternatif rows for this alternative
        $stmtDelNilai = $pdo->prepare(
            'DELETE FROM nilai_alternatif WHERE alternatif_id = :alternatif_id'
        );
        $stmtDelNilai->execute([':alternatif_id' => $id]);

        if (!empty($nilai)) {
            $stmtNilai = $pdo->prepare(
                'INSERT INTO nilai_alternatif (alternatif_id, kriteria_id, nilai)
                 VALUES (:alternatif_id, :kriteria_id, :nilai)'
            );
            foreach ($nilai as $kriteriaId => $nilaiVal) {
                $stmtNilai->execute([
                    ':alternatif_id' => $id,
                    ':kriteria_id'   => $kriteriaId,
                    ':nilai'         => $nilaiVal,
                ]);
            }
        }

        $pdo->commit();

        // Fetch the updated row
        $stmtFetch = $pdo->prepare(
            'SELECT id, nama, created_at, updated_at FROM alternatif WHERE id = :id'
        );
        $stmtFetch->execute([':id' => $id]);
        $row = $stmtFetch->fetch();

        $data = [
            'id'         => (int) $row['id'],
            'nama'       =>       $row['nama'],
            'nilai'      =>       $nilai,
            'created_at' =>       $row['created_at'],
            'updated_at' =>       $row['updated_at'],
        ];

        jsonResponse(true, null, 200, $data);
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($e->getCode() === '23000') {
            jsonResponse(false, 'Nama alternatif sudah digunakan', 409);
        }
        handleDbError($e, 'api/alternatif.php PUT');
    }
}

// ── DELETE — remove an alternative (cascade handles nilai_alternatif) ─────────

function handleDelete(PDO $pdo, array $input): never
{
    $id = filter_var($input['id'] ?? null, FILTER_VALIDATE_INT);
    if ($id === false || $id === null || $id <= 0) {
        jsonResponse(false, 'ID alternatif tidak valid', 400);
    }

    try {
        // Ensure the record exists before attempting deletion
        $stmtExist = $pdo->prepare('SELECT id FROM alternatif WHERE id = :id LIMIT 1');
        $stmtExist->execute([':id' => $id]);
        if ($stmtExist->fetch() === false) {
            jsonResponse(false, 'Alternatif tidak ditemukan', 404);
        }

        // The FK ON DELETE CASCADE on nilai_alternatif handles related rows automatically
        $stmt = $pdo->prepare('DELETE FROM alternatif WHERE id = :id');
        $stmt->execute([':id' => $id]);

        jsonResponse(true, null, 200, ['id' => $id]);
    } catch (PDOException $e) {
        handleDbError($e, 'api/alternatif.php DELETE');
    }
}

// validateAlternatifInput() is defined in src/helpers/alternatif.php
