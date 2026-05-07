<?php

/**
 * Unit Tests — SPKEngine
 *
 * Tests the core SAW (Simple Additive Weighting) algorithm implementation:
 *   - normalize(): Benefit and Cost normalization
 *   - calculatePreference(): weighted sum of normalized values
 *   - rank(): descending sort with rank positions
 *
 * Requirements: 4.1, 4.2, 4.3
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Spk\Engine\SPKEngine;

class SPKEngineTest extends TestCase
{
    private SPKEngine $engine;

    protected function setUp(): void
    {
        $this->engine = new SPKEngine();
    }

    // ── normalize() — Benefit ─────────────────────────────────────────────────

    public function testNormalizeBenefitBasic(): void
    {
        $values = [1 => 80.0, 2 => 100.0, 3 => 60.0];
        $result = $this->engine->normalize($values, 'benefit');

        $this->assertEqualsWithDelta(0.8, $result[1], 1e-10);
        $this->assertEqualsWithDelta(1.0, $result[2], 1e-10);
        $this->assertEqualsWithDelta(0.6, $result[3], 1e-10);
    }

    public function testNormalizeBenefitMaxIsOne(): void
    {
        $values = [1 => 50.0, 2 => 200.0, 3 => 150.0];
        $result = $this->engine->normalize($values, 'benefit');

        // The alternative with the highest value must normalize to exactly 1.0
        $this->assertSame(1.0, $result[2]);
    }

    public function testNormalizeBenefitAllValuesInRange(): void
    {
        $values = [1 => 10.0, 2 => 40.0, 3 => 25.0, 4 => 5.0];
        $result = $this->engine->normalize($values, 'benefit');

        foreach ($result as $id => $normalized) {
            $this->assertGreaterThan(0.0, $normalized, "Normalized value for alt {$id} must be > 0");
            $this->assertLessThanOrEqual(1.0, $normalized, "Normalized value for alt {$id} must be <= 1");
        }
    }

    public function testNormalizeBenefitCaseInsensitive(): void
    {
        $values = [1 => 50.0, 2 => 100.0];
        $resultLower = $this->engine->normalize($values, 'benefit');
        $resultUpper = $this->engine->normalize($values, 'BENEFIT');
        $resultMixed = $this->engine->normalize($values, 'Benefit');

        $this->assertEquals($resultLower, $resultUpper);
        $this->assertEquals($resultLower, $resultMixed);
    }

    // ── normalize() — Cost ────────────────────────────────────────────────────

    public function testNormalizeCostBasic(): void
    {
        $values = [1 => 5.0, 2 => 10.0, 3 => 2.0];
        $result = $this->engine->normalize($values, 'cost');

        // min = 2.0
        $this->assertEqualsWithDelta(2.0 / 5.0, $result[1], 1e-10);
        $this->assertEqualsWithDelta(2.0 / 10.0, $result[2], 1e-10);
        $this->assertEqualsWithDelta(1.0, $result[3], 1e-10);
    }

    public function testNormalizeCostMinIsOne(): void
    {
        $values = [1 => 100.0, 2 => 30.0, 3 => 75.0];
        $result = $this->engine->normalize($values, 'cost');

        // The alternative with the lowest value must normalize to exactly 1.0
        $this->assertSame(1.0, $result[2]);
    }

    public function testNormalizeCostAllValuesInRange(): void
    {
        $values = [1 => 10.0, 2 => 40.0, 3 => 25.0, 4 => 5.0];
        $result = $this->engine->normalize($values, 'cost');

        foreach ($result as $id => $normalized) {
            $this->assertGreaterThan(0.0, $normalized, "Normalized value for alt {$id} must be > 0");
            $this->assertLessThanOrEqual(1.0, $normalized, "Normalized value for alt {$id} must be <= 1");
        }
    }

    public function testNormalizeCostThrowsOnZeroValue(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/tidak boleh nol/i');

        $values = [1 => 10.0, 2 => 0.0, 3 => 5.0];
        $this->engine->normalize($values, 'cost');
    }

    // ── normalize() — Edge cases ──────────────────────────────────────────────

    public function testNormalizeEmptyArrayReturnsEmpty(): void
    {
        $this->assertSame([], $this->engine->normalize([], 'benefit'));
        $this->assertSame([], $this->engine->normalize([], 'cost'));
    }

    public function testNormalizeAllEqualBenefit(): void
    {
        $values = [1 => 50.0, 2 => 50.0, 3 => 50.0];
        $result = $this->engine->normalize($values, 'benefit');

        foreach ($result as $normalized) {
            $this->assertEqualsWithDelta(1.0, $normalized, 1e-10);
        }
    }

    public function testNormalizeAllEqualCost(): void
    {
        $values = [1 => 30.0, 2 => 30.0, 3 => 30.0];
        $result = $this->engine->normalize($values, 'cost');

        foreach ($result as $normalized) {
            $this->assertEqualsWithDelta(1.0, $normalized, 1e-10);
        }
    }

    public function testNormalizeSingleElement(): void
    {
        $resultBenefit = $this->engine->normalize([5 => 42.0], 'benefit');
        $this->assertEqualsWithDelta(1.0, $resultBenefit[5], 1e-10);

        $resultCost = $this->engine->normalize([5 => 42.0], 'cost');
        $this->assertEqualsWithDelta(1.0, $resultCost[5], 1e-10);
    }

    public function testNormalizeInvalidTypeThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->engine->normalize([1 => 10.0], 'invalid');
    }

    public function testNormalizePreservesKeys(): void
    {
        $values = [10 => 80.0, 20 => 100.0, 30 => 60.0];
        $result = $this->engine->normalize($values, 'benefit');

        $this->assertArrayHasKey(10, $result);
        $this->assertArrayHasKey(20, $result);
        $this->assertArrayHasKey(30, $result);
    }

    // ── calculatePreference() ─────────────────────────────────────────────────

    public function testCalculatePreferenceBasic(): void
    {
        // 2 kriteria, 3 alternatif
        $normalizedMatrix = [
            1 => [1 => 0.8, 2 => 1.0, 3 => 0.6],
            2 => [1 => 1.0, 2 => 0.5, 3 => 0.75],
        ];
        $weights = [1 => 0.6, 2 => 0.4];

        $result = $this->engine->calculatePreference($normalizedMatrix, $weights);

        // Alt 1: 0.6*0.8 + 0.4*1.0 = 0.48 + 0.40 = 0.88
        $this->assertEqualsWithDelta(0.88, $result[1], 1e-10);
        // Alt 2: 0.6*1.0 + 0.4*0.5 = 0.60 + 0.20 = 0.80
        $this->assertEqualsWithDelta(0.80, $result[2], 1e-10);
        // Alt 3: 0.6*0.6 + 0.4*0.75 = 0.36 + 0.30 = 0.66
        $this->assertEqualsWithDelta(0.66, $result[3], 1e-10);
    }

    public function testCalculatePreferenceEmptyMatrixReturnsEmpty(): void
    {
        $this->assertSame([], $this->engine->calculatePreference([], [1 => 0.5]));
    }

    public function testCalculatePreferenceEmptyWeightsReturnsEmpty(): void
    {
        $matrix = [1 => [1 => 0.8, 2 => 1.0]];
        $this->assertSame([], $this->engine->calculatePreference($matrix, []));
    }

    public function testCalculatePreferenceMissingWeightTreatedAsZero(): void
    {
        // Kriteria 2 has no weight entry → treated as 0
        $normalizedMatrix = [
            1 => [1 => 1.0, 2 => 0.5],
            2 => [1 => 0.8, 2 => 1.0],
        ];
        $weights = [1 => 1.0]; // weight for kriteria 2 is missing

        $result = $this->engine->calculatePreference($normalizedMatrix, $weights);

        // Alt 1: 1.0*1.0 + 0.0*0.8 = 1.0
        $this->assertEqualsWithDelta(1.0, $result[1], 1e-10);
        // Alt 2: 1.0*0.5 + 0.0*1.0 = 0.5
        $this->assertEqualsWithDelta(0.5, $result[2], 1e-10);
    }

    public function testCalculatePreferencePreservesAltIds(): void
    {
        $normalizedMatrix = [
            1 => [10 => 0.8, 20 => 1.0],
        ];
        $weights = [1 => 1.0];

        $result = $this->engine->calculatePreference($normalizedMatrix, $weights);

        $this->assertArrayHasKey(10, $result);
        $this->assertArrayHasKey(20, $result);
    }

    // ── rank() ────────────────────────────────────────────────────────────────

    public function testRankDescendingOrder(): void
    {
        $preferences = [1 => 0.88, 2 => 0.66, 3 => 0.80];
        $result = $this->engine->rank($preferences);

        // Should be sorted: 0.88, 0.80, 0.66
        $this->assertSame(1, $result[0]['rank']);
        $this->assertEqualsWithDelta(0.88, $result[0]['preference'], 1e-10);
        $this->assertSame(1, $result[0]['id']);

        $this->assertSame(2, $result[1]['rank']);
        $this->assertEqualsWithDelta(0.80, $result[1]['preference'], 1e-10);
        $this->assertSame(3, $result[1]['id']);

        $this->assertSame(3, $result[2]['rank']);
        $this->assertEqualsWithDelta(0.66, $result[2]['preference'], 1e-10);
        $this->assertSame(2, $result[2]['id']);
    }

    public function testRankCountMatchesInput(): void
    {
        $preferences = [1 => 0.9, 2 => 0.7, 3 => 0.5, 4 => 0.8];
        $result = $this->engine->rank($preferences);

        $this->assertCount(4, $result);
    }

    public function testRankPositionsAreSequential(): void
    {
        $preferences = [1 => 0.9, 2 => 0.7, 3 => 0.5, 4 => 0.8];
        $result = $this->engine->rank($preferences);

        $ranks = array_column($result, 'rank');
        sort($ranks);
        $this->assertSame([1, 2, 3, 4], $ranks);
    }

    public function testRankEmptyReturnsEmpty(): void
    {
        $this->assertSame([], $this->engine->rank([]));
    }

    public function testRankSingleElement(): void
    {
        $result = $this->engine->rank([5 => 0.75]);

        $this->assertCount(1, $result);
        $this->assertSame(5, $result[0]['id']);
        $this->assertEqualsWithDelta(0.75, $result[0]['preference'], 1e-10);
        $this->assertSame(1, $result[0]['rank']);
    }

    public function testRankResultStructure(): void
    {
        $preferences = [1 => 0.9, 2 => 0.7];
        $result = $this->engine->rank($preferences);

        foreach ($result as $entry) {
            $this->assertArrayHasKey('id', $entry);
            $this->assertArrayHasKey('preference', $entry);
            $this->assertArrayHasKey('rank', $entry);
        }
    }

    public function testRankTieBreaking(): void
    {
        // arsort() is stable in PHP 8 — equal values keep their original order
        $preferences = [1 => 0.8, 2 => 0.8, 3 => 0.5];
        $result = $this->engine->rank($preferences);

        // Both alt 1 and alt 2 have the same preference; they should both appear
        // before alt 3, and all three should have distinct rank positions.
        $this->assertCount(3, $result);

        $ranks = array_column($result, 'rank');
        $this->assertSame([1, 2, 3], $ranks);

        // The last entry must be alt 3 (lowest preference)
        $this->assertSame(3, $result[2]['id']);
    }

    public function testRankPreferencesAreNonIncreasing(): void
    {
        $preferences = [1 => 0.3, 2 => 0.9, 3 => 0.6, 4 => 0.1, 5 => 0.75];
        $result = $this->engine->rank($preferences);

        for ($i = 0; $i < count($result) - 1; $i++) {
            $this->assertGreaterThanOrEqual(
                $result[$i + 1]['preference'],
                $result[$i]['preference'],
                "Preference at rank {$result[$i]['rank']} should be >= preference at rank {$result[$i+1]['rank']}"
            );
        }
    }

    // ── Performance ───────────────────────────────────────────────────────────

    public function testPerformanceLargeDataset(): void
    {
        // 100 alternatif × 20 kriteria should complete well under 3 seconds
        $altIds = range(1, 100);
        $kritIds = range(1, 20);

        $normalizedMatrix = [];
        foreach ($kritIds as $kId) {
            $row = [];
            foreach ($altIds as $aId) {
                $row[$aId] = mt_rand(1, 100) / 100.0;
            }
            $normalizedMatrix[$kId] = $row;
        }

        $weights = [];
        $remaining = 1.0;
        foreach ($kritIds as $i => $kId) {
            if ($i === count($kritIds) - 1) {
                $weights[$kId] = round($remaining, 4);
            } else {
                $w = round(mt_rand(1, 50) / 1000.0, 4);
                $weights[$kId] = $w;
                $remaining -= $w;
            }
        }

        $start = microtime(true);

        $preferences = $this->engine->calculatePreference($normalizedMatrix, $weights);
        $ranked = $this->engine->rank($preferences);

        $elapsed = microtime(true) - $start;

        $this->assertCount(100, $ranked);
        $this->assertLessThan(3.0, $elapsed, "Large dataset calculation took too long: {$elapsed}s");
    }
}
