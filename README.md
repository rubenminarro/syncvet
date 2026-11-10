# SyncVet: backend base (Laravel 13)

Esquema del MVP y arquitectura base de SyncVet. Las reglas de trabajo están en `CLAUDE.md`.

Estado: FASE 0 (puesta en marcha), FASE 1 (clientes y pacientes) y FASE 2 (datos maestros) completadas; sigue la FASE 3, agenda y turnos. El estado de cada fase está en
[`docs/roadmap/README.md`](docs/roadmap/README.md) y el punto de continuidad, en [`docs/handoff.md`](docs/handoff.md).

## Qué incluye

| Carpeta | Contenido |
|---|---|
| `database/migrations` | 35 migraciones: clínicas, base de Laravel (usuarios, cache, colas, tokens, permisos), 19 de SyncVet, auditoría, configuración, secuencias, el documento del cliente ampliado a 50 caracteres y su número base (FASE 1, entrega 1.2) , el microchip del paciente ampliado a 60 (entrega 1.3) y la baja de especies y razas con sus nombres a 90 y 110 (entrega 1.4), `users.is_active` (FASE 2, entrega 2.2b), la matrícula del veterinario ampliada a 60 (entrega 2.3) y el contacto y el sobreturno de los turnos (FASE 3, entrega 3.2a). `clinic_id` en las tablas de negocio (D1) |
| `app/Enums` | 36 enums (estados y tipos) con `label()` en español |
| `app/Models` | 35 modelos con casts, relaciones y `Auditable` / `SoftDeletes` donde corresponde; los de negocio con `BelongsToClinic`, especies y razas con `SharedCatalog`; las fechas con hora con el cast `AppDateTime` |
| `app/Exceptions` | `AppException`, 17 excepciones de dominio y `ClinicContextMissingException` (fallo cerrado) |
| `app/Services` | Reglas de negocio de autenticación, clientes, pacientes y su foto, especies y razas, sucursales y depósitos, usuarios, veterinarios, servicios, la configuración y el logo, la limpieza de archivos huérfanos, las agendas y sus bloqueos, y los turnos; `ClinicService`, `CatalogLock`, `DocumentSequenceService` y `AuditLogger` |
| `app/Http` | Controllers, Requests y Resources de autenticación y perfil, clientes, pacientes, especies y razas, sucursales y depósitos, usuarios y roles, veterinarios, servicios, la configuración de la clínica, la auditoría, las agendas y sus bloqueos, y los turnos; middleware `EnsureJsonResponse` y `SetCurrentClinic` |
| `app/Traits`, `app/Support` | `ApiResponse`, `Auditable`, `Decimal`, `CurrentClinic` (clínica en contexto), `ExecutionOrigin`, `UserPassword` (regla de contraseñas), `PwnedPasswordsVerifier`, `ApiDateTime` (fechas con hora), `PrivateImageStore` (foto y logo) y los auxiliares de nombres, búsquedas y bajas |
| `app/Console/Commands` | `clinic:create`: alta de una clínica con su sucursal, su depósito y su primer administrador; `files:prune-orphans`: limpieza diaria de fotos y logos huérfanos (con `--dry-run`, [`docs/development/setup.md`](docs/development/setup.md)) |
| `bootstrap/app.php` | Mapeo de excepciones a JSON |
| `routes/api.php` | 70 rutas: autenticación y perfil (`login`, `logout`, `me`, `me/current-branch`, `me/password`), clientes, pacientes y su foto, especies y razas, sucursales y depósitos, usuarios y roles, veterinarios, servicios, la configuración y el logo de la clínica, la auditoría, las agendas y sus bloqueos, y los turnos |
| `database/seeders` | `RolesAndPermissionsSeeder` (131 permisos, 3 roles), `SpeciesSeeder` y `DemoSeeder` (datos de demostración, solo en `local` y `testing`) |

## Tablas por módulo

| Módulo | Tablas |
|---|---|
| Clínicas | clinics |
| Sucursales | branches, branch_user, warehouses |
| Personas | users, veterinarians, clients |
| Pacientes | species, breeds, patients |
| Agenda | services, schedules, schedule_hours, schedule_blocks, appointments |
| Historia clínica | consultations, consultation_attachments, consultation_items, patient_applications |
| Documentos | document_templates, patient_documents |
| Avisos | notifications, reminders, client_notifications |
| Inventario | products, product_stocks, product_lots, inventory_movements |
| Caja y facturación | cash_registers, cash_movements, invoice_stamps, invoices, invoice_items, payments, sifen_events |
| Sistema | audit_logs, system_settings, document_sequences, cache, jobs, sessions, personal_access_tokens, permisos |

## Verificado

Con el código de Laravel 13:

* Las 27 migraciones que tenía el paquete base suben y bajan en SQLite con claves foráneas activas. Las 35 migraciones actuales suben en MySQL 8.4 (`syncvet_test_mysql`) con cada corrida de la suite; las de la FASE 1 (el documento del cliente, su número base, el microchip del paciente y la baja de especies y razas) se probaron también bajando y volviendo a subir, y las de la 2.2b (`users.is_active`) y la 2.3 (la matrícula del veterinario) también; la bajada de la 2.3 tiene además un test en MySQL. En la entrega 0.2 se verificaron en MySQL las 28 que había entonces ([resultado](docs/fases/fase-00-puesta-en-marcha.md#entrega-02)).
* Generan 221 sentencias MySQL; el nombre de índice más largo tiene 56 caracteres (límite 64). Se midió sobre el
  `schema-mysql.sql` del paquete base, quitado en el registro R-005: lo reemplazan la verificación en MySQL de la entrega 0.5 y
  `SchemaNamesTest`, que comprueba los nombres en cada corrida.
* Prueba funcional de los Services: alta de cliente persona y empresa, códigos `PAC-000001` y `PAC-000002`, raza de otra
  especie (422), mismo dueño (422), cambio de dueño, baja con pacientes (409), baja que libera el documento, auditoría.
* Los 131 permisos de los Controllers y de los roles existen en el seeder.

La suite de PHPUnit (`php artisan test`) cubre los roles y permisos del seeder y de las rutas, y las respuestas de error en JSON
(entrega 0.1); la separación por clínica (`tests/Feature/Tenancy`, 0.2); la autenticación (`tests/Feature/Auth`, 0.3);
`clinic:create`, el `DemoSeeder` y la regla de contraseñas (`tests/Feature/Console`, `Seeders`, `Security` y `tests/Unit`, 0.4); y el
acceso por rol a cada ruta, los nombres del esquema y los documentos de ejemplo ficticios (`tests/Feature/Access` y `Database`,
0.5); cada regla de clientes, pacientes, especies y razas (FASE 1) y de sucursales y depósitos, usuarios, veterinarios, servicios,
la configuración y la auditoría (FASE 2), con los mensajes en voseo y las fechas con hora. Al cierre de la FASE 2: 988 casos, en
SQLite y en MySQL ([cierre](docs/fases/fase-02-datos-maestros.md#cierre-de-la-fase-2)).

## Instalación

Proyecto Laravel 13 con Sanctum 4 y spatie/laravel-permission 8 (entrega 0.1 de la FASE 0). Requisitos: PHP 8.3+ con `bcmath`,
`intl`, `pdo_mysql`, `pdo_sqlite`, `gd` (con JPEG, PNG y WEBP) y `exif` (la foto del paciente; [`setup.md`](docs/development/setup.md)),
y la base de zonas horarias 2025a o posterior (Paraguay en UTC-3 todo el año; en Laragon, la extensión `timezonedb`,
[`setup.md`](docs/development/setup.md#zona-horaria-de-paraguay-base-de-zonas-de-php)); Composer; MySQL 8.

```bash
git clone https://github.com/rubenminarro/syncvet.git
cd syncvet
composer install
cp .env.example .env
php artisan key:generate
```

1. Crear la base en MySQL: `CREATE DATABASE syncvet CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;`
2. Revisar en `.env` los datos de conexión (`DB_*`). La zona horaria (`America/Asuncion`) y el idioma (`es`) ya están en
   `config/app.php`; los tokens duran `SANCTUM_EXPIRATION` minutos (720).
3. Migrar y sembrar roles, permisos y especies: `php artisan migrate --seed`.
4. Crear una clínica con su sucursal, su depósito y su primer administrador: `php artisan clinic:create` (pide los datos y la
   contraseña; con `--no-interaction` y todas las opciones, genera la contraseña y la muestra una sola vez). Ver
   `php artisan clinic:create --help`.
5. Solo en desarrollo, datos de demostración: definir `DEMO_PASSWORD` en `.env` y correr `php artisan db:seed --class=DemoSeeder`
   (dos clínicas con un usuario por rol, clientes y pacientes; los usuarios están en
   [`docs/testing/guia-postman.md`](docs/testing/guia-postman.md)). Se niega fuera de `local` y `testing`.
6. Correr los tests: `php artisan test` (SQLite en `database/testing.sqlite`, no toca MySQL). En MySQL, sobre una base aparte
   (`syncvet_test_mysql`): `DB_CONNECTION=mysql DB_DATABASE=syncvet_test_mysql php artisan test`. Cuándo correr cada una:
   `CLAUDE.md` §3, Entorno.

## Variables de entorno propias

| Variable | Valor por defecto | Para qué |
|---|---|---|
| `EMAIL_DNS_CHECK` | `true` | Validar que el dominio del email exista. `phpunit.xml` lo desactiva |
| `SANCTUM_EXPIRATION` | `720` | Minutos de vida de un token de la API (registro R-004) |
| `TRUSTED_PROXIES` | vacía | Proxies de confianza para `X-Forwarded-For` ([`deployment.md`](docs/development/deployment.md)) |
| `DEMO_PASSWORD` | vacía | Contraseña de los usuarios del `DemoSeeder` (al menos 12 caracteres, con mayúsculas, minúsculas, números y símbolos; no puede aparecer en filtraciones conocidas). Sin ella, el seeder no corre |

## Pendiente

* Verificar los códigos SIFEN de `PaymentMethod::sifenCode()` y `invoice_items.unit_of_measure` contra el Manual Técnico.
