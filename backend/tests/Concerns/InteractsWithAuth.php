<?php

namespace Tests\Concerns;

use App\Models\Clinic;
use App\Models\User;
use Illuminate\Testing\TestResponse;

/**
 * Login por la API (entrega 0.3). Usa tokens reales de Sanctum, no
 * Sanctum::actingAs: el vencimiento y la revocación solo se prueban así.
 * La contraseña de la UserFactory es "password".
 */
trait InteractsWithAuth
{
    use InteractsWithClinics;

    protected function userWithRole(Clinic $clinic, string $role, array $attributes = []): User
    {
        $user = User::factory()->for($clinic)->create($attributes);
        $user->assignRole($role);

        return $user;
    }

    protected function login(string $email, string $password = 'password'): TestResponse
    {
        $this->forgetAuthenticatedUser();

        return $this->postJson('/api/login', ['email' => $email, 'password' => $password]);
    }

    protected function tokenFor(User $user): string
    {
        return $this->login($user->email)->assertOk()->json('data.token');
    }

    protected function getWithToken(string $token, string $uri): TestResponse
    {
        $this->forgetAuthenticatedUser();

        return $this->withToken($token)->getJson($uri);
    }

    protected function postWithToken(string $token, string $uri): TestResponse
    {
        $this->forgetAuthenticatedUser();

        return $this->withToken($token)->postJson($uri);
    }

    /**
     * El guard guarda el usuario del pedido anterior dentro del mismo test: sin
     * esto, un token revocado o vencido seguiría "autenticado".
     */
    protected function forgetAuthenticatedUser(): void
    {
        $this->app['auth']->forgetGuards();
        $this->flushHeaders();
    }
}
