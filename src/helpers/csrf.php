<?php

/**
 * CSRF Token Helper
 *
 * Provides functions to generate and validate CSRF tokens stored in the PHP session.
 * Tokens are 32-byte hex strings (64 characters) generated with CSPRNG.
 *
 * Requirements: 7.5
 */

declare(strict_types=1);

/**
 * Generate a new CSRF token and store it in the session.
 *
 * If a token already exists in the session it is reused, so calling this
 * function multiple times within the same request is safe.
 *
 * @return string CSRF token (64-character hex string)
 */
function generateCsrfToken(): string
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

/**
 * Validate a CSRF token against the one stored in the session.
 *
 * Uses hash_equals() to prevent timing attacks.
 *
 * @param string $token Token submitted by the client (from form field or header)
 * @return bool         True if the token matches, false otherwise
 */
function validateCsrfToken(string $token): bool
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (empty($_SESSION['csrf_token']) || $token === '') {
        return false;
    }

    return hash_equals($_SESSION['csrf_token'], $token);
}
