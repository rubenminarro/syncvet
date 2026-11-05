<?php

namespace Tests\Feature\Database;

use App\Models\Clinic;
use App\Services\ClinicService;
use Tests\TestCase;

/**
 * La ClinicFactory calcula sus RUC en el código, donde FictitiousDocumentsTest no
 * los ve: este test revisa lo que genera (R-013).
 */
class ClinicFactoryTest extends TestCase
{
    public function test_it_generates_fictitious_nine_digit_rucs_with_a_valid_check_digit(): void
    {
        $clinics = Clinic::factory()->count(200)->make();

        foreach ($clinics as $clinic) {
            // 9 dígitos: hoy no se emiten. Sin ceros a la izquierda, que el RUC no lleva.
            $this->assertMatchesRegularExpression('/^[1-9]\d{8}$/', $clinic->ruc);
            $this->assertSame((string) ClinicService::rucCheckDigit($clinic->ruc), $clinic->ruc_dv);
        }

        $this->assertCount(200, $clinics->pluck('ruc')->unique(), 'Los RUC se repiten.');
    }
}
