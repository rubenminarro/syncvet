# Reglas de negocio: agendas, horario semanal y bloqueos

Reglas vigentes del backend (antes, `backend/CLAUDE.md` §6; el índice, en su
[§6](../../../backend/CLAUDE.md#6-reglas-de-negocio-vigentes)). Las referencias `§N` son de
[`backend/CLAUDE.md`](../../../backend/CLAUDE.md); las `§6.N`, de los archivos de esta carpeta. Si una regla contradice al
código, manda el código.

### 6.10 Agendas, horario semanal, bloqueos, turnos, disponibilidad y cola del día

* ABM en `schedules` (`apiResource`; permisos `schedules.index`, `.store`, `.show`, `.update` y `.destroy`), con `ScheduleService`. Las
  arma solo el administrador (P9); el `recepcionista` y el `veterinario` listan y ven el detalle y reciben 403 al escribir. Una agenda
  de otra clínica → 404.
* `branch_id` obligatorio: una sucursal activa de la clínica (422 "La sucursal no existe o está inactiva."). Veterinario, servicio o
  los dos (P10; sin ninguno → 422 en `veterinarian_id`, "Elegí un veterinario, un servicio o los dos."). El veterinario: de la
  clínica, activo, con su usuario activo (422 "El veterinario no existe o está inactivo.") y asignado a la sucursal de la agenda
  (`branch_user`; 422 "El veterinario no trabaja en esa sucursal.", también al mover la agenda de sucursal). El servicio: de la
  clínica y activo. Al editar se acepta el veterinario o el servicio que la agenda ya tiene aunque se haya inactivado.
* **Una agenda con turnos** (en cualquier estado, también cancelados) no cambia de sucursal ni de veterinario: 422
  `SCHEDULE_NOT_EDITABLE`, "La agenda ya tiene turnos y no puede cambiar de sucursal ni de veterinario: creá otra agenda e inactivá
  esta." (pregunta 14). El servicio sí cambia.
* Nombre de 2 a 100 con al menos una letra (`CatalogMessages::NAME_REGEX`), guardado limpio (`CatalogName::clean`) y único dentro de
  la sucursal sin mayúsculas, acentos ni espacios repetidos, con la clínica bloqueada; también al mover la agenda a otra sucursal:
  422 "Ya hay una agenda con ese nombre en la sucursal.". `slot_minutes` de 5 a 240 en múltiplos de 5 (30 por defecto); `color`
  `#RRGGBB` en mayúsculas, opcional; `is_active` (`true` por defecto). `online_booking` no forma parte de la API (se ignora y no se
  muestra).
* **Horario semanal** (`hours`, `schedule_hours`): tramos `{weekday, starts_at, ends_at}`, con `weekday` de 1 (lunes) a 7 (domingo) y
  horas `HH:MM` de reloj de Asunción (no son un instante: sin `AppDateTime`), de 00:00 a 23:55 en múltiplos de 5, `starts_at <
  ends_at`. En el mismo día no se superponen (tocarse sí: 08:00–12:00 y 12:00–16:00), hasta 6 tramos por día y 42 en total. Opcional
  en el alta. Al editar, si llega, **reemplaza** el horario entero dentro de la transacción de la edición (`hours: []` lo vacía;
  `hours: null` → 422): borra los tramos que no están, crea los nuevos y deja los que no cambian, cada uno con su auditoría; si la
  edición falla, el horario anterior queda. No toca los turnos que ya existen.
* Inactivar (`is_active: false`) aunque tenga turnos futuros: siguen, y la recepción los reprograma (pregunta 24).
* **Baja física** (sin `SoftDeletes`, pregunta 13), solo sin turnos en cualquier estado: si no, 409 `SCHEDULE_IN_USE`, "La agenda
  tiene turnos y no se puede eliminar: inactivala." Se elimina con sus tramos y sus bloqueos, modelo por modelo (auditoría), en una
  transacción. Una agenda es uso de su veterinario (`VeterinarianService::USAGE`) y de su servicio (`ServiceCatalogService::USAGE`,
  también para el nombre fijo); eliminada, deja de serlo.
* **Bloqueos** (`schedules/{schedule}/blocks`: listar y crear; `DELETE schedule-blocks/{scheduleBlock}`; permisos
  `schedule-blocks.index`, `.store` y `.destroy`, con nombres de ruta propios): los crea y los elimina solo el administrador; los tres
  roles los listan. `starts_at` y `ends_at` son instantes (`AppDateTime`: con zona o sin ella en hora de Asunción) en múltiplos de 5
  minutos sin segundos, `ends_at > starts_at`, hasta 366 días; `reason` hasta 255; `created_by` es quien lo crea. Se superponen entre
  sí, también en una agenda inactiva, y no se editan (se eliminan y se crea otro). Un bloqueo sobre turnos se permite: la respuesta
  los lista en `affected_appointments` (los de la agenda que ocupan el horario, `AppointmentStatus::occupiesSlot`: a confirmar,
  confirmado, fichado y atendido, y se superponen con la regla `a.starts_at < b.ends_at` y `a.ends_at > b.starts_at`; los que solo se
  tocan no cuentan), pregunta 22. Un bloqueo de otra clínica → 404.
* Listados paginados con `ListFilters`. Agendas: activas salvo `include_inactive=1`; `branch_id`, `veterinarian_id` y `service_id`;
  `search` literal en el nombre; orden por nombre e id. Bloqueos: los que terminan desde hoy, salvo `from`/`to` (`ApiDateTime`);
  `search` literal en el motivo; orden por `starts_at`. El detalle (`ShowScheduleResource`) suma `hours` y `upcoming_blocks` (hasta
  20).
* Concurrencia: §3, orden de bloqueo de la FASE 3. `Schedule`, `ScheduleHour` y `ScheduleBlock` son auditables.
