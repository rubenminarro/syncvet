<?php

namespace Tests\Feature\Tenancy\Fixtures;

use App\Models\Client;
use App\Models\Clinic;
use App\Support\CurrentClinic;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Job de prueba que cuenta los clientes. Con una clínica la fija con
 * CurrentClinic::run, como debe hacer todo job; sin ella, consulta sin contexto.
 */
class CountClientsJob implements ShouldQueue
{
    use Queueable;

    public static ?int $result = null;

    public function __construct(
        public ?Clinic $clinic = null
    ) {
    }

    public function handle(): void
    {
        self::$result = $this->clinic
            ? CurrentClinic::run($this->clinic, fn () => Client::count())
            : Client::count();
    }
}
