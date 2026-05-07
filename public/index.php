<?php
/**
 * Entry point — redirect to dashboard if authenticated, otherwise to login.
 *
 * Requirements: 1.5
 */

declare(strict_types=1);

session_start();

// Check for an active, non-expired session.
$sessionLifetime = 60 * 60; // 60 minutes in seconds
$isLoggedIn = isset($_SESSION['user_id'])
    && isset($_SESSION['last_activity'])
    && (time() - $_SESSION['last_activity']) < $sessionLifetime;

if ($isLoggedIn) {
    $_SESSION['last_activity'] = time();
    header('Location: dashboard.php');
} else {
    // Destroy stale session data before redirecting to login.
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_unset();
        session_destroy();
    }
    header('Location: login.php');
}

exit;
