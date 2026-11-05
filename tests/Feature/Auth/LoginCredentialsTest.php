<?php

namespace Tests\Feature\Auth;

use App\Exceptions\ClinicException;
use App\Models\User;
use App\Services\AuthService;
use App\Services\ClinicService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Mockery;
use Tests\Concerns\InteractsWithAuth;
use Tests\TestCase;

/**
 * El email del usuario se guarda y se busca en minúsculas, y el login siempre
 * verifica un hash: el tiempo de respuesta no revela qué emails tienen cuenta.
 */
class LoginCredentialsTest extends TestCase
{
    use InteractsWithAuth;

    public function test_the_user_email_is_stored_in_lowercase(): void
    {
        $clinic = $this->createClinic();

        $fromFactory = User::factory()->for($clinic)->create(['email' => 'Ana.Gomez@Example.COM']);
        $fromModel = User::factory()->for($clinic)->create();
        $fromModel->update(['email' => 'Otro.Email@Example.com']);

        $this->assertSame('ana.gomez@example.com', DB::table('users')->where('id', $fromFactory->id)->value('email'));
        $this->assertSame('otro.email@example.com', DB::table('users')->where('id', $fromModel->id)->value('email'));
    }

    public function test_login_finds_the_user_whatever_the_case_of_the_email(): void
    {
        $user = $this->userWithRole($this->createClinic(), 'recepcionista', ['email' => 'ana@example.com']);

        $this->login('ANA@Example.com')->assertOk()->assertJsonPath('data.user.id', $user->id);
    }

    public function test_clinic_service_stores_the_admin_email_in_lowercase_and_compares_it_so(): void
    {
        $service = app(ClinicService::class);
        $admin = ['email' => 'Admin@Central.com.py', 'first_name' => 'María', 'last_name' => 'Benítez', 'password' => 'secreta-123'];

        $clinic = $service->create(['name' => 'Central', 'legal_name' => 'Central S.A.', 'ruc' => '99999910', 'ruc_dv' => '9'], $admin);

        $this->assertSame('admin@central.com.py', DB::table('users')->where('clinic_id', $clinic->id)->value('email'));

        try {
            $service->create(
                ['name' => 'Otra', 'legal_name' => 'Otra S.A.', 'ruc' => '99999911', 'ruc_dv' => '7'],
                [...$admin, 'email' => 'ADMIN@central.com.py']
            );
            $this->fail('Se aceptó el mismo email con otras mayúsculas.');
        } catch (ClinicException $e) {
            $this->assertSame('CLINIC_ADMIN_EMAIL_TAKEN', $e->getErrorCode());
        }
    }

    public function test_an_unknown_email_still_verifies_a_password_hash(): void
    {
        $this->expectOneHashCheck();

        $this->assertErrorCode($this->login('nadie@example.com', 'otra-clave'), 422, 'INVALID_CREDENTIALS');
    }

    public function test_a_deleted_user_still_verifies_a_password_hash(): void
    {
        $user = $this->userWithRole($this->createClinic(), 'recepcionista');
        $user->delete();
        $this->expectOneHashCheck();

        $this->assertErrorCode($this->login($user->email), 422, 'INVALID_CREDENTIALS');
    }

    public function test_a_wrong_password_verifies_the_password_hash_once(): void
    {
        $user = $this->userWithRole($this->createClinic(), 'recepcionista');
        $this->expectOneHashCheck();

        $this->assertErrorCode($this->login($user->email, 'otra-clave'), 422, 'INVALID_CREDENTIALS');
    }

    /**
     * Hash::check se llama una sola vez y hace su trabajo real: el mock envuelve al
     * hasher configurado y le delega cada llamada.
     */
    private function expectOneHashCheck(): void
    {
        $real = Hash::getFacadeRoot();
        $mock = Mockery::mock($real);
        $mock->shouldReceive('check')->once()->andReturnUsing(fn (...$args) => $real->check(...$args));

        Hash::swap($mock);
    }


    /**
     * El hash de relleno es un bcrypt real con el costo de producción: verificarlo
     * tarda lo mismo que verificar la contraseña de un usuario. Si cambia
     * BCRYPT_ROUNDS en .env.example, hay que regenerar AuthService::DUMMY_HASH.
     */
    public function test_the_dummy_hash_is_a_bcrypt_with_the_production_cost(): void
    {
        preg_match('/^BCRYPT_ROUNDS=(\d+)$/m', file_get_contents(base_path('.env.example')), $match);
        $info = password_get_info(AuthService::DUMMY_HASH);

        $this->assertNotEmpty(AuthService::DUMMY_HASH);
        $this->assertSame('bcrypt', $info['algoName']);
        $this->assertSame((int) $match[1], $info['options']['cost']);
    }

    public function test_an_unknown_email_is_checked_against_the_dummy_hash(): void
    {
        $hashes = [];
        $real = Hash::getFacadeRoot();
        $mock = Mockery::mock($real);
        $mock->shouldReceive('check')->once()->andReturnUsing(function ($value, $hash) use ($real, &$hashes) {
            $hashes[] = $hash;

            return $real->check($value, $hash);
        });
        Hash::swap($mock);

        $this->assertErrorCode($this->login('nadie@example.com', 'otra-clave'), 422, 'INVALID_CREDENTIALS');
        $this->assertSame([AuthService::DUMMY_HASH], $hashes);
    }

    public function test_password_and_remember_token_are_hidden_when_the_user_is_serialized(): void
    {
        $user = new User();
        $user->setRawAttributes(['id' => 1, 'email' => 'ana@example.com', 'password' => 'hash', 'remember_token' => 'recordar']);

        $this->assertSame(['id' => 1, 'email' => 'ana@example.com'], $user->toArray());
        $this->assertStringNotContainsString('hash', $user->toJson());
    }

    public function test_clinic_service_rejects_an_admin_email_with_non_ascii_characters(): void
    {
        try {
            app(ClinicService::class)->create(
                ['name' => 'Central', 'legal_name' => 'Central S.A.', 'ruc' => '99999910', 'ruc_dv' => '9'],
                ['email' => 'ānā@central.com.py', 'first_name' => 'Ana', 'last_name' => 'Benítez', 'password' => 'secreta-123']
            );
            $this->fail('Se aceptó un email con caracteres que no son ASCII.');
        } catch (ClinicException $e) {
            $this->assertSame('CLINIC_ADMIN_EMAIL_INVALID', $e->getErrorCode());
            $this->assertSame(422, $e->getStatus());
        }

        $this->assertSame(0, DB::table('clinics')->where('ruc', '99999910')->count());
    }
}
