<?php

namespace Tests\Feature\Tenancy;

use App\Enums\AuditEvent;
use App\Exceptions\ClinicContextMissingException;
use App\Models\Client;
use App\Services\AuditLogger;
use App\Support\CurrentClinic;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithClinics;
use Tests\TestCase;

/**
 * El alta toma siempre la clínica del contexto (BelongsToClinic): nunca la del
 * cuerpo del pedido ni otra.
 */
class ClinicAssignmentTest extends TestCase
{
    use InteractsWithClinics;

    public function test_client_creation_takes_the_clinic_from_context_even_if_the_body_sends_another(): void
    {
        $own = $this->createClinic();
        $other = $this->createClinic();
        $this->actingIn($own, ['clients.store']);

        $id = $this->postJson('/api/clients', $this->clientPayload(['clinic_id' => $other->id]))
            ->assertCreated()
            ->json('data.id');

        $this->assertSame($own->id, DB::table('clients')->where('id', $id)->value('clinic_id'));
    }

    public function test_patient_creation_takes_the_clinic_from_context_even_if_the_body_sends_another(): void
    {
        $own = $this->createClinic();
        $other = $this->createClinic();
        $client = $this->clientIn($own);
        $this->actingIn($own, ['patients.store']);

        $id = $this->postJson("/api/clients/{$client->id}/patients", [
            'name' => 'Toby',
            'species_id' => $this->globalSpeciesId(),
            'clinic_id' => $other->id,
        ])->assertCreated()->json('data.id');

        $this->assertSame($own->id, DB::table('patients')->where('id', $id)->value('clinic_id'));
    }

    public function test_clinic_id_is_not_mass_assignable(): void
    {
        $this->assertNull((new Client(['clinic_id' => 999]))->clinic_id);
    }

    public function test_creating_in_another_clinic_fails(): void
    {
        $own = $this->createClinic();
        $other = $this->createClinic();

        try {
            CurrentClinic::run($own, function () use ($other) {
                $client = new Client(['document_number' => '99400002', 'first_name' => 'Ana', 'last_name' => 'Ruiz', 'email' => 'ana@example.com', 'phone' => '981123456']);
                $client->clinic_id = $other->id;
                $client->save();
            });

            $this->fail('Se creó un cliente en otra clínica.');
        } catch (ClinicContextMissingException) {
            $this->assertSame(0, DB::table('clients')->count());
        }
    }

    public function test_creating_without_clinic_in_context_fails(): void
    {
        $this->expectException(ClinicContextMissingException::class);

        Client::create(['document_number' => '99400002', 'first_name' => 'Ana', 'last_name' => 'Ruiz', 'email' => 'ana@example.com', 'phone' => '981123456']);
    }

    public function test_audit_log_takes_the_clinic_from_context(): void
    {
        $own = $this->createClinic();
        $this->actingIn($own, ['clients.store']);

        $id = $this->postJson('/api/clients', $this->clientPayload())->assertCreated()->json('data.id');

        $log = DB::table('audit_logs')
            ->where('auditable_type', 'client')
            ->where('auditable_id', $id)
            ->where('event', AuditEvent::CREATED->value)
            ->first();

        $this->assertSame($own->id, $log?->clinic_id);
    }

    public function test_clinic_id_cannot_change_on_update(): void
    {
        $own = $this->createClinic();
        $other = $this->createClinic();
        $client = $this->clientIn($own);

        try {
            CurrentClinic::run($own, fn () => $client->forceFill(['clinic_id' => $other->id])->save());
            $this->fail('Se pasó un cliente a otra clínica.');
        } catch (ClinicContextMissingException) {
            $this->assertSame($own->id, DB::table('clients')->where('id', $client->id)->value('clinic_id'));
        }
    }

    /**
     * La auditoría usa la clínica del registro auditado antes que la del contexto:
     * editar un usuario de otra clínica no deja su rastro en la clínica en contexto.
     */
    public function test_audit_log_takes_the_clinic_of_the_audited_record(): void
    {
        $own = $this->createClinic();
        $foreignUser = $this->userIn($this->createClinic());

        CurrentClinic::run($own, fn () => $foreignUser->update(['first_name' => 'Otro']));

        $log = DB::table('audit_logs')
            ->where('auditable_type', 'user')
            ->where('auditable_id', $foreignUser->id)
            ->where('event', AuditEvent::UPDATED->value)
            ->sole();

        $this->assertSame($foreignUser->clinic_id, $log->clinic_id);
    }

    /**
     * Un job o un comando no tiene usuario: el evento queda en la clínica que fijó
     * con CurrentClinic::run.
     */
    public function test_audit_log_takes_the_clinic_from_context_in_a_command(): void
    {
        $clinic = $this->createClinic();

        Artisan::command('testing:audit-download', function () use ($clinic): void {
            CurrentClinic::run($clinic, fn () => app(AuditLogger::class)->record(AuditEvent::DOWNLOADED));
        });
        Artisan::call('testing:audit-download');

        $log = DB::table('audit_logs')->where('event', AuditEvent::DOWNLOADED->value)->sole();

        $this->assertNull($log->user_id);
        $this->assertSame($clinic->id, $log->clinic_id);
    }
}
