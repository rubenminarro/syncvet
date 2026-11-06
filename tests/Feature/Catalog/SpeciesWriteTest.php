<?php

namespace Tests\Feature\Catalog;

use App\Services\SpeciesService;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithCatalog;
use Tests\TestCase;

/**
 * Alta y edición de especies (FASE 1, entrega 1.4): nombre único sin distinguir
 * mayúsculas ni acentos frente a las globales y a las propias; las globales no
 * se editan (403); el recepcionista no escribe.
 */
class SpeciesWriteTest extends TestCase
{
    use InteractsWithCatalog;

    public function test_the_vet_creates_an_own_species(): void
    {
        $clinic = $this->createClinic();
        $this->actAsVet($clinic);

        $response = $this->postJson('/api/species', ['name' => '  Hurón  '])->assertCreated();

        $response->assertJsonPath('data.name', 'Hurón')
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.is_global', false)
            ->assertJsonPath('data.breeds', []);
        $this->assertSame($clinic->id, $this->speciesRow($response->json('data.id'))->clinic_id);
    }

    public function test_a_species_can_be_created_inactive(): void
    {
        $this->actAsVet($this->createClinic());

        $this->postJson('/api/species', ['name' => 'Erizo', 'is_active' => false])->assertCreated()->assertJsonPath('data.is_active', false);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function namesOfGlobalSpecies(): array
    {
        return [
            'igual' => ['Canino'],
            'mayúsculas' => ['CANINO'],
            'minúsculas' => ['felino'],
            'sin acento' => ['Lagomorfo'],
            'con espacios' => ['  reptil '],
        ];
    }

    #[DataProvider('namesOfGlobalSpecies')]
    public function test_the_name_of_a_global_species_is_taken(string $name): void
    {
        $this->actAsVet($this->createClinic());

        $response = $this->postJson('/api/species', ['name' => $name]);

        $this->assertErrorCode($response, 422, 'VALIDATION_ERROR');
        $response->assertJsonPath('errors.name.0', SpeciesService::NAME_TAKEN);
    }

    public function test_the_name_of_an_own_species_is_taken_without_case_or_accents(): void
    {
        $this->actAsVet($this->createClinic());
        $this->createSpecies('Hurón');

        foreach (['Hurón', 'huron', 'HURÓN', 'Hurón '] as $name) {
            $this->assertErrorCode($this->postJson('/api/species', ['name' => $name]), 422, 'VALIDATION_ERROR');
        }

        $this->assertSame(1, DB::table('species')->whereNotNull('clinic_id')->count());
    }

    public function test_another_clinic_can_use_the_same_name(): void
    {
        $this->actAsVet($this->createClinic());
        $this->createSpecies('Hurón');

        $this->actAsVet($this->createClinic());

        $this->postJson('/api/species', ['name' => 'Hurón'])->assertCreated();
    }

    public function test_name_rules(): void
    {
        $this->actAsVet($this->createClinic());

        $this->postJson('/api/species', [])->assertUnprocessable()->assertJsonPath('errors.name.0', 'El nombre de la especie es obligatorio.');
        $this->postJson('/api/species', ['name' => 'H'])->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->postJson('/api/species', ['name' => str_repeat('a', 61)])->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->postJson('/api/species', ['name' => 'Hurón//deleted_1'])->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->postJson('/api/species', ['name' => 'Hurón', 'is_active' => 'x'])->assertUnprocessable()->assertJsonValidationErrors('is_active');
        $this->postJson('/api/species', ['name' => str_repeat('a', 60)])->assertCreated();
    }

    public function test_the_vet_renames_and_inactivates_an_own_species(): void
    {
        $this->actAsVet($this->createClinic());
        $id = $this->createSpecies('Hurón');

        $this->patchJson("/api/species/{$id}", ['name' => 'Hurón doméstico'])->assertOk()->assertJsonPath('data.name', 'Hurón doméstico');
        $this->putJson("/api/species/{$id}", ['is_active' => false])->assertOk()
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('data.name', 'Hurón doméstico');
    }

    public function test_renaming_keeps_its_own_name_with_other_case_and_rejects_another(): void
    {
        $this->actAsVet($this->createClinic());
        $id = $this->createSpecies('Hurón');
        $this->createSpecies('Erizo');

        $this->patchJson("/api/species/{$id}", ['name' => 'HURON'])->assertOk()->assertJsonPath('data.name', 'HURON');

        $response = $this->patchJson("/api/species/{$id}", ['name' => 'erizo']);
        $this->assertErrorCode($response, 422, 'VALIDATION_ERROR');
        $response->assertJsonPath('errors.name.0', SpeciesService::NAME_TAKEN);

        $this->assertErrorCode($this->patchJson("/api/species/{$id}", ['name' => 'Canino']), 422, 'VALIDATION_ERROR');
    }

    public function test_a_name_sent_empty_answers_only_required(): void
    {
        $this->actAsVet($this->createClinic());
        $id = $this->createSpecies('Hurón');

        $this->patchJson("/api/species/{$id}", ['name' => ''])->assertUnprocessable()
            ->assertJsonPath('errors.name', ['El nombre de la especie es obligatorio.']);
    }

    public function test_a_global_species_cannot_be_edited_nor_hidden(): void
    {
        $this->actAsVet($this->createClinic());
        $canine = $this->globalSpeciesId();

        $this->assertErrorCode($this->patchJson("/api/species/{$canine}", ['is_active' => false]), 403, 'CATALOG_GLOBAL_NOT_EDITABLE');
        // 403 antes que el nombre repetido: la global no se edita.
        $this->assertErrorCode($this->patchJson("/api/species/{$canine}", ['name' => 'Felino']), 403, 'CATALOG_GLOBAL_NOT_EDITABLE');

        $this->assertTrue((bool) $this->speciesRow($canine)->is_active);
        $this->assertSame('Canino', $this->speciesRow($canine)->name);
    }

    public function test_a_species_of_another_clinic_answers_404(): void
    {
        $this->actAsVet($this->createClinic());
        $foreign = $this->createSpecies('Hurón');

        $this->actAsVet($this->createClinic());

        $this->assertErrorCode($this->patchJson("/api/species/{$foreign}", ['name' => 'Otro hurón']), 404, 'RESOURCE_NOT_FOUND');
        $this->assertSame('Hurón', $this->speciesRow($foreign)->name);
    }

    public function test_the_receptionist_cannot_write(): void
    {
        $clinic = $this->createClinic();
        $this->actAsVet($clinic);
        $id = $this->createSpecies('Hurón');
        Sanctum::actingAs($this->userWithRole($clinic, 'recepcionista'));

        $this->assertErrorCode($this->postJson('/api/species', ['name' => 'Erizo']), 403, 'FORBIDDEN');
        $this->assertErrorCode($this->patchJson("/api/species/{$id}", ['name' => 'Erizo']), 403, 'FORBIDDEN');
        $this->assertErrorCode($this->deleteJson("/api/species/{$id}"), 403, 'FORBIDDEN');
    }

    public function test_changes_are_audited(): void
    {
        $this->actAsVet($this->createClinic());
        $id = $this->createSpecies('Hurón');
        $this->patchJson("/api/species/{$id}", ['is_active' => false])->assertOk();

        $this->assertSame(
            ['created', 'updated'],
            DB::table('audit_logs')->where('auditable_type', 'species')->where('auditable_id', $id)->orderBy('id')->pluck('event')->all()
        );
    }
}
