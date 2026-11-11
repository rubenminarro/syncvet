<?php

namespace Tests\Feature\Catalog;

use Laravel\Sanctum\Sanctum;
use Tests\Concerns\InteractsWithCatalog;
use Tests\TestCase;

/**
 * GET species y GET species/{species}/breeds (FASE 1, entrega 1.4): por defecto,
 * las activas con sus razas activas; include_inactive=1 trae todas. Ni las de
 * otra clínica ni las eliminadas.
 */
class SpeciesIndexTest extends TestCase
{
    use InteractsWithCatalog;

    public function test_by_default_only_active_species_with_active_breeds(): void
    {
        $clinic = $this->createClinic();
        $this->actAsVet($clinic);
        $ferret = $this->createSpecies('Hurón');
        $this->createBreed($ferret, 'Angora de hurón');
        $this->createBreed($ferret, 'Albino', ['is_active' => false]);
        $this->createSpecies('Erizo', ['is_active' => false]);

        $species = collect($this->getJson('/api/species')->assertOk()->json('data'))->keyBy('name');

        $this->assertArrayHasKey('Canino', $species->all());
        $this->assertArrayNotHasKey('Erizo', $species->all());
        $this->assertSame(['Angora de hurón'], collect($species['Hurón']['breeds'])->pluck('name')->all());
    }

    public function test_include_inactive_brings_every_species_and_breed(): void
    {
        $clinic = $this->createClinic();
        $this->actAsVet($clinic);
        $ferret = $this->createSpecies('Hurón');
        $this->createBreed($ferret, 'Albino', ['is_active' => false]);
        $this->createSpecies('Erizo', ['is_active' => false]);

        $species = collect($this->getJson('/api/species?include_inactive=1')->assertOk()->json('data'))->keyBy('name');

        $this->assertFalse($species['Erizo']['is_active']);
        $this->assertSame(['Albino'], collect($species['Hurón']['breeds'])->pluck('name')->all());
        $this->assertFalse($species['Hurón']['breeds'][0]['is_active']);
    }

    public function test_the_response_keeps_the_previous_shape_and_adds_is_global(): void
    {
        $clinic = $this->createClinic();
        $this->actAsVet($clinic);
        $ferret = $this->createSpecies('Hurón');
        $breed = $this->createBreed($ferret, 'Albino');

        $species = collect($this->getJson('/api/species')->assertOk()->json('data'))->keyBy('name');

        $this->assertSame(['id', 'name', 'is_active', 'is_global', 'breeds'], array_keys($species['Canino']));
        $this->assertTrue($species['Canino']['is_global']);
        $this->assertSame(['id', 'species_id', 'name', 'is_active', 'is_global'], array_keys($species['Canino']['breeds'][0]));
        $this->assertFalse($species['Hurón']['is_global']);
        $this->assertSame(['id' => $breed, 'species_id' => $ferret, 'name' => 'Albino', 'is_active' => true, 'is_global' => false], $species['Hurón']['breeds'][0]);
    }

    public function test_species_of_another_clinic_and_deleted_ones_are_not_listed(): void
    {
        $other = $this->createClinic();
        $this->actAsVet($other);
        $this->createSpecies('Hurón ajeno');

        $clinic = $this->createClinic();
        $this->actAsVet($clinic);
        $deleted = $this->createSpecies('Erizo');
        $this->deleteJson("/api/species/{$deleted}")->assertOk();

        $names = collect($this->getJson('/api/species?include_inactive=1')->assertOk()->json('data'))->pluck('name');

        $this->assertNotContains('Hurón ajeno', $names);
        $this->assertSame([], $names->filter(fn ($name) => str_starts_with($name, 'Erizo'))->values()->all());
    }

    public function test_breeds_of_a_species(): void
    {
        $clinic = $this->createClinic();
        $this->actAsVet($clinic);
        $canine = $this->globalSpeciesId();
        $this->createBreed($canine, 'Raza propia');
        $this->createBreed($canine, 'Raza inactiva', ['is_active' => false]);

        $names = collect($this->getJson("/api/species/{$canine}/breeds")->assertOk()->json('data'))->pluck('name');
        $this->assertContains('Labrador Retriever', $names);
        $this->assertContains('Raza propia', $names);
        $this->assertNotContains('Raza inactiva', $names);
        $this->assertNotContains('Siamés', $names);

        $all = collect($this->getJson("/api/species/{$canine}/breeds?include_inactive=1")->assertOk()->json('data'))->pluck('name');
        $this->assertContains('Raza inactiva', $all);
    }

    public function test_breeds_of_a_species_of_another_clinic_answer_404(): void
    {
        $this->actAsVet($this->createClinic());
        $foreign = $this->createSpecies('Hurón ajeno');

        $this->actAsVet($this->createClinic());

        $this->assertErrorCode($this->getJson("/api/species/{$foreign}/breeds"), 404, 'RESOURCE_NOT_FOUND');
    }

    public function test_an_invalid_filter_answers_422(): void
    {
        $this->actAsVet($this->createClinic());

        $this->getJson('/api/species?include_inactive=x')->assertUnprocessable()->assertJsonValidationErrors('include_inactive');
    }

    public function test_the_receptionist_lists_but_does_not_write(): void
    {
        $clinic = $this->createClinic();
        Sanctum::actingAs($this->userWithRole($clinic, 'recepcionista'));

        $this->getJson('/api/species')->assertOk();
        $this->assertErrorCode($this->postJson('/api/species', ['name' => 'Hurón']), 403, 'FORBIDDEN');
    }
}
