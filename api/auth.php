<?php

/**
 * Authentication API Endpoint
 *
 * Handles POST login requests from the login form or AJAX clients.
 *
 * Behaviour:
 *   - Validates CSRF token (HTTP 403 on failure)
 *   - Looks up the user by username using a PDO prepared statement
 *   - Verifies the submitted password against the stored bcrypt hash
 *   - On success: creates a PHP session with user_id, username, last_activity
 *   - On failure: returns "Username atau password salah" (does not reveal which field)
 *
 * Response format:
 *   - If the request carries `Accept: application/json`, returns JSON
 *   - Otherwise redirects to dashboard (success) or login?error=1 (failure)
 *
 * Session timeout: 60 minutes of inactivity
 *
 * Requirements: 1.1, 1.2, 1.3, 1.6
 */

declare(strict_types=1);

// ── Bootstrap ────────────────────────────────────────────────────────────────

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/helpers/csrf.php';

// Start (or resume) the session before any output
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── Constants ─────────────────────────────────────────────────────────────────

const SESSION_LIFETIME = 3600; // 60 minutes in seconds

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
 * @param bool   $success  Whether the operation succeeded
 * @param string $message  Human-readable message
 * @param int    $status   HTTP status code
 * @param array  $extra    Additional fields merged into the response body
 */
function sendJson(bool $success, string $message, int $status = 200, array $extra = []): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $extra));
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

// ── Payload size guard (max 1 MB) ─────────────────────────────────────────────

$contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
if ($contentLength > 1_048_576) {
    if (isJsonRequest()) {
        sendJson(false, 'Payload terlalu besar', 413);
    }
    redirect('../public/login.php?error=1');
}

// ── Parse input ───────────────────────────────────────────────────────────────

// Support both form-encoded and JSON bodies
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (str_contains($contentType, 'application/json')) {
    $body = (array) json_decode(file_get_contents('php://input'), true);
    $username  = trim((string) ($body['username']  ?? ''));
    $password  = (string) ($body['password']  ?? '');
    $csrfToken = (string) ($body['csrf_token'] ?? '');
} else {
    $username  = trim((string) ($_POST['username']  ?? ''));
    $password  = (string) ($_POST['password']  ?? '');
    $csrfToken = (string) ($_POST['csrf_token'] ?? '');
}

// ── CSRF validation ───────────────────────────────────────────────────────────

if (!validateCsrfToken($csrfToken)) {
    if (isJsonRequest()) {
        sendJson(false, 'Token CSRF tidak valid', 403);
    }
    redirect('../public/login.php?error=1');
}

// ── Basic input presence check ────────────────────────────────────────────────

if ($username === '' || $password === '') {
    if (isJsonRequest()) {
        sendJson(false, 'Username atau password salah', 401);
    }
    redirect('../public/login.php?error=1');
}

// ── Database lookup ───────────────────────────────────────────────────────────

try {
    $pdo = getDbConnection();

    $stmt = $pdo->prepare(
        'SELECT id, username, password FROM users WHERE username = :username LIMIT 1'
    );
    $stmt->execute([':username' => $username]);
    $user = $stmt->fetch();
} catch (PDOException $e) {
    // Log the real error but never expose it to the client
    $logEntry = sprintf(
        "[%s] auth.php PDOException: %s in %s:%d\n",
        date('Y-m-d H:i:s'),
        $e->getMessage(),
        $e->getFile(),
        $e->getLine()
    );
    @error_log($logEntry, 3, __DIR__ . '/../logs/error.log');

    if (isJsonRequest()) {
        sendJson(false, 'Terjadi kesalahan server. Silakan coba lagi.', 500);
    }
    redirect('../public/login.php?error=1');
}

// ── Credential verification ───────────────────────────────────────────────────

// Intentionally use the same generic message for both "user not found" and
// "wrong password" to avoid username enumeration (Requirement 1.2).
$credentialsValid = $user !== false && password_verify($password, $user['password']);

if (!$credentialsValid) {
    if (isJsonRequest()) {
        sendJson(false, 'Username atau password salah', 401);
    }
    redirect('../public/login.php?error=1');
}

// ── Session creation ──────────────────────────────────────────────────────────

// Regenerate session ID to prevent session fixation attacks
session_regenerate_id(true);

$_SESSION['user_id']       = (int) $user['id'];
$_SESSION['username']      = $user['username'];
$_SESSION['last_activity'] = time();

// Rotate the CSRF token after a successful login
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

// ── Success response ──────────────────────────────────────────────────────────

if (isJsonRequest()) {
    sendJson(true, 'Login berhasil', 200, [
        'data' => [
            'username'      => $user['username'],
            'redirect'      => '../public/dashboard.php',
        ],
    ]);
}

redirect('../public/dashboard.php');
