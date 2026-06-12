<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Models\AiReport;
use App\Models\IrrigationEvent;
use App\Models\PlantProfile;
use App\Models\SensorReading;
use App\Models\Settings;
use App\Services\AiReportService;

final class AiReportController extends ApiController
{
    /** POST /api/v1/ai/report — genereaza (sau ia din cache) un raport pe o perioada. */
    public function generate(Request $request): void
    {
        $range = (string) $request->input('range', '7d');
        if (!in_array($range, ['24h', '7d', '30d'], true)) {
            $range = '7d';
        }

        $days  = match ($range) { '24h' => 1, '30d' => 30, default => 7 };
        $end   = date('Y-m-d H:00:00');
        $start = date('Y-m-d H:00:00', strtotime("-{$days} days"));

        // Cache: acelasi interval (rotunjit la ora) -> raport deja generat.
        $cached = AiReport::findCached($start, $end);
        if ($cached !== null) {
            Response::success([
                'continut' => $cached['continut'],
                'model'    => $cached['model'],
                'cached'   => true,
                'perioada' => ['start' => $start, 'end' => $end],
            ]);
        }

        $readings = SensorReading::history($range);
        $events   = IrrigationEvent::inRange($range);

        // Context bogat pentru un raport relevant:
        //   - profilul activ (Cactus / Legume / etc.) -> recomandari specifice
        //   - praguri configurate -> referinta pentru "potrivit/nepotrivit"
        //   - comenzi refuzate per motiv -> semnal de anomalii (gol des, apa tulbure, etc.)
        $context = [
            'profile'  => PlantProfile::active(),
            'settings' => Settings::get(),
            'refused'  => $this->countRefusedCommands($start, $end),
        ];

        $report = AiReportService::fromConfig()->generate($readings, $events, $start, $end, $context);
        $saved  = AiReport::create($start, $end, $report['continut'], $report['model']);

        Response::success([
            'continut' => $saved['continut'],
            'model'    => $saved['model'],
            'cached'   => false,
            'perioada' => ['start' => $start, 'end' => $end],
        ]);
    }

    /**
     * Numara comenzile REFUZATE de firmware in perioada (rezervor_gol, apa_tulbure,
     * cancelled, etc.) grupate pe motiv. Folosit ca semnal de anomalii in raport.
     *
     * @return array<string,int>  ex: ['rezervor_gol' => 3, 'cancelled' => 1]
     */
    private function countRefusedCommands(string $start, string $end): array
    {
        $rows = Database::getInstance()->query(
            "SELECT refused_reason, COUNT(*) AS n
               FROM commands
              WHERE refused_reason IS NOT NULL
                AND ack_at BETWEEN :s AND :e
              GROUP BY refused_reason
              ORDER BY n DESC",
            ['s' => $start, 'e' => $end]
        )->fetchAll();

        $out = [];
        foreach ($rows as $r) {
            $out[(string) $r['refused_reason']] = (int) $r['n'];
        }
        return $out;
    }
}
