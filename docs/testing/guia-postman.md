# Guía de pruebas con Postman

Requests por fase, encadenados. Todos llevan `Accept: application/json` y, salvo el login, `Authorization: Bearer {{token}}`.
Las respuestas siguen el contrato de `CLAUDE.md` §4.

## FASE 0 — Autenticación

Entrega 0.3. Variables de la colección: `{{base_url}}` (por ejemplo, `http://syncvet.test`), `{{token}}` y `{{token_b}}`.

> **Validación manual pendiente:** por decisión del usuario, esta sección se recorre con Postman junto con la entrega 0.4,
> cuando exista el `DemoSeeder`. Hasta entonces la respaldan los tests de `tests/Feature/Auth` y `tests/Feature/Tenancy`.

**Datos.** Dos clínicas, cada una con su administrador. Hasta el `DemoSeeder` de la entrega 0.4, se crean con `php artisan tinker`:

```php
$s = app(App\Services\ClinicService::class);
$s->create(['name' => 'Clínica A', 'legal_name' => 'Clínica A S.A.', 'ruc' => '80009735', 'ruc_dv' => '1'],
    ['email' => 'admin-a@example.com', 'first_name' => 'Ana', 'last_name' => 'Gómez', 'password' => 'secreta-123']);
$s->create(['name' => 'Clínica B', 'legal_name' => 'Clínica B S.A.', 'ruc' => '80012345', 'ruc_dv' => '0'],
    ['email' => 'admin-b@example.com', 'first_name' => 'Beto', 'last_name' => 'Ruiz', 'password' => 'secreta-123']);
```

**Sesión.**

| # | Request | Body | Esperado |
|---|---|---|---|
| 1 | `POST /api/login` | `{"email":"admin-a@example.com","password":"secreta-123"}` | 200; `data.token_type` = `Bearer`; `data.expires_at` 12 horas después, en UTC (`Z`); `data.user.clinic.name` = "Clínica A". Guardar `data.token` en `{{token}}` |
| 2 | `GET /api/me` con `{{token}}` | | 200: usuario, `clinic`, `current_branch` (`SUC-01`), `roles` = `["administrador"]`, `permissions` ordenados (los 119), `veterinarian` = `null` |
| 3 | `POST /api/login` otra vez (mismo usuario) | igual al 1 | 200 con otro token: las dos sesiones quedan vigentes. Guardarlo en `{{token_b}}` |
| 4 | `POST /api/logout` con `{{token}}` | | 200, `data` = `null` |
| 5 | `GET /api/me` con `{{token}}` | | 401 `UNAUTHENTICATED` (revocado) |
| 6 | `GET /api/me` con `{{token_b}}` | | 200: el logout revocó solo el token actual |
| 7 | `GET /api/me` sin `Authorization` | | 401 `UNAUTHENTICATED` |

**Credenciales e intentos de más.**

| # | Request | Body | Esperado |
|---|---|---|---|
| 8 | `POST /api/login` con contraseña incorrecta | `{"email":"admin-a@example.com","password":"otra"}` | 422 `INVALID_CREDENTIALS`, "El email o la contraseña no son correctos." |
| 9 | `POST /api/login` con un email que no existe | `{"email":"nadie@example.com","password":"otra"}` | 422 `INVALID_CREDENTIALS`, el mismo mensaje que el 8 |
| 9b | `POST /api/login` con el email en mayúsculas | `{"email":"ADMIN-A@Example.com","password":"secreta-123"}` | 200: el email se guarda y se busca en minúsculas |
| 10 | `POST /api/login` sin `password` | `{"email":"admin-a@example.com"}` | 422 `VALIDATION_ERROR`, `errors.password` = "Ingresá tu contraseña." |
| 11 | El 8 cinco veces (el 9b dejó el contador en cero) | | 422 cada vez |
| 12 | `POST /api/login` con la contraseña correcta | igual al 1 | 429 `AUTH_TOO_MANY_ATTEMPTS`, "Demasiados intentos. Probá de nuevo en N segundos.", encabezado `Retry-After` |
| 12b | `POST /api/login` con una variante Unicode del email | `{"email":"ādmin-a@example.com","password":"secreta-123"}` | 422 `VALIDATION_ERROR`, `errors.email` = "Ingresá un email sin acentos ni caracteres especiales." |
| 13 | Igual al 1 con `admin-b@example.com` | | 200: el bloqueo es por usuario e IP |
| 14 | Esperar un minuto y repetir el 1 | | 200; un login correcto deja el contador en cero |

**Separación entre clínicas** (pendiente de la entrega 0.2). Con `{{token}}` de la clínica A (repetir el 1) y `{{token_b}}` de la
clínica B (login con `admin-b@example.com`):

| # | Request | Body | Esperado |
|---|---|---|---|
| 15 | `POST /api/clients` con `{{token_b}}` | El cliente 1 de la FASE 1 | 201. Guardar `data.id` como `{{client_b}}` |
| 16 | `GET /api/clients/{{client_b}}` con `{{token}}` | | 404 `RESOURCE_NOT_FOUND`: un id de otra clínica no existe para A |
| 17 | `PATCH /api/clients/{{client_b}}` con `{{token}}` | `{"phone":"+595981999999"}` | 404 `RESOURCE_NOT_FOUND` |
| 18 | `GET /api/clients` con `{{token}}` | | 200 sin el cliente de B |
| 19 | `POST /api/clients` con `{{token}}` | El mismo cuerpo del 15 | 201: documento y email son únicos por clínica |
| 20 | En tinker: `App\Models\Clinic::where('name', 'Clínica B')->update(['status' => 'suspended'])`. Después, `GET /api/me` con `{{token_b}}` | | 403 `CLINIC_NOT_AVAILABLE` |
| 21 | `POST /api/login` con `admin-b@example.com` | | 403 `CLINIC_NOT_AVAILABLE`; no se crea el token. Queda `LOGIN_FAILED` con el motivo "clínica suspendida" y no cuenta como intento fallido |
| 22 | `POST /api/login` con `admin-b@example.com` y una contraseña incorrecta | | 422 `INVALID_CREDENTIALS`: sin la contraseña correcta no se revela la suspensión |
| 23 | `POST /api/logout` con `{{token_b}}` | | 200: con la clínica suspendida el usuario igual cierra su sesión |

## FASE 1 — Clientes y pacientes

Endpoints ya presentes en el paquete base; la guía se valida al cerrar la FASE 1.

| # | Request | Body | Esperado |
|---|---|---|---|
| 1 | `POST /api/clients` | `{"document_number":"1234567","first_name":"Ana","last_name":"Gómez","email":"ana@ejemplo.com.py","phone":"+595981000000"}` | 201, `data.display_name` = "Ana Gómez" |
| 2 | `POST /api/clients` | `{"kind":"company","document_number":"80012345-6","business_name":"Haras del Sur SA","email":"haras@ejemplo.com.py","phone":"+595981000001"}` | 201 |
| 3 | `POST /api/clients` con `first_name` en una empresa | | 422 `VALIDATION_ERROR` |
| 4 | `GET /api/species` | | 200, especies con sus razas |
| 5 | `POST /api/clients/{id}/patients` | `{"name":"Toby","species_id":1,"birth_date":"2023-01-10"}` | 201, `data.code` = `PAC-000001` |
| 6 | `POST /api/clients/{id}/patients` con una raza de otra especie | | 422 `PATIENT_BREED_SPECIES_MISMATCH` |
| 7 | `POST /api/patients/{id}/change-owner` | `{"client_id": <empresa>}` | 200, `data.client.display_name` = "Haras del Sur SA" |
| 8 | `POST /api/patients/{id}/change-owner` con el mismo dueño | | 422 `PATIENT_SAME_OWNER` |
| 9 | `PATCH /api/patients/{id}` con `client_id` | | 422 `VALIDATION_ERROR` |
| 10 | `DELETE /api/clients/{id}` con pacientes | | 409 `CLIENT_IN_USE` |
| 11 | `GET /api/clients?search=Toby` | | 200, encuentra a Ana por su mascota |
