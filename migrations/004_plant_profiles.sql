-- ============================================================
--  AquaSmart — Migrare 004: profile de plante
--  Tabela plant_profiles + 6 profiluri (5 predefinite + 1 Custom).
--  Profilul Custom se initializeaza din settings curente.
--
--  Idempotent: CREATE TABLE IF NOT EXISTS + INSERT IGNORE (UNIQUE pe nume).
-- ============================================================

USE aquasmart;

CREATE TABLE IF NOT EXISTS plant_profiles (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nume          VARCHAR(60) NOT NULL UNIQUE,
    emoji         VARCHAR(10) NOT NULL,
    descriere     VARCHAR(255) NULL,
    sol_min       TINYINT UNSIGNED NOT NULL  COMMENT '% sub care solul e considerat uscat',
    tds_max       SMALLINT UNSIGNED NOT NULL COMMENT 'ppm maxim acceptat',
    interval_min  SMALLINT UNSIGNED NOT NULL COMMENT 'minute minim intre udari',
    durata_max    SMALLINT UNSIGNED NOT NULL COMMENT 'secunde maxim per udare (informativ)',
    is_custom     TINYINT(1) NOT NULL DEFAULT 0,
    is_active     TINYINT(1) NOT NULL DEFAULT 0,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Profiluri predefinite
INSERT IGNORE INTO plant_profiles
    (nume, emoji, descriere, sol_min, tds_max, interval_min, durata_max, is_custom, is_active)
VALUES
    -- durata_max = plafon (sec) pentru fuzzy + valoare implicita la radio.
    -- Calibrate pentru pompa DC mica (50-100 ml/s) si ghivece mici.
    -- 3s ≈ 150-300 ml apa pentru cactus, 30s ≈ 1.5-3 L pentru legume mari.
    ('Suculente/Cactus', '🌵', 'Udare rară, sol uscat. Tolerează apă mai mineralizată.',         15, 600, 720, 3,   0, 0),
    ('Plante interior',  '🌿', 'Udare echilibrată pentru ficus, monstera, pothos etc.',           35, 700, 360, 10,  0, 0),
    ('Legume',           '🍅', 'Roșii, ardei, salată — udare frecventă, apă curată.',             45, 500, 240, 30,  0, 0),
    ('Flori',            '🌸', 'Mușcate, petunii, begonii — udare moderată.',                     40, 600, 300, 15,  0, 0),
    ('Răsaduri',         '🌱', 'Plante tinere — udare scurtă și deasă, apă foarte curată.',       50, 400, 180, 5,   0, 0);

-- Custom — preluat din settings curente, activ implicit
INSERT IGNORE INTO plant_profiles
    (nume, emoji, descriere, sol_min, tds_max, interval_min, durata_max, is_custom, is_active)
SELECT 'Custom', '⚙️', 'Configurare personalizată — preluată din setările curente.',
       prag_sol_uscat, prag_tds_maxim, interval_minim_udare, 60, 1, 1
  FROM settings
 WHERE id = 1;
