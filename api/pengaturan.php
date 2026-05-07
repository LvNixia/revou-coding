<?php

/**
 * Pengaturan (Settings) API Endpoint
 *
 * Manages system settings stored in the `pengaturan` key-value table.
 *
 * Methods:
 *   GET  — return all settings as a JSON object
 *   POST — update settings (nama_sistem, nama_organisasi, optional logo upload)
 *
 * Auth:   requireAuth()       — HTTP 401 if session is invalid
 * CSRF:   validateCsrfToken() — HTTP 403 for POST with bad token
 *
 * Logo upload rules:
 *   - Accepted MIME types : image/png, image/jpeg, image/svg+xml
 *   - Maximum file size   : 2 MB (2,097,152 bytes)
 *   - Saved to            : assets/uploads/
 *
 * Response format:
 *   { "success": true,  "data": { ... } }
 *   { "success": false, "message": "Pesan error" }
 *
 * HTTP status codes:
 *   200 — success
 *   400 — bad input / unsupported file format
 *   401 — unauthenticated
 *   403 — CSRF token invalid
 *   405 — method not allowed
 *   413 — file too large
 *   500 — server error
 *
 * Requirements: 8.1, 8.2, 8.4, 8.5
 */

declare(strict_types=1);

// ── Bootstrap ────────────────────────────────────────────────────────────────

require_once __DIR__ . '/../src/middleware/auth_guard.php';
require_once __DIR__ . '/../src/helpers/csrf.php';
require_once __DIR__ . '/../src/helpers/sanitize.php';
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

// ── Route to handler ──────────────────────────────────────────────────────────

$method = strtoupper($_SERVER['REQUEST_METHOD']);

// CSRF validation for write operations
if ($method === 'POST') {
    $csrfToken = (string) ($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!validateCsrfToken($csrfToken)) {
        jsonResponse(false, 'Token CSRF tidak valid', 403);
    }
}

try {
    $pdo = getDbConnection();
} catch (PDOException $e) {
    handleDbError($e, 'api/pengaturan.php bootstrap');
}

match ($method) {
    'GET'   => handleGet($pdo),
    'POST'  => handlePost($pdo),
    default => jsonResponse(false, 'Method not allowed', 405),
};

// ── GET — return all settings ─────────────────────────────────────────────────

function handleGet(PDO $pdo): never
{
    try {
        $stmt = $pdo->prepare('SELECT kunci, nilai FROM pengaturan ORDER BY kunci ASC');
        $stmt->execute();
        $rows = $stmt->fetchAll();

        // Convert key-value rows into a flat associative object for the response
        $data = [];
        foreach ($rows as $row) {
            $data[$row['kunci']] = $row['nilai'];
        }

        jsonResponse(true, null, 200, $data);
    } catch (PDOException $e) {
        handleDbError($e, 'api/pengaturan.php GET');
    }
}

// ── POST — update settings ────────────────────────────────────────────────────

function handlePost(PDO $pdo): never
{
    // ── Validate nama_sistem (required) ──────────────────────────────────────
    $namaSistem = trim($_POST['nama_sistem'] ?? '');
    if ($namaSistem === '') {
        jsonResponse(false, 'Nama sistem tidak boleh kosong', 400);
    }
    $namaSistem = sanitizeString($namaSistem);

    // ── Validate nama_organisasi (optional) ──────────────────────────────────
    $namaOrganisasi = sanitizeString(trim($_POST['nama_organisasi'] ?? ''));

    // ── Handle logo upload (optional) ────────────────────────────────────────
    $logoPath = null; // null means "no change"

    if (isset($_FILES['logo']) && $_FILES['logo']['error'] !== UPLOAD_ERR_NO_FILE) {
        $file = $_FILES['logo'];

        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            // UPLOAD_ERR_INI_SIZE or UPLOAD_ERR_FORM_SIZE indicate oversized file
            if (in_array($file['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
                jsonResponse(false, 'Ukuran file melebihi batas 2MB', 413);
            }
            jsonResponse(false, 'Gagal mengunggah file logo', 400);
        }

        // Validate file size (max 2 MB)
        $maxSize = 2_097_152; // 2 MB in bytes
        if ($file['size'] > $maxSize) {
            jsonResponse(false, 'Ukuran file melebihi batas 2MB', 413);
        }

        // Validate MIME type using finfo (more reliable than client-supplied type)
        $allowedMimes = ['image/png', 'image/jpeg', 'image/svg+xml'];
        $finfo        = new finfo(FILEINFO_MIME_TYPE);
        $detectedMime = $finfo->file($file['tmp_name']);

        // SVG files may be detected as text/html or text/xml by finfo; also accept
        // the client-supplied type for SVG as a fallback since it is text-based.
        $clientMime = strtolower($file['type'] ?? '');
        $isSvg      = ($detectedMime === 'image/svg+xml')
                   || ($detectedMime === 'text/html' && $clientMime === 'image/svg+xml')
                   || ($detectedMime === 'text/xml'  && $clientMime === 'image/svg+xml')
                   || ($detectedMime === 'application/xml' && $clientMime === 'image/svg+xml');

        if (!in_array($detectedMime, $allowedMimes, true) && !$isSvg) {
            jsonResponse(false, 'Format file tidak didukung. Gunakan PNG, JPG, atau SVG', 400);
        }

        // Determine file extension from MIME type
        $extMap = [
            'image/png'       => 'png',
            'image/jpeg'      => 'jpg',
            'image/svg+xml'   => 'svg',
            'text/html'       => 'svg',
            'text/xml'        => 'svg',
            'application/xml' => 'svg',
        ];
        $ext = $extMap[$detectedMime] ?? 'png';

        // Generate a unique filename to avoid collisions
        $filename  = 'logo_' . bin2hex(random_bytes(8)) . '.' . $ext;
        $uploadDir = __DIR__ . '/../assets/uploads/';
        $destPath  = $uploadDir . $filename;

        // Ensure the upload directory exists
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            jsonResponse(false, 'Gagal menyimpan file logo', 500);
        }

        // Store the relative path for use in HTML/CSS
        $logoPath = 'assets/uploads/' . $filename;
    }

    // ── Persist settings to database ─────────────────────────────────────────
    try {
        $pdo->beginTransaction();

        // Upsert nama_sistem
        $stmt = $pdo->prepare(
            'INSERT INTO pengaturan (kunci, nilai) VALUES (:kunci, :nilai)
             ON DUPLICATE KEY UPDATE nilai = VALUES(nilai)'
        );
        $stmt->execute([':kunci' => 'nama_sistem', ':nilai' => $namaSistem]);

        // Upsert nama_organisasi
        $stmt->execute([':kunci' => 'nama_organisasi', ':nilai' => $namaOrganisasi]);

        // Upsert logo_path only when a new logo was uploaded
        if ($logoPath !== null) {
            $stmt->execute([':kunci' => 'logo_path', ':nilai' => $logoPath]);
        }

        $pdo->commit();

        // Return the current state of all settings after the update
        $stmtFetch = $pdo->prepare('SELECT kunci, nilai FROM pengaturan ORDER BY kunci ASC');
        $stmtFetch->execute();
        $rows = $stmtFetch->fetchAll();

        $data = [];
        foreach ($rows as $row) {
            $data[$row['kunci']] = $row['nilai'];
        }

        jsonResponse(true, null, 200, $data);
    } catch (PDOException $e) {
        $pdo->rollBack();
        handleDbError($e, 'api/pengaturan.php POST');
    }
}
