<?php

declare(strict_types=1);

namespace App\Services;

use GuzzleHttp\Client;
use Throwable;

/**
 * Genereaza un raport text despre o perioada.
 *  - cu ANTHROPIC_API_KEY -> rezumat generat de Claude
 *  - fara cheie / la eroare -> rezumat local determinist (fallback), clar marcat
 */
final class AiReportService
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $model
    ) {}

    public static function fromConfig(): self
    {
        $cfg = require dirname(__DIR__, 2) . '/config/app.php';
        return new self(
            $cfg['anthropic']['api_key'] ?? '',
            $cfg['anthropic']['model'] ?? 'claude-sonnet-4-6'
        );
    }

    /**
     * Genereaza raport pe baza de date agregate + context (profil + praguri + refuzuri).
     *
     * @param array      $readings  Citiri din sensor_readings pe perioada
     * @param array      $events    Udari din irrigation_events pe perioada
     * @param string     $start     Data start (ISO sau MySQL)
     * @param string     $end       Data end
     * @param array|null $context   ['profile' => ?array, 'settings' => array, 'refused' => array]
     *                              Daca null → raport fara context specific.
     * @return array{continut:string, model:string}
     */
    public function generate(array $readings, array $events, string $start, string $end, ?array $context = null): array
    {
        $stats = $this->aggregate($readings, $events);
        $ctx   = $context ?? [];

        if ($this->apiKey === '') {
            return [
                'continut' => $this->localReport($stats, $ctx, $start, $end, false),
                'model'    => 'local-fallback',
            ];
        }

        try {
            $client = new Client(['timeout' => 30]);
            $res = $client->post('https://api.anthropic.com/v1/messages', [
                'headers' => [
                    'x-api-key'         => $this->apiKey,
                    'anthropic-version' => '2023-06-01',
                    'content-type'      => 'application/json',
                ],
                'json' => [
                    'model'      => $this->model,
                    'max_tokens' => 1024,
                    'messages'   => [[
                        'role'    => 'user',
                        'content' => $this->prompt($stats, $ctx, $start, $end),
                    ]],
                ],
            ]);

            $data = json_decode((string) $res->getBody(), true);
            $text = $data['content'][0]['text'] ?? '';

            if (trim($text) === '') {
                throw new \RuntimeException('Raspuns gol de la Claude.');
            }

            return ['continut' => trim($text), 'model' => $this->model];
        } catch (Throwable $e) {
            return [
                'continut' => $this->localReport($stats, $ctx, $start, $end, true)
                    . "\n\n_(Generarea AI a esuat: " . $e->getMessage() . ")_",
                'model'    => 'local-fallback',
            ];
        }
    }

    private function aggregate(array $readings, array $events): array
    {
        $n = count($readings);

        // Acumulatori pe TOATE intrarile fuzzy + min/max pentru variatie.
        // Folosim arrays separate ca sa calculam min/max corect (sariem NULL).
        $sumTemp  = $sumUm  = $sumTds = $sumSol = $sumApa = $sumTurb = $sumNivel = 0.0;
        $cntTemp  = $cntUm  = $cntTds = $cntSol = $cntApa = $cntTurb = $cntNivel = 0;
        $minSol = $maxSol = null;
        $minNivel = $maxNivel = null;
        $rain = 0;

        foreach ($readings as $r) {
            if (isset($r['temp_aer']) && $r['temp_aer'] !== null) {
                $sumTemp += (float) $r['temp_aer']; $cntTemp++;
            }
            if (isset($r['umiditate_aer']) && $r['umiditate_aer'] !== null) {
                $sumUm += (float) $r['umiditate_aer']; $cntUm++;
            }
            if (isset($r['tds']) && $r['tds'] !== null) {
                $sumTds += (float) $r['tds']; $cntTds++;
            }
            // Umiditate sol — INTRAREA PRINCIPALA fuzzy
            if (isset($r['umiditate_sol']) && $r['umiditate_sol'] !== null) {
                $v = (float) $r['umiditate_sol'];
                $sumSol += $v; $cntSol++;
                $minSol = $minSol === null ? $v : min($minSol, $v);
                $maxSol = $maxSol === null ? $v : max($maxSol, $v);
            }
            if (isset($r['temp_apa']) && $r['temp_apa'] !== null) {
                $sumApa += (float) $r['temp_apa']; $cntApa++;
            }
            // Turbiditate (TS-300B) si nivel rezervor
            if (isset($r['turbiditate']) && $r['turbiditate'] !== null) {
                $sumTurb += (float) $r['turbiditate']; $cntTurb++;
            }
            if (isset($r['nivel_cm']) && $r['nivel_cm'] !== null) {
                $v = (float) $r['nivel_cm'];
                $sumNivel += $v; $cntNivel++;
                $minNivel = $minNivel === null ? $v : min($minNivel, $v);
                $maxNivel = $maxNivel === null ? $v : max($maxNivel, $v);
            }
            $rain += !empty($r['ploaie']) ? 1 : 0;
        }

        $totalSec = 0;
        $perMotiv = ['manual' => 0, 'fuzzy' => 0, 'programat' => 0];
        foreach ($events as $e) {
            $totalSec += (int) ($e['durata_secunde'] ?? 0);
            $m = $e['motiv'] ?? 'manual';
            $perMotiv[$m] = ($perMotiv[$m] ?? 0) + 1;
        }

        return [
            'n_readings'  => $n,
            // Senzori aer/apă
            'avg_temp'    => $cntTemp ? round($sumTemp / $cntTemp, 1) : null,
            'avg_um'      => $cntUm   ? round($sumUm   / $cntUm,   1) : null,
            'avg_apa'     => $cntApa  ? round($sumApa  / $cntApa,  1) : null,
            // Sol — intrarea principala fuzzy, cu min/max ca sa vedem variatia
            'avg_sol'     => $cntSol  ? round($sumSol  / $cntSol)     : null,
            'min_sol'     => $minSol  !== null ? (int) round($minSol) : null,
            'max_sol'     => $maxSol  !== null ? (int) round($maxSol) : null,
            // Calitate apa
            'avg_tds'     => $cntTds  ? round($sumTds  / $cntTds)     : null,
            'avg_turb'    => $cntTurb ? round($sumTurb / $cntTurb)    : null,
            // Rezervor
            'avg_nivel'   => $cntNivel ? round($sumNivel / $cntNivel, 1) : null,
            'min_nivel'   => $minNivel,  // cm (mic = plin)
            'max_nivel'   => $maxNivel,  // cm (mare = gol)
            // Ploaie
            'rain_pct'    => $n ? round($rain * 100 / $n) : 0,
            // Udari
            'n_events'    => count($events),
            'total_sec'   => $totalSec,
            'per_motiv'   => $perMotiv,
        ];
    }

    private function prompt(array $s, array $ctx, string $start, string $end): string
    {
        $profile  = $ctx['profile']  ?? null;
        $settings = $ctx['settings'] ?? [];
        $refused  = $ctx['refused']  ?? [];

        // CONTEXT — profilul activ defineste tipul plantei si plafonul de udare.
        $profileTxt = $profile
            ? "Profil plantă activ: **{$profile['nume']}** "
              . "(sol_min={$profile['sol_min']}%, interval_min={$profile['interval_min']}min, "
              . "durata_max={$profile['durata_max']}s)"
            : "Profil plantă: necunoscut";

        // Praguri configurate de utilizator
        $pragSol  = $settings['prag_sol_uscat']         ?? '?';
        $pragTds  = $settings['prag_tds_maxim']         ?? '?';
        $pragTurb = $settings['prag_turbiditate_maxim'] ?? '?';
        $intvMin  = $settings['interval_minim_udare']   ?? '?';

        // Comenzi refuzate de firmware (rezervor_gol, apa_tulbure, etc.)
        $totalRef = array_sum($refused);
        $refusedTxt = $totalRef === 0
            ? "Comenzi refuzate: 0 (sistem fără probleme)"
            : "Comenzi REFUZATE: {$totalRef}\n" .
              implode("\n", array_map(
                  fn ($motiv, $n) => "  • {$motiv}: {$n}",
                  array_keys($refused), array_values($refused)
              ));

        $durariMin = intdiv($s['total_sec'], 60);
        $durariSec = $s['total_sec'] % 60;

        return <<<PROMPT
Esti asistent expert pentru AquaSmart, un sistem IoT de irigare a plantelor cu apa de ploaie.
Scrie un raport CONCRET in romana, max 250 cuvinte, format markdown cu sectiuni.

# Context

{$profileTxt}
Perioada analizata: {$start} → {$end}
Citiri inregistrate: {$s['n_readings']}

# Senzori (medii pe perioada)

- **Umiditate sol**: avg {$s['avg_sol']}% (min {$s['min_sol']}%, max {$s['max_sol']}%) — prag uscat: {$pragSol}%
- Temperatura aer: {$s['avg_temp']} °C
- Umiditate aer: {$s['avg_um']} %
- Temperatura apa: {$s['avg_apa']} °C
- TDS: {$s['avg_tds']} ppm (prag: {$pragTds})
- Turbiditate: {$s['avg_turb']} NTU (prag: {$pragTurb})
- Nivel rezervor: avg {$s['avg_nivel']} cm (min {$s['min_nivel']} cm = mai plin, max {$s['max_nivel']} cm = mai gol)
- Citiri cu ploaie activa: {$s['rain_pct']}%

# Udari (efectiv executate)

- Total: {$s['n_events']} udari, durata cumulata {$durariMin}m {$durariSec}s
- Pe motiv: manual={$s['per_motiv']['manual']}, fuzzy={$s['per_motiv']['fuzzy']}, programat={$s['per_motiv']['programat']}

# Comenzi neexecutate

{$refusedTxt}

# Cere

Scrie raportul in 4 sectiuni cu markdown headings:

## Stare generala
Una-doua propozitii: cum se descurca planta acum, daca pragurile sunt potrivite pentru profilul {profile.nume}.

## Anomalii detectate
Eventuale probleme: sol mereu uscat, TDS in crestere, rezervor frecvent gol, comenzi refuzate repetate, apa rece etc.
Daca nimic atipic, scrie "Sistem stabil — fără anomalii".

## Recomandari concrete
3-5 actiuni SPECIFICE profilului activ. Exemple:
- "Reduceti pragul sol uscat de la X% la Y% pentru a uda mai frecvent"
- "Curatati senzorul TS-300B (turbiditate medie ridicata sugereaza biofilm)"
- "Adaugati apa proaspata in rezervor — TDS-ul mediu indica concentrare prin evaporare"

## Eficienta apei de ploaie
Una-doua propozitii: cat de mult s-a folosit ploaia? Rezervorul s-a reumplut natural?

Foloseste tonul prietenos dar concret, ca un specialist in horticultura digitala.
PROMPT;
    }

    private function localReport(array $s, array $ctx, string $start, string $end, bool $afterError): string
    {
        $profile = $ctx['profile'] ?? null;
        $refused = $ctx['refused'] ?? [];

        $min = intdiv($s['total_sec'], 60);
        $sec = $s['total_sec'] % 60;

        $lines = [];
        $lines[] = "# Raport AquaSmart";
        $lines[] = "**Perioada:** {$start} → {$end}";
        if ($profile) {
            $lines[] = "**Profil plantă:** {$profile['nume']}"
                . " (sol_min={$profile['sol_min']}%, interval={$profile['interval_min']}min, durata_max={$profile['durata_max']}s)";
        }
        $lines[] = '';

        $lines[] = "## Sumar senzori";
        $lines[] = "- Citiri inregistrate: **{$s['n_readings']}**";
        $lines[] = "- Umiditate sol: **{$s['avg_sol']}%** (min {$s['min_sol']}%, max {$s['max_sol']}%)";
        $lines[] = "- Temperatura aer: **{$s['avg_temp']} °C**";
        $lines[] = "- Umiditate aer: **{$s['avg_um']} %**";
        $lines[] = "- Temperatura apa: **{$s['avg_apa']} °C**";
        $lines[] = "- TDS: **{$s['avg_tds']} ppm**";
        $lines[] = "- Turbiditate: **{$s['avg_turb']} NTU**";
        $lines[] = "- Nivel rezervor: **{$s['avg_nivel']} cm** (min {$s['min_nivel']} cm, max {$s['max_nivel']} cm)";
        $lines[] = "- Citiri cu ploaie: **{$s['rain_pct']}%**";
        $lines[] = '';

        $lines[] = "## Irigare";
        $lines[] = "- Total udari executate: **{$s['n_events']}** "
            . "(manual {$s['per_motiv']['manual']}, fuzzy {$s['per_motiv']['fuzzy']}, "
            . "programat {$s['per_motiv']['programat']})";
        $lines[] = "- Timp total udare: **{$min}m {$sec}s**";
        if (!empty($refused)) {
            $totalRef = array_sum($refused);
            $lines[] = "- Comenzi REFUZATE de firmware: **{$totalRef}**";
            foreach ($refused as $motiv => $n) {
                $lines[] = "  - {$motiv}: {$n}";
            }
        }
        $lines[] = '';

        $lines[] = "## Observatii";
        $lines[] = $s['rain_pct'] >= 30
            ? "- Ploaie frecventa — rezervorul a beneficiat de reumplere naturala; udarile pot fi reduse."
            : "- Putina ploaie — monitorizeaza nivelul rezervorului ca sa nu ramana gol.";
        if ($s['avg_um'] !== null) {
            $lines[] = $s['avg_um'] < 45
                ? "- Umiditate aer scazuta — evapotranspiratie crescuta, posibil necesare udari mai dese."
                : "- Umiditate aer in limite rezonabile.";
        }
        if ($s['avg_turb'] !== null && $s['avg_turb'] > 50) {
            $lines[] = "- Turbiditate medie ridicata (**{$s['avg_turb']} NTU**) — verifica daca s-a depus mal in rezervor.";
        }
        if ($s['max_nivel'] !== null && $s['max_nivel'] > 25) {
            $lines[] = "- Rezervorul a atins niveluri foarte mici (max {$s['max_nivel']} cm distanta) — completeaza apa.";
        }
        $lines[] = '';

        $lines[] = $afterError
            ? "_Raport generat local (fallback)._"
            : "_Raport generat local (fara cheie Claude — adauga ANTHROPIC_API_KEY pentru raport AI)._";

        return implode("\n", $lines);
    }
}
