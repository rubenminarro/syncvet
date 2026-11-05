<?php

/*
 * Base SQLite de los tests. phpunit.xml la da relativa a la raíz del proyecto;
 * aquí se vuelve absoluta para que los tests no dependan de la carpeta desde la
 * que se corren (un IDE, `cd tests`), y se crea si no existe. RefreshDatabase la
 * migra y la siembra una vez por ejecución (migrate:fresh), así que no hace falta
 * borrarla.
 *
 * Solo con la conexión sqlite. Con otra (DB_CONNECTION=mysql DB_DATABASE=syncvet_test_mysql),
 * DB_DATABASE es el nombre de una base: no se toca ni se crea ningún archivo.
 */
$root = dirname(__DIR__);
$connection = $_ENV['DB_CONNECTION'] ?? (getenv('DB_CONNECTION') ?: 'sqlite');
$database = $_ENV['DB_DATABASE'] ?? (getenv('DB_DATABASE') ?: 'database/testing.sqlite');

if ($connection === 'sqlite' && $database !== ':memory:') {
    if (! preg_match('#^([a-zA-Z]:)?[\\\\/]#', $database)) {
        $database = $root.'/'.$database;
    }

    $_ENV['DB_DATABASE'] = $_SERVER['DB_DATABASE'] = $database;
    putenv("DB_DATABASE={$database}");

    if (! file_exists($database)) {
        touch($database);
    }
}

require $root.'/vendor/autoload.php';
