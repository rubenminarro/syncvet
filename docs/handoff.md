# Handoff de SyncVet

Documento principal para retomar el desarrollo desde cualquier computadora, con o sin el contexto de sesiones anteriores.

> **Sesión nueva de Claude Code:** ejecutar `/retomar`, o leer este documento y [`CLAUDE.md`](../CLAUDE.md), verificar el estado de
> Git y continuar desde [Próximas tareas](#próximas-tareas). [`README.md`](README.md) indica dónde buscar cada tema.

Si este documento contradice al código, manda el código.

## Punto de continuidad

| Dato | Valor |
|---|---|
| Actualizado | 2026-11-05 |
| Backend | Repositorio [`rubenminarro/syncvet`](https://github.com/rubenminarro/syncvet). Proyecto Laravel 13 sobre el paquete base, en la rama `feature/fase-00-04-clinic-create`, que sigue a `feature/fase-00-03-auth`, `feature/fase-00-02-clinicas` y `feature/fase-00-paquete-base` (sin merge a `main`) |
| Último commit de código | `e7047b2` Corrige los hallazgos de la segunda revisión de la entrega 0.4 (R-011), en `origin/feature/fase-00-04-clinic-create`. `origin/main` está en `3eee538` |
| Frontend | Sin empezar |
| Última entrega de código | FASE 0, entrega 0.4: `clinic:create` y `DemoSeeder` (registro R-012) |
| Tests del backend | Corrida de `php artisan test` del 2026-11-05 sobre `e7047b2`: 185 métodos de test (222 casos con los *data providers*) en verde; 3208 aserciones en SQLite (1 omitido: el de la collation de MySQL) y 3222 contra MySQL 8.4.3 (`syncvet_test_mysql`). `tests/Feature` (entrega 0.1), `tests/Feature/Tenancy` (0.2), `tests/Feature/Auth` con `TrustedProxiesTest` (0.3), y `tests/Feature/Console`, `Seeders`, `Security` y `tests/Unit` (0.4). Los tests no escriben en `storage/logs` (`LOG_CHANNEL=null`) |
| Último checkpoint validado | Entrega 0.4 en `e7047b2`: suite en verde en SQLite y MySQL; el usuario la validó el 2026-11-05 a mano (`DemoSeeder`, `clinic:create` y Postman, que completa la prueba pendiente de la 0.3) ([resultado](fases/fase-00-puesta-en-marcha.md#entrega-04)) |

El estado actual siempre se confirma con `git log origin/main` y `php artisan test`.

## Estado del proyecto

SyncVet es la API de gestión de una clínica veterinaria (Paraguay), con el frontend en otro repositorio. El plan del MVP tiene 11
fases ([`roadmap/README.md`](roadmap/README.md)); la FASE 0 está en desarrollo, con las entregas 0.1 a 0.4 cerradas.

**El sistema no está listo para producción.**

## Próximas tareas

| Orden | Tarea | Bloqueo |
|---|---|---|
| 1 | FASE 0, entrega 0.5: acceso por rol (`tests/Feature/Access`) y verificación final en MySQL ([`fases/fase-00-puesta-en-marcha.md`](fases/fase-00-puesta-en-marcha.md)). Diseño aprobado (R-005) | Autorización del usuario |
| 2 | FASE 1: clientes y pacientes. En el ABM de especies y razas, cubrir `insert()` y `upsert()` ([resultado de la 0.2](fases/fase-00-puesta-en-marcha.md#entrega-02)); cédulas de ejemplo ficticias en los tests (deuda 7) | FASE 0 cerrada |

Ninguna entrega empieza sin autorización del usuario.

## Decisiones técnicas que hay que tener presentes

| Tema | Decisión |
|---|---|
| Arquitectura | Controller → Form Request → Service → Model → Resource → ApiResponse (`CLAUDE.md` §4) |
| Estados | `string(20)` en la base y Enum en PHP; nunca columnas `ENUM` de MySQL |
| Dinero | `decimal(14,2)` con BCMath; importes en PYG redondeados a 0 decimales (R-004) |
| Zona horaria | `America/Asuncion` en la base; la API envía UTC |
| Separación por clínica | Una base compartida con `clinic_id`, scope global y fallo cerrado (D1, registros R-002 y R-004). Implementada en la entrega 0.2 (R-007); reglas en `CLAUDE.md` §3 |
| Autenticación | Login con email (en minúsculas y solo ASCII); tokens de 12 horas; 5 intentos fallidos por minuto por usuario e IP, o por email si no existe (R-004, R-008); clínicas creadas con `clinic:create` (R-004). En producción, caché en Redis para el límite (deuda 6) |
| Contraseñas | 12 caracteres con mayúsculas, minúsculas, números y símbolos, y no filtrada (Have I Been Pwned); `App\Support\UserPassword` (R-009). Ninguna contraseña llega a una traza ni al log (R-010). Datos de ejemplo con documentos ficticios (R-011). En Laragon, `curl.cainfo` correcto ([`development/setup.md`](development/setup.md)) |
| Facturación | SIFEN (Paraguay), en las FASES 8 y 9 |
