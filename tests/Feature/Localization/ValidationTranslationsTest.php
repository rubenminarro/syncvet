<?php

namespace Tests\Feature\Localization;

use App\Models\Client;
use App\Models\Clinic;
use App\Models\Patient;
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

    public function test_every_line_keeps_the_placeholders_of_the_original(): void
    {
        $spanish = $this->messageLines($this->spanishLines());

        foreach ($this->messageLines($this->frameworkLines()) as $key => $line) {
            preg_match_all('/:[a-z_]+/', $line, $placeholders);

            foreach (array_unique($placeholders[0]) as $placeholder) {
                $this->assertStringContainsString($placeholder, $spanish[$key] ?? '', "validation.{$key} perdió {$placeholder}.");
            }
        }
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
                $this->assertTrue(Lang::has("validation.attributes.{$field}"), "{$class}: el campo {$field} no tiene nombre en español.");
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
        $this->actingIn($clinic, ['clients.index', 'clients.store', 'clients.update', 'patients.index', 'patients.store', 'patients.update', 'patients.change-owner']);

        $clientFields = ['kind', 'business_name', 'document_number', 'first_name', 'last_name', 'email', 'phone', 'address', 'city', 'notifications_enabled', 'notes'];
        $patientFields = ['name', 'species_id', 'breed_id', 'sex', 'is_neutered', 'birth_date', 'birth_date_estimated', 'color', 'weight_kg', 'microchip', 'allergies', 'notes', 'custom_fields', 'code'];

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
        }

        $requests[] = ['GET', '/api/clients?kind=x', []];
        $requests[] = ['GET', '/api/patients?species_id=x&include_deceased=x', []];

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

            if (! is_subclass_of($class, FormRequest::class)) {
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
                $rule instanceof Password => array_map(fn (string $key) => [$key], ['min', 'password.letters', 'password.mixed', 'password.numbers', 'password.symbols', 'password.uncompromised']),
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
}
