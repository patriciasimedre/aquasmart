<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Request;
use App\Core\Response;

final class HeartbeatController extends ApiController
{
    /** POST /api/v1/heartbeat — ESP32 semnaleaza ca e online. */
    public function beat(Request $request): void
    {
        $this->requireEsp32($request);

        Response::success([
            'pong'        => true,
            'server_time' => date('c'),
        ]);
    }
}
