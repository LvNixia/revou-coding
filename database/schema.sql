-- =============================================================================
-- SPK Website — Database Schema
-- MySQL 8.x
--
-- Idempotent: safe to run multiple times.
-- Uses CREATE TABLE IF NOT EXISTS and INSERT ... ON DUPLICATE KEY UPDATE.
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- -----------------------------------------------------------------------------
-- Table: users
-- Stores admin/user accounts. Passwords are stored as bcrypt hashes.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username   VARCHAR(50)  NOT NULL UNIQUE,
    password   VARCHAR(255) NOT NULL,          -- bcrypt hash
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Table: kriteria
-- Evaluation criteria with weight and type (benefit / cost).
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS kriteria (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nama       VARCHAR(100) NOT NULL UNIQUE,
    bobot      DECIMAL(5,4) NOT NULL,          -- 0.0001 – 1.0000
    tipe       ENUM('benefit', 'cost') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT chk_bobot CHECK (bobot >= 0.0001 AND bobot <= 1.0000)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Table: alternatif
-- Decision alternatives (candidates to be evaluated).
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS alternatif (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nama       VARCHAR(100) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Table: nilai_alternatif
-- Numeric value of each alternative for each criterion.
-- Cascade-deletes when the parent alternative or criterion is removed.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS nilai_alternatif (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    alternatif_id INT UNSIGNED NOT NULL,
    kriteria_id   INT UNSIGNED NOT NULL,
    nilai         DECIMAL(15,6) NOT NULL,
    UNIQUE KEY uq_alt_krit (alternatif_id, kriteria_id),
    FOREIGN KEY (alternatif_id) REFERENCES alternatif(id) ON DELETE CASCADE,
    FOREIGN KEY (kriteria_id)   REFERENCES kriteria(id)   ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Table: riwayat_perhitungan
-- Header record for each SAW calculation session.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS riwayat_perhitungan (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    dihitung_pada     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    jumlah_kriteria   INT UNSIGNED NOT NULL,
    jumlah_alternatif INT UNSIGNED NOT NULL,
    catatan           TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Table: hasil_perhitungan
-- Per-alternative preference value and ranking for a calculation session.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS hasil_perhitungan (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    riwayat_id       INT UNSIGNED NOT NULL,
    alternatif_id    INT UNSIGNED NOT NULL,
    nilai_preferensi DECIMAL(15,10) NOT NULL,
    ranking          INT UNSIGNED NOT NULL,
    FOREIGN KEY (riwayat_id)    REFERENCES riwayat_perhitungan(id) ON DELETE CASCADE,
    FOREIGN KEY (alternatif_id) REFERENCES alternatif(id)          ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Table: nilai_normalisasi
-- Normalised value per (alternative, criterion) for a calculation session.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS nilai_normalisasi (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    riwayat_id    INT UNSIGNED NOT NULL,
    alternatif_id INT UNSIGNED NOT NULL,
    kriteria_id   INT UNSIGNED NOT NULL,
    nilai_normal  DECIMAL(15,10) NOT NULL,
    FOREIGN KEY (riwayat_id)    REFERENCES riwayat_perhitungan(id) ON DELETE CASCADE,
    FOREIGN KEY (alternatif_id) REFERENCES alternatif(id)          ON DELETE CASCADE,
    FOREIGN KEY (kriteria_id)   REFERENCES kriteria(id)            ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Table: pengaturan
-- Key-value store for system settings (name, organisation, logo path, etc.).
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS pengaturan (
    kunci      VARCHAR(50) PRIMARY KEY,
    nilai      TEXT NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default system settings — safe to re-run (ON DUPLICATE KEY UPDATE is a no-op
-- when the stored value already exists, preserving any admin customisations).
INSERT INTO pengaturan (kunci, nilai)
VALUES
    ('nama_sistem',      'SPK - Sistem Pendukung Keputusan'),
    ('nama_organisasi',  'Organisasi'),
    ('logo_path',        '')
ON DUPLICATE KEY UPDATE
    kunci = kunci;   -- intentional no-op: preserve existing values

SET FOREIGN_KEY_CHECKS = 1;
