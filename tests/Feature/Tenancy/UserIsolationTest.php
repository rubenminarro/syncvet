<?php

namespace Tests\Feature\Tenancy;

use App\Models\User;
use App\Support\CurrentClinic;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\InteractsWithClinics;
use Tests\TestCase;

/**
 * User no tiene ClinicScope (el login y Sanctum lo buscan antes de conocer la
 * clínica), pero el route binding de {user} solo resuelve dentro de la clínica
 * actual. Las rutas son de prueba hasta que exista el ABM de usuarios (FASE 2).
 */
class UserIsolationTest extends TestCase
{
    use InteractsWithClinics;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['api', 'auth:sanctum', 'clinic'])->group(function () {
            Route::get('/api/testing/users/{user}', fn (User $user) => response()->json(['id' => $user->id]));
            Route::get('/api/testing/current-clinic', fn () => response()->json(['clinic_id' => CurrentClinic::id()]));
        });
    }

    public function test_user_of_another_clinic_is_not_found(): void
    {
        $own = $this->createClinic();
        $foreign = $this->userIn($this->createClinic());

        $this->actingIn($own);

        $this->assertErrorCode($this->getJson("/api/testing/users/{$foreign->id}"), 404, 'RESOURCE_NOT_FOUND');
    }

    public function test_user_of_own_clinic_is_found(): void
    {
        $own = $this->createClinic();
        $colleague = $this->userIn($own);

        $this->actingIn($own);

        $this->getJson("/api/testing/users/{$colleague->id}")->assertOk()->assertJson(['id' => $colleague->id]);
    }

    /**
     * El login (entrega 0.3) busca al usuario por email sin clínica en contexto, y
     * Sanctum resuelve el token también sin ella: un scope en User los rompería.
     */
    public function test_user_is_found_without_clinic_in_context(): void
    {
        $clinic = $this->createClinic();
        $user = $this->userIn($clinic);
        $token = $user->createToken('test')->plainTextToken;

        $this->assertFalse(CurrentClinic::has());
        $this->assertTrue(Auth::guard('web')->validate(['email' => $user->email, 'password' => 'password']));

        $this->withToken($token)
            ->getJson('/api/testing/current-clinic')
            ->assertOk()
            ->assertJson(['clinic_id' => $clinic->id]);
    }

    public function test_explicit_user_queries_filter_by_clinic(): void
    {
        $own = $this->createClinic();
        $mine = $this->userIn($own);
        $this->userIn($this->createClinic());

        $ids = CurrentClinic::run($own, fn () => User::inClinic()->pluck('id')->all());

        $this->assertSame([$mine->id], $ids);
    }
}
