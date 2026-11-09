<?php

namespace Tests\Feature\Localization;

use App\Models\Branch;
use App\Models\Client;
use App\Models\Clinic;
use App\Models\Patient;
use App\Models\Service;
use App\Models\User;
use App\Models\Veterinarian;
use App\Models\Warehouse;
use App\Rules\RucCheckDigit;
use App\Rules\UniqueClientDocument;
use App\Rules\WholeAmount;
use App\Support\CurrentClinic;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Routing\Route;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\In;
use Illuminate\Validation\Rules\NotIn;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Rules\ProhibitedIf;
use Illuminate\Validation\Rules\RequiredIf;
use Illuminate\Validation\Rules\Unique;
use Symfony\Component\Finder\Finder;
use Tests\Concerns\InteractsWithClinics;
use Tests\TestCase;

/**
 * Traducciones de validación (FASE 1, entrega 1.1). El idioma de la aplicación y
 * el de respaldo son "es" y Laravel no trae traducciones al español: sin
 * lang/es/validation.php, una regla sin mensaje propio devuelve la clave cruda
 * ("validation.string").
 */
class ValidationTranslationsTest extends TestCase
{
    use InteractsWithClinics;

    /**
     * Reglas que no producen un mensaje propio.
     */
    private const RULES_WITHOUT_MESSAGE = ['sometimes', 'nullable', 'bail'];

    public function test_the_spanish_file_has_exactly_the_framework_keys(): void
    {
        $english = array_keys($this->messageLines($this->frameworkLines()));
        $spanish = array_keys($this->messageLines($this->spanishLines()));

        $this->assertSame([], array_values(array_diff($english, $spanish)), 'Faltan claves en lang/es/validation.php.');
        $this->assertSame([], array_values(array_diff($spanish, $english)), 'Sobran claves en lang/es/validation.php.');
    }

    public function test_no_line_is_left_in_english(): void
    {
        $english = $this->messageLines($this->frameworkLines());

        foreach ($this->messageLines($this->spanishLines()) as $key => $line) {
            $this->assertNotSame($english[$key] ?? null, $line, "validation.{$key} sigue en inglés.");
        }
    }

    /**
     * Los mismos marcadores que el original, ni uno más ni uno menos: un marcador
     * cambiado (":values" por ":value") o inventado llegaría crudo al usuario
     * (deuda 10).
     */
    public function test_every_line_keeps_exactly_the_placeholders_of_the_original(): void
    {
        $spanish = $this->messageLines($this->spanishLines());

        foreach ($this->messageLines($this->frameworkLines()) as $key => $line) {
            $this->assertSame([], $this->placeholderDifferences($line, $spanish[$key] ?? ''), "validation.{$key} cambió sus marcadores.");
        }
    }

    public function test_a_changed_or_invented_placeholder_is_detected(): void
    {
        $original = 'The :attribute field must be greater than :value.';

        $this->assertSame([], $this->placeholderDifferences($original, 'El campo :attribute debe ser mayor que :value.'));
        $this->assertSame(['falta :value', 'sobra :values'], $this->placeholderDifferences($original, 'El campo :attribute debe ser mayor que :values.'));
        $this->assertSame(['sobra :atributo'], $this->placeholderDifferences($original, 'El campo :attribute (:atributo) debe ser mayor que :value.'));
        $this->assertSame(['falta :attribute'], $this->placeholderDifferences($original, 'El campo debe ser mayor que :value.'));


        $this->assertSame([':attribute', ':value'], $this->placeholders('The :attribute field must be greater than :value.'));
        $this->assertSame([':attribute', ':values'], $this->placeholders('El campo :attribute debe ser mayor que :values.'));
        $this->assertSame([':atributo', ':attribute'], $this->placeholders('El campo :attribute (:atributo) es obligatorio.'));
        $this->assertSame([':Attribute', ':attribute'], $this->placeholders(':Attribute es obligatorio: el campo :attribute.'));
        $this->assertSame([], $this->placeholders('Formato: hh:mm, a las 10:30.'));
    }

    /**
     * Toda validación de un pedido va en un Form Request, también la de los
     * filtros de los listados: así los tests de este archivo revisan su mensaje y
     * el nombre en español de cada campo (deuda 11).
     */
    public function test_controllers_do_not_validate_outside_form_requests(): void
    {
        $found = [];

        foreach ((new Finder())->files()->in(app_path('Http/Controllers'))->name('*.php') as $file) {
            foreach (file($file->getPathname()) as $number => $line) {
                if (preg_match('/->validate(WithBag)?\s*\(|\bvalidator\s*\(|Validator::make\s*\(/', $line)) {
                    $found[] = $file->getRelativePathname() . ':' . ($number + 1) . ' ' . trim($line);
                }
            }
        }

        $this->assertSame([], $found, 'Validación fuera de un Form Request.');
    }

    public function test_the_application_loads_the_spanish_lines(): void
    {
        $this->assertSame('es', app()->getLocale());
        $this->assertSame('El campo color debe ser un texto.', __('validation.string', ['attribute' => 'color']));
    }

    /**
     * Cada regla de cada Form Request tiene un mensaje propio o una traducción.
     * Una regla de un tipo que el test no conoce lo hace fallar: hay que decidir
     * qué mensaje le corresponde.
     */
    public function test_every_rule_of_every_form_request_has_a_spanish_message(): void
    {
        foreach ($this->formRequests() as $class => [$rules, $messages]) {
            foreach ($rules as $field => $fieldRules) {
                foreach ($this->messageKeys($fieldRules, "{$class} → {$field}") as $alternatives) {
                    $this->assertNotEmpty(
                        array_filter($alternatives, fn (string $key) => isset($messages["{$field}.{$key}"]) || isset($messages[$key]) || Lang::has("validation.{$key}")),
                        "{$class}: la regla " . end($alternatives) . " de {$field} no tiene mensaje en español."
                    );
                }
            }
        }
    }

    public function test_every_field_of_every_form_request_has_a_spanish_name(): void
    {
        foreach ($this->formRequests() as $class => [$rules]) {
            foreach (array_keys($rules) as $field) {
                // Un elemento de una lista ("roles.*") lleva su comodín en la clave, como lo
                // busca el validador: Arr::dot de los nombres (FASE 2, entrega 2.2a).
                $this->assertTrue(
                    Lang::has("validation.attributes.{$field}") || array_key_exists($field, Arr::dot(Lang::get('validation.attributes'))),
                    "{$class}: el campo {$field} no tiene nombre en español."
                );
            }
        }
    }

    public function test_a_rule_without_its_own_message_uses_the_spanish_line(): void
    {
        $clinic = $this->createClinic();
        $patient = $this->patientOf($this->clientIn($clinic));
        $this->actingIn($clinic, ['patients.update']);

        $this->patchJson("/api/patients/{$patient->id}", ['color' => 5, 'allergies' => ['x']])
            ->assertUnprocessable()
            ->assertJsonPath('errors.color.0', 'El campo color debe ser un texto.')
            ->assertJsonPath('errors.allergies.0', 'El campo alergias debe ser un texto.');
    }

    /**
     * De punta a punta: pedidos inválidos a cada endpoint con validación; ningún
     * mensaje es una clave cruda.
     */
    public function test_invalid_requests_never_answer_a_raw_translation_key(): void
    {
        $clinic = $this->createClinic();
        $client = $this->clientIn($clinic);
        $patient = $this->patientOf($client);
        $this->actingIn($clinic, ['clients.index', 'clients.store', 'clients.update', 'patients.index', 'patients.store', 'patients.update', 'patients.change-owner', 'species.index', 'species.store', 'species.update', 'branches.index', 'branches.store', 'branches.update', 'warehouses.index', 'warehouses.store', 'warehouses.update', 'users.index', 'users.store', 'users.update', 'veterinarians.index', 'veterinarians.store', 'veterinarians.update', 'services.index', 'services.store', 'services.update', 'system-settings.update', 'system-settings.upload-logo', 'audit-logs.index']);
        [$species, $breed] = $this->ownSpeciesWithBreed($clinic, 'Hurón');
        [$branch, $warehouse] = CurrentClinic::run($clinic, function () {
            $branch = Branch::create(['code' => 'SUC-01', 'name' => 'Casa central', 'establishment' => '001', 'is_default' => true]);

            return [$branch, Warehouse::create(['branch_id' => $branch->id, 'code' => 'DEP-01', 'name' => 'Principal', 'is_default' => true])];
        });
        $branchFields = ['code', 'name', 'establishment', 'address', 'phone', 'is_default', 'is_active', 'warehouse_code', 'warehouse_name'];
        $warehouseFields = ['code', 'name', 'is_default', 'is_active', 'branch_id'];
        $colleague = User::factory()->for($clinic)->create();
        $userFields = ['first_name', 'last_name', 'email', 'password', 'roles', 'branch_ids', 'is_active'];
        $veterinarian = CurrentClinic::run($clinic, fn () => Veterinarian::create(['user_id' => $colleague->id, 'code' => 'VET-000001']));
        $service = CurrentClinic::run($clinic, fn () => Service::create(['code' => 'SRV-000001', 'name' => 'Consulta general']));
        $serviceFields = ['code', 'name', 'category', 'description', 'duration_minutes', 'standard_price', 'tax_rate', 'status'];
        $veterinarianFields = ['user_id', 'code', 'license_number', 'specialty', 'commission_percentage', 'status', 'notes'];

        $clientFields = ['kind', 'business_name', 'document_number', 'first_name', 'last_name', 'email', 'phone', 'address', 'city', 'notifications_enabled', 'notes'];
        $patientFields = ['name', 'species_id', 'breed_id', 'sex', 'is_neutered', 'birth_date', 'birth_date_estimated', 'color', 'weight_kg', 'microchip', 'allergies', 'notes', 'code'];

        $requests = [];

        foreach ($this->invalidValues() as $value) {
            $clientPayload = array_fill_keys($clientFields, $value);
            $patientPayload = array_fill_keys($patientFields, $value);

            $requests[] = ['POST', '/api/clients', $clientPayload];
            $requests[] = ['POST', '/api/clients', [...$clientPayload, 'kind' => 'company']];
            $requests[] = ['PATCH', "/api/clients/{$client->id}", $clientPayload];
            $requests[] = ['POST', "/api/clients/{$client->id}/patients", $patientPayload];
            $requests[] = ['PATCH', "/api/patients/{$patient->id}", [...$patientPayload, 'client_id' => $value, 'deceased_at' => $value]];
            $requests[] = ['POST', "/api/patients/{$patient->id}/change-owner", ['client_id' => $value]];
            $requests[] = ['POST', '/api/login', ['email' => $value, 'password' => $value]];
            $requests[] = ['POST', '/api/species', ['name' => $value, 'is_active' => $value]];
            $requests[] = ['PATCH', "/api/species/{$species}", ['name' => $value, 'is_active' => $value]];
            $requests[] = ['POST', "/api/species/{$species}/breeds", ['name' => $value, 'is_active' => $value]];
            $requests[] = ['PATCH', "/api/species/{$species}/breeds/{$breed}", ['name' => $value, 'is_active' => $value, 'species_id' => $value]];
            $requests[] = ['POST', '/api/branches', array_fill_keys($branchFields, $value)];
            $requests[] = ['PATCH', "/api/branches/{$branch->id}", array_fill_keys($branchFields, $value)];
            $requests[] = ['POST', "/api/branches/{$branch->id}/warehouses", array_fill_keys($warehouseFields, $value)];
            $requests[] = ['PATCH', "/api/warehouses/{$warehouse->id}", array_fill_keys($warehouseFields, $value)];
            $requests[] = ['POST', '/api/users', array_fill_keys($userFields, $value)];
            $requests[] = ['POST', '/api/users', [...array_fill_keys($userFields, $value), 'roles' => [$value, $value], 'branch_ids' => [$value, $value]]];
            $requests[] = ['PATCH', "/api/users/{$colleague->id}", array_fill_keys($userFields, $value)];
            $requests[] = ['POST', '/api/veterinarians', array_fill_keys($veterinarianFields, $value)];
            $requests[] = ['PATCH', "/api/veterinarians/{$veterinarian->id}", array_fill_keys($veterinarianFields, $value)];
            $requests[] = ['POST', '/api/services', array_fill_keys($serviceFields, $value)];
            $requests[] = ['PATCH', "/api/services/{$service->id}", array_fill_keys($serviceFields, $value)];
            $requests[] = ['PATCH', '/api/system-settings', [...array_fill_keys(['name', 'legal_name', 'ruc', 'ruc_dv'], $value), 'settings' => array_fill_keys(['contact_email', 'contact_phone', 'website', 'document_footer', 'logo_path', 'x'], $value)]];
            $requests[] = ['PATCH', '/api/system-settings', ['settings' => $value, 'ruc' => $value]];
            $requests[] = ['POST', '/api/system-settings/logo', ['logo' => $value]];
            $requests[] = ['PATCH', '/api/me/current-branch', ['branch_id' => $value]];
            $requests[] = ['PATCH', '/api/me/password', ['current_password' => $value, 'password' => $value]];
        }

        $requests[] = ['GET', '/api/clients?kind=x', []];
        $requests[] = ['GET', '/api/patients?species_id=x&include_deceased=x', []];
        $requests[] = ['GET', "/api/clients/{$client->id}/patients?include_deceased=x", []];
        $requests[] = ['GET', '/api/species?include_inactive=x', []];
        $requests[] = ['GET', "/api/species/{$species}/breeds?include_inactive=x", []];
        $requests[] = ['GET', '/api/branches?include_inactive=x&per_page=0', []];
        $requests[] = ['GET', "/api/branches/{$branch->id}/warehouses?include_inactive=x&page=0", []];
        $requests[] = ['GET', '/api/users?role=x&branch_id=x', []];
        $requests[] = ['GET', '/api/users?branch_id=999999&per_page=0', []];
        $requests[] = ['GET', '/api/users?include_inactive=x', []];
        $requests[] = ['GET', '/api/veterinarians?status=x&branch_id=x', []];
        $requests[] = ['GET', '/api/veterinarians?branch_id=999999&per_page=0', []];
        $requests[] = ['GET', '/api/veterinarians?include_inactive=x', []];
        $requests[] = ['GET', '/api/services?category=x&status=x&include_inactive=x', []];
        $requests[] = ['GET', '/api/audit-logs?from=x&to=x&user_id=x&event=x&auditable_type=x&auditable_id=x', []];
        $requests[] = ['GET', '/api/audit-logs?from=2026-11-09&to=2026-11-08&user_id=999999&auditable_id=1', []];

        foreach ($requests as [$method, $uri, $payload]) {
            $response = $this->json($method, $uri, $payload)->assertUnprocessable();

            foreach (Arr::flatten($response->json('errors')) as $message) {
                $this->assertStringStartsNotWith('validation.', $message, "{$method} {$uri}");
            }
        }
    }

    /**
     * Valores que hacen fallar reglas distintas: tipo, largo, formato.
     *
     * @return list<mixed>
     */
    private function invalidValues(): array
    {
        return [['x'], 12345, str_repeat('x', 2001), '!', '', 'x'];
    }

    /**
     * Las reglas y los mensajes de cada Form Request, armadas con una clínica en
     * contexto y los modelos de la ruta.
     *
     * @return array<class-string, array{0: array<string, mixed>, 1: array<string, string>}>
     */
    private function formRequests(): array
    {
        $clinic = $this->createClinic();
        $client = $this->clientIn($clinic);
        $patient = $this->patientOf($client);

        $requests = [];

        foreach ((new Finder())->files()->in(app_path('Http/Requests'))->name('*.php') as $file) {
            $class = 'App\\Http\\Requests\\' . $file->getBasename('.php');

            // Una base abstracta (PrivateImageRequest, FASE 2, entrega 2.5) se revisa en sus subclases.
            if (! is_subclass_of($class, FormRequest::class) || (new \ReflectionClass($class))->isAbstract()) {
                continue;
            }

            $requests[$class] = $this->rulesAndMessages($class, $clinic, $client, $patient);
        }

        $this->assertNotEmpty($requests);

        return $requests;
    }

    /**
     * @param class-string<FormRequest> $class
     * @return array{0: array<string, mixed>, 1: array<string, string>}
     */
    private function rulesAndMessages(string $class, Clinic $clinic, Client $client, Patient $patient): array
    {
        $request = $class::create('/', 'POST');
        $route = (new Route('POST', '/', []))->bind($request);
        $route->setParameter('client', $client);
        $route->setParameter('patient', $patient);
        $request->setRouteResolver(fn () => $route);

        return CurrentClinic::run($clinic, fn () => [$request->rules(), Arr::dot($request->messages())]);
    }

    /**
     * Las claves de mensaje de las reglas de un campo ("required", "min"…). Cada
     * regla da una lista de claves alternativas: una regla objeto admite su clase
     * como clave del mensaje propio (Enum::class) o la traducción ("enum").
     *
     * @return list<list<string>>
     */
    private function messageKeys(mixed $rules, string $where): array
    {
        $keys = [];

        foreach (is_string($rules) ? explode('|', $rules) : $rules as $rule) {
            if (is_string($rule)) {
                $name = Str::snake(Str::before($rule, ':'));

                if (! in_array($name, self::RULES_WITHOUT_MESSAGE, true)) {
                    $keys[] = [$name];
                }

                continue;
            }

            array_push($keys, ...match (true) {
                $rule instanceof Enum => [[Enum::class, 'enum']],
                $rule instanceof Unique => [['unique']],
                $rule instanceof Exists => [['exists']],
                $rule instanceof RequiredIf => [['required']],
                $rule instanceof ProhibitedIf => [['prohibited']],
                $rule instanceof In => [['in']],
                $rule instanceof NotIn => [['not_in']],
                // Trae su propio mensaje (RucCheckDigit::MESSAGE); VoseoTest lo revisa.
                $rule instanceof RucCheckDigit => [],
                // Trae su propio mensaje, con el nombre del campo (entrega 2.4); VoseoTest lo revisa.
                $rule instanceof WholeAmount => [],
                // Usa el mensaje "unique" del campo en el Request (R-018).
                $rule instanceof UniqueClientDocument => [['unique']],
                $rule instanceof Password =>array_map(fn (string $key) => [$key], ['min', 'password.letters', 'password.mixed', 'password.numbers', 'password.symbols', 'password.uncompromised']),
                default => $this->fail("{$where}: regla sin clasificar (" . get_debug_type($rule) . '); agregala a messageKeys().'),
            });
        }

        return $keys;
    }

    /**
     * @return array<string, mixed>
     */
    private function frameworkLines(): array
    {
        return require base_path('vendor/laravel/framework/src/Illuminate/Translation/lang/en/validation.php');
    }

    /**
     * @return array<string, mixed>
     */
    private function spanishLines(): array
    {
        return require lang_path('es/validation.php');
    }

    /**
     * Los mensajes por regla, con las claves anidadas en puntos ("min.string"),
     * sin "custom" ni "attributes" (son propios de cada proyecto).
     *
     * @param array<string, mixed> $lines
     * @return array<string, string>
     */
    private function messageLines(array $lines): array
    {
        return Arr::dot(Arr::except($lines, ['custom', 'attributes']));
    }

    /**
     * Los marcadores que faltan o sobran en la traducción, frente al original.
     *
     * @return list<string>
     */
    private function placeholderDifferences(string $original, string $translation): array
    {
        $expected = $this->placeholders($original);
        $actual = $this->placeholders($translation);

        return [
            ...array_map(fn (string $p) => "falta {$p}", array_values(array_diff($expected, $actual))),
            ...array_map(fn (string $p) => "sobra {$p}", array_values(array_diff($actual, $expected))),
        ];
    }

    /**
     * Los marcadores de una línea (":attribute", ":Attribute", ":value"…), sin
     * repetir y ordenados. No cuenta los dos puntos dentro de una palabra o una
     * hora ("hh:mm", "10:30").
     *
     * @return list<string>
     */
    private function placeholders(string $line): array
    {
        preg_match_all('/(?<![\p{L}\d]):[a-zA-Z_]+/u', $line, $matches);

        $placeholders = array_values(array_unique($matches[0]));
        sort($placeholders);

        return $placeholders;
    }
}
