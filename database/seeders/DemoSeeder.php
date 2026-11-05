<?php

namespace Database\Seeders;

use App\Enums\ClientKind;
use App\Enums\DocumentSeries;
use App\Enums\PatientSex;
use App\Models\Breed;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Clinic;
use App\Models\Patient;
use App\Models\Species;
use App\Models\User;
use App\Models\Veterinarian;
use App\Services\ClientService;
use App\Services\ClinicService;
use App\Services\DocumentSequenceService;
use App\Services\PatientService;
use App\Support\CurrentClinic;
use App\Support\UserPassword;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Datos de demostración para desarrollo y Postman (entrega 0.4): dos clínicas, cada
 * una con un usuario por rol (administrador, recepcionista y veterinario con su
 * ficha), clientes y pacientes. Los usuarios están en docs/testing/guia-postman.md.
 *
 * - Solo en local y testing: en otro entorno se niega (crea usuarios con una
 *   contraseña conocida).
 * - La contraseña de todos los usuarios sale de DEMO_PASSWORD (config/demo.php):
 *   sin ella, o si no cumple la regla de contraseñas, se niega.
 * - Idempotente: lo que ya existe (la clínica por su RUC, el usuario por su email,
 *   el cliente por su documento y el paciente por su dueño y su nombre) no se
 *   vuelve a crear ni se modifica.
 * - Por los Services: ClinicService, ClientService y PatientService. Los usuarios
 *   recepcionista y veterinario, y la ficha del veterinario, con los modelos: el
 *   ABM de usuarios y de veterinarios es de la FASE 2.
 *
 * php artisan db:seed --class=DemoSeeder (después de migrate --seed).
 */
class DemoSeeder extends Seeder
{
    /**
     * El RUC va sin el dígito verificador: lo calcula ClinicService::rucCheckDigit.
     */
    public const CLINICS = [
        [
            'name' => 'Clínica A',
            'legal_name' => 'Clínica Veterinaria A S.A.',
            'ruc' => '80009735',
            'users' => [
                'administrador' => ['admin-a@example.com', 'Ana', 'Gómez'],
                'recepcionista' => ['recepcion-a@example.com', 'Rosa', 'Benítez'],
                'veterinario' => ['vet-a@example.com', 'Víctor', 'Acosta'],
            ],
            'license' => 'MV-1001',
            'clients' => [
                ['kind' => 'person', 'document_number' => '5100001', 'first_name' => 'Laura', 'last_name' => 'Martínez', 'phone' => '+595981100001', 'email' => 'laura.martinez@example.com', 'city' => 'Asunción', 'patients' => [
                    ['name' => 'Firulais', 'species' => 'Canino', 'breed' => 'Mestizo', 'sex' => 'male'],
                    ['name' => 'Mishi', 'species' => 'Felino', 'breed' => 'Siamés', 'sex' => 'female'],
                ]],
                ['kind' => 'person', 'document_number' => '5100002', 'first_name' => 'Carlos', 'last_name' => 'Duarte', 'phone' => '+595981100002', 'email' => 'carlos.duarte@example.com', 'city' => 'Luque', 'patients' => [
                    ['name' => 'Rocky', 'species' => 'Canino', 'breed' => 'Labrador Retriever', 'sex' => 'male'],
                ]],
                ['kind' => 'company', 'document_number' => '80024680-1', 'business_name' => 'Granja San Jorge S.R.L.', 'phone' => '+595211100003', 'email' => 'contacto@granjasanjorge.example.com', 'city' => 'San Lorenzo', 'patients' => [
                    ['name' => 'Lucero', 'species' => 'Equino', 'breed' => 'Criollo', 'sex' => 'female'],
                ]],
            ],
        ],
        [
            'name' => 'Clínica B',
            'legal_name' => 'Clínica Veterinaria B S.A.',
            'ruc' => '80012345',
            'users' => [
                'administrador' => ['admin-b@example.com', 'Beto', 'Ruiz'],
                'recepcionista' => ['recepcion-b@example.com', 'Mirta', 'Giménez'],
                'veterinario' => ['vet-b@example.com', 'Diego', 'Fernández'],
            ],
            'license' => 'MV-2001',
            'clients' => [
                ['kind' => 'person', 'document_number' => '5200001', 'first_name' => 'Patricia', 'last_name' => 'Ortiz', 'phone' => '+595982200001', 'email' => 'patricia.ortiz@example.com', 'city' => 'Encarnación', 'patients' => [
                    ['name' => 'Luna', 'species' => 'Felino', 'breed' => 'Persa', 'sex' => 'female'],
                ]],
                ['kind' => 'person', 'document_number' => '5200002', 'first_name' => 'Jorge', 'last_name' => 'Villalba', 'phone' => '+595982200002', 'email' => 'jorge.villalba@example.com', 'city' => 'Ciudad del Este', 'patients' => [
                    ['name' => 'Toby', 'species' => 'Canino', 'breed' => 'Beagle', 'sex' => 'male'],
                    ['name' => 'Kira', 'species' => 'Canino', 'breed' => 'Pastor Alemán', 'sex' => 'female'],
                ]],
            ],
        ],
    ];

    public function run(
        ClinicService $clinics,
        ClientService $clients,
        PatientService $patients,
        DocumentSequenceService $sequences
    ): void {
        $password = $this->password();

        // Roles, permisos y especies: los necesita y son idempotentes.
        $this->call([RolesAndPermissionsSeeder::class, SpeciesSeeder::class]);

        foreach (self::CLINICS as $data) {
            DB::transaction(function () use ($data, $password, $clinics, $clients, $patients, $sequences) {
                $clinic = $this->clinic($data, $password, $clinics);

                CurrentClinic::run($clinic, function () use ($data, $password, $clients, $patients, $sequences) {
                    $this->user($data['users']['recepcionista'], 'recepcionista', $password);

                    $vet = $this->user($data['users']['veterinario'], 'veterinario', $password);
                    $this->veterinarian($vet, $data['license'], $sequences);

                    foreach ($data['clients'] as $clientData) {
                        $client = $this->client($clientData, $clients);

                        foreach ($clientData['patients'] as $patientData) {
                            $this->patient($client, $patientData, $patients);
                        }
                    }
                });
            });
        }
    }

    /**
     * Se niega fuera de local y testing, sin DEMO_PASSWORD o con una que no cumple la
     * regla. El mensaje nunca incluye la contraseña.
     */
    private function password(): string
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException(
                'El DemoSeeder solo corre en los entornos local y testing: crea usuarios con una contraseña conocida.'
            );
        }

        $password = (string) config('demo.password');

        if ($password === '') {
            throw new RuntimeException('Definí DEMO_PASSWORD en .env: es la contraseña de los usuarios de demostración.');
        }

        if (($error = UserPassword::error($password)) !== null) {
            throw new RuntimeException("DEMO_PASSWORD no cumple la regla de contraseñas. {$error}");
        }

        return $password;
    }

    private function clinic(array $data, #[\SensitiveParameter] string $password, ClinicService $clinics): Clinic
    {
        $existing = Clinic::where('ruc', $data['ruc'])->first();

        if ($existing !== null) {
            return $existing;
        }

        [$email, $firstName, $lastName] = $data['users']['administrador'];

        return $clinics->create(
            [
                'name' => $data['name'],
                'legal_name' => $data['legal_name'],
                'ruc' => $data['ruc'],
                'ruc_dv' => (string) ClinicService::rucCheckDigit($data['ruc']),
            ],
            ['email' => $email, 'first_name' => $firstName, 'last_name' => $lastName, 'password' => $password]
        );
    }

    /**
     * Usuario de la clínica en contexto, en su sucursal por defecto y con su rol.
     *
     * @param array{0: string, 1: string, 2: string} $person
     */
    private function user(array $person, string $role, #[\SensitiveParameter] string $password): User
    {
        [$email, $firstName, $lastName] = $person;

        // Consulta global a propósito: el email es único en todo el sistema (User no
        // tiene scope de clínica).
        $existing = User::withTrashed()->where('email', User::normalizeEmail($email))->first();

        if ($existing !== null) {
            return $existing;
        }

        $branch = Branch::where('is_default', true)->firstOrFail();

        $user = new User([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'name' => "{$firstName} {$lastName}",
            'email' => $email,
            'password' => $password,
            'current_branch_id' => $branch->id,
        ]);
        $user->clinic_id = CurrentClinic::id();
        $user->save();

        $user->branches()->attach($branch->id);
        $user->assignRole($role);

        return $user;
    }

    private function veterinarian(User $user, string $license, DocumentSequenceService $sequences): void
    {
        if (Veterinarian::withTrashed()->where('user_id', $user->id)->exists()) {
            return;
        }

        $sequences->create(DocumentSeries::VETERINARIAN, fn (string $code) => Veterinarian::create([
            'user_id' => $user->id,
            'code' => $code,
            'license_number' => $license,
        ]));
    }

    private function client(array $data, ClientService $clients): Client
    {
        $existing = Client::where('document_number', $data['document_number'])->first();

        if ($existing !== null) {
            return $existing;
        }

        $fields = ['kind' => ClientKind::from($data['kind'])->value];

        foreach (['document_number', 'first_name', 'last_name', 'business_name', 'phone', 'email', 'city'] as $field) {
            if (isset($data[$field])) {
                $fields[$field] = $data[$field];
            }
        }

        return $clients->create($fields);
    }

    private function patient(Client $client, array $data, PatientService $patients): void
    {
        if ($client->patients()->where('name', $data['name'])->exists()) {
            return;
        }

        $species = Species::whereNull('clinic_id')->where('name', $data['species'])->firstOrFail();
        $breed = Breed::whereNull('clinic_id')->where('species_id', $species->id)->where('name', $data['breed'])->firstOrFail();

        $patients->create($client, [
            'name' => $data['name'],
            'species_id' => $species->id,
            'breed_id' => $breed->id,
            'sex' => PatientSex::from($data['sex'])->value,
        ]);
    }
}
