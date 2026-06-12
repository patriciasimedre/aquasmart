<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Request;
use App\Core\Response;

/** Baza pentru controllerele API: autentificarea ESP32 prin Bearer token. */
abstract class ApiController
{
    /** Cheia ESP32 din config (poate fi goala in dev). */
    protected function esp32Key(): string
    {
        $cfg = require dirname(__DIR__, 3) . '/config/app.php';
        return (string) ($cfg['api']['esp32_key'] ?? '');
    }

    /**
     * Verifica Bearer token-ul ESP32. Daca nu corespunde, raspunde 401 si opreste.
     * In dev (cheie negolita necompletata) lasa sa treaca, ca sa poti testa firmware-ul.
     */
    protected function requireEsp32(Request $request): void
    {
        $key = $this->esp32Key();
        if ($key === '') {
            return; // dev: ESP32_API_KEY nesetat
        }
        if (!hash_equals($key, (string) $request->bearerToken())) {
            Response::error('Token ESP32 invalid.', 401);
        }
    }
}
