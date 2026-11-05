# Handoff de SyncVet

Documento principal para retomar el desarrollo desde cualquier computadora, con o sin el contexto de sesiones anteriores.

> **Sesión nueva de Claude Code:** ejecutar `/retomar`, o leer este documento y [`CLAUDE.md`](../CLAUDE.md), verificar el estado de
> Git y continuar desde [Próximas tareas](#próximas-tareas). [`README.md`](README.md) indica dónde buscar cada tema.

Si este documento contradice al código, manda el código.

## Punto de continuidad

| Dato | Valor |
|---|---|
| Actualizado | 2026-11-05 |
| Backend | Repositorio [`rubenminarro/syncvet`](https://github.com/rubenminarro/syncvet). Proyecto Laravel 13 sobre el paquete base, en la rama `feature/fase-00-03-auth`, que sigue a `feature/fase-00-02-clinicas` y a `feature/fase-00-paquete-base` (sin merge a `main`) |
| Último commit de código | `727546e` Registra la deuda del límite en ráfagas y completa la segunda revisión de la 0.3, en `origin/feature/fase-00-03-auth`. `origin/main` está en `3eee538` |
| Frontend | Sin empezar |
| Última entrega de código | FASE 0, entrega 0.3: autenticación (registro R-008) |
| Tests del backend | Corrida de `php artisan test` del 2026-11-05 sobre `727546e`: 132 métodos de test (149 casos con los *data providers*) en verde; 689 aserciones en SQLite (1 omitido: el de la collation de MySQL) y 703 contra MySQL 8.4.3 (`syncvet_test_mysql`). `tests/Feature` (entrega 0.1), `tests/Feature/Tenancy` (entrega 0.2) y `tests/Feature/Auth` con `TrustedProxiesTest` (entrega 0.3) |
| Último checkpoint validado | Entrega 0.3 en `727546e`: suite en verde; el usuario la validó el 2026-11-05 y dejó la prueba con Postman para la 0.4 ([resultado](fases/fase-00-puesta-en-marcha.md#entrega-03)) |

El estado actual siempre se confirma con `git log origin/main` y `php artisan test`.

## Estado del proyecto

SyncVet es la API de gestión de una clínica veterinaria (Paraguay), con el frontend en otro repositorio. El plan del MVP tiene 11
fases ([`roadmap/README.md`](roadmap/README.md)); la FASE 0 está en desarrollo, con las entregas 0.1, 0.2 y 0.3 cerradas.

**El sistema no está listo para producción.**

## Próximas tareas

| Orden | Tarea | Bloqueo |
|---|---|---|
| 1 | FASE 0, entrega 0.4: `clinic:create` y `DemoSeeder` ([`fases/fase-00-puesta-en-marcha.md`](fases/fase-00-puesta-en-marcha.md)). Diseño aprobado (R-005). Con el `DemoSeeder`, recorrer con Postman la sección de la FASE 0 de [`testing/guia-postman.md`](testing/guia-postman.md) (pendiente de la 0.3) | Autorización del usuario |
| 2 | FASE 0, entrega 0.5 (acceso por rol y verificación en MySQL) | Cierre de la entrega anterior |
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
| Autenticación | Login con email (en minúsculas y solo ASCII); tokens de 12 horas; 5 intentos fallidos por minuto por usuario e IP, o por email si no existe (R-004, R-008); clínicas creadas con `clinic:create` (R-004). En producción, caché en Redis para el límite (deuda 6) |
| Facturación | SIFEN (Paraguay), en las FASES 8 y 9 |
