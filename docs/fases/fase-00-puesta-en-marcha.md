# FASE 0 — Puesta en marcha

Etapa: Base. Estado: **PENDIENTE**. El estado vigente de cada fase está en [`docs/roadmap/README.md`](../roadmap/README.md).

## Objetivo

Proyecto instalado y corriendo, con autenticación, la base de la arquitectura, tests y documentación.

## Alcance inicial

El diseño (paso 2) completa cada entrega con reglas de negocio, datos, endpoints, errores y tests.

| Entrega | Contenido | Estado |
|---|---|---|
| 0.1 | Crear el repositorio y el proyecto Laravel 13; copiar el paquete base | PENDIENTE |
| 0.2 | Aplicar D1: tabla `clinics`, `clinic_id`, scope y fallo cerrado (registro 0.2) | PENDIENTE |
| 0.3 | Instalar Sanctum y spatie/laravel-permission (guard `api`) | PENDIENTE |
| 0.4 | `AuthController`: login, logout y `me`; login y logout auditados | PENDIENTE |
| 0.5 | `phpunit.xml` con SQLite en archivo; roles sembrados una vez por ejecución; cada test en transacción | PENDIENTE |
| 0.6 | Tests: `tests/Feature/Auth`, `tests/Feature/Access` y `ClinicRolesTest` | PENDIENTE |
| 0.7 | Migraciones probadas también en MySQL | PENDIENTE |

## Preguntas de negocio

Las respuestas se anotan con la fecha y pasan a [`docs/decisions/registro.md`](../decisions/registro.md).

| # | Pregunta | Respuesta | Fecha |
|---|---|---|---|
| 1 | D1: ¿cómo se separan los datos de cada clínica? | Una base compartida con `clinic_id` (registro 0.2) | 2026-11-04 |
| 2 | ¿Los usuarios entran con email o con nombre de usuario? | | |
| 3 | ¿Los tokens vencen? ¿Cuánto duran? | | |
| 4 | ¿Un usuario puede trabajar en dos clínicas distintas? (D1 asume que no) | | |
| 5 | ¿Quién da de alta una clínica nueva: un superadministrador de la plataforma o un registro público? | | |

## Diseño

### 0.2 Separación por clínica (D1)

Decidido en el [registro 0.2](../decisions/registro.md); este es el punto de partida del diseño.

| Pieza | Detalle |
|---|---|
| Migración `clinics` | `name`, `legal_name`, `ruc`, `ruc_dv`, `timezone` (por defecto `America/Asuncion`), `status` (`ClinicStatus`: `active`, `suspended`), timestamps. Va antes que `users` |
| `clinic_id` | `foreignId('clinic_id')->constrained('clinics')->restrictOnDelete()` en cada tabla de negocio, con índice compuesto donde se filtra por fecha o estado. Como el esquema todavía no está en producción, se agrega en las migraciones existentes, no en migraciones nuevas |
| Únicos | Compuestos con `clinic_id`. Nombres propios si superan 64 caracteres |
| `App\Support\CurrentClinic` | Servicio `scoped`: `set(Clinic)`, `id()`, `run(Clinic, Closure)` para jobs y comandos |
| `App\Models\Concerns\BelongsToClinic` | Agrega `ClinicScope`, completa `clinic_id` al crear y define `clinic()` |
| `ClinicScope` | Filtra por `CurrentClinic::id()`; sin clínica lanza `ClinicContextMissingException` (fallo cerrado) |
| Middleware `SetCurrentClinic` | Después de `auth:sanctum`; usuario sin clínica o clínica suspendida → 403 `CLINIC_NOT_AVAILABLE` |
| Form Requests | `Rule::unique(...)->where('clinic_id', ...)` y `Rule::exists(...)->where('clinic_id', ...)` |
| `DocumentSequenceService` | Contador por clínica (`clinic_id` + `key`) y verificación del código dentro de la clínica |
| `ClinicService::create` | Crea clínica, sucursal `SUC-01` con establecimiento `001`, depósito `DEP-01` y configuración; reemplaza las filas por defecto de la migración 000005 |
| Tests de aislamiento | Listado, detalle (404 con id ajeno), alta (clinic_id forzado), validación de únicos y exists, secuencias por clínica, job sin clínica falla |

PENDIENTE: el resto del diseño lo completa el paso 2: lo completa el paso 2 ([`docs/prompts/2-diseno.md`](../prompts/2-diseno.md)), aplicando la regla de oro de
[`docs/project/principios.md`](../project/principios.md).

## Criterio de cierre

`migrate` corre en MySQL y en SQLite; login con cada rol; `ClinicRolesTest` verifica que existan todos los permisos de los roles; suite en verde.

## Prompt de implementación (paso 4)

Usar solo con el diseño aprobado.

```text
Implementa la FASE 0 según docs/fases/fase-00-puesta-en-marcha.md (diseño aprobado).

- Crea el proyecto Laravel 13 y copia el paquete base (app, bootstrap/app.php, config/validation.php, database, routes, docs, .claude, CLAUDE.md).
- Instala Sanctum (php artisan install:api) y spatie/laravel-permission; guard api.
- Aplica la decisión D1 de docs/decisions/registro.md al esquema y a los modelos.
- AuthController: POST /api/login (público, devuelve data.token), POST /api/logout, GET /api/me (usuario, permisos ordenados, ficha de veterinario o null). Login y logout quedan en audit_logs.
- phpunit.xml con SQLite en archivo; migrar y sembrar roles una vez por ejecución, cada test en transacción; EMAIL_DNS_CHECK=false.
- Tests: tests/Feature/Auth, tests/Feature/Access (cada rol contra rutas permitidas y prohibidas) y ClinicRolesTest.
- Corre las migraciones también en MySQL y verifica nombres de índices de hasta 64 caracteres.

Sigue CLAUDE.md. Al terminar, corre la suite e infórmame archivos, tests y resultados. No hagas commit.
```

## Resultado

PENDIENTE: lo completa el cierre ([`docs/prompts/5-cierre.md`](../prompts/5-cierre.md)): archivos, endpoints, tests y aserciones,
desvíos del diseño y por qué.
