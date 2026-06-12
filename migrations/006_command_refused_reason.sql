-- ============================================================
--  AquaSmart — Migrare 006: motiv refuz comanda
--  Coloana noua in commands: cand ESP32 refuza o comanda de udare
--  (ex. rezervor gol), stocheaza motivul ca text scurt.
--  Idempotent: protejat cu information_schema check.
-- ============================================================

USE aquasmart;

SET @x = (SELECT COUNT(*) FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME   = 'commands'
            AND COLUMN_NAME  = 'refused_reason');
SET @s = IF(@x = 0,
            "ALTER TABLE commands ADD COLUMN refused_reason VARCHAR(40) NULL COMMENT 'Motiv refuz (ex. rezervor_gol) — null daca a fost executata normal'",
            "SET @info = 'refused_reason exista deja'");
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
