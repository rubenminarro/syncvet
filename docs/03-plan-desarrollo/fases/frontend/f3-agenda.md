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
    ("Martes 17 de noviembre: 12 turnos."). El resultado de una acción ("Turno confirmado.") va en el aviso (toast), que ya es
    `role="status"` dentro de una región `aria-live` siempre en el DOM: no se repite en la región de la agenda, para que el lector
    no lo lea dos veces (decisión del usuario en la revisión de la 3.5). La actualización periódica no anuncia
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
| 3.1a | Agendas: listado, detalle, alta y edición con el horario semanal, inactivar y baja (`/configuracion/agendas`) | No | `feature/front-3-1a-agendas`, desde `docs/front-f3-diseno` | COMPLETADO (R-086, [resultado](f3-resultados.md#entrega-31a)) |
| 3.1b | Bloqueos de una agenda: listar, crear (con los turnos afectados) y eliminar | No | `feature/front-3-1b-bloqueos`, desde la 3.1a | COMPLETADO (R-087, [resultado](f3-resultados.md#entrega-31b)) |
| front-03 | Soporte del backend: `per_page` hasta 500 en `GET appointments` ([pregunta 1](#preguntas-del-diseño)) | — | `feature/front-03-turnos-por-pagina`, desde la 3.1b | COMPLETADO (R-088, [resultado](f3-resultados.md#entrega-front-03)) |
| 3.2 | La agenda del día: grilla por agenda, filtros, la sucursal actual, el teclado y "Agenda" en el menú (solo lectura) | front-03 | `feature/front-3-2-agenda-dia`, desde front-03 | COMPLETADO (R-089, [resultado](f3-resultados.md#entrega-32)) |
| demo-02 | Soporte del backend para la demo: un comando `php artisan demo:agenda` que crea en la demo agendas para las sucursales y turnos ficticios de la semana en curso (varios estados, un bloqueo y un sobreturno), idempotente y sin tocar `admin-a`, `recepcion-a` ni `vet-a`. La demo no tiene agendas activas ni turnos propios (H6, desvío 14 de la 3.2) | — | `feature/demo-02-agenda`, desde la 3.2 | COMPLETADO (R-090, [resultado](f3-resultados.md#entrega-demo-02)) |
| 3.3 | La semana y la lista de turnos (`/turnos`, con los filtros de la API y las listas en la URL) | No | `feature/front-3-3-semana-lista`, desde demo-02 (decisión del usuario del 2026-11-18) | COMPLETADO (R-092, [resultado](f3-resultados.md#entrega-33)) |
| 3.4 | El turno: detalle, alta (paciente, cliente o contacto; servicio y duración; horario con la disponibilidad; sobreturno), edición y reprogramación | No | `feature/front-3-4-turno`, desde la 3.3 | COMPLETADO (R-093, [resultado](f3-resultados.md#entrega-34)) |
| 3.5 | Los estados: confirmar, fichar, atendido, ausente y deshacerlo, cancelar con motivo | No | `feature/front-3-5-estados`, desde la 3.4 | COMPLETADO (R-094, [resultado](f3-resultados.md#entrega-35)) |
| 3.6 | Las series: el alta con repetición, los conflictos de la serie y cancelar con alcance | No | `feature/front-3-6-series`, desde la 3.5 | APROBADA (siguiente) |
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

Vacío: la entrega se cerró (R-086) y todo lo que había acá pasó a su [resultado](f3-resultados.md#entrega-31a).

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

Vacío: la entrega se cerró (R-087) y todo lo que había acá pasó a su [resultado](f3-resultados.md#entrega-31b).

### front-03 Soporte del backend: más turnos por página en el listado

**Aprobada con la opción (a) de la [pregunta 1](#preguntas-del-diseño).** `IndexAppointmentRequest` acepta `per_page` de 1 a 500
(el resto de los listados sigue hasta 100, `ListFilters`), con su mensaje. Sin rutas, permisos, migraciones ni `error_code` nuevos;
aditivo (con `per_page` de hasta 100 responde lo mismo que hoy). Tests: 500 pasa y 501 → 422; la cantidad de consultas fija con 1 y
con 300 turnos (sin N+1, `InspectsQueries`). Postman: un pedido de la colección de turnos con `per_page=500`. `backend/CLAUDE.md`
§6.10 y la [guía de Postman](../../../05-pruebas/guia-postman.md). Al cerrar: `composer test` y Newman de la colección de turnos.

#### Pendiente para el cierre (front-03)

Vacío: la entrega se cerró (R-088) y todo lo que había acá pasó a su [resultado](f3-resultados.md#entrega-front-03).

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

Vacío: la entrega se cerró (R-089) y todo lo que había acá pasó a su [resultado](f3-resultados.md#entrega-32).

### demo-02 Soporte del backend: la agenda de la demo

**Comando** `php artisan demo:agenda` (`app/Console/Commands/DemoAgendaCommand.php`), en la Clínica A del `DemoSeeder` (por su RUC
y su marca). Sin rutas, permisos, migraciones ni `error_code` nuevos.

* **Lo que crea**, por los Services (`ScheduleService`, `ScheduleBlockService`, `ServiceCatalogService`, `AppointmentService` y
  `AppointmentTransitionService`) y con `admin-a` como usuario (la auditoría es la de la API): los servicios "Demo Consulta
  general", "Demo Baño y corte", "Demo Guardia" y "Demo Vacunación"; en la sucursal del `DemoSeeder` (`SUC-01` "Casa central",
  por su código, `ClinicService::FIRST_BRANCH_CODE`; nunca en las que dejan Postman o Playwright, aunque alguna sea la por
  defecto), "Demo Consultorio 1" (con la ficha de `vet-a`), "Demo Consultorio 2", "Demo Peluquería", "Demo Guardia" (abierta
  todos los días, sin veterinario) y "Demo Vacunación", cada una con su horario semanal; los turnos de la semana en curso
  (lunes a domingo en `America/Asuncion`), uno cada dos o tres horarios, con los pacientes del `DemoSeeder`. El estado sale de la
  hora: los que ya terminaron, atendidos, ausentes o cancelados; el que está en curso, fichado; los que vienen, a confirmar,
  confirmados o cancelados. Siempre hay un turno en cada estado (lo que falta va en la guardia de ayer, de la semana pasada si es
  lunes temprano), un fichado hoy, un sobreturno en la agenda de `vet-a`, un turno solo con contacto (primera vez, nombre y
  teléfono inventados) y un bloqueo sobre un turno que ocupa el horario (en la peluquería o la guardia, el primero de hoy por
  venir).
* **Idempotente.** La marca es el prefijo "Demo " del nombre. Al correrlo de nuevo borra los turnos y los bloqueos de las agendas
  "Demo …", actualiza esas agendas (conservan su id), elimina las "Demo …" que ya no están en la definición o que quedaron en otra
  sucursal, reutiliza los servicios y regenera la semana actual. No toca otras agendas ni sus turnos, ni las sucursales, ni la
  Clínica B, ni `admin-a`, `recepcion-a` o `vet-a`. Si un turno de una agenda "Demo …" tiene una consulta (FASE 4), o si
  `SUC-01` no está o está inactiva, se niega.
* **Se niega** fuera de `local` y `testing` (los entornos del `DemoSeeder`) y sin la clínica de la demo; todo va en una
  transacción.
* **Resumen** al final: las agendas con su sucursal, su veterinario y sus turnos; los turnos por estado; los de hoy, los
  sobreturnos, los de un contacto y el bloqueo.

**Tests** (`tests/Feature/Console/DemoAgendaCommandTest.php`, 11): lo que crea y los estados con el reloj fijo (miércoles
18/11/2026 10:15), las cinco agendas en `SUC-01`; dos corridas iguales (las agendas con el mismo id); otra corrida, otro lunes, que
regenera la semana; que no toca otros datos ni los tres usuarios; solo `SUC-01` con otra sucursal activa y por defecto, y la "Demo
Vacunación" vieja de esa sucursal eliminada con su turno (la agenda propia de esa sucursal queda); el reloj restaurado después de
una corrida y después de una falla a mitad de un paso con el reloj corrido (sin nada a medias); la negativa en `production` y sin
la clínica de la demo; la semana de Asunción con el domingo 22:30 (lunes 01:30 en UTC); el lunes temprano, con el atendido y el
ausente del domingo anterior. Ninguno depende de la hora real: los que corren el comando fijan el reloj (`travelTo`).

#### Pendiente para el cierre (demo-02)

Vacío: la entrega se cerró (R-090) y todo lo que había acá pasó a su [resultado](f3-resultados.md#entrega-demo-02).

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

Vacío: la entrega se cerró (R-092) y todo lo que había acá pasó a su [resultado](f3-resultados.md#entrega-33).

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
* Al editar, se mandan **solo los campos que cambiaron** contra la versión del turno cargada en el formulario: "quién" entero si
  cambió algo de él (pasar de contacto a paciente vacía el contacto); el servicio, el origen, el motivo, las notas, `schedule_id`,
  `starts_at` y `ends_at`, cada uno si cambió (la API valida solo lo que llega, P7). Así un guardado no pisa lo que otra PC cambió
  en los campos que no se tocaron. Decisión del usuario en el cierre de la 3.4 ([R-093](../../../01-arquitectura/decisiones/registro/R-051-100.md#r-093-cierre-de-la-entrega-34-del-frontend-el-turno-detalle-alta-edición-y-reprogramación-2026-11-19)),
  que reemplaza lo aprobado en R-085: "el formulario se manda completo salvo `starts_at`, `ends_at` y `schedule_id` si no
  cambiaron; `client_id` se reenvía como está".

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

Vacío: la entrega se cerró (R-093) y todo lo que había acá pasó a su [resultado](f3-resultados.md#entrega-34).

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
("Turno confirmado."), solo en el toast, que ya es `role="status"` (sin repetirlo en la región de la agenda: se leería dos veces;
decisión del usuario en la revisión). Los errores, con `dialogError` (los 422 de negocio, el mensaje y
"Cerrar", con el turno vuelto a pedir). "Ahora" para las reglas de hora es la del navegador; si difiere de la del servidor, decide la
API.

**Tests (Vitest).** `transitions.ts` contra la tabla del backend (un test lee `app/Enums/AppointmentTransition.php`, como H2 de la F2,
y falla si cambia); cada acción con su permiso en los tres roles; fichar sin paciente y otro día; ausente antes y después del inicio
(relojes falsos); el veterinario en un turno ajeno sin "Fichar" ni "Atendido" y en uno de una agenda sin veterinario con los dos;
cancelar sin motivo, sin pedido; `APPOINTMENT_INVALID_TRANSITION` en el diálogo; deshacer el ausente con el 409.

**Playwright** (`e2e/estados.spec.ts`, el nombre del pedido de la entrega, aceptado en la revisión). Confirmar, fichar y que `vet-a` lo marque atendido en otro contexto; marcar ausente un
turno ya empezado (el ayudante lo crea a una hora pasada de hoy) y deshacerlo; cancelar con motivo y verlo oculto en la grilla.

**Mutaciones a probar.** "Fichar" habilitado otro día; el veterinario con "Atendido" en un turno ajeno; cancelar sin `cancellation_reason`.

**En el navegador.** Las acciones a 375 px; el diálogo de cancelar con el teclado.

#### Pendiente para el cierre (3.5)

Vacío: la entrega se cerró (R-094) y todo lo que había acá pasó a su [resultado](f3-resultados.md#entrega-35).

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

> El resultado de cada entrega y el cierre de la fase están en [`f3-resultados.md`](f3-resultados.md).
