<?php

/**
 * Logout API Endpoint
 *
 * Handles POST logout requests. Validates the CSRF token, destroys the active
 * session completely, then redirects the user to the login page.
 *
 * Behaviour:
 *   - Accepts POST requests only (405 for any other method)
 *   - Validates CSRF token from POST body (HTTP 403 on failure)
 *   - Calls session_unset() and session_destroy() to wipe the session
 *   - For JSON requests (Accept: application/json) returns a JSON success body
 *   - For regular requests redirects to ../public/login.php
 *
 * Requirements: 1.4
 */

declare(strict_types=1);

// ── Bootstrap ────────────────────────────────────────────────────────────────

require_once __DIR__ . '/../src/helpers/csrf.php';

// Start (or resume) the session before reading $_SESSION or CSRF data
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── Helpers ───────────────────────────────────────────────────────────────────

/**
 * Determine whether the current request expects a JSON response.
 * True when the Accept header contains "application/json".
 */
function isJsonRequest(): bool
{
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    return str_contains($accept, 'application/json');
}

/**
 * Send a JSON response and terminate execution.
 *
 * @param bool   $success Whether the operation succeeded
 * @param string $message Human-readable message
 * @param int    $status  HTTP status code
 */
function sendJson(bool $success, string $message, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => $success, 'message' => $message]);
    exit;
}

/**
 * Redirect to a URL and terminate execution.
 */
function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

// ── Request validation ────────────────────────────────────────────────────────

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if (isJsonRequest()) {
        sendJson(false, 'Method not allowed', 405);
    }
    redirect('../public/login.php');
}

// ── Parse CSRF token ──────────────────────────────────────────────────────────

// Support both form-encoded and JSON bodies
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (str_contains($contentType, 'application/json')) {
    $body      = (array) json_decode(file_get_contents('php://input'), true);
    $csrfToken = (string) ($body['csrf_token'] ?? '');
} else {
    $csrfToken = (string) ($_POST['csrf_token'] ?? '');
}

// ── CSRF validation ───────────────────────────────────────────────────────────

if (!validateCsrfToken($csrfToken)) {
    if (isJsonRequest()) {
        sendJson(false, 'Token CSRF tidak valid', 403);
    }
    redirect('../public/login.php?error=1');
}

// ── Session destruction ───────────────────────────────────────────────────────

// Clear all session variables
session_unset();

// Destroy the session on the server
session_destroy();

// ── Success response ──────────────────────────────────────────────────────────

if (isJsonRequest()) {
    sendJson(true, 'Logout berhasil');
}

redirect('../public/login.php');
