<?php

namespace App\Support;

use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Event;

/**
 * Qué está ejecutando el proceso: un job, un pedido HTTP o un comando. Lo usa
 * ClinicContextMissingException para dejar en el log dónde se perdió la clínica.
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
     * Sigue los jobs con los eventos de la cola. Se llama al arrancar la aplicación.
     */
    public static function listen(): void
    {
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
    }

    /**
     * @return array<string, string>
     */
    public static function describe(): array
    {
        if (self::$jobs !== []) {
            return ['origin' => 'job', 'job' => end(self::$jobs)];
        }

        $request = app()->bound('request') ? app('request') : null;

        if ($request?->route() !== null) {
            return ['origin' => 'http', 'method' => $request->method(), 'path' => '/' . ltrim($request->path(), '/')];
        }

        if (app()->runningInConsole()) {
            return ['origin' => 'console', 'command' => implode(' ', array_slice($_SERVER['argv'] ?? [], 1)) ?: 'desconocido'];
        }

        return ['origin' => 'desconocido'];
    }
}
