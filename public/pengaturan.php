<?php

/**
 * Halaman Pengaturan Sistem
 *
 * Menyediakan antarmuka untuk:
 *   - Mengubah nama sistem dan nama organisasi
 *   - Mengunggah logo (PNG, JPG, SVG, maks 2MB)
 *   - Menampilkan pratinjau logo saat ini
 *   - Menampilkan pesan error untuk format/ukuran file tidak valid
 *
 * Requirements: 8.1, 8.2, 8.4, 8.5
 */

declare(strict_types=1);

require_once '../src/middleware/auth_guard.php';
require_once '../src/helpers/csrf.php';

requireAuth();
$csrfToken = generateCsrfToken();
$pageTitle = 'Pengaturan';

require_once '../src/layout/main.php';
?>

<!-- ── Pengaturan Page Content ────────────────────────────────────────────── -->

<div class="space-y-6 max-w-2xl">

    <!-- Page heading -->
    <div>
        <h2 class="text-xl font-semibold text-gray-800">Pengaturan Sistem</h2>
        <p class="mt-1 text-sm text-gray-500">Kelola nama sistem, nama organisasi, dan logo yang ditampilkan di seluruh halaman.</p>
    </div>

    <!-- Alert banner (success / error) -->
    <div id="alert-banner"
         role="alert"
         aria-live="polite"
         class="hidden flex items-start gap-3 rounded-lg border px-4 py-3 text-sm">
        <svg id="alert-icon" class="mt-0.5 h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24"
             stroke="currentColor" stroke-width="2" aria-hidden="true">
        </svg>
        <span id="alert-msg"></span>
    </div>

    <!-- Settings form card -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
        <form id="pengaturan-form" novalidate enctype="multipart/form-data">

            <div class="px-6 py-6 space-y-6">

                <!-- Nama Sistem -->
                <div>
                    <label for="nama-sistem"
                           class="block text-sm font-medium text-gray-700 mb-1">
                        Nama Sistem <span class="text-red-500" aria-hidden="true">*</span>
                    </label>
                    <input type="text"
                           id="nama-sistem"
                           name="nama_sistem"
                           maxlength="100"
                           required
                           autocomplete="off"
                           placeholder="Contoh: SPK Pemilihan Vendor"
                           class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm
                                  text-gray-900 placeholder-gray-400 shadow-sm
                                  focus:border-primary-500 focus:outline-none focus:ring-1
                                  focus:ring-primary-500 transition-colors">
                    <p id="error-nama-sistem" class="hidden mt-1 text-xs text-red-600" role="alert"></p>
                </div>

                <!-- Nama Organisasi -->
                <div>
                    <label for="nama-organisasi"
                           class="block text-sm font-medium text-gray-700 mb-1">
                        Nama Organisasi
                        <span class="font-normal text-gray-400">(opsional)</span>
                    </label>
                    <input type="text"
                           id="nama-organisasi"
                           name="nama_organisasi"
                           maxlength="100"
                           autocomplete="off"
                           placeholder="Contoh: PT. Contoh Jaya"
                           class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm
                                  text-gray-900 placeholder-gray-400 shadow-sm
                                  focus:border-primary-500 focus:outline-none focus:ring-1
                                  focus:ring-primary-500 transition-colors">
                </div>

                <!-- Logo Upload -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Logo
                        <span class="font-normal text-gray-400">(PNG, JPG, atau SVG — maks 2MB)</span>
                    </label>

                    <!-- Current logo preview -->
                    <div id="logo-preview-wrap" class="hidden mb-3">
                        <p class="text-xs text-gray-500 mb-1">Logo saat ini:</p>
                        <img id="logo-preview"
                             src=""
                             alt="Logo saat ini"
                             class="h-16 w-auto object-contain rounded border border-gray-200 bg-gray-50 p-1">
                    </div>

                    <!-- File input -->
                    <div class="flex items-center gap-3">
                        <label for="logo-input"
                               class="cursor-pointer inline-flex items-center gap-2 rounded-lg border
                                      border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700
                                      shadow-sm hover:bg-gray-50 focus-within:ring-2 focus-within:ring-primary-500
                                      transition-colors">
                            <svg class="h-4 w-4 text-gray-500" fill="none" viewBox="0 0 24 24"
                                 stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                      d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"/>
                            </svg>
                            Pilih File
                            <input type="file"
                                   id="logo-input"
                                   name="logo"
                                   accept=".png,.jpg,.jpeg,.svg,image/png,image/jpeg,image/svg+xml"
                                   class="sr-only"
                                   onchange="handleFileChange(this)">
                        </label>
                        <span id="file-name" class="text-sm text-gray-500">Tidak ada file dipilih</span>
                    </div>

                    <!-- File error message -->
                    <p id="error-logo" class="hidden mt-1 text-xs text-red-600" role="alert"></p>
                </div>

            </div><!-- /fields -->

            <!-- Footer buttons -->
            <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-200 bg-gray-50 rounded-b-xl">
                <button type="button"
                        id="btn-reset"
                        onclick="resetForm()"
                        class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm
                               font-medium text-gray-700 shadow-sm hover:bg-gray-50
                               focus:outline-none focus:ring-2 focus:ring-primary-500
                               transition-colors">
                    Reset
                </button>
                <button type="submit"
                        id="btn-simpan"
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
                    <span id="submit-label">Simpan Pengaturan</span>
                </button>
            </div>

        </form>
    </div>

    <!-- ── Danger Zone ──────────────────────────────────────────────────────── -->
    <div class="bg-white rounded-xl shadow-sm border border-red-200 mt-8">
        <div class="px-6 py-5 border-b border-red-100">
            <h3 class="text-base font-semibold text-red-700">Zona Berbahaya</h3>
            <p class="mt-1 text-sm text-gray-500">Tindakan di bawah ini bersifat permanen dan tidak dapat dibatalkan.</p>
        </div>
        <div class="px-6 py-5 flex items-center justify-between gap-4">
            <div>
                <p class="text-sm font-medium text-gray-800">Reset Data</p>
                <p class="mt-0.5 text-sm text-gray-500">
                    Hapus seluruh data alternatif, nilai alternatif, dan semua hasil perhitungan dari database.
                    Data kriteria, pengguna, dan pengaturan sistem tidak akan terpengaruh.
                </p>
            </div>
            <button type="button"
                    id="btn-open-reset-modal"
                    onclick="openResetModal()"
                    class="shrink-0 inline-flex items-center gap-2 rounded-lg border border-red-300
                           bg-white px-4 py-2 text-sm font-medium text-red-600 shadow-sm
                           hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-500
                           transition-colors">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                     stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0
                             2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898
                             0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>
                </svg>
                Reset Data
            </button>
        </div>
    </div>

</div><!-- /max-w-2xl -->

<!-- ── Reset Confirmation Modal ──────────────────────────────────────────────── -->
<div id="reset-modal"
     role="dialog"
     aria-modal="true"
     aria-labelledby="reset-modal-title"
     class="hidden fixed inset-0 z-50 flex items-center justify-center p-4">

    <!-- Backdrop -->
    <div id="reset-modal-backdrop"
         class="absolute inset-0 bg-black/50"
         onclick="closeResetModal()"></div>

    <!-- Dialog panel -->
    <div class="relative z-10 w-full max-w-md bg-white rounded-xl shadow-xl">

        <!-- Header -->
        <div class="flex items-start gap-4 px-6 pt-6 pb-4">
            <div class="flex-shrink-0 flex items-center justify-center h-10 w-10 rounded-full bg-red-100">
                <svg class="h-5 w-5 text-red-600" fill="none" viewBox="0 0 24 24"
                     stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0
                             2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898
                             0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>
                </svg>
            </div>
            <div class="flex-1 min-w-0">
                <h3 id="reset-modal-title" class="text-base font-semibold text-gray-900">
                    Konfirmasi Reset Data
                </h3>
                <p class="mt-1 text-sm text-gray-500">
                    Tindakan ini akan menghapus secara permanen:
                </p>
                <ul class="mt-2 text-sm text-gray-600 space-y-1 list-disc list-inside">
                    <li>Semua data alternatif</li>
                    <li>Semua nilai alternatif</li>
                    <li>Semua hasil perhitungan &amp; riwayat</li>
                    <li>Semua nilai normalisasi</li>
                </ul>
                <p class="mt-3 text-sm font-medium text-gray-700">
                    Data kriteria, pengguna, dan pengaturan sistem <span class="text-green-700">tidak</span> akan dihapus.
                </p>
            </div>
        </div>

        <!-- Step 2: type confirmation -->
        <div class="px-6 pb-2">
            <label for="reset-confirm-input"
                   class="block text-sm font-medium text-gray-700 mb-1">
                Ketik <span class="font-mono font-bold text-red-600">RESET</span> untuk mengonfirmasi
            </label>
            <input type="text"
                   id="reset-confirm-input"
                   name="reset_confirmation"
                   autocomplete="off"
                   spellcheck="false"
                   placeholder="RESET"
                   oninput="onResetInputChange(this)"
                   class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm
                          text-gray-900 placeholder-gray-400 shadow-sm
                          focus:border-red-500 focus:outline-none focus:ring-1
                          focus:ring-red-500 transition-colors font-mono">
            <p id="reset-modal-error"
               class="hidden mt-1 text-xs text-red-600"
               role="alert"></p>
        </div>

        <!-- Footer buttons -->
        <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-gray-100 mt-2">
            <button type="button"
                    onclick="closeResetModal()"
                    class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm
                           font-medium text-gray-700 shadow-sm hover:bg-gray-50
                           focus:outline-none focus:ring-2 focus:ring-gray-400
                           transition-colors">
                Batal
            </button>
            <button type="button"
                    id="btn-confirm-reset"
                    disabled
                    onclick="executeReset()"
                    class="inline-flex items-center gap-2 rounded-lg bg-red-600 px-4 py-2
                           text-sm font-medium text-white shadow-sm hover:bg-red-700
                           focus:outline-none focus:ring-2 focus:ring-red-500
                           disabled:opacity-40 disabled:cursor-not-allowed transition-colors">
                <svg id="reset-spinner"
                     class="hidden animate-spin h-4 w-4 text-white"
                     fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10"
                            stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                </svg>
                <span id="reset-btn-label">Konfirmasi Reset</span>
            </button>
        </div>

    </div>
</div>

<script>
/**
 * Pengaturan page — client-side logic
 *
 * Responsibilities:
 *   - Load current settings from API on page load
 *   - Client-side file validation (format and size)
 *   - Submit form via Fetch API with FormData (multipart)
 *   - Show success / error messages
 *   - Update sidebar header after save (without page reload)
 *
 * Requirements: 8.1, 8.2, 8.4, 8.5
 */
(function () {
    'use strict';

    // ── Constants ────────────────────────────────────────────────────────────

    var MAX_FILE_SIZE   = 2 * 1024 * 1024; // 2 MB
    var ALLOWED_TYPES   = ['image/png', 'image/jpeg', 'image/svg+xml'];
    var ALLOWED_EXTS    = ['.png', '.jpg', '.jpeg', '.svg'];

    // ── DOM helpers ──────────────────────────────────────────────────────────

    function el(id) { return document.getElementById(id); }
    function showEl(id) { el(id).classList.remove('hidden'); }
    function hideEl(id) { el(id).classList.add('hidden'); }

    // ── Alert banner ─────────────────────────────────────────────────────────

    var alertTimer = null;

    /**
     * Show the page-level alert banner.
     * @param {string} msg
     * @param {'success'|'error'} type
     */
    function showAlert(msg, type) {
        var banner = el('alert-banner');
        var msgEl  = el('alert-msg');
        var iconEl = el('alert-icon');

        msgEl.textContent = msg;
        banner.className  = 'flex items-start gap-3 rounded-lg border px-4 py-3 text-sm';

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
        }, 6000);
    }

    // ── Load settings ────────────────────────────────────────────────────────

    function loadSettings() {
        fetch('../api/pengaturan.php', {
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
                showAlert(json.message || 'Gagal memuat pengaturan.', 'error');
                return;
            }
            populateForm(json.data || {});
        })
        .catch(function (err) {
            console.error('[Pengaturan] load error:', err);
            showAlert('Gagal memuat pengaturan. Silakan muat ulang halaman.', 'error');
        });
    }

    /**
     * Populate form fields with data from the API.
     * @param {Object} data  Key-value settings object
     */
    function populateForm(data) {
        el('nama-sistem').value      = data.nama_sistem      || '';
        el('nama-organisasi').value  = data.nama_organisasi  || '';

        // Show current logo preview if available
        if (data.logo_path) {
            el('logo-preview').src = '../' + data.logo_path;
            showEl('logo-preview-wrap');
        } else {
            hideEl('logo-preview-wrap');
        }
    }

    // ── File input handler ───────────────────────────────────────────────────

    /**
     * Validate the selected file and update the UI.
     * @param {HTMLInputElement} input
     */
    window.handleFileChange = function (input) {
        var file    = input.files && input.files[0];
        var errEl   = el('error-logo');
        var nameEl  = el('file-name');

        // Clear previous error
        errEl.textContent = '';
        hideEl('error-logo');

        if (!file) {
            nameEl.textContent = 'Tidak ada file dipilih';
            return;
        }

        // Validate file extension as a quick pre-check
        var fileName  = file.name.toLowerCase();
        var validExt  = ALLOWED_EXTS.some(function (ext) { return fileName.endsWith(ext); });

        // Validate MIME type (client-side; server will re-validate)
        var validMime = ALLOWED_TYPES.indexOf(file.type) !== -1;

        if (!validExt && !validMime) {
            errEl.textContent = 'Format file tidak didukung. Gunakan PNG, JPG, atau SVG';
            showEl('error-logo');
            input.value = '';
            nameEl.textContent = 'Tidak ada file dipilih';
            return;
        }

        // Validate file size
        if (file.size > MAX_FILE_SIZE) {
            errEl.textContent = 'Ukuran file melebihi batas 2MB';
            showEl('error-logo');
            input.value = '';
            nameEl.textContent = 'Tidak ada file dipilih';
            return;
        }

        nameEl.textContent = file.name;
    };

    // ── Form validation ──────────────────────────────────────────────────────

    /**
     * Validate required fields before submit.
     * @returns {boolean}
     */
    function validateForm() {
        var valid = true;

        var namaSistem = el('nama-sistem').value.trim();
        if (namaSistem === '') {
            el('error-nama-sistem').textContent = 'Nama sistem tidak boleh kosong';
            showEl('error-nama-sistem');
            el('nama-sistem').classList.add('border-red-500');
            valid = false;
        } else {
            hideEl('error-nama-sistem');
            el('nama-sistem').classList.remove('border-red-500');
        }

        // Also check if there's a pending file error
        if (!el('error-logo').classList.contains('hidden')) {
            valid = false;
        }

        return valid;
    }

    // ── Submit ───────────────────────────────────────────────────────────────

    function setLoading(loading) {
        var btn     = el('btn-simpan');
        var spinner = el('submit-spinner');
        var label   = el('submit-label');

        btn.disabled = loading;
        if (loading) {
            spinner.classList.remove('hidden');
            label.textContent = 'Menyimpan...';
        } else {
            spinner.classList.add('hidden');
            label.textContent = 'Simpan Pengaturan';
        }
    }

    el('pengaturan-form').addEventListener('submit', function (e) {
        e.preventDefault();

        if (!validateForm()) return;

        var formData = new FormData();
        formData.append('nama_sistem',      el('nama-sistem').value.trim());
        formData.append('nama_organisasi',  el('nama-organisasi').value.trim());
        formData.append('csrf_token',       typeof CSRF_TOKEN !== 'undefined' ? CSRF_TOKEN : '');

        var logoInput = el('logo-input');
        if (logoInput.files && logoInput.files[0]) {
            formData.append('logo', logoInput.files[0]);
        }

        setLoading(true);

        fetch('../api/pengaturan.php', {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                // Do NOT set Content-Type — browser sets it with boundary for multipart
            },
            credentials: 'same-origin',
            body: formData,
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

            var json   = result.json;
            var status = result.status;

            if (json && json.success) {
                showAlert('Pengaturan berhasil disimpan.', 'success');

                // Update the form with the latest saved data
                populateForm(json.data || {});

                // Reset file input
                logoInput.value = '';
                el('file-name').textContent = 'Tidak ada file dipilih';

                // Update sidebar header without page reload
                updateSidebarHeader(json.data || {});
            } else {
                var msg = (json && json.message) || 'Gagal menyimpan pengaturan.';

                // Show specific error messages for file issues
                if (status === 413 || (msg && msg.indexOf('2MB') !== -1)) {
                    el('error-logo').textContent = 'Ukuran file melebihi batas 2MB';
                    showEl('error-logo');
                } else if (status === 400 && msg && msg.indexOf('Format') !== -1) {
                    el('error-logo').textContent = 'Format file tidak didukung. Gunakan PNG, JPG, atau SVG';
                    showEl('error-logo');
                } else {
                    showAlert(msg, 'error');
                }
            }
        })
        .catch(function (err) {
            console.error('[Pengaturan] save error:', err);
            showAlert('Terjadi kesalahan. Silakan coba lagi.', 'error');
        })
        .finally(function () {
            setLoading(false);
        });
    });

    // ── Update sidebar header ─────────────────────────────────────────────────

    /**
     * Update the sidebar logo/name area after a successful save so changes
     * are reflected immediately without a full page reload.
     *
     * @param {Object} data  Updated settings from the API response
     */
    function updateSidebarHeader(data) {
        // Update system name text
        var sistemNameEls = document.querySelectorAll('.sidebar-sistem-name');
        sistemNameEls.forEach(function (el) {
            el.textContent = data.nama_sistem || 'SPK';
        });

        // Update org name text
        var orgNameEls = document.querySelectorAll('.sidebar-org-name');
        orgNameEls.forEach(function (el) {
            el.textContent = data.nama_organisasi || '';
        });

        // Update page title
        document.title = 'Pengaturan — ' + (data.nama_sistem || 'SPK');

        // Update logo in sidebar if present
        var logoImg = document.querySelector('aside img[alt="Logo"]');
        var logoPlaceholder = document.querySelector('aside .sidebar-logo-placeholder');

        if (data.logo_path) {
            var newSrc = '../' + data.logo_path;
            if (logoImg) {
                logoImg.src = newSrc;
            } else if (logoPlaceholder) {
                // Replace placeholder div with an img element
                var img = document.createElement('img');
                img.src = newSrc;
                img.alt = 'Logo';
                img.className = 'h-8 w-8 object-contain rounded';
                logoPlaceholder.parentNode.replaceChild(img, logoPlaceholder);
            }
        }
    }

    // ── Reset form ───────────────────────────────────────────────────────────

    /**
     * Reset the form to the last saved state by reloading from the API.
     */
    window.resetForm = function () {
        // Clear file input
        el('logo-input').value = '';
        el('file-name').textContent = 'Tidak ada file dipilih';

        // Clear errors
        hideEl('error-nama-sistem');
        hideEl('error-logo');
        el('nama-sistem').classList.remove('border-red-500');

        // Reload from API
        loadSettings();
    };

    // ── Init ─────────────────────────────────────────────────────────────────

    loadSettings();

}());

// ── Reset Data Modal ──────────────────────────────────────────────────────────
// Runs outside the IIFE so onclick attributes can reach these functions.

/**
 * Open the reset confirmation modal and reset its state.
 */
function openResetModal() {
    var modal = document.getElementById('reset-modal');
    var input = document.getElementById('reset-confirm-input');
    var btn   = document.getElementById('btn-confirm-reset');
    var err   = document.getElementById('reset-modal-error');

    // Reset state
    input.value   = '';
    btn.disabled  = true;
    err.textContent = '';
    err.classList.add('hidden');

    modal.classList.remove('hidden');
    // Focus the input after the modal is visible
    setTimeout(function () { input.focus(); }, 50);

    // Trap Escape key
    document.addEventListener('keydown', onResetModalKeydown);
}

/**
 * Close the reset confirmation modal.
 */
function closeResetModal() {
    document.getElementById('reset-modal').classList.add('hidden');
    document.removeEventListener('keydown', onResetModalKeydown);
}

/**
 * Close modal on Escape key press.
 * @param {KeyboardEvent} e
 */
function onResetModalKeydown(e) {
    if (e.key === 'Escape') {
        closeResetModal();
    }
}

/**
 * Enable/disable the confirm button based on whether the user typed "RESET".
 * @param {HTMLInputElement} input
 */
function onResetInputChange(input) {
    var btn = document.getElementById('btn-confirm-reset');
    btn.disabled = (input.value.trim() !== 'RESET');
}

/**
 * Execute the data reset by calling the API endpoint.
 */
function executeReset() {
    var btn     = document.getElementById('btn-confirm-reset');
    var spinner = document.getElementById('reset-spinner');
    var label   = document.getElementById('reset-btn-label');
    var err     = document.getElementById('reset-modal-error');

    // Guard: confirmation text must match
    var input = document.getElementById('reset-confirm-input');
    if (input.value.trim() !== 'RESET') {
        err.textContent = 'Ketik "RESET" untuk melanjutkan.';
        err.classList.remove('hidden');
        return;
    }

    // Show loading state
    btn.disabled = true;
    spinner.classList.remove('hidden');
    label.textContent = 'Mereset...';
    err.classList.add('hidden');

    var formData = new FormData();
    formData.append('csrf_token',   typeof CSRF_TOKEN !== 'undefined' ? CSRF_TOKEN : '');
    formData.append('confirmation', 'RESET');

    fetch('../api/reset.php', {
        method: 'POST',
        headers: { 'Accept': 'application/json' },
        credentials: 'same-origin',
        body: formData,
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

        var json = result.json;

        if (json && json.success) {
            closeResetModal();
            // Show success alert on the page
            var banner = document.getElementById('alert-banner');
            var msgEl  = document.getElementById('alert-msg');
            var iconEl = document.getElementById('alert-icon');

            msgEl.textContent = json.message || 'Data berhasil direset.';
            banner.className  = 'flex items-start gap-3 rounded-lg border px-4 py-3 text-sm ' +
                                'bg-green-50 border-green-200 text-green-700';
            iconEl.innerHTML  = '<path stroke-linecap="round" stroke-linejoin="round" ' +
                                'd="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>';
            banner.classList.remove('hidden');

            setTimeout(function () { banner.classList.add('hidden'); }, 6000);
        } else {
            var msg = (json && json.message) || 'Gagal mereset data. Silakan coba lagi.';
            err.textContent = msg;
            err.classList.remove('hidden');

            // Restore button
            btn.disabled = false;
            spinner.classList.add('hidden');
            label.textContent = 'Konfirmasi Reset';
        }
    })
    .catch(function (fetchErr) {
        console.error('[Reset] fetch error:', fetchErr);
        err.textContent = 'Terjadi kesalahan jaringan. Silakan coba lagi.';
        err.classList.remove('hidden');

        btn.disabled = false;
        spinner.classList.add('hidden');
        label.textContent = 'Konfirmasi Reset';
    });
}
</script>

<?php require_once '../src/layout/footer.php'; ?>
