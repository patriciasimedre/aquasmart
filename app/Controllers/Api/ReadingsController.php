<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Request;
use App\Core\Response;
use App\Models\Command;
use App\Models\IrrigationEvent;
use App\Models\PlantProfile;
use App\Models\SensorReading;
use App\Models\Settings;
use App\Services\IrrigationDecision;
use App\Services\WeatherService;

final class ReadingsController extends ApiController
{
    /** POST /api/v1/readings — ESP32 trimite o citire. */
    public function store(Request $request): void
    {
        $this->requireEsp32($request);

        $id = SensorReading::create([
            'temp_apa'      => $request->input('temp_apa'),
            'temp_aer'      => $request->input('temp_aer'),
            'umiditate_aer' => $request->input('umiditate_aer'),
            'umiditate_sol' => $request->input('umiditate_sol'),
            'tds'           => $request->input('tds'),
            'turbiditate'   => $request->input('turbiditate'),
            'ploaie'        => $request->input('ploaie'),
            'nivel_sus'     => $request->input('nivel_sus'),
            'nivel_jos'     => $request->input('nivel_jos'),
            'nivel_cm'      => $request->input('nivel_cm'),
        ]);

        // Dupa fiecare citire, re-evalueaza fuzzy si emite comanda daca e cazul.
        // Esecul deciziei nu trebuie sa blocheze salvarea citirii.
        $decizie = null;
        try {
            $d = (new IrrigationDecision())->evaluate(true);
            $decizie = [
                'gate'          => $d['gate'],
                'durata_finala' => $d['durata_finala'],
                'actiune'       => $d['actiune'],
            ];
        } catch (\Throwable $e) {
            $decizie = ['eroare' => $e->getMessage()];
        }

        Response::success(['id' => $id, 'decizie' => $decizie], 201);
    }

    /** GET /api/v1/dashboard/snapshot — date live pentru dashboard. */
    public function snapshot(Request $request): void
    {
        $reading  = SensorReading::latest();
        $settings = Settings::get();
        $event    = IrrigationEvent::last();

        $online   = false;
        $lastSeen = null;
        $age      = null;
        if ($reading !== null) {
            $lastSeen = $reading['timestamp'];
            $age      = max(0, time() - strtotime((string) $reading['timestamp']));
            $online   = $age <= 600; // 10 min
            // Derivare nivel: prefera HC-SR04 (nivel_cm), fallback pe float switches.
            $reading['nivel'] = SensorReading::nivelFromRow($reading);
        }

        // Profilul activ + pragul de sol uscat sunt necesare in frontend
        // pentru pre-check inainte de udarea manuala (alerta „solul nu are nevoie").
        $profile = PlantProfile::active();

        Response::success([
            'reading'  => $reading,
            'settings' => [
                'mod_automat'          => (int) $settings['mod_automat'],
                'prag_umiditate_aer'   => (float) $settings['prag_umiditate_aer'],
                'interval_minim_udare' => (int) $settings['interval_minim_udare'],
                'prag_sol_uscat'       => (int) ($settings['prag_sol_uscat'] ?? 30),
            ],
            'active_profile' => $profile ? [
                'id'          => (int) $profile['id'],
                'nume'        => $profile['nume'],
                'durata_max'  => (int) $profile['durata_max'],
                'sol_min'     => (int) $profile['sol_min'],
            ] : null,
            'last_event'   => $event,
            'command'      => Command::currentOpen(),
            // Ultima comanda refuzata local de ESP32 in ultimele 10 min — banner UI.
            'last_refused' => Command::lastRefused(10),
            'weather'      => WeatherService::fromConfig()->forecast(),
            'system'       => ['online' => $online, 'last_seen' => $lastSeen, 'last_seen_age' => $age],
        ]);
    }

    /** GET /api/v1/history?range=24h|7d|30d — date pentru grafice + tabel udari. */
    public function history(Request $request): void
    {
        $range = (string) $request->query('range', '24h');
        if (!in_array($range, ['24h', '7d', '30d'], true)) {
            $range = '24h';
        }

        $points = array_map(static function (array $r): array {
            return [
                't'             => $r['timestamp'],
                'temp_aer'      => $r['temp_aer'] !== null ? (float) $r['temp_aer'] : null,
                'temp_apa'      => $r['temp_apa'] !== null ? (float) $r['temp_apa'] : null,
                'umiditate_aer' => $r['umiditate_aer'] !== null ? (float) $r['umiditate_aer'] : null,
                'umiditate_sol' => isset($r['umiditate_sol']) && $r['umiditate_sol'] !== null
                    ? (int) $r['umiditate_sol'] : null,
                'tds'           => $r['tds'] !== null ? (int) $r['tds'] : null,
                'turbiditate'   => isset($r['turbiditate']) && $r['turbiditate'] !== null
                    ? (int) $r['turbiditate'] : null,
                'nivel_cm'      => isset($r['nivel_cm']) && $r['nivel_cm'] !== null
                    ? (float) $r['nivel_cm'] : null,
                'nivel'         => SensorReading::nivelFromRow($r),
            ];
        }, SensorReading::history($range));

        Response::success([
            'range'  => $range,
            'points' => $points,
            'events' => IrrigationEvent::inRange($range),
        ]);
    }
}
