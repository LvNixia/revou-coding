<?php
/**
 * Main layout template.
 *
 * Include this file after requireAuth() and generateCsrfToken() have been
 * called in the parent page. The parent page is responsible for:
 *
 *   $pageTitle  (string) — used in <title> and the active sidebar link
 *   $csrfToken  (string) — injected into the page as a JS constant
 *
 * Usage in a page file:
 *
 *   <?php
 *   require_once '../src/middleware/auth_guard.php';
 *   requireAuth();
 *   $csrfToken = generateCsrfToken();
 *   $pageTitle = 'Dashboard';
 *   require_once '../src/layout/main.php';
 *   ?>
 *   <!-- page-specific content goes here -->
 *   <?php require_once '../src/layout/footer.php'; ?>
 *
 * This file renders the <html>, <head>, header bar, and sidebar.
 * The closing </body></html> is rendered by footer.php (to be created in Task 3).
 */

declare(strict_types=1);

// Fetch system settings for header display.
// Gracefully degrade if the database is not yet available (e.g., first-run).
$settingsForLayout = [];
try {
    require_once __DIR__ . '/../../config/database.php';
    $pdo = getDbConnection();
    $stmt = $pdo->query("SELECT kunci, nilai FROM pengaturan");
    foreach ($stmt->fetchAll() as $row) {
        $settingsForLayout[$row['kunci']] = $row['nilai'];
    }
} catch (Throwable $e) {
    // Silently fall back to defaults — layout must not crash on DB errors.
}

$sistemName  = htmlspecialchars($settingsForLayout['nama_sistem']     ?? 'SPK', ENT_QUOTES, 'UTF-8');
$orgName     = htmlspecialchars($settingsForLayout['nama_organisasi'] ?? '',    ENT_QUOTES, 'UTF-8');
$logoPath    = $settingsForLayout['logo_path'] ?? '';
$pageTitle   = isset($pageTitle) ? htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') : 'SPK';
$csrfToken   = $csrfToken ?? '';

// Navigation items: [label, href, icon (Heroicons outline name)]
$navItems = [
    ['Dashboard',   '../public/dashboard.php',   'home'],
    ['Kriteria',    '../public/kriteria.php',     'adjustments-horizontal'],
    ['Alternatif',  '../public/alternatif.php',   'table-cells'],
    ['Hitung SAW',  '../public/hitung.php',       'calculator'],
    ['Hasil',       '../public/hasil.php',        'chart-bar'],
    ['Riwayat',     '../public/riwayat.php',      'clock'],
    ['Pengaturan',  '../public/pengaturan.php',   'cog-6-tooth'],
];
?>
<!DOCTYPE html>
<html lang="id" class="h-full bg-gray-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> — <?= $sistemName ?></title>

    <!-- Tailwind CSS via CDN (Play CDN for development) -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50:  '#eff6ff',
                            100: '#dbeafe',
                            500: '#3b82f6',
                            600: '#2563eb',
                            700: '#1d4ed8',
                            800: '#1e40af',
                            900: '#1e3a8a',
                        }
                    }
                }
            }
        }
    </script>

    <!-- Chart.js via CDN (used on hasil.php) -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js" defer></script>
</head>
<body class="h-full">

<!-- CSRF token available globally in JavaScript -->
<script>
    const CSRF_TOKEN = <?= json_encode($csrfToken) ?>;
</script>

<div class="flex h-full">

    <!-- ------------------------------------------------------------------ -->
    <!-- Sidebar                                                              -->
    <!-- ------------------------------------------------------------------ -->
    <aside id="sidebar"
           class="flex flex-col w-64 min-h-screen bg-primary-800 text-white shrink-0
                  transition-transform duration-200 ease-in-out
                  -translate-x-full fixed inset-y-0 left-0 z-40
                  md:relative md:translate-x-0">

        <!-- Logo / system name -->
        <div class="flex items-center gap-3 px-4 py-5 border-b border-primary-700">
            <?php if ($logoPath !== ''): ?>
                <img src="<?= htmlspecialchars($logoPath, ENT_QUOTES, 'UTF-8') ?>"
                     alt="Logo"
                     class="h-8 w-8 object-contain rounded">
            <?php else: ?>
                <div class="h-8 w-8 rounded bg-primary-500 flex items-center justify-center
                            text-white font-bold text-sm shrink-0">
                    SPK
                </div>
            <?php endif; ?>
            <div class="overflow-hidden">
                <p class="text-sm font-semibold leading-tight truncate"><?= $sistemName ?></p>
                <?php if ($orgName !== ''): ?>
                    <p class="text-xs text-primary-300 truncate"><?= $orgName ?></p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Navigation links -->
        <nav class="flex-1 overflow-y-auto py-4 px-2 space-y-1">
            <?php foreach ($navItems as [$label, $href, $icon]): ?>
                <?php
                $isActive = isset($pageTitle) && strtolower($pageTitle) === strtolower($label);
                $linkClass = $isActive
                    ? 'flex items-center gap-3 px-3 py-2 rounded-md text-sm font-medium bg-primary-900 text-white'
                    : 'flex items-center gap-3 px-3 py-2 rounded-md text-sm font-medium text-primary-100 hover:bg-primary-700 hover:text-white transition-colors';
                ?>
                <a href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>"
                   class="<?= $linkClass ?>">
                    <span class="text-primary-300 text-base">&#9632;</span>
                    <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <!-- Logout -->
        <div class="px-2 py-4 border-t border-primary-700">
            <form method="POST" action="../api/logout.php">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <button type="submit"
                        class="w-full flex items-center gap-3 px-3 py-2 rounded-md text-sm
                               font-medium text-primary-100 hover:bg-primary-700 hover:text-white
                               transition-colors text-left">
                    <span class="text-primary-300">&#10006;</span>
                    Logout
                </button>
            </form>
        </div>
    </aside>

    <!-- Sidebar overlay (mobile) -->
    <div id="sidebar-overlay"
         class="fixed inset-0 bg-black/50 z-30 hidden md:hidden"
         onclick="toggleSidebar()"></div>

    <!-- ------------------------------------------------------------------ -->
    <!-- Main content area                                                    -->
    <!-- ------------------------------------------------------------------ -->
    <div class="flex flex-col flex-1 min-w-0 overflow-hidden">

        <!-- Top bar -->
        <header class="flex items-center gap-4 px-4 py-3 bg-white border-b border-gray-200 shadow-sm">
            <!-- Mobile menu toggle -->
            <button type="button"
                    onclick="toggleSidebar()"
                    class="md:hidden p-2 rounded-md text-gray-500 hover:text-gray-700
                           hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-primary-500"
                    aria-label="Toggle navigation">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
            </button>

            <h1 class="text-lg font-semibold text-gray-800 truncate"><?= $pageTitle ?></h1>
        </header>

        <!-- Page content injected here -->
        <main class="flex-1 overflow-y-auto p-4 md:p-6">
