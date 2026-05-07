<?php

/**
 * Halaman Manajemen Kriteria
 *
 * Menyediakan antarmuka untuk:
 *   - Menampilkan daftar kriteria dalam tabel yang dapat diurutkan
 *   - Menambah kriteria baru via form dengan validasi client-side
 *   - Mengedit kriteria yang sudah ada
 *   - Menghapus kriteria dengan konfirmasi dialog
 *   - Menampilkan pesan error untuk nama duplikat (HTTP 409)
 *
 * Requirements: 2.1, 2.2, 2.3, 2.4, 2.6, 2.7
 */

declare(strict_types=1);

require_once '../src/middleware/auth_guard.php';
require_once '../src/helpers/csrf.php';

requireAuth();
$csrfToken = generateCsrfToken();
$pageTitle = 'Kriteria';

require_once '../src/layout/main.php';
?>

<!-- ── Kriteria Page Content ──────────────────────────────────────────────── -->

<div class="space-y-6">

    <!-- Page heading + Add button -->
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-xl font-semibold text-gray-800">Manajemen Kriteria</h2>
            <p class="mt-1 text-sm text-gray-500">Kelola kriteria evaluasi beserta bobot dan tipenya.</p>
        </div>
        <button type="button"
                id="btn-tambah"
                onclick="openFormModal()"
                class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2
                       text-sm font-medium text-white shadow-sm hover:bg-primary-700
                       focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2
                       transition-colors">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
            </svg>
            Tambah Kriteria
        </button>
    </div>

    <!-- Alert banner (success / error) -->
    <div id="alert-banner"
         role="alert"
         aria-live="polite"
         class="hidden flex items-start gap-3 rounded-lg border px-4 py-3 text-sm">
        <svg id="alert-icon" class="mt-0.5 h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24"
             stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round"
                  d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0
                     2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898
                     0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>
        </svg>
        <span id="alert-msg"></span>
    </div>

    <!-- Criteria table card -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">

        <!-- Table toolbar: total bobot indicator -->
        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100 bg-gray-50">
            <p class="text-sm text-gray-600">
                Total bobot:
                <span id="total-bobot"
                      class="font-semibold tabular-nums text-gray-900">—</span>
                <span id="bobot-warning"
                      class="hidden ml-2 text-xs font-medium text-amber-700 bg-amber-100
                             rounded-full px-2 py-0.5">
                    Harus = 1.00
                </span>
            </p>
            <p id="row-count" class="text-xs text-gray-400"></p>
        </div>

        <!-- Scrollable table wrapper -->
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm" id="kriteria-table">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col"
                            class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider w-10">
                            No
                        </th>
                        <th scope="col"
                            data-col="nama"
                            onclick="sortTable('nama')"
                            class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase
                                   tracking-wider cursor-pointer select-none hover:bg-gray-100
                                   transition-colors group">
                            <span class="flex items-center gap-1">
                                Nama Kriteria
                                <span id="sort-icon-nama" class="text-gray-300 group-hover:text-gray-500">&#8597;</span>
                            </span>
                        </th>
                        <th scope="col"
                            data-col="bobot"
                            onclick="sortTable('bobot')"
                            class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase
                                   tracking-wider cursor-pointer select-none hover:bg-gray-100
                                   transition-colors group">
                            <span class="flex items-center gap-1">
                                Bobot
                                <span id="sort-icon-bobot" class="text-gray-300 group-hover:text-gray-500">&#8597;</span>
                            </span>
                        </th>
                        <th scope="col"
                            data-col="tipe"
                            onclick="sortTable('tipe')"
                            class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase
                                   tracking-wider cursor-pointer select-none hover:bg-gray-100
                                   transition-colors group">
                            <span class="flex items-center gap-1">
                                Tipe
                                <span id="sort-icon-tipe" class="text-gray-300 group-hover:text-gray-500">&#8597;</span>
                            </span>
                        </th>
                        <th scope="col"
                            class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">
                            Aksi
                        </th>
                    </tr>
                </thead>
                <tbody id="kriteria-tbody" class="divide-y divide-gray-100 bg-white">
                    <!-- Loading skeleton -->
                    <tr id="row-loading">
                        <td colspan="5" class="px-4 py-8 text-center text-sm text-gray-400">
                            <div class="flex items-center justify-center gap-2">
                                <svg class="animate-spin h-4 w-4 text-primary-500" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                                </svg>
                                Memuat data...
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

</div><!-- /space-y-6 -->

<!-- ── Add / Edit Modal ───────────────────────────────────────────────────── -->

<div id="form-modal"
     role="dialog"
     aria-modal="true"
     aria-labelledby="modal-title"
     class="hidden fixed inset-0 z-50 flex items-center justify-center p-4">

    <!-- Backdrop -->
    <div class="absolute inset-0 bg-black/50" onclick="closeFormModal()"></div>

    <!-- Dialog panel -->
    <div class="relative bg-white rounded-xl shadow-xl w-full max-w-md mx-auto">

        <!-- Header -->
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
            <h3 id="modal-title" class="text-base font-semibold text-gray-800">Tambah Kriteria</h3>
            <button type="button"
                    onclick="closeFormModal()"
                    class="rounded-md p-1 text-gray-400 hover:text-gray-600 hover:bg-gray-100
                           focus:outline-none focus:ring-2 focus:ring-primary-500 transition-colors"
                    aria-label="Tutup">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <!-- Form -->
        <form id="kriteria-form" novalidate onsubmit="handleFormSubmit(event)">
            <input type="hidden" id="form-id" name="id" value="">

            <div class="px-6 py-5 space-y-4">

                <!-- Nama -->
                <div>
                    <label for="form-nama"
                           class="block text-sm font-medium text-gray-700 mb-1">
                        Nama Kriteria <span class="text-red-500" aria-hidden="true">*</span>
                    </label>
                    <input type="text"
                           id="form-nama"
                           name="nama"
                           maxlength="100"
                           required
                           autocomplete="off"
                           placeholder="Contoh: Harga, Kualitas, Jarak"
                           class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm
                                  text-gray-900 placeholder-gray-400 shadow-sm
                                  focus:border-primary-500 focus:outline-none focus:ring-1
                                  focus:ring-primary-500 transition-colors">
                    <p id="error-nama" class="hidden mt-1 text-xs text-red-600" role="alert"></p>
                </div>

                <!-- Bobot -->
                <div>
                    <label for="form-bobot"
                           class="block text-sm font-medium text-gray-700 mb-1">
                        Bobot <span class="text-red-500" aria-hidden="true">*</span>
                        <span class="font-normal text-gray-400">(0.01 – 1.00)</span>
                    </label>
                    <input type="number"
                           id="form-bobot"
                           name="bobot"
                           min="0.01"
                           max="1.00"
                           step="0.01"
                           required
                           placeholder="Contoh: 0.30"
                           class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm
                                  text-gray-900 placeholder-gray-400 shadow-sm
                                  focus:border-primary-500 focus:outline-none focus:ring-1
                                  focus:ring-primary-500 transition-colors">
                    <p id="error-bobot" class="hidden mt-1 text-xs text-red-600" role="alert"></p>
                </div>

                <!-- Tipe -->
                <div>
                    <label for="form-tipe"
                           class="block text-sm font-medium text-gray-700 mb-1">
                        Tipe <span class="text-red-500" aria-hidden="true">*</span>
                    </label>
                    <select id="form-tipe"
                            name="tipe"
                            required
                            class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm
                                   text-gray-900 shadow-sm bg-white
                                   focus:border-primary-500 focus:outline-none focus:ring-1
                                   focus:ring-primary-500 transition-colors">
                        <option value="" disabled selected>Pilih tipe kriteria</option>
                        <option value="benefit">Benefit (nilai lebih tinggi lebih baik)</option>
                        <option value="cost">Cost (nilai lebih rendah lebih baik)</option>
                    </select>
                    <p id="error-tipe" class="hidden mt-1 text-xs text-red-600" role="alert"></p>
                </div>

                <!-- API error (e.g. duplicate name from server) -->
                <div id="form-api-error"
                     class="hidden rounded-lg bg-red-50 border border-red-200 px-3 py-2
                            text-sm text-red-700"
                     role="alert">
                </div>

            </div><!-- /fields -->

            <!-- Footer buttons -->
            <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-200 bg-gray-50 rounded-b-xl">
                <button type="button"
                        onclick="closeFormModal()"
                        class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm
                               font-medium text-gray-700 shadow-sm hover:bg-gray-50
                               focus:outline-none focus:ring-2 focus:ring-primary-500
                               transition-colors">
                    Batal
                </button>
                <button type="submit"
                        id="btn-submit"
                        class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2
                               text-sm font-medium text-white shadow-sm hover:bg-primary-700
                               focus:outline-none focus:ring-2 focus:ring-primary-500
                               disabled:opacity-60 disabled:cursor-not-allowed transition-colors">
                    <svg id="submit-spinner"
                         class="hidden animate-spin h-4 w-4 text-white"
                         fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10"
                                stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                    </svg>
                    <span id="submit-label">Simpan</span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ── Delete Confirmation Modal ─────────────────────────────────────────── -->

<div id="delete-modal"
     role="dialog"
     aria-modal="true"
     aria-labelledby="delete-modal-title"
     class="hidden fixed inset-0 z-50 flex items-center justify-center p-4">

    <!-- Backdrop -->
    <div class="absolute inset-0 bg-black/50" onclick="closeDeleteModal()"></div>

    <!-- Dialog panel -->
    <div class="relative bg-white rounded-xl shadow-xl w-full max-w-sm mx-auto">
        <div class="px-6 py-5">
            <div class="flex items-start gap-4">
                <div class="flex-shrink-0 h-10 w-10 rounded-full bg-red-100 flex items-center justify-center">
                    <svg class="h-5 w-5 text-red-600" fill="none" viewBox="0 0 24 24"
                         stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0
                                 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898
                                 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>
                    </svg>
                </div>
                <div>
                    <h3 id="delete-modal-title" class="text-base font-semibold text-gray-800">Hapus Kriteria</h3>
                    <p class="mt-1 text-sm text-gray-600">
                        Apakah Anda yakin ingin menghapus kriteria
                        <strong id="delete-nama" class="text-gray-800"></strong>?
                        Seluruh nilai alternatif yang terkait juga akan dihapus.
                        Tindakan ini tidak dapat dibatalkan.
                    </p>
                </div>
            </div>
        </div>
        <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-200 bg-gray-50 rounded-b-xl">
            <button type="button"
                    onclick="closeDeleteModal()"
                    class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm
                           font-medium text-gray-700 shadow-sm hover:bg-gray-50
                           focus:outline-none focus:ring-2 focus:ring-primary-500 transition-colors">
                Batal
            </button>
            <button type="button"
                    id="btn-confirm-delete"
                    onclick="confirmDelete()"
                    class="inline-flex items-center gap-2 rounded-lg bg-red-600 px-4 py-2
                           text-sm font-medium text-white shadow-sm hover:bg-red-700
                           focus:outline-none focus:ring-2 focus:ring-red-500
                           disabled:opacity-60 disabled:cursor-not-allowed transition-colors">
                <svg id="delete-spinner"
                     class="hidden animate-spin h-4 w-4 text-white"
                     fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10"
                            stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                </svg>
                Hapus
            </button>
        </div>
    </div>
</div>

<script>
/**
 * Kriteria page — client-side logic
 *
 * Responsibilities:
 *   - Load and render the criteria list from the API
 *   - Client-side sortable table (nama, bobot, tipe)
 *   - Add / Edit modal with client-side validation
 *   - Delete confirmation modal
 *   - Show server-side error messages (e.g. HTTP 409 duplicate name)
 *   - Total bobot indicator with warning when != 1.00
 *
 * Requirements: 2.1, 2.2, 2.3, 2.4, 2.6, 2.7
 */
(function () {
    'use strict';

    // ── State ────────────────────────────────────────────────────────────────

    /** @type {Array<{id:number, nama:string, bobot:number, tipe:string}>} */
    var kriteriaList = [];

    /** Current sort column and direction */
    var sortCol = '';
    var sortDir = 'asc'; // 'asc' | 'desc'

    /** ID of the row being edited (null = add mode) */
    var editingId = null;

    /** ID of the row pending deletion */
    var deletingId = null;

    // ── DOM helpers ──────────────────────────────────────────────────────────

    function el(id) { return document.getElementById(id); }

    function showEl(id)  { el(id).classList.remove('hidden'); }
    function hideEl(id)  { el(id).classList.add('hidden'); }

    // ── Alert banner ─────────────────────────────────────────────────────────

    var alertTimer = null;

    /**
     * Show the page-level alert banner.
     *
     * @param {string}  msg
     * @param {'success'|'error'} type
     */
    function showAlert(msg, type) {
        var banner  = el('alert-banner');
        var msgEl   = el('alert-msg');
        var iconEl  = el('alert-icon');

        msgEl.textContent = msg;

        // Reset classes
        banner.className = 'flex items-start gap-3 rounded-lg border px-4 py-3 text-sm';

        if (type === 'success') {
            banner.classList.add('bg-green-50', 'border-green-200', 'text-green-700');
            iconEl.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>';
        } else {
            banner.classList.add('bg-red-50', 'border-red-200', 'text-red-700');
            iconEl.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>';
        }

        banner.classList.remove('hidden');

        // Auto-hide after 5 s
        if (alertTimer) clearTimeout(alertTimer);
        alertTimer = setTimeout(function () {
            banner.classList.add('hidden');
        }, 5000);
    }

    // ── Fetch & render ───────────────────────────────────────────────────────

    function loadKriteria() {
        fetch('../api/kriteria.php', {
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
            if (!json.success) {
                renderError(json.message || 'Gagal memuat data.');
                return;
            }
            kriteriaList = json.data || [];
            renderTable();
        })
        .catch(function (err) {
            console.error('[Kriteria] load error:', err);
            renderError('Gagal memuat data. Silakan muat ulang halaman.');
        });
    }

    function renderError(msg) {
        var tbody = el('kriteria-tbody');
        tbody.innerHTML =
            '<tr><td colspan="5" class="px-4 py-8 text-center text-sm text-red-500">' +
            escapeHtml(msg) + '</td></tr>';
        el('total-bobot').textContent = '—';
        el('row-count').textContent = '';
        hideEl('bobot-warning');
    }

    /**
     * Sort kriteriaList in-place according to sortCol / sortDir.
     */
    function sortData() {
        if (!sortCol) return;

        kriteriaList.sort(function (a, b) {
            var va = a[sortCol];
            var vb = b[sortCol];

            var cmp;
            if (typeof va === 'number') {
                cmp = va - vb;
            } else {
                cmp = String(va).localeCompare(String(vb), 'id');
            }
            return sortDir === 'asc' ? cmp : -cmp;
        });
    }

    /**
     * Re-render the table body from kriteriaList.
     */
    function renderTable() {
        sortData();
        updateSortIcons();

        var tbody = el('kriteria-tbody');

        if (kriteriaList.length === 0) {
            tbody.innerHTML =
                '<tr><td colspan="5" class="px-4 py-8 text-center text-sm text-gray-400">' +
                'Belum ada kriteria. Klik "Tambah Kriteria" untuk memulai.</td></tr>';
            el('total-bobot').textContent = '0.00';
            el('row-count').textContent = '';
            hideEl('bobot-warning');
            return;
        }

        var rows = '';
        kriteriaList.forEach(function (k, idx) {
            var tipeBadge = k.tipe === 'benefit'
                ? '<span class="inline-flex items-center rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700">Benefit</span>'
                : '<span class="inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-700">Cost</span>';

            rows +=
                '<tr class="hover:bg-gray-50 transition-colors">' +
                  '<td class="px-4 py-3 text-gray-500 tabular-nums">' + (idx + 1) + '</td>' +
                  '<td class="px-4 py-3 font-medium text-gray-900">' + escapeHtml(k.nama) + '</td>' +
                  '<td class="px-4 py-3 tabular-nums text-gray-700">' + k.bobot.toFixed(2) + '</td>' +
                  '<td class="px-4 py-3">' + tipeBadge + '</td>' +
                  '<td class="px-4 py-3 text-right">' +
                    '<div class="inline-flex items-center gap-2">' +
                      '<button type="button" ' +
                              'onclick="openFormModal(' + k.id + ')" ' +
                              'class="rounded-md px-2.5 py-1 text-xs font-medium text-primary-700 ' +
                                     'bg-primary-50 hover:bg-primary-100 border border-primary-200 ' +
                                     'focus:outline-none focus:ring-2 focus:ring-primary-500 transition-colors" ' +
                              'aria-label="Edit ' + escapeAttr(k.nama) + '">' +
                        'Edit' +
                      '</button>' +
                      '<button type="button" ' +
                              'onclick="openDeleteModal(' + k.id + ', \'' + escapeAttr(k.nama) + '\')" ' +
                              'class="rounded-md px-2.5 py-1 text-xs font-medium text-red-700 ' +
                                     'bg-red-50 hover:bg-red-100 border border-red-200 ' +
                                     'focus:outline-none focus:ring-2 focus:ring-red-500 transition-colors" ' +
                              'aria-label="Hapus ' + escapeAttr(k.nama) + '">' +
                        'Hapus' +
                      '</button>' +
                    '</div>' +
                  '</td>' +
                '</tr>';
        });

        tbody.innerHTML = rows;

        // Update total bobot
        var total = kriteriaList.reduce(function (sum, k) { return sum + k.bobot; }, 0);
        var totalStr = total.toFixed(2);
        el('total-bobot').textContent = totalStr;
        el('row-count').textContent = kriteriaList.length + ' kriteria';

        // Show warning if total != 1.00
        if (Math.abs(total - 1.0) > 0.001) {
            showEl('bobot-warning');
        } else {
            hideEl('bobot-warning');
        }
    }

    // ── Sort ─────────────────────────────────────────────────────────────────

    /**
     * Called by onclick on sortable column headers.
     * @param {string} col  'nama' | 'bobot' | 'tipe'
     */
    window.sortTable = function (col) {
        if (sortCol === col) {
            sortDir = sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            sortCol = col;
            sortDir = 'asc';
        }
        renderTable();
    };

    function updateSortIcons() {
        ['nama', 'bobot', 'tipe'].forEach(function (col) {
            var icon = el('sort-icon-' + col);
            if (!icon) return;
            if (col === sortCol) {
                icon.innerHTML = sortDir === 'asc' ? '&#8593;' : '&#8595;';
                icon.classList.remove('text-gray-300');
                icon.classList.add('text-primary-600');
            } else {
                icon.innerHTML = '&#8597;';
                icon.classList.remove('text-primary-600');
                icon.classList.add('text-gray-300');
            }
        });
    }

    // ── Add / Edit modal ─────────────────────────────────────────────────────

    /**
     * Open the form modal.
     * @param {number|undefined} id  If provided, populate form for editing.
     */
    window.openFormModal = function (id) {
        resetForm();
        hideEl('form-api-error');

        if (id !== undefined) {
            // Edit mode
            var k = kriteriaList.find(function (x) { return x.id === id; });
            if (!k) return;

            editingId = id;
            el('modal-title').textContent = 'Edit Kriteria';
            el('submit-label').textContent = 'Simpan Perubahan';
            el('form-id').value    = k.id;
            el('form-nama').value  = k.nama;
            el('form-bobot').value = k.bobot.toFixed(2);
            el('form-tipe').value  = k.tipe;
        } else {
            // Add mode
            editingId = null;
            el('modal-title').textContent = 'Tambah Kriteria';
            el('submit-label').textContent = 'Simpan';
            el('form-id').value = '';
        }

        el('form-modal').classList.remove('hidden');
        el('form-nama').focus();
    };

    window.closeFormModal = function () {
        el('form-modal').classList.add('hidden');
        resetForm();
    };

    function resetForm() {
        el('kriteria-form').reset();
        el('form-id').value = '';
        editingId = null;
        ['nama', 'bobot', 'tipe'].forEach(function (f) {
            hideEl('error-' + f);
            el('error-' + f).textContent = '';
            var input = el('form-' + f);
            if (input) {
                input.classList.remove('border-red-500', 'focus:ring-red-500', 'focus:border-red-500');
                input.classList.add('border-gray-300', 'focus:ring-primary-500', 'focus:border-primary-500');
            }
        });
        hideEl('form-api-error');
        el('form-api-error').textContent = '';
        setSubmitLoading(false);
    }

    // ── Client-side validation ────────────────────────────────────────────────

    /**
     * Validate the form fields.
     * @returns {boolean} true if valid
     */
    function validateForm() {
        var valid = true;

        // nama
        var nama = el('form-nama').value.trim();
        if (nama === '') {
            setFieldError('nama', 'Nama kriteria tidak boleh kosong');
            valid = false;
        } else if (nama.length > 100) {
            setFieldError('nama', 'Nama kriteria maksimal 100 karakter');
            valid = false;
        } else {
            clearFieldError('nama');
        }

        // bobot
        var bobotRaw = el('form-bobot').value.trim();
        var bobot    = parseFloat(bobotRaw);
        if (bobotRaw === '' || isNaN(bobot)) {
            setFieldError('bobot', 'Bobot harus berupa angka');
            valid = false;
        } else if (bobot < 0.01 || bobot > 1.00) {
            setFieldError('bobot', 'Bobot harus berada dalam rentang 0.01 hingga 1.00');
            valid = false;
        } else {
            clearFieldError('bobot');
        }

        // tipe
        var tipe = el('form-tipe').value;
        if (tipe !== 'benefit' && tipe !== 'cost') {
            setFieldError('tipe', 'Pilih tipe kriteria');
            valid = false;
        } else {
            clearFieldError('tipe');
        }

        return valid;
    }

    function setFieldError(field, msg) {
        var errEl = el('error-' + field);
        errEl.textContent = msg;
        showEl('error-' + field);
        var input = el('form-' + field);
        if (input) {
            input.classList.add('border-red-500', 'focus:ring-red-500', 'focus:border-red-500');
            input.classList.remove('border-gray-300', 'focus:ring-primary-500', 'focus:border-primary-500');
        }
    }

    function clearFieldError(field) {
        var errEl = el('error-' + field);
        errEl.textContent = '';
        hideEl('error-' + field);
        var input = el('form-' + field);
        if (input) {
            input.classList.remove('border-red-500', 'focus:ring-red-500', 'focus:border-red-500');
            input.classList.add('border-gray-300', 'focus:ring-primary-500', 'focus:border-primary-500');
        }
    }

    // ── Form submit ───────────────────────────────────────────────────────────

    window.handleFormSubmit = function (event) {
        event.preventDefault();
        hideEl('form-api-error');

        if (!validateForm()) return;

        var isEdit = editingId !== null;
        var payload = {
            csrf_token: typeof CSRF_TOKEN !== 'undefined' ? CSRF_TOKEN : '',
            nama:  el('form-nama').value.trim(),
            bobot: parseFloat(el('form-bobot').value),
            tipe:  el('form-tipe').value,
        };
        if (isEdit) {
            payload.id = editingId;
        }

        setSubmitLoading(true);

        fetch('../api/kriteria.php', {
            method: isEdit ? 'PUT' : 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-Token': payload.csrf_token,
            },
            credentials: 'same-origin',
            body: JSON.stringify(payload),
        })
        .then(function (res) {
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
            setSubmitLoading(false);

            var json   = result.json;
            var status = result.status;

            if (!json.success) {
                // Show server error inside the modal
                var apiErr = el('form-api-error');
                apiErr.textContent = json.message || 'Terjadi kesalahan.';
                showEl('form-api-error');

                // Highlight the nama field for duplicate-name errors (HTTP 409)
                if (status === 409) {
                    setFieldError('nama', json.message || 'Nama kriteria sudah digunakan');
                }
                return;
            }

            // Success
            closeFormModal();
            loadKriteria();
            showAlert(
                isEdit ? 'Kriteria berhasil diperbarui.' : 'Kriteria berhasil ditambahkan.',
                'success'
            );
        })
        .catch(function (err) {
            console.error('[Kriteria] submit error:', err);
            setSubmitLoading(false);
            var apiErr = el('form-api-error');
            apiErr.textContent = 'Terjadi kesalahan jaringan. Silakan coba lagi.';
            showEl('form-api-error');
        });
    };

    function setSubmitLoading(loading) {
        var btn     = el('btn-submit');
        var spinner = el('submit-spinner');
        var label   = el('submit-label');

        if (loading) {
            btn.disabled = true;
            showEl('submit-spinner');
            label.textContent = 'Menyimpan...';
        } else {
            btn.disabled = false;
            hideEl('submit-spinner');
            // Restore label based on mode
            label.textContent = editingId !== null ? 'Simpan Perubahan' : 'Simpan';
        }
    }

    // ── Delete modal ──────────────────────────────────────────────────────────

    /**
     * Open the delete confirmation modal.
     * @param {number} id
     * @param {string} nama
     */
    window.openDeleteModal = function (id, nama) {
        deletingId = id;
        el('delete-nama').textContent = nama;
        el('delete-modal').classList.remove('hidden');
    };

    window.closeDeleteModal = function () {
        el('delete-modal').classList.add('hidden');
        deletingId = null;
        setDeleteLoading(false);
    };

    window.confirmDelete = function () {
        if (deletingId === null) return;

        setDeleteLoading(true);

        fetch('../api/kriteria.php', {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-Token': typeof CSRF_TOKEN !== 'undefined' ? CSRF_TOKEN : '',
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                csrf_token: typeof CSRF_TOKEN !== 'undefined' ? CSRF_TOKEN : '',
                id: deletingId,
            }),
        })
        .then(function (res) {
            if (res.status === 401) {
                window.location.href = 'login.php';
                return null;
            }
            return res.json();
        })
        .then(function (json) {
            if (!json) return;
            setDeleteLoading(false);

            if (!json.success) {
                closeDeleteModal();
                showAlert(json.message || 'Gagal menghapus kriteria.', 'error');
                return;
            }

            closeDeleteModal();
            loadKriteria();
            showAlert('Kriteria berhasil dihapus.', 'success');
        })
        .catch(function (err) {
            console.error('[Kriteria] delete error:', err);
            setDeleteLoading(false);
            closeDeleteModal();
            showAlert('Terjadi kesalahan jaringan. Silakan coba lagi.', 'error');
        });
    };

    function setDeleteLoading(loading) {
        var btn     = el('btn-confirm-delete');
        var spinner = el('delete-spinner');

        if (loading) {
            btn.disabled = true;
            showEl('delete-spinner');
        } else {
            btn.disabled = false;
            hideEl('delete-spinner');
        }
    }

    // ── Keyboard: close modals on Escape ─────────────────────────────────────

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            if (!el('form-modal').classList.contains('hidden'))   closeFormModal();
            if (!el('delete-modal').classList.contains('hidden')) closeDeleteModal();
        }
    });

    // ── Utility ───────────────────────────────────────────────────────────────

    function escapeHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function escapeAttr(str) {
        return String(str)
            .replace(/\\/g, '\\\\')
            .replace(/'/g, "\\'");
    }

    // ── Init ──────────────────────────────────────────────────────────────────

    loadKriteria();

}());
</script>

<?php require_once '../src/layout/footer.php'; ?>
