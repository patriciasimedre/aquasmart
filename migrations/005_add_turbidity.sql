-- ============================================================
--  AquaSmart — Migrare 005: senzor turbiditate TS-300B
--  Coloana noua in sensor_readings + prag configurabil in settings.
--  Idempotent: protejat cu information_schema check (re-rulare sigura).
-- ============================================================

USE aquasmart;

-- --- sensor_readings.turbiditate ---
SET @x = (SELECT COUNT(*) FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME   = 'sensor_readings'
            AND COLUMN_NAME  = 'turbiditate');
SET @s = IF(@x = 0,
            "ALTER TABLE sensor_readings ADD COLUMN turbiditate SMALLINT UNSIGNED NULL COMMENT 'NTU, senzor TS-300B claritate apa' AFTER tds",
            "SET @info = 'turbiditate exista deja'");
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- --- settings.prag_turbiditate_maxim ---
SET @x = (SELECT COUNT(*) FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME   = 'settings'
            AND COLUMN_NAME  = 'prag_turbiditate_maxim');
SET @s = IF(@x = 0,
            "ALTER TABLE settings ADD COLUMN prag_turbiditate_maxim SMALLINT UNSIGNED NOT NULL DEFAULT 50 COMMENT 'NTU peste care apa e prea tulbure pentru irigare'",
            "SET @info = 'prag_turbiditate_maxim exista deja'");
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
