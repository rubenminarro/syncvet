<?php

/*
 * Datos de demostración (DemoSeeder, entrega 0.4). Solo en local y testing.
 *
 * DEMO_PASSWORD: contraseña de todos los usuarios de demostración. No tiene valor
 * por defecto: sin ella el DemoSeeder se niega a correr. Debe cumplir la regla de
 * contraseñas (R-009: al menos 12 caracteres, con mayúsculas, minúsculas, números
 * y símbolos, y que no aparezca en filtraciones conocidas).
 */

return [
    'password' => env('DEMO_PASSWORD'),
];
