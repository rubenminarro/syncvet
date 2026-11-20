# FASE 4 — Historia clínica: resultados

El resultado de cada entrega y el cierre de la fase. El diseño y las decisiones, en
[`fase-04-historia-clinica.md`](fase-04-historia-clinica.md).

## Resultado

Lo completa el cierre de cada entrega ([`docs/03-plan-desarrollo/prompts/7-cierre.md`](../../prompts/7-cierre.md)): archivos,
endpoints, tests y aserciones, desvíos del diseño y por qué.

### Entrega 4.1

La consulta: abrirla desde el turno o sin turno, editarla abierta, el listado y el detalle. Implementación en
`feature/back-4-1-consulta`, creada desde `844a7c0` (el ajuste de R-099), en el commit WIP `f9f95d4`; revisión con correcciones en
`36aef4c`; el cierre va en un commit aparte. Decisiones en [R-100](../../../01-arquitectura/decisiones/registro.md). Las reglas, en
[`consultas.md`](../../../02-requisitos/reglas/consultas.md).

**Archivos.**

* Nuevos: la migración `2026_11_21_000000_adjust_consultations_for_phase_4`; `ConsultationService`, `AppointmentOwnership`
  (las reglas de `attend`, compartidas), `ConsultationController`, `PermissionsSyncCommand`; `StoreConsultationRequest`,
  `UpdateConsultationRequest`, `IndexConsultationRequest` y `ConsultationMessages`; `ConsultationResource` y
  `ShowConsultationResource`.
* Cambiados: `Consultation` (`treatment`, `instructions`, casts; deuda 2), `Appointment` (la relación `consultation`),
  `ConsultationException`, `AppointmentResource` y `ShowAppointmentResource`, `AppointmentController` y `AppointmentQueueService`
  (`withExists`), `AppointmentService` (`withRetries` con mensaje de conflicto y `loadDetail` con la consulta),
  `AppointmentTransitionService` (usa `AppointmentOwnership`), `RolesAndPermissionsSeeder` y `tests/Fixtures/clinic-roles.json`,
  `routes/api.php`, `lang/es/validation.php` y `PostmanCleanupCommand` (las consultas de la corrida, primero).
* Tests nuevos: `tests/Feature/Consultations/` (`ConsultationStoreTest`, `ConsultationUpdateTest`, `ConsultationIndexTest`,
  `ConsultationShowTest`, `ConsultationLockTest` y `ConsultationConcurrencyTest`), `PermissionsSyncCommandTest`,
  `ConsultationsPhase4MigrationTest` y `tests/Concerns/InteractsWithConsultations`. Cambiados: `RoleAccessTest`, `RouteBindingTest`,
  `PostmanCleanupCommandTest`, `AppointmentQueueTest` y `DemoAgendaCommandTest`.
* Documentación: `consultas.md` (§6.12), `turnos.md`, `pendientes-de-diseno.md`, `02-requisitos/README.md`, la guía de Postman y la
  colección `syncvet-fase-04-consultas`, `setup.md` (`permissions:sync`), `backend/README.md`, los `CLAUDE.md` (el presupuesto por
  entrega de R-100) y `testing.md`.

**Endpoints.** `GET`/`POST consultations` y `GET`/`PUT|PATCH consultations/{consultation}`, sin `DELETE` (405). Rutas 78 → 82,
permisos 135 → 136 (`consultations.show-clinical`), migraciones 36 → 37. En la FASE 3, campos aditivos: `consultation` en el detalle
del turno y `has_consultation` en el listado y en la cola.

**Tests.** Corrida de `composer test` del cierre (2026-11-20, sobre `36aef4c`): 1590 casos, 1572 en verde y 18 omitidos (de MySQL),
15776 aserciones, en una sola corrida. Frente al cierre de la F3 del frontend (R-098: 1511 casos y 15085 aserciones): 79 casos más.

* `ConsultationStoreTest` (22): desde el turno fichado (atendido y su auditoría), el dueño actual, el atendido el mismo día y otro
  día, los otros estados, el 409 (también por el índice único), otro veterinario, la agenda sin veterinario, el administrador, el
  veterinario del pedido, el fallecido, sin turno, sin sucursal, el veterinario obligatorio y activo, uno de turno o paciente, otra
  clínica, `consulted_at`, la recepción, los códigos y los campos del turno.
* `ConsultationUpdateTest` (10, con *data provider*: 26 casos): la edición parcial, el administrador, otra ficha, cerrada o anulada,
  los límites y sus bordes, `consulted_at`, los campos fijos, la recepción y el `DELETE`.
* `ConsultationIndexTest` (9): los últimos 30 días, los filtros, el turno, los filtros mal formados, la vista de la recepción y la
  clínica, otra clínica y el conteo de consultas (con el administrador y la recepción; y el de los turnos con `has_consultation`).
* `ConsultationShowTest` (6): la vista del administrador (también sin el permiso en su rol, por la Gate), del veterinario, de la
  recepción y de un rol propio; otra clínica.
* `ConsultationLockTest` (5): el orden de bloqueo al abrir, con y sin turno, y al editar; el deadlock y la espera vencida; el turno
  que cambia mientras se bloquea.
* `ConsultationConcurrencyTest` (1, solo MySQL): dos aperturas simultáneas del mismo turno, una 201 y otra 409.
* `PermissionsSyncCommandTest` (4): una base de la FASE 3 queda igual a la foto; dos corridas; los usuarios conservan sus roles;
  `--dry-run`.
* `ConsultationsPhase4MigrationTest` (3): las columnas, el índice con nombre propio y, en MySQL, la bajada y la subida.

**MySQL, Newman y la base local** (en la implementación, sobre `f9f95d4`; la revisión no tocó la migración, la concurrencia ni la
colección). MySQL (`syncvet_test_mysql`), los tests afectados: 125 de 125, 1146 aserciones. Newman, `syncvet-fase-04-consultas`: 31
pedidos y 54 aserciones, sin fallas; la limpieza borró 2 consultas, 2 turnos, la agenda, el paciente, el cliente, la ficha y el
servicio, y dio de baja el usuario. `permissions:sync` en la base local: el permiso en el administrador y el veterinario, no en la
recepción; la segunda corrida, sin cambios.

**Mutaciones** (4, en la implementación; antes del presupuesto de R-100), todas detectadas y revertidas con el editor:

* Sacar el paso del turno a atendido: `ConsultationStoreTest::test_opening_from_a_checked_in_appointment_marks_it_attended_in_the_same_transaction`.
* Aceptar el turno atendido otro día: `test_an_appointment_attended_another_day_is_rejected`.
* Dar `consultations.show-clinical` a la recepción: `ClinicRolesTest` (la foto), `PermissionsSyncCommandTest`, `ConsultationIndexTest` y
  `ConsultationShowTest`.
* Saltear el fallecido: `test_a_deceased_patient_gets_no_new_consultation`.

**Desvíos del diseño y precisiones.**

1. Un `veterinarian_id` distinto del que corresponde (el del turno o la ficha de quien abre) responde 422, en lugar de ignorarse.
2. La vista administrativa no lleva las claves clínicas (no van en `null`); el motivo es administrativo; en el listado, lo clínico es
   el diagnóstico.
3. `RoleAccessTest` acepta un 403 de negocio (`APPOINTMENT_NOT_YOURS`, `CONSULTATION_NOT_YOURS`) como permitido: el permiso ya pasó.
4. Código compartido con la FASE 3: `withRetries` con un mensaje de conflicto propio; las reglas de `attend`, en
   `AppointmentOwnership` (revisión), sin cambios de comportamiento; `AppointmentQueueTest` reconoce la consulta de la cola con el
   `exists`.
5. Deuda 2 en `Consultation`: la temperatura con `decimal:1` y los signos vitales enteros.
6. `has_consultation`, solo en el listado y la cola; el detalle del turno lleva `consultation`.
7. Editar: el administrador por su rol; un rol propio con `consultations.update` y sin ficha, 403 `CONSULTATION_NOT_YOURS`.
8. `permissions:sync --dry-run`, que no estaba en el diseño.

**Revisión.** Sin hallazgos en el índice único, el orden de bloqueo, `consulted_at` ni la salida de textos clínicos. Correcciones: el
permiso clínico se pregunta una vez por pedido (el test de conteo, también con la recepción) y las reglas de `attend` pasaron a
`AppointmentOwnership`. Las dos corridas completas anteriores tuvieron una falla de detalle cada una (`AppointmentQueueTest`, la
consulta de la cola con el `exists`; `DemoAgendaCommandTest`, que arma `AppointmentTransitionService` a mano), corregidas; la corrida
del cierre, en verde en una sola corrida (R-100).
