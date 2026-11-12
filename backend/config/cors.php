<?php

/*
|--------------------------------------------------------------------------
| CORS (entrega front-00, B2; R-058)
|--------------------------------------------------------------------------
|
| Solo los orígenes de CORS_ALLOWED_ORIGINS, separados por comas. Cada uno tiene que ser
| un origen exacto, esquema://host[:puerto], y se normaliza como lo manda el navegador
| (en minúsculas, sin la barra final). Lo demás se descarta (App\Support\CorsOrigins), en
| particular todo lo que tenga un "*": php-cors lo tomaría como "cualquier origen" o como
| un patrón. AppServiceProvider avisa los descartados en el log, una vez por hora. Sin la
| variable, o vacía, ningún origen. En desarrollo el proxy de Vite manda /api al backend
| desde el mismo origen, sin CORS.
|
| Sin sanctum/csrf-cookie y sin credenciales: la API usa tokens Bearer (las cookies
| de Sanctum quedan para la FASE 10). Retry-After se expone para que el frontend
| muestre la cuenta regresiva de un 429 aunque esté en otro origen.
|
*/

$corsOrigins = App\Support\CorsOrigins::parse(env('CORS_ALLOWED_ORIGINS'));

return [

    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => $corsOrigins['allowed'],

    // Propia (php-cors no la lee): lo que se descartó de la variable, para el aviso del log.
    'discarded_origins' => $corsOrigins['discarded'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => ['Retry-After'],

    'max_age' => 600,

    'supports_credentials' => false,

];
