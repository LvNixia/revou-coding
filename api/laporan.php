<?php

/**
 * Laporan PDF API Endpoint
 *
 * Generates and streams a PDF report for a given calculation session.
 *
 * Method:
 *   GET ?riwayat_id=X
 *
 * Auth:   requireAuth() — HTTP 401 if session is invalid
 *
 * The PDF contains:
 *   1. Title: "Laporan Hasil Perhitungan SAW"
 *   2. System name and organisation name (from pengaturan table)
 *   3. Date/time of calculation
 *   4. Summary: number of criteria, number of alternatives
 *   5. Criteria table: No, Nama, Bobot, Tipe
 *   6. Alternatives table: No, Nama, [nilai per kriteria]
 *   7. Normalisation matrix table
 *   8. Ranking table: No, Nama, Nilai Preferensi, Ranking
 *
 * HTTP status codes:
 *   200 — PDF streamed successfully
 *   400 — missing or invalid riwayat_id
 *   401 — unauthenticated
 *   404 — riwayat_id not found
 *   500 — server error
 *
 * Requirements: 5.3
 */

declare(strict_types=1);

// ── Bootstrap ────────────────────────────────────────────────────────────────

require_once __DIR__ . '/../src/middleware/auth_guard.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../vendor/autoload.php';

// Enforce authentication
requireAuth();

// ── Helpers ───────────────────────────────────────────────────────────────────

/**
 * Send a JSON error response and terminate.
 */
function errorResponse(string $message, int $status): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

// ── Only GET is accepted ──────────────────────────────────────────────────────

$method = strtoupper($_SERVER['REQUEST_METHOD']);
if ($method !== 'GET') {
    errorResponse('Method not allowed', 405);
}

// ── Validate riwayat_id parameter ────────────────────────────────────────────

$riwayatIdRaw = $_GET['riwayat_id'] ?? '';
if ($riwayatIdRaw === '' || !ctype_digit((string) $riwayatIdRaw)) {
    errorResponse('Parameter riwayat_id tidak valid', 400);
}
$riwayatId = (int) $riwayatIdRaw;

// ── Database connection ───────────────────────────────────────────────────────

try {
    $pdo = getDbConnection();
} catch (PDOException $e) {
    $entry = sprintf(
        "[%s] api/laporan.php bootstrap PDOException: %s in %s:%d\n",
        date('Y-m-d H:i:s'),
        $e->getMessage(),
        $e->getFile(),
        $e->getLine()
    );
    @error_log($entry, 3, __DIR__ . '/../logs/error.log');
    errorResponse('Terjadi kesalahan server. Silakan coba lagi.', 500);
}

// ── Fetch system settings ─────────────────────────────────────────────────────

$settings = [];
try {
    $stmtSettings = $pdo->query('SELECT kunci, nilai FROM pengaturan');
    foreach ($stmtSettings->fetchAll() as $row) {
        $settings[$row['kunci']] = $row['nilai'];
    }
} catch (PDOException $e) {
    // Non-fatal — fall back to defaults
}

$namaSistem      = $settings['nama_sistem']      ?? 'SPK - Sistem Pendukung Keputusan';
$namaOrganisasi  = $settings['nama_organisasi']  ?? '';

// ── Fetch riwayat_perhitungan ─────────────────────────────────────────────────

try {
    $stmtRiwayat = $pdo->prepare(
        'SELECT id, dihitung_pada, jumlah_kriteria, jumlah_alternatif
         FROM riwayat_perhitungan
         WHERE id = :id'
    );
    $stmtRiwayat->execute([':id' => $riwayatId]);
    $riwayat = $stmtRiwayat->fetch();
} catch (PDOException $e) {
    errorResponse('Terjadi kesalahan server. Silakan coba lagi.', 500);
}

if (!$riwayat) {
    errorResponse('Data riwayat perhitungan tidak ditemukan', 404);
}

// ── Fetch kriteria ────────────────────────────────────────────────────────────

try {
    $stmtKriteria = $pdo->prepare('SELECT id, nama, bobot, tipe FROM kriteria ORDER BY id');
    $stmtKriteria->execute();
    $kriteriaRows = $stmtKriteria->fetchAll();
} catch (PDOException $e) {
    errorResponse('Terjadi kesalahan server. Silakan coba lagi.', 500);
}

// Build kriteria map [id => row]
$kriteriaMap = [];
foreach ($kriteriaRows as $row) {
    $kriteriaMap[(int) $row['id']] = $row;
}

// ── Fetch alternatif ──────────────────────────────────────────────────────────

try {
    $stmtAlt = $pdo->prepare('SELECT id, nama FROM alternatif ORDER BY id');
    $stmtAlt->execute();
    $alternatifRows = $stmtAlt->fetchAll();
} catch (PDOException $e) {
    errorResponse('Terjadi kesalahan server. Silakan coba lagi.', 500);
}

// Build alternatif map [id => nama]
$alternatifMap = [];
foreach ($alternatifRows as $row) {
    $alternatifMap[(int) $row['id']] = $row['nama'];
}

// ── Fetch nilai_alternatif ────────────────────────────────────────────────────

try {
    $stmtNilai = $pdo->prepare(
        'SELECT alternatif_id, kriteria_id, nilai FROM nilai_alternatif'
    );
    $stmtNilai->execute();
    $nilaiRows = $stmtNilai->fetchAll();
} catch (PDOException $e) {
    errorResponse('Terjadi kesalahan server. Silakan coba lagi.', 500);
}

// Build nilai map [alt_id][krit_id] => nilai
$nilaiMap = [];
foreach ($nilaiRows as $row) {
    $aid = (int) $row['alternatif_id'];
    $kid = (int) $row['kriteria_id'];
    $nilaiMap[$aid][$kid] = (float) $row['nilai'];
}

// ── Fetch hasil_perhitungan for this riwayat ──────────────────────────────────

try {
    $stmtHasil = $pdo->prepare(
        'SELECT alternatif_id, nilai_preferensi, ranking
         FROM hasil_perhitungan
         WHERE riwayat_id = :rid
         ORDER BY ranking ASC'
    );
    $stmtHasil->execute([':rid' => $riwayatId]);
    $hasilRows = $stmtHasil->fetchAll();
} catch (PDOException $e) {
    errorResponse('Terjadi kesalahan server. Silakan coba lagi.', 500);
}

// ── Fetch nilai_normalisasi for this riwayat ──────────────────────────────────

try {
    $stmtNorm = $pdo->prepare(
        'SELECT alternatif_id, kriteria_id, nilai_normal
         FROM nilai_normalisasi
         WHERE riwayat_id = :rid'
    );
    $stmtNorm->execute([':rid' => $riwayatId]);
    $normRows = $stmtNorm->fetchAll();
} catch (PDOException $e) {
    errorResponse('Terjadi kesalahan server. Silakan coba lagi.', 500);
}

// Build normalisasi map [alt_id][krit_id] => nilai_normal
$normMap = [];
foreach ($normRows as $row) {
    $aid = (int) $row['alternatif_id'];
    $kid = (int) $row['kriteria_id'];
    $normMap[$aid][$kid] = (float) $row['nilai_normal'];
}

// ── Build HTML content for PDF ────────────────────────────────────────────────

// Pre-assign riwayat fields for clean heredoc interpolation
$riwayatJumlahKriteria   = (int) $riwayat['jumlah_kriteria'];
$riwayatJumlahAlternatif = (int) $riwayat['jumlah_alternatif'];

// Format timestamp
$dihitungPada = $riwayat['dihitung_pada'];
$tsFormatted  = '';
if ($dihitungPada) {
    $dt = new DateTime($dihitungPada);
    $dt->setTimezone(new DateTimeZone('Asia/Jakarta'));
    $bulan = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    ];
    $tsFormatted = $dt->format('d') . ' ' . $bulan[(int) $dt->format('n')] . ' ' .
                   $dt->format('Y') . ', ' . $dt->format('H:i') . ' WIB';
}

/**
 * Escape a value for safe HTML output.
 */
function h(mixed $val): string
{
    return htmlspecialchars((string) $val, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// Collect kriteria IDs in order
$kriteriaIds = array_keys($kriteriaMap);

// Build ranking list sorted by rank
$rankingList = [];
foreach ($hasilRows as $row) {
    $aid = (int) $row['alternatif_id'];
    $rankingList[] = [
        'id'         => $aid,
        'nama'       => $alternatifMap[$aid] ?? '—',
        'preferensi' => (float) $row['nilai_preferensi'],
        'ranking'    => (int) $row['ranking'],
    ];
}

// ── Criteria table HTML ───────────────────────────────────────────────────────

$kriteriaTableRows = '';
$no = 1;
foreach ($kriteriaRows as $row) {
    $tipeLabel = $row['tipe'] === 'benefit' ? 'Benefit' : 'Cost';
    $kriteriaTableRows .= '<tr>' .
        '<td class="center">' . $no++ . '</td>' .
        '<td>' . h($row['nama']) . '</td>' .
        '<td class="center">' . number_format((float) $row['bobot'], 4) . '</td>' .
        '<td class="center">' . h($tipeLabel) . '</td>' .
        '</tr>';
}

// ── Alternatives table HTML ───────────────────────────────────────────────────

$altTableHeader = '<th>No</th><th>Nama Alternatif</th>';
foreach ($kriteriaRows as $row) {
    $altTableHeader .= '<th>' . h($row['nama']) . '</th>';
}

$altTableRows = '';
$no = 1;
foreach ($alternatifRows as $altRow) {
    $aid = (int) $altRow['id'];
    $altTableRows .= '<tr>' .
        '<td class="center">' . $no++ . '</td>' .
        '<td>' . h($altRow['nama']) . '</td>';
    foreach ($kriteriaIds as $kid) {
        $val = $nilaiMap[$aid][$kid] ?? null;
        $altTableRows .= '<td class="center">' .
            ($val !== null ? number_format($val, 2) : '—') .
            '</td>';
    }
    $altTableRows .= '</tr>';
}

// ── Normalisation matrix table HTML ──────────────────────────────────────────

$normTableHeader = '<th>No</th><th>Nama Alternatif</th>';
foreach ($kriteriaRows as $row) {
    $normTableHeader .= '<th>' . h($row['nama']) . '</th>';
}

$normTableRows = '';
$no = 1;
foreach ($rankingList as $entry) {
    $aid = $entry['id'];
    $normTableRows .= '<tr>' .
        '<td class="center">' . $no++ . '</td>' .
        '<td>' . h($entry['nama']) . '</td>';
    foreach ($kriteriaIds as $kid) {
        $val = $normMap[$aid][$kid] ?? null;
        $normTableRows .= '<td class="center">' .
            ($val !== null ? number_format($val, 4) : '—') .
            '</td>';
    }
    $normTableRows .= '</tr>';
}

// ── Ranking table HTML ────────────────────────────────────────────────────────

$rankingTableRows = '';
$no = 1;
foreach ($rankingList as $entry) {
    $isTop   = $entry['ranking'] === 1;
    $rowAttr = $isTop ? ' class="top-row"' : '';
    $rankingTableRows .= '<tr' . $rowAttr . '>' .
        '<td class="center">' . $no++ . '</td>' .
        '<td>' . h($entry['nama']) . ($isTop ? ' ★' : '') . '</td>' .
        '<td class="center">' . number_format($entry['preferensi'], 4) . '</td>' .
        '<td class="center">' . $entry['ranking'] . '</td>' .
        '</tr>';
}

// ── Assemble full HTML ────────────────────────────────────────────────────────

$html = <<<HTML
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<style>
    body {
        font-family: 'DejaVu Sans', Arial, sans-serif;
        font-size: 10pt;
        color: #1a1a1a;
        margin: 0;
        padding: 0;
    }
    .header-block {
        text-align: center;
        border-bottom: 2px solid #1e40af;
        padding-bottom: 10px;
        margin-bottom: 16px;
    }
    .header-block h1 {
        font-size: 15pt;
        font-weight: bold;
        color: #1e40af;
        margin: 0 0 4px 0;
    }
    .header-block p {
        font-size: 9pt;
        color: #555;
        margin: 2px 0;
    }
    .section-title {
        font-size: 11pt;
        font-weight: bold;
        color: #1e40af;
        border-bottom: 1px solid #93c5fd;
        padding-bottom: 3px;
        margin: 18px 0 8px 0;
    }
    .summary-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 8px;
    }
    .summary-table td {
        padding: 4px 8px;
        font-size: 9.5pt;
    }
    .summary-table td:first-child {
        font-weight: bold;
        width: 40%;
        color: #374151;
    }
    table.data-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 9pt;
        margin-bottom: 8px;
    }
    table.data-table th {
        background-color: #1e40af;
        color: #ffffff;
        padding: 5px 6px;
        text-align: center;
        font-weight: bold;
        border: 1px solid #1e3a8a;
    }
    table.data-table td {
        padding: 4px 6px;
        border: 1px solid #d1d5db;
        vertical-align: middle;
    }
    table.data-table tr:nth-child(even) td {
        background-color: #f0f4ff;
    }
    table.data-table tr.top-row td {
        background-color: #dcfce7;
        font-weight: bold;
        color: #166534;
    }
    td.center {
        text-align: center;
    }
    .footer-note {
        font-size: 8pt;
        color: #9ca3af;
        text-align: right;
        margin-top: 20px;
        border-top: 1px solid #e5e7eb;
        padding-top: 6px;
    }
</style>
</head>
<body>

<div class="header-block">
    <h1>Laporan Hasil Perhitungan SAW</h1>
    <p>{$namaSistem}</p>
    {$namaOrganisasiLine}
</div>

<div class="section-title">Ringkasan Perhitungan</div>
<table class="summary-table">
    <tr><td>Tanggal Perhitungan</td><td>: {$tsFormatted}</td></tr>
    <tr><td>Jumlah Kriteria</td><td>: {$riwayatJumlahKriteria}</td></tr>
    <tr><td>Jumlah Alternatif</td><td>: {$riwayatJumlahAlternatif}</td></tr>
</table>

<div class="section-title">Data Kriteria</div>
<table class="data-table">
    <thead>
        <tr><th>No</th><th>Nama Kriteria</th><th>Bobot</th><th>Tipe</th></tr>
    </thead>
    <tbody>
        {$kriteriaTableRows}
    </tbody>
</table>

<div class="section-title">Data Alternatif</div>
<table class="data-table">
    <thead>
        <tr>{$altTableHeader}</tr>
    </thead>
    <tbody>
        {$altTableRows}
    </tbody>
</table>

<div class="section-title">Matriks Normalisasi</div>
<table class="data-table">
    <thead>
        <tr>{$normTableHeader}</tr>
    </thead>
    <tbody>
        {$normTableRows}
    </tbody>
</table>

<div class="section-title">Tabel Ranking</div>
<table class="data-table">
    <thead>
        <tr><th>No</th><th>Nama Alternatif</th><th>Nilai Preferensi</th><th>Ranking</th></tr>
    </thead>
    <tbody>
        {$rankingTableRows}
    </tbody>
</table>

<div class="footer-note">
    Laporan dibuat otomatis oleh sistem pada {$tsFormatted}
</div>

</body>
</html>
HTML;

// Inject optional organisation line
$namaOrganisasiLine = $namaOrganisasi !== ''
    ? '<p>' . h($namaOrganisasi) . '</p>'
    : '';

$html = str_replace('{$namaOrganisasiLine}', $namaOrganisasiLine, $html);

// ── Generate PDF with mPDF ────────────────────────────────────────────────────

try {
    $mpdf = new \Mpdf\Mpdf([
        'mode'          => 'utf-8',
        'format'        => 'A4',
        'margin_top'    => 15,
        'margin_bottom' => 15,
        'margin_left'   => 15,
        'margin_right'  => 15,
        'tempDir'       => sys_get_temp_dir(),
    ]);

    $mpdf->SetTitle('Laporan Hasil Perhitungan SAW');
    $mpdf->SetAuthor($namaSistem);
    $mpdf->SetCreator($namaSistem);

    $mpdf->WriteHTML($html);

    // Stream the PDF as a download
    $filename = 'laporan-saw-' . $riwayatId . '-' . date('Ymd') . '.pdf';
    $mpdf->Output($filename, \Mpdf\Output\Destination::DOWNLOAD);
} catch (Throwable $e) {
    $entry = sprintf(
        "[%s] api/laporan.php mPDF error: %s in %s:%d\n",
        date('Y-m-d H:i:s'),
        $e->getMessage(),
        $e->getFile(),
        $e->getLine()
    );
    @error_log($entry, 3, __DIR__ . '/../logs/error.log');
    errorResponse('Gagal membuat laporan PDF. Silakan coba lagi.', 500);
}
