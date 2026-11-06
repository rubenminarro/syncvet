# Handoff de SyncVet

Documento principal para retomar el desarrollo desde cualquier computadora, con o sin el contexto de sesiones anteriores.

> **Sesión nueva de Claude Code:** ejecutar `/retomar`, o leer este documento y [`CLAUDE.md`](../CLAUDE.md), verificar el estado de
> Git y continuar desde [Próximas tareas](#próximas-tareas). [`README.md`](README.md) indica dónde buscar cada tema.

Si este documento contradice al código, manda el código.

## Punto de continuidad

| Dato | Valor |
|---|---|
| Actualizado | 2026-11-06 |
| Backend | Repositorio [`rubenminarro/syncvet`](https://github.com/rubenminarro/syncvet). Proyecto Laravel 13 sobre el paquete base. La FASE 0 se integró a `main` con el merge `63b3eff` (ramas `feature/fase-00-paquete-base` → … → `feature/fase-00-05-acceso`). La FASE 1 está completada (R-028) en ramas sin integrar a `main`: `docs/fase-01-diseno` (`c6cd327`, diseño), encima `feature/fase-01-01-traducciones` (entrega 1.1), encima `feature/fase-01-02-clientes` (entrega 1.2), encima `feature/fase-01-03-pacientes` (entrega 1.3), encima `feature/fase-01-04-especies` (entrega 1.4) y, encima, `feature/fase-01-05-foto` (entrega 1.5) |
| Último commit de código | `79f7e8e`, las correcciones de la revisión de la 1.5 (R-027), en `feature/fase-01-05-foto`, sin integrar a `main`. El cierre de la 1.5 y de la FASE 1 (R-028) solo documenta |
| Frontend | Sin empezar |
| Última entrega de código | FASE 1, entrega 1.5: foto del paciente (subir, ver y borrar en `patients/{patient}/photo`; JPG, PNG o WEBP de hasta 5 MB por el contenido, 413 `PAYLOAD_TOO_LARGE` si es más grande; disco privado con nombre al azar; descarga con `nosniff` y `no-store`; `has_photo` en lugar de `photo_path`; registro R-026). Revisada y corregida (R-027: disco propio `patient_photos` sin `serve`, `photo_path` fuera de `$fillable` y limitado a la carpeta del paciente, la imagen vuelta a codificar con GD sin EXIF ni contenido pegado, CSP en la descarga y aviso en el log si falla un borrado), validada por el usuario y cerrada (R-028). Con ella, la FASE 1 queda COMPLETADO ([cierre de la FASE 1](fases/fase-01-clientes-y-pacientes.md#cierre-de-la-fase-1)) |
| Tests del backend | Corrida de `php artisan test` del 2026-11-06 sobre las correcciones de la revisión de la 1.5 (`79f7e8e`; MySQL, otra vez en el cierre de la FASE 1): 616 casos con los *data providers* en verde; 6756 aserciones en SQLite (2 omitidos, solo de MySQL) y 6772 contra MySQL 8.4.3 (`syncvet_test_mysql`). `tests/Feature` (entrega 0.1), `tests/Feature/Tenancy` (0.2), `tests/Feature/Auth` con `TrustedProxiesTest` (0.3), `tests/Feature/Console`, `Seeders`, `Security` y `tests/Unit` (0.4), `tests/Feature/Access` y `Database` (0.5), `tests/Feature/Localization`, `HttpVerbsTest` y `VoseoTest` (1.1), `tests/Feature/Clients`, `PatientDeleteTest` y `DocumentBaseTest` (1.2), `tests/Feature/Patients` (1.3), y `tests/Feature/Catalog`, `SharedCatalogInsertTest`, `SpeciesSeederTest` y `CatalogNameTest` (1.4), y `PatientPhotoTest` (1.5). Cuándo correr cada base: `CLAUDE.md` §3, Entorno |
| Último checkpoint validado | Entrega 1.5 con las correcciones de la revisión (R-027), en `79f7e8e`: suite en verde en SQLite y MySQL sobre ese código, y las colecciones de la foto (dos veces seguidas sin recrear la base) y de pacientes con Newman. El usuario la validó el 2026-11-06 en Postman contra `syncvet`: foto 60 de 60 y pacientes 61 de 61, sin errores (R-028, [validación del usuario](fases/fase-01-clientes-y-pacientes.md#validación-del-usuario-r-028)). Antes, entrega 1.4 con las correcciones de la revisión (R-024), en `aadc1d9`: suite en verde en SQLite y MySQL sobre ese código, y las tres colecciones con Newman (especies dos veces seguidas sin recrear la base). El usuario la validó el 2026-11-06 con las tres colecciones en Postman contra `syncvet`: especies 93 de 93 (dos veces), pacientes 61 de 61 y clientes 48 de 48 (R-025, [validación del usuario](fases/fase-01-clientes-y-pacientes.md#validación-del-usuario-r-025)). Antes, entrega 1.3 en `837484a` (el handoff con su hash, en `be47879`), validada el 2026-11-06 con las colecciones de clientes y de pacientes (R-022, [validación del usuario](fases/fase-01-clientes-y-pacientes.md#validación-del-usuario-r-022)). Antes, entrega 1.2 en `544f5cd`: suite en verde en SQLite y MySQL; el usuario la validó el 2026-11-05 con la colección de Postman ([resultado de la 1.2](fases/fase-01-clientes-y-pacientes.md#entrega-12)). El "Errors 1" del Collection Runner quedó PENDIENTE DE VERIFICACIÓN: Newman no lo reproduce |

El estado actual siempre se confirma con `git log origin/main` y `php artisan test`.

## Estado del proyecto

SyncVet es la API de gestión de una clínica veterinaria (Paraguay), con el frontend en otro repositorio. El plan del MVP tiene 11
fases ([`roadmap/README.md`](roadmap/README.md)); la FASE 0 está completada (R-014) y la FASE 1 también (R-028), con las entregas
1.1 (R-017), 1.2 (R-018 y R-019), 1.3 (R-020 a R-022), 1.4 (R-023 a R-025) y 1.5 (R-026 a R-028) cerradas. Sigue la FASE 2, datos maestros.

**El sistema no está listo para producción.**

## Próximas tareas

| Orden | Tarea | Bloqueo |
|---|---|---|
| 1 | FASE 2: datos maestros ([`fases/fase-02-datos-maestros.md`](fases/fase-02-datos-maestros.md)). Empieza por el análisis de lo hecho (paso 1, [`prompts/1-analisis.md`](prompts/1-analisis.md)) y sigue con el diseño de la fase y sus preguntas de negocio (paso 2, [`prompts/2-diseno.md`](prompts/2-diseno.md)). Antes, si se quiere, integrar la FASE 1 a `main` (las ramas `docs/fase-01-diseno` → … → `feature/fase-01-05-foto`), como se hizo con la FASE 0 | Autorización del usuario para empezar la fase; el merge a `main`, también |
| 2 | Opcional: ver en el Collection Runner el detalle del "Errors 1" de la validación de la 1.2 (PENDIENTE DE VERIFICACIÓN; Newman no lo reproduce) | Ninguno |

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
