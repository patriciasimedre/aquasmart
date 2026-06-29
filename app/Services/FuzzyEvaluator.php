<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Controller fuzzy clasic Mamdani in DOUA etape pentru decizia de udare.
 *
 *   Etapa 1 — BLOCARE CRISP (pre-fuzzy). Returneaza imediat durata 0
 *             si motivul, daca oricare conditie e adevarata:
 *             ploaie activa, rezervor gol, TDS peste prag, temperatura
 *             apei in afara intervalului, prognoza ploaie > 50% in 3h,
 *             turbiditate toxica (>200 NTU, hard-coded) sau peste pragul
 *             configurabil (mal/sedimente care infunda pompa).
 *
 *   Etapa 2 — INFERENTA MAMDANI cu 5 intrari fuzzy:
 *             - umiditate_sol (% capacitiv v2.0.0)  — FACTOR PRINCIPAL
 *             - umiditate_aer (%)                    — factor corectie
 *             - temp_aer      (°C)                   — factor evaporare
 *             - nivel         ('gol'|'partial'|'plin') — singleton crisp
 *             - tds           (ppm)                  — calitate apa
 *
 *   Pragul `prag_sol_uscat` (din Setari) muta dinamic centrul flancului USCAT
 *   in fuzzSol(): default 30% reproduce comportamentul istoric trap(0,0,25,35);
 *   valori mai mari extind zona "uscat", valori mai mici o restrang. MEDIU si
 *   UMED raman fixate ca sa nu se rupa regulile pentru sol cu adevarat umed.
 *
 *   Iesire: durata udare 0..300 s -> snap la {0,15,30,60,120,180,300}.
 *   AND = min, agregare = max, defuzzificare = centroid.
 */
final class FuzzyEvaluator
{
    private const STEP = 1.0;
    private const UNIVERSE_MAX = 300.0;

    /** Valori discrete de pompa la care "lipim" rezultatul defuzzificat. */
    private const SNAP = [0, 15, 30, 60, 120, 180, 300];

    /**
     * Prag absolut peste care apa e considerata toxica indiferent de setari —
     * apa cu peste 200 NTU contine prea multe particule pentru orice irigare
     * (risc de infundare pompa + dauneaza radacinilor). Hard-coded, neoverridable.
     */
    public const TURBIDITATE_TOXICA = 200;

    // ---- Functii de apartenenta elementare -------------------------------

    private static function tri(float $x, float $a, float $b, float $c): float
    {
        if ($x <= $a || $x >= $c) {
            return 0.0;
        }
        return $x < $b ? ($x - $a) / ($b - $a) : ($c - $x) / ($c - $b);
    }

    /** Trapezoid care trateaza corect umerii (a==b stanga, c==d dreapta). */
    private static function trap(float $x, float $a, float $b, float $c, float $d): float
    {
        if ($x < $a || $x > $d) {
            return 0.0;
        }
        if ($x < $b) {
            return $a == $b ? 1.0 : ($x - $a) / ($b - $a);
        }
        if ($x <= $c) {
            return 1.0;
        }
        return $c == $d ? 1.0 : ($d - $x) / ($d - $c);
    }

    // ---- Fuzzificare intrari ---------------------------------------------

    /**
     * Pragul `prag_sol_uscat` (default 30%) e centrul flancului descrescator al
     * multimii USCAT: membership = 1.0 sub (prag-5), scade liniar la 0 in (prag+5).
     * Astfel slider-ul din UI ridica/coboara nivelul la care solul e considerat
     * "uscat" pentru regulile R1–R10. MEDIU si UMED raman fixate ca sa pastram
     * semantica: sol > 55% e tot "umed" indiferent de prag.
     *
     * @return array{USCAT:float,MEDIU:float,UMED:float}
     */
    private function fuzzSol(float $x, float $pragUscat = 30.0): array
    {
        $x    = max(0.0, min(100.0, $x));
        $prag = max(10.0, min(55.0, $pragUscat));

        return [
            'USCAT' => self::trap($x, 0, 0, max(0.0, $prag - 5.0), $prag + 5.0),
            'MEDIU' => self::tri($x, 28, 50, 62),
            'UMED'  => self::trap($x, 55, 70, 100, 100),
        ];
    }

    /** @return array{SCAZUTA:float,MEDIE:float,RIDICATA:float} */
    private function fuzzAer(float $x): array
    {
        $x = max(0.0, min(100.0, $x));
        return [
            'SCAZUTA'  => self::trap($x, 0, 0, 35, 45),
            'MEDIE'    => self::tri($x, 38, 55, 72),
            'RIDICATA' => self::trap($x, 65, 78, 100, 100),
        ];
    }

    /** @return array{RECE:float,TEMPERATA:float,CALDA:float} */
    private function fuzzTempAer(float $x): array
    {
        return [
            'RECE'      => self::trap($x, -20, -20, 8, 15),
            'TEMPERATA' => self::tri($x, 10, 20, 30),
            'CALDA'     => self::trap($x, 25, 32, 50, 50),
        ];
    }

    /** Crisp -> singleton. @return array{GOL:float,PARTIAL:float,PLIN:float} */
    private function fuzzNivel(string $s): array
    {
        return [
            'GOL'     => $s === 'gol' ? 1.0 : 0.0,
            'PARTIAL' => $s === 'partial' ? 1.0 : 0.0,
            'PLIN'    => $s === 'plin' ? 1.0 : 0.0,
        ];
    }

    /** @return array{BUN:float,ACCEPTABIL:float,RAU:float} */
    private function fuzzTds(float $x): array
    {
        $x = max(0.0, $x);
        return [
            'BUN'        => self::trap($x, 0, 0, 400, 550),
            'ACCEPTABIL' => self::tri($x, 450, 625, 800),
            'RAU'        => self::trap($x, 700, 850, 2000, 2000),
        ];
    }

    // ---- Multimile de iesire (durata, secunde) ---------------------------

    private function outMembership(string $set, float $x): float
    {
        return match ($set) {
            'FARA'  => self::trap($x, 0, 0, 0, 20),
            'SCURT' => self::tri($x, 15, 35, 60),
            'MEDIU' => self::tri($x, 50, 120, 180),
            'LUNG'  => self::trap($x, 150, 220, 300, 300),
            default => 0.0,
        };
    }

    // ---- Baza de reguli (21) ---------------------------------------------

    /** Fiecare regula: ['if' => [[var, termen], ...], 'then' => set iesire]. */
    private function rules(): array
    {
        return [
            // Sol uscat — prioritate maxima
            ['if' => [['sol','USCAT'],   ['nivel','PLIN'],    ['aer','SCAZUTA'],  ['temp','CALDA']],     'then' => 'LUNG'],   // R1
            ['if' => [['sol','USCAT'],   ['nivel','PLIN'],    ['aer','SCAZUTA'],  ['temp','TEMPERATA']], 'then' => 'LUNG'],   // R2
            ['if' => [['sol','USCAT'],   ['nivel','PLIN'],    ['aer','MEDIE'],    ['temp','CALDA']],     'then' => 'MEDIU'],  // R3
            ['if' => [['sol','USCAT'],   ['nivel','PLIN'],    ['aer','MEDIE'],    ['temp','TEMPERATA']], 'then' => 'MEDIU'],  // R4
            ['if' => [['sol','USCAT'],   ['nivel','PLIN'],    ['aer','RIDICATA']],                       'then' => 'SCURT'],  // R5
            ['if' => [['sol','USCAT'],   ['nivel','PARTIAL'], ['aer','SCAZUTA']],                        'then' => 'MEDIU'],  // R6
            ['if' => [['sol','USCAT'],   ['nivel','PARTIAL'], ['aer','MEDIE']],                          'then' => 'SCURT'],  // R7
            ['if' => [['sol','USCAT'],   ['nivel','PARTIAL'], ['aer','RIDICATA']],                       'then' => 'FARA'],   // R8 — conservare: cu rezervor partial + aer foarte umed, planta pierde putin, asteptam ploaia
            ['if' => [['sol','USCAT'],   ['tds','RAU']],                                                 'then' => 'SCURT'],  // R9
            ['if' => [['sol','USCAT'],   ['tds','RAU'],       ['nivel','PARTIAL']],                      'then' => 'FARA'],   // R10

            // Sol mediu
            ['if' => [['sol','MEDIU'],   ['nivel','PLIN'],    ['aer','SCAZUTA'],  ['temp','CALDA']],     'then' => 'MEDIU'],  // R11
            ['if' => [['sol','MEDIU'],   ['nivel','PLIN'],    ['aer','SCAZUTA'],  ['temp','TEMPERATA']], 'then' => 'SCURT'],  // R12
            ['if' => [['sol','MEDIU'],   ['nivel','PLIN'],    ['aer','MEDIE']],                          'then' => 'SCURT'],  // R13
            ['if' => [['sol','MEDIU'],   ['nivel','PLIN'],    ['aer','RIDICATA']],                       'then' => 'FARA'],   // R14
            ['if' => [['sol','MEDIU'],   ['nivel','PARTIAL']],                                           'then' => 'SCURT'],  // R15
            ['if' => [['sol','MEDIU'],   ['nivel','PARTIAL'], ['aer','RIDICATA']],                       'then' => 'FARA'],   // R16
            ['if' => [['sol','MEDIU'],   ['tds','RAU']],                                                 'then' => 'FARA'],   // R17
            ['if' => [['sol','MEDIU'],   ['temp','RECE']],                                               'then' => 'FARA'],   // R18

            // Sol umed
            ['if' => [['sol','UMED']],                                                                   'then' => 'FARA'],   // R19

            // Temperatura — corectii
            ['if' => [['sol','USCAT'],   ['temp','CALDA'],    ['aer','SCAZUTA'],  ['nivel','PLIN']],     'then' => 'LUNG'],   // R20
            ['if' => [['sol','MEDIU'],   ['temp','RECE'],     ['nivel','PLIN']],                         'then' => 'FARA'],   // R21
        ];
    }

    // ---- Verificarea crisp (Etapa 1) -------------------------------------

    /** Returneaza codul motivului de blocare, sau null daca trecem mai departe. */
    private function preCheck(array $in): ?string
    {
        if (!empty($in['ploaie'])) {
            return 'ploaie_activa';
        }
        if (($in['nivel'] ?? '') === 'gol') {
            return 'rezervor_gol';
        }

        $tds    = (int) ($in['tds'] ?? 0);
        $tdsMax = (int) ($in['prag_tds_maxim'] ?? 800);
        if ($tds > $tdsMax) {
            return 'tds_ridicat';
        }

        // Turbiditate — doua niveluri:
        //   1) Toxic absolut (hard-coded) — protectia pompei, indiferent de setari.
        //   2) Peste prag configurabil — utilizatorul a decis ca apa e prea tulbure.
        if (isset($in['turbiditate']) && $in['turbiditate'] !== null) {
            $turb    = (int) $in['turbiditate'];
            $turbMax = (int) ($in['prag_turbiditate_maxim'] ?? 50);
            if ($turb > self::TURBIDITATE_TOXICA) {
                return 'apa_toxica';
            }
            if ($turb > $turbMax) {
                return 'apa_tulbure';
            }
        }

        $tApa = isset($in['temp_apa']) ? (float) $in['temp_apa'] : null;
        $tMin = (float) ($in['prag_temp_apa_min'] ?? 5);
        $tMax = (float) ($in['prag_temp_apa_max'] ?? 40);
        if ($tApa !== null && $tApa < $tMin) {
            return 'temp_apa_scazuta';
        }
        if ($tApa !== null && $tApa > $tMax) {
            return 'temp_apa_ridicata';
        }

        $prog = (float) ($in['prognoza_ploaie'] ?? 0.0);
        if ($prog > 0.5) {
            return 'prognoza_ploaie';
        }

        return null;
    }

    // ---- Evaluare completa ------------------------------------------------

    /**
     * @param array{
     *     umiditate_sol:float, umiditate_aer:float, temp_aer:float,
     *     nivel:string, tds:int, ploaie:int, temp_apa:float,
     *     prognoza_ploaie?:float,
     *     prag_sol_uscat?:float, prag_tds_maxim?:int,
     *     prag_temp_apa_min?:int, prag_temp_apa_max?:int
     * } $in
     * @return array detaliu complet (blocare, memberships, reguli, durata, etc.)
     */
    public function evaluate(array $in): array
    {
        // --- Etapa 1: blocare crisp ---
        $motiv = $this->preCheck($in);
        if ($motiv !== null) {
            return [
                'blocat'         => true,
                'motiv_blocare'  => $motiv,
                'memberships'    => [],
                'reguli_active'  => [],
                'durata_bruta'   => 0.0,
                'durata'         => 0,
                'decizie'        => 'nu_uda',
                'explicatie'     => $this->explicaBlocare($motiv, $in),
            ];
        }

        // --- Etapa 2: Mamdani ---
        $mu = [
            'sol'   => $this->fuzzSol(
                (float) ($in['umiditate_sol'] ?? 40.0),
                (float) ($in['prag_sol_uscat'] ?? 30.0)
            ),
            'aer'   => $this->fuzzAer((float) ($in['umiditate_aer'] ?? 50.0)),
            'temp'  => $this->fuzzTempAer((float) ($in['temp_aer'] ?? 20.0)),
            'nivel' => $this->fuzzNivel((string) ($in['nivel'] ?? 'partial')),
            'tds'   => $this->fuzzTds((float) ($in['tds'] ?? 200)),
        ];

        // Taria fiecarei reguli + taria agregata pe fiecare multime de iesire.
        $strength = ['FARA' => 0.0, 'SCURT' => 0.0, 'MEDIU' => 0.0, 'LUNG' => 0.0];
        $fired = [];
        foreach ($this->rules() as $i => $rule) {
            $w = 1.0;
            foreach ($rule['if'] as [$var, $term]) {
                $w = min($w, $mu[$var][$term] ?? 0.0);
            }
            if ($w > 0.0) {
                $strength[$rule['then']] = max($strength[$rule['then']], $w);
                $fired[] = ['regula' => $i + 1, 'taria' => round($w, 3), 'iesire' => $rule['then']];
            }
        }

        // Agregare Mamdani + centroid pe universul 0..300.
        $num = 0.0;
        $den = 0.0;
        for ($x = 0.0; $x <= self::UNIVERSE_MAX; $x += self::STEP) {
            $agg = 0.0;
            foreach ($strength as $set => $s) {
                if ($s > 0.0) {
                    $agg = max($agg, min($s, $this->outMembership($set, $x)));
                }
            }
            $num += $x * $agg;
            $den += $agg;
        }
        $raw = $den > 0.0 ? $num / $den : 0.0;
        $snap = $this->snap($raw);
        $decizie = $this->decisionLabel($snap);

        return [
            'blocat'         => false,
            'motiv_blocare'  => null,
            'memberships'    => $mu,
            'reguli_active'  => $fired,
            'durata_bruta'   => round($raw, 2),
            'durata'         => $snap,
            'decizie'        => $decizie,
            'explicatie'     => $this->explicaFuzzy($mu, $in, $snap, $decizie),
        ];
    }

    // ---- Helpere defuzzificare & etichete --------------------------------

    private function snap(float $raw): int
    {
        $best = self::SNAP[0];
        $bd   = INF;
        foreach (self::SNAP as $v) {
            $d = abs($v - $raw);
            if ($d < $bd) {
                $bd = $d;
                $best = $v;
            }
        }
        return $best;
    }

    private function decisionLabel(int $snap): string
    {
        return match (true) {
            $snap === 0  => 'nu_uda',
            $snap <= 30  => 'uda_scurt',
            $snap <= 120 => 'uda_mediu',
            default      => 'uda_lung',
        };
    }

    // ---- Explicatii in romana --------------------------------------------

    private function explicaBlocare(string $motiv, array $in): string
    {
        $tds     = (int) ($in['tds'] ?? 0);
        $tdsMax  = (int) ($in['prag_tds_maxim'] ?? 800);
        $tApa    = isset($in['temp_apa']) ? (float) $in['temp_apa'] : 0.0;
        $tMin    = (int) ($in['prag_temp_apa_min'] ?? 5);
        $tMax    = (int) ($in['prag_temp_apa_max'] ?? 40);
        $prog    = (int) round(100 * (float) ($in['prognoza_ploaie'] ?? 0.0));
        $turb    = (int) ($in['turbiditate'] ?? 0);
        $turbMax = (int) ($in['prag_turbiditate_maxim'] ?? 50);

        return match ($motiv) {
            'ploaie_activa'     => 'Blocat: ploaie activă detectată acum.',
            'rezervor_gol'      => 'Blocat: rezervorul e gol — protejăm pompa.',
            'tds_ridicat'       => "Blocat: calitate apă slabă (TDS {$tds} ppm > {$tdsMax} ppm limită).",
            'temp_apa_scazuta'  => "Blocat: temperatura apei prea scăzută (" . number_format($tApa, 1) . "°C < {$tMin}°C limită).",
            'temp_apa_ridicata' => "Blocat: temperatura apei prea ridicată (" . number_format($tApa, 1) . "°C > {$tMax}°C limită).",
            'prognoza_ploaie'   => "Blocat: prognoză ploaie {$prog}% în următoarele 3h — economisim apa.",
            'apa_toxica'        => "Blocat: apă toxică ({$turb} NTU > " . self::TURBIDITATE_TOXICA . " NTU prag absolut) — prea multe sedimente, risc de înfundare a pompei și deteriorare radăcini.",
            'apa_tulbure'       => "Blocat: apă prea tulbure ({$turb} NTU > {$turbMax} NTU limită) — particulele în suspensie pot bloca picurătorii și sufoca rădăcinile.",
            default             => 'Blocat (motiv necunoscut).',
        };
    }

    private function explicaFuzzy(array $mu, array $in, int $durata, string $decizie): string
    {
        $solTerm  = $this->dominant($mu['sol']);
        $aerTerm  = $this->dominant($mu['aer']);
        $tempTerm = $this->dominant($mu['temp']);

        $tradSol  = ['USCAT' => 'uscat', 'MEDIU' => 'mediu', 'UMED' => 'umed'][$solTerm] ?? $solTerm;
        $tradAer  = ['SCAZUTA' => 'scăzută', 'MEDIE' => 'medie', 'RIDICATA' => 'ridicată'][$aerTerm] ?? $aerTerm;
        $tradTemp = ['RECE' => 'rece', 'TEMPERATA' => 'temperată', 'CALDA' => 'caldă'][$tempTerm] ?? $tempTerm;

        $sol  = (int) round((float) ($in['umiditate_sol'] ?? 0));
        $aer  = (int) round((float) ($in['umiditate_aer'] ?? 0));
        $temp = number_format((float) ($in['temp_aer'] ?? 0), 1);

        $verdict = match ($decizie) {
            'nu_uda'    => 'condițiile nu cer udare acum',
            'uda_scurt' => "udare scurtă recomandată ({$durata}s)",
            'uda_mediu' => "udare medie recomandată ({$durata}s)",
            'uda_lung'  => "udare lungă recomandată ({$durata}s)",
            default     => "durată {$durata}s",
        };

        return "Sol {$tradSol} ({$sol}%), aer {$tradAer} ({$aer}%), temperatură {$tradTemp} ({$temp}°C) → {$verdict}.";
    }

    /** Returneaza eticheta termenului cu cea mai mare apartenenta. */
    private function dominant(array $memberships): string
    {
        $best = '';
        $bv   = -1.0;
        foreach ($memberships as $term => $val) {
            if ($val > $bv) {
                $bv = $val;
                $best = $term;
            }
        }
        return $best;
    }
}
