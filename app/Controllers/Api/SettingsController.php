<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Request;
use App\Core\Response;
use App\Models\Settings;

final class SettingsController extends ApiController
{
    /**
     * POST /api/v1/settings — dashboard actualizeaza praguri / mod automat.
     * Accepta orice subset al campurilor permise (white-list in Settings::FIELDS):
     *   prag_umiditate_aer, interval_minim_udare, mod_automat,
     *   prag_sol_uscat, prag_tds_maxim, prag_temp_apa_min, prag_temp_apa_max.
     */
    public function update(Request $request): void
    {
        $accepted = [
            'prag_umiditate_aer',
            'interval_minim_udare',
            'mod_automat',
            'prag_sol_uscat',
            'prag_tds_maxim',
            'prag_temp_apa_min',
            'prag_temp_apa_max',
            'prag_turbiditate_maxim',
        ];

        $body    = $request->all();
        $changes = [];
        foreach ($accepted as $name) {
            if (array_key_exists($name, $body)) {
                $changes[$name] = $body[$name];
            }
        }

        Response::success(Settings::update($changes));
    }
}
