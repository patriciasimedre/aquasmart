-- ============================================================
--  AquaSmart — Migrare 002: DATE DEMO (doar pentru prezentare)
--
--  Reversibil / re-rulabil: goleste si repopuleaza sensor_readings
--  si irrigation_events. NU atinge settings, commands, ai_reports.
--
--  Curatare manuala (revenire la gol):
--    TRUNCATE TABLE sensor_readings;
--    TRUNCATE TABLE irrigation_events;
-- ============================================================

USE aquasmart;

TRUNCATE TABLE sensor_readings;
TRUNCATE TABLE irrigation_events;

-- ~200 citiri pe ultimele ~7 zile (una la ~50 minute), valori plauzibile.
INSERT INTO sensor_readings
    (timestamp, temp_apa, temp_aer, umiditate_aer, umiditate_sol, tds, ploaie, nivel_sus, nivel_jos)
WITH RECURSIVE seq(n) AS (
    SELECT 0
    UNION ALL
    SELECT n + 1 FROM seq WHERE n < 199
)
SELECT
    NOW() - INTERVAL (199 - n) * 50 MINUTE                                          AS timestamp,
    ROUND(13 + 4  * SIN(n / 9)        + (RAND() - 0.5),      2)                      AS temp_apa,
    ROUND(16 + 8  * SIN(n / 7)        + 2 * (RAND() - 0.5),  2)                      AS temp_aer,
    ROUND(55 + 18 * SIN(n / 11 + 1)   + 4 * (RAND() - 0.5),  2)                      AS umiditate_aer,
    GREATEST(0, LEAST(100, ROUND(45 + 18 * SIN(n / 14 + 2) + 6 * (RAND() - 0.5))))   AS umiditate_sol,
    ROUND(110 + 40 * SIN(n / 13)      + 20 * RAND())                                 AS tds,
    IF(RAND() < 0.10, 1, 0)                                                          AS ploaie,
    IF(MOD(n, 60) < 45, 1, 0)                                                        AS nivel_sus,
    1                                                                                AS nivel_jos
FROM seq;

-- ~8 evenimente de udare pe parcursul saptamanii.
INSERT INTO irrigation_events
    (timestamp_start, durata_secunde, motiv, nivel_inainte, temp_aer_inainte)
VALUES
    (NOW() - INTERVAL 6 DAY + INTERVAL 8  HOUR, 30, 'fuzzy',     'plin',    18.4),
    (NOW() - INTERVAL 5 DAY + INTERVAL 19 HOUR, 60, 'fuzzy',     'partial', 24.1),
    (NOW() - INTERVAL 4 DAY + INTERVAL 7  HOUR, 15, 'manual',    'plin',    15.7),
    (NOW() - INTERVAL 3 DAY + INTERVAL 18 HOUR, 30, 'fuzzy',     'partial', 22.9),
    (NOW() - INTERVAL 2 DAY + INTERVAL 9  HOUR, 60, 'programat', 'plin',    19.2),
    (NOW() - INTERVAL 2 DAY + INTERVAL 20 HOUR, 15, 'manual',    'partial', 21.0),
    (NOW() - INTERVAL 1 DAY + INTERVAL 8  HOUR, 30, 'fuzzy',     'plin',    17.6),
    (NOW() - INTERVAL 6 HOUR,                   30, 'fuzzy',     'partial', 20.3);
