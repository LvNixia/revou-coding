<?php

/**
 * Dashboard API Endpoint
 *
 * Returns a JSON summary of the current database state:
 *   - jumlah_kriteria: total active criteria
 *   - jumlah_alternatif: total alternatives
 *   - tanggal_hitung_terakhir: timestamp of the most recent calculation (or null)
 *
 * Method: GET
 * Auth:   requireAuth() — returns HTTP 401 if session is invalid
 *
 * Response format:
 *   { "success": true, "data": { "jumlah_kriteria": N, "jumlah_alternatif": N,
 *                                "tanggal_hitung_terakhir": "..." | null } }
 *
 * Requirements: 6.1, 6.3
 */

declare(strict_types=1);

// ── Bootstrap ────────────────────────────────────────────────────────────────

require_once __DIR__ . '/../src/middleware/auth_guard.php';
require_once __DIR__ . '/../config/database.php';

// Enforce authentication — returns HTTP 401 for API requests without a valid session
requireAuth();

// ── Method guard ─────────────────────────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// ── Payload size guard (max 1 MB) ─────────────────────────────────────────────

$contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
if ($contentLength > 1_048_576) {
    http_response_code(413);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'Payload terlalu besar']);
    exit;
}

// ── Database queries ──────────────────────────────────────────────────────────

try {
    $pdo = getDbConnection();

    // Count active criteria
    $stmtKriteria = $pdo->prepare('SELECT COUNT(*) AS jumlah FROM kriteria');
    $stmtKriteria->execute();
    $jumlahKriteria = (int) $stmtKriteria->fetchColumn();

    // Count alternatives
    $stmtAlternatif = $pdo->prepare('SELECT COUNT(*) AS jumlah FROM alternatif');
    $stmtAlternatif->execute();
    $jumlahAlternatif = (int) $stmtAlternatif->fetchColumn();

    // Most recent calculation timestamp
    $stmtRiwayat = $pdo->prepare('SELECT MAX(dihitung_pada) AS tanggal FROM riwayat_perhitungan');
    $stmtRiwayat->execute();
    $tanggalHitungTerakhir = $stmtRiwayat->fetchColumn() ?: null;

} catch (PDOException $e) {
    $logEntry = sprintf(
        "[%s] api/dashboard.php PDOException: %s in %s:%d\n",
        date('Y-m-d H:i:s'),
        $e->getMessage(),
        $e->getFile(),
        $e->getLine()
    );
    @error_log($logEntry, 3, __DIR__ . '/../logs/error.log');

    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'Terjadi kesalahan server. Silakan coba lagi.']);
    exit;
}

// ── Success response ──────────────────────────────────────────────────────────

http_response_code(200);
header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'success' => true,
    'data'    => [
        'jumlah_kriteria'        => $jumlahKriteria,
        'jumlah_alternatif'      => $jumlahAlternatif,
        'tanggal_hitung_terakhir' => $tanggalHitungTerakhir,
    ],
]);
