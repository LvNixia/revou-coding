<?php

/**
 * Halaman Riwayat Perhitungan
 *
 * Menampilkan daftar semua riwayat perhitungan SAW yang pernah dilakukan,
 * diurutkan berdasarkan tanggal terbaru (dihitung_pada DESC).
 *
 * Kolom tabel:
 *   - No
 *   - Tanggal Perhitungan
 *   - Jumlah Kriteria
 *   - Jumlah Alternatif
 *   - Aksi (Lihat Detail → hasil.php?riwayat_id=X)
 *
 * Data dimuat secara asinkron dari ../api/riwayat.php menggunakan Fetch API.
 *
 * Requirements: 5.6
 */

declare(strict_types=1);

require_once '../src/middleware/auth_guard.php';
require_once '../src/helpers/csrf.php';

requireAuth();
$csrfToken = generateCsrfToken();
$pageTitle = 'Riwayat';

require_once '../src/layout/main.php';
?>

<!-- ── Riwayat Perhitungan Content ───────────────────────────────────────── -->

<div class="space-y-6">

    <!-- Page heading -->
    <div>
        <h2 class="text-xl font-semibold text-gray-800">Riwayat Perhitungan</h2>
        <p class="mt-1 text-sm text-gray-500">
            Daftar semua perhitungan SAW yang pernah dilakukan, diurutkan dari yang terbaru.
        </p>
    </div>

    <!-- Error banner (hidden by default) -->
    <div id="riwayat-error"
         role="alert"
         aria-live="polite"
         class="hidden flex items-start gap-3 rounded-lg bg-red-50 border border-red-200
                px-4 py-3 text-sm text-red-700">
        <svg class="mt-0.5 h-4 w-4 shrink-0 text-red-500" fill="none" viewBox="0 0 24 24"
             stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round"
                  d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0
                     2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898
                     0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>
        </svg>
        <span id="riwayat-error-msg">Gagal memuat data. Silakan muat ulang halaman.</span>
    </div>

    <!-- Riwayat table card -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">

        <!-- Table header bar -->
        <div class="px-4 py-3 border-b border-gray-100 bg-gray-50 flex items-center justify-between">
            <h3 class="text-sm font-semibold text-gray-700">Daftar Riwayat</h3>
            <!-- Loading indicator -->
            <span id="riwayat-loading"
                  class="inline-flex items-center gap-1.5 text-xs text-gray-400">
                <svg class="animate-spin h-3.5 w-3.5 text-gray-400"
                     fill="none" viewBox="0 0 24 24" aria-hidden="true">
                    <circle class="opacity-25" cx="12" cy="12" r="10"
                            stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor"
                          d="M4 12a8 8 0 018-8v8H4z"></path>
                </svg>
                Memuat data…
            </span>
        </div>

        <!-- Table -->
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col"
                            class="px-4 py-3 text-left text-xs font-semibold text-gray-500
                                   uppercase tracking-wider w-12">
                            No
                        </th>
                        <th scope="col"
                            class="px-4 py-3 text-left text-xs font-semibold text-gray-500
                                   uppercase tracking-wider">
                            Tanggal Perhitungan
                        </th>
                        <th scope="col"
                            class="px-4 py-3 text-center text-xs font-semibold text-gray-500
                                   uppercase tracking-wider">
                            Jumlah Kriteria
                        </th>
                        <th scope="col"
                            class="px-4 py-3 text-center text-xs font-semibold text-gray-500
                                   uppercase tracking-wider">
                            Jumlah Alternatif
                        </th>
                        <th scope="col"
                            class="px-4 py-3 text-center text-xs font-semibold text-gray-500
                                   uppercase tracking-wider w-32">
                            Aksi
                        </th>
                    </tr>
                </thead>
                <tbody id="riwayat-tbody"
                       class="divide-y divide-gray-100 bg-white">
                    <!-- Skeleton rows while loading -->
                    <tr class="animate-pulse">
                        <td class="px-4 py-3">
                            <div class="h-4 w-6 rounded bg-gray-100"></div>
                        </td>
                        <td class="px-4 py-3">
                            <div class="h-4 w-40 rounded bg-gray-100"></div>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <div class="h-4 w-8 rounded bg-gray-100 mx-auto"></div>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <div class="h-4 w-8 rounded bg-gray-100 mx-auto"></div>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <div class="h-6 w-20 rounded bg-gray-100 mx-auto"></div>
                        </td>
                    </tr>
                    <tr class="animate-pulse">
                        <td class="px-4 py-3">
                            <div class="h-4 w-6 rounded bg-gray-100"></div>
                        </td>
                        <td class="px-4 py-3">
                            <div class="h-4 w-36 rounded bg-gray-100"></div>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <div class="h-4 w-8 rounded bg-gray-100 mx-auto"></div>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <div class="h-4 w-8 rounded bg-gray-100 mx-auto"></div>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <div class="h-6 w-20 rounded bg-gray-100 mx-auto"></div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

    </div><!-- /table card -->

</div><!-- /space-y-6 -->

<script>
/**
 * Riwayat Perhitungan — client-side logic
 *
 * Fetches the history list from api/riwayat.php and renders it into the table.
 *
 * Requirements: 5.6
 */
(function () {
    'use strict';

    // ── DOM helpers ──────────────────────────────────────────────────────────

    function el(id) { return document.getElementById(id); }

    /**
     * Escape a string for safe insertion as HTML text content.
     * @param {string} str
     * @returns {string}
     */
    function escapeHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    /**
     * Format a MySQL DATETIME string (YYYY-MM-DD HH:MM:SS) into a
     * human-readable Indonesian locale string.
     *
     * @param {string|null} raw
     * @returns {string}
     */
    function formatTanggal(raw) {
        if (!raw) {
            return '—';
        }
        // MySQL returns "YYYY-MM-DD HH:MM:SS" — replace space with T for ISO 8601
        var date = new Date(raw.replace(' ', 'T'));
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

    // ── Error banner ─────────────────────────────────────────────────────────

    function showError(msg) {
        var banner = el('riwayat-error');
        var msgEl  = el('riwayat-error-msg');
        if (msg && msgEl) {
            msgEl.textContent = msg;
        }
        if (banner) {
            banner.classList.remove('hidden');
        }
    }

    // ── Hide loading indicator ────────────────────────────────────────────────

    function hideLoading() {
        var loadingEl = el('riwayat-loading');
        if (loadingEl) {
            loadingEl.classList.add('hidden');
        }
    }

    // ── Render table ──────────────────────────────────────────────────────────

    /**
     * Render the history list into the table body.
     *
     * @param {Array<{
     *   id: number,
     *   dihitung_pada: string,
     *   jumlah_kriteria: number,
     *   jumlah_alternatif: number,
     *   catatan: string|null
     * }>} items
     */
    function renderTable(items) {
        var tbody = el('riwayat-tbody');
        if (!tbody) return;

        if (!items || items.length === 0) {
            tbody.innerHTML =
                '<tr>' +
                  '<td colspan="5" class="px-4 py-10 text-center text-sm text-gray-400">' +
                    'Belum ada riwayat perhitungan. Jalankan perhitungan SAW terlebih dahulu.' +
                  '</td>' +
                '</tr>';
            return;
        }

        var rows = '';
        items.forEach(function (item, idx) {
            var detailUrl = 'hasil.php?riwayat_id=' + encodeURIComponent(item.id);

            rows +=
                '<tr class="hover:bg-gray-50 transition-colors">' +
                  '<td class="px-4 py-3 text-gray-500 tabular-nums">' +
                    (idx + 1) +
                  '</td>' +
                  '<td class="px-4 py-3 text-gray-900">' +
                    escapeHtml(formatTanggal(item.dihitung_pada)) +
                  '</td>' +
                  '<td class="px-4 py-3 text-center tabular-nums text-gray-700">' +
                    item.jumlah_kriteria +
                  '</td>' +
                  '<td class="px-4 py-3 text-center tabular-nums text-gray-700">' +
                    item.jumlah_alternatif +
                  '</td>' +
                  '<td class="px-4 py-3 text-center">' +
                    '<a href="' + escapeHtml(detailUrl) + '"' +
                       ' class="inline-flex items-center gap-1.5 rounded-md bg-primary-600' +
                       ' px-3 py-1.5 text-xs font-medium text-white shadow-sm' +
                       ' hover:bg-primary-700 focus:outline-none focus:ring-2' +
                       ' focus:ring-primary-500 focus:ring-offset-1 transition-colors"' +
                       ' aria-label="Lihat detail perhitungan ' + escapeHtml(formatTanggal(item.dihitung_pada)) + '">' +
                      '<svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24"' +
                           ' stroke="currentColor" stroke-width="2" aria-hidden="true">' +
                        '<path stroke-linecap="round" stroke-linejoin="round"' +
                             ' d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5' +
                             ' 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639' +
                             'C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.964-7.178z"/>' +
                        '<path stroke-linecap="round" stroke-linejoin="round"' +
                             ' d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0z"/>' +
                      '</svg>' +
                      'Lihat Detail' +
                    '</a>' +
                  '</td>' +
                '</tr>';
        });

        tbody.innerHTML = rows;
    }

    // ── Fetch riwayat data ────────────────────────────────────────────────────

    fetch('../api/riwayat.php', {
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
        hideLoading();

        if (!json) return; // redirected

        if (!json.success) {
            showError(json.message || 'Gagal memuat data. Silakan muat ulang halaman.');
            return;
        }

        renderTable(json.data || []);
    })
    .catch(function (err) {
        console.error('[Riwayat] Fetch error:', err);
        hideLoading();
        showError('Gagal memuat data. Silakan muat ulang halaman.');
    });

}());
</script>

<?php require_once '../src/layout/footer.php'; ?>
