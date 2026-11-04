<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Spatie\Permission\PermissionRegistrar;

/**
 * Base de todos los tests. RefreshDatabase migra y siembra (DatabaseSeeder:
 * roles, permisos y especies) una sola vez por ejecución, y corre cada test
 * dentro de una transacción que se deshace al terminar.
 */
abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    /**
     * Sembrar al migrar (una vez por ejecución, no por test).
     */
    protected bool $seed = true;

    protected function setUp(): void
    {
        parent::setUp();

        // La caché de Spatie sobrevive entre tests del mismo proceso.
        $this->app->make(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
