<?php

/**
 * Un pedido HTTP a la aplicación en un proceso aparte, con su propia conexión a la base
 * (FASE 3, entrega 3.2a: AppointmentConcurrencyTest). Imprime {"status": …, "body": …}.
 *
 * Uso: php tests/Support/concurrent-request.php <método> <uri> <token> <cuerpo JSON>
 *
 * El entorno (APP_ENV, DB_CONNECTION, DB_DATABASE…) lo fija el test que lo lanza.
 */

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

require __DIR__ . '/../../vendor/autoload.php';

$app = require __DIR__ . '/../../bootstrap/app.php';

[, $method, $uri, $token, $body] = $argv;

$kernel = $app->make(Kernel::class);
$request = Request::create($uri, $method, [], [], [], [
    'HTTP_AUTHORIZATION' => "Bearer {$token}",
    'HTTP_ACCEPT' => 'application/json',
    'CONTENT_TYPE' => 'application/json',
], $body);

$response = $kernel->handle($request);

fwrite(STDOUT, json_encode([
    'status' => $response->getStatusCode(),
    'body' => json_decode((string) $response->getContent(), true),
]));

$kernel->terminate($request, $response);
