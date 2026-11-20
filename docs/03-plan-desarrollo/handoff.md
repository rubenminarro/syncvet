# Handoff de SyncVet

Documento principal para retomar el desarrollo desde cualquier computadora, con o sin el contexto de sesiones anteriores.

> **Sesión nueva de Claude Code:** ejecutar `/retomar` desde la raíz del repositorio, o leer este documento, el [`CLAUDE.md`](../../CLAUDE.md) de la raíz y el del proyecto que se toca ([`backend/CLAUDE.md`](../../backend/CLAUDE.md) o [`frontend/CLAUDE.md`](../../frontend/CLAUDE.md)), verificar el estado de
> Git y continuar desde [Próximas tareas](#próximas-tareas). [`README.md`](../README.md) indica dónde buscar cada tema.

Si este documento contradice al código, manda el código.

## Punto de continuidad

| Dato | Valor |
|---|---|
| Actualizado | 2026-11-20 (R-094: cierre de la 3.5 del frontend, los estados del turno) |
| Rama | `feature/front-3-5-estados`, desde el cierre de la 3.4 (`d067957`), con la 3.5 cerrada |
| Sin integrar a `main` | Las ramas de la F3 del frontend (3.1a a 3.5) y las del backend front-03 y demo-02: se integran al cerrar la F3 |
| Último checkpoint validado | La 3.5 del frontend (R-094), el 2026-11-20 ([resultado](fases/frontend/f3-resultados.md#entrega-35)) |
| Tests del backend | Cierre de demo-02 (R-090), sobre `93e6fcd`: `composer test`, 1496 casos, 16 omitidos (solo de MySQL), 14891 aserciones, en verde |
| Tests del frontend | Cierre de la 3.5 (R-094), sobre `d0f3ad2`: `npm test` del frontend, 1721 tests en 82 archivos, en verde; Playwright, 41 casos en 20 archivos |
| Repositorio | Monorepo (R-057): `backend/`, `frontend/` y `docs/`; Claude Code, las skills y Newman, desde la raíz |
| Historial | Cada entrega, en el `…-resultados.md` de su fase y en su R del [registro](../01-arquitectura/decisiones/registro.md); este handoff hasta R-090, en [`historial-handoff.md`](historial-handoff.md) |

El estado actual siempre se confirma con `git log origin/main` y `php artisan test`.

## Estado del proyecto

* **Backend:** FASE 0 a FASE 3 completadas e integradas a `main`; después de la F3 del frontend, la FASE 4 (historia clínica).
* **Frontend:** F0, F1 y F2 completadas e integradas a `main`; la F3 (agenda y turnos), en curso.
* El detalle, en el [roadmap](README.md) y en [`modulos.md`](modulos.md).

**El sistema no está listo para producción.**

## Próximas tareas

| Orden | Tarea | Bloqueo |
|---|---|---|
| 1 | En cada PC, los pasos de [`setup.md`](../04-desarrollo/setup.md#migración-al-monorepo-r-057) | Ninguno |
| 2 | **Siguiente.** La 3.6 del frontend, las series ([diseño](fases/frontend/f3-agenda.md#36-las-series)), en una rama nueva desde el cierre de la 3.5; los datos de la demo, con `php artisan demo:agenda` ([`setup.md`](../04-desarrollo/setup.md#agenda-de-la-demo-demoagenda)). Al cerrar la F3, las deudas 27 y 28 ([deuda técnica](deuda-tecnica.md)) | La autorización del usuario (aprobada en el diseño, R-085) |
| 3a | Prueba manual del usuario: una foto vertical de un celular en el detalle del paciente (la orientación la aplica el backend, R-027; desvío 11 de la 1.4) | Ninguno |
| 4 | Opcional, de la revisión de la 1.3a: mirar con ventana el selector nativo de la fecha de nacimiento (desvío 10) | Ninguno |
| 5 | Opcional: verificar el desvío 16 de la 1.1 (dos pedidos iguales al entrar a `/clientes?buscar=…` con `npm run dev`; probablemente el doble efecto del `StrictMode` de React en desarrollo) con `npm run build` y `npm run preview` | Ninguno |
| 6 | Después de la F3 del frontend, el análisis de la FASE 4, historia clínica ([documento de la fase](fases/backend/fase-04-historia-clinica.md), con la relación entre "atendido" y la consulta pendiente de la FASE 3) (D2, R-058) | Las fases F0 a F3 del frontend |
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
