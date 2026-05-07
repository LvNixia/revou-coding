<?php

/**
 * Bobot (Weight) Validation Helper
 *
 * Provides a reusable function to validate that the sum of all kriteria bobot
 * values stored in the database equals exactly 1.00 (within a floating-point
 * tolerance of ±0.001).
 *
 * This validation is required before any SAW calculation is executed.
 *
 * Requirements: 2.5
 */

declare(strict_types=1);

/** Allowed floating-point tolerance when comparing total bobot to 1.00. */
const BOBOT_TOLERANCE = 0.001;

/**
 * Validate that the sum of all kriteria bobot values equals 1.00.
 *
 * Queries the `kriteria` table and sums the `bobot` column.
 * Returns true when the total is within ±BOBOT_TOLERANCE of 1.00.
 * Returns the error message string when the total is outside that range.
 *
 * @param PDO $pdo Active database connection
 * @return true|string  true on success, error message string on failure
 *
 * @throws PDOException if the database query fails
 */
function validateBobotTotal(PDO $pdo): true|string
{
    $stmt = $pdo->prepare('SELECT COALESCE(SUM(bobot), 0) AS total FROM kriteria');
    $stmt->execute();
    $row   = $stmt->fetch();
    $total = (float) $row['total'];

    if (abs($total - 1.0) <= BOBOT_TOLERANCE) {
        return true;
    }

    return 'Total bobot kriteria harus sama dengan 1.00';
}
