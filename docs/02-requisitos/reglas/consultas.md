# Reglas de negocio: la consulta (historia clínica)

Reglas vigentes del backend (el índice, en [`backend/CLAUDE.md`](../../../backend/CLAUDE.md#6-reglas-de-negocio-vigentes) §6). Las
referencias `§N` son de [`backend/CLAUDE.md`](../../../backend/CLAUDE.md). El diseño de la fase, con sus preguntas y decisiones, está
en [`fase-04-historia-clinica.md`](../../03-plan-desarrollo/fases/backend/fase-04-historia-clinica.md) (R-099). Si una regla
contradice al código, manda el código.

### 6.12 La consulta (FASE 4)

* **La consulta** (`consultations`, entrega 4.1; `ConsultationService`): `GET`/`POST consultations` y `GET`/`PATCH`/`PUT
  consultations/{consultation}` (permisos `consultations.index`, `.store`, `.show` y `.update`); sin baja (`DELETE` → 405): se anula
  (4.2). La abren y la editan el administrador y el veterinario; la recepción la lista y la ve, sin el contenido clínico. Una de otra
  clínica → 404. `Consultation` es auditable. El código, `CON-` (`DocumentSequenceService`).
* **Abrir desde un turno** (`appointment_id`; uno de `appointment_id` o `patient_id`, no los dos, 422):
  * **Fichado:** el turno pasa a **atendido** (`attended_at` = ahora) en la misma transacción, con su auditoría.
  * **Ya atendido sin consulta:** solo el **mismo día del turno** en Asunción (otro día, 422 `CONSULTATION_APPOINTMENT_NOT_TODAY`); el
    turno no cambia.
  * **En otro estado** (a confirmar, confirmado, ausente, cancelado): 422 `APPOINTMENT_INVALID_TRANSITION`.
  * **Las reglas de `attend`** (`AppointmentOwnership`, compartida con fichar y atendido): un veterinario sin `appointments.update` abre
    solo desde los turnos de su ficha o de agendas sin veterinario (403 `APPOINTMENT_NOT_YOURS`, antes que el estado).
  * **Un turno, como máximo una consulta:** si ya la tiene, 409 `CONSULTATION_ALREADY_EXISTS`; también cuando la carrera la detiene el
    índice único (`consultations_appointment_unique`, reconocido por su nombre en MySQL y por `consultations.appointment_id` en SQLite,
    §3).
  * Toma del turno el paciente, la sucursal y el motivo (si el pedido no trae `reason`).
* **Abrir sin turno** (`patient_id`; urgencia o sin turno): la sucursal es la actual de quien abre (sin sucursal actual, 422
  `CONSULTATION_NO_BRANCH`). No crea un turno.
* **El cliente** es el dueño **actual** del paciente al abrir; un cambio de dueño posterior no cambia la consulta.
* **Paciente fallecido:** no recibe una consulta nueva (422 `PATIENT_DECEASED`), desde el turno ni sin él.
* **El veterinario:** el del turno; si el turno no tiene, la ficha (no eliminada) de quien abre; si no tiene ficha (el administrador),
  `veterinarian_id` del pedido, obligatorio (422 "Indicá el veterinario…"), activo y no eliminado (422 "El veterinario está
  inactivo."). Un `veterinarian_id` distinto del que corresponde → 422 ("El veterinario de la consulta es el del turno o el de tu
  ficha…").
* **`consulted_at`:** por defecto, ahora; un instante (con zona, o sin ella en hora de Asunción); no futuro (al segundo) ni anterior al
  fichado del turno (422 en `consulted_at`).
* **Editar** (`PATCH`, parcial; `PUT`, igual): solo **abierta** (cerrada o anulada, 422 `CONSULTATION_NOT_EDITABLE`); la de **su
  ficha** o el **administrador** (otro, 403 `CONSULTATION_NOT_YOURS`, antes que el estado). Se editan `consulted_at` (no `null`) y los
  campos clínicos; el turno, el paciente, el cliente, el veterinario, la sucursal, el estado y el código, `prohibited`.
* **Límites** (pregunta 13, en caracteres): `reason` hasta 255; `history`, `physical_exam`, `diagnosis`, `treatment` e `instructions`
  hasta 10000; `mucous_membranes` 50; `capillary_refill_time` 20. Peso de 0,01 a 9999,99 kg (2 decimales); temperatura de 30 a 45 °C
  (1 decimal); frecuencia cardíaca de 1 a 400; respiratoria de 1 a 200; condición corporal de 1 a 9. Todos opcionales mientras está
  abierta; los decimales, con punto.
* **Listado** (`GET consultations`): de **toda la clínica**, por `consulted_at` descendente e id. Filtros: `patient_id`, `client_id`,
  `veterinarian_id`, `branch_id`, `appointment_id` (de la clínica), `status` (uno o varios), `from`/`to` sobre `consulted_at` (una
  fecha sola es el día entero de Asunción; hasta 31 días; sin ellos, **los últimos 30 días**; con uno solo, ese día) y `search`
  (literal: el código, el nombre o el código del paciente y el cliente). Los de `ListFilters`; un filtro mal formado, 422.
* **Lo que ve cada uno** (pregunta 7 y ajuste de R-099): con `consultations.show-clinical` (el **administrador y el veterinario**),
  el contenido clínico: en el listado, el diagnóstico; en el detalle, la anamnesis, el examen físico, el diagnóstico, el tratamiento y
  las indicaciones. Sin él (la **recepción**), solo lo administrativo (código, fecha, estado, sucursal, paciente, cliente,
  veterinario, turno, motivo, quién la creó, cerró o anuló): las claves clínicas no están. El permiso se consulta con `can()` (pasa
  por la Gate y su `before` del administrador), nunca con `hasPermissionTo()`.
* **En el turno:** `ShowAppointmentResource` suma `consultation` (`id`, `code`, `status`) o `null`; el listado de turnos y la cola del
  día, `has_consultation` (con `withExists`, sin N+1). Aditivo: el resto de la API de la FASE 3 no cambia.
* **Concurrencia** (§3; el orden de la FASE 3 más la consulta: veterinario → paciente → cliente → turno → consulta → serie): abrir
  toma el veterinario, el paciente y el cliente con `sharedLock` y el turno con `lockForUpdate` antes de revisar su estado y su
  consulta; si el turno cambió de paciente o de veterinario entre la lectura y el bloqueo, se repite. Editar, la consulta con
  `lockForUpdate` antes de leer su estado. Los reintentos de `AppointmentService::withRetries` (un deadlock, hasta 3 intentos; una
  espera vencida, 409 `CONFLICT` enseguida, "La consulta cambió mientras se guardaba: volvé a intentarlo.").
* **Permisos en una base que ya existe:** `php artisan permissions:sync` (idempotente; [`setup.md`](../../04-desarrollo/setup.md)):
  crea los permisos que faltan, quita los que salieron, le da al administrador todos y sincroniza los demás roles con `clinicRoles()`.
  Los usuarios conservan sus roles.
* **Inmutabilidad** (entrega 4.2, D4): una consulta cerrada o anulada no se edita. Toda escritura sobre una consulta pasa por
  `ConsultationService::lockEditable()` (el único lugar; lo reusan los ítems, las aplicaciones y los adjuntos de la 4.3 a la 4.5): la
  consulta con `lockForUpdate`, la ficha (403 `CONSULTATION_NOT_YOURS`) y después el estado (422 `CONSULTATION_NOT_EDITABLE`).
* **Cerrar** (`POST consultations/{consultation}/close`, `consultations.close`, sin cuerpo; preguntas 5, 8 y 14): la de su ficha o el
  administrador (otro, 403 `CONSULTATION_NOT_YOURS`); solo abierta (422 `CONSULTATION_NOT_CLOSABLE`); exige el **motivo y el
  diagnóstico** (si falta alguno, 422 `CONSULTATION_CLOSE_INCOMPLETE` con `errors` por campo, `reason` y `diagnosis`). Fija `closed_at`
  y `closed_by` y copia el peso de la consulta al paciente, con su auditoría, si la consulta lo tiene y el paciente no falleció (un
  fallecido solo cambia su fecha y sus notas, §6.2). No hay cierre automático: el listado con `status=open` muestra las pendientes.
  El turno no cambia.
* **Enmiendas** (`POST consultations/{consultation}/amendments`, `consultations.amend`: su veterinario y el administrador; pregunta 4):
  una cerrada se corrige con registros aparte; lo escrito nunca cambia y no hay reapertura (`consultations.reopen` salió). Solo sobre
  una cerrada (abierta o anulada, 422 `CONSULTATION_NOT_AMENDABLE`); la de su ficha o el administrador (403 `CONSULTATION_NOT_YOURS`).
  `content` de 3 a 10000 caracteres, `reason` de 3 a 500 y `field` opcional, uno de los campos clínicos (los que se editan en una
  abierta, `consulted_at` incluido) o `null` para una nota general. Responde 201 con el detalle de la consulta. La enmienda
  (`consultation_amendments`) es inmutable (el modelo rechaza editarla o borrarla), sin `updated_at`, auditada; la consulta no se
  borra mientras tenga enmiendas. En el detalle van en `amendments` (de la más vieja a la más nueva, con su autor y su hora), solo
  con `consultations.show-clinical`.
* **Anular** (`POST consultations/{consultation}/cancel`, `consultations.cancel`: el administrador; pregunta 12): con
  `cancellation_reason` (de 3 a 500); abierta o cerrada (anulada, 422 `CONSULTATION_NOT_CANCELLABLE`). Fija `cancelled_at`,
  `cancelled_by` y el motivo; queda visible, con todo su contenido. Sus ítems pendientes pasan a `no_charge` (no se cobran; uno
  facturado no se toca, y desde la FASE 8 impedirá anular). El turno sigue atendido. **Pendiente de la 4.4:** sus aplicaciones se
  anularán con ella cuando exista la anulación de aplicaciones.
* **Concurrencia de la 4.2:** cerrar toma el paciente (copia el peso) y después la consulta, los dos con `lockForUpdate` (§3,
  paciente → consulta); enmendar, la consulta; anular, la consulta y después sus ítems, por id (consulta → ítem). Los mismos
  reintentos de `withRetries`.
