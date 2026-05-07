<?php

/**
 * Riwayat Perhitungan API Endpoint
 *
 * Returns a JSON list of all calculation history records, ordered by
 * dihitung_pada DESC (newest first).
 *
 * Method: GET
 * Auth:   requireAuth() — returns HTTP 401 if session is invalid
 *
 * Response format:
 *   {
 *     "success": true,
 *     "data": [
 *       {
 *         "id": 1,
 *         "dihitung_pada": "2024-01-15 10:30:00",
 *         "jumlah_kriteria": 3,
 *         "jumlah_alternatif": 5,
 *         "catatan": null
 *       },
 *       ...
 *     ]
 *   }
 *
 * Requirements: 5.6
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

// ── Database query ────────────────────────────────────────────────────────────

try {
    $pdo = getDbConnection();

    $stmt = $pdo->prepare(
        'SELECT id, dihitung_pada, jumlah_kriteria, jumlah_alternatif, catatan
         FROM riwayat_perhitungan
         ORDER BY dihitung_pada DESC'
    );
    $stmt->execute();
    $rows = $stmt->fetchAll();

    // Cast numeric fields to proper types
    $data = array_map(function (array $row): array {
        return [
            'id'                => (int) $row['id'],
            'dihitung_pada'     => $row['dihitung_pada'],
            'jumlah_kriteria'   => (int) $row['jumlah_kriteria'],
            'jumlah_alternatif' => (int) $row['jumlah_alternatif'],
            'catatan'           => $row['catatan'],
        ];
    }, $rows);

} catch (PDOException $e) {
    $logEntry = sprintf(
        "[%s] api/riwayat.php PDOException: %s in %s:%d\n",
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
    'data'    => $data,
]);
