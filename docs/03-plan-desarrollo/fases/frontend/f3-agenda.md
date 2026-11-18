# F3 — Agenda y turnos (frontend)

Etapa: Atención. Estado: **EN CURSO**. Diseño propuesto el 2026-11-17 en `docs/front-f3-diseno` (desde `main` en `2651337`, el
merge de la F2) y **aprobado por el usuario el 2026-11-17 (R-085)**, con las 15 [preguntas del diseño](#preguntas-del-diseño)
respondidas (todas con la recomendación; la 1, la 9, la 13 y la 14, con precisiones) y las diferencias D1 a D5 corregidas en los
documentos. Ninguna entrega empieza sin su autorización. El estado vigente de cada fase está en
[`docs/03-plan-desarrollo/README.md`](../../README.md).

## Objetivo

Las pantallas de la agenda, sobre la API ya cerrada de la [FASE 3 del backend](../backend/fase-03-agenda-y-turnos.md): las agendas
con su horario semanal y sus bloqueos (el administrador), la agenda del día y de la semana y la lista de turnos, el alta, la edición y
la reprogramación de un turno (único o en serie, con el sobreturno), sus estados (confirmar, fichar, atendido, ausente y deshacerlo,
cancelar con motivo y alcance) y la cola del día. Se usan tal cual las convenciones de la F0 a la F2
([`frontend/CLAUDE.md`](../../../../frontend/CLAUDE.md) §5): listados con la búsqueda en la URL, formularios con las reglas de la API
y un solo envío, `ConfirmDialog` y `dialogError`, la `key` en los detalles, `branchKey` para lo que depende de la sucursal, el trato
por `error_code`, las fechas en `America/Asuncion` con `@date-fns/tz` y el voseo.

## Lo que da la API (resumen del análisis)

Las reglas completas están en [`backend/CLAUDE.md`](../../../../backend/CLAUDE.md) §6.10; acá, solo lo que decide la interfaz. Rutas
leídas de `php artisan route:list` (las 20 de la FASE 3, sin cambios desde su cierre) y permisos leídos de
`tests/Fixtures/clinic-roles.json` (el administrador los tiene todos).

| Recurso | Endpoints | Permisos (recepcionista / veterinario) | Notas para el frontend |
|---|---|---|---|
| Agendas | `GET schedules` (`search`, `branch_id`, `veterinarian_id`, `service_id`, `include_inactive`; por nombre), `POST schedules`, `GET/PATCH/DELETE schedules/{schedule}` | `schedules.index` y `.show`: los tres. `.store`, `.update`, `.destroy`: solo el administrador | El listado (`ScheduleResource`): `name`, `branch` (`id`, `name`), `veterinarian` (`id`, `code`, `name`, o `null`), `service` (o `null`), `slot_minutes`, `color` (`#RRGGBB` o `null`) e `is_active`; **sin** el horario. El detalle suma `hours` (`{id, weekday, starts_at, ends_at}`, `"08:00"`, `weekday` 1 = lunes) y `upcoming_blocks` (hasta 20). Baja física solo sin turnos (`SCHEDULE_IN_USE`) |
| Bloqueos | `GET/POST schedules/{schedule}/blocks` (`from`, `to`, `search`; por defecto, los que terminan desde hoy), `DELETE schedule-blocks/{scheduleBlock}` | `schedule-blocks.index`: los tres. `.store`, `.destroy`: solo el administrador | `starts_at`, `ends_at` (instantes, múltiplos de 5 minutos, hasta 366 días) y `reason`. No se editan. El alta responde `affected_appointments`: los turnos que quedaron adentro |
| Disponibilidad | `GET schedules/{schedule}/availability` (`from`, `to`: fechas solas, hasta 7 días; `service_id`; `duration_minutes`) | `schedules.availability`: los tres | `{schedule_id, duration_minutes, days: [{date, slots: [{starts_at, ends_at}]}]}`: los horarios que el alta aceptaría (no ofrece los que ya empezaron; el alta sí los acepta hoy). Solo lectura |
| Turnos | `GET appointments`, `POST appointments` (también la serie, con `recurrence`), `GET/PATCH appointments/{appointment}` (sin baja: `DELETE` → 405) | `appointments.index` y `.show`: los tres. `.store` y `.update`: el administrador y la recepción | Ver abajo |
| Estados | `POST appointments/{appointment}/` + `confirm`, `check-in`, `attend`, `no-show`, `undo-no-show`, `cancel` | `confirm`, `cancel`, `no-show` (también deshacerlo): administrador y recepción. `check-in`: los tres. `attend`: administrador y veterinario. `overbook`: administrador y recepción | Responden 200 con el detalle. `cancel` pide `cancellation_reason` (3 a 500) y `scope` (`single`, `following`, `series`), con `meta.cancelled_count` |
| Cola del día | `GET appointments/queue` (`date`, `all`, `veterinarian_id`, `schedule_id`) | `appointments.queue`: los tres | De la **sucursal actual** (sin ella, 422 `APPOINTMENT_QUEUE_NO_BRANCH`), sin los cancelados, sin paginar, hasta 200 (`meta.truncated`). Por defecto, a quien tiene ficha, sus turnos y los de las agendas sin veterinario (`meta.includes_unassigned`) |

**El turno** (`AppointmentResource` en el listado; `ShowAppointmentResource` en el detalle y en las respuestas de las acciones):
`schedule` (`id`, `name`), `branch` (`id`, `name`), `veterinarian` (`id`, `code`, `name`, o `null`), `service` (`id`, `code`, `name`, o
`null`), `patient` (`id`, `code`, `name`, o `null`), `client` (`id`, `display_name`, o `null`), `contact_name`, `contact_phone`,
`starts_at` y `ends_at` (UTC, `Z`), `status` y `source` (`{value, label}`), `is_overbooked`, `series_id` (o `null`) y `reason`. El
detalle suma `notes`, `created_by`, `confirmed_at`, `checked_in_at`, `attended_at`, `no_show_at`, `cancelled_at`, `cancelled_by`,
`cancellation_reason` y `created_at`. La cola suma la especie y la raza del paciente y `checked_in_at`.

**El listado** (`IndexAppointmentRequest`): por `starts_at` e id; `from` y `to` (una fecha sola es el día entero de Asunción, o una
fecha con hora); sin ninguno, hoy; con uno solo, ese día; **hasta 31 días**. Filtros: `schedule_id`, `branch_id`, `veterinarian_id`,
`service_id`, `patient_id`, `client_id`, `status` (uno o varios: `status[]=confirmed&status[]=checked_in`), `series_id` y `search`
(paciente: nombre y código; cliente: nombre, apellido, razón social y documento; el contacto). Trae **todos los estados** si no se
filtra, también los cancelados. Paginado con `ListFilters`: `per_page` de 1 a **100**.

**El alta y la edición** (`AppointmentMessages`): `schedule_id` (obligatorio), `patient_id`, `client_id`, o `contact_name` (2 a 150,
al menos una letra, sin caracteres de control ni `<` `>`) con `contact_phone` (la regla de clientes), uno de los tres; `service_id`
(opcional: el de la agenda); `starts_at` (obligatorio) y `ends_at` (opcional: la duración del servicio o `slot_minutes`; al editar,
conserva la duración), con zona o sin ella, en múltiplos de 5 minutos sin segundos; `source` (`reception`, `phone` o `whatsapp`);
`reason` (hasta 255), `notes` (hasta 1000), `overbook`. `branch_id`, `veterinarian_id`, `status`, `series_id` e `is_overbooked` →
422 (`prohibited`). La edición es parcial y **solo mueve si cambian** la agenda, `starts_at` o `ends_at` (mandar los mismos valores no
revisa otra vez el horario: un turno de una agenda inactiva se sigue editando).

**Las reglas que el navegador puede repetir y las que no.** Repite la forma (la grilla de 5 minutos, el fin después del inicio, el
mismo día, hasta 720 minutos, un día que no pasó, quién, el contacto completo, los largos, la serie de 2 a 52 turnos y hasta un año).
**No puede saber** el horario semanal al momento de guardar, los bloqueos ni la superposición (otra PC pudo dar el turno hace un
segundo): los decide la API, con las filas bloqueadas.

`error_code` de la fase (todos van por el trato `business` de la tabla de la F0, con lo que suma cada entrega):

| `error_code` | HTTP | Mensaje de la API | Trato propio en la F3 |
|---|---|---|---|
| `APPOINTMENT_OVERLAP` | 409 | "El horario ya está ocupado: elegí otro o dalo como sobreturno." | Al campo del horario; se vuelven a pedir la disponibilidad y la agenda; con `appointments.overbook`, la acción "Dar como sobreturno" ([pregunta 8](#preguntas-del-diseño)) |
| `APPOINTMENT_OUTSIDE_HOURS` | 422 | "El turno está fuera del horario de la agenda." | Al campo del horario; se vuelve a pedir la disponibilidad (el horario pudo cambiar) |
| `APPOINTMENT_IN_BLOCK` | 422 | "La agenda está bloqueada en ese horario." | Igual que el anterior |
| `APPOINTMENT_SERIES_CONFLICT` | 409 | "Hay fechas de la serie que no se pueden dar: revisá la lista." | La lista de `errors.conflicts` (cada fecha en Asunción, con su `message`) en una alerta del formulario; no se crea ninguno |
| `APPOINTMENT_NOT_EDITABLE` | 422 | "Solo se modifican los turnos a confirmar o confirmados." | Alerta arriba; se vuelve a pedir el turno (otro lo fichó o lo canceló) |
| `APPOINTMENT_INVALID_TRANSITION` | 422 | "El turno está «…» y no admite ese cambio." | En el diálogo de la acción, con "Cerrar"; se vuelve a pedir el turno |
| `APPOINTMENT_PATIENT_REQUIRED` | 422 | "Para fichar el turno, cargá el paciente." | En el diálogo, con "Cerrar" y el enlace a editar el turno (el navegador ya lo avisa antes) |
| `APPOINTMENT_CHECK_IN_NOT_TODAY` | 422 | "Solo se fichan los turnos del día." | En el diálogo, con "Cerrar" (el navegador ya lo avisa antes) |
| `APPOINTMENT_NO_SHOW_TOO_EARLY` | 422 | "El turno todavía no empezó: no se puede marcar como ausente." | Igual |
| `APPOINTMENT_NO_SHOW_NOT_UNDOABLE` | 422 | "El ausente solo se deshace el mismo día del turno." | Igual |
| `APPOINTMENT_NOT_YOURS` | 403 | "Ese turno no es de tu agenda." | En el diálogo, con "Cerrar"; el navegador ya oculta la acción en turnos ajenos ([pregunta 14](#preguntas-del-diseño)) |
| `APPOINTMENT_QUEUE_NO_BRANCH` | 422 | "Elegí tu sucursal actual para ver la cola del día." | En la cola, el mensaje en lugar de la tabla (el layout ya pide elegir la sucursal) |
| `SCHEDULE_IN_USE` | 409 | "La agenda tiene turnos y no se puede eliminar: inactivala." | En el diálogo de baja, el mensaje y "Inactivar" (como `SERVICE_IN_USE`) |
| `SCHEDULE_NOT_EDITABLE` | 422 | "La agenda ya tiene turnos y no puede cambiar de sucursal ni de veterinario: creá otra agenda e inactivá esta." | Alerta arriba del formulario de la agenda |
| `CONFLICT` | 409 | (agendas y turnos: la fila cambió en 3 intentos, o una espera vencida) | El de la tabla de la F0: "Alguien cambió estos datos…" y se vuelven a pedir |

Lo que **no tiene `error_code` propio** y llega como 422 `VALIDATION_ERROR` en su campo (va con `applyValidationErrors`): el
veterinario de la agenda inactivo o una agenda inactiva (`schedule_id`: "El veterinario de la agenda está inactivo.", "La agenda no
existe o está inactiva."), el servicio inactivo (`service_id`), el paciente fallecido o de otro dueño (`patient_id`), un día que ya
pasó (`starts_at`: "No podés dar un turno en un día que ya pasó."), cruzar la medianoche o pasar de 720 minutos (`ends_at`) y fichar
un paciente fallecido (`patient_id`, en el diálogo).

## Diferencias entre los documentos y el código

Manda el código. Ninguna cambia el contrato. Corregidas en los documentos con la aprobación del diseño (R-085): D1 y D5 en el
documento de la FASE 3 del backend (como precisiones, sin reescribir lo aprobado) y D2 a D4 en `frontend/CLAUDE.md` §5 y §6.

| # | Documento | Dice | El código | Propuesta |
|---|---|---|---|---|
| D1 | [`fase-03-agenda-y-turnos.md`](../backend/fase-03-agenda-y-turnos.md), Decisiones comunes y Orden de bloqueo | El orden de la fase es "clínica → veterinario → servicio → agenda → paciente → turno" | `AppointmentService` toma también el **cliente** (`sharedLock`) después del paciente; `backend/CLAUDE.md` §3 ya lo dice ("… → paciente → cliente → turno", desde R-051) | Nada que cambiar en el código: el documento de la fase es el diseño aprobado y no se reescribe; `backend/CLAUDE.md` §3 es la fuente vigente. Sin efecto en el frontend |
| D2 | El pedido de este diseño y la tabla de errores del frontend (`APPOINTMENT_*` como códigos de negocio) | "Veterinario inactivo" como un error propio, al lado de "turno ya tomado" y "fuera de horario" | El veterinario (o la agenda) inactivo es un 422 `VALIDATION_ERROR` en `schedule_id`, sin `error_code` propio; el servicio inactivo, en `service_id`; el día pasado, en `starts_at` | El formulario los recibe por `applyValidationErrors` en su campo, no por la tabla de códigos de negocio |
| D3 | [`frontend/CLAUDE.md`](../../../../frontend/CLAUDE.md) §5, claves de las consultas | Los turnos son un ejemplo de consulta "cuyos datos dependen de la sucursal actual" | Solo la cola del día depende de la sucursal actual. `GET appointments` no la usa: trae toda la clínica salvo `branch_id` | En la F3, la agenda manda `branch_id` (la sucursal que muestra) y la clave la lleva con `branchKey` ([pregunta 2](#preguntas-del-diseño)); el texto de §5 se precisa en la 3.2 |
| D4 | [`frontend/CLAUDE.md`](../../../../frontend/CLAUDE.md) §5, la cola del día | El ejemplo de clave es `['branch', 10, 'appointments', 'queue', { date }]` | La cola también filtra por `all`, `veterinarian_id` y `schedule_id` | La clave suma esos filtros (abajo) |
| D5 | [`fase-03-agenda-y-turnos.md`](../backend/fase-03-agenda-y-turnos.md), Permisos por rol | `appointments.check-in` para el veterinario, sin restricción ("Fichar, cualquiera", pregunta 18) | Desde la 3.2b (R-053), el veterinario sin `appointments.update` ficha y marca atendido solo los turnos de su ficha y de agendas sin veterinario (403 `APPOINTMENT_NOT_YOURS`) | El documento ya lo anota en la respuesta de la pregunta 18; el frontend sigue al código ([pregunta 14](#preguntas-del-diseño)) |

## Hallazgos del análisis

| # | Hallazgo | Propuesta |
|---|---|---|
| H1 | **Volumen de la semana.** Una sucursal con 4 agendas, de 8 a 16 con turnos de 30 minutos, 6 días, da hasta 384 turnos por semana (más los cancelados); `GET appointments` pagina de a 100 como máximo | [Pregunta 1](#preguntas-del-diseño): la entrega de soporte **front-03** (el listado de turnos acepta `per_page` hasta 500, solo ese listado) o el frontend pide todas las páginas |
| H2 | `GET appointments` filtra por **un** veterinario o **una** agenda, y `veterinarian_id` deja afuera las agendas sin veterinario: la vista por defecto del veterinario (sus turnos y los de las agendas sin veterinario, como la cola) no sale de un solo filtro | La agenda pide la sucursal y el rango, y filtra la agenda, el veterinario y el estado **en el navegador**: cambiar un filtro no hace otro pedido. La lista (`/turnos`) usa los filtros de la API, con su paginación |
| H3 | `ScheduleResource` (el listado) no trae el horario semanal; está solo en el detalle | La grilla pide el detalle de cada agenda que muestra (`['schedules', 'detail', id]`, `staleTime` de 5 minutos), para sombrear las horas sin atención y armar el rango de horas del día. Son pocas por sucursal: sin cambio en el backend |
| H4 | `upcoming_blocks` trae hasta 20 y solo los próximos | La grilla pide los bloqueos del rango de cada agenda (`GET schedules/{id}/blocks?from&to`) |
| H5 | El cliente de la API (`src/lib/api/client.ts`) no arma listas en la URL (`QueryValue` no admite arreglos) y el filtro `status` de turnos es una lista | En la 3.3, `buildUrl` suma las listas como `status[]=a&status[]=b`, con sus tests; sin cambiar las demás llamadas |
| H6 | El **`DemoSeeder` no crea agendas, servicios ni turnos** | [Pregunta 13](#preguntas-del-diseño): Playwright los crea por la API en un ayudante de `e2e/support` (sin cambios en el backend) |
| H7 | La disponibilidad no ofrece un horario de hoy que ya empezó, pero el alta lo acepta (pregunta 16 del backend: el que llegó sin turno) | El selector de horario ofrece "Otra hora" escrita a mano ([pregunta 7](#preguntas-del-diseño)) |
| H8 | `APPOINTMENT_SERIES_CONFLICT` trae `errors.conflicts` (una lista), no claves de campos: `applyValidationErrors` lo mandaría entero a la alerta como texto | Un trato propio que muestra la lista con la fecha en Asunción y el `message` de cada una |
| H9 | Paraguay es UTC-3 todo el año desde el 15/10/2024, pero las fechas anteriores tenían horario de verano (el 01/10/2023 tuvo 23 horas y el 23/03/2024, 25: el atraso fue a las 00:00 del 24 y repitió las 23:00 del 23; corregido en la revisión de la 3.2, desvío 5) | Toda cuenta de días con `TZDate` (`addDays`, `startOfDay` en la zona), nunca sumando 24 horas ni con un `-03:00` fijo; tests con esos dos días (como la 2.6) |
| H10 | Un turno no cruza la medianoche (`ends_at` del día siguiente → 422 en `ends_at`, también a las 00:00) y los tramos van hasta las 23:55 | La grilla nunca dibuja un turno partido; Zod rechaza el fin en otro día con el mensaje de la API |
| H11 | `ShowAppointmentResource` no trae el teléfono del cliente (solo el del contacto) | Sin cambio: el detalle enlaza al cliente (`/clientes/$id`), que lo muestra |
| H12 | El rol `veterinario` puede listar todos los turnos (pregunta 25 del backend: la cola es una vista por defecto, no una restricción) | [Pregunta 3](#preguntas-del-diseño): la vista por defecto del veterinario, como la cola |

## Decisiones comunes de la fase

Lo que sigue es la propuesta; donde depende de una pregunta, lo dice.

* **URL en castellano** (como lo anticipa [`frontend/CLAUDE.md`](../../../../frontend/CLAUDE.md) §4: `/agenda` y `/turnos`):

  | URL | Entrega |
  |---|---|
  | `/configuracion/agendas`, `/nueva`, `/$id`, `/$id/editar` (con el horario) | 3.1a |
  | Los bloqueos, dentro del detalle de la agenda (diálogos, como los depósitos) | 3.1b |
  | `/agenda?vista=dia&fecha=2026-11-17&agenda=&veterinario=&cancelados=` | 3.2 |
  | `/agenda?vista=semana&…` | 3.3 |
  | `/turnos?desde=&hasta=&agenda=&veterinario=&estados=&serie=&buscar=&pagina=` (la lista; `serie`, desde la 3.6) | 3.3 |
  | `/turnos/nuevo?agenda=&inicio=`, `/turnos/$id`, `/turnos/$id/editar` | 3.4 |
  | `/agenda/cola?fecha=&todos=` | 3.7 |

  `fecha`, `inicio`, `desde` y `hasta` se suman a `TEXT_SEARCH_PARAMS` (se leen como texto) y `validateSearch` descarta lo que no tenga
  su forma (`2026-11-17`; `inicio`, `2026-11-17T09:30`, la hora de pared de Asunción, como la auditoría). Ningún parámetro reutiliza el
  nombre de otro listado con otros valores: `estado` ya existe en veterinarios y servicios (`activos|inactivos|todos`), así que el de
  los turnos es `estados` (una lista separada por comas).
* **Menú** ([pregunta 2](#preguntas-del-diseño) para la sucursal): "Agenda" (`appointments.index`, los tres roles), después de
  "Pacientes", activo en `/agenda…` y `/turnos…`; "Agendas" es una tarjeta de `/configuracion`, solo para el administrador. Las
  entregas que tocan el menú corren `e2e/permisos.spec.ts` (R-084).
* **La sucursal.** La agenda, la lista y la cola muestran la **sucursal actual** del selector de la barra
  ([pregunta 2](#preguntas-del-diseño)); sin sucursal actual, el aviso del layout. Cambiarla en la barra invalida estas consultas
  (`invalidateBranchQueries`, que ya existe).
* **Claves de las consultas** (`api.ts` de cada módulo):

  | Clave | Qué |
  |---|---|
  | `['schedules', 'list', { search, branch_id, veterinarian_id, include_inactive, page }]` | `GET schedules` (la pantalla del administrador) |
  | `['schedules', 'options', { branch_id }]` | `GET schedules?branch_id=&per_page=100` (activas), para los filtros y el formulario del turno |
  | `['schedules', 'detail', id]` | `GET schedules/{id}` (con el horario), `staleTime` de 5 minutos |
  | `['schedules', id, 'blocks', { from, to }]` | `GET schedules/{id}/blocks` |
  | `['schedules', id, 'availability', { from, to, service_id, duration_minutes }]` | `GET schedules/{id}/availability`, `staleTime` de 0 |
  | `branchKey(branchId, 'appointments', 'range', { from, to })` | La agenda: `GET appointments?branch_id=&from=&to=&per_page=…` (día o semana) |
  | `branchKey(branchId, 'appointments', 'list', { from, to, schedule_id, veterinarian_id, status, search, page })` | La lista |
  | `branchKey(branchId, 'appointments', 'queue', { date, all, veterinarian_id, schedule_id })` | La cola del día (D4) |
  | `['appointments', 'detail', id]` | `GET appointments/{id}` (un turno se abre desde cualquier sucursal: no lleva `branchKey`) |

  Invalidaciones (nunca toda la caché): un alta, una edición o una acción de un turno → `['branch', *, 'appointments']` (las tres de la
  sucursal, `refetchType: 'active'`), su detalle (con la respuesta del 200, `setQueryData`) y la disponibilidad de la agenda de origen
  y la de destino; una agenda → `['schedules']`; un bloqueo → sus bloqueos, el detalle de la agenda y la disponibilidad.
* **Actualización** ([pregunta 9](#preguntas-del-diseño)): `staleTime` de 30 s en la agenda y la cola; al volver a la pestaña (ya lo
  hace Query) y cada 60 s mientras la pestaña está visible (`refetchInterval`, sin correr en segundo plano), en pausa mientras hay un
  formulario o un diálogo abierto (pregunta 9). Una
  respuesta vieja no pisa la nueva (`placeholderData: keepPreviousData` al cambiar de día).
* **Fechas** (H9, H10). Todo en `America/Asuncion` con `@date-fns/tz`, nunca con la zona de la PC: un módulo puro
  `features/appointments/dates.ts`, con sus tests corridos en UTC, como `features/audit/dates.ts`:
  * **El día** es una fecha `yyyy-MM-dd` de Asunción; "hoy", `todayInAppZone()`. La semana empieza el **lunes** (`weekday` 1 de la API).
    Se suma de a días con `addDays` sobre un `TZDate` (la hora de pared se conserva), nunca con `+ 86400000`.
  * **A la API**: el rango de la agenda va como fechas solas (`from=2026-11-16&to=2026-11-22`: la API las toma como días enteros de
    Asunción, también uno de 23 o 25 horas); `starts_at` y `ends_at` con `toApiDateTime` (`-03:00`, o el desplazamiento que tenga esa
    fecha).
  * **De la API**: `formatTime`, `formatDate` y `inAppZone` (ya existen). La posición en la grilla sale de la hora de pared
    (`inAppZone(starts_at)`), no de los milisegundos desde la medianoche: en un día de 25 horas, la hora repetida cae en su misma fila
    (dos turnos a la misma hora de pared se apilan, como un sobreturno).
  * **Medianoche** (H10): un turno nunca cruza; Zod lo rechaza con "El turno tiene que empezar y terminar el mismo día.".
* **La grilla** (3.2 y 3.3). Filas de 15 minutos; el rango de horas del día va del primer tramo al último de las agendas que se ven
  (con su detalle, H3), ampliado a cualquier turno que quede afuera (un turno movido antes de cambiar el horario); sin tramos, de 08:00
  a 18:00. Las horas fuera del horario y los bloqueos, sombreados con su motivo. Cada turno muestra la hora, el paciente (o el cliente,
  o el contacto), el servicio y el estado (texto e ícono, no solo color), con el color de la agenda como borde. El sobreturno, con su
  marca. Los turnos que se superponen (sobreturnos) se dibujan lado a lado.
* **Estados en la grilla** ([pregunta 5](#preguntas-del-diseño)): los cancelados ocultos salvo "Mostrar los cancelados"; los ausentes,
  atenuados.
* **Permisos.** Cada ruta con `requirePermission`; los botones con `useCan()`; `PERMISSIONS` suma los de la fase con los nombres de la
  API. Los tests, con `meFor(rol)` de los tres roles reales.
* **Rendimiento** (H1, [pregunta 1](#preguntas-del-diseño)). La agenda hace **un pedido por día o por semana** de la sucursal
  (`per_page=500`, front-03, pregunta 1), más el detalle y los bloqueos de cada agenda que se ve (en caché). Si `meta.pagination.total`
  supera lo traído, la grilla avisa "Hay más turnos de los que se muestran: usá la lista." en lugar de mostrar una semana incompleta
  sin decirlo: nunca corta en silencio. La lista pagina de a 20, como los demás listados. La cola, un pedido (hasta 200, con el aviso de
  `meta.truncated`).
* **Celular, 375 px** ([pregunta 4](#preguntas-del-diseño) para la semana). Sin desplazamiento horizontal de la página. "Día" muestra
  **una agenda por vez** (un selector arriba, con la primera de la vista por defecto) y la grilla en una columna; "Semana" se ve como
  una lista agrupada por día (las mismas tarjetas de turno, sin la grilla); la lista, como tarjetas en lugar de tabla (el patrón de
  `DataTable` en pantallas chicas). Los botones de las acciones van en el detalle del turno, con el alto táctil de Tabler.
* **Accesibilidad.**
  * **La grilla** sigue el patrón *grid* de WAI-ARIA: `role="grid"` con sus encabezados (las agendas o los días) y las horas como
    encabezados de fila; **un solo punto de tabulación** (*roving tabindex*): las flechas recorren las celdas y los turnos, Inicio y Fin
    van al comienzo y al fin de la fila, Re Pág y Av Pág cambian de día (o de semana); Enter abre el turno o, en una celda libre, da un
    turno ahí (solo con `appointments.store`). Cada turno tiene un nombre accesible completo ("09:00 a 09:30, Luna, de Laura Martínez,
    Consulta, Confirmado, Consultorio 1"). La lista es la alternativa equivalente sin grilla.
  * **Anuncios**: una región `role="status"` siempre en el DOM dice qué se está viendo al cambiar de día, de vista o de filtro
    ("Martes 17 de noviembre: 12 turnos.") y el resultado de una acción ("Turno confirmado."). La actualización periódica no anuncia
    nada (no interrumpe al lector de pantalla); el foco no se mueve si un turno cambia de lugar por otra PC (si el turno con foco
    desaparece, va a su celda).
  * Los diálogos, con `ConfirmDialog` (el foco, Escape, Tab dentro del diálogo y la vuelta al botón que lo abrió).
* **Preparado para la FASE 4, sin implementarlo.** Las acciones de un turno salen de una tabla pura
  (`features/appointments/transitions.ts`, espejo de `AppointmentTransition`: origen, destino, permiso y las reglas conocidas de cada
  una) y de un componente de acciones que la recibe; "Marcar atendido" es una entrada más. La FASE 4 (la consulta se abre desde un
  turno fichado y lo marca atendido, según la [propuesta del backend](../backend/fase-03-agenda-y-turnos.md#relación-con-la-consulta-fase-4))
  podrá sumar "Abrir la consulta" para el veterinario sin tocar la grilla. En la F3 no hay enlace ni texto de la consulta; el detalle
  muestra `attended_at`.
* **Sin paquetes nuevos.** La grilla es propia (Tabler y CSS), sin una biblioteca de calendario: tendría que trabajar con la zona de
  Asunción por `@date-fns/tz` (no con la de la PC) y con el teclado del patrón *grid*, y el diseño no la necesita. Si en la 3.2 aparece
  una que cumpla las dos cosas, se propone con su versión exacta antes de instalarla.
* **Código común nuevo** (cada uno en la entrega que lo usa primero): las listas en la URL del cliente de la API (H5, 3.3);
  `features/appointments/dates.ts` (3.2); el componente de la grilla con el teclado (3.2); el selector de cliente, paciente o contacto
  (3.4); `transitions.ts` (3.5).

## Entregas

| Entrega | Contenido | Necesita el backend | Rama | Estado |
|---|---|---|---|---|
| 3.1a | Agendas: listado, detalle, alta y edición con el horario semanal, inactivar y baja (`/configuracion/agendas`) | No | `feature/front-3-1a-agendas`, desde `docs/front-f3-diseno` | COMPLETADO (R-086, [resultado](#entrega-31a)) |
| 3.1b | Bloqueos de una agenda: listar, crear (con los turnos afectados) y eliminar | No | `feature/front-3-1b-bloqueos`, desde la 3.1a | COMPLETADO (R-087, [resultado](#entrega-31b)) |
| front-03 | Soporte del backend: `per_page` hasta 500 en `GET appointments` ([pregunta 1](#preguntas-del-diseño)) | — | `feature/front-03-turnos-por-pagina`, desde la 3.1b | COMPLETADO (R-088, [resultado](#entrega-front-03)) |
| 3.2 | La agenda del día: grilla por agenda, filtros, la sucursal actual, el teclado y "Agenda" en el menú (solo lectura) | front-03 | `feature/front-3-2-agenda-dia`, desde front-03 | COMPLETADO (R-089, [resultado](#entrega-32)) |
| demo-02 | Soporte del backend para la demo: un comando `php artisan demo:agenda` que crea en la demo agendas para las sucursales y turnos ficticios de la semana en curso (varios estados, un bloqueo y un sobreturno), idempotente y sin tocar `admin-a`, `recepcion-a` ni `vet-a`. La demo no tiene agendas activas ni turnos propios (H6, desvío 14 de la 3.2) | — | `feature/demo-02-agenda`, desde la 3.2 | APROBADA (siguiente; decisión del usuario del 2026-11-18, R-089) |
| 3.3 | La semana y la lista de turnos (`/turnos`, con los filtros de la API y las listas en la URL) | No | `feature/front-3-3-semana-lista`, desde la 3.2 | APROBADA (después de demo-02) |
| 3.4 | El turno: detalle, alta (paciente, cliente o contacto; servicio y duración; horario con la disponibilidad; sobreturno), edición y reprogramación | No | `feature/front-3-4-turnos`, desde la 3.3 | APROBADA |
| 3.5 | Los estados: confirmar, fichar, atendido, ausente y deshacerlo, cancelar con motivo | No | `feature/front-3-5-estados`, desde la 3.4 | APROBADA |
| 3.6 | Las series: el alta con repetición, los conflictos de la serie y cancelar con alcance | No | `feature/front-3-6-series`, desde la 3.5 | APROBADA |
| 3.7 | La cola del día (`/agenda/cola`), con fichar y atendido desde ahí ([pregunta 11](#preguntas-del-diseño)) | No | `feature/front-3-7-cola`, desde la 3.6 | APROBADA |

Cada rama sale de la anterior, como en la F2, y todo se integra a `main` al cerrar la F3. Las agendas van primero: sin ellas no hay
dónde dar un turno desde la interfaz. El turno va en tres entregas (3.4, 3.5 y 3.6): juntas serían el triple que las demás. Cada
entrega recorre los pasos 4 a 7 de [`prompts/README.md`](../../prompts/README.md).

## Diseño por entrega

### 3.1a Agendas: listado, detalle, alta, edición y horario

**Pantallas.**

| URL | Permiso | Contenido |
|---|---|---|
| `/configuracion/agendas?buscar=&sucursal=&inactivas=&pagina=` | `schedules.update` (la tarjeta, como las demás de `/configuracion`) | Búsqueda ("Buscá por nombre"), sucursal e "Incluir las inactivas"; tabla: nombre (enlace, con el color), sucursal, veterinario, servicio, duración del turno ("30 min") e "Inactiva". "Nueva agenda" (`schedules.store`) |
| `/configuracion/agendas/nueva`, `/$id/editar` | `schedules.store`, `schedules.update` | El formulario |
| `/configuracion/agendas/$id` | `schedules.update` | Los datos, el horario semanal (una fila por día, de lunes a domingo, con sus tramos), alta y última modificación; Editar, Inactivar o Activar y Eliminar |

**Formulario.** Sucursal (activas), nombre, veterinario (los activos de esa sucursal, con su usuario activo, de
`GET veterinarians?branch_id=`), servicio (activos), duración del turno (`slot_minutes`: 5 a 240, de a 5; 30 por defecto), color
(`<input type="color">`; opcional; la API lo guarda en mayúsculas) y el horario: por día, una lista de tramos "de … a …" (`TimeField` de a 5
minutos, de 00:00 a 23:55), con "Sumar un tramo", "Quitar" y "Copiar el lunes a los días hábiles". Al editar, el horario se manda
entero (reemplaza al anterior: `hours: []` lo vacía); el aviso "El horario nuevo no cambia los turnos que ya están dados."

**Validación.**

| Campo | Zod (navegador) | Solo la API |
|---|---|---|
| `branch_id` | Obligatorio | Sucursal inactivada mientras tanto |
| `name` | 2 a 100, `CatalogMessages::NAME_REGEX` (al menos una letra) | Repetido en la sucursal ("Ya hay una agenda con ese nombre en la sucursal.") |
| `veterinarian_id`, `service_id` | Al menos uno de los dos ("Elegí un veterinario, un servicio o los dos.") | Inactivos, o el veterinario sin esa sucursal (422 al campo); `SCHEDULE_NOT_EDITABLE` |
| `slot_minutes` | Entero de 5 a 240, múltiplo de 5 | — |
| `color` | `#RRGGBB` | — |
| `hours` | Cada tramo con inicio antes del fin, en múltiplos de 5; sin superponerse en el mismo día (tocarse sí); hasta 6 por día y 42 en total, con los mensajes de `ScheduleMessages` | — |

Las claves con índice del 422 (`hours.3.starts_at`) van al tramo correspondiente (el patrón `hours.*.starts_at` de
`applyValidationErrors`, que ya existe).

**Acciones en el detalle.** Inactivar o Activar (`{ is_active }`; el aviso "Los turnos que ya tiene siguen: reprogramalos o
cancelalos.") y Eliminar (`SCHEDULE_IN_USE`: el mensaje y "Inactivar").

**Tests (Vitest).** `validateSearch`; el horario: superposición en el mismo día (y tocarse pasa), 6 y 7 tramos en un día, 23:55, el
fin antes del inicio; "Copiar el lunes"; el cuerpo con `hours: []` al vaciarlo; sin veterinario ni servicio,
sin pedido; `hours.1.ends_at` a su tramo; `SCHEDULE_NOT_EDITABLE` en la alerta; la baja con `SCHEDULE_IN_USE`; el recepcionista y el
veterinario sin la tarjeta y con `/sin-permiso`.

**Playwright** (`e2e/agendas.spec.ts`; corre también `e2e/permisos.spec.ts`, R-084, por la tarjeta y la ruta nuevas). Alta de una
agenda de `vet-a` con dos tramos el lunes que se tocan y verla después de recargar; editar el horario; inactivarla y verla con
"Incluir las inactivas"; eliminarla (sin turnos).

**Mutaciones a probar.** El horario mandado sin los días vacíos (un día vaciado queda con sus tramos viejos); la superposición sin
controlar en Zod; el tramo de 23:55 rechazado.

**En el navegador.** El editor del horario con el teclado; el ancho de un celular; el selector de color.

#### Pendiente para el cierre (3.1a)

Vacío: la entrega se cerró (R-086) y todo lo que había acá pasó a su [resultado](#entrega-31a).

### 3.1b Bloqueos de una agenda

**En el detalle de la agenda**, la tarjeta "Bloqueos" (`schedule-blocks.index`, los tres roles; la pantalla es del administrador):
los próximos (`GET schedules/{id}/blocks`, paginado) con desde, hasta y motivo; "Nuevo bloqueo" y "Eliminar" (`schedule-blocks.store`
y `.destroy`), en diálogos.

**El alta.** Desde y hasta, cada uno con fecha (`DateField`) y hora (`TimeField`, de a 5 minutos), mandados con `toApiDateTime`;
"Todo el día" pone desde las 00:00 de la fecha desde hasta las 00:00 del día siguiente a la fecha hasta (un bloqueo, a diferencia de
un turno, puede cruzar la medianoche y durar hasta 366 días). Motivo (hasta 255). Con el 201, si `affected_appointments` no está vacío, el diálogo pasa a mostrar la
lista ("Estos turnos quedaron dentro del bloqueo: reprogramalos o cancelalos.") con el enlace a cada uno (desde la 3.4).

**Validación.** Zod: el fin después del inicio, en múltiplos de 5, hasta 366 días ("El bloqueo no puede durar más de 366 días.", el
mensaje de `StoreScheduleBlockRequest`). La API: lo mismo.

**Tests (Vitest).** El cuerpo con `-03:00` con la PC en UTC; "Todo el día"; los afectados en el diálogo; 366 y 367 días; el
recepcionista ve la lista sin "Nuevo bloqueo".

**Playwright** (`e2e/agendas.spec.ts`). Un bloqueo de una hora en la agenda creada y verlo en el detalle; eliminarlo. Los afectados,
en la 3.4 (cuando haya turnos desde la interfaz).

**Mutaciones a probar.** La fecha mandada con `toISOString()` de un `Date` local; los afectados sin mostrar.

**En el navegador.** El diálogo con el teclado y el selector nativo de la fecha.

#### Pendiente para el cierre (3.1b)

Vacío: la entrega se cerró (R-087) y todo lo que había acá pasó a su [resultado](#entrega-31b).

### front-03 Soporte del backend: más turnos por página en el listado

**Aprobada con la opción (a) de la [pregunta 1](#preguntas-del-diseño).** `IndexAppointmentRequest` acepta `per_page` de 1 a 500
(el resto de los listados sigue hasta 100, `ListFilters`), con su mensaje. Sin rutas, permisos, migraciones ni `error_code` nuevos;
aditivo (con `per_page` de hasta 100 responde lo mismo que hoy). Tests: 500 pasa y 501 → 422; la cantidad de consultas fija con 1 y
con 300 turnos (sin N+1, `InspectsQueries`). Postman: un pedido de la colección de turnos con `per_page=500`. `backend/CLAUDE.md`
§6.10 y la [guía de Postman](../../../05-pruebas/guia-postman.md). Al cerrar: `composer test` y Newman de la colección de turnos.

#### Pendiente para el cierre (front-03)

Vacío: la entrega se cerró (R-088) y todo lo que había acá pasó a su [resultado](#entrega-front-03).

### 3.2 La agenda del día

**Pantalla.** `/agenda?vista=dia&fecha=&agenda=&veterinario=&cancelados=` (`appointments.index`, los tres): la barra de la agenda
(Hoy, día anterior y siguiente, la fecha con `DateField`, "Día", "Semana" y "Lista" (estas dos, desde la 3.3), los filtros de agenda y
veterinario y "Mostrar los cancelados") y la grilla con una columna por agenda activa de la sucursal (las filtradas). El veterinario
entra con su vista por defecto ([pregunta 3](#preguntas-del-diseño)). Un clic (o Enter) en un turno lleva a su detalle (desde la 3.4;
en la 3.2, la tarjeta del turno muestra sus datos en un panel de solo lectura).

**Datos.** `GET appointments?branch_id=<actual>&from=<fecha>&to=<fecha>&per_page=…` y el detalle y los bloqueos de cada agenda que se ve
(H3, H4). Los filtros, en el navegador (H2).

**Turnos dentro de un bloqueo** (decisión del usuario en la revisión de la 3.1b): la API crea el bloqueo aunque haya turnos en
ese horario y solo los lista en el 201 (`affected_appointments`); para que no se pierdan al cerrar ese diálogo, la grilla marca
cada turno que se superpone con un bloqueo de su agenda (`a.starts_at < b.ends_at` y `a.ends_at > b.starts_at`, los estados que
ocupan el horario) con "Dentro de un bloqueo" (texto e ícono, no solo color), calculado en el navegador con los bloqueos que ya
se piden para el sombreado. Test: un turno adentro, uno que solo toca el bloqueo (sin la marca) y uno cancelado (sin la marca).

**Tests (Vitest).** `dates.ts`: el día, la semana desde el lunes, sumar días, el día de 23 horas (2023-10-01) y el de 25
(2024-03-23), la posición de un turno por su hora de pared, con la PC en UTC; `validateSearch` (fecha mal formada, vista desconocida);
la grilla: las columnas, el sombreado fuera del horario y de un bloqueo, el rango de horas ampliado por un turno afuera, los
sobreturnos lado a lado, los cancelados ocultos y con el filtro, los ausentes atenuados; el teclado (flechas, Inicio, Fin, Re Pág y Av
Pág; un solo punto de tabulación); el anuncio al cambiar de día; el aviso de "Hay más turnos…"; el veterinario con su vista por
defecto y "Ver toda la sucursal"; sin sucursal actual; el cambio de sucursal que vuelve a pedir; el menú con "Agenda" para los tres.

**Playwright** (`e2e/agenda.spec.ts`; corre también `e2e/permisos.spec.ts`, R-084, por el ítem "Agenda"). Con el ayudante de la
[pregunta 13](#preguntas-del-diseño): una agenda y dos turnos de hoy creados por la API; verlos en su columna; ir al día siguiente y
volver con el teclado; el veterinario `vet-a` los ve en su vista por defecto.

**Mutaciones a probar.** La fecha sumada con milisegundos (falla el día de 25 horas); la posición con `getHours()` de la PC; el
filtro del veterinario sin las agendas sin veterinario.

**En el navegador.** La grilla con 4 agendas a 1280 px y una sola a 375 px, sin desplazamiento horizontal; el foco visible al
recorrer con el teclado; el lector de pantalla con el nombre de un turno (Narrador); la consola sin errores.

#### Pendiente para el cierre (3.2)

Vacío: la entrega se cerró (R-089) y todo lo que había acá pasó a su [resultado](#entrega-32).

### 3.3 La semana y la lista de turnos

**Semana** ([pregunta 4](#preguntas-del-diseño)): `/agenda?vista=semana&fecha=` (cualquier día de la semana; se muestra de lunes a
domingo), con siete columnas, de una agenda por vez (el filtro de agenda, con la primera de la vista por defecto). Un solo pedido de
la semana (`from` lunes, `to` domingo). En el celular, la lista agrupada por día.

**Lista** (`/turnos?desde=&hasta=&agenda=&veterinario=&estados=&buscar=&pagina=`, `appointments.index`): el modelo de `/clientes`
(`useSearchText`, 20 por página, "Esta página no existe."), con desde y hasta (fechas solas; sin ninguna, hoy; hasta 31 días, con el
mensaje de la API "El rango no puede superar los 31 días."), agenda, veterinario, estados (casillas, mandadas como `status[]`, H5) y la
búsqueda ("Buscá por paciente, cliente, documento o contacto"). Tabla: fecha y hora, agenda, paciente (o cliente o contacto), servicio,
estado y "Sobreturno"; cada fila enlaza al turno. La sucursal actual va siempre como `branch_id`.

**Turnos dentro de un bloqueo** (decisión del usuario en la revisión de la 3.1b, como en la [3.2](#32-la-agenda-del-día)): la
semana y la lista marcan "Dentro de un bloqueo" en cada turno que se superpone con un bloqueo de su agenda (con los bloqueos de
las agendas del rango, en caché), para que los turnos que quedaron adentro al crear el bloqueo se reprogramen o se cancelen
aunque ya se haya cerrado el diálogo de la 3.1b. Test: la marca en la semana y en la lista; sin ella, el que solo toca el bloqueo.

**Cliente de la API** (H5): `buildUrl` admite listas (`status: ['confirmed', 'checked_in']` → `status[]=confirmed&status[]=checked_in`),
con sus tests; las llamadas de hoy no cambian.

**Tests (Vitest).** La semana de lunes a domingo (también la que contiene el 23/03/2024); `buildUrl` con listas, una lista vacía (sin
clave) y los valores de siempre; `validateSearch` de la lista (`estados` desconocido, rango invertido, 32 días); el pedido con
`status[]`; la búsqueda con la espera de 300 ms y "Buscando…"; el cambio de vista conserva la fecha.

**Playwright** (`e2e/agenda.spec.ts`). La semana con los turnos del caso de la 3.2; la lista filtrada por estado y la búsqueda por el
nombre del paciente.

**Mutaciones a probar.** La semana que empieza el domingo; `status` mandado como texto con comas; el rango sin el tope.

**En el navegador.** La semana a 1280 px y a 375 px (la lista por día); la lista como tarjetas en el celular.

#### Pendiente para el cierre (3.3)

Vacío.

### 3.4 El turno: detalle, alta, edición y reprogramación

**Pantallas.**

| URL | Permiso | Contenido |
|---|---|---|
| `/turnos/$id` | `appointments.show` | Fecha, hora y duración; agenda, sucursal y veterinario; paciente (enlace), cliente (enlace) o el contacto; servicio, origen, motivo y notas; estado, "Sobreturno", la serie (desde la 3.6) y las horas de cada estado; quién lo creó; Editar y Reprogramar (`appointments.update`, solo a confirmar o confirmado). Las acciones de estado, desde la 3.5 |
| `/turnos/nuevo?agenda=&inicio=` | `appointments.store` | El formulario; desde la grilla, la celda elegida llena la agenda y el inicio |
| `/turnos/$id/editar` | `appointments.update` | El mismo formulario ("Reprogramar" lleva acá con el foco en el horario) |

**Formulario** ([preguntas 6 a 8 y 10](#preguntas-del-diseño)).

* **Quién**: un selector con tres opciones (`RadioGroup`): "Paciente" (la búsqueda de `GET patients?search=`, que también encuentra al
  dueño; elegir el paciente fija el cliente, que se muestra), "Cliente sin paciente" (`GET clients?search=`) y "Primera vez, sin
  cliente" (nombre y teléfono de contacto). Sin alta rápida ([pregunta 6](#preguntas-del-diseño)): el detalle de un turno con contacto o
  sin paciente muestra "Para fichar, cargá el paciente." con los enlaces a "Nuevo cliente" y a editar el turno.
* **Agenda** (las activas de la sucursal actual), **servicio** (el de la agenda por defecto; los activos) y **duración** (la del
  servicio o `slot_minutes`; editable de a 5 minutos, hasta 720).
* **Horario** ([pregunta 7](#preguntas-del-diseño)): la fecha y los horarios libres de la disponibilidad
  (`GET schedules/{id}/availability?from=&to=&service_id=&duration_minutes=`) como botones (un grupo de radios accesible), y "Otra
  hora" para escribirla (la API decide). Se mandan `starts_at` y `ends_at` con `toApiDateTime`.
* **Origen** (Recepción, Teléfono, WhatsApp; Recepción por defecto), **motivo** y **notas**.
* Al editar, el formulario se manda completo **salvo** `starts_at`, `ends_at` y `schedule_id` si no cambiaron (no hace falta: la API no
  mueve con los mismos valores, pero así el cuerpo dice lo que se quiso cambiar); `client_id` se reenvía como está (la API acepta el
  guardado después de un cambio de dueño).

**Validación.**

| Campo | Zod (navegador) | Solo la API |
|---|---|---|
| Quién | Uno de los tres; el contacto con nombre (2 a 150, al menos una letra, sin `<` `>` ni caracteres de control) y teléfono (`PHONE_PATTERN`, con `normalizePhone`) juntos | Paciente fallecido, de otro dueño, eliminado |
| `schedule_id` | Obligatoria | Inactiva, o su veterinario inactivo (422 en el campo, D2) |
| `starts_at`, `ends_at` | Múltiplos de 5; el fin después del inicio; el mismo día (H10); hasta 720 minutos; no un día que ya pasó (con `todayInAppZone()`), con los mensajes de `AppointmentMessages` | Fuera del horario, en un bloqueo, superpuesto |
| `service_id` | — | Inactivo |
| `reason`, `notes` | Hasta 255 y 1000, en caracteres | — |

**Errores** (la tabla de [lo que da la API](#lo-que-da-la-api-resumen-del-análisis)): `APPOINTMENT_OVERLAP`, `_OUTSIDE_HOURS` e
`_IN_BLOCK` al campo del horario, con la disponibilidad vuelta a pedir (la grilla de horarios libres se actualiza sola); el sobreturno
([pregunta 8](#preguntas-del-diseño)): con `appointments.overbook`, "Dar como sobreturno" en la alerta del 409 abre un
`ConfirmDialog` ("El horario ya está ocupado. ¿Querés darlo igual como sobreturno?") y reenvía con `overbook: true`; sin el permiso,
solo el mensaje. `APPOINTMENT_NOT_EDITABLE`, alerta y el turno vuelto a pedir. 422 a sus campos. Un solo envío (`useSingleAction`).

**Tests (Vitest).** Las reglas de Zod en su borde (720 y 725 minutos, 23:55, el fin a las 00:00 del día siguiente, ayer y hoy con la
PC en UTC y la hora de Asunción ya en el día siguiente); quién (los tres y sus mensajes); el cuerpo del alta con `-03:00`; la edición sin
`starts_at` si no cambió; la disponibilidad como radios y "Otra hora"; el 409 con y sin `appointments.overbook` y el reenvío con
`overbook: true`; `_OUTSIDE_HOURS` en el campo; el veterinario inactivo en `schedule_id`; el veterinario sin "Nuevo turno" ni
"Editar"; un solo pedido con doble clic.

**Playwright** (`e2e/turnos.spec.ts`). La recepción da un turno a un paciente del `DemoSeeder` desde una celda libre y lo ve en la
grilla; otro turno en el mismo horario: el 409 y "Dar como sobreturno"; reprogramarlo a otra hora; un turno de "Primera vez" con
contacto; un bloqueo sobre un turno muestra el afectado (de la 3.1b).

**Mutaciones a probar.** El fin a las 00:00 aceptado; el alta sin volver a pedir la disponibilidad después del 409; `overbook`
mandado sin el permiso.

**En el navegador.** El formulario a 375 px; los horarios libres con el teclado; la pestaña Red con el cuerpo del alta y de la
reprogramación.

#### Pendiente para el cierre (3.4)

Vacío.

### 3.5 Los estados del turno

**Acciones** (en el detalle y en el panel del turno de la grilla), de `transitions.ts`, cada una con su permiso
([pregunta 14](#preguntas-del-diseño) para las que la API rechazaría):

| Acción | Desde | Permiso | Lo que el navegador avisa antes |
|---|---|---|---|
| Confirmar | A confirmar | `appointments.confirm` | — |
| Fichar | A confirmar, confirmado | `appointments.check-in` | Sin paciente: deshabilitado, "Para fichar, cargá el paciente."; otro día: deshabilitado, "Solo se fichan los turnos del día."; el veterinario, solo en los suyos y los de agendas sin veterinario |
| Marcar atendido | Fichado | `appointments.attend` | El veterinario, solo en los suyos y los de agendas sin veterinario |
| Ausente | A confirmar, confirmado | `appointments.no-show` | Antes del inicio: deshabilitado, "Se marca desde las 09:30." |
| Deshacer el ausente | Ausente | `appointments.no-show` | Otro día: oculto. Con el horario ocupado, el 409 y, con `appointments.overbook`, "Volver como sobreturno" |
| Cancelar | A confirmar, confirmado, fichado | `appointments.cancel` | — |

Cancelar abre un diálogo con el motivo (`TextareaField`, 3 a 500, obligatorio). Las demás, sin diálogo salvo deshacer el ausente
(`tone="primary"`, "El turno vuelve a confirmado."). Con el 200, `setQueryData` del detalle, la invalidación de la agenda y el aviso
("Turno confirmado."), también en la región `role="status"`. Los errores, con `dialogError` (los 422 de negocio, el mensaje y
"Cerrar", con el turno vuelto a pedir). "Ahora" para las reglas de hora es la del navegador; si difiere de la del servidor, decide la
API.

**Tests (Vitest).** `transitions.ts` contra la tabla del backend (un test lee `app/Enums/AppointmentTransition.php`, como H2 de la F2,
y falla si cambia); cada acción con su permiso en los tres roles; fichar sin paciente y otro día; ausente antes y después del inicio
(relojes falsos); el veterinario en un turno ajeno sin "Fichar" ni "Atendido" y en uno de una agenda sin veterinario con los dos;
cancelar sin motivo, sin pedido; `APPOINTMENT_INVALID_TRANSITION` en el diálogo; deshacer el ausente con el 409.

**Playwright** (`e2e/turnos-estados.spec.ts`). Confirmar, fichar y que `vet-a` lo marque atendido en otro contexto; marcar ausente un
turno ya empezado (el ayudante lo crea a una hora pasada de hoy) y deshacerlo; cancelar con motivo y verlo oculto en la grilla.

**Mutaciones a probar.** "Fichar" habilitado otro día; el veterinario con "Atendido" en un turno ajeno; cancelar sin `cancellation_reason`.

**En el navegador.** Las acciones a 375 px; el diálogo de cancelar con el teclado.

#### Pendiente para el cierre (3.5)

Vacío.

### 3.6 Las series

**El alta con repetición** (en el formulario de la 3.4, solo en el alta): "Repetir" con "cada N semanas" (1 a 12) y "Cantidad de
turnos" (2 a 52) o "Hasta" (una fecha); el resumen ("8 turnos, los martes a las 09:30, hasta el 12/01/2027"). Zod repite las reglas:
una de cantidad o fecha, la fecha desde el primer turno, de 2 a 52 turnos y como mucho un año después (con los mensajes de
`AppointmentMessages`; la cuenta de fechas con `TZDate`, como `AppointmentRecurrence`: la misma hora de pared). Con el 201, al primer
turno, con "Serie de N turnos creada correctamente." (el `message` de la API).

**Los conflictos** (H8): `APPOINTMENT_SERIES_CONFLICT` muestra la lista de `errors.conflicts` (la fecha y la hora en Asunción y el
`message` de cada una) en una alerta arriba del formulario; si hay superposiciones y el usuario tiene `appointments.overbook`, "Dar la
serie con sobreturnos" (solo saltea la superposición; si queda una fecha fuera del horario o en un bloqueo, la API vuelve a responder
la lista).

**Cancelar con alcance**: en un turno con `series_id`, el diálogo de cancelar suma "Solo este turno", "Este y los siguientes" y "Toda
la serie" (`scope`), con el aviso de que de los demás se cancelan solo los a confirmar o confirmados que todavía no empezaron; el
aviso del 200 es el `message` de la API ("Se cancelaron N turnos de la serie."). El detalle muestra "Turno de una serie" con el enlace
a la lista filtrada por la serie (`/turnos?serie=`, `series_id`).

**Tests (Vitest).** El resumen y la cuenta de fechas (también una serie que cruza el 23/03/2024, con la hora de pared); 52 y 53
turnos; "hasta" a un año y un día; el cuerpo con `recurrence`; la lista de conflictos con sus fechas en Asunción; el alcance en el
cuerpo y oculto sin serie.

**Playwright** (`e2e/turnos-series.spec.ts`). Una serie de 4 turnos semanales y verlos en la lista de la serie; otra serie que choca
con uno (la lista de conflictos); cancelar "Este y los siguientes" desde el tercero.

**Mutaciones a probar.** La cuenta de fechas con milisegundos; `scope` mandado en un turno sin serie; la lista de conflictos con la hora
de la PC.

**En el navegador.** El formulario con la repetición a 375 px; la lista de conflictos larga.

#### Pendiente para el cierre (3.6)

Vacío.

### 3.7 La cola del día

**Pantalla** ([pregunta 11](#preguntas-del-diseño)). `/agenda/cola?fecha=&todos=` (`appointments.queue`, los tres), con su pestaña en
la barra de la agenda: los turnos del día de la sucursal actual por hora (sin los cancelados), con el paciente (especie y raza), el
cliente, el servicio, el veterinario, el estado y, en los fichados, "Esperando desde las 09:12" (`checked_in_at`). El veterinario ve
por defecto los suyos y los de las agendas sin veterinario (`meta.includes_unassigned`: "Incluye las agendas sin veterinario.") y
"Ver toda la sucursal" (`todos=1` → `all=1`). Las acciones de la 3.5 en cada fila (fichar, atendido, ausente). `meta.truncated`: "Se
muestran los primeros 200 turnos.". Sin sucursal actual, el mensaje de `APPOINTMENT_QUEUE_NO_BRANCH`.

**Tests (Vitest).** La clave con sus filtros (D4); la vista por defecto del veterinario y `all=1`; el texto de la espera; el aviso de
`truncated`; el 422 sin sucursal; las acciones por rol.

**Playwright** (`e2e/cola.spec.ts`). `vet-a` ve su turno fichado en la cola y lo marca atendido; la recepción ve toda la sucursal.

**Mutaciones a probar.** `all=1` por defecto para el veterinario; la cola sin la sucursal en la clave.

**En el navegador.** La cola a 375 px; la actualización al volver a la pestaña.

#### Pendiente para el cierre (3.7)

Vacío.

## Documentación por entrega

| Entrega | Actualizar |
|---|---|
| front-03 | `backend/CLAUDE.md` §6.10, la [guía de Postman](../../../05-pruebas/guia-postman.md) y su colección, este documento, el roadmap y el handoff |
| 3.1a a 3.7 | `frontend/CLAUDE.md` (las convenciones nuevas: la grilla y su teclado, `dates.ts` de la agenda, las listas en la URL, `transitions.ts`; las precisiones D3 y D4 de §5; la tabla de errores con los tratos de la fase), este documento (Resultado), el roadmap, `modulos.md` y el handoff |

## Preguntas del diseño

Cada una cambia el comportamiento: el usuario elige. La recomendación, en negrita.

| # | Pregunta | Opciones | Recomendación | Respuesta |
|---|---|---|---|---|
| 1 | ¿Cómo trae la agenda una semana con más de 100 turnos? (H1) | (a) Entrega de soporte **front-03**: `GET appointments` acepta `per_page` hasta 500, solo ese listado; (b) el frontend pide todas las páginas de a 100 en paralelo; (c) la semana muestra una sola agenda y se acepta el aviso "Hay más turnos…" si pasa de 100 | **(a)**: un pedido, sin páginas que cambian entre un pedido y otro (con (b), un turno nuevo corre el resto y uno puede repetirse o faltar); es aditiva y el rango ya tiene tope (31 días) | **(a)**, aprobada (usuario): front-03, con `per_page` hasta 500 solo en `GET appointments`. Si `total` supera lo traído, la pantalla avisa que faltan turnos; nunca corta en silencio |
| 2 | ¿De qué sucursal es la agenda? | (a) La sucursal actual de la barra (para ver otra, se cambia ahí); (b) un filtro propio entre las sucursales del usuario, sin cambiar la actual | **(a)**: la cola del día ya funciona así, `branchKey` se invalida al cambiarla y no hay dos sucursales "actuales" en pantalla | **(a)**, aprobada (usuario): la sucursal actual de la barra |
| 3 | ¿Qué ve el veterinario al entrar a la agenda? (H12) | (a) Por defecto, sus agendas y las de las agendas sin veterinario de la sucursal, con "Ver toda la sucursal" (como la cola); (b) toda la sucursal; (c) solo los suyos, sin la opción (la API igual le da todos) | **(a)**: es lo que decidió el backend para la cola (pregunta 25 y D1 de la 3.4) y no oculta lo que la API le permite ver | **(a)**, aprobada (usuario): sus turnos y los de las agendas sin veterinario, con "Ver toda la sucursal" |
| 4 | ¿Qué muestra la semana? | (a) Una agenda por vez, en siete columnas; (b) todas las agendas, con los turnos de cada día apilados | **(a)**: con varias agendas, una semana apilada no se lee y no entra en una tablet | **(a)**, aprobada (usuario): una agenda por vez, con el selector |
| 5 | ¿Los cancelados y los ausentes en la grilla? | (a) Cancelados ocultos con "Mostrar los cancelados" y ausentes atenuados; (b) todos visibles igual; (c) cancelados ocultos sin opción | **(a)**: un cancelado libera el horario y en la grilla confunde; la lista los muestra siempre | **(a)**, aprobada (usuario): cancelados ocultos con "Mostrar los cancelados" y ausentes atenuados |
| 6 | ¿Alta rápida de cliente y paciente desde el turno? | (a) No: la primera vez va con nombre y teléfono de contacto (lo previsto por la API, P4) y el paciente se carga antes de fichar; (b) un diálogo con los campos obligatorios del cliente (documento, email…) y del paciente dentro del formulario del turno; (c) un enlace a "Nuevo cliente" que vuelve al turno | **(a)**: el alta de un cliente pide documento y email, que por teléfono no siempre se tienen, y (b) duplica dos formularios grandes; el contacto existe para eso | **(a)**, aprobada (usuario): sin alta rápida; la primera vez, con nombre y teléfono de contacto |
| 7 | ¿Cómo se elige el horario? (H7) | (a) Los horarios libres de la disponibilidad como botones, y "Otra hora" a mano (la API decide); (b) solo la hora a mano; (c) solo los libres | **(a)**: lo común es un clic y lo raro (el que llegó sin turno a una hora ya empezada) sigue siendo posible | **(a)**, aprobada (usuario): los horarios libres como botones y "Otra hora" |
| 8 | ¿Cómo se da un sobreturno? | (a) Después del 409 `APPOINTMENT_OVERLAP`, "Dar como sobreturno" con confirmación, solo con `appointments.overbook`; (b) además, una casilla "Sobreturno" en el formulario; (c) solo la casilla | **(a)**: es lo que sugiere el mensaje de la API y nadie da un sobreturno sin saber que el horario estaba tomado | **(a)**, aprobada (usuario): el sobreturno solo después del 409 |
| 9 | ¿La agenda se actualiza sola? | (a) Al volver a la pestaña, después de cada cambio propio y cada 60 s con la pestaña visible; (b) sin el intervalo | **(a)**: la recepción trabaja en varias PC a la vez; 60 s es un pedido por minuto y por pestaña visible | **(a)**, aprobada (usuario), con una precisión: cada 60 s, en pausa mientras hay un formulario o un diálogo abierto y con la pestaña oculta |
| 10 | ¿El alta y el detalle del turno, en páginas o en un diálogo sobre la grilla? | (a) Páginas (`/turnos/nuevo`, `/turnos/$id`), como el resto; (b) un diálogo o panel lateral | **(a)**: funciona igual en el celular, tiene URL (se comparte y "Atrás" vuelve a la agenda) y reutiliza los patrones de formulario | **(a)**, aprobada (usuario): el alta y el detalle, en páginas |
| 11 | ¿La cola del día va en la F3? | (a) Sí, como la 3.7; (b) se deja para la FASE 4, junto con la consulta | **(a)**: es la pantalla del veterinario y de la recepción para fichar y marcar atendido, y la API ya está; la FASE 4 le suma "Abrir la consulta" | **(a)**, aprobada (usuario): la cola del día en la F3, como última entrega (3.7) |
| 12 | ¿Las agendas (con el horario y los bloqueos) se administran desde el frontend en la F3? | (a) Sí, en `/configuracion/agendas` (3.1a y 3.1b); (b) no, por ahora con Postman | **(a)**: sin ellas no hay agenda que mostrar en una clínica nueva | **(a)**, aprobada (usuario): las agendas se administran desde el frontend |
| 13 | ¿De dónde salen la agenda y el servicio de Playwright? (H6) | (a) Un ayudante de `e2e/support` que los crea por la API con el administrador antes de cada spec; (b) el `DemoSeeder` suma una agenda y un servicio (cambia el backend y los datos de demostración) | **(a)**: no toca el backend ni la demo, y cada spec arma lo que necesita (también un turno ya empezado para el ausente) | **(a)**, aprobada (usuario): el ayudante de `e2e/support` crea por la API, en `syncvet_e2e`, lo que cada spec necesita (agendas, servicios y turnos) y lo limpia; la demo no se toca |
| 14 | Las acciones que la API rechazaría por una regla que el navegador conoce (un turno ajeno para el veterinario, fichar otro día o sin paciente, ausente antes del inicio) | (a) Ocultas las de un turno ajeno; deshabilitadas, con el motivo, las de hora y de paciente; (b) todas visibles y que responda la API | **(a)**: el motivo a la vista evita el error; la API vuelve a controlar todo | **(a)**, aprobada (usuario), con una precisión: ocultas o deshabilitadas con el motivo y, además, el 403 y el 422 de la API tratados en el diálogo (la API vuelve a controlar todo) |
| 15 | ¿Se puede arrastrar un turno en la grilla para reprogramarlo? | (a) No en la F3: "Reprogramar" lleva al formulario; (b) arrastrar y soltar, además del formulario | **(a)**: arrastrar no es accesible con el teclado sin un mecanismo aparte, y el formulario ya resuelve agenda, día y hora; se puede sumar después | **(a)**, aprobada (usuario): sin arrastrar en la F3 |

## Criterio de cierre

* Las entregas aprobadas cerradas, con Vitest y Playwright en verde, `typecheck`, `lint` y `build` sin errores ni avisos.
* Por front-03: `composer test` y la suite del backend completa en MySQL y en serie, al cerrar la fase.
* Newman de **todas** las colecciones de `docs/05-pruebas/postman` desde la raíz (R-057), sin fallas.

## Resultado

### Entrega 3.1a

Estado: **COMPLETADO** el 2026-11-18 (R-086). Las agendas en `/configuracion/agendas` (listado, detalle, alta, edición con el
horario semanal, inactivar o activar y baja), solo para el administrador, en `feature/front-3-1a-agendas` (desde
`docs/front-f3-diseno` en `df2588a`), sin integrar a `main` (se integra al cerrar la F3). Commits: `549f1c6` (WIP de la
implementación, el 2026-11-17), `f0f463a` (WIP de la revisión, el 2026-11-18) y el de este cierre (R-086: la documentación). Sin
cambios en el backend ni paquetes nuevos.

**Hecho.** `features/schedules` (tipos, API, búsqueda, reglas del formulario, listado, detalle, formulario con el horario semanal,
inactivar o activar y baja), las cuatro rutas de `/configuracion/agendas` con `requirePermission` (`schedules.update`, `.store`;
la baja con `schedules.destroy`), la tarjeta "Agendas" en `/configuracion`, tres permisos en `PERMISSIONS`, el ayudante de
Playwright `e2e/support/api.ts` (sesión por la API en `syncvet_e2e` y limpieza de las agendas de un spec) y `e2e/agendas.spec.ts`;
`e2e/permisos.spec.ts` suma la tarjeta y el caso del recepcionista y el veterinario en `/configuracion/agendas` (R-084).

**Desvíos** (los 9, aceptados por el usuario; ninguno cambia el comportamiento del diseño):

| # | Desvío | Motivo |
|---|---|---|
| 1 | El error de un día del horario (tramos superpuestos, más de 6) va en el tramo que lo provoca (su "Desde"), no en una clave `hours` del día | Así el foco y el mensaje quedan en el tramo a corregir; la clave `hours` que mande la API (y `hours.N.weekday`) va a la alerta de arriba, porque no hay un campo del día |
| 2 | El color tiene una casilla "Con color en la agenda" | `<input type="color">` no puede quedar vacío; sin la casilla el color va en `null` |
| 3 | El veterinario se elige entre los activos de la clínica de un solo pedido (`GET veterinarians?per_page=100`, `['veterinarians', 'options']`), filtrados en el navegador por la sucursal elegida y el usuario activo; cambiar la sucursal suelta el veterinario que no trabaja en ella | El diseño decía `GET veterinarians?branch_id=`: así no hay un pedido por cada cambio de sucursal. Los servicios, igual (`['services', 'options']`). En la edición, el veterinario o el servicio que ya no está activo se muestra con "(actual)" |
| 4 | "Copiar el lunes a los días hábiles" queda deshabilitado si el lunes no tiene tramos | Copiar un lunes vacío borraría los tramos del martes al viernes sin aviso |
| 5 | La mutación 4 (no soltar el veterinario al cambiar de sucursal) pasó la primera vez: el test miraba el `<select>`, que se ve vacío sin la opción aunque el formulario conserve el valor | Se reforzó el test para mirar el cuerpo del `PATCH` (`veterinarian_id: null`); con eso la mutación falla |
| 6 | El control 2 en Edge encontró que "Sumar un tramo" no llevaba el foco al "Desde" del tramo nuevo | Corregido: el tramo se dibuja desde `fields` (antes, desde `useWatch`, un render más tarde) y el foco se mueve con `setFocus` después del render; test nuevo en `SchedulePages.test.tsx` |
| 7 | En la edición recién cargada, el veterinario se veía "(actual)" mientras la lista de veterinarios todavía no había llegado | Corregido: "(actual)" solo con la lista cargada. La captura `05-formulario-375.png` es de antes de la corrección |
| 8 | Durante los controles en Edge, el PHP de Laragon dejó de responder (`syncvet.test` daba 502 y no quedó ningún `php-cgi`) | No se tocó Laragon: los controles siguieron con un `php artisan serve` propio en `:8020` sobre la misma base de la demo y Vite en `:5180`, los dos detenidos por su PID. El usuario reinició Laragon; en la revisión `syncvet.test` respondió y los controles se repitieron contra él (abajo) |
| 9 | Playwright, la primera corrida de `agendas.spec.ts` falló por el script: `getByLabel('Sucursal')` también encontraba el selector "Sucursal actual" de la barra | `exact: true` en las etiquetas; 1 de 1 después |

**Mutaciones** (4 de 4 detectadas, revertidas): el horario vacío sin la clave `hours` (2 fallas); la superposición sin controlar en
el navegador (3 fallas); 23:55 rechazado (2 fallas); el veterinario sin soltar al cambiar de sucursal (1 falla, con el test del
desvío 5).

**En Edge, en la implementación** (sin ventana, `es-PY`, contra la demo con el backend propio del desvío 8; 8 de 8): (1) la tarjeta lleva al listado; (2)
"Sumar un tramo" con Enter deja el foco en "Desde" y las horas se escriben con el teclado; (2b) "Quitar" con Enter devuelve el foco
a "Sumar un tramo al lunes"; (3) el color deshabilitado sin la casilla, mandado `#E8590C` y mostrado en el detalle; (3b) "Copiar el
lunes": cinco tramos de lunes a viernes; (4) a 375 px, el detalle, el formulario y el listado sin desplazamiento horizontal; (5) la
agenda temporal ("Control Edge …") eliminada por la interfaz (0 en la base después); (6) la consola sin errores. Cada corrida
ingresó y salió como `admin-a` (sus tokens revocados; los ingresos quedan en la auditoría, como en la F2); `admin-a`, `recepcion-a`
y `vet-a` sin cambios. Capturas fuera del repositorio (scratchpad de la sesión, `capturas-3-1a`).

**Pruebas.** Vitest de lo tocado: 70 de 70 en `features/schedules` (3 archivos) y `vitest related` del permiso y la tarjeta, 600
de 600 en 28 archivos. Playwright: `agendas.spec.ts` 1 de 1 (repetido después de las correcciones de los desvíos 6 y 7, 1 de 1 en 1,1 min) y `permisos.spec.ts` 7 de 7. Corrida final, el 2026-11-17, desde `frontend/`: `npm test`, 1401 tests en 67 archivos, en verde; `typecheck`, `lint` y `build` sin errores ni avisos; desde `backend/`, `FictitiousDocumentsTest`, 3 de 3.

**Revisión** (2026-11-18, sobre el WIP `549f1c6`, contra `df2588a`, con el presupuesto por entrega). `syncvet.test` respondió después
de que el usuario reiniciara Laragon (la primera petición tardó 23 s; las siguientes, 0,2 s): el desvío 8 queda resuelto.

* **Paridad del horario con la API** (`ScheduleMessages::validateHours`): bien. El mismo orden (la forma de cada hora; si alguna
  falla, nada más; el fin antes del inicio o igual, en su tramo y fuera de su día; por día, más de 6 o, si no, la superposición
  ordenando por inicio y comparando como texto, con tocarse permitido); 23:55 válida; el horario se manda entero en el orden del
  formulario (`hours: []` al vaciarlo) y las claves con índice del 422 caen en su tramo. El total de 42 sale del tope de 6 por día.
* **Veterinario por sucursal**: los activos con el usuario activo de la sucursal elegida (la API devuelve solo los activos sin
  `include_inactive`); al cambiar de sucursal se suelta el que no trabaja en ella, y en la edición se conserva el actual si se vuelve a
  su sucursal. Con turnos, cambiar la sucursal manda `veterinarian_id: null` y la API responde `SCHEDULE_NOT_EDITABLE`, que va a la
  alerta de arriba (con test).
* **Inactivar, activar y baja**: solo `{ is_active }`, con el aviso de los turnos que siguen (la API inactiva aunque haya turnos
  futuros); la baja con `SCHEDULE_IN_USE` muestra el mensaje de la API y "Inactivar" (o solo "Cerrar" si ya está inactiva).
* **Color**: validado por la API (`#RRGGBB`), así que el `style` de la muestra no recibe otra cosa; sin la casilla va en `null`.
* **Permisos**: la tarjeta con `schedules.update` (y `/configuracion` ya pide `system-settings.update`: los otros roles no llegan);
  las cuatro rutas con `requirePermission`; "Nueva agenda" y "Eliminar" con `useCan()`.
* **Foco y teclado**: "Sumar un tramo" lleva el foco a "Desde"; "Quitar" lo devuelve a "Sumar un tramo" del día (con 6 tramos el botón
  sigue enfocable, `focusableWhenInactive`).

**Hallazgo y corrección** (1, menor): el test de permisos de `SchedulePages.test.tsx` recorría la lista, el detalle y el alta, pero
no `/configuracion/agendas/$id/editar`; se sumó (2 casos nuevos: el recepcionista y el veterinario). Sin cambios en `src/` fuera de
los tests: ni el menú ni el permiso de una ruta cambiaron, así que no corresponde Playwright.

**Mutaciones de la revisión** (3 de 3 detectadas, revertidas): tocarse contado como superposición (`<` → `<=`, 4 fallas);
`SCHEDULE_IN_USE` ofrece "Inactivar" aunque ya esté inactiva (1 falla); el color mandado sin la casilla (2 fallas).

**En Edge, contra `syncvet.test`** (los controles del desvío 8, repetidos; sin ventana, `es-PY`, con un Vite propio en `:5180`
detenido por su PID; 8 de 8): (1) la tarjeta lleva al listado; (2) "Sumar un tramo" con Enter deja el foco en "Desde" y las horas se
escriben con el teclado (08:00 a 12:00 y 12:00 a 16:00); (2b) "Quitar" con Enter devuelve el foco a "Sumar un tramo al lunes"; (3) el
color deshabilitado sin la casilla, mandado `#E8590C` y mostrado en el detalle; (3b) "Copiar el lunes": dos tramos de lunes a viernes,
el sábado y el domingo sin tramos; (4) a 375 px, el detalle, el formulario y el listado sin desplazamiento horizontal, y la edición
con "Víctor Acosta" sin "(actual)"; (5) la agenda temporal eliminada por la interfaz (0 en la base); (6) la consola sin errores. La
captura `05-formulario-375.png` se rehízo ya sin "(actual)" (fuera del repositorio, en el scratchpad de la sesión, `capturas-3-1a`).
Nota del script, no de la interfaz: en Edge con `es-PY`, el campo de hora tiene el segmento a. m./p. m. y Tab lo recorre antes de
pasar al campo siguiente. Ingresó y salió `admin-a` (el token revocado; el ingreso queda en la auditoría); `admin-a`, `recepcion-a`
y `vet-a` sin cambios; ninguna agenda "Control Edge" en la demo.

**Corrida final de la revisión** (2026-11-18, desde `frontend/`): `typecheck`, `lint` y `build` sin errores ni avisos; `npm test`,
la primera vez 1 falla en `ClientDetailPage.test.tsx` ("una persona: sus datos…", ajena a la 3.1a; pasó sola), y repetida la suite
completa: 1403 tests en 67 archivos, en verde. Desde `backend/`, `FictitiousDocumentsTest`, 3 de 3.

**Validación.** El usuario aceptó los desvíos y la revisión con su corrección, y pidió el cierre. Claude Code: Edge sin ventana, 8 de 8
en la implementación (con el backend propio del desvío 8) y 8 de 8 repetidos contra `syncvet.test` en la revisión; Playwright,
`e2e/agendas.spec.ts` 1 de 1 y `e2e/permisos.spec.ts` 7 de 7 (en la implementación; la revisión solo tocó un test de Vitest, sin
Playwright); la corrida completa de Playwright, al cerrar la F3.

Corrida de `npm test` del frontend (2026-11-18): 1403 tests en 67 archivos, en verde.

### Entrega 3.1b

Estado: **COMPLETADO** el 2026-11-18 (R-087). Los bloqueos de una agenda (listar, crear con los turnos que quedan adentro y
eliminar), en la tarjeta "Bloqueos" del detalle de la agenda, en `feature/front-3-1b-bloqueos` (desde `913f1d3`, el cierre de la
3.1a), sin integrar a `main` (se integra al cerrar la F3). Commits: `2c30241` (WIP de la implementación), `cbb4663` (WIP de la
revisión) y el de este cierre (R-087: la documentación), todos el 2026-11-18. Sin cambios en el backend ni paquetes nuevos.

**Hecho.** `features/schedules/blocks.ts` (el formulario del alta en Asunción con `TZDate` y `toApiDateTime`, "Todo el día", las
reglas de `StoreScheduleBlockRequest` con sus mensajes y el período de un bloqueo para mostrar) con `blocks.test.ts`;
`ScheduleBlocks.tsx` (la tarjeta "Bloqueos" del detalle con `schedule-blocks.index`, paginada, sin `from` ni `to`; "Nuevo bloqueo"
con `.store` y "Eliminar" con `.destroy`, en `ConfirmDialog` con `useSingleAction` y `dialogError`) con `ScheduleBlocks.test.tsx`;
en `api.ts`, la consulta (`['schedules', id, 'blocks', { from, to, page }]`), el alta, la baja y `afterBlocksChanged` (los bloqueos y
la disponibilidad de la agenda, `['schedules', id]`, y el detalle marcado viejo sin otro pedido); tres permisos en `PERMISSIONS`; el
ayudante de Playwright `createScheduleFor` (la agenda por la API en `syncvet_e2e`) y un caso nuevo en `e2e/agendas.spec.ts`. Sin
rutas nuevas, sin cambios en el menú, en el backend ni en los paquetes.

**Desvíos** (los 11, aceptados por el usuario en la revisión; ninguno cambia el comportamiento del diseño):

| # | Desvío | Motivo |
|---|---|---|
| 1 | Los turnos que quedan adentro se muestran **después** del 201, no antes de confirmar | Es lo que hace la API (pregunta 22 del backend): crea el bloqueo aunque haya turnos, no lo rechaza ni pide confirmación, y los devuelve en `affected_appointments`. No hay una vista previa en la API (armarla con `GET appointments` no sirve: ese listado llega hasta 31 días y un bloqueo, hasta 366). Antes de confirmar, el diálogo avisa: "Si ya hay turnos en ese horario, el bloqueo se crea igual y te mostramos cuáles quedaron adentro…"; con el 201, el diálogo pasa a la lista (día y hora en Asunción, paciente con su dueño, cliente o contacto con su teléfono, y el estado) con solo "Cerrar" |
| 2 | Sin el enlace a cada turno de la lista | `/turnos/$id` llega en la 3.4: se suma ahí (el diseño ya lo dice) |
| 3 | La validación del navegador es una función pura (`blockFormErrors`), no Zod | El diálogo no usa React Hook Form, como los demás diálogos con campos (depósitos y fallecimiento, `deceased.ts`): las mismas reglas y mensajes de la API, con sus tests en los bordes |
| 4 | A la API, `starts_at` y `ends_at` van con `-03:00` (`toApiDateTime`), no en UTC con `Z` | Lo dice el diseño y la convención de `frontend/CLAUDE.md` §5; la API acepta los dos y responde en UTC con `Z`, que se muestra en Asunción |
| 5 | La API no tiene un 409 propio de los bloqueos (ni `error_code` de negocio) | Los tests prueban el 409 `CONFLICT` genérico (el aviso de la F0 y el formulario intacto para probar de nuevo) y el 404 de la baja (otra pestaña ya lo eliminó: aviso y la lista de nuevo). El 404 del alta (la agenda eliminada) va al listado con `useScheduleGone` |
| 6 | El recepcionista y el veterinario se prueban con la tarjeta sola | La ruta del detalle es del administrador (`schedules.update`, 3.1a): hoy solo él ve la tarjeta. El test monta la tarjeta con los permisos reales de los otros roles (lista sin "Nuevo bloqueo" ni "Eliminar") y comprueba que la ruta los lleva a `/sin-permiso` |
| 7 | Los errores del 422 de la API van a la **fecha** ("Fecha desde", "Fecha hasta"); los del navegador, a la hora si el problema es la hora | La API manda una sola clave por instante (`starts_at`, `ends_at`); el navegador sabe qué parte está mal (fuera de la grilla, a la hora; el fin antes del inicio el mismo día, a "Hora hasta"; en otro día o más de 366 días, a "Fecha hasta") |
| 8 | "Todo el día" en un día que empezó con el cambio de horario (01/10/2023) empieza a la 01:00 y en la tabla se muestra con la hora, no como "todo el día" | Las 00:00 no existieron ese día (`TZDate` da la 01:00 `-03:00`, el comienzo real del día; el día dura 23 horas, con test). Solo fechas anteriores al 15/10/2024 |
| 9 | Se corrió `e2e/permisos.spec.ts` (7 de 7) aunque no cambian el menú ni el permiso de una ruta | `PERMISSIONS` suma tres claves (R-084 lo nombra) |
| 10 | Playwright, la primera corrida de `agendas.spec.ts`: 2 fallas | El caso nuevo, por el ayudante: el nombre del veterinario está en `user.name`, no en `name` (corregido). El de la 3.1a falló en la misma corrida y su error quedó fuera de la salida recortada: no se determinó la causa; sin cambios en ese caso, la corrida siguiente dio 2 de 2 |
| 11 | En Edge, la primera corrida del script dio 7 de 8: el control 7 falló por el script (`getByRole` encontraba también el "Eliminar" de la fila, cuyo nombre accesible empieza igual) | `exact: true`; repetido el script entero, 8 de 8. Por eso `admin-a` ingresó dos veces (dos ingresos en la auditoría) |

**Mutaciones** (4 de 4 detectadas, revertidas): la fecha armada con la zona de la PC (`new Date(...).toISOString()`) en lugar de
`toApiDateTime` (7 fallas); "Todo el día" hasta las 00:00 de la fecha hasta, sin el día siguiente (6 fallas); los turnos adentro sin
mostrar (el diálogo se cierra siempre, 1 falla); el límite de 366 días rechazado en el borde (`>=`, 2 fallas).

**En Edge** (sin ventana, `es-PY`, zona `America/Asuncion`, contra `syncvet.test` con un Vite propio en `:5180`, detenido por su PID;
8 de 8; la agenda temporal "Control Edge 3.1b …" creada y eliminada por la API, con sus bloqueos: 0 en la demo después): (1) "Nuevo
bloqueo" con Enter abre con el foco en "Cancelar" y Escape lo cierra y devuelve el foco al botón; (2) la fecha escrita con el teclado
en el selector nativo (dd/mm/aaaa) queda `yyyy-MM-dd` y Tab no sale del diálogo; (3) 09:03 da el mensaje de la grilla y "hasta" antes
de "desde", el del fin, sin pedido; (4) "Todo el día" con la barra espaciadora oculta las horas y manda de las 00:00 `-03:00` a las
00:00 del día siguiente a la fecha hasta, y la tabla dice "Del … al …, todo el día"; (5) la API lo guardó en UTC (03:00 `Z`); (6) a
375 px, el detalle y el diálogo sin desplazamiento horizontal; (7) eliminar con el teclado (el foco empieza en "Cancelar"); (8) la
consola sin errores. Ingresó y salió `admin-a` (tokens revocados); `admin-a`, `recepcion-a` y `vet-a` sin cambios. Capturas fuera del
repositorio (scratchpad de la sesión, `capturas-3-1b`): `01-validacion-1280.png`, `02-todo-el-dia-1280.png`,
`03-detalle-con-bloqueo-1280.png`, `04-detalle-375.png` y `05-dialogo-375.png`.

**Pruebas.** Durante el trabajo, `vitest related` de lo tocado: 627 de 627 en 29 archivos; `blocks.test.ts` 22 y
`ScheduleBlocks.test.tsx` 12. Playwright: `e2e/agendas.spec.ts` 2 de 2 (en la segunda corrida, desvío 10) y `e2e/permisos.spec.ts`
7 de 7. Corrida final (2026-11-18, desde `frontend/`): Prettier de los archivos cambiados, `typecheck`, `lint` y `build` sin errores ni
avisos; `npm test`, 1437 tests en 69 archivos, en verde, a la primera. Desde `backend/`, `FictitiousDocumentsTest`, 3 de 3.

**Para la 3.4:** el enlace a cada turno de la lista de afectados y el caso de Playwright con un turno adentro (con turnos desde la
interfaz).

**Revisión (2026-11-18, sobre `2c30241`, con el diff desde `913f1d3`).** `syncvet.test` respondía (200 en `/` y `/up`).
Decisiones del usuario: **los 11 desvíos, aceptados**; los turnos adentro, como la API (el bloqueo se crea igual y se muestran
después), con la marca "Dentro de un bloqueo" en la grilla y en la lista registrada en la [3.2](#32-la-agenda-del-día) y la
[3.3](#33-la-semana-y-la-lista-de-turnos); la consulta previa de los turnos que quedarían adentro, como mejora del backend no
urgente ([deuda 25](../../deuda-tecnica.md)).

* **Revisado, sin hallazgos.** Las fechas: el alta arma la hora de pared de Asunción con `TZDate` y la manda con `toApiDateTime`
  (`-03:00`); lo recibido (`Z`) se muestra con `inAppZone`; "Todo el día" va hasta las 00:00 del día siguiente a la fecha hasta (el
  día 32 lo normaliza `TZDate`, también el 31/12), con los días de 23 y 25 horas y el cruce de medianoche probados con la PC en UTC.
  La paridad con `StoreScheduleBlockRequest`: múltiplos de 5 en la hora de Asunción, el fin estrictamente después del inicio
  (`<=` como `lessThanOrEqualTo`), 366 días de calendario sumados en Asunción (`addDays` sobre `TZDate`, como `addDays` de Carbon
  en la hora local; el borde pasa y 5 minutos más no), el motivo sin los espacios de los extremos (como `TrimStrings`) y contado
  en caracteres (`Array.from`, como `mb_strlen`), sin `maxLength` nativo; los mensajes, los de la API. El aviso previo y la lista
  posterior de los afectados (día, hora, quién y estado en Asunción, solo "Cerrar"). El 409 `CONFLICT` en el diálogo con el
  formulario intacto, los 422 a sus campos (y las claves desconocidas a la alerta), el 404 del alta (al listado,
  `useScheduleGone`) y el de la baja (aviso y la lista de nuevo). Los permisos: `schedule-blocks.index`, `.store` y `.destroy`, los
  nombres de `ScheduleBlockController` y del `RolesAndPermissionsSeeder` (el recepcionista y el veterinario, solo `.index`); con
  los permisos reales de los dos roles, la tarjeta sin "Nuevo bloqueo" ni "Eliminar". 375 px: el control 6 de Edge de la
  implementación (sin cambios de pantalla en la revisión, no se repitió).
* **Hallazgo 1 (menor, corregido): la página de bloqueos que se queda vacía.** La paginación se pasaba solo con
  `last_page > 1`: al eliminar el único bloqueo de la página 2, la respuesta daba `last_page` 1 y la tabla decía "La agenda no
  tiene bloqueos desde hoy." aunque la 1 tuviera bloqueos (lo mismo si los de esa página terminaban u otra pestaña los
  eliminaba). Ahora, al eliminar el único de una página que no es la primera, vuelve a la anterior (`onDeleted` de
  `DeleteBlockDialog`), y una página fuera de rango muestra "Esta página no existe." con "Ir a la página 1", como los listados.
  Dos tests nuevos en `ScheduleBlocks.test.tsx`. Sin cambios en la API, los permisos ni los textos de lo que ya andaba.
* **Hallazgo 2, el test frágil (desvío 10): la causa, el límite de 10 s sobre un pedido lento del backend (corregido).** Antes de
  tocar nada, `e2e/agendas.spec.ts` 3 veces seguidas: 2 de 2 en cada una. Se descartaron, con 0 fallas en 27 intentos: los datos
  compartidos (la búsqueda del caso de la 3.1a es el nombre completo y el caso nuevo corre después, en serie, sin crear nada
  antes de fallar en su ayudante), el checkbox "Incluir las inactivas" controlado por la URL (con un spec temporal, 0 de 10
  desmarcados justo después del clic y 0 de 10 fallas de `check()`), el aviso antes de recargar en la edición (la URL ya es la del
  detalle cuando se ve el aviso, 10 de 10), la CPU del navegador frenada ×3 (4 de 4) y una optimización nueva de dependencias de
  Vite (no hubo). Con el código final, el spec completo falló 2 de 3 veces en el caso de la 3.1a (solo, 3 de 3 en verde) y 3 de
  13 corridas en total, siempre en las lentas (2,4 a 2,7 min contra 1,1). Con el tiempo de cada pedido a la API registrado
  (`requestfinished`, un gancho temporal), la falla quedó a la vista: `expect(getByText('Agenda eliminada
  correctamente.')).toBeVisible()` con el límite por defecto de 10 s y `DELETE /api/schedules/1` de **10.803 ms** (la misma baja,
  1,1 a 1,6 s en las corridas en verde; los demás pedidos, de 0,3 a 3,7 s). El aviso llegaba después del límite (en la captura
  está). No es la entrega: es el backend de Playwright (`php artisan serve` en Windows), que a veces tarda más de 10 s en un pedido.
  **Corrección:** los resultados de las acciones de los dos casos (los cinco avisos y la marca "Inactivada") esperan hasta 30 s
  (`SAVED`), con el motivo en el spec; el aviso dura 6 s desde que aparece, así que esperar más no lo pierde. Después, 3 corridas
  seguidas del spec completo: 2 de 2 en cada una. Los specs temporales y el gancho se borraron.

**Mutaciones de la revisión** (4 de 4 detectadas, revertidas; solo los tests del archivo): el motivo contado con `.length` (un
emoji es 2, `blocks.test.ts`, 1 falla); "Nuevo bloqueo" sin `schedule-blocks.store` (`ScheduleBlocks.test.tsx`, 2 fallas: el
recepcionista y el veterinario); `ends_at` del 422 a "Fecha desde" (1 falla); el hallazgo 1 sin volver a la página anterior (1
falla).

**Pruebas de la revisión.** `vitest related` de `ScheduleBlocks.tsx`: 571 de 571 en 27 archivos. Corrida final (2026-11-18, desde
`frontend/`): Prettier de los archivos cambiados, `typecheck`, `lint` y `build` sin errores ni avisos; `npm test`, 1439 tests en 69
archivos, en verde, a la primera (2 más que la implementación: los del hallazgo 1). Desde `backend/`, `FictitiousDocumentsTest`,
3 de 3. Playwright: `e2e/agendas.spec.ts` con la corrección, 3 corridas seguidas, 2 de 2 en cada una (y `eslint` y `tsc` del spec).
No se tocó la demo (`syncvet.test`, solo para comprobar que respondía): Playwright usa `syncvet_e2e`; `admin-a`, `recepcion-a` y
`vet-a`, sin cambios. Los servidores de Playwright los levanta y los detiene Playwright (al final, ningún puerto escuchando en
`:8010` ni `:5174`).

**Validación.** El usuario aceptó los 11 desvíos, decidió los turnos adentro como la API (con la marca de la 3.2 y la 3.3 y la
deuda 25), pidió la revisión y el cierre. Claude Code: Edge sin ventana, 8 de 8 contra `syncvet.test` en la implementación (la
revisión no cambió la pantalla salvo la página fuera de rango, cubierta por Vitest); Playwright, `e2e/agendas.spec.ts` 2 de 2 en
3 corridas seguidas con la corrección del hallazgo 2 y `e2e/permisos.spec.ts` 7 de 7 (en la implementación); la corrida completa
de Playwright, al cerrar la F3.

Corrida de `npm test` del frontend (2026-11-18): 1439 tests en 69 archivos, en verde.

### Entrega front-03

Estado: **COMPLETADO** el 2026-11-18 (R-088). Soporte del backend pedido por la F3 del frontend ([pregunta 1](#preguntas-del-diseño)):
`per_page` de 1 a 500 en `GET appointments`, en `feature/front-03-turnos-por-pagina` (desde `c71e71d`, el cierre de la 3.1b), sin
integrar a `main` (se integra al cerrar la F3). Commits: `60b3615` (WIP de la implementación), `4a05e9c` (WIP de la revisión, con
`XDEBUG_MODE: 'off'` en Playwright) y el de este cierre (R-088: la documentación), todos el 2026-11-18. Sin rutas, permisos,
migraciones ni `error_code` nuevos (78, 135, 36); sin paquetes nuevos.

**Hecho.** `IndexAppointmentRequest`: `per_page` de 1 a 500 (`MAX_PER_PAGE`), con su mensaje propio para el entero, el mínimo y el
máximo (`PER_PAGE_OUT_OF_RANGE`, "Pedí de 1 a 500 turnos por página.", uno solo aunque fallen dos reglas); `ListFilters` no cambia
(los demás listados, hasta 100; 10 por defecto en todos). La respuesta es la misma (`meta.pagination` en `snake_case`, con
`total`). El `with` del listado ya traía las seis relaciones de `AppointmentResource` (desde la 3.2a): no hubo que cambiar el
controlador. Sin rutas, permisos, migraciones ni `error_code` nuevos (78, 135, 36).

**Pruebas.** `tests/Feature/Appointments/AppointmentPerPageTest.php` (nuevo, 18 casos): 10, 100 y 500 pasan; 501, 0, -1, `abc` y
`10.5` → 422 con el mensaje (un solo mensaje en `errors.per_page`); los otros diez listados con `ListFilters` (clientes, pacientes,
sucursales, depósitos, usuarios, veterinarios, servicios, agendas, bloqueos y auditoría) aceptan 100 y rechazan 101 y 500; el
`total` con más turnos que los pedidos; y la cantidad de consultas con 1 turno y con 300 (tres agendas, con y sin veterinario o
servicio; con paciente, solo con cliente y con un contacto): **9 y 9**, después de un pedido que llena la caché de permisos (como
front-02). Durante el trabajo: `AppointmentPerPageTest` 18 de 18; `AppointmentIndexTest`, `ValidationTranslationsTest`,
`VoseoTest`, `ListFiltersTest` y `PaginationMetaTest`, 47 de 47. Corrida final: `composer test` (SQLite, en serie),
1485 casos, 1469 pasan y 16 omitidos (solo de MySQL), 14416 aserciones, en 5 min 56 s (frente al cierre de la F2, 18 casos más: los de `AppointmentPerPageTest`). `FictitiousDocumentsTest`, 3 de 3 (también por la colección de Postman). MySQL no se corrió: la entrega no tiene migraciones, concurrencia ni búsqueda de texto.

**Mutaciones** (3 de 3 detectadas, revertidas): el tope en 501 (1 falla: el caso 501); `ListFilters` con 500 para todos los
listados (10 fallas, una por listado); sin `veterinarian.user` ni `patient` en el `with` (de 9 a 506 consultas con 300 turnos).

**EXPLAIN en MySQL 8.4** (la consulta que arma Laravel: `clinic_id` del scope, `branch_id`, `schedule_id` y el rango de
`starts_at` de una semana, `order by starts_at, id limit 500`, y su `count(*)`). En una base temporal propia
(`syncvet_explain_tmp`, `CREATE TABLE LIKE` de `appointments`, con sus índices, y 60.000 turnos de 3 clínicas, 12 sucursales y 48
agendas en 26 días; `ANALYZE TABLE`; borrada después) y en la demo (193 turnos): con `schedule_id`, `range` sobre
`appointments_schedule_id_starts_at_ends_at_index` (336 filas estimadas, con `filesort` de esas filas por el `id` del orden); sin
`schedule_id`, `range` sobre `appointments_branch_id_starts_at_index` (1344 filas, sin `filesort`; `EXPLAIN ANALYZE`, 5,7 ms para
500 filas). Los dos `count(*)`, los mismos índices. **No falta ningún índice: sin migración.**

**Postman.** Colección de turnos: "22a" (`per_page=500`, 200 con los cuatro turnos y `per_page` 500), "22b" (501) y "22c" (0),
422 con el mensaje. Newman, solo esa colección, servidor propio en el puerto 8001 (`XDEBUG_MODE=off php artisan serve`, detenido
por su PID): **61 pedidos y 113 aserciones, sin fallas** (antes, 58 y 107). La guía, con las cifras y los casos.

**El pedido lento de la 3.1b (hallazgo 2 de su revisión): `DELETE /api/schedules/{id}`, 10.803 ms en `e2e/agendas.spec.ts`.**
Medido sin Playwright con un script de Node (en el scratchpad de la sesión, fuera del repositorio): entra con `admin-a`, arma la
agenda como el caso de la 3.1a (dos tramos del lunes, editada a un tramo y el miércoles, inactivada), la elimina y sale; pedidos en
serie, salvo donde se dice. Milisegundos, mediana (máximo):

| Servidor | Baja sola | Baja con 3 `GET` en paralelo | Los demás pedidos |
|---|---|---|---|
| `syncvet.test` (Laragon, Xdebug en `debug` con `start_with_request=trigger`), 5 vueltas | 300 (357) | — | 155 a 523 |
| `php artisan serve` (como Playwright) con Xdebug, 5 y 3 vueltas | 343 (403) | 1162 (1184) | 179 a 491 |
| `php artisan serve` con `XDEBUG_MODE=off` (solo ese proceso), 5 y 3 vueltas | 122 (132) | 575 (658) | 63 a 281 |

**Causa: no es el backend.** La baja hace poco (bloquea la fila, `exists` de turnos, borra 2 tramos y los bloqueos uno por uno con
su auditoría) y tarda 0,1 a 0,4 s. Lo que la estira es el entorno de Playwright: (1) `php artisan serve` en Windows es **un solo
proceso** (`php -S`, sin `PHP_CLI_SERVER_WORKERS`): los pedidos que la pantalla dispara a la vez hacen cola, y la baja con 3 `GET`
delante tarda 3,4 veces más; (2) el `webServer` de Playwright levanta PHP **con Xdebug cargado** (`debug`), unas 2,5 a 3 veces más
lento que sin él; (3) con Playwright, Edge y Vite en la misma PC, todo es más lento (en las corridas de la 3.1b los demás pedidos
tardaban de 0,3 a 3,7 s, y la misma baja, 1,1 a 1,6 s en las verdes). Los 10,8 s no se reprodujeron fuera de Playwright. **Sin
corrección en el backend** (no hay nada lento en la baja). **Para decidir (usuario):** sumar `XDEBUG_MODE: 'off'` al `env` del
`webServer` del backend en `frontend/playwright.config.ts` (una línea del frontend, fuera de esta entrega; bajaría todos los pedidos
de Playwright a menos de la mitad). Los 30 s de `SAVED` de la 3.1b quedan igual.

**Demo.** La medición creó y eliminó sus agendas por la API (0 "Medición baja…" después) y revocó sus tokens (6 ingresos y 6
salidas de `admin-a` en la auditoría, como en la 3.1b); Newman dejó lo que deja cada corrida de la colección de turnos (sus turnos,
clientes y paciente, guía de Postman) y sus 3 tokens se borraron. `admin-a`, `recepcion-a` y `vet-a`, sin cambios (`updated_at`
del 2026-11-12). Ningún servidor propio quedó escuchando (8001 y 8021 libres).

**Desvíos.**

| # | Desvío | Motivo |
|---|---|---|
| 1 | El mensaje de `per_page` es propio del listado de turnos ("Pedí de 1 a 500 turnos por página."), no el genérico de `lang/es/validation.php` que usan los demás listados ("El campo resultados por página no puede ser mayor que 100.") | El diseño dice "con su mensaje" y el pedido, en voseo; uno solo para las tres reglas (`integer`, `min`, `max`), así `abc` no da dos mensajes |
| 2 | No se cambió `AppointmentController`: el `with` ya traía las relaciones del Resource | La carga anticipada es de la 3.2a; el test nuevo la fija (mutación 3) |
| 3 | El EXPLAIN se hizo en una base temporal con datos sintéticos, además de la demo | Con 193 turnos el optimizador elige cualquier plan; la base se creó con `CREATE TABLE LIKE` (los índices de `appointments`, sin las claves foráneas) y se borró |
| 4 | El pedido lento no se corrigió | La causa es el servidor de Playwright, no el código de la baja (arriba); la línea de `playwright.config.ts` queda para decidir |
| 5 | Las mediciones con `php artisan serve` fueron contra la demo (`syncvet`), no contra `syncvet_e2e` | Para medir el mismo código y los mismos datos que `syncvet.test` cambiando solo el servidor y Xdebug |

**Decisiones del usuario (2026-11-18).** Se aceptan los 5 desvíos. Sí a `XDEBUG_MODE: 'off'` en el `env` del `webServer` del
backend en `frontend/playwright.config.ts` (hecho en la revisión, abajo); los 30 s de `SAVED` de la 3.1b quedan como están;
Playwright completo, al cerrar la F3.

**Revisión (2026-11-18)**, sobre el diff `c71e71d..60b3615`, por un revisor que no la escribió:

* **`IndexAppointmentRequest`.** La regla de `per_page` pisa la de `ListFilters` (va después del `...`) y el controlador pagina con
  `$request->integer('per_page')`, la misma entrada que valida el Request: no hay otra vía para pasar más de 500 (el cuerpo de un
  `GET` también pasa por la validación; un arreglo, `per_page[]=600`, falla en `integer`). Bordes probados con el validador:
  `600.5`, `-0.5`, `500.0`, `1e2`, `5e2` y `0500` → 422 con **un solo** mensaje (Laravel deja de revisar `min` y `max` cuando falla
  `integer`); `+500` y ` 500` pasan y dan 500 (inofensivo). El mensaje está en voseo y pasa `VoseoTest`. Ningún otro listado usa
  `IndexAppointmentRequest`.
* **`ListFilters` y los otros 10 listados**, sin cambios en el diff; los 10 controladores siguen paginando con
  `ListFilters::DEFAULT_PER_PAGE`, y `test_the_other_lists_still_stop_at_100` cubre los 10.
* **`AppointmentPerPageTest`.** El conteo falla de verdad si vuelve el N+1: el turno único tiene paciente, cliente, servicio y una
  agenda con veterinario (las seis relaciones con clave, así cada una hace su consulta también con 1 turno) y el pedido previo
  llena la caché de permisos. No depende del orden (300 turnos con `per_page=500` entran en una página) ni de la caché (cada
  `countQueries` registra su propio `DB::listen`). Sin cambios.
* **Postman, "22a" a "22c".** Filtran por la agenda de la corrida (`schedule_id`), así el `total: 4` de "22a" vale también en una
  segunda corrida sin recrear la base; "22b" y "22c" comparan el mensaje exacto. Sin cambios.
* **Documentación**: completa (`backend/CLAUDE.md` §3 y §6.10, la guía de Postman y esta sección). **Hallazgo 1 (menor, corregido):**
  la línea nueva de §6.10 de `backend/CLAUDE.md` pasaba de 130 caracteres; se partió.
* **Mutaciones** (2 de 2 detectadas, revertidas): `per_page` con `numeric` en lugar de `integer` (2 fallas: `10.5` → 200 y `abc`
  con el mensaje genérico); sin `client` en el `with` (de 9 a 208 consultas con 300 turnos).
* **Tests relacionados** (sin Xdebug): `AppointmentPerPageTest`, `AppointmentIndexTest`, `ListFiltersTest`, `VoseoTest` y
  `ValidationTranslationsTest`, 54 de 54 (3399 aserciones). No se tocó código del backend (solo el `CLAUDE.md`): sin `composer
  test` ni Newman en la revisión; valen los de la implementación (1485 casos; 61 pedidos y 113 aserciones).

**`XDEBUG_MODE: 'off'` en Playwright** (`frontend/playwright.config.ts`, con su comentario; `frontend/CLAUDE.md` §8). Con
`--no-reload`, `php artisan serve` pasa el entorno entero a `php -S` (`ServeCommand::startProcess`), así que la variable llega al
servidor. Medición con `npx playwright test e2e/agendas.spec.ts e2e/permisos.spec.ts` (9 casos, todos en verde), dos corridas de
cada lado, alternadas (antes, después, después, antes):

| | Corrida 1 | Corrida 2 | Promedio | `agendas.spec.ts` (promedio) | `permisos.spec.ts` (promedio) |
|---|---|---|---|---|---|
| Antes (con Xdebug) | 101 s | 99 s | 100 s | 37,6 s (39,4 y 35,8) | 39,8 s (40,3 y 39,3) |
| Después (`XDEBUG_MODE=off`) | 102 s | 97 s | 99,5 s | 33,8 s (33,1 y 34,4) | 40,3 s (42,6 y 38,0) |

La mejora en la corrida es chica: `agendas.spec.ts` baja un 10 % (el caso de los bloqueos, de 19,2 y 13,8 s a 11,9 y 12,6 s) y
`permisos.spec.ts` no cambia (casi todo es esperar la interfaz). El total incluye la preparación de `syncvet_e2e` y el arranque de
Vite, que no dependen de Xdebug. La línea queda: no cuesta nada y saca la variable más lenta de los pedidos. `typecheck`, ESLint y
Prettier del archivo, sin errores.

**Demo.** La revisión no la tocó (Playwright usa `syncvet_e2e`); `admin-a`, `recepcion-a` y `vet-a`, con su `updated_at` del
2026-11-12. Los servidores de Playwright (`:8010` y `:5174`) los levantó y los cerró Playwright; ninguno quedó escuchando.

**Validación.** El usuario aceptó los 5 desvíos, decidió `XDEBUG_MODE: 'off'` en Playwright, pidió la revisión y el cierre. Corrida
del cierre (2026-11-18, desde `frontend/`, sobre el código de `4a05e9c`): `typecheck`, `lint` y `build` sin errores ni avisos;
`npm test`, 1439 tests en 69 archivos, en verde, a la primera. El código del backend no cambió después de la corrida final de la
implementación (`composer test` en serie, 1485 casos, 1469 pasan, 16 omitidos, 14416 aserciones; Newman de turnos, 61 pedidos y 113
aserciones, sin fallas): en la revisión, solo su `CLAUDE.md`.

Corrida de `composer test` del backend (2026-11-18): 1485 casos, 1469 pasan y 16 omitidos, 14416 aserciones, en verde.

Corrida de `npm test` del frontend (2026-11-18): 1439 tests en 69 archivos, en verde.

### Entrega 3.2

Estado: **COMPLETADO** el 2026-11-18 (R-089). La agenda del día, de solo lectura, en `feature/front-3-2-agenda-dia` (desde
`d30e578`, el cierre de front-03), sin integrar a `main` (se integra al cerrar la F3). Commits: `cdc184a` (WIP de la
implementación), `91ee463` (WIP de la revisión) y el de este cierre (R-089: la documentación), todos el 2026-11-18. Una ruta nueva
(`/agenda`), un permiso nuevo en `PERMISSIONS` (`appointments.index`, de la API) y "Agenda" en el menú; sin cambios en el backend
ni paquetes nuevos.

Implementación del 2026-11-18 en `feature/front-3-2-agenda-dia` (desde `d30e578`). Sin cambios en el backend ni
paquetes nuevos. El diseño coincide con la API real (`AppointmentResource`, `GET appointments` con `per_page` hasta 500,
`GET schedules/{id}/blocks` con `from`/`to` por superposición, `me.veterinarian.id`): no hubo que frenar.

**Hecho.** `/agenda` (`appointments.index`, los tres roles) y "Agenda" en el menú, después de "Pacientes"
(`PERMISSIONS.appointmentsIndex`). `features/appointments/`: `dates.ts` (el día, sumar días con `addDays` sobre `TZDate` al
mediodía, la semana desde el lunes, el comienzo real del día, los días de 23 y 25 horas, la hora de pared), `grid.ts` (puro: las
columnas, el rango de horas, lo sombreado fuera del horario y por los bloqueos, la fila, el alto y el carril de cada turno, la marca
"Dentro de un bloqueo" con la regla de la API y los estados que ocupan el horario, los filtros y el nombre accesible), `search.ts`
(`validateSearch`), `api.ts` (un pedido del día con `per_page=500` y `branchKey`, las agendas de la sucursal, los bloqueos del día;
`staleTime` de 30 s, cada 60 s sin correr en segundo plano y `keepPreviousData`), `AgendaGrid.tsx` (el patrón *grid* con un solo
punto de tabulación) y `AgendaDayPage.tsx` (la barra, los filtros, el aviso de los turnos que faltan, el anuncio en
`role="status"`, el panel de solo lectura y una agenda por vez por debajo de 768 px). `fecha` en `TEXT_SEARCH_PARAMS`. La pausa de
la actualización, común: `src/lib/hooks/auto-refresh.ts` (`usePauseAutoRefresh`, que ya usa `ConfirmDialog`, y la pestaña oculta).
Playwright: `e2e/agenda-dia.spec.ts` (2 casos) con los ayudantes `createAgendaDay`, `retireAgendasNamed` y `asuncionDay` en
`e2e/support/api.ts`; `e2e/permisos.spec.ts`, con "Agenda" en el menú de los tres roles.

**Desvíos y decisiones para revisar:**

| # | Desvío | Motivo |
|---|---|---|
| 1 | El spec es `e2e/agenda-dia.spec.ts`, no `e2e/agenda.spec.ts` | Lo pidió así el usuario en el pedido de la entrega |
| 2 | La fecha va **siempre** en la URL, también la de hoy ("Hoy" escribe `fecha=<hoy>`); sin `fecha`, la página muestra hoy | Lo pide el pedido ("con la fecha en la URL como texto"). La convención de los listados deja fuera los valores por defecto, pero "hoy" cambia a la medianoche: un enlace copiado mostraría otro día. El test lo encontró: el día siguiente era el de hoy (18/11) y la fecha desaparecía de la URL |
| 3 | `todos=1` en la URL para "Ver toda la sucursal" (la URL del diseño no lo nombra) | Es el nombre que el diseño ya da en la cola (`/agenda/cola?todos=`), con el mismo valor |
| 4 | Además de las agendas activas, se suman al final, marcadas "(inactivada)", las agendas que no están entre ellas pero tienen turnos **visibles** ese día (una con solo cancelados, solo con "Mostrar los cancelados") | El diseño habla de "una columna por agenda activa"; una agenda inactivada conserva sus turnos (pregunta 24 del backend) y se perderían de la grilla sin aviso (decisión 1: nunca cortar en silencio). Con test |
| 5 | El día de 25 horas es el **23/03/2024**, no el 24/03 (H9) | `TZDate`: el atraso fue a las 00:00 del 24 y repitió las 23:00 del 23; el 24 dura 24 horas. Los tests de la 2.6 y la 3.1b ya usaban el 23/03 |
| 6 | Un turno que cruce la medianoche se corta a las 24:00 con "sigue el día siguiente" en su nombre accesible | La API no lo permite (H10); la grilla igual lo trata sin romperse, con test de datos armados |
| 7 | La barra no muestra todavía "Semana" ni "Lista"; `vista=semana` se descarta | Llegan en la 3.3 |
| 8 | Los bloqueos del día también se vuelven a pedir cada 60 s (y con la misma pausa) | La marca "Dentro de un bloqueo" depende de ellos; son pocos pedidos (uno por agenda que se ve) |
| 9 | La pausa por diálogo o formulario se prueba con `pauseAutoRefresh()` en la página y con `ConfirmDialog` en `auto-refresh.test.tsx` | En la 3.2 la agenda no abre diálogos ni formularios propios (el panel del turno es de solo lectura, no es un diálogo y no pausa); la 3.4 y la 3.5 los suman y ya quedan cubiertos por `ConfirmDialog` |
| 10 | Enter en una celda libre no hace nada | El alta desde la grilla es de la 3.4 (con `appointments.store`) |
| 11 | La limpieza de Playwright cancela los turnos, elimina el bloqueo e **inactiva** la agenda (no la elimina) | Los turnos no tienen baja (`DELETE` → 405) y una agenda con turnos no se elimina (`SCHEDULE_IN_USE`). `syncvet_e2e` se vacía en la corrida siguiente; la demo no se toca |
| 12 | Hallazgo en Edge, corregido: un día sin ninguna agenda que mostrar no tiene grilla y, después de Av Pág, el foco caía en `body` (Re Pág ya no volvía) | Ahora el foco pasa al aviso del día vacío, que responde a Re Pág y Av Pág. Test nuevo en `AgendaDayPage.test.tsx` |
| 13 | Ajuste visual en Edge: en un turno de 30 minutos con sobreturno y dentro de un bloqueo, las dos marcas van en una sola línea | En dos líneas no entraban y se cortaban (captura `02-bloqueo-1280.png`, antes y después) |
| 14 | En Edge, la grilla a 1280 px se vio con **3** agendas (no 4) y la de muchas columnas, con 15 | La demo no tiene agendas activas (las de Newman están inactivadas): se usaron los turnos que dejó Newman el 23/11 (tres agendas con turnos visibles; con los cancelados, 15) y un bloqueo temporal, sin crear turnos (no se pueden borrar) |
| 15 | El script de Edge se corrió 6 veces hasta el 8 de 8 | Fallas del script, no de la pantalla: el bloqueo temporal en otra hora (la base guarda la hora de Asunción, no UTC), `waitForURL` espera un `load` que la navegación del router no dispara y a 375 px el nombre del usuario está oculto en la barra; más el hallazgo 12 y el ajuste 13. Por eso hubo 19 ingresos de `admin-a` en la auditoría, y 7 tokens de las corridas cortadas quedaron sin revocar (el "Salir" manda el `logout` sin esperarlo y el navegador se cerraba antes): se borraron de `personal_access_tokens` (solo los de la sesión, ids 675 a 686) |
| 16 | En desarrollo salen dos pedidos de turnos al abrir la agenda, el primero cancelado (`ERR_ABORTED`) | El `StrictMode` de React monta dos veces y Query cancela el primero; queda uno solo. En el build de producción, uno |
| 17 | Mutación 4 (la pausa ignorada): falló el test del diálogo; el de la pestaña oculta siguió en verde | TanStack Query ya no corre el intervalo en segundo plano (`refetchIntervalInBackground: false`): son dos protecciones, y la pestaña oculta queda cubierta igual |

**Mutaciones** (4 de 4 detectadas, revertidas): la hora de pared con la zona de la PC (`new Date(...)` en `wallMinutes`, 11
fallas); la marca de bloqueo con `<=`/`>=` (tocarse cuenta, 1 falla); el aviso de los turnos que faltan apagado (1 falla); la pausa
ignorada (`refreshInterval` siempre 60 s, 1 falla, desvío 17).

**En Edge** (sin ventana, `es-PY`, zona `America/Asuncion`, contra `syncvet.test` (200 en `/` y `/up`) con un Vite propio en
`:5180`, detenido por su PID; 8 de 8 en la corrida final): (1) a 1280 px, una columna por agenda con turnos visibles, un pedido con
`per_page=500` y sin desplazamiento de la página; (2) el bloqueo sombreado con su motivo y los dos turnos adentro con la marca
(texto e ícono); (3) el foco visible con el teclado (borde de 3 px) entrando con Tab en un solo punto; (4) Enter abre el panel de
solo lectura con el foco en el título y "Cerrar" lo devuelve al turno; (5) Av Pág y Re Pág cambian de día y se anuncia
("Martes 24 de noviembre: sin turnos." y "Lunes 23 de noviembre: 15 turnos."); (6) con 15 columnas, la grilla se desplaza en su caja
y la página no; (7) a 375 px, una agenda por vez con "Agenda que se ve", sin desplazamiento horizontal; (8) la consola sin errores.
El bloqueo temporal ("Control Edge 3.2", agenda 105) se creó y se eliminó por la API en cada corrida (0 bloqueos después).
`admin-a`, `recepcion-a` y `vet-a` sin cambios (`updated_at` del 2026-11-12). Capturas fuera del repositorio (scratchpad de la
sesión, `capturas-3-2`): `01-dia-1280.png`, `02-bloqueo-1280.png`, `03-foco-1280.png`, `04-panel-1280.png`,
`05-cancelados-1280.png` y `06-dia-375.png`.

**Pruebas.** Durante el trabajo, `vitest related` de lo tocado (666 en 34 archivos, con las 9 fallas esperadas del menú, corregidas)
y los archivos nuevos: `dates.test.ts` 12, `grid.test.ts` 20, `search.test.ts` 12, `AgendaDayPage.test.tsx` 22 y
`auto-refresh.test.tsx` 4. Playwright: `e2e/agenda-dia.spec.ts` 2 de 2 y `e2e/permisos.spec.ts` 7 de 7, a la primera (9 de 9 en
1,6 min); 34 casos en 17 archivos (`--list`). Corrida final (2026-11-18, desde `frontend/`): Prettier de los archivos cambiados,
`typecheck`, `lint` y `build` sin errores ni avisos; `npm test`, 1510 tests en 74 archivos, en verde, a la primera. Desde
`backend/`, `FictitiousDocumentsTest` falló 1 vez por un comentario de `dates.ts` (los milisegundos de un día, 8 dígitos): se
reescribió el comentario y dio 3 de 3 (con `dates.test.ts`, 12 de 12; solo cambió un comentario, sin repetir la suite).

**Revisión (2026-11-18, sobre el WIP `cdc184a`, `git diff d30e578`).** Decisiones del usuario: se aceptan los 17 desvíos; H9
corregido en el diseño (el día de 25 horas es el 23/03/2024, también en los tests de la 3.2, la 3.3 y la 3.6 del diseño); regla
nueva para los controles en Edge (un ingreso por rol, los tokens revocados siempre en un `finally` y, si falla el script, se
repite solo el control que falló), en `frontend/CLAUDE.md` §8 y en el "Presupuesto por entrega" del `CLAUDE.md` raíz.

Revisado sin hallazgos: las filas de 15 minutos y el turno que no empieza en un múltiplo de 15 (va en su fila, corrido); los
sobreturnos lado a lado (`assignLanes`, por grupos de superpuestos); la medianoche (corte a las 24:00, desvío 6); los días de 23
y 25 horas (la hora de pared y el fin por la duración); la marca "Dentro de un bloqueo", igual a `affectedAppointments` de
`ScheduleBlockService` (`occupyingValues()`, `starts_at < ends_at` y `ends_at > starts_at`, estrictos, con los bloqueos sin
recortar); el aviso de los turnos que faltan; la vista del veterinario y `todos=1`; `auto-refresh.ts` (el intervalo es de Query:
se limpia al desmontar el observador y no se duplica; cada pausa se suelta una sola vez y en la limpieza del efecto, también al
desmontar `ConfirmDialog` abierto; la pestaña, por `visibilitychange`); el foco (el día vacío, desvío 12, y el turno que
desaparece); el anuncio sin la actualización periódica; los permisos del menú y de la ruta (`appointments.index`, los tres
roles, con `schedules.show` y `schedule-blocks.index` para el detalle y los bloqueos); 375 px (una agenda por vez). Detalle
cosmético sin corregir: el 01/10/2023, un bloqueo que empieza con el día se dibuja desde la fila de las 00:00, una hora que ese
día no existió.

| # | Hallazgo | Corrección |
|---|---|---|
| R1 | **Rendimiento con 300 turnos.** `navigation()` buscaba la fila de cada turno recorriendo todas las filas y todos los turnos de la columna (cuadrático) y se recalculaba en cada render, y cada tecla volvía a dibujar todos los turnos (con `formatTime` en la zona). Medido en jsdom con 300 turnos en una columna: 10 flechas, 1232 ms (en 4 columnas, 917 ms) | La fila sale directo del inicio (`startingItemsByRow`), `navigation` en `useMemo` sobre la grilla y `AgendaItem` en `memo` con el `onOpen` de la página: 10 flechas, 143 ms (123 ms en 4 columnas). Medido con un test temporal, borrado (un límite de tiempo en un test sería frágil). Test nuevo: el turno de las 10:05 va en la celda de las 10:00, corrido 8 px, y la flecha lo alcanza |
| R2 | Los selectores "Agenda" y "Agenda que se ve" mostraban la agenda inactivada sin la marca que tiene su columna | `scheduleOptionLabel`: "Consultorio viejo (inactivada)"; el test de la agenda inactivada lo comprueba |

**Mutaciones de la revisión** (3 de 3 detectadas, revertidas): la marca de bloqueo sin filtrar por estado (2 fallas en
`grid.test.ts`); la vista por defecto del veterinario sin las agendas sin veterinario (2 fallas, `grid.test.ts` y
`AgendaDayPage.test.tsx`); la pausa que nunca se suelta (`usePauseAutoRefresh` sin la limpieza: 1 falla, en
`auto-refresh.test.tsx`).

**Pruebas de la revisión.** `vitest related` de `AgendaGrid.tsx` y `AgendaDayPage.tsx`, 595 en 28 archivos. Corrida final
(2026-11-18, desde `frontend/`): Prettier de los archivos cambiados, `typecheck`, `lint` y `build` sin errores ni avisos;
`npm test`, 1511 tests en 74 archivos, en verde, a la primera. Desde `backend/`, `FictitiousDocumentsTest` 3 de 3. Playwright
(se tocó la grilla): `e2e/agenda-dia.spec.ts` 2 de 2 (1,5 min); `permisos.spec.ts` no se corrió (el menú y los permisos no
cambiaron). Sin Edge ni cambios en la demo; `admin-a`, `recepcion-a` y `vet-a` sin tocar; los puertos de Playwright, libres al
terminar.

**Validación.** Claude Code: 8 de 8 controles en Edge contra `syncvet.test` (con el foco del día vacío corregido, desvío 12, y los
7 tokens que quedaron sin revocar, borrados, desvío 15), Playwright de `e2e/agenda-dia.spec.ts` y `e2e/permisos.spec.ts` y la
revisión con sus correcciones (R1 y R2). El usuario aceptó los 17 desvíos, decidió la corrección de H9 y la regla de los controles
en Edge, pidió la revisión y el cierre, y eligió la siguiente: demo-02, antes de la 3.3. La corrida del cierre es la de la revisión (2026-11-18, desde `frontend/`, sobre el código de `91ee463`; después
solo cambió la documentación): `typecheck`, `lint` y `build` sin errores ni avisos; `npm test`, 1511 tests en 74 archivos, en
verde, a la primera; `FictitiousDocumentsTest`, 3 de 3; Playwright, `e2e/agenda-dia.spec.ts` 2 de 2 (en la implementación, con
`e2e/permisos.spec.ts`, 9 de 9; 34 casos en 17 archivos). El código del backend no cambió: vale la corrida de front-03
(`composer test`, 1485 casos). La corrida completa de Playwright, al cerrar la F3.

Corrida de `npm test` del frontend (2026-11-18): 1511 tests en 74 archivos, en verde.
