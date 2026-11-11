<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Tokens de Sanctum vencidos hace más de 24 horas (los tokens duran 12 horas, R-004).
Schedule::command('sanctum:prune-expired --hours=24')->daily();

// Fotos de pacientes y logos huérfanos de más de 24 horas (FASE 2, entrega 2.5; deuda 14). En
// producción necesita el cron del scheduler (FASE 10).
Schedule::command('files:prune-orphans')->dailyAt('03:00');
