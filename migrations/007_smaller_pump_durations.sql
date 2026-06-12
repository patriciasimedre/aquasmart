-- ============================================================
--  AquaSmart — Migrare 007: durate adaptate pompei mici
--  Defaultul vechi (30-180s) era pentru sisteme cu pompe lente.
--  Pentru o pompa DC mica (50-100 ml/s) + ghivece mici, plafoanele
--  reale sunt mult mai scurte. Cactus = 3s, Legume = 30s, etc.
--  Idempotent: UPDATE-uri cu WHERE pe nume; rulare repetata = no-op.
-- ============================================================

USE aquasmart;

UPDATE plant_profiles SET durata_max = 3  WHERE nume = 'Suculente/Cactus';
UPDATE plant_profiles SET durata_max = 10 WHERE nume = 'Plante interior';
UPDATE plant_profiles SET durata_max = 30 WHERE nume = 'Legume';
UPDATE plant_profiles SET durata_max = 15 WHERE nume = 'Flori';
UPDATE plant_profiles SET durata_max = 5  WHERE nume = 'Răsaduri';
-- Custom: NU atingem — userul poate seta orice prin reactivarea Custom (mostenit).
