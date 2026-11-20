# Handoff de SyncVet

Documento principal para retomar el desarrollo desde cualquier computadora, con o sin el contexto de sesiones anteriores.

> **Sesión nueva de Claude Code:** ejecutar `/retomar` desde la raíz del repositorio, o leer este documento, el [`CLAUDE.md`](../../CLAUDE.md) de la raíz y el del proyecto que se toca ([`backend/CLAUDE.md`](../../backend/CLAUDE.md) o [`frontend/CLAUDE.md`](../../frontend/CLAUDE.md)), verificar el estado de
> Git y continuar desde [Próximas tareas](#próximas-tareas). [`README.md`](../README.md) indica dónde buscar cada tema.

Si este documento contradice al código, manda el código.

## Punto de continuidad

| Dato | Valor |
|---|---|
| Actualizado | 2026-11-20 (R-101: la entrega 4.2 del backend, cerrada) |
| Rama | `feature/back-4-2-cierre` (desde `9e7c478`, el cierre de la 4.1), con la 4.2 cerrada |
| Sin integrar a `main` | `feature/back-4-diseno`, `feature/back-4-1-consulta` y `feature/back-4-2-cierre`; se integran con la FASE 4 |
| Último checkpoint validado | El cierre de la 4.2 del backend (R-101), el 2026-11-20, sobre el código de `6c00790` ([resultado](fases/backend/fase-04-resultados.md#entrega-42)) |
| Tests del backend | Revisión de la 4.2 (R-101), sobre el código de `6c00790`: `composer test`, 1631 casos, 1613 en verde y 18 omitidos (de MySQL), 16085 aserciones, en una sola corrida; en la implementación, MySQL (los tests afectados) 60 de 60 y Newman `syncvet-fase-04-consultas`, 45 pedidos y 84 aserciones, sin fallas |
| Tests del frontend | Cierre de la F3 (R-098), sobre `3081c83`: `npm test` del frontend, 1835 tests en 89 archivos, en verde; Playwright completo, 46 de 46 en una sola corrida |
| Repositorio | Monorepo (R-057): `backend/`, `frontend/` y `docs/`; Claude Code, las skills y Newman, desde la raíz |
| Historial | Cada entrega, en el `…-resultados.md` de su fase y en su R del [registro](../01-arquitectura/decisiones/registro.md); este handoff hasta R-090, en [`historial-handoff.md`](historial-handoff.md) |

El estado actual siempre se confirma con `git log origin/main` y `php artisan test`.

## Estado del proyecto

* **Backend:** FASE 0 a FASE 3 completadas e integradas a `main`; la FASE 4 (historia clínica), EN DESARROLLO: la 4.1 (la consulta, R-100) y la 4.2 (cierre, enmiendas y anulación, R-101), cerradas.
* **Frontend:** F0 a F3 completadas e integradas a `main` (R-063, R-072, R-083 y R-098; la F3, con el merge `5f180f3`).
* El detalle, en el [roadmap](README.md) y en [`modulos.md`](modulos.md).

**El sistema no está listo para producción.**

## Próximas tareas

| Orden | Tarea | Bloqueo |
|---|---|---|
| 1 | En cada PC, los pasos de [`setup.md`](../04-desarrollo/setup.md#migración-al-monorepo-r-057) | Ninguno |
| 2 | **Siguiente.** El backend, FASE 4, entrega 4.3 (los servicios usados en la consulta: ítems de tipo `service` con el precio del catálogo, descuento y totales con BCMath; solo con la consulta abierta, por `lockEditable`), en una rama nueva desde el cierre de la 4.2 ([diseño](fases/backend/fase-04-historia-clinica.md), R-099). Con el presupuesto por entrega de R-100 | La autorización del usuario |
| 2b | Opcional: la deuda 29 (los bloqueos de la cola por sucursal, una entrega de soporte del backend) y la 19 (`typescript-eslint@8.73.0`), cuando el usuario lo pida ([deuda técnica](deuda-tecnica.md)) | Decisión del usuario |
| 3a | Prueba manual del usuario: una foto vertical de un celular en el detalle del paciente (la orientación la aplica el backend, R-027; desvío 11 de la 1.4) | Ninguno |
| 4 | Opcional, de la revisión de la 1.3a: mirar con ventana el selector nativo de la fecha de nacimiento (desvío 10) | Ninguno |
| 5 | Opcional: verificar el desvío 16 de la 1.1 (dos pedidos iguales al entrar a `/clientes?buscar=…` con `npm run dev`; probablemente el doble efecto del `StrictMode` de React en desarrollo) con `npm run build` y `npm run preview` | Ninguno |
| 7 | Opcional: ver en el Collection Runner el detalle del "Errors 1" de la validación de la 1.2 (PENDIENTE DE VERIFICACIÓN; Newman no lo reproduce) | Ninguno |

Ninguna entrega empieza sin autorización del usuario.

## Decisiones técnicas que hay que tener presentes

| Tema | Decisión |
|---|---|
| Arquitectura | Controller → Form Request → Service → Model → Resource → ApiResponse (`backend/CLAUDE.md` §4) |
| Estados | `string(20)` en la base y Enum en PHP; nunca columnas `ENUM` de MySQL |
| Dinero | `decimal(14,2)` con BCMath; importes en PYG redondeados a 0 decimales (R-004) |
| Zona horaria | `America/Asuncion` en la base (UTC-3 todo el año desde el 15/10/2024); la API envía UTC (`Z`). Fechas con hora con el cast `AppDateTime` y los filtros con `ApiDateTime` (deuda 13, R-042). La sesión de MySQL en `-03:00` (`DB_TIMEZONE`, deuda 16, R-049): las horas de negocio las pone PHP, nunca el reloj de MySQL. PHP con la base de zonas 2025a o posterior: en Laragon, la extensión `timezonedb` ([`development/setup.md`](../04-desarrollo/setup.md#zona-horaria-de-paraguay-base-de-zonas-de-php)); `AsuncionTimezoneTest` (R-043) |
| Separación por clínica | Una base compartida con `clinic_id`, scope global y fallo cerrado (D1, registros R-002 y R-004). Implementada en la entrega 0.2 (R-007); reglas en `backend/CLAUDE.md` §3 |
| Autenticación | Login con email (en minúsculas y solo ASCII); tokens de 12 horas; 5 intentos fallidos por minuto por usuario e IP, o por email si no existe (R-004, R-008); clínicas creadas con `clinic:create` (R-004). En producción, caché en Redis para el límite (deuda 6) |
| Contraseñas | 12 caracteres con mayúsculas, minúsculas, números y símbolos, y no filtrada (Have I Been Pwned); `App\Support\UserPassword` (R-009). Ninguna contraseña llega a una traza ni al log (R-010). Datos de ejemplo con documentos ficticios (R-011, R-013). En Laragon, `curl.cainfo` correcto ([`development/setup.md`](../04-desarrollo/setup.md)) |
| Facturación | SIFEN (Paraguay), en las FASES 8 y 9 |
