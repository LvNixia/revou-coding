<?php

/**
 * Halaman Dashboard
 *
 * Menampilkan ringkasan data sistem:
 *   - Jumlah kriteria aktif
 *   - Jumlah alternatif
 *   - Tanggal perhitungan terakhir
 *
 * Data dimuat secara asinkron dari ../api/dashboard.php menggunakan Fetch API.
 *
 * Requirements: 6.1, 6.3
 */

declare(strict_types=1);

require_once '../src/middleware/auth_guard.php';
require_once '../src/helpers/csrf.php';

requireAuth();
$csrfToken = generateCsrfToken();
$pageTitle = 'Dashboard';

require_once '../src/layout/main.php';
?>

<!-- ── Dashboard Content ──────────────────────────────────────────────────── -->

<div class="space-y-6">

    <!-- Page heading -->
    <div>
        <h2 class="text-xl font-semibold text-gray-800">Selamat datang di SPK</h2>
        <p class="mt-1 text-sm text-gray-500">Ringkasan data sistem saat ini.</p>
    </div>

    <!-- Summary cards -->
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">

        <!-- Card: Jumlah Kriteria -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5 flex items-center gap-4">
            <div class="flex-shrink-0 h-12 w-12 rounded-lg bg-primary-100 flex items-center justify-center">
                <!-- Heroicons: adjustments-horizontal -->
                <svg class="h-6 w-6 text-primary-700" fill="none" viewBox="0 0 24 24"
                     stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M10.5 6h9.75M10.5 6a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75
                             6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3
                             0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0
                             0 0-3 0m-9.75 0h9.75"/>
                </svg>
            </div>
            <div class="min-w-0">
                <p class="text-sm font-medium text-gray-500 truncate">Jumlah Kriteria</p>
                <p id="card-kriteria"
                   class="mt-0.5 text-2xl font-bold text-gray-900 tabular-nums"
                   aria-live="polite">
                    <span class="inline-block h-7 w-10 rounded bg-gray-100 animate-pulse"></span>
                </p>
            </div>
        </div>

        <!-- Card: Jumlah Alternatif -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5 flex items-center gap-4">
            <div class="flex-shrink-0 h-12 w-12 rounded-lg bg-green-100 flex items-center justify-center">
                <!-- Heroicons: table-cells -->
                <svg class="h-6 w-6 text-green-700" fill="none" viewBox="0 0 24 24"
                     stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M3.375 19.5h17.25m-17.25 0a1.125 1.125 0 0 1-1.125-1.125M3.375
                             19.5h1.5C5.496 19.5 6 18.996 6 18.375m-3.75 0V5.625m0
                             0A1.125 1.125 0 0 1 3.375 4.5h1.5m-1.5 0h1.5m13.5
                             15h1.5c.621 0 1.125-.504 1.125-1.125M20.625 19.5V5.625m0
                             0A1.125 1.125 0 0 0 19.5 4.5h-1.5m1.5 0h-1.5m-13.5
                             0h13.5M6 18.375V5.625M6 5.625A1.125 1.125 0 0 1 7.125
                             4.5h9.75A1.125 1.125 0 0 1 18 5.625M6 5.625v12.75m12-12.75v12.75"/>
                </svg>
            </div>
            <div class="min-w-0">
                <p class="text-sm font-medium text-gray-500 truncate">Jumlah Alternatif</p>
                <p id="card-alternatif"
                   class="mt-0.5 text-2xl font-bold text-gray-900 tabular-nums"
                   aria-live="polite">
                    <span class="inline-block h-7 w-10 rounded bg-gray-100 animate-pulse"></span>
                </p>
            </div>
        </div>

        <!-- Card: Tanggal Perhitungan Terakhir -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5 flex items-center gap-4">
            <div class="flex-shrink-0 h-12 w-12 rounded-lg bg-amber-100 flex items-center justify-center">
                <!-- Heroicons: clock -->
                <svg class="h-6 w-6 text-amber-700" fill="none" viewBox="0 0 24 24"
                     stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0z"/>
                </svg>
            </div>
            <div class="min-w-0">
                <p class="text-sm font-medium text-gray-500 truncate">Perhitungan Terakhir</p>
                <p id="card-hitung"
                   class="mt-0.5 text-sm font-semibold text-gray-900 leading-snug"
                   aria-live="polite">
                    <span class="inline-block h-5 w-28 rounded bg-gray-100 animate-pulse"></span>
                </p>
            </div>
        </div>

    </div><!-- /grid -->

    <!-- Error banner (hidden by default) -->
    <div id="dashboard-error"
         role="alert"
         class="hidden flex items-start gap-3 rounded-lg bg-red-50 border border-red-200
                px-4 py-3 text-sm text-red-700">
        <svg class="mt-0.5 h-4 w-4 shrink-0 text-red-500" fill="none" viewBox="0 0 24 24"
             stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round"
                  d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0
                     2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898
                     0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>
        </svg>
        <span id="dashboard-error-msg">Gagal memuat data. Silakan muat ulang halaman.</span>
    </div>

</div><!-- /space-y-6 -->

<script>
/**
 * Fetch dashboard summary data from the API and populate the summary cards.
 * Requirements: 6.1, 6.3
 */
(function () {
    'use strict';

    /**
     * Format a MySQL DATETIME string (YYYY-MM-DD HH:MM:SS) into a
     * human-readable Indonesian locale string.
     *
     * @param {string|null} raw
     * @returns {string}
     */
    function formatTanggal(raw) {
        if (!raw) {
            return 'Belum ada perhitungan';
        }
        // MySQL returns "YYYY-MM-DD HH:MM:SS" — replace space with T for ISO 8601
        const date = new Date(raw.replace(' ', 'T'));
        if (isNaN(date.getTime())) {
            return raw; // fallback: display as-is
        }
        return date.toLocaleString('id-ID', {
            day:    '2-digit',
            month:  'long',
            year:   'numeric',
            hour:   '2-digit',
            minute: '2-digit',
        });
    }

    /**
     * Show the error banner with an optional custom message.
     *
     * @param {string} [msg]
     */
    function showError(msg) {
        const banner = document.getElementById('dashboard-error');
        const msgEl  = document.getElementById('dashboard-error-msg');
        if (msg && msgEl) {
            msgEl.textContent = msg;
        }
        if (banner) {
            banner.classList.remove('hidden');
        }
    }

    /**
     * Replace the loading skeleton in a card with the actual value.
     *
     * @param {string} id      Element id
     * @param {string} value   Text to display
     */
    function setCardValue(id, value) {
        const el = document.getElementById(id);
        if (el) {
            el.textContent = value;
        }
    }

    // Fetch summary data
    fetch('../api/dashboard.php', {
        method:  'GET',
        headers: {
            'Accept':       'application/json',
            'X-CSRF-Token': typeof CSRF_TOKEN !== 'undefined' ? CSRF_TOKEN : '',
        },
        credentials: 'same-origin',
    })
    .then(function (response) {
        if (response.status === 401) {
            // Session expired — redirect to login
            window.location.href = 'login.php';
            return null;
        }
        if (!response.ok) {
            throw new Error('HTTP ' + response.status);
        }
        return response.json();
    })
    .then(function (json) {
        if (!json) return; // redirected

        if (!json.success) {
            showError(json.message || 'Gagal memuat data. Silakan muat ulang halaman.');
            return;
        }

        const data = json.data || {};

        setCardValue('card-kriteria',   String(data.jumlah_kriteria   ?? 0));
        setCardValue('card-alternatif', String(data.jumlah_alternatif ?? 0));
        setCardValue('card-hitung',     formatTanggal(data.tanggal_hitung_terakhir ?? null));
    })
    .catch(function (err) {
        console.error('[Dashboard] Fetch error:', err);
        showError('Gagal memuat data. Silakan muat ulang halaman.');
    });
}());
</script>

<?php require_once '../src/layout/footer.php'; ?>
