# Handoff de SyncVet

Documento principal para retomar el desarrollo desde cualquier computadora, con o sin el contexto de sesiones anteriores.

> **Sesión nueva de Claude Code:** ejecutar `/retomar`, o leer este documento y [`CLAUDE.md`](../CLAUDE.md), verificar el estado de
> Git y continuar desde [Próximas tareas](#próximas-tareas). [`README.md`](README.md) indica dónde buscar cada tema.

Si este documento contradice al código, manda el código.

## Punto de continuidad

| Dato | Valor |
|---|---|
| Actualizado | 2026-11-06 |
| Backend | Repositorio [`rubenminarro/syncvet`](https://github.com/rubenminarro/syncvet). Proyecto Laravel 13 sobre el paquete base. La FASE 0 se integró a `main` con el merge `63b3eff` (ramas `feature/fase-00-paquete-base` → … → `feature/fase-00-05-acceso`). La FASE 1 avanza en ramas sin integrar a `main`: `docs/fase-01-diseno` (`c6cd327`, diseño), encima `feature/fase-01-01-traducciones` (entrega 1.1), encima `feature/fase-01-02-clientes` (entrega 1.2) y, encima, `feature/fase-01-03-pacientes` (entrega 1.3) |
| Último commit de código | `837484a` Corrige los hallazgos de la revisión de la entrega 1.3 (R-021), sobre `fd2334f` (implementación de la 1.3), en `feature/fase-01-03-pacientes`, sin integrar a `main` |
| Frontend | Sin empezar |
| Última entrega de código | FASE 1, entrega 1.3: pacientes, con los tests de cada regla de §6.2, sin `custom_fields` en la API, el microchip a 60 caracteres y `deceased_at` en la hora de Asunción (registro R-020), y las correcciones de su revisión (R-021) |
| Tests del backend | Corrida de `php artisan test` del 2026-11-06 sobre `837484a`: 449 casos con los *data providers* en verde; 5626 aserciones en SQLite (2 omitidos, solo de MySQL) y 5642 contra MySQL 8.4.3 (`syncvet_test_mysql`). `tests/Feature` (entrega 0.1), `tests/Feature/Tenancy` (0.2), `tests/Feature/Auth` con `TrustedProxiesTest` (0.3), `tests/Feature/Console`, `Seeders`, `Security` y `tests/Unit` (0.4), `tests/Feature/Access` y `Database` (0.5), `tests/Feature/Localization`, `HttpVerbsTest` y `VoseoTest` (1.1), `tests/Feature/Clients`, `PatientDeleteTest` y `DocumentBaseTest` (1.2), y `tests/Feature/Patients` (1.3). Cuándo correr cada base: `CLAUDE.md` §3, Entorno |
| Último checkpoint validado | Entrega 1.3 con las correcciones de la revisión (R-021), en `837484a`: suite en verde en SQLite y MySQL, y las colecciones de clientes y de pacientes con Newman, dos veces seguidas sin recrear la base ([resultado de la 1.3](fases/fase-01-clientes-y-pacientes.md#entrega-13)); falta la validación del usuario. Antes, entrega 1.2 en `544f5cd`: suite en verde en SQLite y MySQL; el usuario la validó el 2026-11-05 con la colección de Postman ([resultado de la 1.2](fases/fase-01-clientes-y-pacientes.md#entrega-12)). El "Errors 1" del Collection Runner quedó PENDIENTE DE VERIFICACIÓN: Newman no lo reproduce |

El estado actual siempre se confirma con `git log origin/main` y `php artisan test`.

## Estado del proyecto

SyncVet es la API de gestión de una clínica veterinaria (Paraguay), con el frontend en otro repositorio. El plan del MVP tiene 11
fases ([`roadmap/README.md`](roadmap/README.md)); la FASE 0 está completada (R-014) y la FASE 1 está en desarrollo, con las entregas
1.1 (R-017), 1.2 (R-018 y R-019) y 1.3 (R-020) cerradas.

**El sistema no está listo para producción.**

## Próximas tareas

| Orden | Tarea | Bloqueo |
|---|---|---|
| 1 | FASE 1: clientes y pacientes ([`fases/fase-01-clientes-y-pacientes.md`](fases/fase-01-clientes-y-pacientes.md)). Preguntas de negocio respondidas (R-015) y diseño aprobado el 2026-11-05 con cinco entregas, 1.1 a 1.5 (R-016). Entregas 1.1 (R-017), 1.2 (R-018 y R-019) y 1.3 (R-020 y R-021) cerradas. Sigue la entrega 1.4: ABM de especies y razas (`is_active`, `SoftDeletes`, nombres únicos) y la deuda 8. Antes de probar a mano, aplicar las migraciones nuevas (`php artisan migrate`) o recrear la base ([`development/setup.md`](development/setup.md)) | Autorización del usuario para cada entrega |
| 2 | Validar la entrega 1.3 con la colección de pacientes ([`testing/guia-postman.md`](testing/guia-postman.md)) | Ninguno |
| 3 | Opcional: ver en el Collection Runner el detalle del "Errors 1" de la validación de la 1.2 (PENDIENTE DE VERIFICACIÓN; Newman no lo reproduce) | Ninguno |

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
