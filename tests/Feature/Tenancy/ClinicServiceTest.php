<?php

namespace Tests\Feature\Tenancy;

use App\Enums\AuditEvent;
use App\Enums\ClinicStatus;
use App\Exceptions\ClinicException;
use App\Models\Clinic;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\ClinicService;
use App\Support\CurrentClinic;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Concerns\InteractsWithClinics;
use Tests\TestCase;

/**
 * ClinicService::create deja una clínica operativa (sucursal, depósito y
 * administrador), o nada si algo falla.
 */
class ClinicServiceTest extends TestCase
{
    use InteractsWithClinics;

    // RUC ficticio (R-010): los reales solo están en RucCheckDigitTest.
    private const DATA = ['name' => 'Veterinaria Central', 'legal_name' => 'Veterinaria Central S.A.', 'ruc' => '99999910', 'ruc_dv' => '9'];

    private const ADMIN = ['email' => 'admin@central.com.py', 'first_name' => 'María', 'last_name' => 'Benítez', 'password' => 'secreta-123'];

    public function test_create_leaves_clinic_branch_warehouse_and_administrator(): void
    {
        $clinic = app(ClinicService::class)->create(self::DATA, self::ADMIN);

        $this->assertSame(ClinicStatus::ACTIVE, $clinic->fresh()->status);
        $this->assertSame('America/Asuncion', $clinic->fresh()->timezone);
        $this->assertFalse(CurrentClinic::has(), 'create() no restauró el contexto.');

        $branch = DB::table('branches')->where('clinic_id', $clinic->id)->sole();
        $this->assertSame(['SUC-01', 'Casa central', '001'], [$branch->code, $branch->name, $branch->establishment]);
        $this->assertTrue((bool) $branch->is_default);

        $warehouse = DB::table('warehouses')->where('clinic_id', $clinic->id)->sole();
        $this->assertSame(['DEP-01', 'Principal', $branch->id], [$warehouse->code, $warehouse->name, $warehouse->branch_id]);
        $this->assertTrue((bool) $warehouse->is_default);

        $admin = User::where('email', self::ADMIN['email'])->sole();
        $this->assertSame($clinic->id, $admin->clinic_id);
        $this->assertSame('María Benítez', $admin->name);
        $this->assertSame($branch->id, $admin->current_branch_id);
        $this->assertTrue(Hash::check(self::ADMIN['password'], $admin->password));
        $this->assertTrue($admin->hasRole('administrador'));
        $this->assertSame([$branch->id], DB::table('branch_user')->where('user_id', $admin->id)->pluck('branch_id')->all());

        $this->assertTrue(
            DB::table('audit_logs')->where('clinic_id', $clinic->id)->where('auditable_type', 'clinic')
                ->where('event', AuditEvent::CREATED->value)->exists(),
            'El alta de la clínica no quedó auditada con su clinic_id.'
        );
    }

    public function test_two_clinics_each_get_their_own_suc_01_and_dep_01(): void
    {
        app(ClinicService::class)->create(self::DATA, self::ADMIN);
        app(ClinicService::class)->create(
            ['name' => 'Otra', 'legal_name' => 'Otra S.A.', 'ruc' => '99999911', 'ruc_dv' => (string) ClinicService::rucCheckDigit('99999911')],
            [...self::ADMIN, 'email' => 'admin@otra.com.py'],
        );

        $this->assertSame(2, DB::table('branches')->where('code', 'SUC-01')->count());
        $this->assertSame(2, DB::table('warehouses')->where('code', 'DEP-01')->count());
    }

    public function test_nothing_remains_if_something_fails(): void
    {
        Warehouse::creating(fn () => throw new RuntimeException('Falla simulada'));

        try {
            app(ClinicService::class)->create(self::DATA, self::ADMIN);
            $this->fail('El alta no falló.');
        } catch (RuntimeException) {
        }

        $this->assertFalse(Clinic::where('ruc', self::DATA['ruc'])->exists());
        $this->assertSame(0, DB::table('branches')->count());
        $this->assertFalse(User::withTrashed()->where('email', self::ADMIN['email'])->exists());
        $this->assertFalse(CurrentClinic::has());
    }

    public static function invalidRucs(): array
    {
        return [
            'dígito verificador incorrecto' => ['99999910', '8'],
            'RUC con letras' => ['9999991A', '1'],
            'dígito verificador vacío' => ['99999910', ''],
        ];
    }

    #[DataProvider('invalidRucs')]
    public function test_invalid_ruc_is_rejected(string $ruc, string $dv): void
    {
        $this->assertClinicError('CLINIC_RUC_INVALID', fn () => app(ClinicService::class)->create([...self::DATA, 'ruc' => $ruc, 'ruc_dv' => $dv], self::ADMIN));
        $this->assertSame(0, Clinic::count());
    }

    public function test_ruc_is_unique_in_the_whole_system(): void
    {
        $this->createClinic(['ruc' => self::DATA['ruc'], 'ruc_dv' => self::DATA['ruc_dv']]);

        $this->assertClinicError('CLINIC_RUC_TAKEN', fn () => app(ClinicService::class)->create(self::DATA, self::ADMIN));
    }

    public function test_admin_email_is_unique_in_the_whole_system_including_deleted_users(): void
    {
        $this->userIn($this->createClinic())->forceFill(['email' => self::ADMIN['email']])->save();
        User::where('email', self::ADMIN['email'])->first()->delete();

        $this->assertClinicError('CLINIC_ADMIN_EMAIL_TAKEN', fn () => app(ClinicService::class)->create(self::DATA, self::ADMIN));
        $this->assertFalse(Clinic::where('ruc', self::DATA['ruc'])->exists());
    }

    private function assertClinicError(string $errorCode, \Closure $action): void
    {
        try {
            $action();
            $this->fail("No se lanzó {$errorCode}.");
        } catch (ClinicException $e) {
            $this->assertSame($errorCode, $e->getErrorCode());
            $this->assertSame(422, $e->getStatus());
        }
    }

    /**
     * El alta de una clínica queda en SU auditoría, aunque create() se llame desde
     * el contexto de otra clínica (AuditLogger prefiere la clínica del registro).
     */
    public function test_creation_audit_belongs_to_the_new_clinic_even_inside_another_context(): void
    {
        $other = $this->createClinic();
        $otherLogs = DB::table('audit_logs')->where('clinic_id', $other->id)->count();

        $clinic = CurrentClinic::run($other, fn () => app(ClinicService::class)->create(self::DATA, self::ADMIN));

        $this->assertSame(
            [$clinic->id],
            DB::table('audit_logs')->where('auditable_type', 'clinic')->where('auditable_id', $clinic->id)
                ->where('event', AuditEvent::CREATED->value)->pluck('clinic_id')->all(),
            'El alta de la clínica quedó en la auditoría de otra clínica.'
        );
        $this->assertSame(
            $otherLogs,
            DB::table('audit_logs')->where('clinic_id', $other->id)->count(),
            'La auditoría de la otra clínica recibió registros del alta.'
        );
    }
}
