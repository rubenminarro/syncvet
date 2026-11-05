# FASE 0 — Puesta en marcha

Etapa: Base. Estado: **EN DESARROLLO**: entregas 0.1 (R-006), 0.2 (R-007) y 0.3 (R-008) cerradas; diseño aprobado el 2026-11-04 (R-005). El estado vigente de cada fase está en
[`docs/roadmap/README.md`](../roadmap/README.md).

## Objetivo

Proyecto instalado y corriendo, con clínicas separadas (D1), autenticación, la base de la arquitectura, tests y documentación.

## Entregas

El alcance inicial tenía siete entregas (0.1 a 0.7). El diseño las reagrupa en cinco, porque las piezas dependen entre sí: las
migraciones 000003 y 000004 y el modelo `User` necesitan Sanctum y Spatie para correr, y los tests de D1 necesitan `phpunit.xml`.
Cada entrega recorre los pasos 4 a 7 y deja la suite en verde.

| Entrega | Contenido | Antes era | Estado |
|---|---|---|---|
| 0.1 | Proyecto Laravel 13 sobre el paquete base, Sanctum y Spatie (solo configuración), `phpunit.xml` y base de tests, `ClinicRolesTest` | 0.1, 0.3, 0.5 y parte de 0.6 | COMPLETADO (R-006) |
| 0.2 | D1: `clinics`, `clinic_id`, scopes, fallo cerrado, `ClinicService::create` y tests de aislamiento | 0.2 | COMPLETADO (R-007) |
| 0.3 | Autenticación: login, logout y `me`; tokens de 12 horas; límite de intentos; auditoría | 0.4 y parte de 0.6 | COMPLETADO (R-008) |
| 0.4 | Comando `clinic:create` y datos de demostración | Nueva (P5) | PENDIENTE |
| 0.5 | Acceso por rol (`tests/Feature/Access`) y verificación final en MySQL | Parte de 0.6 y 0.7 | PENDIENTE |

## Preguntas de negocio

Las respuestas se anotan con la fecha y pasan a [`docs/decisions/registro.md`](../decisions/registro.md).

| # | Pregunta | Respuesta | Fecha |
|---|---|---|---|
| 1 | D1: ¿cómo se separan los datos de cada clínica? | Una base compartida con `clinic_id` (registro R-002) | 2026-11-04 |
| 2 | ¿Los usuarios entran con email o con nombre de usuario? | Con email, único en todo el sistema (R-004) | 2026-11-04 |
| 3 | ¿Los tokens vencen? ¿Cuánto duran? | Vencen a las 12 horas (una jornada). El logout revoca el token actual (R-004) | 2026-11-04 |
| 4 | ¿Un usuario puede trabajar en dos clínicas distintas? | No: pertenece a una sola clínica en el MVP. Sin *teams* de Spatie (R-004) | 2026-11-04 |
| 5 | ¿Quién da de alta una clínica nueva? | El administrador de la plataforma con `php artisan clinic:create`, que también crea el primer usuario administrador. Sin registro público ni rol de plataforma en el MVP (R-004) | 2026-11-04 |
| 6 | ¿Hay límite de intentos en el login? | Sí, 5 por minuto por email e IP, desde esta fase (R-004; cierra la deuda #4) | 2026-11-04 |

Las preguntas que dejó el diseño, ya respondidas, están al final, en [Preguntas del diseño](#preguntas-del-diseño).

## Regla de oro aplicada

Análisis de las entidades nuevas con [`docs/project/principios.md`](../project/principios.md).

| Pregunta | Clínica (`clinics`) | Usuario (`users`) | Token de acceso |
|---|---|---|---|
| ¿Quién la crea? | El administrador de la plataforma, con `clinic:create` | `clinic:create` (el primer administrador); el resto, el administrador de la clínica en la FASE 2 | El login |
| ¿Quién la modifica? | Nadie por API en el MVP; datos fiscales por comando o en la FASE 2 (a definir) | El administrador de la clínica (FASE 2) | Nadie |
| ¿Quién la usa? | Todo el sistema: es el contexto de cada consulta | Auditoría, `created_by`, caja, ficha de veterinario | Cada pedido autenticado |
| Estados | `ClinicStatus`: `active`, `suspended` | Activo o eliminado (`SoftDeletes`) | Vigente, vencido (12 h) o revocado (logout) |
| ¿Qué depende de ella? | Todas las tablas de negocio | Veterinario, caja, documentos emitidos, auditoría | Nada |
| Impacto económico | Es la unidad de facturación SIFEN (RUC) y del cobro del SaaS | Caja diaria por usuario (FASE 8) | Ninguno |
| ¿Qué pasa si se cancela? | Se suspende: sus usuarios reciben `CLINIC_NOT_AVAILABLE` (403) en el login y en cada pedido; los datos se conservan | — | — |
| ¿Qué pasa si se elimina? | No se elimina en el MVP (`restrictOnDelete` en todas las FK) | `SoftDeletes`: no puede entrar; sus registros históricos se conservan | Se borra la fila |
| Permisos | Ninguno por API: solo comando | `users.*` (FASE 2) | Ninguno (cualquier usuario autenticado) |
| `error_code` | `CLINIC_NOT_AVAILABLE` | `INVALID_CREDENTIALS`, `AUTH_TOO_MANY_ATTEMPTS` | `UNAUTHENTICATED` (vencido o revocado) |
| Consulta, stock, facturación | Toda consulta, movimiento de stock, factura y recordatorio queda en una clínica; el timbrado y el establecimiento SIFEN son de su sucursal | El usuario que crea, emite o anula | — |

## Diseño por entrega

### 0.1 Proyecto Laravel 13 sobre el paquete base

**Instalación** (R-004). El paquete ya está en la raíz del repositorio:

1. `composer create-project laravel/laravel:^13 <carpeta temporal>` fuera del repositorio.
2. Copiar a la raíz solo lo que el paquete no tiene. **Ante un choque gana el archivo del paquete**: se copia sin sobrescribir.

| Se trae del esqueleto | Ya está en el paquete (no se pisa) |
|---|---|
| `artisan`, `composer.json`, `composer.lock`, `.env.example`, `.gitignore`, `.gitattributes`, `.editorconfig` | `bootstrap/app.php` |
| `public/`, `storage/`, `resources/`, `bootstrap/providers.php`, `bootstrap/cache/` | `config/validation.php` |
| `config/*` salvo `validation.php` | `routes/api.php` |
| `routes/web.php`, `routes/console.php` | `database/migrations/0001_01_01_000000` a `000002` (mismo nombre que las del esqueleto) |
| `app/Http/Controllers/Controller.php` | `app/Providers/AppServiceProvider.php`, `app/Models/User.php` |
| `database/factories/UserFactory.php`, `database/seeders/DatabaseSeeder.php`, `tests/`, `phpunit.xml` | `database/seeders/RolesAndPermissionsSeeder.php` y `SpeciesSeeder.php` |

3. Verificar con `git status` que **ningún archivo versionado quedó modificado**: solo aparecen archivos nuevos.
4. `composer require laravel/sanctum spatie/laravel-permission`. Publicar **solo la configuración**:
   `vendor:publish --tag=sanctum-config` y `vendor:publish --provider="Spatie\Permission\PermissionServiceProvider" --tag=permission-config`.
   No se usa `install:api` (publica una migración que choca con la 000003 y agrega `routes/api.php`, que ya existe); lo que
   hace se completa a mano: `api:` en `withRouting` ya está en `bootstrap/app.php`.
5. Ajustes posteriores, cada uno sobre un archivo traído del esqueleto:

| Archivo | Cambio |
|---|---|
| `config/app.php` | `timezone` `America/Asuncion`, `locale` y `fallback_locale` `es` |
| `config/auth.php` | Guard `api` con driver `sanctum` (el guard de Spatie en el seeder y en `User::$guard_name` es `api`) |
| `config/permission.php` | Sin *teams* (`teams => false`, R-004) |
| `.env.example` | MySQL `syncvet` en Laragon, `EMAIL_DNS_CHECK=true`, `SANCTUM_EXPIRATION=720` |
| `database/seeders/DatabaseSeeder.php` | Llama a `RolesAndPermissionsSeeder` y `SpeciesSeeder` |
| `database/factories/UserFactory.php` | Columnas reales: `first_name`, `last_name`, `name` (sin `remember_token` ni `email_verified_at`, que el esquema no tiene) |

**Tests** (0.5 original):

| Pieza | Detalle |
|---|---|
| `phpunit.xml` | `DB_CONNECTION=sqlite`, `DB_DATABASE=database/testing.sqlite` (archivo, ignorado por Git), `CACHE_STORE=array`, `QUEUE_CONNECTION=sync`, `EMAIL_DNS_CHECK=false`, `BCRYPT_ROUNDS=4` |
| `tests/TestCase.php` | `RefreshDatabase` con `$seed = true`: migra y siembra roles y especies **una vez por ejecución**; cada test corre en una transacción. `setUp` limpia la caché de permisos de Spatie |
| `tests/Feature/ClinicRolesTest.php` | Cada permiso de `clinicRoles()` existe en `PERMISSIONS`; los permisos no se repiten; existen los roles `administrador`, `recepcionista` y `veterinario` con guard `api`; cada permiso de cada Controller (`permission:` en `HasMiddleware`) existe en `PERMISSIONS` |
| `tests/Feature/HealthTest.php` | `GET /up` responde 200; `GET /api/no-existe` responde JSON 404 `RESOURCE_NOT_FOUND` |

**Criterio de la entrega:** `php artisan migrate:fresh --seed` corre en SQLite y en MySQL; suite en verde; `git status` sin archivos
del paquete modificados salvo los listados.

### 0.2 Separación por clínica (D1)

Decidido en los registros R-002 y R-004.

**Migraciones.** Como el esquema no está en producción, `clinic_id` se agrega en las migraciones existentes.

| Pieza | Detalle |
|---|---|
| `0001_01_00_000000_create_clinics_table.php` | Migración nueva: ordena antes que `users`. Columnas: `name` (100), `legal_name` (150), `ruc` (20), `ruc_dv` (1), `timezone` (40, por defecto `America/Asuncion`), `status` (`string(20)`, `ClinicStatus`, por defecto `active`), timestamps. Sin `SoftDeletes`: una clínica se suspende, no se elimina. Con ella el esquema pasa a tener 28 migraciones |
| `clinic_id` obligatorio | `foreignId('clinic_id')->constrained('clinics')->restrictOnDelete()` en: `users`, `branches`, `warehouses`, `veterinarians`, `clients`, `patients`, `services`, `schedules`, `schedule_hours`, `schedule_blocks`, `appointments`, `products`, `product_stocks`, `product_lots`, `inventory_movements`, `consultations`, `consultation_attachments`, `consultation_items`, `patient_applications`, `document_templates`, `patient_documents`, `reminders`, `client_notifications`, `cash_registers`, `cash_movements`, `invoice_stamps`, `invoices`, `invoice_items`, `payments`, `sifen_events`, `system_settings`, `document_sequences` |
| `clinic_id` nulo | `species`, `breeds` (nulo = global) y `audit_logs` (nulo = login fallido con un email desconocido) |
| Sin `clinic_id` | `branch_user` (pivote), `password_reset_tokens`, `sessions`, `cache*`, `jobs`, `job_batches`, `failed_jobs`, `personal_access_tokens`, tablas de Spatie (roles comunes, R-002), `notifications` de Laravel |
| Migración 000005 | **Se quitan** las filas por defecto `SUC-01` y `DEP-01` (R-004); las crea `ClinicService::create` |

**Únicos que pasan a compuestos** (nombre propio si el automático supera 64 caracteres):

| Tabla | Antes | Después |
|---|---|---|
| `branches` | `code`; `establishment` | `(clinic_id, code)`; `(clinic_id, establishment)` |
| `warehouses` | `code` | `(clinic_id, code)` |
| `veterinarians` | `code`; `license_number` | `(clinic_id, code)`; `(clinic_id, license_number)`. `user_id` sigue único |
| `clients` | `document_number`; `email` | `(clinic_id, document_number)`; `(clinic_id, email)` |
| `species` | `name` | `(clinic_id, name)` + validación en la aplicación (ver catálogos) |
| `breeds` | `(species_id, name)` | `(clinic_id, species_id, name)` + validación en la aplicación |
| `patients` | `code`; `microchip` | `(clinic_id, code)`; `(clinic_id, microchip)` |
| `services`, `products` | `code`; `name` | `(clinic_id, code)`; `(clinic_id, name)` |
| `consultations` | `code` | `(clinic_id, code)`. `appointment_id` sigue único |
| `document_templates` | `(type, name)` | `(clinic_id, type, name)` |
| `client_notifications` | `dedupe_key` | `(clinic_id, dedupe_key)` |
| `invoice_stamps` | `inv_stamps_number_est_exp_type_unique` | `(clinic_id, stamp_number, establishment, expedition_point, document_type)` como `inv_stamps_clinic_number_est_exp_type_unique` |
| `invoices` | `number` | `(clinic_id, number)`. `cdc` sigue único global (R-002) |
| `payments` | `receipt_sequence`; `receipt_number` | `(clinic_id, receipt_sequence)`; `(clinic_id, receipt_number)` |
| `system_settings`, `document_sequences` | `key` | `(clinic_id, key)` |
| Sin cambio | `users.email` (login, R-004), `patient_documents.verification_code` (verificación pública), `product_stocks` y `product_lots` (sus claves ya son de una clínica), `failed_jobs.uuid`, `personal_access_tokens.token` | |

**Índices.** Los que empiezan por una clave foránea de la propia clínica (`patient_id`, `client_id`, `schedule_id`, `branch_id`,
`invoice_id`, `consultation_id`, `product_id`, `user_id`, `cash_register_id`) no cambian. Los que empiezan por una columna sin
clínica pasan a empezar por `clinic_id`: `clients (last_name, first_name)`, `services (category, status)`,
`products (type, status)`, `reminders (status, notify_at)`, `client_notifications (type, status)`,
`invoices (sifen_status, sifen_sent_at)`, `payments (payment_date, method)`, `audit_logs (event, created_at)` y
`audit_logs (created_at)`.

**Código.**

| Pieza | Detalle |
|---|---|
| `App\Enums\ClinicStatus` | `ACTIVE = 'active'`, `SUSPENDED = 'suspended'`, con `label()` y `values()` |
| `App\Models\Clinic` | Sin scope (es el contexto). `Auditable`. Relaciones `users()`, `branches()` |
| `App\Support\CurrentClinic` | Registrado como `scoped`. `set(Clinic)`, `get(): Clinic`, `id(): int` (lanza `ClinicContextMissingException` si no hay), `has(): bool`, `run(Clinic, Closure)` (fija, ejecuta y restaura el anterior, también si hay excepción) |
| `App\Exceptions\ClinicContextMissingException` | `LogicException`: es un error de programación, no de negocio. Responde el 500 genérico (`INTERNAL_SERVER_ERROR`) sin traza. Queda en el log con su origen: método y ruta del pedido, la clase del job o el nombre del comando, que sale del evento `CommandStarting` para que `Artisan::call` informe el comando real (R-005). Es la única excepción al "nunca 500", porque llegar ahí significa un bug |
| `App\Models\Scopes\ClinicScope` | `where(<tabla>.clinic_id, CurrentClinic::id())`; sin clínica, la excepción anterior |
| `App\Models\Scopes\SharedCatalogScope` | Para `Species` y `Breed`: `whereNull(clinic_id)->orWhere(clinic_id, id)` agrupado; sin clínica, la misma excepción (R-004) |
| `App\Models\Concerns\BelongsToClinic` | Agrega `ClinicScope`, define `clinic()`, y al crear fija `clinic_id = CurrentClinic::id()`. Si el modelo ya trae otro `clinic_id`, lanza `ClinicContextMissingException` (nunca se crea en otra clínica). Al editar, cambiar `clinic_id` lanza la misma excepción (`ClinicIdGuard`): un registro no pasa a otra clínica. `clinic_id` no es `fillable` |
| `App\Models\Concerns\SharedCatalog` | Igual que el anterior con `SharedCatalogScope`. Editar o eliminar un registro global lanza `CatalogException` `CATALOG_GLOBAL_NOT_EDITABLE` (403); el ABM es de la FASE 1. Cambiar `clinic_id` al editar (también pasar uno propio a global) lanza `ClinicContextMissingException`. Las escrituras masivas (`update`, `delete`, `forceDelete`, `increment`…) pasan por `SharedCatalogBuilder`: solo alcanzan los registros de la clínica en contexto y no aceptan `clinic_id`. El seeder crea los globales sin scope (motivo registrado: no hay clínica) y solo busca entre los globales |
| Modelos | Los 29 modelos de negocio restantes con `BelongsToClinic`; `Species` y `Breed` con `SharedCatalog`. Revisar relaciones y casts al tocarlos (deuda #2) |
| `User` | `clinic_id` y `clinic()`, **sin** `ClinicScope`. Motivo registrado: el login y Sanctum buscan al usuario antes de conocer su clínica, y el scope fallaría. Toda consulta de usuarios fuera del login y de Sanctum filtra por `clinic_id` de forma explícita, y el *route binding* de `{user}` resuelve solo dentro de la clínica actual (`resolveRouteBinding`): un usuario de otra clínica responde 404 (R-005) |
| `AuditLog` | `ClinicScope` para leer (FASE 2). `AuditLogger::record` completa `clinic_id` explícitamente: la del registro auditado (su `clinic_id`, o la propia clínica si se audita una `Clinic`), si no la clínica en contexto, si no la del usuario, si no nulo |
| Middleware `SetCurrentClinic` | En el grupo de `auth:sanctum`. Fija la clínica del usuario. Clínica suspendida → 403 `CLINIC_NOT_AVAILABLE` (`ClinicException`). Debe correr **antes** que `SubstituteBindings` (`prependToPriorityList`), para que el *route model binding* ya tenga el scope: un id de otra clínica da 404 |
| Form Requests | `Rule::unique(...)->where('clinic_id', CurrentClinic::id())` y lo mismo en `exists`. Especies y razas en `StorePatientRequest`: `exists` con "nulo o la clínica actual". Archivos: `StoreClientRequest`, `UpdateClientRequest`, `StorePatientRequest`, `UpdatePatientRequest`, `ChangePatientOwnerRequest` |
| `DocumentSequenceService` | Usa el query builder, al que no llegan los scopes: filtra `clinic_id` explícitamente (motivo registrado) en la secuencia y en la verificación del código. Una clínica nueva empieza su serie en 1 (hoy arranca en `max(id) + 1`, global). `isCodeViolation` reconoce el nombre del índice compuesto (`<tabla>_clinic_id_code_unique`) |
| `ClinicService::create(array $data, array $admin): Clinic` | En una transacción y dentro de `CurrentClinic::run`: crea la clínica, la sucursal `SUC-01` "Casa central" con establecimiento `001`, el depósito `DEP-01` "Principal" y el usuario administrador (rol `administrador`, `branch_user` y `current_branch_id`). Valida el email del administrador único en todo el sistema y el RUC: único en todo el sistema y con dígito verificador válido (módulo 11) |
| `App\Exceptions\ClinicException` | `CLINIC_NOT_AVAILABLE` (403); `CLINIC_RUC_TAKEN` (422), `CLINIC_RUC_INVALID` (422) |
| `App\Exceptions\CatalogException` | `CATALOG_GLOBAL_NOT_EDITABLE` (403) |
| Seeders | `SpeciesSeeder` crea especies y razas globales (`clinic_id` nulo) |

**Tests** (`tests/Feature/Tenancy`, R-002). Cada uno falla si se quita la protección que prueba.

| Test | Verifica |
|---|---|
| `ClinicScopeTest` | El listado de clientes y pacientes solo muestra los de la clínica; contar con el modelo da solo los propios |
| `RouteBindingTest` | `GET`, `PATCH` y `DELETE` con un id de otra clínica → 404 `RESOURCE_NOT_FOUND`; `clients/{ajeno}/patients` → 404 |
| `UserIsolationTest` | El *route binding* de un usuario de otra clínica responde 404 (con una ruta de prueba hasta que exista el ABM de usuarios de la FASE 2); el login sigue encontrando al usuario sin clínica en contexto |
| `ClinicAssignmentTest` | El alta toma la clínica del contexto aunque el cuerpo traiga `clinic_id`; crear con otro `clinic_id` falla; cambiar `clinic_id` al editar falla; la auditoría usa la clínica del registro auditado y, en un comando sin usuario, la del contexto |
| `ValidationScopeTest` | El mismo `document_number`, `email` y `microchip` se aceptan en dos clínicas y se rechazan dentro de la misma; `exists` no acepta un cliente de otra clínica en `change-owner` |
| `SharedCatalogTest` | Una clínica ve las especies globales y las suyas, no las de otra; no puede editar una global (`CATALOG_GLOBAL_NOT_EDITABLE`); `update` y `delete` masivos no tocan globales ni ajenos y no cambian `clinic_id`; una especie propia no pasa a global; `SpeciesSeeder` solo busca y crea globales |
| `DocumentSequenceTest` | Cada clínica numera `PAC-` desde 1, de forma independiente |
| `FailClosedTest` | Consultar un modelo con scope sin clínica lanza `ClinicContextMissingException`; un job o un comando sin `CurrentClinic::run` falla; `run` y `SetCurrentClinic` restauran la clínica anterior (también si el pedido falla); el log registra la ruta, el job o el comando que lo causó |
| `SuspendedClinicTest` | Con la clínica suspendida, un pedido autenticado responde 403 `CLINIC_NOT_AVAILABLE` |
| `ClinicServiceTest` | `create` deja clínica, `SUC-01`, `DEP-01` y administrador; si algo falla no queda nada (transacción); el alta queda en la auditoría de la clínica nueva aunque se llame desde el contexto de otra |

### 0.3 Autenticación

**Reglas** (R-004).

* Login con email y contraseña. El mismo mensaje para un email desconocido y para una contraseña incorrecta. Sin usuario se verifica
  un hash de relleno precalculado con el costo de producción (`AuthService::DUMMY_HASH`), para que el tiempo no revele qué emails existen.
* El email solo admite ASCII, en el login (`ascii`) y al crear usuarios (`CLINIC_ADMIN_EMAIL_INVALID`, 422, en `ClinicService`): la
  collation de MySQL iguala `ā` y `a`, y con acentos un email distinto sería el mismo login (revisión de la 0.3).
* Un usuario eliminado (`SoftDeletes`) no puede entrar: se comporta como un email desconocido. Al eliminarlo se borran sus tokens, así
  que restaurarlo no revive sesiones viejas (revisión de la 0.3).
* Credenciales correctas con la clínica suspendida → 403 `CLINIC_NOT_AVAILABLE`; no se crea el token. Con la contraseña incorrecta, el
  422 `INVALID_CREDENTIALS` de siempre: la suspensión no se revela.
* Cada login crea un token nuevo que vence a las 12 horas (`expires_at`, más `sanctum.expiration = 720` como respaldo). Puede haber
  varios tokens vigentes por usuario.
* Un token vencido o revocado → 401 `UNAUTHENTICATED` (contrato existente).
* El logout revoca solo el token actual. Funciona también con la clínica suspendida (sin `SetCurrentClinic`): el usuario siempre puede
  cerrar su sesión (revisión de la 0.3).
* Límite: 5 intentos fallidos por minuto por usuario e IP (`login:user:<id>|ip`), o por email en minúsculas e IP si el email no existe
  (`login:email:<email>|ip`); los emails inexistentes también cuentan. La clave por usuario evita que una variante del email que la base
  considere igual abra un contador nuevo (revisión de la 0.3). El intento se cuenta **antes** de verificar la contraseña, en una sola
  operación, para que una ráfaga simultánea no pase junta; un login correcto limpia el contador y uno con la clínica suspendida lo
  descuenta. El sexto → 429 `AUTH_TOO_MANY_ATTEMPTS`, con `Retry-After`.
* La IP es la del cliente solo detrás de un proxy de `TRUSTED_PROXIES` (vacía por defecto: ninguno). Ver
  [`deployment.md`](../development/deployment.md).
* La contraseña no llega al log: los errores de negocio (`AppException` y sus subclases) no se reportan (`dontReport`), y los
  parámetros de contraseña llevan `#[\SensitiveParameter]` (revisión de la 0.3).
* Auditoría: `LOGIN` y `LOGOUT` con el usuario y su clínica; `LOGIN_FAILED` con el email en `new_values` y el usuario si existe. Los
  enums ya existen en `AuditEvent`.
* Tarea programada diaria: `sanctum:prune-expired --hours=24`, en `routes/console.php`.

**Endpoints.** Sin permisos de Spatie: cualquier usuario autenticado. El seeder no cambia (119 permisos).

| Método | Ruta | Middleware | Request | Respuesta | Errores |
|---|---|---|---|---|---|
| `POST` | `/api/login` | Público | `LoginRequest`: `email` (requerido, ASCII, email, máx. 255), `password` (requerido, string) | 200 `data`: `token`, `token_type` (`Bearer`), `expires_at` (UTC `Z`), `user` (`MeResource`) | 422 `VALIDATION_ERROR`; 422 `INVALID_CREDENTIALS`; 403 `CLINIC_NOT_AVAILABLE`; 429 `AUTH_TOO_MANY_ATTEMPTS` |
| `POST` | `/api/logout` | `auth:sanctum` (sin `SetCurrentClinic`: funciona con la clínica suspendida) | — | 200 `data: null` | 401 `UNAUTHENTICATED` |
| `GET` | `/api/me` | `auth:sanctum`, `SetCurrentClinic` | — | 200 `MeResource` | 401 `UNAUTHENTICATED`; 403 `CLINIC_NOT_AVAILABLE` |

`MeResource`: `id`, `first_name`, `last_name`, `name`, `email`, `clinic` (`id`, `name`), `current_branch` (`id`, `code`, `name`) o
`null`, `roles` (nombres), `permissions` (nombres, ordenados; el administrador recibe todos), `veterinarian` (`id`, `code`) o `null`.

| Pieza | Detalle |
|---|---|
| `AuthController` | `HasMiddleware`: `auth:sanctum` salvo en `login`; `SetCurrentClinic` salvo en `login` y `logout`. Llama a `AuthService` |
| `AuthService` | `login(email, password, ip): array`, `logout(User)`. Contador con `RateLimiter` |
| `App\Exceptions\AuthException` | `INVALID_CREDENTIALS` (422), `AUTH_TOO_MANY_ATTEMPTS` (429, con `Retry-After` en la respuesta) |
| Mensajes (voseo) | "El email o la contraseña no son correctos.", "Demasiados intentos. Probá de nuevo en N segundos.", "La clínica no está disponible." |

**Tests** (`tests/Feature/Auth`): login correcto con cada rol y estructura de la respuesta; contraseña incorrecta y email desconocido
con el mismo mensaje; usuario eliminado no entra; clínica suspendida no entra y no crea token; sexto intento fallido → 429 y otro
email desde la misma IP no está bloqueado; login correcto limpia el contador; token vencido (avanzando el reloj 12 h) → 401; logout
revoca solo el token actual; `me` devuelve permisos ordenados y la ficha de veterinario o `null`; auditoría de `LOGIN`,
`LOGIN_FAILED` y `LOGOUT` con `clinic_id`. Agregados en la revisión: la contraseña no llega al log; variantes Unicode del email y
emails inexistentes llegan al 429; el intento cuenta antes de verificar la contraseña; clave incorrecta en una clínica suspendida →
422; logout con la clínica suspendida; tokens borrados al eliminar el usuario; hash de relleno con el costo de producción; validación
de `password` (array), del email (más de 255, no ASCII) y `$hidden`; proxies de confianza (`tests/Feature/TrustedProxiesTest`).

### 0.4 Alta de clínicas (`clinic:create`)

| Pieza | Detalle |
|---|---|
| Comando | `php artisan clinic:create`. Pide de forma interactiva (Laravel Prompts), o por opciones: `--name`, `--legal-name`, `--ruc`, `--ruc-dv`, `--admin-email`, `--admin-first-name`, `--admin-last-name`. Contraseña: interactiva, oculta y con confirmación; sin interacción, se genera y se muestra una sola vez |
| Lógica | Valida los datos, verifica el RUC y el email antes de pedir la contraseña (R-010), llama a `ClinicService::create` y muestra la clínica, la sucursal, el depósito y el email del administrador. Un error de validación termina con código 1 y el mensaje, sin traza |
| Auditoría | El alta queda en `audit_logs` con `clinic_id` de la clínica nueva y `user_id` nulo (no hay usuario autenticado) |
| `DemoSeeder` | Solo en `local` y `testing`, idempotente (correrlo dos veces no duplica nada) y nunca en producción: en otro entorno **falla** con una excepción (R-010). Dos clínicas de demostración (A y B, para probar la separación de datos; R-010), cada una con un usuario por rol (`administrador`, `recepcionista`, `veterinario` con su ficha), clientes y pacientes, para Postman. RUC ficticios y la marca `demo_data` en `system_settings`: una clínica con su RUC sin la marca, o un email que ya es de otra clínica, lo hacen fallar |
| Contraseñas | Regla de R-009 con el verificador propio de Have I Been Pwned (3 segundos; sin respuesta, se acepta con un aviso sin traza) y sin argumentos en las trazas (`zend.exception_ignore_args`), R-010 |

**Tests** (`tests/Feature/Console/ClinicCreateCommandTest`): crea todo con opciones; email repetido falla sin crear la clínica;
RUC inválido falla; el administrador creado puede hacer login. La revisión de la entrega (R-010) suma `tests/Feature/Seeders`,
`tests/Feature/Security` y `tests/Unit`.

### 0.5 Acceso por rol y verificación en MySQL

| Pieza | Detalle |
|---|---|
| `tests/Feature/Access` | Para `recepcionista` y `veterinario`, cada ruta existente (`clients`, `clients.patients`, `patients`, `change-owner`, `species`) contra lo que da `clinicRoles()`: permitida → no 403; prohibida → 403 `FORBIDDEN`. El administrador, una vez, para confirmar `Gate::before`. Sin token → 401 |
| MySQL | `migrate:fresh --seed` en MySQL 8; consulta a `information_schema` para confirmar nombres de índices y claves foráneas de hasta 64 caracteres; `migrate:rollback` completo |
| `database/schema-mysql.sql` | Quitado con la aprobación del diseño (R-005); esta verificación lo reemplaza |
| Guía de Postman | Login, `me` y logout en [`docs/testing/guia-postman.md`](../testing/guia-postman.md) |

## Lo que no cambia

Contrato de respuesta, `error_code` y códigos HTTP existentes; permisos del seeder (119) y roles; reglas de negocio de clientes y
pacientes. Los mensajes existentes siguen con tú hasta la FASE 1 (R-004).

## Preguntas del diseño

| # | Pregunta | Recomendación | Respuesta (R-005) | Fecha |
|---|---|---|---|---|
| 1 | ¿El RUC de la clínica es único en todo el sistema? ¿Se valida el dígito verificador (módulo 11)? | Único y validado: cada clínica factura con su RUC | Único en todo el sistema y con validación del dígito verificador (módulo 11) | 2026-11-04 |
| 2 | Límite de intentos: ¿cuentan solo los fallidos (y un login correcto limpia el contador) o todos? | Solo los fallidos, como Laravel por defecto | Solo cuentan los fallidos; un login correcto limpia el contador | 2026-11-04 |
| 3 | Contraseña del primer administrador en `clinic:create`: ¿se escribe (oculta, con confirmación) o se genera y se muestra una vez? | Se escribe; con `--no-interaction` se genera y se muestra una vez | Se escribe oculta y con confirmación; sin interacción, se genera y se muestra una sola vez | 2026-11-04 |
| 4 | Como el email y el documento del cliente, ¿el microchip y la matrícula del veterinario son únicos por clínica (un paciente puede estar en dos clínicas)? | Sí, por clínica | Únicos por clínica: la misma mascota puede atenderse en dos clínicas, y un único global revelaría datos de otra clínica | 2026-11-04 |
| 5 | Usuarios inactivos: ¿alcanza con eliminarlos (`SoftDeletes`) o hace falta un estado activo/inactivo? | Decidir en la FASE 2; en la FASE 0 alcanza con `SoftDeletes` | Se decide en la FASE 2 | 2026-11-04 |
| 6 | ¿Un usuario puede tener varias sesiones a la vez (computadora y tablet)? | Sí: cada login crea su token | Sí, cada login crea su token; `sanctum:prune-expired` diario limpia los vencidos | 2026-11-04 |
| 7 | ¿Se agrega el `DemoSeeder` con una clínica y un usuario por rol para desarrollo y Postman? | Sí, solo en `local` y `testing` | Sí, solo en `local` y `testing`, idempotente y nunca en producción | 2026-11-04 |
| 8 | `database/schema-mysql.sql`: ¿se regenera después de D1 o se quita? | Quitarlo: el esquema vive en las migraciones y se verifica en MySQL | Se quita; la verificación en MySQL de la entrega 0.5 lo reemplaza | 2026-11-04 |
| 9 | ¿Aprobás la reagrupación de siete entregas en cinco? | Sí | Aprobada | 2026-11-04 |

## Criterio de cierre

`migrate:fresh --seed` corre en MySQL y en SQLite; los tests de aislamiento pasan y cada uno falla al quitar su protección; login
con cada rol; `ClinicRolesTest` verifica los permisos de los roles y de los controllers; `clinic:create` deja una clínica operativa;
suite en verde.

## Prompt de implementación (paso 4)

Usar solo con el diseño aprobado, una entrega por vez.

```text
Implementa la entrega 0.X de la FASE 0 según docs/fases/fase-00-puesta-en-marcha.md (diseño aprobado).

Sigue CLAUDE.md y el diseño de esa entrega, sin adelantar las siguientes. Al terminar, corre la suite (y en las entregas con
migraciones, migrate:fresh --seed también en MySQL) e infórmame archivos, tests y resultados. No hagas commit.
```

## Resultado

Cada entrega lo completa en su cierre ([`docs/prompts/7-cierre.md`](../prompts/7-cierre.md)). Entregas 0.4 y 0.5: PENDIENTE.

### Entrega 0.1

Commits `11b82b3` (implementación) y `2b50149` (correcciones de la revisión), en `feature/fase-00-paquete-base`. Validada por el
usuario el 2026-11-04: suite, `migrate:fresh --seed` y `GET /up`. Decisiones en [R-006](../decisions/registro.md).

**Archivos.**

| Origen | Archivos |
|---|---|
| Esqueleto de Laravel 13.40 (sin pisar el paquete) | `artisan`, `composer.json`, `composer.lock`, `.env.example`, `.gitignore`, `.gitattributes`, `.editorconfig`, `public/`, `storage/`, `resources/views/welcome.blade.php`, `bootstrap/providers.php`, `bootstrap/cache/`, `config/*` salvo `validation.php`, `routes/web.php`, `routes/console.php`, `app/Http/Controllers/Controller.php`, `database/.gitignore` |
| Paquetes | `laravel/sanctum` 4.3 y `spatie/laravel-permission` 8.3; solo `config/sanctum.php` y `config/permission.php` |
| Ajustados | `config/app.php` (`America/Asuncion`, `es`), `config/auth.php` (guard `api` con `sanctum`), `config/sanctum.php` (`SANCTUM_EXPIRATION`, 720), `config/permission.php` (sin *teams*), `.env.example`, `composer.json` (nombre del proyecto y `setup` con `--seed`), `DatabaseSeeder`, `UserFactory` |
| Tests | `phpunit.xml`, `tests/bootstrap.php`, `tests/TestCase.php`, `tests/Feature/ClinicRolesTest.php`, `tests/Feature/HealthTest.php`, `tests/Feature/ErrorResponseTest.php` |
| Del paquete, cambiado en la revisión | `bootstrap/app.php`: renderer de los errores HTTP sin renderer propio y de `HttpResponseException` |
| Documentación | `README.md`, `CLAUDE.md` §4 (lista de `error_code`), `docs/development/setup.md`, `docs/development/testing.md`, scripts de `/validar-docs` |

**Endpoints.** Ninguno nuevo. `GET /up` (*health check* de Laravel) responde 200.

**Tests.** 18 tests y 58 aserciones en verde (`php artisan test`), también corriendo PHPUnit desde `tests/`.

| Clase | Tests | Aserciones | Verifica |
|---|---|---|---|
| `ClinicRolesTest` | 8 | 36 | Permisos sin repetir y sembrados con guard `api`; los tres roles; el administrador con todos; cada permiso de `clinicRoles()` y de cada Controller existe en `PERMISSIONS`; cada ruta de `/api` exige un `permission:` |
| `HealthTest` | 3 | 5 | `GET /up` 200; ruta inexistente → JSON 404 `RESOURCE_NOT_FOUND`; sin token → JSON 401 `UNAUTHENTICATED` |
| `ErrorResponseTest` | 7 | 17 | Autorización denegada y `abort(403)` → 403 `FORBIDDEN`; `abort(409)` → `CONFLICT`; otro 4xx conserva su estado (`HTTP_ERROR`); 429 conserva `Retry-After`; `HttpResponseException` sale tal cual; un error inesperado → 500 sin detalle |

En la revisión se rompió a propósito, de a una, cada protección: permiso de un rol o de un Controller que no existe, permiso
repetido, sin renderer de 404 o de 401, sin `EnsureJsonResponse`, ruta sin permiso, sin los renderers nuevos. En todos los casos falla
el test que la protege.

**Desvíos del diseño.**

| Desvío | Motivo |
|---|---|
| Del esqueleto no se trajeron `package.json`, Vite, `resources/css`, `resources/js` ni `AGENTS.md` | El frontend vive en otro repositorio |
| `tests/bootstrap.php`, no previsto | Crea la base SQLite si falta y vuelve absoluta su ruta, para que los tests no dependan de la carpeta desde la que se corren |
| `HealthTest` agrega el 401 en JSON | Protege el contrato `UNAUTHENTICATED` sin token |
| `ErrorResponseTest`, `test_every_api_route_requires_a_permission` y el cambio de `bootstrap/app.php` | Correcciones de la revisión, decididas por el usuario (R-006) |
| `composer setup` con `--seed` | Sin la siembra la instalación quedaba sin roles ni permisos |

**Hallazgos de la revisión y su corrección** (commit `2b50149`).

| # | Hallazgo | Corrección |
|---|---|---|
| 1 | Los errores HTTP sin renderer propio (`abort(403)`, una autorización denegada, `abort(409)`) y la `HttpResponseException` caían en el catch-all de `bootstrap/app.php` y respondían 500. No se producía todavía: ningún Form Request deniega | Renderer genérico antes del catch-all, con los `error_code` nuevos `CONFLICT` y `HTTP_ERROR`; la `HttpResponseException` sale tal cual. `ErrorResponseTest` |
| 2 | `DB_DATABASE` relativo en `phpunit.xml`: PHPUnit fallaba si no se corría desde la raíz | `tests/bootstrap.php` vuelve absoluta la ruta |
| 3 | `composer setup` migraba sin sembrar | `migrate --seed --force` |
| 4 | Una ruta sin `permission:` no hacía fallar ningún test | `test_every_api_route_requires_a_permission`, con la lista de excepciones `ROUTES_WITHOUT_PERMISSION` (vacía) |
| 5 | `README.md` desactualizado, y `/validar-docs` no comparaba permisos ni migraciones y excluía por error dos cifras | `README.md` corregido; el script compara `permisos` y `migraciones` y clasifica bien las dos cifras (33 pruebas propias en verde) |

**Para la entrega 0.3:** `login`, `logout` y `me` no llevan permiso; hay que agregarlas a `ROUTES_WITHOUT_PERMISSION` en
`ClinicRolesTest`, con su motivo.

### Entrega 0.2

Commits `fb52b73` (implementación) y `cf8f862` (correcciones de la revisión), en `feature/fase-00-02-clinicas`. Validada por el
usuario el 2026-11-05: suite y `migrate:fresh --seed`. Decisiones en [R-007](../decisions/registro.md).

**Archivos.**

| Tipo | Archivos |
|---|---|
| Migraciones | Nueva: `0001_01_00_000000_create_clinics_table.php`. `clinic_id` agregado en las existentes (`users` y las tablas de negocio; nulo en `species`, `breeds` y `audit_logs`), con los únicos y los índices por clínica. La 000005 ya no crea `SUC-01` ni `DEP-01` |
| Clínica y contexto | `app/Enums/ClinicStatus.php`, `app/Models/Clinic.php`, `app/Support/CurrentClinic.php`, `app/Support/ExecutionOrigin.php`, `app/Http/Middleware/SetCurrentClinic.php`, `app/Services/ClinicService.php` |
| Aislamiento | `app/Models/Concerns/BelongsToClinic.php`, `SharedCatalog.php` y `ClinicIdGuard.php`; `app/Models/Scopes/ClinicScope.php` y `SharedCatalogScope.php`; `app/Models/Builders/SharedCatalogBuilder.php` |
| Excepciones | `ClinicContextMissingException`, `ClinicException`, `CatalogException` |
| Cambiados | Los 29 modelos de negocio (`BelongsToClinic`), `Species` y `Breed` (`SharedCatalog`), `User` (`clinic()`, `inClinic()`, *route binding* acotado), `AuditLog` (`ClinicScope`); los cinco Form Requests; `AuditLogger`, `DocumentSequenceService`, `AppServiceProvider`, `bootstrap/app.php`, `routes/api.php`, `SpeciesSeeder`, `UserFactory` |
| Tests | `tests/Concerns/InteractsWithClinics.php`, `database/factories/ClinicFactory.php` y las diez clases de `tests/Feature/Tenancy`, con el job de prueba `Fixtures/CountClientsJob.php` |
| Documentación | Este documento, `CLAUDE.md` (§1, §3 y §7), `README.md`, `docs/roadmap/deuda-tecnica.md` y los documentos de estado |

**Endpoints.** Ninguno nuevo. Las rutas de `/api` pasan por `SetCurrentClinic`: un id de otra clínica responde 404
`RESOURCE_NOT_FOUND`, y una clínica suspendida, 403 `CLINIC_NOT_AVAILABLE`.

**Tests.** Suite: 90 casos de PHPUnit (75 métodos de test) y 290 aserciones en verde, en SQLite y contra MySQL 8.4.3
(`syncvet_test_mysql`). Los de esta entrega: 72 casos y 232 aserciones en `tests/Feature/Tenancy`. La tabla cuenta casos: cada
fila de un *data provider* es uno.

| Clase | Tests | Aserciones | Verifica |
|---|---|---|---|
| `ClinicAssignmentTest` | 9 | 13 | El alta toma la clínica del contexto aunque el cuerpo traiga `clinic_id`; `clinic_id` no es asignable; crear en otra clínica o sin clínica falla; cambiar `clinic_id` al editar falla; la auditoría usa la clínica del registro y, en un comando sin usuario, la del contexto |
| `ClinicScopeTest` | 4 | 14 | Los listados de clientes y pacientes y la búsqueda solo ven la clínica; contar con el modelo da solo los propios |
| `ClinicServiceTest` | 9 | 36 | `create` deja clínica, `SUC-01`, `DEP-01` y administrador, y cada clínica los suyos; si algo falla no queda nada; RUC inválido, RUC repetido y email repetido (también de un usuario eliminado) se rechazan; el alta queda en la auditoría de la clínica nueva |
| `DocumentSequenceTest` | 1 | 7 | Cada clínica numera `PAC-` desde 1, de forma independiente |
| `FailClosedTest` | 12 | 35 | Sin clínica fallan los modelos con scope, `User::inClinic()` y la numeración; un job o un comando sin `CurrentClinic::run` falla e informa su origen; `run` y `SetCurrentClinic` restauran el contexto; un pedido sin clínica responde 500 y deja la ruta en el log |
| `RouteBindingTest` | 10 | 40 | Cada ruta de clientes y pacientes con un id de otra clínica responde 404; los propios se encuentran |
| `SharedCatalogTest` | 15 | 45 | Especies y razas globales y propias, nunca las de otra clínica; los globales no se editan ni se eliminan; las escrituras masivas solo tocan los propios y no cambian `clinic_id`; un registro propio no pasa a global ni a otra clínica; `SpeciesSeeder` solo toca globales; un paciente no usa especies ni razas de otra clínica |
| `SuspendedClinicTest` | 2 | 4 | Clínica suspendida → 403 `CLINIC_NOT_AVAILABLE`; activa, opera |
| `UserIsolationTest` | 4 | 9 | `{user}` de otra clínica → 404; los de la propia se encuentran; Sanctum y la validación del login encuentran al usuario sin clínica en contexto; `inClinic()` filtra |
| `ValidationScopeTest` | 6 | 29 | Documento, email y microchip únicos por clínica, en el alta y en la edición; `change-owner` no acepta un cliente de otra clínica |

**Desvíos del diseño.**

| Desvío | Motivo | Quién |
|---|---|---|
| `CLINIC_ADMIN_EMAIL_TAKEN` (422) en `ClinicException` | El diseño pedía el email del administrador único, pero no nombraba su `error_code` | Usuario (commit `fb52b73`) |
| Más índices empiezan por `clinic_id`: además de los listados, los de filtro y listado (`clients` por teléfono y razón social, `patients` por nombre, `products` por código de barras, y los de estado o fecha de turnos, lotes, movimientos, consultas, aplicaciones y facturas) | Toda búsqueda de un listado filtra primero por la clínica | Implementación |
| Quedan sin `clinic_id` los índices de los *morphs*, `appointments.series_id` y `client_notifications.external_id` | Buscan un registro puntual; el webhook del proveedor busca por `external_id` antes de conocer la clínica | Implementación |
| `App\Support\ExecutionOrigin`, no previsto | Registra el origen de `ClinicContextMissingException`: el job, el comando o la ruta (R-005) | Implementación; los comandos por `CommandStarting`, corrección de la revisión |
| `SpeciesSeeder` con el *query builder* | Los modelos exigen una clínica en contexto, y la siembra no la tiene | Implementación, dentro del diseño (motivo registrado) |
| `AuditLogger`: primero la clínica del registro auditado, después la del contexto (el diseño decía el contexto primero) | El alta de una clínica llamada desde otra quedaba en la auditoría de esa otra | Usuario, en la revisión |
| `SharedCatalogBuilder` y `ClinicIdGuard`, no previstos | Escrituras masivas sobre los catálogos y cambio de `clinic_id` al editar | Usuario, en la revisión |
| El test del origen de un comando activa los eventos de consola con `rerouteSymfonyCommandEvents()` | Laravel no los dispara en los tests, y en producción sí. Es un método `@internal`: si Laravel lo cambia, falla ese test | Implementación, en la revisión |

**Hallazgos de la revisión y su corrección** (commit `cf8f862`). Ninguno se producía por la API existente; todos se reprodujeron
con un test.

| # | Hallazgo | Corrección |
|---|---|---|
| 1 | Un `update()` o `delete()` masivo sobre `Species` o `Breed` alcanzaba a los globales: el scope los incluye y las escrituras masivas no disparan los eventos del modelo | `SharedCatalogBuilder` limita `update`, `delete`, `forceDelete`, `touch`, `increment` y `decrement` a la clínica en contexto, y rechaza `clinic_id`. `SharedCatalogTest` |
| 2 | `AuditLogger` prefería la clínica en contexto: `ClinicService::create` llamado desde otra clínica dejaba el alta en la auditoría de esa otra | Primero la clínica del registro auditado (su `clinic_id`, o la propia `Clinic`), después el contexto, después el usuario. `ClinicServiceTest`, `ClinicAssignmentTest` |
| 3 | `clinic_id` se podía cambiar al editar (con `forceFill`): un cliente pasaba a otra clínica, o una especie propia a global | `ClinicIdGuard` en el `updating` de `BelongsToClinic` y de `SharedCatalog`. `ClinicAssignmentTest`, `SharedCatalogTest` |
| 4 | Con `Artisan::call`, el log informaba el `argv` del proceso que llamaba, no el comando | `ExecutionOrigin` sigue `CommandStarting` y `CommandFinished`. `FailClosedTest` |
| 5 | Tres protecciones sin test (E, G y H de la tabla siguiente) | Tests nuevos en `SharedCatalogTest`, `FailClosedTest` y `ClinicAssignmentTest` |
| 6 | Sin claves foráneas compuestas (`clinic_id` + `id`) | Deuda #5 ([`deuda-tecnica.md`](../roadmap/deuda-tecnica.md)) |

**Protecciones quitadas de a una.** La implementación quitó 44, y todas hacían fallar un test salvo dos mutaciones equivalentes. La
revisión quitó ocho más:

| Protección quitada | Resultado |
|---|---|
| A. `SharedCatalog`: el error al crear con otro `clinic_id` | Ningún test falla. Mutación equivalente: la línea siguiente (`setAttribute('clinic_id', CurrentClinic::id())`) pisa el valor recibido, así que el registro igual queda en la clínica en contexto; sin la verificación solo se pierde el aviso. Se mantiene para que el error sea visible, como en `BelongsToClinic`, donde sí tiene test |
| B. `SharedCatalogScope` sin agrupar el `OR` (`whereNull(...)->orWhere(...)` suelto) | Ningún test falla. Mutación equivalente: Eloquent agrupa en un paréntesis propio las condiciones que agrega cada *global scope* (`Builder::callScope` y `addNewWheresWithinGroup`), así que el `OR` nunca escapa del resto del filtro. El agrupado explícito queda para no depender de ese detalle |
| C. `AuditLogger`: la clínica de la `Clinic` auditada | Falla `ClinicServiceTest` |
| D. `ClinicScope` con `clinic_id` sin el nombre de la tabla | Ningún test falla. Equivalente hoy: ninguna consulta une dos tablas con `clinic_id`. Con un *join* así el error sería de SQL (columna ambigua) y el pedido fallaría: nunca devolvería datos de otra clínica. Se mantiene `qualifyColumn` para que esos *joins* funcionen cuando existan |
| E. `SpeciesSeeder` sin `whereNull('clinic_id')` al buscar | Falla `SharedCatalogTest::test_seeder_only_touches_global_records` (nuevo) |
| F. `ExecutionOrigin` sin quitar el job terminado | Falla `FailClosedTest` |
| G. `SetCurrentClinic` fija la clínica sin restaurarla | Falla `FailClosedTest::test_request_restores_the_clinic_context_when_it_ends` (nuevo) |
| H. `AuditLogger` sin la clínica en contexto | Falla `ClinicAssignmentTest::test_audit_log_takes_the_clinic_from_context_in_a_command` (nuevo) |

Cada corrección de la revisión también se quitó de a una: el filtro de `update`, `delete` y `forceDelete` del builder, el rechazo de
`clinic_id`, el registro del builder, las dos ramas nuevas de `AuditLogger`, `ClinicIdGuard` en `BelongsToClinic` y los dos
eventos de comando. En todos los casos falla su test. `ClinicIdGuard` en `SharedCatalog` está cubierto dos veces: guardar el
modelo termina en `SharedCatalogBuilder::update`, que también rechaza `clinic_id`. Al quitar las dos capas, fallan los tests de
inmutabilidad de `SharedCatalogTest`.

**Verificación en MySQL** (`syncvet_test_mysql`, MySQL 8.4.3): `migrate:fresh --seed`, rollback completo y `migrate --seed` otra
vez; 28 migraciones, 52 tablas y nombres de índices y claves de hasta 56 caracteres.

**Para la FASE 1:** `SharedCatalogBuilder` no cubre `insert()` ni `upsert()`: insertar una especie o una raza por el *builder* sin
`clinic_id` crearía un registro global, porque no pasa por el evento de alta. Ningún código lo hace todavía; tenerlo en cuenta en
el ABM de especies y razas.

### Entrega 0.3

Commits `2114244` (implementación), `3da07f2` (correcciones de la primera revisión, de seguridad) y `727546e` (correcciones de la
segunda revisión), en `feature/fase-00-03-auth`. Validada por el usuario el 2026-11-05: suite en verde. La prueba manual con Postman
([`guia-postman.md`](../testing/guia-postman.md)) queda, por decisión del usuario, para la entrega 0.4, cuando exista el `DemoSeeder`.
Decisiones en [R-008](../decisions/registro.md).

**Archivos.**

| Tipo | Archivos |
|---|---|
| Nuevos | `app/Http/Controllers/Api/AuthController.php`, `app/Services/AuthService.php`, `app/Http/Requests/LoginRequest.php`, `app/Http/Resources/MeResource.php`, `app/Exceptions/AuthException.php`, `config/trustedproxy.php` |
| Cambiados | `app/Models/User.php` (email en minúsculas, `normalizeEmail()`, `remember_token` oculto, tokens borrados al eliminar), `app/Exceptions/AppException.php` (encabezados de la respuesta, para `Retry-After`), `app/Exceptions/ClinicException.php` y `app/Services/ClinicService.php` (`CLINIC_ADMIN_EMAIL_INVALID`, `#[\SensitiveParameter]`), `bootstrap/app.php` (`dontReport(AppException::class)` y encabezados), `routes/api.php`, `routes/console.php` (`sanctum:prune-expired` diario), `.env.example` (`TRUSTED_PROXIES`) |
| Tests | `tests/Concerns/InteractsWithAuth.php`, las siete clases de `tests/Feature/Auth`, `tests/Feature/TrustedProxiesTest.php`; `ClinicRolesTest` (`login`, `logout` y `me` en `ROUTES_WITHOUT_PERMISSION`, con su motivo); `tests/bootstrap.php` (solo prepara el archivo con la conexión `sqlite`) |
| Documentación | Este documento, [`guia-postman.md`](../testing/guia-postman.md) (sección de la FASE 0), [`deployment.md`](../development/deployment.md), [`deuda-tecnica.md`](../roadmap/deuda-tecnica.md) (deuda 6), [`fase-10-reportes-y-piloto.md`](fase-10-reportes-y-piloto.md), `CLAUDE.md` (§1, §3 y §7) y los documentos de estado |

**Endpoints.** `POST /api/login` (público), `POST /api/logout` y `GET /api/me`, como en el diseño de la 0.3 de este documento. Sin
permiso de Spatie; el seeder sigue con 119 permisos. `error_code` nuevos: `INVALID_CREDENTIALS` (422), `AUTH_TOO_MANY_ATTEMPTS` (429,
con `Retry-After`) y `CLINIC_ADMIN_EMAIL_INVALID` (422, en `ClinicService`).

**Tests.** Suite (corrida del 2026-11-05 sobre `727546e`): 132 métodos de test, 149 casos con los *data providers*; en SQLite, 689
aserciones con 1 omitido (el de la collation de MySQL); contra MySQL 8.4.3 (`syncvet_test_mysql`), 703 aserciones sin omitidos. Los
de esta entrega: 57 métodos y 59 casos, 399 aserciones en SQLite y 413 en MySQL. La tabla cuenta casos.

| Clase | Tests | Aserciones | Verifica |
|---|---|---|---|
| `LoginTest` | 13 | 131 | Login con cada rol (token y perfil); token de 12 horas; contraseña incorrecta, email desconocido y usuario eliminado con el mismo error; clínica suspendida sin token, y con la clave incorrecta, 422 (no revela la suspensión); varios tokens vigentes; validación con voseo: `password` no string, email de más de 255 y email no ASCII |
| `LoginThrottleTest` | 12 | 140 (154 en MySQL) | Sexto fallido → 429 con `Retry-After`; contador por usuario y por IP; mayúsculas; un login correcto lo limpia; solo cuentan los fallidos (no los de una clínica suspendida); el bloqueo dura un minuto; emails inexistentes también cuentan; el intento se cuenta antes de verificar la contraseña; variantes Unicode nunca entran; en MySQL, el contador es el del usuario sea cual sea la variante del email (omitido en SQLite) |
| `LoginCredentialsTest` | 10 | 27 | Email guardado y buscado en minúsculas (también en `ClinicService`); siempre se verifica un hash, también sin usuario o con usuario eliminado; el hash de relleno es un bcrypt con el costo de producción y es el que se usa; `password` y `remember_token` ocultos; `ClinicService` rechaza un email no ASCII |
| `SessionTest` | 6 | 35 | El token vale hasta las 12 horas; el logout revoca solo el actual; `logout` y `me` exigen token; token válido de una clínica suspendida → 403; logout con la clínica suspendida; eliminar un usuario borra sus tokens y restaurarlo no los revive |
| `AuthAuditTest` | 6 | 18 | `LOGIN`, `LOGIN_FAILED` (email conocido, desconocido y clínica suspendida con su motivo) y `LOGOUT` con el usuario y su clínica; `sanctum:prune-expired` programado a diario |
| `MeTest` | 4 | 21 | Perfil con permisos ordenados; sin sucursal actual, `null`; ficha de veterinario; el administrador recibe todos los permisos |
| `AuthLoggingTest` | 4 | 18 | Los logins fallidos y de clínica suspendida no escriben en el log; los errores de negocio no se reportan y `ClinicContextMissingException` sí; la contraseña no está en la traza de `AuthService::login` ni en la de `ClinicService::create` |
| `TrustedProxiesTest` | 4 | 9 | Ningún proxy de confianza por defecto; sin proxies, `X-Forwarded-For` se ignora, también con un Host `*.on-forge.com`; un proxy de confianza pasa la IP del cliente |

**Protecciones quitadas de a una.** La implementación quitó 41 y la primera revisión, 18: todas hacían fallar un test. La segunda
revisión repitió 19 en SQLite y 3 en MySQL. Solo sobrevivió `#[\SensitiveParameter]` en `ClinicService::create`, que no tenía test; se
agregó (`AuthLoggingTest::test_the_admin_password_is_not_in_the_clinic_service_trace`) y ahora falla al quitarlo. Dos resultados
esperados: la clave por usuario solo se puede probar en MySQL (en SQLite el test se omite), y quitar solo la regla `ascii` no hace
fallar los dos tests de la segunda capa (variantes Unicode), sino su propio test de validación.

**Desvíos del diseño.**

| Desvío | Motivo | Quién |
|---|---|---|
| El email de `User` se guarda y se busca en minúsculas (`normalizeEmail()`) | El login no depende de la collation: SQLite distingue mayúsculas | Implementación (`2114244`) |
| Sin usuario se verifica un hash de relleno (`AuthService::DUMMY_HASH`, bcrypt con el costo de producción, precalculado) | El tiempo de respuesta no revela qué emails tienen cuenta | Implementación; la constante, usuario, el 2026-11-05, a propuesta de la primera revisión |
| `AppException` acepta encabezados | El 429 envía `Retry-After` | Implementación (`2114244`) |
| Límite por usuario e IP (por email si no existe), no por `email\|ip` | Con la collation de MySQL, una variante Unicode del email encontraba al usuario con un contador nuevo | Usuario, el 2026-11-05, a propuesta de la primera revisión (`3da07f2`) |
| El intento se cuenta antes de verificar la contraseña; un login con la clínica suspendida lo descuenta | Pedidos simultáneos pasaban todos la verificación del límite | Usuario, el 2026-11-05, a propuesta de la primera revisión |
| El email solo admite ASCII, en el login y en `ClinicService` (`CLINIC_ADMIN_EMAIL_INVALID`, nuevo) | La collation de MySQL iguala `ā` y `a` | Usuario, el 2026-11-05, a propuesta de la primera revisión |
| `logout` sin `SetCurrentClinic` | El usuario siempre puede cerrar su sesión, aunque la clínica esté suspendida | Usuario, el 2026-11-05, a propuesta de la primera revisión |
| Los errores de negocio (`AppException` y subclases) no se reportan al log, en toda la API | La traza de un login fallido llevaba la contraseña al log | Usuario, el 2026-11-05, a propuesta de la primera revisión |
| Al eliminar un usuario se borran sus tokens; `remember_token` oculto | Restaurarlo no revive sesiones viejas | Usuario, el 2026-11-05, a propuesta de la primera revisión |
| `config/trustedproxy.php` con `TRUSTED_PROXIES`, vacía por defecto | La IP del límite y de la auditoría no puede venir de un `X-Forwarded-For` del cliente | Usuario, el 2026-11-05, a propuesta de la primera revisión |
| Validación manual con Postman en la 0.4 | Hace falta el `DemoSeeder` | Usuario |

**Hallazgos de la primera revisión (de seguridad) y su corrección** (commit `3da07f2`). Las correcciones las decidió el usuario el
2026-11-05, a propuesta de la revisión.

| # | Hallazgo | Corrección |
|---|---|---|
| 1 | La contraseña del login llegaba a `storage/logs/laravel.log` en la traza de la excepción (con `zend.exception_ignore_args=Off`) | `dontReport(AppException::class)`; `#[\SensitiveParameter]` en `AuthService::login` y `ClinicService::create`; `deployment.md` exige `zend.exception_ignore_args=On`. `AuthLoggingTest` |
| 2 | En MySQL, una variante Unicode del email (`ānā@…`) encontraba al usuario con un contador nuevo y salteaba el límite | Contador por usuario e IP; email solo ASCII en el login y en `ClinicService`. `LoginThrottleTest`, `LoginTest`, `LoginCredentialsTest` |
| 3 | Una ráfaga de pedidos simultáneos pasaba toda la verificación del límite | El intento se cuenta antes de verificar la contraseña, en una sola operación. `LoginThrottleTest` |
| 4 | `TrustedProxies` nulo: con un Host `*.on-forge.com`, que manda el cliente, Laravel confiaba en todos los proxies | `TRUSTED_PROXIES`, vacía por defecto (lista vacía, no nula). `TrustedProxiesTest` |
| 5 | Restaurar un usuario eliminado revivía sus tokens | Se borran al eliminarlo. `SessionTest` |
| 6 | El hash de relleno se calculaba en el pedido: un bcrypt extra la primera vez en cada proceso | Constante precalculada, con un test que exige el costo de producción |
| 7 | Con la clínica suspendida el usuario no podía cerrar su sesión | `logout` sin `SetCurrentClinic`. `SessionTest` |
| 8 | Con `DB_CONNECTION=mysql`, `tests/bootstrap.php` convertía `DB_DATABASE` en una ruta y la suite corría contra otra base | Solo prepara el archivo con la conexión `sqlite` |
| 9 | Protecciones sin test: hash de relleno, clave incorrecta en una clínica suspendida, fallos de emails inexistentes, `password` como array, `$hidden` y email de más de 255 | Tests nuevos en `LoginCredentialsTest`, `LoginTest` y `LoginThrottleTest` |

**Hallazgos de la segunda revisión y su corrección** (commit `727546e`). La revisión verificó en SQLite y en MySQL que ninguna
contraseña llega al log en 13 caminos del login, incluidas tres fallas 500 forzadas (hash que no es bcrypt, falla al crear el token y
caída de la caché): en las 500 la contraseña aparece como `SensitiveParameterValue`. También, que el logout funciona con la clínica
suspendida y que las variantes Unicode no saltean el límite.

| # | Hallazgo | Corrección |
|---|---|---|
| 1 | Con la caché `database`, 20 logins fallidos simultáneos dejaron pasar a verificar la contraseña de 5 a 9 en MySQL (de 0 a 4 de más), con un usuario existente y con emails inexistentes. En SQLite, la contención dio 500 (`database is locked`), pero SQLite solo se usa en los tests | Deuda 6 ([`deuda-tecnica.md`](../roadmap/deuda-tecnica.md)): Redis en producción, en la FASE 10 (entrega 10.5), verificando que pasen exactamente 5 |
| 2 | `#[\SensitiveParameter]` en `ClinicService::create` sin test | `AuthLoggingTest::test_the_admin_password_is_not_in_the_clinic_service_trace` |
| 3 | Comentarios desactualizados en `routes/api.php` (el logout fijaba la clínica) y en `LoginThrottleTest` (contador por email) | Corregidos |
| 4 | Un borrado masivo de usuarios (`User::where(...)->delete()`) no dispara `deleted` y deja los tokens vigentes. Ningún código lo hace todavía | Regla en `CLAUDE.md` §3: los usuarios se eliminan modelo por modelo |

**Deuda que queda.** Deuda 6: el límite del login con la caché `database` no es exacto ante una ráfaga simultánea (riesgo bajo: unas
pocas claves más por minuto e IP). Se resuelve con Redis en producción en la FASE 10; como la carrera está en el `RateLimiter` de
Laravel, al desplegar hay que verificar que pasen exactamente 5 y, si no, envolver el `hit` en `Cache::lock`.

**Para la entrega 0.4:** recorrer con Postman la sección de la FASE 0 de [`guia-postman.md`](../testing/guia-postman.md) con los
usuarios del `DemoSeeder`.
