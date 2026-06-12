<?php

declare(strict_types=1);

// ------------------------------------------------------------
//  Diagnostic prognoza meteo (OpenWeatherMap).
//  Rulare:  php scripts/test_weather.php
//  Arata exact ce raspunde API-ul (status + corp) si rezultatul
//  parsat de WeatherService, ca sa vezi de ce nu merge.
// ------------------------------------------------------------

require_once dirname(__DIR__) . '/vendor/autoload.php';

Dotenv\Dotenv::createImmutable(dirname(__DIR__))->safeLoad();

use App\Services\WeatherService;
use GuzzleHttp\Client;

$cfg  = require dirname(__DIR__) . '/config/app.php';
$key  = (string) ($cfg['weather']['api_key'] ?? '');
$city = (string) ($cfg['weather']['city'] ?? 'Timisoara,RO');

fwrite(STDOUT, "Oras configurat : {$city}\n");
fwrite(STDOUT, 'Cheie API       : ' . ($key === '' ? 'LIPSESTE (adauga OPENWEATHER_API_KEY in .env)' : '••• ' . substr($key, -4)) . "\n");

if ($key === '') {
    fwrite(STDOUT, "\n=> Adauga in .env:\n   OPENWEATHER_API_KEY=cheia_ta\n   OPENWEATHER_CITY=Timisoara,RO\n");
    exit(1);
}

// 1) Apel BRUT, ca sa vedem statusul real (401 = cheie invalida/neactivata, 404 = oras gresit)
fwrite(STDOUT, "\n--- Apel direct OpenWeatherMap ---\n");
try {
    $client = new Client(['timeout' => 8, 'http_errors' => false]);
    $res = $client->get('https://api.openweathermap.org/data/2.5/forecast', [
        'query' => ['q' => $city, 'appid' => $key, 'units' => 'metric', 'lang' => 'ro', 'cnt' => 8],
    ]);
    $status = $res->getStatusCode();
    $body   = (string) $res->getBody();
    fwrite(STDOUT, "HTTP {$status}\n");
    if ($status !== 200) {
        fwrite(STDOUT, substr($body, 0, 300) . "\n");
        if ($status === 401) {
            fwrite(STDOUT, "\n401 = cheie invalida SAU inca neactivata (cheile noi se activeaza in ~10 min - 2h).\n");
        } elseif ($status === 404) {
            fwrite(STDOUT, "\n404 = oras negasit. Incearca OPENWEATHER_CITY=Timisoara,RO sau alt format.\n");
        }
        exit(1);
    }
    $data = json_decode($body, true);
    fwrite(STDOUT, 'Oras returnat   : ' . ($data['city']['name'] ?? '?') . "\n");
    fwrite(STDOUT, 'Sloturi (3h)    : ' . count($data['list'] ?? []) . "\n");
} catch (Throwable $e) {
    fwrite(STDOUT, 'EROARE retea: ' . $e->getMessage() . "\n");
    exit(1);
}

// 2) Ce returneaza serviciul aplicatiei (folosit de /api/v1/dashboard/snapshot)
fwrite(STDOUT, "\n--- WeatherService::forecast() ---\n");
$out = WeatherService::fromConfig()->forecast();
fwrite(STDOUT, json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");
fwrite(STDOUT, "\n" . ($out['available'] ? 'OK — cardul meteo va functiona.' : 'Inca indisponibil — vezi mesajele de mai sus.') . "\n");
