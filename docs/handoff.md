# Handoff de SyncVet

Documento principal para retomar el desarrollo desde cualquier computadora, con o sin el contexto de sesiones anteriores.

> **Sesión nueva de Claude Code:** ejecutar `/retomar`, o leer este documento y [`CLAUDE.md`](../CLAUDE.md), verificar el estado de
> Git y continuar desde [Próximas tareas](#próximas-tareas). [`README.md`](README.md) indica dónde buscar cada tema.

Si este documento contradice al código, manda el código.

## Punto de continuidad

| Dato | Valor |
|---|---|
| Actualizado | 2026-11-07 |
| Backend | Repositorio [`rubenminarro/syncvet`](https://github.com/rubenminarro/syncvet). Proyecto Laravel 13 sobre el paquete base. La FASE 0 se integró a `main` con el merge `63b3eff` (ramas `feature/fase-00-paquete-base` → … → `feature/fase-00-05-acceso`). La FASE 1 está completada (R-028) e integrada a `main` con el merge `7b35ea2` (ramas `docs/fase-01-diseno` → `feature/fase-01-01-traducciones` → … → `feature/fase-01-05-foto`). La FASE 2 está en desarrollo, sin integrar a `main` (el merge se hace al final de la fase): el diseño aprobado (R-029) en `docs/fase-02-diseno`; encima, `feature/fase-02-01-sucursales` con la entrega 2.1 cerrada (R-030 y R-031); encima, `feature/fase-02-02a-usuarios` para la entrega 2.2a, todavía sin código |
| Último commit de código | El de la entrega 2.1 en `feature/fase-02-01-sucursales`, hijo de `405ea96`: implementación, correcciones de la revisión y cierre en un solo commit (un commit no puede llevar su propio hash; se informa al subirlo). Antes, `79f7e8e` (correcciones de la 1.5, R-027), integrado a `main` con `7b35ea2` |
| Frontend | Sin empezar |
| Última entrega de código | FASE 2, entrega 2.1: sucursales y depósitos (alta, edición e inactivación, sin baja; sucursal y depósito por defecto; `establishment` fijo con facturas; códigos y nombres únicos con la clínica bloqueada; 7 rutas nuevas, 26 → 33; `branches.index` al recepcionista y al veterinario; R-030). Revisada en una sesión aparte y corregida (R-031: depósito en una sucursal inactiva → 422 en `branch`, y tests de la búsqueda por código, de la sucursal nueva sin usuarios, del verbo `crear` y del orden clínica → sucursal → usuario), validada por el usuario y cerrada (R-031; [resultado de la 2.1](fases/fase-02-datos-maestros.md#entrega-21)). Antes, FASE 1, entrega 1.5: foto del paciente (subir, ver y borrar en `patients/{patient}/photo`; JPG, PNG o WEBP de hasta 5 MB por el contenido, 413 `PAYLOAD_TOO_LARGE` si es más grande; disco privado con nombre al azar; descarga con `nosniff` y `no-store`; `has_photo` en lugar de `photo_path`; registro R-026). Revisada y corregida (R-027: disco propio `patient_photos` sin `serve`, `photo_path` fuera de `$fillable` y limitado a la carpeta del paciente, la imagen vuelta a codificar con GD sin EXIF ni contenido pegado, CSP en la descarga y aviso en el log si falla un borrado), validada por el usuario y cerrada (R-028). Con ella, la FASE 1 queda COMPLETADO ([cierre de la FASE 1](fases/fase-01-clientes-y-pacientes.md#cierre-de-la-fase-1)) |
| Tests del backend | Corrida de `php artisan test` del 2026-11-07 sobre las correcciones de la revisión de la 2.1 (el código del commit de la 2.1): 664 casos con los *data providers* en verde; 7525 aserciones en SQLite (2 omitidos, solo de MySQL) y 7541 contra MySQL 8.4.3 (`syncvet_test_mysql`). `tests/Feature` (entrega 0.1), `tests/Feature/Tenancy` (0.2), `tests/Feature/Auth` con `TrustedProxiesTest` (0.3), `tests/Feature/Console`, `Seeders`, `Security` y `tests/Unit` (0.4), `tests/Feature/Access` y `Database` (0.5), `tests/Feature/Localization`, `HttpVerbsTest` y `VoseoTest` (1.1), `tests/Feature/Clients`, `PatientDeleteTest` y `DocumentBaseTest` (1.2), `tests/Feature/Patients` (1.3), y `tests/Feature/Catalog`, `SharedCatalogInsertTest`, `SpeciesSeederTest` y `CatalogNameTest` (1.4), y `PatientPhotoTest` (1.5), y `tests/Feature/Branches` (2.1). Cuándo correr cada base: `CLAUDE.md` §3, Entorno |
| Último checkpoint validado | Entrega 2.1 con las correcciones de la revisión (R-031): suite en verde en SQLite y MySQL sobre ese código. El usuario la validó el 2026-11-07 en Postman (Collection Runner, una iteración) con la colección de sucursales: 107 de 107, sin errores ([validación del usuario](fases/fase-02-datos-maestros.md#validación-del-usuario-r-031)). Antes, entrega 1.5 con las correcciones de la revisión (R-027), en `79f7e8e`: suite en verde en SQLite y MySQL sobre ese código, y las colecciones de la foto (dos veces seguidas sin recrear la base) y de pacientes con Newman. El usuario la validó el 2026-11-06 en Postman contra `syncvet`: foto 60 de 60 y pacientes 61 de 61, sin errores (R-028, [validación del usuario](fases/fase-01-clientes-y-pacientes.md#validación-del-usuario-r-028)). Antes, entrega 1.4 con las correcciones de la revisión (R-024), en `aadc1d9`: suite en verde en SQLite y MySQL sobre ese código, y las tres colecciones con Newman (especies dos veces seguidas sin recrear la base). El usuario la validó el 2026-11-06 con las tres colecciones en Postman contra `syncvet`: especies 93 de 93 (dos veces), pacientes 61 de 61 y clientes 48 de 48 (R-025, [validación del usuario](fases/fase-01-clientes-y-pacientes.md#validación-del-usuario-r-025)). Antes, entrega 1.3 en `837484a` (el handoff con su hash, en `be47879`), validada el 2026-11-06 con las colecciones de clientes y de pacientes (R-022, [validación del usuario](fases/fase-01-clientes-y-pacientes.md#validación-del-usuario-r-022)). Antes, entrega 1.2 en `544f5cd`: suite en verde en SQLite y MySQL; el usuario la validó el 2026-11-05 con la colección de Postman ([resultado de la 1.2](fases/fase-01-clientes-y-pacientes.md#entrega-12)). El "Errors 1" del Collection Runner quedó PENDIENTE DE VERIFICACIÓN: Newman no lo reproduce |

El estado actual siempre se confirma con `git log origin/main` y `php artisan test`.

## Estado del proyecto

SyncVet es la API de gestión de una clínica veterinaria (Paraguay), con el frontend en otro repositorio. El plan del MVP tiene 11
fases ([`roadmap/README.md`](roadmap/README.md)); la FASE 0 está completada (R-014) y la FASE 1 también (R-028), con las entregas
1.1 (R-017), 1.2 (R-018 y R-019), 1.3 (R-020 a R-022), 1.4 (R-023 a R-025) y 1.5 (R-026 a R-028) cerradas. La FASE 2, datos
maestros, está en desarrollo: diseño aprobado (R-029) y entrega 2.1, sucursales y depósitos, cerrada (R-030 y R-031).

**El sistema no está listo para producción.**

## Próximas tareas

| Orden | Tarea | Bloqueo |
|---|---|---|
| 1 | FASE 2, entrega 2.2a: usuarios (alta, edición, detalle y listado; roles y sucursales; último administrador; [diseño](fases/fase-02-datos-maestros.md#22a-usuarios-abm-roles-y-sucursales), aprobado en R-029). Se implementa en `feature/fase-02-02a-usuarios`, creada desde `feature/fase-02-01-sucursales`, con el prompt de implementación de la fase (paso 4). Revisa la deuda 2 en `User` | Autorización del usuario para empezar la implementación |
| — | Merge de la FASE 2 a `main` | Al final de la fase, con autorización del usuario |
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
