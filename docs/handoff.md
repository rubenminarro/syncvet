# Handoff de SyncVet

Documento principal para retomar el desarrollo desde cualquier computadora, con o sin el contexto de sesiones anteriores.

> **Sesión nueva de Claude Code:** ejecutar `/retomar`, o leer este documento y [`CLAUDE.md`](../CLAUDE.md), verificar el estado de
> Git y continuar desde [Próximas tareas](#próximas-tareas). [`README.md`](README.md) indica dónde buscar cada tema.

Si este documento contradice al código, manda el código.

## Punto de continuidad

| Dato | Valor |
|---|---|
| Actualizado | 2026-11-05 |
| Backend | Repositorio [`rubenminarro/syncvet`](https://github.com/rubenminarro/syncvet). Proyecto Laravel 13 sobre el paquete base, en la rama `feature/fase-00-02-clinicas`, que sigue a `feature/fase-00-paquete-base` (sin merge a `main`) |
| Último commit de código | `cf8f862` Corrige los hallazgos de la revisión de la entrega 0.2 (FASE 0, D1), en `origin/feature/fase-00-02-clinicas`. `origin/main` está en `3eee538` |
| Frontend | Sin empezar |
| Última entrega de código | FASE 0, entrega 0.2: separación por clínica, D1 (registro R-007) |
| Tests del backend | Corrida de `php artisan test` del 2026-11-05: 75 tests (90 casos con los *data providers*) y 290 aserciones en verde, en SQLite y contra MySQL 8.4 (`syncvet_test_mysql`). `tests/Feature` (entrega 0.1) y `tests/Feature/Tenancy` (entrega 0.2) |
| Último checkpoint validado | Entrega 0.2 en `cf8f862`: suite en verde; el usuario validó la suite y `migrate:fresh --seed` el 2026-11-05 ([resultado](fases/fase-00-puesta-en-marcha.md#entrega-02)) |

El estado actual siempre se confirma con `git log origin/main` y `php artisan test`.

## Estado del proyecto

SyncVet es la API de gestión de una clínica veterinaria (Paraguay), con el frontend en otro repositorio. El plan del MVP tiene 11
fases ([`roadmap/README.md`](roadmap/README.md)); la FASE 0 está en desarrollo, con las entregas 0.1 y 0.2 cerradas.

**El sistema no está listo para producción.**

## Próximas tareas

| Orden | Tarea | Bloqueo |
|---|---|---|
| 1 | FASE 0, entrega 0.3: autenticación ([`fases/fase-00-puesta-en-marcha.md`](fases/fase-00-puesta-en-marcha.md)). Diseño aprobado (R-005). Agregar `login`, `logout` y `me` a `ROUTES_WITHOUT_PERMISSION` de `ClinicRolesTest` | Ninguno |
| 2 | FASE 0, entregas 0.4 y 0.5 (`clinic:create`, acceso por rol y MySQL) | Cierre de la entrega anterior |
| 3 | FASE 1: clientes y pacientes. En el ABM de especies y razas, cubrir `insert()` y `upsert()` ([resultado de la 0.2](fases/fase-00-puesta-en-marcha.md#entrega-02)) | FASE 0 cerrada |

Ninguna entrega empieza sin autorización del usuario.

## Decisiones técnicas que hay que tener presentes

| Tema | Decisión |
|---|---|
| Arquitectura | Controller → Form Request → Service → Model → Resource → ApiResponse (`CLAUDE.md` §4) |
| Estados | `string(20)` en la base y Enum en PHP; nunca columnas `ENUM` de MySQL |
| Dinero | `decimal(14,2)` con BCMath; importes en PYG redondeados a 0 decimales (R-004) |
| Zona horaria | `America/Asuncion` en la base; la API envía UTC |
| Separación por clínica | Una base compartida con `clinic_id`, scope global y fallo cerrado (D1, registros R-002 y R-004). Implementada en la entrega 0.2 (R-007); reglas en `CLAUDE.md` §3 |
| Autenticación | Login con email; tokens de 12 horas; 5 intentos por minuto por email e IP; clínicas creadas con `clinic:create` (R-004) |
| Facturación | SIFEN (Paraguay), en las FASES 8 y 9 |
