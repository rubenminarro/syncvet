# Reglas de negocio: turnos, estados, disponibilidad y cola del día

Reglas vigentes del backend (antes, `backend/CLAUDE.md` §6; el índice, en su
[§6](../../../backend/CLAUDE.md#6-reglas-de-negocio-vigentes)). Las referencias `§N` son de
[`backend/CLAUDE.md`](../../../backend/CLAUDE.md); las `§6.N`, de los archivos de esta carpeta. Si una regla contradice al
código, manda el código.

### 6.10 (continuación) Turnos, estados del turno, disponibilidad y cola del día

* **Turnos** (`appointments`, 3.2a; `AppointmentService`): `GET`/`POST appointments` y `GET`/`PATCH`/`PUT appointments/{appointment}`
  (permisos `appointments.index`, `.store`, `.show` y `.update`); sin baja (`DELETE` → 405): un turno se cancela (estados, abajo). Los dan y los
  mueven el administrador y la recepción; el `veterinario` los lista y ve el detalle, y recibe 403 al dar o mover. Un turno de otra
  clínica → 404. `Appointment` es auditable.
  * **Agenda:** `schedule_id` obligatorio, una agenda activa de la clínica (422 "La agenda no existe o está inactiva."); si tiene
    veterinario, activo y con su usuario activo (422 en `schedule_id`, "El veterinario de la agenda está inactivo."). `branch_id` y
    `veterinarian_id` se copian de la agenda (en el cuerpo, `prohibited`, como `status`, `series_id` e `is_overbooked`).
  * **Quién** (P4), uno de tres: `patient_id` (de la clínica, no eliminado, no fallecido; al editar se acepta el que el turno ya tiene
    aunque haya fallecido); `client_id` sin paciente; o `contact_name` (2 a 150, con al menos una letra, sin caracteres de control ni
    `<` `>`) y `contact_phone` (la regla de teléfonos de clientes), juntos. Sin ninguno → 422 en `patient_id`. Los datos de contacto,
    solo sin cliente ni paciente (`null` se acepta). **Con paciente, el cliente del turno es el dueño actual del paciente:** otro
    `client_id` → 422 en `patient_id`, "El paciente no es de ese cliente."; al editar se acepta también el `client_id` guardado en el
    turno del mismo paciente (el formulario lo reenvía después de un cambio de dueño), y queda el dueño actual; reenviar el
    `patient_id` también lo actualiza; sin tocar el paciente ni el cliente, el turno queda como estaba (R-051). Al editar se pasa de
    contacto a cliente y de cliente a paciente.
  * **Horario:** `starts_at` obligatorio y `ends_at` opcional (por defecto, la duración del servicio o `slot_minutes` de la agenda),
    instantes (`AppDateTime`) en múltiplos de 5 minutos sin segundos, `ends_at > starts_at`, hasta 720 minutos, el mismo día de
    Asunción, y `starts_at` desde el comienzo del día de hoy en Asunción (422 "No podés dar un turno en un día que ya pasó."). Dentro
    del horario semanal del día de la semana de `starts_at`: el rango entero dentro de un tramo, y **los tramos del mismo día que se
    tocan (08:00–12:00 y 12:00–16:00) cuentan como uno solo**; un hueco entre tramos no (422 `APPOINTMENT_OUTSIDE_HOURS`; R-051).
    Un bloqueo de la agenda que se superpone → 422 `APPOINTMENT_IN_BLOCK`.
  * **Superposición:** con un turno de la agenda que ocupa el horario (`AppointmentStatus::occupiesSlot`) o del mismo veterinario en
    otra agenda (pregunta 15), con `a.starts_at < b.ends_at` y `a.ends_at > b.starts_at` (tocarse no choca) → 409
    `APPOINTMENT_OVERLAP`. **Sobreturno** (`overbook: true`, permiso `appointments.overbook`, del administrador y la recepción; sin él,
    403 en el `authorize()` del Form Request): saltea solo la superposición, no el horario ni los bloqueos. `is_overbooked` marca
    solo el turno dado como sobreturno, si al guardarlo se superponía; se recalcula al moverlo y no cambia si después se cancela el
    otro turno. Un turno común contra un sobreturno → 409.
  * **Servicio** opcional (si no llega, el de la agenda), de la clínica y activo; al editar, también el que el turno ya tiene aunque
    se haya inactivado. `source`: `reception` (por defecto), `phone` o `whatsapp`; `online` → 422 ("El origen debe ser recepción,
    teléfono o WhatsApp: la reserva online no está disponible."). `reason` hasta 255 y `notes` hasta 1000.
  * **Editar y mover** (P7): solo a confirmar o confirmado (422 `APPOINTMENT_NOT_EDITABLE`). Mover es cambiar `schedule_id`,
    `starts_at` o `ends_at`: se revisan otra vez la agenda (activa, con su veterinario activo), el horario, los bloqueos y la
    superposición (sin contarse a sí mismo), y se copian la sucursal y el veterinario de la agenda; sin `ends_at`, conserva la
    duración; conserva el estado (pregunta 17). Sin mover, un turno de una agenda inactiva se sigue editando; cambiar solo el
    servicio no recalcula `ends_at`.
  * **Series** (3.3, P1 y R-054; `AppointmentService::createSeries` y `App\Support\AppointmentRecurrence`): `POST appointments` con
    `recurrence`, `every_weeks` (1 a 12; 1 por defecto, también con `null`) y uno de `count` (2 a 52) o `until` (una fecha `Y-m-d`,
    desde la del primer turno, que dé de 2 a 52 turnos); los dos o ninguno → 422 en `recurrence.count`; otra clave → 422 en
    `recurrence`; `recurrence: null` es un turno único. La última fecha, como mucho un año después del primer turno, también con
    `count` (422 en `recurrence.count` o `recurrence.until`). El turno `i` es el primero más `7 × every_weeks × i` días, a la misma
    hora de reloj de Asunción y con la misma duración. Las reglas de toda la serie (la agenda, el servicio, el paciente, el día y la
    duración) responden su error de siempre sobre el primer turno; las de cada fecha (horario, bloqueos y superposición) se revisan
    en todas, sin cortar en la primera, y si alguna falla no se crea ninguna: 409 `APPOINTMENT_SERIES_CONFLICT` con
    `errors.conflicts`, una entrada por fecha con su primer motivo (`outside_hours`, `in_block`, `overlap`, en ese orden).
    `overbook: true` (con `appointments.overbook`) vale para todas las fechas y saltea solo la superposición; cada turno queda con
    `is_overbooked` si al guardarlo se superponía. Responde 201 con la lista de los turnos (`AppointmentResource`) y `meta.series_id`
    (el turno único sigue con `ShowAppointmentResource` sin `meta`). Los turnos comparten `series_id` y son independientes: cada uno
    se edita, se mueve (conserva su `series_id`) y cambia de estado solo; mover la serie entera queda fuera del MVP (`recurrence` en
    el `PATCH` → 422, "La serie se arma al dar los turnos: cada turno se modifica por separado.").
  * **Uso:** un turno es uso del paciente (`PATIENT_IN_USE`), del cliente (`CLIENT_IN_USE`, §6.1), del veterinario, del servicio, de la
    agenda (`SCHEDULE_IN_USE` y `SCHEDULE_NOT_EDITABLE`) y del usuario que lo crea (`created_by`).
  * **Listado** paginado con `ListFilters`, por `starts_at` e id: filtra por `starts_at` con `from` y `to` (`ApiDateTime`; sin ninguno,
    hoy; con uno solo, solo ese día; hasta 31 días); `schedule_id`, `branch_id`, `veterinarian_id`, `service_id`, `patient_id`,
    `client_id`, `status` (uno o varios) y `series_id` (con `series_id` y **sin `from` ni `to`**, la serie entera, sin filtrar por
    fecha ni el tope de 31 días: hasta 52 turnos en un año, que entran en un pedido con `per_page=100`; con `series_id` y un rango,
    el rango y su tope, como sin serie. **Cambio de comportamiento de front-04**: antes, `series_id` sin rango traía solo los de hoy;
    ningún cliente lo usaba así (ni el frontend ni las colecciones de Postman, revisado al cerrar). Una serie de otra clínica no
    aparece, con o sin rango (el scope de la clínica); la de otra sucursal de la clínica, sí (los turnos no se limitan a las
    sucursales del usuario, H11). `IndexAppointmentRequest::wholeSeries()` y `AppointmentSeriesIndexTest`); `search` literal en el nombre y el código del paciente, el nombre y el documento
    del cliente y el nombre y el teléfono de contacto. **`per_page` de 1 a 500** (front-03; el único listado que pasa de 100, para
    que la semana de una sucursal entre en un pedido; 10 por defecto, como los demás): fuera de rango, un decimal o un texto → 422 en
    `per_page`, "Pedí de 1 a 500 turnos por página." (`IndexAppointmentRequest::PER_PAGE_OUT_OF_RANGE`). `meta.pagination.total`
    cuenta todos los del filtro: si supera lo traído, el frontend avisa que faltan turnos. Las relaciones del Resource (agenda,
    sucursal, veterinario con su usuario, servicio, paciente y cliente) van en el `with`: las mismas consultas con 1 turno que con
    300 (`AppointmentPerPageTest`). La consulta de la semana usa `appointments_branch_id_starts_at_index` o, con `schedule_id`,
    `appointments_schedule_id_starts_at_ends_at_index` (EXPLAIN en MySQL, front-03). El detalle (`ShowAppointmentResource`) suma
    las notas, quién lo creó y las fechas de los estados (`confirmed_at`, `checked_in_at`, `attended_at`, `no_show_at` y `cancelled_at`, en UTC con `Z`).
* **Estados del turno** (3.2b; `AppointmentTransitionController`, `AppointmentTransitionService` y `App\Enums\AppointmentTransition`,
  cada acción con sus estados de origen y su destino): `POST appointments/{appointment}/` + `confirm`, `check-in`, `attend`,
  `no-show`, `undo-no-show` y `cancel`, con su permiso (`appointments.confirm`, `.check-in`, `.attend`, `.no-show` y `.cancel`;
  `undo-no-show` usa `appointments.no-show`, en `ROUTES_WITH_ANOTHER_PERMISSION`). Confirman y cancelan el administrador y la recepción;
  fichan los tres roles; marcan atendido el administrador y el veterinario; marcan ausente y lo deshacen el administrador y la
  recepción. Responden 200 con `ShowAppointmentResource`; cada cambio queda en la auditoría (`updated`, con el estado anterior y el
  nuevo). Las horas las pone PHP (`now()`), con `AppDateTime`.

  | Desde \ Hacia | Confirmado | Fichado | Atendido | Ausente | Cancelado |
  |---|---|---|---|---|---|
  | A confirmar | `confirm` | `check-in` | — | `no-show` | `cancel` |
  | Confirmado | — | `check-in` | — | `no-show` | `cancel` |
  | Fichado | — | — | `attend` | — | `cancel` |
  | Ausente | `undo-no-show`, solo el día del turno | — | — | — | — |
  | Atendido, cancelado | Finales: ninguna transición | | | | |

  * Otra transición, también repetir la misma (confirmar un confirmado), → 422 `APPOINTMENT_INVALID_TRANSITION`, sin cambios. Un
    cancelado no se reabre por ningún camino: tampoco con el `PATCH` (422 `APPOINTMENT_NOT_EDITABLE`).
  * **Confirmar** fija `confirmed_at`. **Fichar** fija `checked_in_at`: el turno tiene paciente (si no, 422
    `APPOINTMENT_PATIENT_REQUIRED`), no fallecido (422 en `patient_id`, "El paciente falleció: no se puede fichar el turno.") y es de
    hoy en Asunción (si no, 422 `APPOINTMENT_CHECK_IN_NOT_TODAY`), a cualquier hora del día (antes del inicio: llegó temprano).
    **Atendido** fija `attended_at`. **Ausente** fija `no_show_at`, desde la hora de inicio (`starts_at <= now`; si no, 422
    `APPOINTMENT_NO_SHOW_TOO_EARLY`), también días después. **Cancelar** pide `cancellation_reason` (de 3 a 500; sin límite de
    anticipación) y fija `cancelled_at` y `cancelled_by`. El cancelado y el ausente liberan el horario; el fichado y el atendido lo
    siguen ocupando.
  * **Cancelar con alcance** (3.3 y R-054; `App\Enums\AppointmentCancelScope`): `scope` `single` (por defecto, también con `null`),
    `following` (este y los siguientes de la serie, por `starts_at` y por id en el mismo instante) o `series` (toda la serie); otro →
    422. El turno pedido sigue las reglas de cancelar (si no lo admite, 422 `APPOINTMENT_INVALID_TRANSITION` y no cambia ninguno); de
    los demás, solo los a confirmar o confirmados que todavía no empezaron (`starts_at > now`), con el mismo motivo, la misma hora
    (`cancelled_at`) y cada uno con su auditoría. En un turno sin serie, el alcance no cambia nada. Responde el turno pedido con
    `meta.cancelled_count` (también con `single`): "Turno cancelado." con uno y "Se cancelaron N turnos de la serie." con más.
  * **Fichar y atendido, por un veterinario** (pregunta 18 y R-053): un usuario con el rol `veterinario` (o con una ficha) y sin
    `appointments.update` marca solo los turnos de su ficha (no eliminada) y los de agendas sin veterinario; sin ficha, o con la ficha
    eliminada, solo los de agendas sin veterinario. Si no, 403 `APPOINTMENT_NOT_YOURS`, "Ese turno no es de tu agenda.", revisado antes
    que el estado (un turno ajeno sin fichar también da 403). El administrador (`Gate::before`) y quien tiene `appointments.update`,
    cualquiera.
  * **Deshacer el ausente** (pregunta 19 y R-053): solo el día del turno en Asunción (otro día → 422
    `APPOINTMENT_NO_SHOW_NOT_UNDOABLE`, aunque el ausente se haya marcado hoy); vuelve a confirmado, `no_show_at` en `null` y
    `confirmed_at` la hora actual si no tenía. Vuelve a ocupar el horario: se revisa la superposición como en un alta, sin contarse a
    sí mismo (el horario semanal, los bloqueos y la agenda activa no). Ocupado → 409 `APPOINTMENT_OVERLAP`, salvo con `overbook: true`
    (`UndoNoShowAppointmentRequest`; permiso `appointments.overbook`, si no, 403 `FORBIDDEN`), que entra con `is_overbooked: true`. Un
    turno que ya era sobreturno vuelve sin mandar `overbook`, pero exige `appointments.overbook` con el horario tomado o libre (si no,
    403 `FORBIDDEN`). `is_overbooked` queda en si se superpone.
  * La relación con la consulta la decide la FASE 4 ([propuesta](../../03-plan-desarrollo/fases/backend/fase-03-agenda-y-turnos.md#relación-con-la-consulta-fase-4)).
* **Disponibilidad** (3.4, R-055; `GET schedules/{schedule}/availability`, permiso `schedules.availability`, de los tres roles;
  `ScheduleAvailabilityService`): los horarios que el alta de un turno aceptaría, con las mismas reglas y el mismo código
  (`ScheduleOccupancy`, §3). Solo lectura: no bloquea nada y el alta vuelve a revisar.
  * Filtros (`ScheduleAvailabilityRequest`): `from` y `to`, fechas solas `Y-m-d` de Asunción (una fecha con hora → 422 "Ingresá una
    fecha (2026-11-09)."); sin `from`, hoy; sin `to`, el día de `from`; `to` antes de `from` → 422; hasta 7 días contando los dos (si no,
    422 en `to`). `service_id`, un servicio de la clínica. `duration_minutes` (decisión D2), entero de 5 a 720 en múltiplos de 5 (si
    no, 422 "Ingresá una duración de 5 a 720 minutos, en múltiplos de 5.").
  * Duración: `duration_minutes` si llega (manda), si no la del servicio pedido, la del servicio de la agenda o `slot_minutes`
    (`AppointmentService::durationMinutes`, la misma del alta sin `ends_at`). Sale en `data.duration_minutes`.
  * Como el alta: una agenda inactiva o con su veterinario inactivo → 422 en `schedule`; el servicio pedido, o el de la agenda,
    inactivo o eliminado → 422 en `service_id` (también con `duration_minutes`). Una agenda de otra clínica → 404; una de otra
    sucursal que la actual del usuario se consulta (como el alta da turnos en cualquier agenda de la clínica; H11).
  * Para cada día, los horarios que empiezan en el comienzo de cada tramo y cada `slot_minutes` mientras empiecen dentro del tramo;
    se ofrece uno si la duración entra en el horario semanal (los tramos que se tocan, como uno solo), no toca un bloqueo, no se
    superpone con un turno que ocupa el horario (en la agenda o del mismo veterinario en otra; un sobreturno existente ocupa, un
    cancelado o un ausente no), no cruza la medianoche y no empezó (`starts_at >= ahora`). Un día anterior a hoy, `slots` vacío. El alta
    acepta un horario de hoy que ya empezó (pregunta 16) aunque la disponibilidad no lo ofrezca.
  * Respuesta: `{schedule_id, duration_minutes, days: [{date, slots: [{starts_at, ends_at}]}]}`, con `date` en Asunción y las horas en
    UTC (`Z`). Una cantidad fija de consultas, sin una por horario.
* **Cola del día** (3.4, R-055; `GET appointments/queue`, permiso `appointments.queue`, de los tres roles; registrada antes de
  `appointments/{appointment}`; `AppointmentQueueService`): los turnos de un día en la **sucursal actual** del usuario, sin los
  cancelados (los ausentes y los atendidos, sí), por `starts_at` y después id (también en la consulta: el índice ya devuelve los
  empates por id), sin paginar.
  * Sin sucursal actual → 422 `APPOINTMENT_QUEUE_NO_BRANCH`. Turnos de otra sucursal o de otra clínica nunca aparecen.
  * Filtros (`AppointmentQueueRequest`): `date` (`Y-m-d` de Asunción, hoy por defecto), `all`, `veterinarian_id` y `schedule_id`
    (de la clínica, también inactivos o eliminados; de otra clínica → 422).
  * Vista por defecto, no restricción (pregunta 25): quien tiene una ficha de veterinario (no eliminada) ve por defecto los turnos de
    su ficha **y los de las agendas sin veterinario de su sucursal actual** (decisión D1: desde la 3.2b los ficha y los marca
    atendidos); con `all=1`, los de toda la sucursal; sin ficha (la recepción, el administrador, un veterinario sin ficha o con la
    ficha eliminada), toda la sucursal. `veterinarian_id`, si llega, manda sobre la ficha y sobre `all`: solo los de ese veterinario,
    también el propio (sin las agendas sin veterinario).
  * Hasta 200 (`AppointmentQueueService::LIMIT`): trae 201 filas y, si hay más, devuelve las primeras 200 con `meta.truncated: true`
    (`false` si no corta; decisión D3).
  * Respuesta: `QueueAppointmentResource` (el `AppointmentResource` del listado con el paciente ampliado, especie y raza, y
    `checked_in_at` para la demora), con una cantidad fija de consultas (sin N+1). `meta`: `date`, `branch_id`, `veterinarian_id` (el
    del filtro: el de la ficha por defecto, `null` con `all=1` o sin ficha), `includes_unassigned` (`true` solo en la vista por
    defecto de una ficha) y `truncated`.
