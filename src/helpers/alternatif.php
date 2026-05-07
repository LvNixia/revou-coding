<?php

/**
 * Alternatif Input Validation Helper
 *
 * Provides a reusable function to validate and sanitize the alternatif fields
 * (nama, nilai) before they are persisted to the database.
 *
 * Extracted from api/alternatif.php so it can be unit-tested independently
 * without triggering HTTP-layer side effects.
 *
 * Requirements: 3.1, 3.2, 3.3, 3.7
 */

declare(strict_types=1);

/**
 * Validate and sanitize the alternatif fields (nama, nilai).
 *
 * Validation rules:
 *   - nama  : non-empty string, max 100 characters
 *   - nilai : associative array of kriteria_id => numeric value
 *             (may be empty array — caller decides if all criteria must be present)
 *
 * @param array $input Raw input array (e.g. from $_POST or json_decode)
 * @return array       [string $nama, array $nilai, string|null $error]
 *                     $error is null when validation passes.
 *                     $nilai is [int kriteria_id => float nilai, ...]
 */
function validateAlternatifInput(array $input): array
{
    // ── nama ──────────────────────────────────────────────────────────────────
    $namaRaw = $input['nama'] ?? null;
    if (!is_string($namaRaw) || trim($namaRaw) === '') {
        return ['', [], 'Nama alternatif tidak boleh kosong'];
    }
    $nama = sanitizeString(trim($namaRaw));
    if (strlen($nama) > 100) {
        return ['', [], 'Nama alternatif maksimal 100 karakter'];
    }

    // ── nilai ─────────────────────────────────────────────────────────────────
    $nilaiRaw = $input['nilai'] ?? null;
    if (!is_array($nilaiRaw)) {
        return ['', [], 'Nilai kriteria harus berupa objek/array'];
    }

    $nilai = [];
    foreach ($nilaiRaw as $kriteriaId => $nilaiVal) {
        // Validate kriteria_id is a positive integer
        $kid = filter_var($kriteriaId, FILTER_VALIDATE_INT);
        if ($kid === false || $kid <= 0) {
            return ['', [], 'ID kriteria tidak valid: ' . $kriteriaId];
        }

        // Validate nilai is numeric
        $parsed = sanitizeNumeric($nilaiVal);
        if ($parsed === false) {
            return ['', [], 'Nilai untuk kriteria ID ' . $kid . ' harus berupa angka'];
        }

        $nilai[(int) $kid] = $parsed;
    }

    return [$nama, $nilai, null];
}
