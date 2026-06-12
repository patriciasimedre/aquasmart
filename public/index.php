<?php

declare(strict_types=1);

// ------------------------------------------------------------
//  Front controller AquaSmart — bootstrap + routing.
// ------------------------------------------------------------

// In dev (php -S ... public/index.php) lasa serverul sa serveasca
// direct fisierele statice existente (css/js/img). Pe Apache/Azure
// rescrierea o face .htaccess, deci acest bloc nu se aplica.
if (PHP_SAPI === 'cli-server') {
    $static = __DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if (is_file($static)) {
        return false;
    }
}

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/app/Support/icons.php';

// .env local; pe Azure App Service variabilele vin din Application settings.
Dotenv\Dotenv::createImmutable(dirname(__DIR__))->safeLoad();

$app = require dirname(__DIR__) . '/config/app.php';

if ($app['debug']) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}

session_set_cookie_params([
    'lifetime' => $app['session']['lifetime'],
    'path'     => '/',
    'secure'   => $app['session']['cookie_secure'],
    'httponly' => $app['session']['cookie_httponly'],
    'samesite' => $app['session']['cookie_samesite'],
]);
session_start();

use App\Core\Router;
use App\Core\Request;
use App\Core\Response;
use App\Controllers\DashboardController;
use App\Controllers\HistoryController;
use App\Controllers\Api\ReadingsController;
use App\Controllers\Api\CommandsController;
use App\Controllers\Api\SettingsController;
use App\Controllers\Api\HeartbeatController;
use App\Controllers\Api\AiReportController;
use App\Controllers\Api\FuzzyController;
use App\Controllers\Api\ProfilesController;

$router  = new Router();
$request = new Request();

// ---------- Pagini (dashboard) ----------
$router->get('/',         [DashboardController::class, 'index']);
$router->get('/control',  [DashboardController::class, 'control']);
$router->get('/istoric',  [HistoryController::class,  'index']);
$router->get('/raport',   [HistoryController::class,  'report']);
$router->get('/despre',   [DashboardController::class, 'despre']);

// ---------- API ESP32 (Bearer ESP32_API_KEY) ----------
$router->post('/api/v1/readings',          [ReadingsController::class,  'store']);
$router->get ('/api/v1/commands/next',     [CommandsController::class,  'next']);
$router->post('/api/v1/commands/{id}/ack', [CommandsController::class,  'ack']);
$router->post('/api/v1/heartbeat',         [HeartbeatController::class, 'beat']);

// ---------- API Dashboard ----------
$router->get ('/api/v1/dashboard/snapshot', [ReadingsController::class,  'snapshot']);
$router->get ('/api/v1/history',            [ReadingsController::class,  'history']);
$router->post('/api/v1/commands',           [CommandsController::class,  'create']);
$router->post('/api/v1/settings',           [SettingsController::class,  'update']);
$router->post('/api/v1/ai/report',          [AiReportController::class,  'generate']);
$router->get ('/api/v1/fuzzy/preview',       [FuzzyController::class,     'preview']);
$router->get ('/api/v1/profiles',            [ProfilesController::class,  'index']);
$router->post('/api/v1/profiles/activate',   [ProfilesController::class,  'activate']);

// ---------- Healthcheck (Azure / test rapid) ----------
$router->get('/api/v1/health', static function () use ($app): void {
    Response::success([
        'app'    => $app['name'],
        'env'    => $app['env'],
        'status' => 'ok',
        'time'   => date('c'),
    ]);
});

$router->dispatch($request);
