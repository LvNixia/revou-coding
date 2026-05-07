<?php

/**
 * Halaman Hasil Perhitungan SAW
 *
 * Menampilkan:
 *   - Tombol "Hitung SAW" untuk memicu perhitungan via POST ke api/hitung.php
 *   - Kartu rekomendasi utama (alternatif dengan nilai preferensi tertinggi)
 *   - Tabel matriks normalisasi (alternatif × kriteria)
 *   - Tabel ranking: No, Nama Alternatif, Nilai Preferensi, Ranking
 *   - Grafik batang nilai preferensi menggunakan Chart.js
 *
 * Data dimuat secara asinkron menggunakan Fetch API.
 * Nama kolom kriteria diambil dari api/kriteria.php (GET).
 *
 * Requirements: 5.1, 5.2, 5.5
 */

declare(strict_types=1);

require_once '../src/middleware/auth_guard.php';
require_once '../src/helpers/csrf.php';

requireAuth();
$csrfToken = generateCsrfToken();
$pageTitle = 'Hasil';

require_once '../src/layout/main.php';
?>

<!-- ── Hasil Perhitungan Content ──────────────────────────────────────────── -->

<div class="space-y-6" id="hasil-page">

    <!-- Page heading + action buttons -->
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-xl font-semibold text-gray-800">Hasil Perhitungan SAW</h2>
            <p class="mt-1 text-sm text-gray-500">
                Jalankan perhitungan untuk melihat ranking dan matriks normalisasi.
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <!-- Unduh Laporan button (hidden until a calculation has been run) -->
            <a id="btn-unduh-laporan"
               href="#"
               target="_blank"
               rel="noopener noreferrer"
               class="hidden inline-flex items-center gap-2 rounded-lg bg-green-600 px-4 py-2
                      text-sm font-medium text-white shadow-sm hover:bg-green-700
                      focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2
                      transition-colors"
               aria-label="Unduh laporan PDF">
                <!-- Download / document icon -->
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24"
                     stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21
                             18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/>
                </svg>
                Unduh Laporan
            </a>

            <!-- Hitung SAW button -->
            <button type="button"
                    id="btn-hitung"
                    onclick="jalankanHitung()"
                    class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2
                           text-sm font-medium text-white shadow-sm hover:bg-primary-700
                           focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2
                           disabled:opacity-60 disabled:cursor-not-allowed transition-colors">
                <!-- Calculator icon -->
                <svg id="hitung-icon"
                     class="h-4 w-4" fill="none" viewBox="0 0 24 24"
                     stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M15.75 15.75V18m-7.5-6.75h.008v.008H8.25v-.008zm0 2.25h.008v.008H8.25V13.5zm0
                             2.25h.008v.008H8.25v-.008zm2.496-4.5h.008v.008h-.008V11.25zm0 2.25h.008v.008h-.008V13.5zm0
                             2.25h.008v.008h-.008v-.008zm2.496-4.5h.008v.008h-.008V11.25zm0 2.25h.008v.008h-.008V13.5zM8.25
                             6h7.5v2.25h-7.5V6zM12 2.25c-5.385 0-9.75 4.365-9.75 9.75s4.365 9.75 9.75 9.75 9.75-4.365
                             9.75-9.75S17.385 2.25 12 2.25z"/>
                </svg>
                <svg id="hitung-spinner"
                     class="hidden animate-spin h-4 w-4 text-white"
                     fill="none" viewBox="0 0 24 24" aria-hidden="true">
                    <circle class="opacity-25" cx="12" cy="12" r="10"
                            stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                </svg>
                <span id="hitung-label">Hitung SAW</span>
            </button>
        </div>
    </div>

    <!-- Error banner (hidden by default) -->
    <div id="error-banner"
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
        <span id="error-msg">Terjadi kesalahan. Silakan coba lagi.</span>
    </div>

    <!-- Results section (hidden until calculation runs) -->
    <div id="results-section" class="hidden space-y-6">

        <!-- ── Rekomendasi Utama card ──────────────────────────────────────── -->
        <div id="rekomendasi-card"
             class="rounded-xl border-2 border-green-400 bg-green-50 p-5 flex items-start gap-4
                    shadow-sm">
            <div class="flex-shrink-0 h-12 w-12 rounded-full bg-green-500 flex items-center
                        justify-center shadow">
                <!-- Trophy / star icon -->
                <svg class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24"
                     stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M11.48 3.499a.562.562 0 0 1 1.04 0l2.125 5.111a.563.563 0 0 0
                             .475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563
                             0 0 0-.182.557l1.285 5.385a.562.562 0 0 1-.84.61l-4.725-2.885a.562.562
                             0 0 0-.586 0L6.982 20.54a.562.562 0 0 1-.84-.61l1.285-5.386a.562.562
                             0 0 0-.182-.557l-4.204-3.602a.562.562 0 0 1 .321-.988l5.518-.442a.563.563
                             0 0 0 .475-.345L11.48 3.5z"/>
                </svg>
            </div>
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-green-700 mb-0.5">
                    Rekomendasi Utama
                </p>
                <p id="rekomendasi-nama"
                   class="text-xl font-bold text-green-900"></p>
                <p class="mt-1 text-sm text-green-700">
                    Nilai Preferensi:
                    <span id="rekomendasi-nilai"
                          class="font-semibold tabular-nums"></span>
                </p>
            </div>
        </div>

        <!-- ── Ranking table ───────────────────────────────────────────────── -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-100 bg-gray-50">
                <h3 class="text-sm font-semibold text-gray-700">Tabel Ranking</h3>
            </div>
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
                                Nama Alternatif
                            </th>
                            <th scope="col"
                                class="px-4 py-3 text-right text-xs font-semibold text-gray-500
                                       uppercase tracking-wider">
                                Nilai Preferensi
                            </th>
                            <th scope="col"
                                class="px-4 py-3 text-center text-xs font-semibold text-gray-500
                                       uppercase tracking-wider w-24">
                                Ranking
                            </th>
                        </tr>
                    </thead>
                    <tbody id="ranking-tbody"
                           class="divide-y divide-gray-100 bg-white">
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ── Bar chart ───────────────────────────────────────────────────── -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <h3 class="text-sm font-semibold text-gray-700 mb-4">
                Grafik Nilai Preferensi
            </h3>
            <div class="relative" style="height:280px;">
                <canvas id="preference-chart" aria-label="Grafik nilai preferensi alternatif"></canvas>
            </div>
        </div>

        <!-- ── Normalization matrix table ─────────────────────────────────── -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-100 bg-gray-50">
                <h3 class="text-sm font-semibold text-gray-700">Matriks Normalisasi</h3>
                <p class="text-xs text-gray-400 mt-0.5">
                    Nilai ternormalisasi setiap alternatif untuk setiap kriteria (4 desimal)
                </p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm" id="normalisasi-table">
                    <thead class="bg-gray-50" id="normalisasi-thead">
                    </thead>
                    <tbody id="normalisasi-tbody"
                           class="divide-y divide-gray-100 bg-white">
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Calculation metadata -->
        <p id="calc-meta"
           class="text-xs text-gray-400 text-right"></p>

    </div><!-- /results-section -->

</div><!-- /space-y-6 -->

<script>
/**
 * Hasil Perhitungan — client-side logic
 *
 * Responsibilities:
 *   - Fetch kriteria list for column headers
 *   - POST to api/hitung.php to trigger SAW calculation
 *   - Render ranking table with rank-1 row highlighted
 *   - Render normalization matrix table
 *   - Render bar chart using Chart.js
 *   - Show top alternative as "Rekomendasi Utama"
 *
 * Requirements: 5.1, 5.2, 5.5
 */
(function () {
    'use strict';

    // ── State ────────────────────────────────────────────────────────────────

    /** @type {Array<{id:number, nama:string, bobot:number, tipe:string}>} */
    var kriteriaList = [];

    /** @type {Chart|null} */
    var chartInstance = null;

    // ── DOM helpers ──────────────────────────────────────────────────────────

    function el(id) { return document.getElementById(id); }
    function showEl(id) { el(id).classList.remove('hidden'); }
    function hideEl(id) { el(id).classList.add('hidden'); }

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

    // ── Error banner ─────────────────────────────────────────────────────────

    function showError(msg) {
        el('error-msg').textContent = msg || 'Terjadi kesalahan. Silakan coba lagi.';
        showEl('error-banner');
    }

    function hideError() {
        hideEl('error-banner');
    }

    // ── Loading state ─────────────────────────────────────────────────────────

    function setLoading(loading) {
        var btn     = el('btn-hitung');
        var icon    = el('hitung-icon');
        var spinner = el('hitung-spinner');
        var label   = el('hitung-label');

        btn.disabled = loading;
        if (loading) {
            icon.classList.add('hidden');
            spinner.classList.remove('hidden');
            label.textContent = 'Menghitung...';
        } else {
            icon.classList.remove('hidden');
            spinner.classList.add('hidden');
            label.textContent = 'Hitung SAW';
        }
    }

    // ── Fetch kriteria list ───────────────────────────────────────────────────

    function loadKriteria() {
        return fetch('../api/kriteria.php', {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-Token': typeof CSRF_TOKEN !== 'undefined' ? CSRF_TOKEN : '',
            },
            credentials: 'same-origin',
        })
        .then(function (res) {
            if (res.status === 401) {
                window.location.href = 'login.php';
                return null;
            }
            if (!res.ok) throw new Error('HTTP ' + res.status);
            return res.json();
        })
        .then(function (json) {
            if (!json) return;
            if (json.success) {
                kriteriaList = json.data || [];
            }
        })
        .catch(function (err) {
            console.warn('[Hasil] Could not load kriteria list:', err);
            // Non-fatal — column headers will fall back to kriteria IDs
        });
    }

    // ── Trigger calculation ───────────────────────────────────────────────────

    /**
     * Called when the user clicks "Hitung SAW".
     * Exposed on window so the onclick attribute can reach it.
     */
    window.jalankanHitung = function () {
        hideError();
        setLoading(true);

        // Ensure we have the latest kriteria names before rendering
        loadKriteria().then(function () {
            return fetch('../api/hitung.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-Token': typeof CSRF_TOKEN !== 'undefined' ? CSRF_TOKEN : '',
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    csrf_token: typeof CSRF_TOKEN !== 'undefined' ? CSRF_TOKEN : '',
                }),
            });
        })
        .then(function (res) {
            if (!res) return null; // redirected
            if (res.status === 401) {
                window.location.href = 'login.php';
                return null;
            }
            return res.json().then(function (json) {
                return { status: res.status, json: json };
            });
        })
        .then(function (result) {
            if (!result) return;

            var json = result.json;

            if (!json.success) {
                showError(json.message || 'Perhitungan gagal. Silakan coba lagi.');
                return;
            }

            renderResults(json.data);
        })
        .catch(function (err) {
            console.error('[Hasil] Fetch error:', err);
            showError('Gagal menghubungi server. Silakan coba lagi.');
        })
        .finally(function () {
            setLoading(false);
        });
    };

    // ── Render results ────────────────────────────────────────────────────────

    /**
     * Render all result sections from the API response data.
     *
     * @param {{
     *   riwayat_id: number,
     *   dihitung_pada: string,
     *   jumlah_kriteria: number,
     *   jumlah_alternatif: number,
     *   ranking: Array<{id:number, nama:string, preference:number, rank:number}>,
     *   normalisasi: Object
     * }} data
     */
    function renderResults(data) {
        var ranking     = data.ranking     || [];
        var normalisasi = data.normalisasi || {};

        // Sort ranking by rank ascending for display
        var sortedRanking = ranking.slice().sort(function (a, b) {
            return a.rank - b.rank;
        });

        renderRekomendasi(sortedRanking);
        renderRankingTable(sortedRanking);
        renderNormalisasiTable(normalisasi, sortedRanking);
        renderChart(sortedRanking);
        renderMeta(data);

        // Show the "Unduh Laporan" button and point it to the correct riwayat_id
        if (data.riwayat_id) {
            var btnUnduh = el('btn-unduh-laporan');
            btnUnduh.href = '../api/laporan.php?riwayat_id=' + encodeURIComponent(data.riwayat_id);
            btnUnduh.classList.remove('hidden');
        }

        showEl('results-section');

        // Scroll results into view smoothly
        el('results-section').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    // ── Rekomendasi Utama ─────────────────────────────────────────────────────

    /**
     * Show the top-ranked alternative as the main recommendation.
     *
     * @param {Array<{id:number, nama:string, preference:number, rank:number}>} sortedRanking
     */
    function renderRekomendasi(sortedRanking) {
        var top = sortedRanking.find(function (r) { return r.rank === 1; });
        if (!top) return;

        el('rekomendasi-nama').textContent  = top.nama;
        el('rekomendasi-nilai').textContent = top.preference.toFixed(4);
    }

    // ── Ranking table ─────────────────────────────────────────────────────────

    /**
     * Render the ranking table body.
     * The row with rank === 1 gets a green highlight.
     *
     * @param {Array<{id:number, nama:string, preference:number, rank:number}>} sortedRanking
     */
    function renderRankingTable(sortedRanking) {
        var tbody = el('ranking-tbody');

        if (sortedRanking.length === 0) {
            tbody.innerHTML =
                '<tr><td colspan="4" class="px-4 py-6 text-center text-sm text-gray-400">' +
                'Tidak ada data ranking.</td></tr>';
            return;
        }

        var rows = '';
        sortedRanking.forEach(function (item, idx) {
            var isTop    = item.rank === 1;
            var rowClass = isTop
                ? 'bg-green-50 border-l-4 border-green-400'
                : 'hover:bg-gray-50 transition-colors';

            var rankBadge = isTop
                ? '<span class="inline-flex items-center justify-center rounded-full ' +
                  'bg-green-500 text-white text-xs font-bold h-6 w-6 shadow-sm">' +
                  item.rank + '</span>'
                : '<span class="inline-flex items-center justify-center rounded-full ' +
                  'bg-gray-100 text-gray-600 text-xs font-medium h-6 w-6">' +
                  item.rank + '</span>';

            var namaCellClass = isTop
                ? 'px-4 py-3 font-semibold text-green-900'
                : 'px-4 py-3 font-medium text-gray-900';

            rows +=
                '<tr class="' + rowClass + '">' +
                  '<td class="px-4 py-3 text-gray-500 tabular-nums">' + (idx + 1) + '</td>' +
                  '<td class="' + namaCellClass + '">' +
                    escapeHtml(item.nama) +
                    (isTop
                        ? ' <span class="ml-1 text-xs font-medium text-green-600 ' +
                          'bg-green-100 rounded-full px-2 py-0.5">Terbaik</span>'
                        : '') +
                  '</td>' +
                  '<td class="px-4 py-3 text-right tabular-nums ' +
                    (isTop ? 'font-semibold text-green-800' : 'text-gray-700') + '">' +
                    item.preference.toFixed(4) +
                  '</td>' +
                  '<td class="px-4 py-3 text-center">' + rankBadge + '</td>' +
                '</tr>';
        });

        tbody.innerHTML = rows;
    }

    // ── Normalization matrix table ────────────────────────────────────────────

    /**
     * Render the normalization matrix table.
     * Columns = kriteria, rows = alternatif (sorted by rank).
     *
     * normalisasi structure from API: { "kriteria_id": { "alt_id": value, ... }, ... }
     *
     * @param {Object} normalisasi
     * @param {Array<{id:number, nama:string, preference:number, rank:number}>} sortedRanking
     */
    function renderNormalisasiTable(normalisasi, sortedRanking) {
        var thead = el('normalisasi-thead');
        var tbody = el('normalisasi-tbody');

        // Collect kriteria IDs from normalisasi keys
        var kriteriaIds = Object.keys(normalisasi).map(Number).sort(function (a, b) { return a - b; });

        if (kriteriaIds.length === 0 || sortedRanking.length === 0) {
            thead.innerHTML = '';
            tbody.innerHTML =
                '<tr><td class="px-4 py-6 text-center text-sm text-gray-400">' +
                'Tidak ada data normalisasi.</td></tr>';
            return;
        }

        // Build header row
        var headerCells = '<th scope="col" class="px-4 py-3 text-left text-xs font-semibold ' +
            'text-gray-500 uppercase tracking-wider sticky left-0 bg-gray-50 z-10">Alternatif</th>';

        kriteriaIds.forEach(function (kid) {
            // Look up kriteria name from the loaded list
            var krit = kriteriaList.find(function (k) { return k.id === kid; });
            var label = krit ? escapeHtml(krit.nama) : 'K' + kid;
            var tipe  = krit ? krit.tipe : '';
            var tipeBadge = tipe
                ? ' <span class="block text-gray-400 font-normal normal-case tracking-normal ' +
                  'text-xs mt-0.5">' + (tipe === 'benefit' ? 'Benefit' : 'Cost') + '</span>'
                : '';

            headerCells +=
                '<th scope="col" class="px-4 py-3 text-right text-xs font-semibold ' +
                'text-gray-500 uppercase tracking-wider">' +
                label + tipeBadge +
                '</th>';
        });

        thead.innerHTML = '<tr>' + headerCells + '</tr>';

        // Build data rows
        var rows = '';
        sortedRanking.forEach(function (item) {
            var isTop    = item.rank === 1;
            var rowClass = isTop
                ? 'bg-green-50 border-l-4 border-green-400'
                : 'hover:bg-gray-50 transition-colors';

            var namaCellClass = isTop
                ? 'px-4 py-3 font-semibold text-green-900 sticky left-0 bg-green-50 z-10'
                : 'px-4 py-3 font-medium text-gray-900 sticky left-0 bg-white z-10';

            var cells = '<td class="' + namaCellClass + '">' + escapeHtml(item.nama) + '</td>';

            kriteriaIds.forEach(function (kid) {
                var kidStr = String(kid);
                var aidStr = String(item.id);
                var val    = (normalisasi[kidStr] && normalisasi[kidStr][aidStr] !== undefined)
                    ? Number(normalisasi[kidStr][aidStr]).toFixed(4)
                    : '—';

                cells +=
                    '<td class="px-4 py-3 text-right tabular-nums ' +
                    (isTop ? 'text-green-800' : 'text-gray-700') + '">' +
                    val + '</td>';
            });

            rows += '<tr class="' + rowClass + '">' + cells + '</tr>';
        });

        tbody.innerHTML = rows;
    }

    // ── Bar chart ─────────────────────────────────────────────────────────────

    /**
     * Render (or update) the preference bar chart using Chart.js.
     *
     * @param {Array<{id:number, nama:string, preference:number, rank:number}>} sortedRanking
     */
    function renderChart(sortedRanking) {
        var canvas = el('preference-chart');
        if (!canvas) return;

        // Destroy previous chart instance to avoid canvas reuse errors
        if (chartInstance) {
            chartInstance.destroy();
            chartInstance = null;
        }

        var labels = sortedRanking.map(function (r) { return r.nama; });
        var values = sortedRanking.map(function (r) { return r.preference; });

        // Color: green for rank 1, blue for others
        var bgColors = sortedRanking.map(function (r) {
            return r.rank === 1 ? 'rgba(34, 197, 94, 0.85)' : 'rgba(59, 130, 246, 0.75)';
        });
        var borderColors = sortedRanking.map(function (r) {
            return r.rank === 1 ? 'rgb(22, 163, 74)' : 'rgb(37, 99, 235)';
        });

        // Wait for Chart.js to be available (loaded with defer)
        function tryRender() {
            if (typeof Chart === 'undefined') {
                setTimeout(tryRender, 100);
                return;
            }

            chartInstance = new Chart(canvas, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Nilai Preferensi',
                        data: values,
                        backgroundColor: bgColors,
                        borderColor: borderColors,
                        borderWidth: 1.5,
                        borderRadius: 4,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function (ctx) {
                                    return ' ' + ctx.parsed.y.toFixed(4);
                                },
                            },
                        },
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 1,
                            ticks: {
                                stepSize: 0.2,
                                callback: function (val) {
                                    return val.toFixed(1);
                                },
                            },
                            grid: { color: 'rgba(0,0,0,0.06)' },
                        },
                        x: {
                            grid: { display: false },
                            ticks: {
                                maxRotation: 30,
                                minRotation: 0,
                            },
                        },
                    },
                },
            });
        }

        tryRender();
    }

    // ── Calculation metadata ──────────────────────────────────────────────────

    /**
     * Show calculation timestamp and counts below the results.
     *
     * @param {{dihitung_pada:string, jumlah_kriteria:number, jumlah_alternatif:number}} data
     */
    function renderMeta(data) {
        var metaEl = el('calc-meta');
        if (!metaEl) return;

        var ts = data.dihitung_pada
            ? new Date(data.dihitung_pada.replace(' ', 'T')).toLocaleString('id-ID', {
                day: '2-digit', month: 'long', year: 'numeric',
                hour: '2-digit', minute: '2-digit',
              })
            : '';

        metaEl.textContent =
            'Dihitung pada: ' + ts +
            ' · ' + data.jumlah_kriteria + ' kriteria' +
            ' · ' + data.jumlah_alternatif + ' alternatif';
    }

}());
</script>

<?php require_once '../src/layout/footer.php'; ?>
