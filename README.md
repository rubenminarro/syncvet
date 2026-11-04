# SyncVet: backend base (Laravel 13)

Esquema del MVP y arquitectura base de SyncVet. Las reglas de trabajo están en `CLAUDE.md`.

## Qué incluye

| Carpeta | Contenido |
|---|---|
| `database/migrations` | 27 migraciones: base de Laravel (usuarios, cache, colas, tokens, permisos) + 19 de SyncVet + auditoría, configuración y secuencias |
| `app/Enums` | 33 enums (estados y tipos) con `label()` en español |
| `app/Models` | 33 modelos con casts, relaciones y `Auditable` / `SoftDeletes` donde corresponde |
| `app/Exceptions` | `AppException` y 11 excepciones de dominio |
| `app/Services` | `ClientService`, `PatientService`, `DocumentSequenceService`, `AuditLogger` |
| `app/Http` | Controllers, Requests y Resources de clientes, pacientes y especies; middleware `EnsureJsonResponse` |
| `app/Traits`, `app/Support` | `ApiResponse`, `Auditable`, `Decimal` |
| `bootstrap/app.php` | Mapeo de excepciones a JSON |
| `routes/api.php` | Rutas de clientes, pacientes y especies |
| `database/seeders` | `RolesAndPermissionsSeeder` (119 permisos, 3 roles) y `SpeciesSeeder` |
| `database/schema-mysql.sql` | SQL de MySQL que generan las migraciones (referencia) |

## Tablas por módulo

| Módulo | Tablas |
|---|---|
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

* Las 27 migraciones suben y bajan en SQLite con claves foráneas activas.
* Generan 221 sentencias MySQL; el nombre de índice más largo tiene 56 caracteres (límite 64).
* Prueba funcional de los Services: alta de cliente persona y empresa, códigos `PAC-000001` y `PAC-000002`, raza de otra
  especie (422), mismo dueño (422), cambio de dueño, baja con pacientes (409), baja que libera el documento, auditoría.
* Los 119 permisos de los Controllers y de los roles existen en el seeder.

No se probaron los Form Requests ni las rutas HTTP: hay que correrlos con PHPUnit en el proyecto real.

## Instalación

```bash
composer create-project laravel/laravel syncvet-backend
cd syncvet-backend
php artisan install:api
composer require spatie/laravel-permission
```

1. Copiar `app/`, `bootstrap/app.php`, `config/validation.php`, `database/`, `routes/api.php` y `CLAUDE.md` sobre el
   proyecto (reemplazan los de Laravel).
2. En `.env`: `APP_TIMEZONE=America/Asuncion`, `DB_CONNECTION=mysql`, `DB_DATABASE=syncvet`.
3. Migrar y sembrar:

```bash
php artisan migrate
php artisan db:seed --class=RolesAndPermissionsSeeder
php artisan db:seed --class=SpeciesSeeder
```

## Pendiente

* Login, logout y `me` (`AuthController`).
* Tests de Feature por módulo (`tests/Feature/Clients`, `tests/Feature/Patients`, `tests/Feature/Access`).
* Decidir cómo se separan los datos de cada clínica (ver `CLAUDE.md`, sección 1).
* Verificar los códigos SIFEN de `PaymentMethod::sifenCode()` y `invoice_items.unit_of_measure` contra el Manual Técnico.
