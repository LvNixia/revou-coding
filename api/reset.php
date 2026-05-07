<?php

/**
 * Reset Data API Endpoint
 *
 * Menghapus seluruh data alternatif dan hasil perhitungan dari database.
 * Tabel yang TIDAK dihapus: kriteria, users, pengaturan.
 *
 * Method: POST only
 *
 * Auth:   requireAuth()       — HTTP 401 jika sesi tidak valid
 * CSRF:   validateCsrfToken() — HTTP 403 jika token tidak valid
 *
 * Request body (application/x-www-form-urlencoded atau JSON):
 *   csrf_token   — CSRF token
 *   confirmation — harus berisi string "RESET"
 *
 * Response format:
 *   { "success": true,  "message": "..." }
 *   { "success": false, "message": "Pesan error" }
 *
 * HTTP status codes:
 *   200 — berhasil
 *   400 — konfirmasi tidak valid
 *   401 — tidak terautentikasi
 *   403 — token CSRF tidak valid
 *   405 — method tidak diizinkan
 *   500 — kesalahan server
 *
 * Tabel yang dihapus (urutan memperhatikan foreign key):
 *   1. nilai_normalisasi
 *   2. hasil_perhitungan
 *   3. riwayat_perhitungan
 *   4. nilai_alternatif
 *   5. alternatif
 *
 * Requirements: 8.3
 */

declare(strict_types=1);

// ── Bootstrap ────────────────────────────────────────────────────────────────

require_once __DIR__ . '/../src/middleware/auth_guard.php';
require_once __DIR__ . '/../src/helpers/csrf.php';
require_once __DIR__ . '/../config/database.php';

// Enforce authentication — returns HTTP 401 for API requests without a valid session
requireAuth();

// ── Helpers ───────────────────────────────────────────────────────────────────

/**
 * Send a JSON response and terminate execution.
 *
 * @param bool        $success Whether the operation succeeded
 * @param string      $message Human-readable message
 * @param int         $status  HTTP status code
 */
function jsonResponse(bool $success, string $message, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => $success, 'message' => $message]);
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

// ── Method guard ──────────────────────────────────────────────────────────────

$method = strtoupper($_SERVER['REQUEST_METHOD']);

if ($method !== 'POST') {
    jsonResponse(false, 'Method not allowed', 405);
}

// ── CSRF validation ───────────────────────────────────────────────────────────

$csrfToken = (string) ($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if (!validateCsrfToken($csrfToken)) {
    jsonResponse(false, 'Token CSRF tidak valid', 403);
}

// ── Confirmation validation ───────────────────────────────────────────────────

$confirmation = trim($_POST['confirmation'] ?? '');
if ($confirmation !== 'RESET') {
    jsonResponse(false, 'Konfirmasi tidak valid. Ketik "RESET" untuk melanjutkan.', 400);
}

// ── Execute reset ─────────────────────────────────────────────────────────────

try {
    $pdo = getDbConnection();
} catch (PDOException $e) {
    handleDbError($e, 'api/reset.php bootstrap');
}

try {
    $pdo->beginTransaction();

    // Disable foreign key checks temporarily to allow truncation in any order
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

    // Delete all calculation-related and alternative data
    // Order: child tables first, then parent tables
    $pdo->exec('DELETE FROM nilai_normalisasi');
    $pdo->exec('DELETE FROM hasil_perhitungan');
    $pdo->exec('DELETE FROM riwayat_perhitungan');
    $pdo->exec('DELETE FROM nilai_alternatif');
    $pdo->exec('DELETE FROM alternatif');

    // Re-enable foreign key checks
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

    $pdo->commit();

    jsonResponse(true, 'Semua data alternatif dan hasil perhitungan berhasil dihapus.', 200);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    // Ensure FK checks are re-enabled even on error
    try { $pdo->exec('SET FOREIGN_KEY_CHECKS = 1'); } catch (PDOException) {}
    handleDbError($e, 'api/reset.php DELETE');
}
