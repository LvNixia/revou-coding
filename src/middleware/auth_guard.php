<?php

/**
 * Session Auth Guard Middleware
 *
 * Provides functions to protect pages and API endpoints from unauthenticated access.
 *
 * Functions:
 *   - requireAuth(): validates the active session. Redirects to login for HTML
 *     requests, returns HTTP 401 for API (JSON) requests.
 *   - refreshSession(): updates $_SESSION['last_activity'] on every valid request.
 *   - isSessionExpired(): returns true if the session has been inactive > 60 minutes.
 *
 * Session validity checks (all must pass):
 *   1. Session is started
 *   2. $_SESSION['user_id'] is set
 *   3. $_SESSION['last_activity'] is set
 *   4. Time since last_activity < 3600 seconds (60 minutes)
 *
 * Requirements: 1.3, 1.5, 7.4
 */

declare(strict_types=1);

/** Session inactivity timeout in seconds (60 minutes). */
const SESSION_TIMEOUT = 3600;

/**
 * Determine whether the current request expects a JSON response.
 * Returns true when the Accept header contains "application/json".
 */
function isApiRequest(): bool
{
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    return str_contains($accept, 'application/json');
}

/**
 * Check whether the current session has been inactive for more than 60 minutes.
 *
 * Returns true  — session is expired (or last_activity is missing).
 * Returns false — session is still within the allowed inactivity window.
 */
function isSessionExpired(): bool
{
    if (!isset($_SESSION['last_activity'])) {
        return true;
    }

    return (time() - (int) $_SESSION['last_activity']) > SESSION_TIMEOUT;
}

/**
 * Update the last_activity timestamp to the current time.
 *
 * Call this on every request that passes authentication to keep the session alive.
 */
function refreshSession(): void
{
    $_SESSION['last_activity'] = time();
}

/**
 * Validate the active session and enforce authentication.
 *
 * Checks performed (in order):
 *   1. Session is started
 *   2. $_SESSION['user_id'] is set
 *   3. $_SESSION['last_activity'] is set
 *   4. Session has not timed out (< 60 minutes of inactivity)
 *
 * If any check fails the session is destroyed and the request is rejected:
 *   - HTML requests  → redirect to /public/login.php
 *   - API requests   → HTTP 401 Unauthorized (JSON body)
 *
 * On success, refreshSession() is called to extend the inactivity window.
 */
function requireAuth(): void
{
    // Ensure the session is started before reading $_SESSION
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $valid = isset($_SESSION['user_id'])
          && isset($_SESSION['last_activity'])
          && !isSessionExpired();

    if (!$valid) {
        // Destroy the invalid / expired session cleanly
        $_SESSION = [];

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }

        if (isApiRequest()) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'message' => 'Sesi tidak valid atau telah kedaluwarsa. Silakan login kembali.',
            ]);
            exit;
        }

        // Determine the correct path to login.php relative to the calling script
        $loginUrl = '/public/login.php';
        header('Location: ' . $loginUrl);
        exit;
    }

    // Session is valid — refresh the inactivity timer
    refreshSession();
}
