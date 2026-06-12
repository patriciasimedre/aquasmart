<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Request;
use App\Core\Response;
use App\Services\IrrigationDecision;

final class FuzzyController extends ApiController
{
    /**
     * GET /api/v1/fuzzy/preview — decizie fuzzy curenta (dry-run, fara comanda).
     * Folosit de pagina Control ca sa arate ce ar decide sistemul si de ce.
     */
    public function preview(Request $request): void
    {
        Response::success((new IrrigationDecision())->evaluate(false));
    }
}
