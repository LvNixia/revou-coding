<?php

/**
 * Kriteria (Criteria) API Endpoint
 *
 * Full CRUD for the `kriteria` table.
 *
 * Methods:
 *   GET    — return all criteria as a JSON array
 *   POST   — add a new criterion (nama, bobot, tipe)
 *   PUT    — update an existing criterion by id
 *   DELETE — delete a criterion (cascade deletes nilai_alternatif via FK)
 *
 * Auth:   requireAuth()       — HTTP 401 if session is invalid
 * CSRF:   validateCsrfToken() — HTTP 403 for POST/PUT/DELETE with bad token
 *
 * Validation rules:
 *   - nama  : non-empty string, unique in the table
 *   - bobot : float in [0.01, 1.00]
 *   - tipe  : 'benefit' or 'cost' (case-insensitive, stored lowercase)
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
 * Requirements: 2.1, 2.2, 2.3, 2.4, 2.6, 2.7, 7.1, 7.3
 */

declare(strict_types=1);

// ── Bootstrap ────────────────────────────────────────────────────────────────

require_once __DIR__ . '/../src/middleware/auth_guard.php';
require_once __DIR__ . '/../src/helpers/csrf.php';
require_once __DIR__ . '/../src/helpers/sanitize.php';
require_once __DIR__ . '/../src/helpers/kriteria.php';
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
        // For PUT/DELETE, PHP does not populate $_POST automatically
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
    handleDbError($e, 'api/kriteria.php bootstrap');
}

match ($method) {
    'GET'    => handleGet($pdo),
    'POST'   => handlePost($pdo, $input),
    'PUT'    => handlePut($pdo, $input),
    'DELETE' => handleDelete($pdo, $input),
    default  => jsonResponse(false, 'Method not allowed', 405),
};

// ── GET — return all criteria ─────────────────────────────────────────────────

function handleGet(PDO $pdo): never
{
    try {
        $stmt = $pdo->prepare(
            'SELECT id, nama, bobot, tipe, created_at, updated_at
               FROM kriteria
              ORDER BY id ASC'
        );
        $stmt->execute();
        $rows = $stmt->fetchAll();

        // Cast types for clean JSON output
        $kriteria = array_map(static function (array $row): array {
            return [
                'id'         => (int)   $row['id'],
                'nama'       =>         $row['nama'],
                'bobot'      => (float) $row['bobot'],
                'tipe'       =>         $row['tipe'],
                'created_at' =>         $row['created_at'],
                'updated_at' =>         $row['updated_at'],
            ];
        }, $rows);

        jsonResponse(true, null, 200, $kriteria);
    } catch (PDOException $e) {
        handleDbError($e, 'api/kriteria.php GET');
    }
}

// ── POST — add a new criterion ────────────────────────────────────────────────

function handlePost(PDO $pdo, array $input): never
{
    [$nama, $bobot, $tipe, $error] = validateKriteriaInput($input);
    if ($error !== null) {
        jsonResponse(false, $error, 400);
    }

    try {
        // Check for duplicate name
        $stmtCheck = $pdo->prepare('SELECT id FROM kriteria WHERE nama = :nama LIMIT 1');
        $stmtCheck->execute([':nama' => $nama]);
        if ($stmtCheck->fetch() !== false) {
            jsonResponse(false, 'Nama kriteria sudah digunakan', 409);
        }

        $stmt = $pdo->prepare(
            'INSERT INTO kriteria (nama, bobot, tipe) VALUES (:nama, :bobot, :tipe)'
        );
        $stmt->execute([
            ':nama'  => $nama,
            ':bobot' => $bobot,
            ':tipe'  => $tipe,
        ]);

        $newId = (int) $pdo->lastInsertId();

        // Fetch the newly created row to return complete data
        $stmtFetch = $pdo->prepare(
            'SELECT id, nama, bobot, tipe, created_at, updated_at FROM kriteria WHERE id = :id'
        );
        $stmtFetch->execute([':id' => $newId]);
        $row = $stmtFetch->fetch();

        $data = [
            'id'         => (int)   $row['id'],
            'nama'       =>         $row['nama'],
            'bobot'      => (float) $row['bobot'],
            'tipe'       =>         $row['tipe'],
            'created_at' =>         $row['created_at'],
            'updated_at' =>         $row['updated_at'],
        ];

        jsonResponse(true, null, 200, $data);
    } catch (PDOException $e) {
        // MySQL duplicate-entry error code
        if ($e->getCode() === '23000') {
            jsonResponse(false, 'Nama kriteria sudah digunakan', 409);
        }
        handleDbError($e, 'api/kriteria.php POST');
    }
}

// ── PUT — update an existing criterion ───────────────────────────────────────

function handlePut(PDO $pdo, array $input): never
{
    $id = filter_var($input['id'] ?? null, FILTER_VALIDATE_INT);
    if ($id === false || $id === null || $id <= 0) {
        jsonResponse(false, 'ID kriteria tidak valid', 400);
    }

    [$nama, $bobot, $tipe, $error] = validateKriteriaInput($input);
    if ($error !== null) {
        jsonResponse(false, $error, 400);
    }

    try {
        // Ensure the record exists
        $stmtExist = $pdo->prepare('SELECT id FROM kriteria WHERE id = :id LIMIT 1');
        $stmtExist->execute([':id' => $id]);
        if ($stmtExist->fetch() === false) {
            jsonResponse(false, 'Kriteria tidak ditemukan', 404);
        }

        // Check for duplicate name (excluding the current record)
        $stmtCheck = $pdo->prepare(
            'SELECT id FROM kriteria WHERE nama = :nama AND id != :id LIMIT 1'
        );
        $stmtCheck->execute([':nama' => $nama, ':id' => $id]);
        if ($stmtCheck->fetch() !== false) {
            jsonResponse(false, 'Nama kriteria sudah digunakan', 409);
        }

        $stmt = $pdo->prepare(
            'UPDATE kriteria SET nama = :nama, bobot = :bobot, tipe = :tipe WHERE id = :id'
        );
        $stmt->execute([
            ':nama'  => $nama,
            ':bobot' => $bobot,
            ':tipe'  => $tipe,
            ':id'    => $id,
        ]);

        // Fetch the updated row
        $stmtFetch = $pdo->prepare(
            'SELECT id, nama, bobot, tipe, created_at, updated_at FROM kriteria WHERE id = :id'
        );
        $stmtFetch->execute([':id' => $id]);
        $row = $stmtFetch->fetch();

        $data = [
            'id'         => (int)   $row['id'],
            'nama'       =>         $row['nama'],
            'bobot'      => (float) $row['bobot'],
            'tipe'       =>         $row['tipe'],
            'created_at' =>         $row['created_at'],
            'updated_at' =>         $row['updated_at'],
        ];

        jsonResponse(true, null, 200, $data);
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            jsonResponse(false, 'Nama kriteria sudah digunakan', 409);
        }
        handleDbError($e, 'api/kriteria.php PUT');
    }
}

// ── DELETE — remove a criterion (cascade handled by FK) ──────────────────────

function handleDelete(PDO $pdo, array $input): never
{
    $id = filter_var($input['id'] ?? null, FILTER_VALIDATE_INT);
    if ($id === false || $id === null || $id <= 0) {
        jsonResponse(false, 'ID kriteria tidak valid', 400);
    }

    try {
        // Ensure the record exists before attempting deletion
        $stmtExist = $pdo->prepare('SELECT id FROM kriteria WHERE id = :id LIMIT 1');
        $stmtExist->execute([':id' => $id]);
        if ($stmtExist->fetch() === false) {
            jsonResponse(false, 'Kriteria tidak ditemukan', 404);
        }

        // The FK ON DELETE CASCADE on nilai_alternatif handles related rows automatically
        $stmt = $pdo->prepare('DELETE FROM kriteria WHERE id = :id');
        $stmt->execute([':id' => $id]);

        jsonResponse(true, null, 200, ['id' => $id]);
    } catch (PDOException $e) {
        handleDbError($e, 'api/kriteria.php DELETE');
    }
}

// validateKriteriaInput() is defined in src/helpers/kriteria.php
