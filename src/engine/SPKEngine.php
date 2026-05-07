<?php

declare(strict_types=1);

namespace Spk\Engine;

use InvalidArgumentException;

/**
 * SPKEngine — Implementasi algoritma SAW (Simple Additive Weighting).
 *
 * Pure class tanpa dependency ke database, sehingga mudah diuji secara unit.
 */
class SPKEngine
{
    /**
     * Normalisasi matriks keputusan per kriteria.
     *
     * Benefit: r_ij = nilai_ij / max(nilai_j)
     * Cost:    r_ij = min(nilai_j) / nilai_ij
     *
     * Edge cases:
     * - Array kosong → kembalikan array kosong.
     * - Semua nilai sama → semua nilai ternormalisasi = 1.0.
     * - Nilai = 0 pada tipe Cost → lempar InvalidArgumentException (division by zero).
     *
     * @param array  $values Array nilai alternatif untuk satu kriteria [alt_id => nilai]
     * @param string $type   'benefit' atau 'cost'
     * @return array         Array nilai ternormalisasi [alt_id => nilai_normal]
     *
     * @throws InvalidArgumentException Jika $type tidak valid atau nilai Cost = 0.
     */
    public function normalize(array $values, string $type): array
    {
        if (empty($values)) {
            return [];
        }

        $type = strtolower($type);
        if ($type !== 'benefit' && $type !== 'cost') {
            throw new InvalidArgumentException(
                "Tipe kriteria tidak valid: '{$type}'. Gunakan 'benefit' atau 'cost'."
            );
        }

        $normalized = [];

        if ($type === 'benefit') {
            $max = max($values);

            // Jika max = 0, semua nilai adalah 0 → normalisasi = 1.0 (semua sama)
            if ($max == 0) {
                foreach ($values as $id => $val) {
                    $normalized[$id] = 1.0;
                }
            } else {
                foreach ($values as $id => $val) {
                    $normalized[$id] = (float) $val / (float) $max;
                }
            }
        } else {
            // Cost
            foreach ($values as $id => $val) {
                if ($val == 0) {
                    throw new InvalidArgumentException(
                        "Nilai kriteria Cost tidak boleh nol (alt_id: {$id})."
                    );
                }
            }

            $min = min($values);

            foreach ($values as $id => $val) {
                $normalized[$id] = (float) $min / (float) $val;
            }
        }

        return $normalized;
    }

    /**
     * Hitung nilai preferensi setiap alternatif.
     *
     * V_i = sum(w_j * r_ij) untuk semua kriteria j
     *
     * @param array $normalizedMatrix [kriteria_id => [alt_id => nilai_normal]]
     * @param array $weights          [kriteria_id => bobot]
     * @return array                  [alt_id => nilai_preferensi]
     */
    public function calculatePreference(array $normalizedMatrix, array $weights): array
    {
        if (empty($normalizedMatrix) || empty($weights)) {
            return [];
        }

        $preferences = [];

        foreach ($normalizedMatrix as $kriteriaId => $altValues) {
            $weight = isset($weights[$kriteriaId]) ? (float) $weights[$kriteriaId] : 0.0;

            foreach ($altValues as $altId => $normalizedValue) {
                if (!isset($preferences[$altId])) {
                    $preferences[$altId] = 0.0;
                }
                $preferences[$altId] += $weight * (float) $normalizedValue;
            }
        }

        return $preferences;
    }

    /**
     * Urutkan alternatif dari nilai preferensi tertinggi ke terendah.
     *
     * @param array $preferences [alt_id => nilai_preferensi]
     * @return array             Array terurut:
     *                           [['id' => alt_id, 'preference' => nilai, 'rank' => posisi], ...]
     */
    public function rank(array $preferences): array
    {
        if (empty($preferences)) {
            return [];
        }

        // Salin agar tidak memodifikasi array asli
        $sorted = $preferences;

        // Urutkan dari tertinggi ke terendah, pertahankan key (alt_id)
        arsort($sorted);

        $result = [];
        $rankPosition = 1;

        foreach ($sorted as $altId => $preference) {
            $result[] = [
                'id'         => $altId,
                'preference' => (float) $preference,
                'rank'       => $rankPosition,
            ];
            $rankPosition++;
        }

        return $result;
    }
}
