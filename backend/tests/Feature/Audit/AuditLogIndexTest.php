<?php

namespace Tests\Feature\Audit;

use App\Enums\AuditEvent;
use App\Http\Requests\IndexAuditLogRequest;
use App\Models\AuditLog;
use App\Models\Clinic;
use App\Models\User;
use App\Support\CurrentClinic;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithAuth;
use Tests\TestCase;

/**
 * Listado de la auditoría (FASE 2, entrega 2.6; P14): solo el administrador, la clínica
 * en contexto (ClinicScope), por created_at e id descendentes, sin old_values ni
 * new_values. Filtros from y to (con zona, sin zona = hora de Asunción, o fecha sola),
 * user_id, event, auditable_type y auditable_id, y search literal en el usuario.
 *
 * Los registros se crean a mano con su created_at en la hora de Asunción (UTC-3 en
 * noviembre): 08:00 de Asunción son las 11:00 UTC.
 */
class AuditLogIndexTest extends TestCase
{
    use InteractsWithAuth;

    private const URL = '/api/audit-logs';

    private Clinic $clinic;

    private User $admin;

    private User $ana;

    /** @var array<string, int> */
    private array $logs = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->clinic = $this->createClinic();
        $this->admin = $this->userWithRole($this->clinic, 'administrador');
        $this->ana = User::factory()->for($this->clinic)->create(['first_name' => 'Ana', 'last_name' => 'Pérez_%', 'name' => 'Ana Pérez_%', 'email' => 'ana.perez@example.com']);
        // Solo los registros del test: el alta de los usuarios ya dejó los suyos.
        DB::table('audit_logs')->delete();

        $this->logs = [
            'day_before' => $this->log('2026-11-08 23:59:59', AuditEvent::LOGIN, $this->admin),
            'morning' => $this->log('2026-11-09 08:00:00', AuditEvent::CREATED, $this->ana, 'client', 7),
            'night' => $this->log('2026-11-09 23:30:00', AuditEvent::UPDATED, $this->ana, 'client', 7),
            'night_other' => $this->log('2026-11-09 23:30:00', AuditEvent::UPDATED, $this->admin, 'patient', 7),
            'next_day' => $this->log('2026-11-10 00:30:00', AuditEvent::DELETED, $this->admin, 'client', 8),
        ];

        Sanctum::actingAs($this->admin);
    }

    public function test_lists_the_logs_of_the_clinic_newest_first_without_values(): void
    {
        // De otra clínica y un login fallido sin clínica: nunca aparecen.
        $other = $this->createClinic();
        CurrentClinic::run($other, fn () => AuditLog::create(['clinic_id' => $other->id, 'event' => AuditEvent::LOGIN, 'created_at' => '2026-11-09 12:00:00']));
        DB::table('audit_logs')->insert(['clinic_id' => null, 'event' => 'login_failed', 'new_values' => json_encode(['email' => 'x@example.com']), 'created_at' => '2026-11-09 12:00:00']);

        $response = $this->getJson(self::URL)->assertOk()
            ->assertJsonPath('message', 'Registros de auditoría obtenidos correctamente.')
            ->assertJsonPath('meta.pagination.total', 5)
            ->assertJsonPath('meta.pagination.perPage', 10);

        // Por created_at descendente; con la misma hora, por id descendente.
        $this->assertSame(
            [$this->logs['next_day'], $this->logs['night_other'], $this->logs['night'], $this->logs['morning'], $this->logs['day_before']],
            array_column($response->json('data'), 'id')
        );

        $first = $response->json('data.0');
        $this->assertSame(['id', 'event', 'auditable_type', 'auditable_id', 'user', 'ip_address', 'created_at'], array_keys($first));
        $this->assertSame(['value' => 'deleted', 'label' => 'Eliminado'], $first['event']);
        $this->assertSame(['id' => $this->admin->id, 'name' => $this->admin->name], $first['user']);
        // La hora de Asunción sale en UTC.
        $this->assertSame('2026-11-10T03:30:00.000000Z', $first['created_at']);
    }

    /**
     * from y to: con zona, sin zona (hora de Asunción) o una fecha sola (todo el día en
     * Asunción, aunque en UTC sean dos días).
     */
    public static function dateFilters(): array
    {
        return [
            'fecha sola: todo el día en Asunción' => ['from=2026-11-09&to=2026-11-09', ['night_other', 'night', 'morning']],
            'from con Z, justo en el registro' => ['from=2026-11-09T11:00:00Z', ['next_day', 'night_other', 'night', 'morning']],
            'from con Z, un segundo después' => ['from=2026-11-09T11:00:01Z', ['next_day', 'night_other', 'night']],
            'to con Z' => ['to=2026-11-10T02:30:00Z', ['night_other', 'night', 'morning', 'day_before']],
            'from sin zona: hora de Asunción' => ['from=2026-11-09T23:30:00', ['next_day', 'night_other', 'night']],
            'from con espacio y sin segundos' => ['from=2026-11-09 23:31', ['next_day']],
            'from con desplazamiento' => ['from=2026-11-09T21:30:00-05:00', ['next_day', 'night_other', 'night']],
            'to con fracción y Z' => ['to=2026-11-09T02:59:59.999Z', ['day_before']],
            'to fecha sola' => ['to=2026-11-08', ['day_before']],
            // created_at no tiene fracción: from se redondea hacia arriba y to hacia abajo.
            'from con fracción: no incluye el segundo anterior' => ['from=2026-11-09T11:00:00.5Z', ['next_day', 'night_other', 'night']],
            'from con .000Z de toISOString()' => ['from=2026-11-09T11:00:00.000Z', ['next_day', 'night_other', 'night', 'morning']],
            'to con fracción: incluye su segundo' => ['to=2026-11-09T11:00:00.5Z', ['morning', 'day_before']],
            'from con +14:00' => ['from=2026-11-10T01:00:00+14:00', ['next_day', 'night_other', 'night', 'morning']],
            'from con -1200 sin dos puntos' => ['from=2026-11-09T14:31:00-1200', ['next_day']],
            'from a las 23:59:59' => ['from=2026-11-09T23:59:59', ['next_day']],
            'from igual a to con hora' => ['from=2026-11-09T08:00:00&to=2026-11-09T08:00:00', ['morning']],
        ];
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('dateFilters')]
    public function test_from_and_to_accept_dates_with_and_without_zone(string $query, array $expected): void
    {
        $response = $this->getJson(self::URL . '?' . str_replace('+', '%2B', $query))->assertOk();

        $this->assertSame($this->ids($expected), array_column($response->json('data'), 'id'));
    }

    public function test_filters_by_user_event_type_and_id(): void
    {
        $filters = [
            "user_id={$this->ana->id}" => ['night', 'morning'],
            'event=updated' => ['night_other', 'night'],
            'auditable_type=client' => ['next_day', 'night', 'morning'],
            'auditable_type=client&auditable_id=7' => ['night', 'morning'],
            // El mismo id en otro tipo no se mezcla.
            'auditable_type=patient&auditable_id=7' => ['night_other'],
            "user_id={$this->admin->id}&event=updated" => ['night_other'],
        ];

        foreach ($filters as $query => $expected) {
            $response = $this->getJson(self::URL . '?' . $query)->assertOk();

            $this->assertSame($this->ids($expected), array_column($response->json('data'), 'id'), $query);
        }
    }

    /**
     * search es literal en el nombre o el email del usuario: "%" y "_" no son comodines.
     * Un usuario eliminado se sigue encontrando, y también se filtra por su id.
     */
    public function test_search_is_literal_in_the_user_name_or_email_also_of_a_deleted_user(): void
    {
        $this->assertSame($this->ids(['night', 'morning']), array_column($this->getJson(self::URL . '?search=' . urlencode('Pérez_%'))->json('data'), 'id'));
        $this->assertSame($this->ids(['night', 'morning']), array_column($this->getJson(self::URL . '?search=ANA.PEREZ@')->json('data'), 'id'));
        $this->assertSame([], $this->getJson(self::URL . '?search=' . urlencode('P_rez'))->json('data'));
        // Como comodín, "Ana%rez" encontraría a "Ana Pérez_%".
        $this->assertSame([], $this->getJson(self::URL . '?search=' . urlencode('Ana%rez'))->json('data'));

        $this->ana->delete();

        $this->assertSame($this->ids(['night', 'morning']), array_column($this->getJson(self::URL . '?search=ana.perez')->json('data'), 'id'));
        $this->assertSame(2, $this->getJson(self::URL . "?user_id={$this->ana->id}")->assertOk()->json('meta.pagination.total'));
    }

    /**
     * event=login_failed trae los inicios de sesión fallidos de la clínica (un email conocido
     * con una contraseña incorrecta), nunca los que no tienen clínica.
     */
    public function test_filters_the_failed_logins_of_the_clinic(): void
    {
        $failed = CurrentClinic::run($this->clinic, fn () => AuditLog::create([
            'clinic_id' => $this->clinic->id,
            'event' => AuditEvent::LOGIN_FAILED,
            'user_id' => $this->ana->id,
            'created_at' => '2026-11-09 12:00:00',
        ])->id);
        DB::table('audit_logs')->insert(['clinic_id' => null, 'event' => 'login_failed', 'new_values' => json_encode(['email' => 'x@example.com']), 'created_at' => '2026-11-09 12:00:00']);
        $foreign = $this->createClinic();
        CurrentClinic::run($foreign, fn () => AuditLog::create(['clinic_id' => $foreign->id, 'event' => AuditEvent::LOGIN_FAILED, 'created_at' => '2026-11-09 12:00:00']));

        $this->getJson(self::URL . '?event=login_failed')->assertOk()
            ->assertJsonPath('meta.pagination.total', 1)
            ->assertJsonPath('data.0.id', $failed)
            ->assertJsonPath('data.0.event', ['value' => 'login_failed', 'label' => 'Inicio de sesión fallido']);
    }

    public function test_pagination(): void
    {
        $response = $this->getJson(self::URL . '?per_page=2&page=2')->assertOk()
            ->assertJsonPath('meta.pagination.total', 5)
            ->assertJsonPath('meta.pagination.currentPage', 2)
            ->assertJsonPath('meta.pagination.lastPage', 3);

        $this->assertSame($this->ids(['night', 'morning']), array_column($response->json('data'), 'id'));
    }

    public static function invalidFilters(): array
    {
        return [
            'to antes de from' => ['from=2026-11-09T12:00:00Z&to=2026-11-09T08:59:59', 'to', IndexAuditLogRequest::TO_BEFORE_FROM],
            'to fecha sola antes de from' => ['from=2026-11-09&to=2026-11-08', 'to', IndexAuditLogRequest::TO_BEFORE_FROM],
            'auditable_id sin tipo' => ['auditable_id=7', 'auditable_type', IndexAuditLogRequest::TYPE_REQUIRED],
            'tipo fuera de la lista' => ['auditable_type=audit_log', 'auditable_type', 'El tipo de registro no es válido.'],
            'tipo con la clase' => ['auditable_type=' . urlencode('App\\Models\\Client'), 'auditable_type', 'El tipo de registro no es válido.'],
            'evento inexistente' => ['event=borrado', 'event', null],
            'fecha en palabras' => ['from=tomorrow', 'from', IndexAuditLogRequest::DATE_FORMAT],
            'fecha imposible' => ['to=2026-02-30', 'to', IndexAuditLogRequest::DATE_FORMAT],
            'hora sin fecha' => ['from=13:00', 'from', IndexAuditLogRequest::DATE_FORMAT],
            'las 24:00' => ['from=2026-11-09T24:00:00', 'from', IndexAuditLogRequest::DATE_FORMAT],
            'minuto 60' => ['to=2026-11-09T10:60:00', 'to', IndexAuditLogRequest::DATE_FORMAT],
            'desplazamiento -2400' => ['from=2026-11-09T10:00:00-2400', 'from', IndexAuditLogRequest::DATE_FORMAT],
            'desplazamiento de más de 14 horas' => ['to=2026-11-09T10:00:00%2B14:30', 'to', IndexAuditLogRequest::DATE_FORMAT],
            'desplazamiento -15:00' => ['from=2026-11-09T10:00:00-15:00', 'from', IndexAuditLogRequest::DATE_FORMAT],
            'auditable_id que no es número' => ['auditable_type=client&auditable_id=x', 'auditable_id', null],
            'user_id inexistente' => ['user_id=999999', 'user_id', 'El usuario no existe.'],
            'per_page fuera de rango' => ['per_page=101', 'per_page', null],
        ];
    }

    #[DataProvider('invalidFilters')]
    public function test_an_invalid_filter_answers_422(string $query, string $field, ?string $message): void
    {
        $response = $this->getJson(self::URL . '?' . $query);

        $this->assertErrorCode($response, 422, 'VALIDATION_ERROR');
        $this->assertArrayHasKey($field, $response->json('errors'));

        if ($message !== null) {
            $this->assertSame($message, $response->json("errors.{$field}.0"));
        }
    }

    public function test_a_user_of_another_clinic_is_not_a_valid_filter(): void
    {
        $foreign = User::factory()->for($this->createClinic())->create();

        $this->assertErrorCode($this->getJson(self::URL . "?user_id={$foreign->id}"), 422, 'VALIDATION_ERROR');
    }

    public static function otherRoles(): array
    {
        return ['recepcionista' => ['recepcionista'], 'veterinario' => ['veterinario']];
    }

    #[DataProvider('otherRoles')]
    public function test_the_other_roles_do_not_see_the_audit(string $role): void
    {
        Sanctum::actingAs($this->userWithRole($this->clinic, $role));

        $this->assertErrorCode($this->getJson(self::URL), 403, 'FORBIDDEN');
        $this->assertErrorCode($this->getJson(self::URL . '/' . $this->logs['morning']), 403, 'FORBIDDEN');
    }

    private function log(string $createdAt, AuditEvent $event, User $user, ?string $type = null, ?int $id = null): int
    {
        return CurrentClinic::run($this->clinic, fn () => AuditLog::create([
            'clinic_id' => $this->clinic->id,
            'event' => $event,
            'auditable_type' => $type,
            'auditable_id' => $id,
            'user_id' => $user->id,
            'created_at' => $createdAt,
        ])->id);
    }

    /**
     * @param list<string> $names
     * @return list<int>
     */
    private function ids(array $names): array
    {
        return array_map(fn (string $name) => $this->logs[$name], $names);
    }
}
