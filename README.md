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
* Generan 221 sentencias MySQL; el nombre de índice más largo tiene 56 caracteres (límite 64). Se midió sobre el
  `schema-mysql.sql` del paquete base, quitado en el registro R-005: la verificación en MySQL de la entrega 0.5 la reemplaza.
* Prueba funcional de los Services: alta de cliente persona y empresa, códigos `PAC-000001` y `PAC-000002`, raza de otra
  especie (422), mismo dueño (422), cambio de dueño, baja con pacientes (409), baja que libera el documento, auditoría.
* Los 119 permisos de los Controllers y de los roles existen en el seeder.

La suite de PHPUnit (`php artisan test`, desde la entrega 0.1) cubre los roles y permisos del seeder y de las rutas, y las
respuestas de error en JSON. Los Form Requests y las rutas de clientes y pacientes todavía no tienen tests (deuda técnica #1).

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
4. Correr los tests: `php artisan test` (SQLite en `database/testing.sqlite`, no toca MySQL).

## Variables de entorno propias

| Variable | Valor por defecto | Para qué |
|---|---|---|
| `EMAIL_DNS_CHECK` | `true` | Validar que el dominio del email exista. `phpunit.xml` lo desactiva |
| `SANCTUM_EXPIRATION` | `720` | Minutos de vida de un token de la API (registro R-004) |

## Pendiente

* Separación por clínica (D1), autenticación, `clinic:create` y acceso por rol: entregas 0.2 a 0.5 de la FASE 0.
* Tests de Feature de clientes y pacientes (FASE 1).
* Verificar los códigos SIFEN de `PaymentMethod::sifenCode()` y `invoice_items.unit_of_measure` contra el Manual Técnico.
