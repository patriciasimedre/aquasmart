<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Request;
use App\Core\Response;
use App\Models\Command;
use App\Models\IrrigationEvent;
use App\Models\SensorReading;

final class CommandsController extends ApiController
{
    /** POST /api/v1/commands — dashboard emite o comanda manuala. */
    public function create(Request $request): void
    {
        $tip = (string) $request->input('tip', 'udare');
        if (!in_array($tip, ['udare', 'oprire'], true)) {
            Response::error('Tip comanda invalid (udare | oprire).', 422);
        }

        $durata = null;
        if ($tip === 'udare') {
            $durata = (int) $request->input('durata', 3);
            if (!in_array($durata, [2, 3, 5, 10, 15, 30, 60], true)) {
                Response::error('Durata invalida (2 | 3 | 5 | 10 | 15 | 30 | 60 secunde).', 422);
            }
        }

        // „Oprește" trebuie sa intrerupa orice udare deja in curs. Marcam udarile
        // pending/executing ca done („cancelled"), apoi cream comanda de oprire.
        // ESP32 va vedea doar oprirea la urmatorul poll si va opri pompa.
        if ($tip === 'oprire') {
            Command::cancelOpenWaterings();
        }

        Response::success(Command::create($tip, $durata), 201);
    }

    /** GET /api/v1/commands/next — ESP32 cere urmatoarea comanda. */
    public function next(Request $request): void
    {
        $this->requireEsp32($request);
        Response::success(Command::claimNext());
    }

    /** POST /api/v1/commands/{id}/ack — ESP32 confirma executia (sau refuzul). */
    public function ack(Request $request, string $id): void
    {
        $this->requireEsp32($request);

        $cmd = Command::find((int) $id);
        if ($cmd === []) {
            Response::error('Comanda inexistenta.', 404);
        }

        // ESP32 poate trimite {"refused":"motiv"} cand a refuzat comanda local
        // (ex. rezervor gol — protectie hardware pompa). In acest caz NU
        // inregistram un eveniment de irigare (n-a avut loc nicio udare).
        $refused = $request->input('refused');
        $refusedReason = is_string($refused) && $refused !== '' ? substr($refused, 0, 40) : null;

        $ok = Command::acknowledge((int) $id, $refusedReason);

        // Eveniment de irigare creat doar daca a fost o udare EXECUTATA cu succes.
        // Motivul = sursa comenzii (manual din UI / fuzzy din decizia automata),
        // ca raportul sa numere corect udarile pe categorii.
        if ($ok && ($cmd['tip'] ?? '') === 'udare' && $refusedReason === null) {
            $reading = SensorReading::latest();
            IrrigationEvent::create(
                (int) ($cmd['durata'] ?? 0),
                (string) ($cmd['sursa'] ?? 'manual'),
                $reading ? SensorReading::nivel($reading['nivel_sus'] ?? null, $reading['nivel_jos'] ?? null) : null,
                $reading && $reading['temp_aer'] !== null ? (float) $reading['temp_aer'] : null
            );
        }

        Response::success([
            'acknowledged' => $ok,
            'refused'      => $refusedReason,
        ]);
    }
}
