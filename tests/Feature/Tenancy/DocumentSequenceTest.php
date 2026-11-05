<?php

namespace Tests\Feature\Tenancy;

use App\Enums\DocumentSeries;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithClinics;
use Tests\TestCase;

/**
 * DocumentSequenceService: cada clínica numera sus códigos desde 1, de forma
 * independiente (la serie y la verificación del código filtran por clínica).
 */
class DocumentSequenceTest extends TestCase
{
    use InteractsWithClinics;

    public function test_each_clinic_numbers_patients_from_one(): void
    {
        $first = $this->createClinic();
        $second = $this->createClinic();
        $firstClient = $this->clientIn($first);
        $secondClient = $this->clientIn($second);

        $this->actingIn($first, ['patients.store']);
        $this->assertSame('PAC-000001', $this->createPatient($firstClient->id));
        $this->assertSame('PAC-000002', $this->createPatient($firstClient->id));

        $this->actingIn($second, ['patients.store']);
        $this->assertSame('PAC-000001', $this->createPatient($secondClient->id));

        $this->assertSame(
            [$first->id => 3, $second->id => 2],
            DB::table('document_sequences')
                ->where('key', DocumentSeries::PATIENT->value)
                ->pluck('next_value', 'clinic_id')
                ->map(fn ($value) => (int) $value)
                ->all()
        );
    }

    private function createPatient(int $clientId): string
    {
        return $this->postJson("/api/clients/{$clientId}/patients", [
            'name' => 'Toby',
            'species_id' => $this->globalSpeciesId(),
        ])->assertCreated()->json('data.code');
    }
}
