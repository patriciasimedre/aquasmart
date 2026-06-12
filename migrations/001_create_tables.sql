-- ============================================================
--  AquaSmart — Migrare 001: creare tabele (concept irigare)
--  Cele 5 tabele din README1.md:
--    sensor_readings, irrigation_events, commands, settings, ai_reports
--
--  Rulare (conform README1.md):
--    mysql -h aquasmart-db.mysql.database.azure.com -u aquaadmin -p \
--          < migrations/001_create_tables.sql
-- ============================================================

CREATE DATABASE IF NOT EXISTS aquasmart
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE aquasmart;

SET NAMES utf8mb4;

-- ------------------------------------------------------------
-- sensor_readings — citiri senzori (insert la 30-60s de la ESP32)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS sensor_readings (
    id             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    timestamp      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    temp_apa       DECIMAL(5,2)    NULL,             -- °C, DS18B20
    temp_aer       DECIMAL(5,2)    NULL,             -- °C, DHT22
    umiditate_aer  DECIMAL(5,2)    NULL,             -- %, DHT22 (0-100)
    tds            SMALLINT UNSIGNED NULL,           -- ppm, TDS Meter V1.0
    ploaie         TINYINT(1)      NOT NULL DEFAULT 0, -- senzor MH-RD
    nivel_sus      TINYINT(1)      NOT NULL DEFAULT 0, -- float switch SUS (galeata plina)
    nivel_jos      TINYINT(1)      NOT NULL DEFAULT 0, -- float switch JOS (galeata goala)
    INDEX idx_sr_timestamp (timestamp)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- irrigation_events — fiecare udare efectuata
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS irrigation_events (
    id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    timestamp_start  DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    durata_secunde   SMALLINT UNSIGNED NOT NULL,
    motiv            ENUM('manual','fuzzy','programat') NOT NULL,
    nivel_inainte    VARCHAR(16)       NULL,   -- gol / partial / plin (derivat din float switches)
    temp_aer_inainte DECIMAL(5,2)      NULL,
    INDEX idx_ie_start (timestamp_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- commands — coada de comenzi emise de dashboard pentru ESP32
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS commands (
    id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    timestamp  DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    tip        ENUM('udare','oprire') NOT NULL,
    durata     SMALLINT UNSIGNED NULL,                          -- secunde (NULL pt 'oprire')
    status     ENUM('pending','executing','done') NOT NULL DEFAULT 'pending',
    ack_at     DATETIME          NULL,                          -- cand a confirmat ESP32
    INDEX idx_cmd_status (status),
    INDEX idx_cmd_timestamp (timestamp)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- settings — configurari sistem (un singur rand, editat din dashboard)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS settings (
    id                   TINYINT UNSIGNED NOT NULL PRIMARY KEY,
    prag_umiditate_aer   DECIMAL(5,2)      NOT NULL DEFAULT 60.00, -- % peste care nu se uda
    interval_minim_udare SMALLINT UNSIGNED NOT NULL DEFAULT 360,   -- minute intre udari
    mod_automat          TINYINT(1)        NOT NULL DEFAULT 1,     -- fuzzy ON/OFF
    updated_at           DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP
                                           ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Randul implicit de configurare (idempotent).
INSERT INTO settings (id, prag_umiditate_aer, interval_minim_udare, mod_automat)
VALUES (1, 60.00, 360, 1)
ON DUPLICATE KEY UPDATE id = id;

-- ------------------------------------------------------------
-- ai_reports — cache pentru rapoartele AI generate (Claude)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS ai_reports (
    id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    timestamp     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP, -- cand a fost generat
    perioada_start DATETIME    NOT NULL,
    perioada_end   DATETIME    NOT NULL,
    continut      MEDIUMTEXT   NOT NULL,
    model         VARCHAR(64)  NOT NULL,
    INDEX idx_ai_period (perioada_start, perioada_end)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
