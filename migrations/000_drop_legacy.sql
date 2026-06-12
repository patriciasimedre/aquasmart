-- ============================================================
--  AquaSmart — Migrare 000: stergere schema VECHE (filtrare)
--
--  IREVERSIBIL. Confirmat explicit de utilizator, fara backup.
--  Sterge cele 10 tabele ale conceptului anterior pentru a
--  permite reconstruirea curata conform README1.md (001).
-- ============================================================

USE aquasmart;

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS actuator_states;
DROP TABLE IF EXISTS ai_reports;
DROP TABLE IF EXISTS calibration_points;
DROP TABLE IF EXISTS commands;
DROP TABLE IF EXISTS devices;
DROP TABLE IF EXISTS filtration_sessions;
DROP TABLE IF EXISTS sensor_readings;
DROP TABLE IF EXISTS sensor_types;
DROP TABLE IF EXISTS system_logs;
DROP TABLE IF EXISTS users;

SET FOREIGN_KEY_CHECKS = 1;
