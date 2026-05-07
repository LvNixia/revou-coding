<?php

/**
 * Unit Tests — Authentication Logic
 *
 * Tests password hashing/verification (bcrypt), session lifecycle, and the
 * isSessionExpired() / requireAuth() guard functions defined in
 * src/middleware/auth_guard.php.
 *
 * These tests do NOT make HTTP requests or database connections.
 * They exercise the helper functions directly.
 *
 * Requirements: 1.1, 1.2, 1.3, 1.4, 1.5
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * @covers isSessionExpired
 * @covers refreshSession
 * @covers requireAuth
 */
class AuthTest extends TestCase
{
    // ── Setup / Teardown ──────────────────────────────────────────────────────

    protected function setUp(): void
    {
        // Start a fresh session for each test
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        // Use a memory-based session handler so no files are written
        session_set_save_handler(new \SessionHandler(), true);
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

    // ── Password hashing tests ────────────────────────────────────────────────

    /**
     * bcrypt hash + verify round-trip must succeed.
     *
     * Requirements: 1.6
     */
    public function testPasswordHashAndVerify(): void
    {
        $plaintext = 'SecureP@ssw0rd!';
        $hash      = password_hash($plaintext, PASSWORD_BCRYPT);

        $this->assertTrue(
            password_verify($plaintext, $hash),
            'password_verify() must return true for the correct plaintext'
        );
    }

    /**
     * Verifying with the wrong password must return false.
     *
     * Requirements: 1.2
     */
    public function testPasswordVerifyFailsWithWrongPassword(): void
    {
        $hash = password_hash('CorrectPassword', PASSWORD_BCRYPT);

        $this->assertFalse(
            password_verify('WrongPassword', $hash),
            'password_verify() must return false for an incorrect password'
        );
    }

    /**
     * The stored hash must never equal the plaintext password.
     *
     * Requirements: 1.6
     */
    public function testPasswordIsNotStoredAsPlaintext(): void
    {
        $plaintext = 'MyPlaintextPassword';
        $hash      = password_hash($plaintext, PASSWORD_BCRYPT);

        $this->assertNotEquals(
            $plaintext,
            $hash,
            'The bcrypt hash must not equal the original plaintext'
        );
    }

    // ── Session creation tests ────────────────────────────────────────────────

    /**
     * After a successful login the session variables must be set correctly.
     *
     * Requirements: 1.3
     */
    public function testSessionCreation(): void
    {
        // Simulate what api/auth.php does after credential verification
        $_SESSION['user_id']       = 1;
        $_SESSION['username']      = 'admin';
        $_SESSION['last_activity'] = time();

        $this->assertArrayHasKey('user_id', $_SESSION);
        $this->assertArrayHasKey('username', $_SESSION);
        $this->assertArrayHasKey('last_activity', $_SESSION);

        $this->assertSame(1, $_SESSION['user_id']);
        $this->assertSame('admin', $_SESSION['username']);
        $this->assertIsInt($_SESSION['last_activity']);
    }

    // ── Session expiry tests ──────────────────────────────────────────────────

    /**
     * isSessionExpired() must return true when last_activity is > 60 minutes ago.
     *
     * Requirements: 1.3
     */
    public function testSessionExpiry(): void
    {
        // Set last_activity to 61 minutes in the past
        $_SESSION['last_activity'] = time() - (SESSION_TIMEOUT + 60);

        $this->assertTrue(
            isSessionExpired(),
            'isSessionExpired() must return true after 60+ minutes of inactivity'
        );
    }

    /**
     * isSessionExpired() must return false when last_activity is within 60 minutes.
     *
     * Requirements: 1.3
     */
    public function testSessionNotExpiredWithinTimeout(): void
    {
        // Set last_activity to 30 minutes ago (well within the 60-minute window)
        $_SESSION['last_activity'] = time() - 1800;

        $this->assertFalse(
            isSessionExpired(),
            'isSessionExpired() must return false within the 60-minute inactivity window'
        );
    }

    /**
     * isSessionExpired() must return true when last_activity is missing.
     *
     * Requirements: 1.3
     */
    public function testSessionExpiredWhenLastActivityMissing(): void
    {
        // Ensure last_activity is not set
        unset($_SESSION['last_activity']);

        $this->assertTrue(
            isSessionExpired(),
            'isSessionExpired() must return true when last_activity is not set'
        );
    }

    // ── Session destruction / logout tests ───────────────────────────────────

    /**
     * After logout the session variables must be cleared.
     *
     * Requirements: 1.4
     */
    public function testSessionDestroyedOnLogout(): void
    {
        // Simulate an active session
        $_SESSION['user_id']       = 1;
        $_SESSION['username']      = 'admin';
        $_SESSION['last_activity'] = time();

        // Simulate what api/logout.php does
        session_unset();
        session_destroy();

        // After destroy, $_SESSION should be empty
        $this->assertEmpty(
            $_SESSION,
            'All session variables must be cleared after logout'
        );
    }

    // ── Unauthenticated redirect tests ────────────────────────────────────────

    /**
     * A session without user_id must be considered unauthenticated.
     *
     * requireAuth() calls exit() which cannot be tested directly in PHPUnit.
     * Instead we verify the guard conditions that requireAuth() checks:
     *   - user_id must be set
     *   - last_activity must be set
     *   - session must not be expired
     *
     * Requirements: 1.5, 7.4
     */
    public function testRedirectForUnauthenticatedUser(): void
    {
        // No session variables set — session is invalid
        $_SESSION = [];

        // Replicate the validity check from requireAuth()
        $valid = isset($_SESSION['user_id'])
              && isset($_SESSION['last_activity'])
              && !isSessionExpired();

        $this->assertFalse(
            $valid,
            'A session without user_id must be considered invalid (unauthenticated)'
        );
    }

    /**
     * A session with user_id but without last_activity must be considered invalid.
     *
     * Requirements: 1.5
     */
    public function testSessionWithoutLastActivityIsInvalid(): void
    {
        $_SESSION['user_id'] = 1;
        // last_activity intentionally not set

        $valid = isset($_SESSION['user_id'])
              && isset($_SESSION['last_activity'])
              && !isSessionExpired();

        $this->assertFalse(
            $valid,
            'A session without last_activity must be considered invalid'
        );
    }

    /**
     * isApiRequest() must return true when Accept header contains application/json.
     *
     * Requirements: 7.4
     */
    public function testIsApiRequestDetectsJsonAcceptHeader(): void
    {
        $_SERVER['HTTP_ACCEPT'] = 'application/json';
        $this->assertTrue(
            isApiRequest(),
            'isApiRequest() must return true when Accept header is application/json'
        );
        unset($_SERVER['HTTP_ACCEPT']);
    }

    /**
     * isApiRequest() must return false when Accept header is text/html.
     *
     * Requirements: 7.4
     */
    public function testIsApiRequestReturnsFalseForHtmlRequest(): void
    {
        $_SERVER['HTTP_ACCEPT'] = 'text/html,application/xhtml+xml';
        $this->assertFalse(
            isApiRequest(),
            'isApiRequest() must return false for a regular HTML request'
        );
        unset($_SERVER['HTTP_ACCEPT']);
    }

    /**
     * A session with user_id and a recent last_activity is considered valid.
     *
     * Requirements: 1.3, 1.5
     */
    public function testValidSessionIsNotExpired(): void
    {
        $_SESSION['user_id']       = 42;
        $_SESSION['username']      = 'testuser';
        $_SESSION['last_activity'] = time();

        $valid = isset($_SESSION['user_id'])
              && isset($_SESSION['last_activity'])
              && !isSessionExpired();

        $this->assertTrue(
            $valid,
            'A session with user_id and a recent last_activity must be considered valid'
        );
    }

    /**
     * refreshSession() must update last_activity to the current time.
     *
     * Requirements: 1.3
     */
    public function testRefreshSessionUpdatesLastActivity(): void
    {
        // Set last_activity to 10 minutes ago
        $_SESSION['last_activity'] = time() - 600;
        $before = $_SESSION['last_activity'];

        refreshSession();

        $this->assertGreaterThan(
            $before,
            $_SESSION['last_activity'],
            'refreshSession() must update last_activity to a more recent timestamp'
        );
    }
}
