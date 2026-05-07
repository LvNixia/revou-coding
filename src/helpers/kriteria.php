<?php

/**
 * Kriteria Input Validation Helper
 *
 * Provides a reusable function to validate and sanitize the common kriteria
 * fields (nama, bobot, tipe) before they are persisted to the database.
 *
 * Extracted from api/kriteria.php so it can be unit-tested independently
 * without triggering HTTP-layer side effects.
 *
 * Requirements: 2.1, 2.7
 */

declare(strict_types=1);

/**
 * Validate and sanitize the common kriteria fields (nama, bobot, tipe).
 *
 * Validation rules:
 *   - nama  : non-empty string, max 100 characters
 *   - bobot : float in [0.01, 1.00]
 *   - tipe  : 'benefit' or 'cost' (case-insensitive, stored lowercase)
 *
 * @param array $input Raw input array (e.g. from $_POST or json_decode)
 * @return array       [string $nama, float $bobot, string $tipe, string|null $error]
 *                     $error is null when validation passes.
 */
function validateKriteriaInput(array $input): array
{
    // ── nama ──────────────────────────────────────────────────────────────────
    $namaRaw = $input['nama'] ?? null;
    if (!is_string($namaRaw) || trim($namaRaw) === '') {
        return ['', 0.0, '', 'Nama kriteria tidak boleh kosong'];
    }
    $nama = sanitizeString(trim($namaRaw));
    if (strlen($nama) > 100) {
        return ['', 0.0, '', 'Nama kriteria maksimal 100 karakter'];
    }

    // ── bobot ─────────────────────────────────────────────────────────────────
    $bobotRaw = $input['bobot'] ?? null;
    $bobot    = sanitizeNumeric($bobotRaw);
    if ($bobot === false) {
        return ['', 0.0, '', 'Bobot harus berupa angka'];
    }
    if ($bobot < 0.01 || $bobot > 1.00) {
        return ['', 0.0, '', 'Bobot harus berada dalam rentang 0.01 hingga 1.00'];
    }

    // ── tipe ──────────────────────────────────────────────────────────────────
    $tipeRaw = $input['tipe'] ?? null;
    if (!is_string($tipeRaw)) {
        return ['', 0.0, '', 'Tipe kriteria tidak valid'];
    }
    $tipe = strtolower(trim($tipeRaw));
    if (!in_array($tipe, ['benefit', 'cost'], true)) {
        return ['', 0.0, '', 'Tipe kriteria harus "benefit" atau "cost"'];
    }

    return [$nama, $bobot, $tipe, null];
}
