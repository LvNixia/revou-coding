<?php

/**
 * End-to-End Tests — SPKEngine (alur lengkap SAW)
 *
 * Menguji alur lengkap dari input data mentah hingga hasil ranking:
 *   input data → normalize() → calculatePreference() → rank()
 *
 * Setiap test case menyertakan perhitungan manual sebagai referensi
 * untuk memverifikasi kebenaran hasil.
 *
 * Requirements: 4.1, 4.2, 4.3
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Spk\Engine\SPKEngine;

class SPKEngineEndToEndTest extends TestCase
{
    private SPKEngine $engine;

    protected function setUp(): void
    {
        $this->engine = new SPKEngine();
    }

    // ── Helper ────────────────────────────────────────────────────────────────

    /**
     * Jalankan alur lengkap SAW dari data mentah.
     *
     * @param array $kriteria  [id => ['bobot' => float, 'tipe' => string]]
     * @param array $alternatif [alt_id => [kriteria_id => nilai]]
     * @return array  Hasil rank(): [['id', 'preference', 'rank'], ...]
     */
    private function runSAW(array $kriteria, array $alternatif): array
    {
        // Langkah 1 — Susun matriks nilai per kriteria
        $matriksNilai = [];
        foreach ($kriteria as $kId => $k) {
            $matriksNilai[$kId] = [];
            foreach ($alternatif as $aId => $nilaiPerKriteria) {
                $matriksNilai[$kId][$aId] = $nilaiPerKriteria[$kId];
            }
        }

        // Langkah 2 — Normalisasi setiap kriteria
        $normalizedMatrix = [];
        foreach ($kriteria as $kId => $k) {
            $normalizedMatrix[$kId] = $this->engine->normalize(
                $matriksNilai[$kId],
                $k['tipe']
            );
        }

        // Langkah 3 — Hitung preferensi
        $weights = array_map(fn($k) => $k['bobot'], $kriteria);
        $preferences = $this->engine->calculatePreference($normalizedMatrix, $weights);

        // Langkah 4 — Ranking
        return $this->engine->rank($preferences);
    }

    // ── Test Case 1: Contoh dari spesifikasi tugas ────────────────────────────
    //
    // Kriteria:
    //   K1 — Harga,   bobot=0.4, tipe=cost
    //   K2 — Kualitas, bobot=0.6, tipe=benefit
    //
    // Alternatif:
    //   A: Harga=100, Kualitas=80
    //   B: Harga=50,  Kualitas=60
    //   C: Harga=75,  Kualitas=90
    //
    // Normalisasi Harga (cost, min=50):
    //   A = 50/100 = 0.5
    //   B = 50/50  = 1.0
    //   C = 50/75  ≈ 0.6667
    //
    // Normalisasi Kualitas (benefit, max=90):
    //   A = 80/90 ≈ 0.8889
    //   B = 60/90 ≈ 0.6667
    //   C = 90/90 = 1.0
    //
    // Preferensi:
    //   A = 0.4*0.5    + 0.6*0.8889 = 0.2    + 0.5333 = 0.7333
    //   B = 0.4*1.0    + 0.6*0.6667 = 0.4    + 0.4    = 0.8
    //   C = 0.4*0.6667 + 0.6*1.0    = 0.2667 + 0.6    = 0.8667
    //
    // Ranking: C(1), B(2), A(3)

    public function testEndToEndSpecExample(): void
    {
        $kriteria = [
            1 => ['bobot' => 0.4, 'tipe' => 'cost'],
            2 => ['bobot' => 0.6, 'tipe' => 'benefit'],
        ];
        $alternatif = [
            'A' => [1 => 100.0, 2 => 80.0],
            'B' => [1 => 50.0,  2 => 60.0],
            'C' => [1 => 75.0,  2 => 90.0],
        ];

        $result = $this->runSAW($kriteria, $alternatif);

        // Jumlah alternatif harus 3
        $this->assertCount(3, $result);

        // Ranking 1 → C
        $this->assertSame('C', $result[0]['id']);
        $this->assertSame(1, $result[0]['rank']);
        $this->assertEqualsWithDelta(0.8667, $result[0]['preference'], 1e-3);

        // Ranking 2 → B
        $this->assertSame('B', $result[1]['id']);
        $this->assertSame(2, $result[1]['rank']);
        $this->assertEqualsWithDelta(0.8, $result[1]['preference'], 1e-10);

        // Ranking 3 → A
        $this->assertSame('A', $result[2]['id']);
        $this->assertSame(3, $result[2]['rank']);
        $this->assertEqualsWithDelta(0.7333, $result[2]['preference'], 1e-3);
    }

    public function testEndToEndSpecExampleRankingOrder(): void
    {
        // Verifikasi bahwa urutan preferensi non-increasing (Req 4.3)
        $kriteria = [
            1 => ['bobot' => 0.4, 'tipe' => 'cost'],
            2 => ['bobot' => 0.6, 'tipe' => 'benefit'],
        ];
        $alternatif = [
            'A' => [1 => 100.0, 2 => 80.0],
            'B' => [1 => 50.0,  2 => 60.0],
            'C' => [1 => 75.0,  2 => 90.0],
        ];

        $result = $this->runSAW($kriteria, $alternatif);

        for ($i = 0; $i < count($result) - 1; $i++) {
            $this->assertGreaterThanOrEqual(
                $result[$i + 1]['preference'],
                $result[$i]['preference'],
                "Preferensi pada rank {$result[$i]['rank']} harus >= rank {$result[$i+1]['rank']}"
            );
        }
    }

    // ── Test Case 2: Semua kriteria benefit ──────────────────────────────────
    //
    // Kriteria:
    //   K1 — Nilai Akademik, bobot=0.5, tipe=benefit
    //   K2 — Pengalaman,     bobot=0.5, tipe=benefit
    //
    // Alternatif:
    //   X: K1=90, K2=70
    //   Y: K1=60, K2=100
    //   Z: K1=80, K2=80
    //
    // Normalisasi K1 (benefit, max=90):
    //   X = 90/90 = 1.0
    //   Y = 60/90 ≈ 0.6667
    //   Z = 80/90 ≈ 0.8889
    //
    // Normalisasi K2 (benefit, max=100):
    //   X = 70/100 = 0.7
    //   Y = 100/100 = 1.0
    //   Z = 80/100 = 0.8
    //
    // Preferensi:
    //   X = 0.5*1.0    + 0.5*0.7  = 0.5   + 0.35  = 0.85
    //   Y = 0.5*0.6667 + 0.5*1.0  = 0.3333 + 0.5  = 0.8333
    //   Z = 0.5*0.8889 + 0.5*0.8  = 0.4444 + 0.4  = 0.8444
    //
    // Ranking: X(1), Z(2), Y(3)

    public function testEndToEndAllBenefit(): void
    {
        $kriteria = [
            1 => ['bobot' => 0.5, 'tipe' => 'benefit'],
            2 => ['bobot' => 0.5, 'tipe' => 'benefit'],
        ];
        $alternatif = [
            'X' => [1 => 90.0, 2 => 70.0],
            'Y' => [1 => 60.0, 2 => 100.0],
            'Z' => [1 => 80.0, 2 => 80.0],
        ];

        $result = $this->runSAW($kriteria, $alternatif);

        $this->assertCount(3, $result);

        // Ranking 1 → X
        $this->assertSame('X', $result[0]['id']);
        $this->assertSame(1, $result[0]['rank']);
        $this->assertEqualsWithDelta(0.85, $result[0]['preference'], 1e-10);

        // Ranking 2 → Z
        $this->assertSame('Z', $result[1]['id']);
        $this->assertSame(2, $result[1]['rank']);
        $this->assertEqualsWithDelta(0.8444, $result[1]['preference'], 1e-3);

        // Ranking 3 → Y
        $this->assertSame('Y', $result[2]['id']);
        $this->assertSame(3, $result[2]['rank']);
        $this->assertEqualsWithDelta(0.8333, $result[2]['preference'], 1e-3);
    }

    // ── Test Case 3: Semua kriteria cost ─────────────────────────────────────
    //
    // Kriteria:
    //   K1 — Biaya,  bobot=0.6, tipe=cost
    //   K2 — Waktu,  bobot=0.4, tipe=cost
    //
    // Alternatif:
    //   P: K1=200, K2=10
    //   Q: K1=100, K2=20
    //   R: K1=150, K2=5
    //
    // Normalisasi K1 (cost, min=100):
    //   P = 100/200 = 0.5
    //   Q = 100/100 = 1.0
    //   R = 100/150 ≈ 0.6667
    //
    // Normalisasi K2 (cost, min=5):
    //   P = 5/10 = 0.5
    //   Q = 5/20 = 0.25
    //   R = 5/5  = 1.0
    //
    // Preferensi:
    //   P = 0.6*0.5    + 0.4*0.5  = 0.3    + 0.2   = 0.5
    //   Q = 0.6*1.0    + 0.4*0.25 = 0.6    + 0.1   = 0.7
    //   R = 0.6*0.6667 + 0.4*1.0  = 0.4    + 0.4   = 0.8
    //
    // Ranking: R(1), Q(2), P(3)

    public function testEndToEndAllCost(): void
    {
        $kriteria = [
            1 => ['bobot' => 0.6, 'tipe' => 'cost'],
            2 => ['bobot' => 0.4, 'tipe' => 'cost'],
        ];
        $alternatif = [
            'P' => [1 => 200.0, 2 => 10.0],
            'Q' => [1 => 100.0, 2 => 20.0],
            'R' => [1 => 150.0, 2 => 5.0],
        ];

        $result = $this->runSAW($kriteria, $alternatif);

        $this->assertCount(3, $result);

        // Ranking 1 → R
        $this->assertSame('R', $result[0]['id']);
        $this->assertSame(1, $result[0]['rank']);
        $this->assertEqualsWithDelta(0.8, $result[0]['preference'], 1e-3);

        // Ranking 2 → Q
        $this->assertSame('Q', $result[1]['id']);
        $this->assertSame(2, $result[1]['rank']);
        $this->assertEqualsWithDelta(0.7, $result[1]['preference'], 1e-10);

        // Ranking 3 → P
        $this->assertSame('P', $result[2]['id']);
        $this->assertSame(3, $result[2]['rank']);
        $this->assertEqualsWithDelta(0.5, $result[2]['preference'], 1e-10);
    }

    // ── Test Case 4: Tiga kriteria campuran (benefit + cost + benefit) ────────
    //
    // Kriteria:
    //   K1 — Performa, bobot=0.3, tipe=benefit
    //   K2 — Harga,    bobot=0.5, tipe=cost
    //   K3 — Garansi,  bobot=0.2, tipe=benefit
    //
    // Alternatif:
    //   M1: K1=85, K2=300, K3=24
    //   M2: K1=70, K2=200, K3=12
    //   M3: K1=90, K2=250, K3=36
    //
    // Normalisasi K1 (benefit, max=90):
    //   M1 = 85/90 ≈ 0.9444
    //   M2 = 70/90 ≈ 0.7778
    //   M3 = 90/90 = 1.0
    //
    // Normalisasi K2 (cost, min=200):
    //   M1 = 200/300 ≈ 0.6667
    //   M2 = 200/200 = 1.0
    //   M3 = 200/250 = 0.8
    //
    // Normalisasi K3 (benefit, max=36):
    //   M1 = 24/36 ≈ 0.6667
    //   M2 = 12/36 ≈ 0.3333
    //   M3 = 36/36 = 1.0
    //
    // Preferensi:
    //   M1 = 0.3*0.9444 + 0.5*0.6667 + 0.2*0.6667
    //      = 0.2833     + 0.3333     + 0.1333     = 0.75
    //   M2 = 0.3*0.7778 + 0.5*1.0    + 0.2*0.3333
    //      = 0.2333     + 0.5        + 0.0667     = 0.8
    //   M3 = 0.3*1.0    + 0.5*0.8    + 0.2*1.0
    //      = 0.3        + 0.4        + 0.2        = 0.9
    //
    // Ranking: M3(1), M2(2), M1(3)

    public function testEndToEndThreeCriteriaMixed(): void
    {
        $kriteria = [
            1 => ['bobot' => 0.3, 'tipe' => 'benefit'],
            2 => ['bobot' => 0.5, 'tipe' => 'cost'],
            3 => ['bobot' => 0.2, 'tipe' => 'benefit'],
        ];
        $alternatif = [
            'M1' => [1 => 85.0, 2 => 300.0, 3 => 24.0],
            'M2' => [1 => 70.0, 2 => 200.0, 3 => 12.0],
            'M3' => [1 => 90.0, 2 => 250.0, 3 => 36.0],
        ];

        $result = $this->runSAW($kriteria, $alternatif);

        $this->assertCount(3, $result);

        // Ranking 1 → M3
        $this->assertSame('M3', $result[0]['id']);
        $this->assertSame(1, $result[0]['rank']);
        $this->assertEqualsWithDelta(0.9, $result[0]['preference'], 1e-10);

        // Ranking 2 → M2
        $this->assertSame('M2', $result[1]['id']);
        $this->assertSame(2, $result[1]['rank']);
        $this->assertEqualsWithDelta(0.8, $result[1]['preference'], 1e-3);

        // Ranking 3 → M1
        $this->assertSame('M1', $result[2]['id']);
        $this->assertSame(3, $result[2]['rank']);
        $this->assertEqualsWithDelta(0.75, $result[2]['preference'], 1e-3);
    }

    // ── Test Case 5: Dua alternatif — kasus minimal ───────────────────────────
    //
    // Kriteria:
    //   K1 — Skor, bobot=1.0, tipe=benefit
    //
    // Alternatif:
    //   Alpha: K1=80
    //   Beta:  K1=40
    //
    // Normalisasi K1 (benefit, max=80):
    //   Alpha = 80/80 = 1.0
    //   Beta  = 40/80 = 0.5
    //
    // Preferensi:
    //   Alpha = 1.0*1.0 = 1.0
    //   Beta  = 1.0*0.5 = 0.5
    //
    // Ranking: Alpha(1), Beta(2)

    public function testEndToEndTwoAlternatifSingleKriteria(): void
    {
        $kriteria = [
            1 => ['bobot' => 1.0, 'tipe' => 'benefit'],
        ];
        $alternatif = [
            'Alpha' => [1 => 80.0],
            'Beta'  => [1 => 40.0],
        ];

        $result = $this->runSAW($kriteria, $alternatif);

        $this->assertCount(2, $result);

        $this->assertSame('Alpha', $result[0]['id']);
        $this->assertSame(1, $result[0]['rank']);
        $this->assertEqualsWithDelta(1.0, $result[0]['preference'], 1e-10);

        $this->assertSame('Beta', $result[1]['id']);
        $this->assertSame(2, $result[1]['rank']);
        $this->assertEqualsWithDelta(0.5, $result[1]['preference'], 1e-10);
    }

    // ── Test Case 6: Semua nilai sama → semua preferensi sama ────────────────
    //
    // Kriteria:
    //   K1 — Nilai, bobot=0.5, tipe=benefit
    //   K2 — Biaya, bobot=0.5, tipe=cost
    //
    // Alternatif (semua nilai identik):
    //   D1: K1=50, K2=100
    //   D2: K1=50, K2=100
    //   D3: K1=50, K2=100
    //
    // Normalisasi K1 (benefit, max=50): semua = 1.0
    // Normalisasi K2 (cost, min=100):  semua = 1.0
    //
    // Preferensi: semua = 0.5*1.0 + 0.5*1.0 = 1.0
    //
    // Ranking: semua rank berbeda (1, 2, 3) karena arsort() mempertahankan urutan

    public function testEndToEndAllIdenticalValues(): void
    {
        $kriteria = [
            1 => ['bobot' => 0.5, 'tipe' => 'benefit'],
            2 => ['bobot' => 0.5, 'tipe' => 'cost'],
        ];
        $alternatif = [
            'D1' => [1 => 50.0, 2 => 100.0],
            'D2' => [1 => 50.0, 2 => 100.0],
            'D3' => [1 => 50.0, 2 => 100.0],
        ];

        $result = $this->runSAW($kriteria, $alternatif);

        $this->assertCount(3, $result);

        // Semua preferensi harus 1.0
        foreach ($result as $entry) {
            $this->assertEqualsWithDelta(1.0, $entry['preference'], 1e-10);
        }

        // Rank harus berurutan 1, 2, 3
        $ranks = array_column($result, 'rank');
        sort($ranks);
        $this->assertSame([1, 2, 3], $ranks);
    }

    // ── Test Case 7: Verifikasi struktur output end-to-end ────────────────────

    public function testEndToEndOutputStructure(): void
    {
        $kriteria = [
            1 => ['bobot' => 0.4, 'tipe' => 'cost'],
            2 => ['bobot' => 0.6, 'tipe' => 'benefit'],
        ];
        $alternatif = [
            'A' => [1 => 100.0, 2 => 80.0],
            'B' => [1 => 50.0,  2 => 60.0],
            'C' => [1 => 75.0,  2 => 90.0],
        ];

        $result = $this->runSAW($kriteria, $alternatif);

        // Setiap entry harus memiliki key: id, preference, rank
        foreach ($result as $entry) {
            $this->assertArrayHasKey('id', $entry, 'Entry harus memiliki key "id"');
            $this->assertArrayHasKey('preference', $entry, 'Entry harus memiliki key "preference"');
            $this->assertArrayHasKey('rank', $entry, 'Entry harus memiliki key "rank"');
        }

        // Rank harus berurutan mulai dari 1
        $ranks = array_column($result, 'rank');
        $this->assertSame(1, min($ranks));
        $this->assertSame(count($result), max($ranks));
    }

    // ── Test Case 8: Preferensi dalam rentang [0, 1] ─────────────────────────

    public function testEndToEndPreferenceInValidRange(): void
    {
        $kriteria = [
            1 => ['bobot' => 0.4, 'tipe' => 'cost'],
            2 => ['bobot' => 0.6, 'tipe' => 'benefit'],
        ];
        $alternatif = [
            'A' => [1 => 100.0, 2 => 80.0],
            'B' => [1 => 50.0,  2 => 60.0],
            'C' => [1 => 75.0,  2 => 90.0],
        ];

        $result = $this->runSAW($kriteria, $alternatif);

        foreach ($result as $entry) {
            $this->assertGreaterThanOrEqual(
                0.0,
                $entry['preference'],
                "Preferensi alt {$entry['id']} harus >= 0"
            );
            $this->assertLessThanOrEqual(
                1.0,
                $entry['preference'],
                "Preferensi alt {$entry['id']} harus <= 1"
            );
        }
    }
}
