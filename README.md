# SyncVet: backend base (Laravel 13)

Esquema del MVP y arquitectura base de SyncVet. Las reglas de trabajo están en `CLAUDE.md`.

## Qué incluye

| Carpeta | Contenido |
|---|---|
| `database/migrations` | 28 migraciones: clínicas, base de Laravel (usuarios, cache, colas, tokens, permisos), 19 de SyncVet, auditoría, configuración y secuencias. `clinic_id` en las tablas de negocio (D1) |
| `app/Enums` | 34 enums (estados y tipos) con `label()` en español |
| `app/Models` | 34 modelos con casts, relaciones y `Auditable` / `SoftDeletes` donde corresponde; los de negocio con `BelongsToClinic`, especies y razas con `SharedCatalog` |
| `app/Exceptions` | `AppException`, 13 excepciones de dominio y `ClinicContextMissingException` (fallo cerrado) |
| `app/Services` | `ClientService`, `PatientService`, `ClinicService`, `DocumentSequenceService`, `AuditLogger` |
| `app/Http` | Controllers, Requests y Resources de clientes, pacientes y especies; middleware `EnsureJsonResponse` y `SetCurrentClinic` |
| `app/Traits`, `app/Support` | `ApiResponse`, `Auditable`, `Decimal`, `CurrentClinic` (clínica en contexto), `ExecutionOrigin` |
| `bootstrap/app.php` | Mapeo de excepciones a JSON |
| `routes/api.php` | Rutas de clientes, pacientes y especies |
| `database/seeders` | `RolesAndPermissionsSeeder` (119 permisos, 3 roles) y `SpeciesSeeder` |

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

* Las 27 migraciones que tenía el paquete base suben y bajan en SQLite con claves foráneas activas. Las 28 actuales se verificaron también en MySQL 8.4 en la entrega 0.2 ([resultado](docs/fases/fase-00-puesta-en-marcha.md#entrega-02)).
* Generan 221 sentencias MySQL; el nombre de índice más largo tiene 56 caracteres (límite 64). Se midió sobre el
  `schema-mysql.sql` del paquete base, quitado en el registro R-005: la verificación en MySQL de la entrega 0.5 la reemplaza.
* Prueba funcional de los Services: alta de cliente persona y empresa, códigos `PAC-000001` y `PAC-000002`, raza de otra
  especie (422), mismo dueño (422), cambio de dueño, baja con pacientes (409), baja que libera el documento, auditoría.
* Los 119 permisos de los Controllers y de los roles existen en el seeder.

La suite de PHPUnit (`php artisan test`, desde la entrega 0.1) cubre los roles y permisos del seeder y de las rutas, las
respuestas de error en JSON y, desde la entrega 0.2, la separación por clínica (`tests/Feature/Tenancy`). Los Form Requests y las rutas de clientes y pacientes todavía no tienen tests (deuda técnica #1).

## Instalación

Proyecto Laravel 13 con Sanctum 4 y spatie/laravel-permission 8 (entrega 0.1 de la FASE 0). Requisitos: PHP 8.3+ con `bcmath`,
`intl`, `pdo_mysql` y `pdo_sqlite`; Composer; MySQL 8.

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
6. Correr los tests: `php artisan test` (SQLite en `database/testing.sqlite`, no toca MySQL).

## Variables de entorno propias

| Variable | Valor por defecto | Para qué |
|---|---|---|
| `EMAIL_DNS_CHECK` | `true` | Validar que el dominio del email exista. `phpunit.xml` lo desactiva |
| `SANCTUM_EXPIRATION` | `720` | Minutos de vida de un token de la API (registro R-004) |
| `TRUSTED_PROXIES` | vacía | Proxies de confianza para `X-Forwarded-For` ([`deployment.md`](docs/development/deployment.md)) |
| `DEMO_PASSWORD` | vacía | Contraseña de los usuarios del `DemoSeeder` (al menos 12 caracteres, con mayúsculas, minúsculas, números y símbolos; no puede aparecer en filtraciones conocidas). Sin ella, el seeder no corre |

## Pendiente

* Separación por clínica (D1), autenticación, `clinic:create` y acceso por rol: entregas 0.2 a 0.5 de la FASE 0.
* Tests de Feature de clientes y pacientes (FASE 1).
* Verificar los códigos SIFEN de `PaymentMethod::sifenCode()` y `invoice_items.unit_of_measure` contra el Manual Técnico.
