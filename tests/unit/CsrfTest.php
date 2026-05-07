<?php

/**
 * Unit Tests — CSRF Token Helper
 *
 * Tests the generateCsrfToken() and validateCsrfToken() functions defined in
 * src/helpers/csrf.php.
 *
 * These tests do NOT make HTTP requests or database connections.
 * They exercise the CSRF helper functions directly via the session.
 *
 * Requirements: 7.5
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * @covers generateCsrfToken
 * @covers validateCsrfToken
 */
class CsrfTest extends TestCase
{
    // ── Setup / Teardown ──────────────────────────────────────────────────────

    protected function setUp(): void
    {
        // Start a fresh session for each test so tokens don't bleed between tests
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        session_start();
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    // ── Token generation tests ────────────────────────────────────────────────

    /**
     * generateCsrfToken() must return a non-empty string.
     *
     * Requirements: 7.5
     */
    public function testTokenGeneration(): void
    {
        $token = generateCsrfToken();

        $this->assertNotEmpty(
            $token,
            'generateCsrfToken() must return a non-empty string'
        );
    }

    /**
     * The generated token must be a valid hexadecimal string.
     *
     * Requirements: 7.5
     */
    public function testTokenIsHexString(): void
    {
        $token = generateCsrfToken();

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]+$/i',
            $token,
            'CSRF token must consist only of hexadecimal characters'
        );
    }

    /**
     * The generated token must be exactly 64 characters long
     * (32 bytes × 2 hex chars per byte).
     *
     * Requirements: 7.5
     */
    public function testTokenLength(): void
    {
        $token = generateCsrfToken();

        $this->assertSame(
            64,
            strlen($token),
            'CSRF token must be exactly 64 characters (32 bytes as hex)'
        );
    }

    // ── Token validation tests ────────────────────────────────────────────────

    /**
     * validateCsrfToken() must return true when the correct token is provided.
     *
     * Requirements: 7.5
     */
    public function testValidTokenAccepted(): void
    {
        $token = generateCsrfToken();

        $this->assertTrue(
            validateCsrfToken($token),
            'validateCsrfToken() must return true for the correct token'
        );
    }

    /**
     * validateCsrfToken() must return false when an incorrect token is provided.
     *
     * Requirements: 7.5
     */
    public function testInvalidTokenRejected(): void
    {
        generateCsrfToken(); // Ensure a token is stored in the session

        $wrongToken = str_repeat('a', 64); // A valid-looking but wrong token

        $this->assertFalse(
            validateCsrfToken($wrongToken),
            'validateCsrfToken() must return false for an incorrect token'
        );
    }

    /**
     * validateCsrfToken() must return false when an empty string is provided.
     *
     * Requirements: 7.5
     */
    public function testEmptyTokenRejected(): void
    {
        generateCsrfToken(); // Ensure a token is stored in the session

        $this->assertFalse(
            validateCsrfToken(''),
            'validateCsrfToken() must return false for an empty string'
        );
    }

    /**
     * Calling generateCsrfToken() twice within the same session must return
     * the same token (token is reused, not regenerated).
     *
     * Requirements: 7.5
     */
    public function testTokenReusedWithinSession(): void
    {
        $firstCall  = generateCsrfToken();
        $secondCall = generateCsrfToken();

        $this->assertSame(
            $firstCall,
            $secondCall,
            'generateCsrfToken() must return the same token when called multiple times in the same session'
        );
    }

    /**
     * A token generated in one session must not validate against a different session.
     *
     * Requirements: 7.5
     */
    public function testTokenFromDifferentSessionRejected(): void
    {
        // Generate a token in the current session
        $tokenFromSession1 = generateCsrfToken();

        // Destroy the session and start a new one (simulating a different session)
        session_destroy();
        session_start();
        $_SESSION = [];

        // Generate a new token for the new session
        generateCsrfToken();

        // The old token must not be valid in the new session
        $this->assertFalse(
            validateCsrfToken($tokenFromSession1),
            'A token from a previous session must not be valid in a new session'
        );
    }

    /**
     * validateCsrfToken() must return false when no token has been generated yet.
     *
     * Requirements: 7.5
     */
    public function testValidationFailsWithNoTokenInSession(): void
    {
        // No generateCsrfToken() call — session has no csrf_token
        $this->assertFalse(
            validateCsrfToken('anytoken'),
            'validateCsrfToken() must return false when no token exists in the session'
        );
    }
}
