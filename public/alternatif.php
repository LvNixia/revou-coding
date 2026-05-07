<?php

/**
 * Halaman Manajemen Alternatif
 *
 * Menyediakan antarmuka untuk:
 *   - Menampilkan daftar alternatif beserta nilai setiap kriteria
 *   - Menambah alternatif baru via form dengan field dinamis per kriteria
 *   - Mengedit alternatif yang sudah ada
 *   - Menghapus alternatif dengan konfirmasi dialog
 *   - Menonaktifkan form jika belum ada kriteria
 *   - Menampilkan pesan error untuk nama duplikat (HTTP 409)
 *
 * Requirements: 3.1, 3.2, 3.3, 3.4, 3.5, 3.6, 3.7, 3.8
 */

declare(strict_types=1);

require_once '../src/middleware/auth_guard.php';
require_once '../src/helpers/csrf.php';

requireAuth();
$csrfToken = generateCsrfToken();
$pageTitle = 'Alternatif';

require_once '../src/layout/main.php';
?>

<!-- ── Alternatif Page Content ───────────────────────────────────────────── -->

<div class="space-y-6">

    <!-- Page heading + Add button -->
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-xl font-semibold text-gray-800">Manajemen Alternatif</h2>
            <p class="mt-1 text-sm text-gray-500">Kelola alternatif beserta nilai setiap kriteria.</p>
        </div>
        <div class="flex flex-col items-start gap-2 sm:items-end">
            <button type="button"
                    id="btn-tambah"
                    onclick="openFormModal()"
                    class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2
                           text-sm font-medium text-white shadow-sm hover:bg-primary-700
                           focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2
                           disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
                </svg>
                Tambah Alternatif
            </button>
            <p id="no-kriteria-msg"
               class="hidden text-xs text-amber-700 bg-amber-50 border border-amber-200
                      rounded-md px-2 py-1">
                Tambahkan kriteria terlebih dahulu
            </p>
        </div>
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

    <!-- Alternatif table card -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">

        <!-- Table toolbar -->
        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100 bg-gray-50">
            <p class="text-sm text-gray-600">Daftar alternatif</p>
            <p id="row-count" class="text-xs text-gray-400"></p>
        </div>

        <!-- Scrollable table wrapper -->
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm" id="alternatif-table">
                <thead class="bg-gray-50" id="alternatif-thead">
                    <tr id="thead-row">
                        <th scope="col"
                            class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider w-10">
                            No
                        </th>
                        <th scope="col"
                            class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">
                            Nama Alternatif
                        </th>
                        <!-- Dynamic kriteria columns injected here by JS -->
                        <th scope="col"
                            class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">
                            Aksi
                        </th>
                    </tr>
                </thead>
                <tbody id="alternatif-tbody" class="divide-y divide-gray-100 bg-white">
                    <!-- Loading skeleton -->
                    <tr id="row-loading">
                        <td colspan="3" class="px-4 py-8 text-center text-sm text-gray-400">
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
    <div class="relative bg-white rounded-xl shadow-xl w-full max-w-lg mx-auto max-h-screen overflow-y-auto">

        <!-- Header -->
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 sticky top-0 bg-white z-10">
            <h3 id="modal-title" class="text-base font-semibold text-gray-800">Tambah Alternatif</h3>
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
        <form id="alternatif-form" novalidate onsubmit="handleFormSubmit(event)">
            <input type="hidden" id="form-id" name="id" value="">

            <div class="px-6 py-5 space-y-4">

                <!-- Nama -->
                <div>
                    <label for="form-nama"
                           class="block text-sm font-medium text-gray-700 mb-1">
                        Nama Alternatif <span class="text-red-500" aria-hidden="true">*</span>
                    </label>
                    <input type="text"
                           id="form-nama"
                           name="nama"
                           maxlength="100"
                           required
                           autocomplete="off"
                           placeholder="Contoh: Produk A, Vendor B"
                           class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm
                                  text-gray-900 placeholder-gray-400 shadow-sm
                                  focus:border-primary-500 focus:outline-none focus:ring-1
                                  focus:ring-primary-500 transition-colors">
                    <p id="error-nama" class="hidden mt-1 text-xs text-red-600" role="alert"></p>
                </div>

                <!-- Dynamic kriteria value fields (injected by JS) -->
                <div id="kriteria-fields">
                    <!-- Fields will be rendered here by renderKriteriaFields() -->
                </div>

                <!-- API error (e.g. duplicate name from server) -->
                <div id="form-api-error"
                     class="hidden rounded-lg bg-red-50 border border-red-200 px-3 py-2
                            text-sm text-red-700"
                     role="alert">
                </div>

            </div><!-- /fields -->

            <!-- Footer buttons -->
            <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-200 bg-gray-50 rounded-b-xl sticky bottom-0">
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
                    <h3 id="delete-modal-title" class="text-base font-semibold text-gray-800">Hapus Alternatif</h3>
                    <p class="mt-1 text-sm text-gray-600">
                        Apakah Anda yakin ingin menghapus alternatif
                        <strong id="delete-nama" class="text-gray-800"></strong>?
                        Seluruh nilai kriteria yang terkait juga akan dihapus.
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
 * Alternatif page — client-side logic
 *
 * Responsibilities:
 *   - Load kriteria list from API to build dynamic table columns and form fields
 *   - Load and render the alternatif list from the API
 *   - Add / Edit modal with dynamic per-kriteria numeric fields
 *   - Disable "Tambah Alternatif" button and show message when no kriteria exist
 *   - Delete confirmation modal
 *   - Show server-side error messages (e.g. HTTP 409 duplicate name)
 *   - Client-side numeric validation for nilai fields
 *
 * Requirements: 3.1, 3.2, 3.3, 3.4, 3.5, 3.6, 3.7, 3.8
 */
(function () {
    'use strict';

    // ── State ────────────────────────────────────────────────────────────────

    /** @type {Array<{id:number, nama:string, bobot:number, tipe:string}>} */
    var kriteriaList = [];

    /** @type {Array<{id:number, nama:string, nilai:Object}>} */
    var alternatifList = [];

    /** ID of the row being edited (null = add mode) */
    var editingId = null;

    /** ID of the row pending deletion */
    var deletingId = null;

    // ── DOM helpers ──────────────────────────────────────────────────────────

    function el(id) { return document.getElementById(id); }
    function showEl(id) { el(id).classList.remove('hidden'); }
    function hideEl(id) { el(id).classList.add('hidden'); }

    // ── Alert banner ─────────────────────────────────────────────────────────

    var alertTimer = null;

    function showAlert(msg, type) {
        var banner = el('alert-banner');
        var msgEl  = el('alert-msg');
        var iconEl = el('alert-icon');

        msgEl.textContent = msg;
        banner.className = 'flex items-start gap-3 rounded-lg border px-4 py-3 text-sm';

        if (type === 'success') {
            banner.classList.add('bg-green-50', 'border-green-200', 'text-green-700');
            iconEl.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>';
        } else {
            banner.classList.add('bg-red-50', 'border-red-200', 'text-red-700');
            iconEl.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>';
        }

        banner.classList.remove('hidden');
        if (alertTimer) clearTimeout(alertTimer);
        alertTimer = setTimeout(function () {
            banner.classList.add('hidden');
        }, 5000);
    }

    // ── Load kriteria (needed for columns + form fields) ──────────────────────

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
            kriteriaList = (json.success && json.data) ? json.data : [];
            updateKriteriaState();
        });
    }

    /**
     * Update the "Tambah Alternatif" button state based on whether kriteria exist.
     */
    function updateKriteriaState() {
        var btnTambah = el('btn-tambah');
        var noKriteriaMsg = el('no-kriteria-msg');

        if (kriteriaList.length === 0) {
            btnTambah.disabled = true;
            btnTambah.setAttribute('aria-disabled', 'true');
            showEl('no-kriteria-msg');
        } else {
            btnTambah.disabled = false;
            btnTambah.removeAttribute('aria-disabled');
            hideEl('no-kriteria-msg');
        }
    }

    // ── Load alternatif ───────────────────────────────────────────────────────

    function loadAlternatif() {
        return fetch('../api/alternatif.php', {
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
            alternatifList = json.data || [];
            renderTable();
        })
        .catch(function (err) {
            console.error('[Alternatif] load error:', err);
            renderError('Gagal memuat data. Silakan muat ulang halaman.');
        });
    }

    // ── Render table ──────────────────────────────────────────────────────────

    function renderError(msg) {
        var tbody = el('alternatif-tbody');
        var colCount = 3 + kriteriaList.length;
        tbody.innerHTML =
            '<tr><td colspan="' + colCount + '" class="px-4 py-8 text-center text-sm text-red-500">' +
            escapeHtml(msg) + '</td></tr>';
        el('row-count').textContent = '';
    }

    /**
     * Inject dynamic kriteria column headers into the table thead.
     */
    function renderTableHeaders() {
        var theadRow = el('thead-row');
        // Remove any previously injected kriteria columns (between Nama and Aksi)
        var existingDynamic = theadRow.querySelectorAll('[data-kriteria-col]');
        existingDynamic.forEach(function (th) { th.parentNode.removeChild(th); });

        // Find the Aksi th (last child) to insert before it
        var aksiTh = theadRow.lastElementChild;

        kriteriaList.forEach(function (k) {
            var th = document.createElement('th');
            th.scope = 'col';
            th.setAttribute('data-kriteria-col', k.id);
            th.className = 'px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider whitespace-nowrap';
            th.textContent = k.nama;
            theadRow.insertBefore(th, aksiTh);
        });
    }

    /**
     * Re-render the table body from alternatifList.
     */
    function renderTable() {
        renderTableHeaders();

        var tbody = el('alternatif-tbody');
        var colCount = 3 + kriteriaList.length;

        if (alternatifList.length === 0) {
            tbody.innerHTML =
                '<tr><td colspan="' + colCount + '" class="px-4 py-8 text-center text-sm text-gray-400">' +
                'Belum ada alternatif. Klik "Tambah Alternatif" untuk memulai.</td></tr>';
            el('row-count').textContent = '';
            return;
        }

        var rows = '';
        alternatifList.forEach(function (a, idx) {
            var nilaiCells = '';
            kriteriaList.forEach(function (k) {
                var val = (a.nilai && a.nilai[k.id] !== undefined) ? a.nilai[k.id] : '—';
                var displayVal = (val === '—') ? val : Number(val).toLocaleString('id-ID', { maximumFractionDigits: 4 });
                nilaiCells +=
                    '<td class="px-4 py-3 tabular-nums text-gray-700">' +
                    escapeHtml(String(displayVal)) +
                    '</td>';
            });

            rows +=
                '<tr class="hover:bg-gray-50 transition-colors">' +
                  '<td class="px-4 py-3 text-gray-500 tabular-nums">' + (idx + 1) + '</td>' +
                  '<td class="px-4 py-3 font-medium text-gray-900">' + escapeHtml(a.nama) + '</td>' +
                  nilaiCells +
                  '<td class="px-4 py-3 text-right">' +
                    '<div class="inline-flex items-center gap-2">' +
                      '<button type="button" ' +
                              'onclick="openFormModal(' + a.id + ')" ' +
                              'class="rounded-md px-2.5 py-1 text-xs font-medium text-primary-700 ' +
                                     'bg-primary-50 hover:bg-primary-100 border border-primary-200 ' +
                                     'focus:outline-none focus:ring-2 focus:ring-primary-500 transition-colors" ' +
                              'aria-label="Edit ' + escapeAttr(a.nama) + '">' +
                        'Edit' +
                      '</button>' +
                      '<button type="button" ' +
                              'onclick="openDeleteModal(' + a.id + ', \'' + escapeAttr(a.nama) + '\')" ' +
                              'class="rounded-md px-2.5 py-1 text-xs font-medium text-red-700 ' +
                                     'bg-red-50 hover:bg-red-100 border border-red-200 ' +
                                     'focus:outline-none focus:ring-2 focus:ring-red-500 transition-colors" ' +
                              'aria-label="Hapus ' + escapeAttr(a.nama) + '">' +
                        'Hapus' +
                      '</button>' +
                    '</div>' +
                  '</td>' +
                '</tr>';
        });

        tbody.innerHTML = rows;
        el('row-count').textContent = alternatifList.length + ' alternatif';
    }

    // ── Dynamic kriteria form fields ──────────────────────────────────────────

    /**
     * Render input fields for each kriteria inside the modal form.
     * @param {Object} existingNilai  Map of kriteria_id => value (for edit mode)
     */
    function renderKriteriaFields(existingNilai) {
        var container = el('kriteria-fields');
        existingNilai = existingNilai || {};

        if (kriteriaList.length === 0) {
            container.innerHTML = '';
            return;
        }

        var html = '<div class="border-t border-gray-100 pt-4">' +
                   '<p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">Nilai per Kriteria</p>' +
                   '<div class="space-y-3">';

        kriteriaList.forEach(function (k) {
            var fieldId = 'form-nilai-' + k.id;
            var errorId = 'error-nilai-' + k.id;
            var currentVal = (existingNilai[k.id] !== undefined) ? existingNilai[k.id] : '';
            var tipeBadge = k.tipe === 'benefit'
                ? '<span class="ml-1 inline-flex items-center rounded-full bg-green-100 px-1.5 py-0.5 text-xs font-medium text-green-700">Benefit</span>'
                : '<span class="ml-1 inline-flex items-center rounded-full bg-amber-100 px-1.5 py-0.5 text-xs font-medium text-amber-700">Cost</span>';

            html +=
                '<div>' +
                  '<label for="' + fieldId + '" class="block text-sm font-medium text-gray-700 mb-1">' +
                    escapeHtml(k.nama) + tipeBadge +
                    ' <span class="font-normal text-gray-400 text-xs">(bobot: ' + k.bobot.toFixed(2) + ')</span>' +
                  '</label>' +
                  '<input type="number" ' +
                         'id="' + fieldId + '" ' +
                         'name="nilai_' + k.id + '" ' +
                         'step="any" ' +
                         'value="' + escapeAttr(String(currentVal)) + '" ' +
                         'placeholder="Masukkan nilai numerik" ' +
                         'class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm ' +
                                'text-gray-900 placeholder-gray-400 shadow-sm ' +
                                'focus:border-primary-500 focus:outline-none focus:ring-1 ' +
                                'focus:ring-primary-500 transition-colors">' +
                  '<p id="' + errorId + '" class="hidden mt-1 text-xs text-red-600" role="alert"></p>' +
                '</div>';
        });

        html += '</div></div>';
        container.innerHTML = html;
    }

    // ── Add / Edit modal ──────────────────────────────────────────────────────

    window.openFormModal = function (id) {
        // Prevent opening if no kriteria
        if (kriteriaList.length === 0) return;

        resetForm();
        hideEl('form-api-error');

        if (id !== undefined) {
            // Edit mode
            var a = alternatifList.find(function (x) { return x.id === id; });
            if (!a) return;

            editingId = id;
            el('modal-title').textContent = 'Edit Alternatif';
            el('submit-label').textContent = 'Simpan Perubahan';
            el('form-id').value   = a.id;
            el('form-nama').value = a.nama;
            renderKriteriaFields(a.nilai || {});
        } else {
            // Add mode
            editingId = null;
            el('modal-title').textContent = 'Tambah Alternatif';
            el('submit-label').textContent = 'Simpan';
            el('form-id').value = '';
            renderKriteriaFields({});
        }

        el('form-modal').classList.remove('hidden');
        el('form-nama').focus();
    };

    window.closeFormModal = function () {
        el('form-modal').classList.add('hidden');
        resetForm();
    };

    function resetForm() {
        el('alternatif-form').reset();
        el('form-id').value = '';
        editingId = null;

        // Clear nama field error
        clearFieldError('nama');

        // Clear kriteria field errors
        kriteriaList.forEach(function (k) {
            var errorId = 'error-nilai-' + k.id;
            var errEl = document.getElementById(errorId);
            if (errEl) {
                errEl.textContent = '';
                errEl.classList.add('hidden');
            }
            var inputEl = document.getElementById('form-nilai-' + k.id);
            if (inputEl) {
                inputEl.classList.remove('border-red-500', 'focus:ring-red-500', 'focus:border-red-500');
                inputEl.classList.add('border-gray-300', 'focus:ring-primary-500', 'focus:border-primary-500');
            }
        });

        hideEl('form-api-error');
        el('form-api-error').textContent = '';
        setSubmitLoading(false);
    }

    // ── Client-side validation ────────────────────────────────────────────────

    function validateForm() {
        var valid = true;

        // Validate nama
        var nama = el('form-nama').value.trim();
        if (nama === '') {
            setFieldError('nama', 'Nama alternatif tidak boleh kosong');
            valid = false;
        } else if (nama.length > 100) {
            setFieldError('nama', 'Nama alternatif maksimal 100 karakter');
            valid = false;
        } else {
            clearFieldError('nama');
        }

        // Validate each kriteria nilai field
        kriteriaList.forEach(function (k) {
            var inputEl = document.getElementById('form-nilai-' + k.id);
            if (!inputEl) return;
            var rawVal = inputEl.value.trim();
            if (rawVal === '') {
                // Empty is allowed — will be omitted from payload
                clearNilaiFieldError(k.id);
                return;
            }
            var numVal = parseFloat(rawVal);
            if (isNaN(numVal)) {
                setNilaiFieldError(k.id, 'Nilai harus berupa angka');
                valid = false;
            } else {
                clearNilaiFieldError(k.id);
            }
        });

        return valid;
    }

    function setFieldError(field, msg) {
        var errEl = el('error-' + field);
        if (!errEl) return;
        errEl.textContent = msg;
        errEl.classList.remove('hidden');
        var inputEl = el('form-' + field);
        if (inputEl) {
            inputEl.classList.add('border-red-500', 'focus:ring-red-500', 'focus:border-red-500');
            inputEl.classList.remove('border-gray-300', 'focus:ring-primary-500', 'focus:border-primary-500');
        }
    }

    function clearFieldError(field) {
        var errEl = el('error-' + field);
        if (!errEl) return;
        errEl.textContent = '';
        errEl.classList.add('hidden');
        var inputEl = el('form-' + field);
        if (inputEl) {
            inputEl.classList.remove('border-red-500', 'focus:ring-red-500', 'focus:border-red-500');
            inputEl.classList.add('border-gray-300', 'focus:ring-primary-500', 'focus:border-primary-500');
        }
    }

    function setNilaiFieldError(kriteriaId, msg) {
        var errEl = document.getElementById('error-nilai-' + kriteriaId);
        if (!errEl) return;
        errEl.textContent = msg;
        errEl.classList.remove('hidden');
        var inputEl = document.getElementById('form-nilai-' + kriteriaId);
        if (inputEl) {
            inputEl.classList.add('border-red-500', 'focus:ring-red-500', 'focus:border-red-500');
            inputEl.classList.remove('border-gray-300', 'focus:ring-primary-500', 'focus:border-primary-500');
        }
    }

    function clearNilaiFieldError(kriteriaId) {
        var errEl = document.getElementById('error-nilai-' + kriteriaId);
        if (!errEl) return;
        errEl.textContent = '';
        errEl.classList.add('hidden');
        var inputEl = document.getElementById('form-nilai-' + kriteriaId);
        if (inputEl) {
            inputEl.classList.remove('border-red-500', 'focus:ring-red-500', 'focus:border-red-500');
            inputEl.classList.add('border-gray-300', 'focus:ring-primary-500', 'focus:border-primary-500');
        }
    }

    // ── Form submit ───────────────────────────────────────────────────────────

    window.handleFormSubmit = function (event) {
        event.preventDefault();
        hideEl('form-api-error');

        if (!validateForm()) return;

        var isEdit = editingId !== null;

        // Build nilai object from dynamic fields
        var nilai = {};
        kriteriaList.forEach(function (k) {
            var inputEl = document.getElementById('form-nilai-' + k.id);
            if (!inputEl) return;
            var rawVal = inputEl.value.trim();
            if (rawVal !== '') {
                nilai[k.id] = parseFloat(rawVal);
            }
        });

        var payload = {
            csrf_token: typeof CSRF_TOKEN !== 'undefined' ? CSRF_TOKEN : '',
            nama: el('form-nama').value.trim(),
            nilai: nilai,
        };
        if (isEdit) {
            payload.id = editingId;
        }

        setSubmitLoading(true);

        fetch('../api/alternatif.php', {
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
                var apiErr = el('form-api-error');
                apiErr.textContent = json.message || 'Terjadi kesalahan.';
                showEl('form-api-error');

                // Highlight nama field for duplicate-name errors (HTTP 409)
                if (status === 409) {
                    setFieldError('nama', json.message || 'Nama alternatif sudah digunakan');
                }
                return;
            }

            // Success
            closeFormModal();
            loadAlternatif();
            showAlert(
                isEdit ? 'Alternatif berhasil diperbarui.' : 'Alternatif berhasil ditambahkan.',
                'success'
            );
        })
        .catch(function (err) {
            console.error('[Alternatif] submit error:', err);
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
            label.textContent = editingId !== null ? 'Simpan Perubahan' : 'Simpan';
        }
    }

    // ── Delete modal ──────────────────────────────────────────────────────────

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

        fetch('../api/alternatif.php', {
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
                showAlert(json.message || 'Gagal menghapus alternatif.', 'error');
                return;
            }

            closeDeleteModal();
            loadAlternatif();
            showAlert('Alternatif berhasil dihapus.', 'success');
        })
        .catch(function (err) {
            console.error('[Alternatif] delete error:', err);
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

    // Load kriteria first (needed for table headers and form fields), then load alternatif
    loadKriteria().then(function () {
        loadAlternatif();
    }).catch(function (err) {
        console.error('[Alternatif] init error:', err);
        loadAlternatif();
    });

}());
</script>

<?php require_once '../src/layout/footer.php'; ?>
