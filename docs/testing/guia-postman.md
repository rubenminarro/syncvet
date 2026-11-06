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

Endpoints ya presentes en el paquete base; la guía se valida al cerrar la FASE 1.

| # | Request | Body | Esperado |
|---|---|---|---|
| 1 | `POST /api/clients` | `{"document_number":"99300001","first_name":"Ana","last_name":"Gómez","email":"ana@mailinator.com","phone":"+595981000000"}` | 201, `data.display_name` = "Ana Gómez" |
| 2 | `POST /api/clients` | `{"kind":"company","document_number":"99999905-2","business_name":"Haras del Sur SA","email":"haras@mailinator.com","phone":"+595981000001"}` | 201 |
| 3 | `POST /api/clients` con `first_name` en una empresa | | 422 `VALIDATION_ERROR` |
| 4 | `GET /api/species` | | 200, especies con sus razas |
| 5 | `POST /api/clients/{id}/patients` | `{"name":"Toby","species_id":<id de Canino en GET /api/species>,"birth_date":"2023-01-10"}` | 201, `data.code` = el siguiente de la serie de la clínica: con el `DemoSeeder` y `{{token}}` (clínica A, con cuatro pacientes), `PAC-000005` |
| 6 | `POST /api/clients/{id}/patients` con una raza de otra especie | | 422 `PATIENT_BREED_SPECIES_MISMATCH` |
| 7 | `POST /api/patients/{id}/change-owner` | `{"client_id": <empresa>}` | 200, `data.client.display_name` = "Haras del Sur SA" |
| 8 | `POST /api/patients/{id}/change-owner` con el mismo dueño | | 422 `PATIENT_SAME_OWNER` |
| 9 | `PATCH /api/patients/{id}` con `client_id` | | 422 `VALIDATION_ERROR` |
| 10 | `DELETE /api/clients/{id}` con pacientes | | 409 `CLIENT_IN_USE` |
| 11 | `GET /api/clients?search=Toby` | | 200, encuentra a Ana por su mascota |
| 12 | `POST /api/clients` con un RUC y el dígito verificador equivocado: el número de la fila 2 (`99999905`) con `-3` | Como el de la fila 1, con otro email | 422 `VALIDATION_ERROR`, `errors.document_number` = "El RUC no es válido: revisá el número y el dígito verificador." (entrega 1.2) |
| 13 | `PATCH /api/clients/{id}` de Ana con `{"kind":"company"}`, sin `business_name` | | 422 `VALIDATION_ERROR`, `errors.business_name` = "La razón social es obligatoria para una empresa." (entrega 1.2) |
| 14 | `POST /api/clients` con el documento de Ana como RUC (`99300001-0`) y con un cero adelante (`099300001`) | Como el de la fila 1, con otro email | 422 `VALIDATION_ERROR`, `errors.document_number` = "Este número de documento ya está en uso." (R-018) |
| 15 | `PATCH /api/clients/{id}` de Ana con `{"first_name":null}` | | 422, `errors.first_name` = ["El nombre es obligatorio."], un solo mensaje (R-018) |
| 16 | `PATCH /api/clients/{id}` de la empresa con `{"kind":"person","first_name":"Rosa","last_name":"Benítez","business_name":null}` | | 200, `data.display_name` = "Rosa Benítez" (R-018) |
| 17 | `GET /api/clients?per_page=abc`, `?per_page=100000` y `?search[]=x` | | 422 `VALIDATION_ERROR`, nunca 500 (R-018) |

### Validación de clientes con la colección (entrega 1.2, R-018)

La colección [`postman/syncvet-fase-01-clientes.postman_collection.json`](postman/syncvet-fase-01-clientes.postman_collection.json)
tiene los 21 pasos de la validación de clientes, en orden. Cada request lleva sus encabezados, su body y un test que verifica
el código HTTP y el dato clave. Los logins guardan los tokens (`token` y `token_admin`) y las altas y la búsqueda guardan los
ids que usan los pasos siguientes (`juan_id`, `empresa_id`, `laura_id`): no hay que copiar nada a mano.

**Preparación.** Recrear la base con el `DemoSeeder` (ver **Datos**, al comienzo) y levantar el servidor en el puerto 8001:

```bash
php artisan migrate:fresh --seed
php artisan db:seed --class=DemoSeeder
php artisan serve --port=8001
```

**Importar y correr.**

1. En Postman: **Import** → elegir el archivo de la colección.
2. En la colección, pestaña **Variables**: completar `demo_password` con la `DEMO_PASSWORD` del `.env` y guardar. `base_url`
   ya es `http://127.0.0.1:8001/api`.
3. **Run collection** (Collection Runner): correrla entera, en orden, sin cambiar el orden de los requests. Esperado: todos los
   tests en verde.

Corre sobre una base recién recreada: crea y da de baja clientes, así que una segunda corrida falla en las altas hasta volver
a recrear la base. Los emails nuevos usan `mailinator.com`: con `EMAIL_DNS_CHECK=true` (el valor del `.env`), la validación
consulta el MX del dominio y rechaza `example.com` (tiene un MX nulo) y los dominios sin servidor de correo.

Usuarios de la clínica A: recepción es `recepcion-a@example.com` (`{{token}}`); admin es `admin-a@example.com`
(`{{token_admin}}`). Laura Martínez (`99100001`, con Firulais) y Toby (clínica B) salen del `DemoSeeder`.

| # | Paso | Request | Usuario | Body | Esperado |
|---|---|---|---|---|---|
| 1 | Login | `POST /login` (dos requests: 01a y 01b) | — | `{"email":"recepcion-a@example.com","password":"{{demo_password}}"}` y lo mismo con `admin-a@example.com` | 200, `data.token` → `token` y `token_admin` |
| 2 | Alta de una persona | `POST /clients` | recepción | `{"document_number":"99300001","first_name":"Juan","last_name":"Pérez","email":"juan.perez@mailinator.com","phone":"+595981300001"}` | 201 "Cliente creado correctamente.", `display_name` = "Juan Pérez" → `juan_id` |
| 3 | Alta de una empresa | `POST /clients` | recepción | `{"kind":"company","document_number":"99999905-2","business_name":"Haras del Sur SA","email":"haras@mailinator.com","phone":"+595981300002"}` | 201, `display_name` = "Haras del Sur SA", nombre y apellido en `null` → `empresa_id` |
| 4 | Empresa con nombre | `POST /clients` | recepción | Una empresa (`99300003`) con `"first_name":"Juan"` | 422, `errors.first_name` = ["Una empresa no lleva nombre: usá la razón social."] |
| 5 | RUC con el dígito equivocado | `POST /clients` | recepción | Como el 2, con el número `99300002` y el dígito 8 (el correcto es 9) | 422, `errors.document_number` = ["El RUC no es válido: revisá el número y el dígito verificador."] |
| 6 | Documento repetido como cédula | `POST /clients` | recepción | Como el 2, con `"document_number":"099100001"` (Laura con un cero) | 422, `errors.document_number` = ["Este número de documento ya está en uso."] |
| 7 | Documento repetido como RUC | `POST /clients` (dos requests: 07a y 07b) | recepción | Como el 2, con `"99100001-3"` (Laura) y con `"99999905"` (la empresa del 3, sin dígito) | 422, el mismo mensaje en los dos |
| 8 | Edición | `PATCH /clients/{{juan_id}}` | recepción | `{"phone":"+595981399999","city":"Asunción"}` | 200 "Cliente actualizado correctamente.", con los datos nuevos |
| 9 | Email con mayúsculas | `PATCH /clients/{{juan_id}}` | recepción | `{"email":"Juan@Mailinator.com"}` | 422, `errors.email` = ["El correo debe estar en minúsculas."] |
| 10 | Nombre vacío | `PATCH /clients/{{juan_id}}` | recepción | `{"first_name":null}` | 422, `errors.first_name` = ["El nombre es obligatorio."], un solo mensaje |
| 11 | Nombre en la empresa | `PATCH /clients/{{empresa_id}}` | recepción | `{"first_name":"Juan"}` | 422, "Una empresa no lleva nombre: usá la razón social." |
| 12 | Cambio de tipo sin razón social | `PATCH /clients/{{juan_id}}` | recepción | `{"kind":"company"}` | 422, `errors.business_name` = ["La razón social es obligatoria para una empresa."] |
| 13 | Cambio de tipo | `PATCH /clients/{{juan_id}}` | recepción | `{"kind":"company","business_name":"Pérez Hnos SA","first_name":null,"last_name":null}` | 200, `kind.value` = `company`, `display_name` = "Pérez Hnos SA" |
| 14 | Vuelta a persona | `PATCH /clients/{{juan_id}}` | recepción | `{"kind":"person","first_name":"Juan","last_name":"Pérez","business_name":null}` | 200, `display_name` = "Juan Pérez", `business_name` en `null` |
| 15 | Búsqueda por mascota | `GET /clients?search=Firulais` | recepción | — | 200, un resultado: Laura Martínez → `laura_id` |
| 16 | Mascota de otra clínica | `GET /clients?search=Toby` | recepción | — | 200, `meta.pagination.total` = 0 |
| 17 | Filtro mal formado | `GET /clients?per_page=abc` | recepción | — | 422 `VALIDATION_ERROR` con `errors.per_page`, nunca 500 |
| 18 | Baja por el recepcionista | `DELETE /clients/{{laura_id}}` | recepción | — | 403 `FORBIDDEN` |
| 19 | Baja con pacientes | `DELETE /clients/{{laura_id}}` | admin | — | 409 `CLIENT_IN_USE`, "El cliente tiene pacientes, consultas o facturas y no puede eliminarse." |
| 20 | Baja sin pacientes | `DELETE /clients/{{juan_id}}` y después `GET /clients/{{juan_id}}` (20a y 20b) | admin y recepción | — | 200 "Cliente eliminado correctamente."; después 404 `RESOURCE_NOT_FOUND` |
| 21 | Documento liberado | `POST /clients` | recepción | Como el 2, con `"document_number":"99300001-0"` | 201: la baja liberó el documento (también como RUC) y el email |
