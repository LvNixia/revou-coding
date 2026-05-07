<?php
/**
 * Halaman Login
 *
 * Halaman publik (tidak memerlukan auth guard).
 * Form submit ke ../api/auth.php via POST.
 *
 * Requirements: 1.1, 1.2
 */

declare(strict_types=1);

session_start();

// Redirect ke dashboard jika sudah login
$sessionLifetime = 60 * 60; // 60 menit
$isLoggedIn = isset($_SESSION['user_id'])
    && isset($_SESSION['last_activity'])
    && (time() - $_SESSION['last_activity']) < $sessionLifetime;

if ($isLoggedIn) {
    $_SESSION['last_activity'] = time();
    header('Location: dashboard.php');
    exit;
}

// Generate CSRF token dan simpan ke sesi
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

// Tampilkan pesan error jika ada (dari query string ?error=1)
$showError = isset($_GET['error']) && $_GET['error'] === '1';
?>
<!DOCTYPE html>
<html lang="id" class="h-full bg-gray-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — SPK</title>

    <!-- Tailwind CSS via CDN -->
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
</head>
<body class="h-full">

<div class="min-h-full flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
    <div class="w-full max-w-md space-y-8">

        <!-- Header -->
        <div class="text-center">
            <div class="mx-auto h-14 w-14 rounded-xl bg-primary-700 flex items-center justify-center
                        shadow-md">
                <span class="text-white font-bold text-lg tracking-wide">SPK</span>
            </div>
            <h1 class="mt-4 text-2xl font-bold tracking-tight text-gray-900">
                Sistem Pendukung Keputusan
            </h1>
            <p class="mt-1 text-sm text-gray-500">Masuk untuk melanjutkan</p>
        </div>

        <!-- Login Card -->
        <div class="bg-white rounded-2xl shadow-md px-8 py-10 space-y-6">

            <!-- Pesan Error -->
            <?php if ($showError): ?>
            <div role="alert"
                 class="flex items-start gap-3 rounded-lg bg-red-50 border border-red-200
                        px-4 py-3 text-sm text-red-700">
                <svg class="mt-0.5 h-4 w-4 shrink-0 text-red-500" fill="none"
                     viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                     aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948
                             3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949
                             3.378c-.866-1.5-3.032-1.5-3.898 0L2.697
                             16.126zM12 15.75h.007v.008H12v-.008z"/>
                </svg>
                <span>Username atau password salah.</span>
            </div>
            <?php endif; ?>

            <!-- Form Login -->
            <form method="POST" action="../api/auth.php" novalidate class="space-y-5">

                <!-- CSRF Token -->
                <input type="hidden"
                       name="csrf_token"
                       value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

                <!-- Field Username -->
                <div>
                    <label for="username"
                           class="block text-sm font-medium text-gray-700 mb-1">
                        Username
                    </label>
                    <input type="text"
                           id="username"
                           name="username"
                           autocomplete="username"
                           required
                           class="block w-full rounded-lg border border-gray-300 px-3 py-2.5
                                  text-sm text-gray-900 placeholder-gray-400
                                  focus:border-primary-500 focus:outline-none focus:ring-2
                                  focus:ring-primary-500/30 transition"
                           placeholder="Masukkan username">
                </div>

                <!-- Field Password -->
                <div>
                    <label for="password"
                           class="block text-sm font-medium text-gray-700 mb-1">
                        Password
                    </label>
                    <input type="password"
                           id="password"
                           name="password"
                           autocomplete="current-password"
                           required
                           class="block w-full rounded-lg border border-gray-300 px-3 py-2.5
                                  text-sm text-gray-900 placeholder-gray-400
                                  focus:border-primary-500 focus:outline-none focus:ring-2
                                  focus:ring-primary-500/30 transition"
                           placeholder="Masukkan password">
                </div>

                <!-- Tombol Submit -->
                <button type="submit"
                        class="w-full rounded-lg bg-primary-700 px-4 py-2.5 text-sm
                               font-semibold text-white shadow-sm hover:bg-primary-800
                               focus:outline-none focus:ring-2 focus:ring-primary-500
                               focus:ring-offset-2 active:bg-primary-900 transition-colors">
                    Masuk
                </button>

            </form>
        </div>

    </div>
</div>

</body>
</html>
