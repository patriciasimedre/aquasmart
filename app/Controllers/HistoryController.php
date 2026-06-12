<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Models\AiReport;

final class HistoryController
{
    /** GET /istoric — grafice + tabel evenimente de udare. */
    public function index(Request $request): void
    {
        Response::view('pages.istoric', [
            'title'  => 'Istoric',
            'active' => 'istoric',
        ]);
    }

    /** GET /raport — generare la cerere a raportului AI. */
    public function report(Request $request): void
    {
        Response::view('pages.raport', [
            'title'   => 'Rapoarte AI',
            'active'  => 'raport',
            'recente' => AiReport::latest(5),
        ]);
    }
}
