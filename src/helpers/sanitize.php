<?php

/**
 * Input Sanitization Helper
 *
 * Provides functions to sanitize and validate user input before processing
 * or storing to the database.
 *
 * Requirements: 7.2, 7.3
 */

declare(strict_types=1);

/**
 * Sanitize a string input to prevent XSS.
 * Converts HTML special characters to their entity equivalents.
 *
 * @param string $input Raw user input
 * @return string       Sanitized string safe for HTML output
 */
function sanitizeString(string $input): string
{
    return htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * Validate and sanitize a numeric value.
 *
 * @param mixed $input Raw input value
 * @return float|false Parsed float on success, false if not a valid number
 */
function sanitizeNumeric(mixed $input): float|false
{
    if (is_bool($input)) {
        return false;
    }

    if (is_int($input) || is_float($input)) {
        return (float) $input;
    }

    if (!is_string($input)) {
        return false;
    }

    $trimmed = trim($input);

    if ($trimmed === '') {
        return false;
    }

    if (!is_numeric($trimmed)) {
        return false;
    }

    return (float) $trimmed;
}

/**
 * Sanitize all string values in an array recursively.
 *
 * @param array $input Raw input array
 * @return array       Array with all string values sanitized
 */
function sanitizeArray(array $input): array
{
    $result = [];

    foreach ($input as $key => $value) {
        if (is_array($value)) {
            $result[$key] = sanitizeArray($value);
        } elseif (is_string($value)) {
            $result[$key] = sanitizeString($value);
        } else {
            $result[$key] = $value;
        }
    }

    return $result;
}
