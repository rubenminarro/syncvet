<?php

namespace Tests\Feature\Services;

use App\Models\Service;
use App\Support\CurrentClinic;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithServices;
use Tests\TestCase;

/**
 * Alta de un servicio (FASE 2, entrega 2.4; P8): código SRV- de la serie, nombre único en
 * la clínica sin mayúsculas, acentos ni espacios repetidos, precio entero (pregunta 21),
 * duración de 5 a 720 en múltiplos de 5 (pregunta 22) y tasa de IVA 0, 5 o 10.
 */
class ServiceStoreTest extends TestCase
{
    use InteractsWithServices;

    private const PRICE_MESSAGE = 'El precio debe ser un número entero, sin decimales, de 0 a 999.999.999.999.';

    public function test_creates_services_with_correlative_srv_codes(): void
    {
        $clinic = $this->createClinic();
        $this->actAsServiceManager($clinic);

        $this->postJson('/api/services', [
            'name' => '  Consulta   general ',
            'category' => 'consultation',
            'description' => 'Control clínico.',
            'duration_minutes' => 45,
            'standard_price' => 150000,
            'tax_rate' => 5,
            'status' => 'active',
        ])
            ->assertCreated()
            ->assertJsonPath('message', 'Servicio creado correctamente.')
            ->assertJsonPath('data.code', 'SRV-000001')
            // El nombre se guarda limpio (CatalogName::clean).
            ->assertJsonPath('data.name', 'Consulta general')
            ->assertJsonPath('data.category', ['value' => 'consultation', 'label' => 'Consulta'])
            ->assertJsonPath('data.description', 'Control clínico.')
            ->assertJsonPath('data.duration_minutes', 45)
            ->assertJsonPath('data.standard_price', '150000.00')
            ->assertJsonPath('data.tax_rate', 5)
            ->assertJsonPath('data.status', ['value' => 'active', 'label' => 'Activo'])
            ->assertJsonStructure(['data' => ['id', 'created_at', 'updated_at']]);

        // Sin los opcionales: duración 30, IVA 10 y activo. El precio como texto de dígitos.
        $this->postJson('/api/services', ['name' => 'Vacuna antirrábica', 'category' => 'vaccination', 'standard_price' => '085000'])
            ->assertCreated()
            ->assertJsonPath('data.code', 'SRV-000002')
            ->assertJsonPath('data.category.label', 'Vacunación')
            ->assertJsonPath('data.duration_minutes', 30)
            ->assertJsonPath('data.standard_price', '85000.00')
            ->assertJsonPath('data.tax_rate', 10)
            ->assertJsonPath('data.status.value', 'active')
            ->assertJsonPath('data.description', null);

        $this->assertSame(['150000.00', '85000.00'], CurrentClinic::run($clinic, fn () => Service::orderBy('id')->pluck('standard_price')->all()));
        $id = CurrentClinic::run($clinic, fn () => Service::where('code', 'SRV-000001')->value('id'));
        $this->assertSame(1, DB::table('audit_logs')->where('auditable_type', 'service')->where('auditable_id', $id)->where('event', 'created')->count());
    }

    /**
     * Repetido sin distinguir mayúsculas, acentos ni espacios, también frente a un inactivo;
     * el de otra clínica y el de un servicio eliminado (con el sufijo) no chocan.
     */
    public function test_the_name_is_unique_in_the_clinic_without_case_accents_or_spaces(): void
    {
        $clinic = $this->createClinic();
        $this->serviceIn($clinic, ['name' => 'Ecografía abdominal']);
        $this->serviceIn($clinic, ['name' => 'Baño medicado', 'status' => 'inactive']);
        $this->serviceIn($this->createClinic(), ['name' => 'Cirugía menor']);
        $this->actAsServiceManager($clinic);

        foreach (['ECOGRAFIA  ABDOMINAL', ' ecografía abdominal', 'bano MEDICADO'] as $name) {
            $this->postJson('/api/services', ['name' => $name, 'category' => 'imaging', 'standard_price' => 1])
                ->assertUnprocessable()
                ->assertJsonPath('error_code', 'VALIDATION_ERROR')
                ->assertJsonPath('errors.name.0', 'Ya hay un servicio con ese nombre.');
        }

        $this->postJson('/api/services', ['name' => 'Cirugía menor', 'category' => 'surgery', 'standard_price' => 1])->assertCreated();
        $this->assertSame(3, DB::table('services')->where('clinic_id', $clinic->id)->count());
    }

    public function test_the_code_is_assigned_by_the_series(): void
    {
        $clinic = $this->createClinic();
        $this->actAsServiceManager($clinic);

        $this->postJson('/api/services', ['name' => 'Consulta', 'category' => 'consultation', 'standard_price' => 1, 'code' => 'SRV-999999'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.code.0', 'El código del servicio se asigna automáticamente.');
    }

    public static function invalidPrices(): array
    {
        return [
            'con decimales (texto)' => ['150000.50'],
            'con decimales (número)' => [150000.5],
            'con ceros decimales (texto)' => ['150000.00'],
            'negativo (número)' => [-1],
            'negativo (texto)' => ['-1'],
            'mayor al máximo (número)' => [1000000000000],
            'mayor al máximo (texto)' => ['1000000000000'],
            'enorme (float de JSON)' => [1e20],
            'con exponente' => ['1e3'],
            'con separador de miles' => ['150.000'],
            'booleano' => [true],
            'lista' => [[1]],
            'texto' => ['gratis'],
        ];
    }

    #[DataProvider('invalidPrices')]
    public function test_the_price_is_a_whole_amount_up_to_999_999_999_999(mixed $price): void
    {
        $clinic = $this->createClinic();
        $this->actAsServiceManager($clinic);

        $this->postJson('/api/services', ['name' => 'Consulta', 'category' => 'consultation', 'standard_price' => $price])
            ->assertUnprocessable()
            ->assertJsonPath('errors.standard_price.0', self::PRICE_MESSAGE);

        $this->assertSame(0, DB::table('services')->count());
    }

    /**
     * Un número de JSON con ".0" llega como float y se rechaza. Con el cuerpo crudo:
     * postJson usa json_encode, que escribe 150000.0 como 150000.
     */
    public function test_a_json_number_with_a_zero_fraction_is_rejected(): void
    {
        $clinic = $this->createClinic();
        $this->actAsServiceManager($clinic);

        $response = $this->call('POST', '/api/services', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], '{"name": "Consulta", "category": "consultation", "standard_price": 150000.0}');

        $response->assertUnprocessable()->assertJsonPath('errors.standard_price.0', self::PRICE_MESSAGE);
        $this->assertSame(0, DB::table('services')->count());
    }

    public function test_the_limits_of_the_price_are_accepted(): void
    {
        $clinic = $this->createClinic();
        $this->actAsServiceManager($clinic);

        $this->postJson('/api/services', ['name' => 'Sin cargo', 'category' => 'other', 'standard_price' => 0])
            ->assertCreated()
            ->assertJsonPath('data.standard_price', '0.00');
        $this->postJson('/api/services', ['name' => 'Internación prolongada', 'category' => 'hospitalization', 'standard_price' => '999999999999'])
            ->assertCreated()
            ->assertJsonPath('data.standard_price', '999999999999.00');
        $this->postJson('/api/services', ['name' => 'Internación máxima', 'category' => 'hospitalization', 'standard_price' => 999999999999])
            ->assertCreated()
            ->assertJsonPath('data.standard_price', '999999999999.00');

        $this->postJson('/api/services', ['name' => 'Sin precio', 'category' => 'other'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.standard_price.0', 'El precio es obligatorio.');
    }

    public static function invalidDurations(): array
    {
        return [
            'cero' => [0, 'La duración debe estar entre 5 y 720 minutos.'],
            'menos de 5' => [4, 'La duración debe estar entre 5 y 720 minutos.'],
            'más de 720' => [725, 'La duración debe estar entre 5 y 720 minutos.'],
            'no múltiplo de 5' => [7, 'La duración debe ser un múltiplo de 5 minutos.'],
            'con decimales' => [7.5, 'La duración debe ser un número entero de minutos.'],
            'texto' => ['media hora', 'La duración debe ser un número entero de minutos.'],
            'nula' => [null, 'La duración es obligatoria.'],
        ];
    }

    #[DataProvider('invalidDurations')]
    public function test_the_duration_goes_from_5_to_720_in_multiples_of_5(mixed $duration, string $message): void
    {
        $clinic = $this->createClinic();
        $this->actAsServiceManager($clinic);

        $this->postJson('/api/services', ['name' => 'Consulta', 'category' => 'consultation', 'standard_price' => 1, 'duration_minutes' => $duration])
            ->assertUnprocessable()
            ->assertJsonPath('errors.duration_minutes.0', $message);
    }

    public function test_the_limits_of_the_duration_the_tax_rate_and_the_category(): void
    {
        $clinic = $this->createClinic();
        $this->actAsServiceManager($clinic);

        foreach ([[5, 0], [720, 5], [60, 10]] as $i => [$duration, $taxRate]) {
            $this->postJson('/api/services', ['name' => "Servicio {$i}", 'category' => 'laboratory', 'standard_price' => 1, 'duration_minutes' => $duration, 'tax_rate' => $taxRate])
                ->assertCreated()
                ->assertJsonPath('data.duration_minutes', $duration)
                ->assertJsonPath('data.tax_rate', $taxRate);
        }

        $this->postJson('/api/services', ['name' => 'Otro', 'category' => 'spa', 'standard_price' => 1, 'tax_rate' => 7, 'status' => 'retired'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.category.0', 'La categoría no es válida: debe ser una de consultation, vaccination, surgery, laboratory, imaging, hospitalization, grooming, other.')
            ->assertJsonPath('errors.tax_rate.0', 'La tasa de IVA no es válida: debe ser 0, 5 o 10.')
            ->assertJsonPath('errors.status.0', 'El estado no es válido: debe ser active (activo) o inactive (inactivo).');
    }

    public static function invalidNames(): array
    {
        return [
            'vacío' => ['', 'El nombre del servicio es obligatorio.'],
            'una letra' => ['A', 'El nombre del servicio debe tener al menos 2 caracteres.'],
            'más de 120' => [str_repeat('a', 121), 'El nombre del servicio no debe tener más de 120 caracteres.'],
            'sin letras' => ['--', 'El nombre del servicio debe tener al menos una letra y solo puede contener letras, números, espacios, puntos, guiones, apóstrofos y paréntesis.'],
            'con barra' => ['Consulta/urgencia', 'El nombre del servicio debe tener al menos una letra y solo puede contener letras, números, espacios, puntos, guiones, apóstrofos y paréntesis.'],
        ];
    }

    #[DataProvider('invalidNames')]
    public function test_the_name_is_validated(string $name, string $message): void
    {
        $clinic = $this->createClinic();
        $this->actAsServiceManager($clinic);

        $this->postJson('/api/services', ['name' => $name, 'category' => 'consultation', 'standard_price' => 1])
            ->assertUnprocessable()
            ->assertJsonPath('errors.name.0', $message);
    }

    /**
     * La recepción y el veterinario listan y ven (services.index y services.show) y
     * reciben 403 al escribir.
     */
    public function test_the_receptionist_and_the_veterinarian_list_and_see_but_do_not_write(): void
    {
        $clinic = $this->createClinic();
        $service = $this->serviceIn($clinic);

        foreach (['recepcionista', 'veterinario'] as $role) {
            $this->actingAs($this->userWithRoles($clinic, [$role]), 'sanctum');

            $this->getJson('/api/services')->assertOk()->assertJsonPath('data.0.code', $service->code);
            $this->getJson("/api/services/{$service->id}")->assertOk()->assertJsonPath('data.standard_price', '150000.00');
            $this->assertErrorCode($this->postJson('/api/services', ['name' => 'Nuevo', 'category' => 'other', 'standard_price' => 1]), 403, 'FORBIDDEN');
            $this->assertErrorCode($this->patchJson("/api/services/{$service->id}", ['standard_price' => 1]), 403, 'FORBIDDEN');
            $this->assertErrorCode($this->deleteJson("/api/services/{$service->id}"), 403, 'FORBIDDEN');
        }

        $this->assertSame(1, DB::table('services')->count());
        $this->assertNull(DB::table('services')->value('deleted_at'));
        $this->assertSame('150000.00', CurrentClinic::run($clinic, fn () => Service::value('standard_price')));
    }
}
