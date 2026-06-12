<?php

declare(strict_types=1);

// Configurare aplicatie. Citeste variabile din .env (incarcate cu vlucas/phpdotenv)
// sau din environment-ul serverului (Azure App Service -> Application settings).

return [
    'name'  => $_ENV['APP_NAME'] ?? 'AquaSmart',
    'env'   => $_ENV['APP_ENV'] ?? 'production',
    'debug' => filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN),
    'url'   => $_ENV['APP_URL'] ?? 'http://localhost:8080',

    // Sesiuni PHP — folosite de dashboard. ESP32 foloseste Bearer token, nu sesiuni.
    'session' => [
        'lifetime'        => 7200, // 2 ore
        'cookie_secure'   => ($_ENV['APP_ENV'] ?? 'production') === 'production',
        'cookie_httponly' => true,
        'cookie_samesite' => 'Strict',
    ],

    // API ESP32 <-> backend
    'api' => [
        'esp32_key'        => $_ENV['ESP32_API_KEY'] ?? '',
        'polling_interval' => 5, // secunde recomandate pt ESP32 GET /commands/next
    ],

    // OpenWeatherMap — prognoza ploaie 24h (intrare in fuzzy)
    'weather' => [
        'api_key' => $_ENV['OPENWEATHER_API_KEY'] ?? '',
        'city'    => $_ENV['OPENWEATHER_CITY'] ?? 'Timisoara,RO',
    ],

    // Claude API — raport AI generat la cerere (optional)
    'anthropic' => [
        'api_key' => $_ENV['ANTHROPIC_API_KEY'] ?? '',
        'model'   => $_ENV['ANTHROPIC_MODEL'] ?? 'claude-sonnet-4-6',
    ],
];
