<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Command;
use App\Models\IrrigationEvent;
use App\Models\PlantProfile;
use App\Models\SensorReading;
use App\Models\Settings;

/**
 * Orchestreaza decizia automata de udare:
 *   1. aplica gateuri APLICATIE (mod automat, interval minim, fara citiri);
 *   2. ruleaza FuzzyEvaluator care are propria etapa de blocare CRISP
 *      (ploaie / rezervor gol / TDS / temp apa / prognoza) urmata de
 *      inferenta Mamdani pe 5 intrari fuzzy + 21 reguli;
 *   3. (optional) pune o comanda de udare in coada pentru ESP32.
 *
 * evaluate(false) = dry-run pentru preview in dashboard.
 * evaluate(true)  = comite comanda (apelat dupa fiecare citire ESP32 / din cron).
 */
final class IrrigationDecision
{
    public function evaluate(bool $commit): array
    {
        $settings   = Settings::get();
        $modAuto    = (int) $settings['mod_automat'] === 1;
        $intervMin  = (int) $settings['interval_minim_udare'];

        $pragSol     = (int) ($settings['prag_sol_uscat']         ?? 30);
        $pragTds     = (int) ($settings['prag_tds_maxim']         ?? 800);
        $pragApaMin  = (int) ($settings['prag_temp_apa_min']      ?? 5);
        $pragApaMax  = (int) ($settings['prag_temp_apa_max']      ?? 40);
        $pragTurb    = (int) ($settings['prag_turbiditate_maxim'] ?? 50);

        // Profilul activ — folosim durata_max ca plafon pentru udarile automate.
        // Astfel Cactus/Suculente nu va primi niciodata 180s, iar Legume poate primi
        // maxim ce a fost setat in profil (default 180s).
        $profile     = PlantProfile::active();
        $durataMax   = $profile ? (int) ($profile['durata_max'] ?? 300) : 300;
        if ($durataMax <= 0) { $durataMax = 300; }

        // --- Citire curenta ---
        $reading = SensorReading::latest();
        $nivel   = $reading ? SensorReading::nivelFromRow($reading) : null;

        $umidAer = $reading && $reading['umiditate_aer'] !== null
            ? (float) $reading['umiditate_aer'] : null;
        $umidSol = $reading && isset($reading['umiditate_sol']) && $reading['umiditate_sol'] !== null
            ? (float) $reading['umiditate_sol'] : null;
        $tempAer = $reading && $reading['temp_aer'] !== null
            ? (float) $reading['temp_aer'] : null;
        $tempApa = $reading && $reading['temp_apa'] !== null
            ? (float) $reading['temp_apa'] : null;
        $tds     = $reading && $reading['tds'] !== null
            ? (int) $reading['tds'] : null;
        $turb    = $reading && isset($reading['turbiditate']) && $reading['turbiditate'] !== null
            ? (int) $reading['turbiditate'] : null;
        $ploaie  = $reading ? (int) ($reading['ploaie'] ?? 0) : 0;
        $nivelCm = $reading && isset($reading['nivel_cm']) && $reading['nivel_cm'] !== null
            ? (float) $reading['nivel_cm'] : null;

        // --- Ultima udare + meteo ---
        $last = IrrigationEvent::last();
        $minSinceLast = $last
            ? max(0, (int) round((time() - strtotime((string) $last['timestamp_start'])) / 60))
            : null;

        $weather = WeatherService::fromConfig()->forecast();
        $prob3h  = (float) ($weather['prob_3h'] ?? 0.0);
        $rain24  = $weather['rain_24h']; // bool|null

        // --- Etapa fuzzy (cu blocare crisp inclusa) ---
        $fuzzy = (new FuzzyEvaluator())->evaluate([
            'umiditate_sol'         => $umidSol ?? 40.0,
            'umiditate_aer'         => $umidAer ?? 50.0,
            'temp_aer'              => $tempAer ?? 20.0,
            'nivel'                 => $nivel ?? 'partial',
            'tds'                   => $tds    ?? 200,
            'turbiditate'           => $turb,    // null => fara verificare (senzor absent)
            'ploaie'                => $ploaie,
            'temp_apa'              => $tempApa ?? 20.0,
            'prognoza_ploaie'       => $prob3h,
            'prag_sol_uscat'        => $pragSol,
            'prag_tds_maxim'        => $pragTds,
            'prag_temp_apa_min'     => $pragApaMin,
            'prag_temp_apa_max'     => $pragApaMax,
            'prag_turbiditate_maxim'=> $pragTurb,
        ]);

        // --- Gateuri APLICATIE (suprascriu fuzzy daca apar) ---
        $appGate = null;
        if (!$modAuto) {
            $appGate = 'mod_manual';
        } elseif ($reading === null) {
            $appGate = 'fara_citiri';
        } elseif ($minSinceLast !== null && $minSinceLast < $intervMin) {
            $appGate = 'interval_minim';
        }

        // --- Sinteza ---
        $gate = $appGate ?? ($fuzzy['blocat'] ? $fuzzy['motiv_blocare'] : null);
        $durataBruta  = ($appGate === null && !$fuzzy['blocat']) ? (int) $fuzzy['durata'] : 0;

        // Plafon dupa profilul activ: fuzzy poate recomanda 180s, dar pentru Cactus
        // (durata_max=3s) nu vrem niciodata mai mult. Profilul Custom = durata_max
        // explicita aleasa de user (sau 300s = nelimitat).
        $durataFinala = min($durataBruta, $durataMax);
        $ramase = ($minSinceLast !== null) ? max(0, $intervMin - $minSinceLast) : null;

        // --- Comitere comanda ---
        $actiune = 'dry_run';
        if ($commit) {
            if ($durataFinala > 0) {
                if (Command::pendingWateringExists()) {
                    $actiune = 'skip_duplicat';
                } else {
                    Command::create('udare', $durataFinala);
                    $actiune = 'comanda_emisa';
                }
            } else {
                $actiune = 'fara_actiune';
            }
        }

        return [
            'timestamp' => date('c'),
            'intrari'   => [
                'mod_automat'         => $modAuto,
                'interval_minim'      => $intervMin,
                'min_de_la_ultima'    => $minSinceLast,
                'min_ramase'          => $ramase,
                'umiditate_sol'       => $umidSol,
                'umiditate_aer'       => $umidAer,
                'temp_aer'            => $tempAer,
                'temp_apa'            => $tempApa,
                'nivel'               => $nivel,
                'nivel_cm'            => $nivelCm,
                'tds'                 => $tds,
                'turbiditate'         => $turb,
                'ploaie'              => $ploaie,
                'prob_3h'             => $prob3h,
                'ploaie_24h'          => $rain24,
                'prag_sol_uscat'      => $pragSol,
                'prag_tds_maxim'      => $pragTds,
                'prag_temp_apa_min'   => $pragApaMin,
                'prag_temp_apa_max'   => $pragApaMax,
                'prag_turbiditate_maxim' => $pragTurb,
                // Plafon din profilul activ (Cactus=30s, Legume=180s, Custom=user)
                'durata_max_profil'   => $durataMax,
                'profil_activ'        => $profile ? ($profile['nume'] ?? null) : null,
            ],
            'fuzzy'         => $fuzzy,
            'gate'          => $gate,
            'durata_bruta_fuzzy' => $durataBruta,   // ce a recomandat fuzzy inainte de plafon
            'durata_finala' => $durataFinala,
            'actiune'       => $actiune,
            'explicatie'    => $this->explica($appGate, $fuzzy, $durataFinala, $actiune, $ramase),
        ];
    }

    private function explica(?string $appGate, array $fuzzy, int $durata, string $actiune, ?int $ramase): string
    {
        $msg = match ($appGate) {
            'mod_manual'     => 'Modul automat e dezactivat — sistemul udă doar la comandă manuală.',
            'fara_citiri'    => 'Nu am încă date de la ESP32 — verifică dacă dispozitivul e pornit și conectat la WiFi.',
            'interval_minim' => sprintf(
                'Așteaptă încă ~%d minute până la următoarea udare permisă (interval minim configurat).',
                $ramase ?? 0
            ),
            default          => $fuzzy['explicatie'] ?? 'Evaluare fuzzy.',
        };

        return match ($actiune) {
            'comanda_emisa' => $msg . ' Comanda de udare a fost trimisă către ESP32.',
            'skip_duplicat' => $msg . ' Există deja o udare în așteptare — nu se dublează.',
            default         => $msg,
        };
    }
}
