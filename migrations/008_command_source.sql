-- ============================================================
--  AquaSmart — Migrare 008: sursa comenzii (manual / fuzzy)
--  Coloana noua in commands: cine a creat comanda — interfata
--  (manual) sau motorul fuzzy (IrrigationDecision). Folosita la
--  ack pentru a inregistra corect motivul evenimentului de udare,
--  astfel incat raportul sa numere separat udarile fuzzy.
--  Idempotent: protejat cu information_schema check.
-- ============================================================

USE aquasmart;

SET @x = (SELECT COUNT(*) FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME   = 'commands'
            AND COLUMN_NAME  = 'sursa');
SET @s = IF(@x = 0,
            "ALTER TABLE commands ADD COLUMN sursa VARCHAR(10) NOT NULL DEFAULT 'manual' COMMENT 'Cine a creat comanda: manual (UI) / fuzzy (decizia automata)'",
            "SET @info = 'sursa exista deja'");
PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
