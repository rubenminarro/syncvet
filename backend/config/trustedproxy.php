<?php

/*
 * Proxies de confianza (balanceador, proxy inverso). Solo de ellos se aceptan los
 * encabezados X-Forwarded-*: la IP del cliente sale de ahí, y con ella el límite de
 * intentos del login y la IP de la auditoría.
 *
 * TRUSTED_PROXIES: vacía (por defecto) = ninguno; una lista de IP o rangos CIDR
 * separados por coma; o "*" = cualquiera (solo si la aplicación no es accesible
 * sin pasar por el proxy). Ver docs/04-desarrollo/deployment.md.
 *
 * Vacía da una lista vacía y no null: con null, Laravel confía en todos cuando el
 * encabezado Host termina en .on-forge.com o .on-vapor.com, y ese encabezado lo
 * manda el cliente.
 */

$proxies = trim((string) env('TRUSTED_PROXIES', ''));

return [
    'proxies' => in_array($proxies, ['*', '**'], true)
        ? $proxies
        : array_values(array_filter(array_map('trim', explode(',', $proxies)), fn (string $ip) => $ip !== '')),
];
