# Handoff de SyncVet

Documento principal para retomar el desarrollo desde cualquier computadora, con o sin el contexto de sesiones anteriores.

> **Sesión nueva de Claude Code:** ejecutar `/retomar`, o leer este documento y [`CLAUDE.md`](../CLAUDE.md), verificar el estado de
> Git y continuar desde [Próximas tareas](#próximas-tareas). [`README.md`](README.md) indica dónde buscar cada tema.

Si este documento contradice al código, manda el código.

## Punto de continuidad

| Dato | Valor |
|---|---|
| Actualizado | 2026-11-05 |
| Backend | Repositorio [`rubenminarro/syncvet`](https://github.com/rubenminarro/syncvet). Proyecto Laravel 13 sobre el paquete base. La FASE 0 se integró a `main` con el merge `63b3eff` (ramas `feature/fase-00-paquete-base` → … → `feature/fase-00-05-acceso`). La FASE 1 avanza en ramas sin integrar a `main`: `docs/fase-01-diseno` (`c6cd327`, diseño) y, encima, `feature/fase-01-01-traducciones` (entrega 1.1) |
| Último commit de código | `0675c5b` Agrega las traducciones de validación y pasa los mensajes a voseo (FASE 1, entrega 1.1), en `feature/fase-01-01-traducciones`, sin integrar a `main` |
| Frontend | Sin empezar |
| Última entrega de código | FASE 1, entrega 1.1: traducciones de validación, voseo y `PUT`/`PATCH` (registro R-017) |
| Tests del backend | Corrida de `php artisan test` del 2026-11-05 sobre `0675c5b`: 220 métodos de test (260 casos con los *data providers*) en verde; 5043 aserciones en SQLite (2 omitidos, solo de MySQL) y 5059 contra MySQL 8.4.3 (`syncvet_test_mysql`). `tests/Feature` (entrega 0.1), `tests/Feature/Tenancy` (0.2), `tests/Feature/Auth` con `TrustedProxiesTest` (0.3), `tests/Feature/Console`, `Seeders`, `Security` y `tests/Unit` (0.4), `tests/Feature/Access` y `Database` (0.5), y `tests/Feature/Localization`, `HttpVerbsTest` y `VoseoTest` (1.1). Unos 34 s en SQLite y 93 s en MySQL. Cuándo correr cada base: `CLAUDE.md` §3, Entorno |
| Último checkpoint validado | Entrega 1.1 en `0675c5b`: suite en verde en SQLite y MySQL; el usuario la validó el 2026-11-05 ([resultado de la 1.1](fases/fase-01-clientes-y-pacientes.md#entrega-11)). La revisión dejó cuatro huecos de los tests sin corregir (deudas 9 a 12) |

El estado actual siempre se confirma con `git log origin/main` y `php artisan test`.

## Estado del proyecto

SyncVet es la API de gestión de una clínica veterinaria (Paraguay), con el frontend en otro repositorio. El plan del MVP tiene 11
fases ([`roadmap/README.md`](roadmap/README.md)); la FASE 0 está completada (R-014) y la FASE 1 está en desarrollo, con la entrega
1.1 cerrada (R-017).

**El sistema no está listo para producción.**

## Próximas tareas

| Orden | Tarea | Bloqueo |
|---|---|---|
| 1 | FASE 1: clientes y pacientes ([`fases/fase-01-clientes-y-pacientes.md`](fases/fase-01-clientes-y-pacientes.md)). Preguntas de negocio respondidas (R-015) y diseño aprobado el 2026-11-05 con cinco entregas, 1.1 a 1.5 (R-016). Entrega 1.1 cerrada (R-017). Sigue la entrega 1.2: clientes (tests de cada regla de `CLAUDE.md` 6.1 y dígito verificador del RUC) y las deudas 9 a 12 de la 1.1 | Autorización del usuario para cada entrega |

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
| Contraseñas | 12 caracteres con mayúsculas, minúsculas, números y símbolos, y no filtrada (Have I Been Pwned); `App\Support\UserPassword` (R-009). Ninguna contraseña llega a una traza ni al log (R-010). Datos de ejemplo con documentos ficticios (R-011, R-013). En Laragon, `curl.cainfo` correcto ([`development/setup.md`](development/setup.md)) |
| Facturación | SIFEN (Paraguay), en las FASES 8 y 9 |
