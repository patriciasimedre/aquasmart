-- ============================================================
--  AquaSmart — Migrare 003: senzor capacitiv sol + HC-SR04 nivel
--                            + 4 praguri noi in settings.
--
--  IDEMPOTENT: fiecare ADD COLUMN e protejat printr-un check pe
--  information_schema, deci re-rularea NU pica. Ramura "skip"
--  foloseste SET (nu SELECT) ca sa nu produca result set
--  neconsumat (altfel DEALLOCATE PREPARE crapa cu PDO).
-- ============================================================

USE aquasmart;

-- --- sensor_readings.umiditate_sol ---
SET @x = (SELECT COUNT(*) FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME   = 'sensor_readings'
            AND COLUMN_NAME  = 'umiditate_sol');
SET @s = IF(@x = 0,
            "ALTER TABLE sensor_readings ADD COLUMN umiditate_sol TINYINT UNSIGNED NULL COMMENT '% umiditate sol, senzor capacitiv v2.0.0' AFTER umiditate_aer",
            "SET @info = 'umiditate_sol exista deja'");
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- --- sensor_readings.nivel_cm ---
SET @x = (SELECT COUNT(*) FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME   = 'sensor_readings'
            AND COLUMN_NAME  = 'nivel_cm');
SET @s = IF(@x = 0,
            "ALTER TABLE sensor_readings ADD COLUMN nivel_cm DECIMAL(5,1) NULL COMMENT 'cm distanta HC-SR04, nivel rezervor' AFTER nivel_jos",
            "SET @info = 'nivel_cm exista deja'");
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- --- settings: prag_sol_uscat ---
SET @x = (SELECT COUNT(*) FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME   = 'settings'
            AND COLUMN_NAME  = 'prag_sol_uscat');
SET @s = IF(@x = 0,
            "ALTER TABLE settings ADD COLUMN prag_sol_uscat TINYINT UNSIGNED NOT NULL DEFAULT 30 COMMENT '% sub care solul e considerat uscat'",
            "SET @info = 'prag_sol_uscat exista deja'");
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- --- settings: prag_tds_maxim ---
SET @x = (SELECT COUNT(*) FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME   = 'settings'
            AND COLUMN_NAME  = 'prag_tds_maxim');
SET @s = IF(@x = 0,
            "ALTER TABLE settings ADD COLUMN prag_tds_maxim SMALLINT UNSIGNED NOT NULL DEFAULT 800 COMMENT 'ppm peste care apa e rea pentru plante'",
            "SET @info = 'prag_tds_maxim exista deja'");
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- --- settings: prag_temp_apa_min ---
SET @x = (SELECT COUNT(*) FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME   = 'settings'
            AND COLUMN_NAME  = 'prag_temp_apa_min');
SET @s = IF(@x = 0,
            "ALTER TABLE settings ADD COLUMN prag_temp_apa_min TINYINT NOT NULL DEFAULT 5 COMMENT 'grade C sub care nu udam'",
            "SET @info = 'prag_temp_apa_min exista deja'");
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;

-- --- settings: prag_temp_apa_max ---
SET @x = (SELECT COUNT(*) FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME   = 'settings'
            AND COLUMN_NAME  = 'prag_temp_apa_max');
SET @s = IF(@x = 0,
            "ALTER TABLE settings ADD COLUMN prag_temp_apa_max TINYINT NOT NULL DEFAULT 40 COMMENT 'grade C peste care nu udam'",
            "SET @info = 'prag_temp_apa_max exista deja'");
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
