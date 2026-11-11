<?php

namespace Tests\Feature;

use App\Models\Clinic;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\InteractsWithUsers;
use Tests\TestCase;

/**
 * EMAIL_DNS_CHECK (config validation.email_dns): phpunit.xml lo apaga para que la
 * suite no salga a la red. Aquí se prende y se usa un dominio reservado (.invalid,
 * RFC 2606), que la validación dns rechaza sin consultar al DNS: si una regla de email
 * dejara de mirar la configuración, uno de estos tests falla, sin depender de la red.
 * Cubre las cuatro reglas: el alta y la edición de clientes, los usuarios
 * (UserMessages::emailRules, la misma en el alta y la edición) y contact_email.
 */
class EmailDnsCheckTest extends TestCase
{
    use InteractsWithUsers;

    private const RESERVED_EMAIL = 'ana@clinica.invalid';

    private Clinic $clinic;

    protected function setUp(): void
    {
        parent::setUp();

        $this->clinic = $this->createClinic();
    }

    public function test_the_client_email_is_checked_against_the_dns_only_when_enabled(): void
    {
        $this->actingIn($this->clinic, ['clients.store', 'clients.update']);
        $client = $this->clientIn($this->clinic);

        config(['validation.email_dns' => true]);
        $this->postJson('/api/clients', $this->clientPayload(['email' => self::RESERVED_EMAIL]))
            ->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->patchJson("/api/clients/{$client->id}", ['email' => self::RESERVED_EMAIL])
            ->assertUnprocessable()->assertJsonValidationErrors('email');

        config(['validation.email_dns' => false]);
        $this->postJson('/api/clients', $this->clientPayload(['email' => self::RESERVED_EMAIL]))->assertCreated();
        $this->patchJson("/api/clients/{$client->id}", ['email' => 'otra@clinica.invalid'])->assertOk();
    }

    public function test_the_user_email_is_checked_against_the_dns_only_when_enabled(): void
    {
        $branch = $this->defaultBranchIn($this->clinic);
        $this->actAsUserManager($this->clinic);

        config(['validation.email_dns' => true]);
        $this->postJson('/api/users', $this->userPayload([$branch->id], ['email' => self::RESERVED_EMAIL]))
            ->assertUnprocessable()->assertJsonValidationErrors('email');

        config(['validation.email_dns' => false]);
        $this->postJson('/api/users', $this->userPayload([$branch->id], ['email' => self::RESERVED_EMAIL]))->assertCreated();
    }

    public function test_the_contact_email_is_checked_against_the_dns_only_when_enabled(): void
    {
        $this->actingIn($this->clinic, ['system-settings.update']);

        config(['validation.email_dns' => true]);
        $this->updateContactEmail()->assertUnprocessable()->assertJsonValidationErrors('settings.contact_email');

        config(['validation.email_dns' => false]);
        $this->updateContactEmail()->assertOk()->assertJsonPath('data.settings.contact_email', self::RESERVED_EMAIL);
    }

    private function updateContactEmail(): TestResponse
    {
        return $this->patchJson('/api/system-settings', ['settings' => ['contact_email' => self::RESERVED_EMAIL]]);
    }
}
