<?php

namespace App\Support;

use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Event;

/**
 * Qué está ejecutando el proceso: un job, un comando o un pedido HTTP. Lo usa
 * ClinicContextMissingException para dejar en el log dónde se perdió la clínica.
 * Gana lo más interno: un job sync dentro de un comando informa el job.
 */
class ExecutionOrigin
{
    /**
     * Jobs en curso, por objeto (un job sync puede despachar otro dentro).
     *
     * @var array<int, string>
     */
    private static array $jobs = [];

    /**
     * Comandos en curso, en orden (Artisan::call puede correr uno dentro de otro).
     *
     * @var list<string>
     */
    private static array $commands = [];

    /**
     * Sigue los jobs y los comandos con sus eventos. Se llama al arrancar la
     * aplicación; empieza sin nada en curso.
     */
    public static function listen(): void
    {
        self::$jobs = [];
        self::$commands = [];

        Event::listen(JobProcessing::class, function (JobProcessing $e): void {
            self::$jobs[spl_object_id($e->job)] = $e->job->resolveName();
        });

        // Un fallo dispara más de uno de estos eventos: quitarlo es idempotente.
        $finished = function (JobProcessed|JobFailed|JobExceptionOccurred $e): void {
            unset(self::$jobs[spl_object_id($e->job)]);
        };

        Event::listen(JobProcessed::class, $finished);
        Event::listen(JobFailed::class, $finished);
        Event::listen(JobExceptionOccurred::class, $finished);

        // El nombre real del comando, también con Artisan::call (argv sería el del
        // proceso que lo llamó). CommandFinished llega aunque el comando falle.
        Event::listen(CommandStarting::class, function (CommandStarting $e): void {
            self::$commands[] = $e->command !== '' ? $e->command : 'desconocido';
        });

        Event::listen(CommandFinished::class, function (): void {
            array_pop(self::$commands);
        });
    }

    /**
     * @return array<string, string>
     */
    public static function describe(): array
    {
        if (self::$jobs !== []) {
            return ['origin' => 'job', 'job' => end(self::$jobs)];
        }

        if (self::$commands !== []) {
            return ['origin' => 'console', 'command' => end(self::$commands)];
        }

        $request = app()->bound('request') ? app('request') : null;

        if ($request?->route() !== null) {
            return ['origin' => 'http', 'method' => $request->method(), 'path' => '/' . ltrim($request->path(), '/')];
        }

        if (app()->runningInConsole()) {
            // Sin evento de comando (por ejemplo, un script que arranca la aplicación).
            return ['origin' => 'console', 'command' => implode(' ', array_slice($_SERVER['argv'] ?? [], 1)) ?: 'desconocido'];
        }

        return ['origin' => 'desconocido'];
    }
}
