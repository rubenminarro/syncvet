# FASE 0 — Puesta en marcha

Etapa: Base. Estado: **COMPLETADO** el 2026-11-05 (R-014): entregas 0.1 (R-006), 0.2 (R-007), 0.3 (R-008), 0.4 (R-012) y 0.5 (R-013 y R-014) cerradas; diseño aprobado el 2026-11-04 (R-005). El estado vigente de cada fase está en
[`docs/03-plan-desarrollo/README.md`](../../README.md).

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
| 0.4 | Comando `clinic:create` y datos de demostración | Nueva (P5) | COMPLETADO (R-012) |
| 0.5 | Acceso por rol (`tests/Feature/Access`) y verificación final en MySQL | Parte de 0.6 y 0.7 | COMPLETADO (R-013, R-014) |

## Preguntas de negocio

Las respuestas se anotan con la fecha y pasan a [`docs/01-arquitectura/decisiones/registro.md`](../../../01-arquitectura/decisiones/registro.md).

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

Análisis de las entidades nuevas con [`docs/00-vision/principios.md`](../../../00-vision/principios.md).

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
  [`deployment.md`](../../../04-desarrollo/deployment.md).
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
| Guía de Postman | Login, `me` y logout en [`docs/05-pruebas/guia-postman.md`](../../../05-pruebas/guia-postman.md) |

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
Implementa la entrega 0.X de la FASE 0 según docs/03-plan-desarrollo/fases/backend/fase-00-puesta-en-marcha.md (diseño aprobado).

Sigue CLAUDE.md y el diseño de esa entrega, sin adelantar las siguientes. Al terminar, corre la suite (y en las entregas con
migraciones, migrate:fresh --seed también en MySQL) e infórmame archivos, tests y resultados. No hagas commit.
```

> El resultado de cada entrega y el cierre de la fase están en [`fase-00-resultados.md`](fase-00-resultados.md).
