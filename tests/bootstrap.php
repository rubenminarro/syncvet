<?php

/*
 * Crea la base SQLite de los tests si no existe. RefreshDatabase la migra y la
 * siembra una vez por ejecución (migrate:fresh), así que no hace falta borrarla.
 */
$database = dirname(__DIR__).'/database/testing.sqlite';

if (! file_exists($database)) {
    touch($database);
}

require dirname(__DIR__).'/vendor/autoload.php';
