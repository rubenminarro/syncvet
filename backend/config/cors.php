<?php

/*
|--------------------------------------------------------------------------
| CORS (entrega front-00, B2; R-058)
|--------------------------------------------------------------------------
|
| Solo los orígenes de CORS_ALLOWED_ORIGINS, separados por comas (sin los espacios
| ni los vacíos). Sin la variable, o vacía, ningún origen: nunca el comodín. Un "*" de
| la variable se descarta (decisión del usuario): Laravel lo tomaría como "cualquier
| origen". AppServiceProvider avisa en el log con wildcard_discarded. En desarrollo el
| proxy de Vite manda /api al backend desde el mismo origen, sin CORS.
|
| Sin sanctum/csrf-cookie y sin credenciales: la API usa tokens Bearer (las cookies
| de Sanctum quedan para la FASE 10). Retry-After se expone para que el frontend
| muestre la cuenta regresiva de un 429 aunque esté en otro origen.
|
*/

$corsOrigins = array_values(array_filter(
    array_map('trim', explode(',', (string) env('CORS_ALLOWED_ORIGINS', ''))),
    fn (string $origin): bool => $origin !== '',
));

return [

    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_filter($corsOrigins, fn (string $origin): bool => $origin !== '*')),

    // Propia (php-cors no la lee): la variable traía "*" y se descartó.
    'wildcard_discarded' => in_array('*', $corsOrigins, true),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => ['Retry-After'],

    'max_age' => 600,

    'supports_credentials' => false,

];
