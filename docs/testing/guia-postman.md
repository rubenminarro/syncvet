# Guía de pruebas con Postman

Requests por fase, encadenados. Todos llevan `Accept: application/json` y, salvo el login, `Authorization: Bearer {{token}}`.
Las respuestas siguen el contrato de `CLAUDE.md` §4.

## FASE 0 — Autenticación

Entregas 0.3 y 0.4. Variables de la colección: `{{base_url}}` (por ejemplo, `http://syncvet.test`), `{{demo_password}}` (el
valor de `DEMO_PASSWORD`), `{{token}}` y `{{token_b}}`.

> **Validación manual:** hecha por el usuario el 2026-11-05 con los datos del `DemoSeeder` (entrega 0.4, R-012), con este alcance:
> login con un usuario de cada clínica (`recepcion-a` y `vet-b`, como las filas 2b y 2c) y `me` con el de la A, un paciente de otra clínica →
> 404 (como la 16, con `GET /api/patients/{id}`), cinco fallidos y el sexto → 429 (como la 11 y la 12, con la sexta también
> incorrecta), logout (como la 4) y `me` después → 401 (como la 5). Las
> demás filas no se recorrieron a mano: las respaldan los tests de `tests/Feature/Auth`, `tests/Feature/Tenancy` y
> `tests/Feature/Seeders`.

**Datos.** Los crea el `DemoSeeder`, solo en `local` (y en los tests). En `.env`, definir `DEMO_PASSWORD` (al menos 12 caracteres,
con mayúsculas, minúsculas, números y símbolos): es la contraseña de todos los usuarios de demostración. Después:

```bash
php artisan migrate:fresh --seed
php artisan db:seed --class=DemoSeeder
```

Es idempotente: correrlo otra vez no duplica nada ni cambia las contraseñas. Sobre una base sembrada antes de R-011 (con los
documentos de ejemplo anteriores) falla: recrearla con los dos comandos. Para usar otra `DEMO_PASSWORD`, repetir los dos
comandos. Solo usa sus clínicas (marcadas como de demostración, con RUC ficticios): si ya hay otra clínica con su RUC, o uno de
sus emails es de un usuario de otra clínica, falla sin tocar nada (R-010).

| Clínica | RUC | Rol | Email | Además |
|---|---|---|---|---|
| Clínica A | 99999901-0 | `administrador` | `admin-a@example.com` | |
| Clínica A | | `recepcionista` | `recepcion-a@example.com` | |
| Clínica A | | `veterinario` | `vet-a@example.com` | Ficha `VET-000001`, matrícula `MV-1001` |
| Clínica B | 99999902-8 | `administrador` | `admin-b@example.com` | |
| Clínica B | | `recepcionista` | `recepcion-b@example.com` | |
| Clínica B | | `veterinario` | `vet-b@example.com` | Ficha `VET-000001`, matrícula `MV-2001` |

Cada clínica tiene su sucursal `SUC-01` y su depósito `DEP-01`. Clientes: en la A, Laura Martínez (`99100001`, con Firulais y
Mishi), Carlos Duarte (`99100002`, con Rocky) y Granja San Jorge S.R.L. (`99999903-6`, con la yegua Lucero); en la B, Patricia
Ortiz (`99200001`, con Luna) y Jorge Villalba (`99200002`, con Toby y Kira). Todos los documentos son ficticios (R-011): RUC
`999999xx` con su dígito verificador y cédulas `99xxxxxx`. Los pacientes de cada clínica numeran desde
`PAC-000001`.

**Sesión.**

| # | Request | Body | Esperado |
|---|---|---|---|
| 1 | `POST /api/login` | `{"email":"admin-a@example.com","password":"{{demo_password}}"}` | 200; `data.token_type` = `Bearer`; `data.expires_at` 12 horas después, en UTC (`Z`); `data.user.clinic.name` = "Clínica A". Guardar `data.token` en `{{token}}` |
| 2 | `GET /api/me` con `{{token}}` | | 200: usuario, `clinic`, `current_branch` (`SUC-01`), `roles` = `["administrador"]`, `permissions` ordenados (los 119), `veterinarian` = `null` |
| 2b | `POST /api/login` con `recepcion-a@example.com` y `GET /api/me` con su token | | 200: `roles` = `["recepcionista"]`, solo los permisos de ese rol, `veterinarian` = `null` |
| 2c | `POST /api/login` con `vet-a@example.com` y `GET /api/me` con su token | | 200: `roles` = `["veterinario"]`, `veterinarian.code` = `VET-000001` |
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
| 9b | `POST /api/login` con el email en mayúsculas | `{"email":"ADMIN-A@Example.com","password":"{{demo_password}}"}` | 200: el email se guarda y se busca en minúsculas |
| 10 | `POST /api/login` sin `password` | `{"email":"admin-a@example.com"}` | 422 `VALIDATION_ERROR`, `errors.password` = "Ingresá tu contraseña." |
| 11 | El 8 cinco veces (el 9b dejó el contador en cero) | | 422 cada vez |
| 12 | `POST /api/login` con la contraseña correcta | igual al 1 | 429 `AUTH_TOO_MANY_ATTEMPTS`, "Demasiados intentos. Probá de nuevo en N segundos.", encabezado `Retry-After` |
| 12b | `POST /api/login` con una variante Unicode del email | `{"email":"ādmin-a@example.com","password":"{{demo_password}}"}` | 422 `VALIDATION_ERROR`, `errors.email` = "Ingresá un email sin acentos ni caracteres especiales." |
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

Se valida con dos colecciones de Postman, que son la fuente de verdad de los pasos: cada request lleva su nombre (con el código HTTP
esperado), sus encabezados, su body y un test que verifica el código HTTP y el dato clave. Esta guía no repite sus pasos ni tiene
requests propios para la FASE 1: una tabla aparte quedaba desactualizada y, si se seguía a mano, creaba datos que hacían fallar las
colecciones (R-021).

| Colección | Entrega | Qué valida |
|---|---|---|
| [`postman/syncvet-fase-01-clientes.postman_collection.json`](postman/syncvet-fase-01-clientes.postman_collection.json) | 1.2 (R-018); repetible desde la 1.3 (R-020) | Alta de persona y de empresa, RUC con el dígito verificador equivocado, documento repetido como cédula, con un cero o como RUC, edición, cambio de tipo, email en minúsculas, búsqueda por mascota, aislamiento entre clínicas, filtros mal formados, baja con pacientes (409) y sin ellos, y el documento liberado |
| [`postman/syncvet-fase-01-pacientes.postman_collection.json`](postman/syncvet-fase-01-pacientes.postman_collection.json) | 1.3 (R-020) | Alta bajo un cliente (el dueño sale de la URL, sin `custom_fields`), `code` prohibido, raza de otra especie, microchip repetido, peso, dueño en la edición, cambio de dueño (también de un fallecido y el 403 del veterinario), búsquedas, listado del cliente, fallecimiento en UTC, edición de un fallecido, listados sin y con fallecidos, baja y microchip liberado |

**Preparación.** Una base con el `DemoSeeder` y las migraciones al día (ver **Datos**, al comienzo): la primera vez, o después de
cambiar a una rama con migraciones nuevas, recrearla o correr `php artisan migrate`. Levantar el servidor en el puerto 8001:

```bash
php artisan serve --port=8001
```

**Importar y correr.**

1. En Postman: **Import** → elegir el archivo de la colección.
2. En la colección, pestaña **Variables**: completar `demo_password` con la `DEMO_PASSWORD` del `.env` y guardar. `base_url`
   ya es `http://127.0.0.1:8001/api`.
3. **Run collection** (Collection Runner): correrla entera, en orden, sin cambiar el orden de los requests. Esperado: todos los
   tests en verde.

**Se corren más de una vez sin recrear la base.** El primer request de cada colección genera un número de corrida y, con él, los
documentos (cédulas ficticias `99xxxxxx`, R-011, y su RUC con el dígito verificador), los emails y el microchip de esa corrida. Cada
colección usa un rango propio que no se cruza con el de la otra: clientes, de 500000 a 749998; pacientes, de 750000 a 999998. Los
logins guardan los tokens y los pasos guardan los ids que usan los siguientes: no hay que copiar nada a mano.

Los emails nuevos usan `mailinator.com`: con `EMAIL_DNS_CHECK=true` (el valor del `.env`), la validación consulta el MX del dominio y
rechaza `example.com` (tiene un MX nulo) y los dominios sin servidor de correo.

**Datos del `DemoSeeder` que usan.** Usuarios de la clínica A: `recepcion-a@example.com` (`{{token}}`), `admin-a@example.com`
(`{{token_admin}}`) y `vet-a@example.com` (`{{token_vet}}`). Laura Martínez (`99100001`, con Firulais) en la clínica A y Toby en la
clínica B. Para probar algo a mano, no crear en la clínica A otro paciente llamado Firulais o Toby: la colección de clientes espera
encontrar un solo Firulais y ningún Toby en esa clínica.

**Lo que no está en las colecciones.** `PATIENT_IN_USE` (409): la API todavía no crea consultas, aplicaciones ni turnos; lo prueba
`PatientDeleteTest`. La especie o raza inactiva: no hay ABM de especies hasta la 1.4; lo prueban `PatientStoreTest` y
`PatientUpdateTest`.

**Con Newman.** Las dos colecciones también corren desde la consola, sin el Runner (la contraseña va como variable de entorno,
que tiene prioridad sobre la de la colección):

```bash
npx newman run docs/testing/postman/syncvet-fase-01-clientes.postman_collection.json --env-var "demo_password=<DEMO_PASSWORD>"
npx newman run docs/testing/postman/syncvet-fase-01-pacientes.postman_collection.json --env-var "demo_password=<DEMO_PASSWORD>"
```
