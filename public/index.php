<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// -----------------------------
// Modo mantenimiento
// -----------------------------
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// -----------------------------
// Autoload Composer
// -----------------------------
require __DIR__.'/../vendor/autoload.php';

// -----------------------------
// Bootstrap Laravel
// -----------------------------
$app = require_once __DIR__.'/../bootstrap/app.php';

// -----------------------------
// Kernel HTTP
// -----------------------------
$kernel = $app->make(Kernel::class);

// -----------------------------
// Capturar la petición
// -----------------------------
$request = Request::capture();

// -----------------------------
// Forzar HTTPS si no lo hay (opcional pero recomendado en Railway)
// -----------------------------
if (!$request->isSecure() && env('APP_ENV') === 'production') {
    $uri = 'https://' . $request->getHttpHost() . $request->getRequestUri();
    header('Location: ' . $uri, true, 301);
    exit;
}

// -----------------------------
// Manejar la petición con Kernel
// -----------------------------
$response = $kernel->handle($request);

// -----------------------------
// Enviar respuesta al navegador
// -----------------------------
$response->send();

// -----------------------------
// Terminar petición
// -----------------------------
$kernel->terminate($request, $response);
