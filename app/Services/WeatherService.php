<?php

declare(strict_types=1);

namespace App\Services;

use GuzzleHttp\Client;
use Throwable;

/**
 * Prognoza meteo (OpenWeatherMap) — folosita ca intrare in fuzzy si pe dashboard.
 * Degradare gratioasa: daca lipseste cheia sau API-ul cade, returneaza null
 * la "rain_24h" (necunoscut), fara sa arunce exceptii in UI.
 */
final class WeatherService
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $city
    ) {}

    /** Construire din config/app.php. */
    public static function fromConfig(): self
    {
        $cfg = require dirname(__DIR__, 2) . '/config/app.php';
        return new self($cfg['weather']['api_key'] ?? '', $cfg['weather']['city'] ?? 'Timisoara,RO');
    }

    /**
     * @return array{available:bool, rain_24h:?bool, prob_3h:float, temp:?float, descriere:?string, oras:string}
     */
    public function forecast(): array
    {
        $out = [
            'available' => false,
            'rain_24h'  => null,
            'prob_3h'   => 0.0,   // probabilitate ploaie in urmatoarele 3h (0.0..1.0)
            'temp'      => null,
            'descriere' => null,
            'oras'      => $this->city,
        ];

        if ($this->apiKey === '') {
            return $out;
        }

        try {
            $client = new Client(['timeout' => 6]);
            $res = $client->get('https://api.openweathermap.org/data/2.5/forecast', [
                'query' => [
                    'q'     => $this->city,
                    'appid' => $this->apiKey,
                    'units' => 'metric',
                    'lang'  => 'ro',
                    'cnt'   => 8, // 8 x 3h = 24h
                ],
            ]);

            $data = json_decode((string) $res->getBody(), true);
            $list = $data['list'] ?? [];

            if ($list === []) {
                return $out;
            }

            $rain = false;
            foreach ($list as $slot) {
                $pop = (float) ($slot['pop'] ?? 0);          // probabilitate precipitatii 0..1
                $vol = (float) ($slot['rain']['3h'] ?? 0);   // mm
                if ($pop >= 0.5 || $vol > 0.0) {
                    $rain = true;
                    break;
                }
            }

            $first = $list[0];
            $out['available'] = true;
            $out['rain_24h']  = $rain;
            $out['prob_3h']   = (float) ($first['pop'] ?? 0.0);
            $out['temp']      = isset($first['main']['temp']) ? round((float) $first['main']['temp'], 1) : null;
            $out['descriere'] = $first['weather'][0]['description'] ?? null;

            return $out;
        } catch (Throwable) {
            return $out;
        }
    }
}
