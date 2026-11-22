# FASE 4 — Historia clínica

Etapa: Atención. Estado: **DISEÑO APROBADO** el 2026-11-20 (R-099), en `feature/back-4-diseno` (desde `main` en `6fb4805`, con la
F3 del frontend integrada, R-098), con las 18 [preguntas del diseño](#preguntas-del-diseño) respondidas. Las entregas 4.1 (la consulta,
R-100), 4.2 (cierre, enmiendas y anulación, R-101), 4.3 (los servicios usados, R-102) y 4.4 (las aplicaciones, R-103), COMPLETADAS; sigue
la 4.5. Los resultados, en [`fase-04-resultados.md`](fase-04-resultados.md).
El estado vigente de cada fase está en
[`docs/03-plan-desarrollo/README.md`](../../README.md).

Este documento reemplaza al esqueleto de la fase (el objetivo, las 6 entregas del roadmap y 3 preguntas) y al borrador
`f4-historia-clinica.md` (pregunta 1). Las 3 preguntas del esqueleto quedan respondidas por las del diseño: qué se edita en una
consulta cerrada y quién reabre (la 4), la consulta sin turno (sí, en [el vínculo con el turno](#el-vínculo-con-el-turno)) y los
campos obligatorios (la 5). El orden de las entregas cambia el del roadmap (pregunta 3).

## Objetivo

El veterinario atiende: abre la consulta desde el turno fichado (o sin turno, en una urgencia), registra el motivo, la anamnesis, el
examen físico con el peso y los signos vitales, el diagnóstico, el tratamiento y las indicaciones, lo que usó (los servicios) y las
vacunas y desparasitaciones aplicadas, adjunta estudios y cierra la consulta. La consulta cerrada no se borra ni se reescribe; el
historial del paciente reúne sus consultas y sus aplicaciones. Es el eslabón "Consulta → Diagnóstico y plan → Servicios + productos
usados + aplicaciones → Cierre" de la [regla de oro](../../../00-vision/principios.md): lo que se registra acá lo usan después el
stock (FASE 5), los documentos (FASE 6), los recordatorios (FASE 7) y la facturación (FASE 8).

## Alcance

**Dentro de la FASE 4:**

* La consulta (`CON-`): abrirla desde un turno fichado (y marcarlo atendido) o sin turno; editarla mientras está abierta; cerrarla;
  la corrección de una cerrada con enmiendas ([pregunta 4](#preguntas-del-diseño)); anularla con motivo; el listado y el
  detalle.
* Los servicios usados en la consulta (`consultation_items` de tipo `service`, con el precio del catálogo y el estado de cobro
  `pending`), solo con la consulta abierta.
* Las aplicaciones (`patient_applications`): vacunas, desparasitaciones internas y externas y medicamentos, con el lote y la próxima
  dosis copiados como texto; con o sin consulta, y anulables con motivo ([pregunta 12](#preguntas-del-diseño)).
* Los permisos nuevos de la fase y el comando que los actualiza en una base que ya existe ([permisos por rol](#permisos-por-rol)).
* Los adjuntos de la consulta (`consultation_attachments`), en un disco privado, como la foto del paciente.
* El historial del paciente: sus consultas y sus aplicaciones en orden, para toda la clínica.
* El soporte de la limpieza de Newman (`postman:cleanup`) y la colección de la fase.

**Fuera de la FASE 4** (según el roadmap):

| Tema | Fase | En la FASE 4 |
|---|---|---|
| Productos, stock, lotes y su consumo desde la consulta | 5 (Inventario) | Sin ítems de producto ni descuento de stock ([pregunta 9](#preguntas-del-diseño)); la aplicación guarda el lote como texto |
| Recetas, certificados, constancias en PDF y plantillas | 6 (Documentos) | Sin recetas ([pregunta 10](#preguntas-del-diseño)): las indicaciones quedan como texto en la consulta; `patient_documents` no se toca |
| Recordatorios de la próxima dosis y avisos al tutor | 7 (Recordatorios) | Se guarda `next_dose_due_at`; nadie lo envía todavía |
| Cobro, factura y SIFEN | 8 y 9 | El ítem queda `pending`; nada lo factura |
| Internación, cirugías con parte quirúrgico, laboratorio integrado | Sin fase (fuera del MVP) | — |
| Las pantallas de la historia clínica | Frontend, después de esta fase (D2, R-058) | Solo la API |

## Lo que ya existe

**El esquema** (del paquete base, R-001; deuda 2: los modelos salieron del esquema y se revisan al usarlos):

| Tabla | Columnas que importan | Observación |
|---|---|---|
| `consultations` | `code`, `branch_id`, `patient_id`, `client_id` (el dueño de ese momento), `appointment_id` (nullable y **única**), `veterinarian_id`, `consulted_at` (`datetime`), `reason` (255), `history` (texto, la anamnesis), `weight_kg` (6,2), `temperature_c` (4,1), `heart_rate`, `respiratory_rate`, `mucous_membranes` (50), `capillary_refill_time` (20), `body_condition_score`, `physical_exam`, `diagnosis`, `plan` (textos), `status` (`ConsultationStatus`: `open`, `closed`, `cancelled`), `created_by`, `closed_at`/`closed_by`, `cancelled_at`/`cancelled_by`/`cancellation_reason` | Índices: `clinic_id`+`code` única, `patient_id`+`consulted_at`, `branch_id`+`consulted_at`, `veterinarian_id`+`consulted_at`, `clinic_id`+`status`. `closed_at` y `cancelled_at` son `timestamp`: la regla vigente pide `datetime` para la hora de negocio (§3, deuda 17). No hay "tratamiento" ni "indicaciones" separados: solo `plan` ([pregunta 6](#preguntas-del-diseño)) |
| `consultation_items` | `type` (`ConsultationItemType`: `service`, `product`), `service_id`, `product_id`, `product_lot_id`, `veterinarian_id` (honorarios), `description`, `quantity` (12,3), `unit_price`, `discount`, `total` (14,2), `tax_rate`, `billing_status` (`pending`, `invoiced`, `no_charge`) | `cascadeOnDelete` con la consulta |
| `consultation_attachments` | `path`, `original_name`, `mime_type`, `size_bytes`, `uploaded_by` | Sin `deleted_at`; `cascadeOnDelete` |
| `patient_applications` | `patient_id`, `consultation_id` (nullable), `consultation_item_id`, `product_id`, `type` (`PatientApplicationType`: `vaccine`, `internal_deworming`, `external_deworming`, `medication`), `name`, `lot_number`, `lot_expires_at`, `dose`, `route`, `applied_at`, `veterinarian_id`, `dose_number`, `next_dose_due_at` (fecha), `notes`, `created_by` | Sin estado ni anulación ([pregunta 12](#preguntas-del-diseño)). Índices: `patient_id`+`type`+`applied_at`, `clinic_id`+`next_dose_due_at` |
| `patient_documents`, `document_templates` | Recetas y documentos emitidos | FASE 6 |

**Los modelos** `Consultation`, `ConsultationItem`, `ConsultationAttachment` y `PatientApplication` existen, con `BelongsToClinic` y
`Auditable` (sus tipos ya están en `AuditLog::AUDITABLE_TYPES`), sin Service, Request, Resource ni rutas. `DocumentSeries::CONSULTATION`
(`CON-`) existe. Las bajas ya los cuentan como uso: `ClientService` y `PatientService` (409 `*_IN_USE` con consultas),
`VeterinarianService` (consultas, ítems, aplicaciones y documentos), `UserService` (`created_by`, `closed_by`, `cancelled_by`,
`uploaded_by`) y `ServiceCatalogService` (el nombre fijo de un servicio usado en `consultation_items`).

**Los permisos** ya están en `RolesAndPermissionsSeeder::PERMISSIONS` y en la foto `tests/Fixtures/clinic-roles.json`:

| Permiso | Administrador | Recepción | Veterinario |
|---|---|---|---|
| `consultations.index`, `consultations.show` | Sí (`Gate::before`) | **Sí** ([pregunta 7](#preguntas-del-diseño)) | Sí |
| `consultations.store`, `consultations.update`, `consultations.close` | Sí | No | Sí |
| `consultations.reopen`, `consultations.cancel` | Sí | No | No |
| `consultation-items.store`, `.update`, `.destroy` | Sí | No | Sí |
| `consultation-attachments.index`, `.store`, `.destroy` | Sí | No | Sí |
| `patient-applications.index` | Sí | Sí | Sí |
| `patient-applications.store` | Sí | No | Sí |

**El turno** (FASE 3, [`turnos.md`](../../../02-requisitos/reglas/turnos.md)): `checked_in → attended` con `POST
appointments/{appointment}/attend` (administrador y veterinario; el veterinario, solo sus turnos y los de agendas sin veterinario, 403
`APPOINTMENT_NOT_YOURS`). **Atendido y cancelado son finales**: no hay "deshacer el atendido". La FASE 3 dejó una
[propuesta](fase-03-agenda-y-turnos.md#relación-con-la-consulta-fase-4) para esta fase (abajo, en el vínculo con el turno).

**El paciente** ([`pacientes.md`](../../../02-requisitos/reglas/pacientes.md)): `weight_kg` propio; un fallecido solo cambia la fecha
y las notas (`PATIENT_DECEASED`); el cambio de dueño no toca las consultas (guardan `client_id` de ese momento).

**La auditoría** ([`auditoria.md`](../../../02-requisitos/reglas/auditoria.md)): `Auditable` guarda `old_values` y `new_values` de cada
cambio; el detalle (`audit-logs/{id}`) los muestra al administrador; los archivos se auditan como `"(archivo)"`.

## Diferencias entre los documentos y lo que existe

Cada una fue una pregunta del diseño; todas quedaron resueltas con sus respuestas (R-099).

| # | Diferencia | Resuelta por la pregunta |
|---|---|---|
| C1 | El nombre del documento: el pedido dice `f4-historia-clinica.md`; los del backend son `fase-NN-…` (ya existe `fase-04-historia-clinica.md`, con el roadmap enlazado) y el paso 2 ([`prompts/2-diseno.md`](../../prompts/2-diseno.md)) diseña en `fase-NN-*.md` | 1 |
| C2 | La D4 ([decisiones pendientes](../../../01-arquitectura/decisiones/README.md)) recomienda **reabrir** la consulta cerrada con permiso; `backend/CLAUDE.md` §3 dice que una consulta cerrada es **inmutable** y se corrige con otro registro; el seeder ya tiene `consultations.reopen` | 4 |
| C3 | El pedido de este diseño pide "tratamiento" e "indicaciones"; la tabla tiene un solo `plan` | 6 |
| C4 | El roadmap pone "ítems de servicio **y de producto** (sin stock)" en la 4.2, pero los productos se crean recién en la 5.1 (no hay API de productos) | 9 |
| C5 | El roadmap pone el cierre en la 4.5 y el criterio de cierre de la fase es "turno fichado → consulta → cierre"; recetas en la FASE 6 pero el pedido las nombra entre "lo que el roadmap ponga en la F4" | 3 y 10 |
| C6 | El seeder le da a la recepción `consultations.index` y `.show` (todo el contenido clínico); el pedido pide cuidar los datos sensibles | 7 |
| C7 | `closed_at` y `cancelled_at` de `consultations` son `timestamp`; la regla vigente pide `datetime` en la hora de negocio (deuda 17) | Migración de la 4.1 (sin pregunta: es la regla) |
| C8 | El esqueleto pide abrir la consulta "desde un turno **fichado**"; el pedido de este diseño habla de "un turno **atendido**" que abre o se liga a una consulta | 2 |

## Decisiones comunes de la fase

Lo que vale para todas las entregas.

* **Arquitectura** (§4): `ConsultationController` (y uno por recurso hijo) → Form Request → `ConsultationService`,
  `ConsultationItemService`, `ConsultationAttachmentService`, `PatientApplicationService` → modelos → `XResource`/`ShowXResource` →
  `ApiResponse`. `ConsultationException` (`error_code` y HTTP) para lo de negocio. Rutas con nombre y su permiso (`InteractsWithApiRoutes`,
  `RoleAccessTest`, `RouteBindingTest`, `ValidationTranslationsTest`, `VoseoTest`, `SnakeCaseKeysTest`).
* **Clínica y sucursal**: todo con `BelongsToClinic` y fallo cerrado; una consulta, un paciente o un turno de otra clínica → 404 (o 422
  en un `*_id` del cuerpo). La consulta guarda la sucursal: la del turno, o la actual del usuario si no hay turno (sin sucursal actual,
  422 `CONSULTATION_NO_BRANCH`, como `APPOINTMENT_QUEUE_NO_BRANCH`). El historial y el listado son de **toda la clínica** (el paciente
  es de la clínica, no de la sucursal) con el filtro `branch_id`.
* **Fechas**: `consulted_at`, `closed_at`, `cancelled_at`, `applied_at` con `AppDateTime` (en la base, hora de Asunción; en la API,
  UTC con `Z`); los filtros con `ApiDateTime` (`from`/`to`: una fecha sola es el día completo de Asunción). `next_dose_due_at` y
  `lot_expires_at`, fechas (`yyyy-MM-dd`).
* **Textos**: `TrimStrings` como siempre; límites en caracteres (`mb_strlen`): `reason` 255, los textos clínicos hasta 10000
  ([pregunta 13](#preguntas-del-diseño)), `cancellation_reason` de 3 a 500 (como el turno).
* **Números del examen**: `weight_kg` de 0,01 a 9999,99; `temperature_c` de 30,0 a 45,0; `heart_rate` de 1 a 400;
  `respiratory_rate` de 1 a 200; `body_condition_score` de 1 a 9 ([pregunta 13](#preguntas-del-diseño)); todos opcionales mientras
  está abierta. Decimales con punto o coma, como el peso del paciente.
* **Dinero** (ítems): `decimal(14,2)` con BCMath, PYG a 0 decimales, el IVA del servicio (§3).
* **Concurrencia** (§3, se suma al orden de la FASE 3: clínica → veterinario → servicio → agenda → paciente → cliente → turno →
  **consulta**): abrir desde un turno bloquea el turno (`lockForUpdate`) y revisa su estado y que no tenga consulta; el índice único
  `appointment_id` es la segunda guarda (su violación → 409 `CONSULTATION_ALREADY_EXISTS`, por el nombre del índice, §3). Editar,
  cerrar, enmendar o anular: la consulta con `lockForUpdate` antes de leer su estado. Ítems y aplicaciones: la consulta con
  `lockForUpdate` (abierta) y el servicio con `sharedLock`. Los reintentos de `AppointmentService::withRetries` (40001/1213 hasta 3
  veces; 1205 → 409 `CONFLICT`).
* **Listados**: `ListFilters` (`search` hasta 100, literal con `SearchTerm`; `per_page` de 1 a 100, 10 por defecto; `page`); 422 con
  un filtro mal formado, nunca 500.
* **Errores nuevos** (todos en `ConsultationException`, mensajes con voseo):

| `error_code` | HTTP | Cuándo |
|---|---|---|
| `CONSULTATION_ALREADY_EXISTS` | 409 | El turno ya tiene una consulta (también la carrera del índice único) |
| `CONSULTATION_NOT_EDITABLE` | 422 | Editar, sumar un ítem, un adjunto o una aplicación a una consulta cerrada o anulada |
| `CONSULTATION_NOT_CLOSABLE` | 422 | Cerrar una que no está abierta |
| `CONSULTATION_CLOSE_INCOMPLETE` | 422 | Cerrar sin lo obligatorio (`errors` por campo, [pregunta 5](#preguntas-del-diseño)) |
| `CONSULTATION_NOT_CANCELLABLE` | 422 | Anular una anulada (o una con ítems facturados, desde la FASE 8) |
| `CONSULTATION_NOT_YOURS` | 403 | Un veterinario que edita, cierra o enmienda la consulta de otra ficha ([pregunta 8](#preguntas-del-diseño)) |
| `CONSULTATION_NOT_AMENDABLE` | 422 | Enmendar una consulta que no está cerrada (abierta o anulada) |
| `CONSULTATION_APPOINTMENT_NOT_TODAY` | 422 | Abrir la consulta de un turno ya atendido fuera del día del turno ([pregunta 2](#preguntas-del-diseño)) |
| `CONSULTATION_NO_BRANCH` | 422 | Sin turno y sin sucursal actual |
| `PATIENT_APPLICATION_NOT_CANCELLABLE` | 422 | Anular una aplicación ya anulada (la 4.4) |
| `PATIENT_DECEASED` | 422 | Abrir una consulta de un paciente fallecido ([pregunta 11](#preguntas-del-diseño)); el mismo código de la FASE 1 |
| Reusados | | `APPOINTMENT_INVALID_TRANSITION` (422: el turno no está fichado), `APPOINTMENT_NOT_YOURS` (403), `RESOURCE_NOT_FOUND` (404), `VALIDATION_ERROR` (422), `CONFLICT` (409), `FORBIDDEN` (403), `PAYLOAD_TOO_LARGE` (413, adjuntos) |

## Modelo de datos

### La consulta

| Campo | Tipo | Reglas |
|---|---|---|
| `code` | `CON-000001` | `DocumentSequenceService::create(DocumentSeries::CONSULTATION)`; nunca `max(id)+1`; puede tener huecos |
| `branch_id` | FK | La del turno; sin turno, la sucursal actual del usuario |
| `patient_id` | FK | El del turno, o el del pedido; no fallecido ([pregunta 11](#preguntas-del-diseño)) |
| `client_id` | FK | El dueño **actual** del paciente al abrir; no cambia con un cambio de dueño posterior |
| `appointment_id` | FK única, nullable | El turno del que se abrió ([vínculo con el turno](#el-vínculo-con-el-turno)) |
| `veterinarian_id` | FK | La ficha de quien la abre; si no tiene ficha (el administrador), la del turno o la del pedido (obligatoria) |
| `consulted_at` | `datetime` | Ahora al abrir; editable mientras está abierta, no futuro, no anterior al fichado del turno |
| `reason` | 255 | El motivo (del turno, `appointments.reason`, si lo tiene) |
| `history` | texto | Anamnesis |
| Examen físico | `weight_kg`, `temperature_c`, `heart_rate`, `respiratory_rate`, `mucous_membranes`, `capillary_refill_time`, `body_condition_score`, `physical_exam` | Las existentes |
| `diagnosis` | texto | |
| `treatment` | texto | `plan` renombrada ([pregunta 6](#preguntas-del-diseño)): el tratamiento, texto clínico |
| `instructions` | texto | **Nueva**: las indicaciones para el tutor (las usará la receta de la FASE 6) |
| `status` | `ConsultationStatus` | `open` → `closed`; `open` o `closed` → `cancelled`; nada sale de `cancelled` |
| `closed_at`, `closed_by` | `datetime`, FK | Al cerrar |
| `cancelled_at`, `cancelled_by`, `cancellation_reason` | `datetime`, FK, texto | Al anular |

El peso de la consulta actualiza el del paciente al cerrar, si tiene peso ([pregunta 14](#preguntas-del-diseño)); el historial muestra
el peso de cada consulta, y con eso la evolución.

### Las enmiendas

Una consulta cerrada se corrige con enmiendas ([pregunta 4](#preguntas-del-diseño)). `consultation_amendments`: `id`, `clinic_id`, `consultation_id` (FK, `restrictOnDelete`), `field` (uno de los campos clínicos, o `null`
para una nota general), `content` (texto, de 3 a 10000), `reason` (de 3 a 500), `created_by`, `created_at` (`datetime`); sin
`updated_at` ni baja: **inmutable**, como `AuditLog`. Índice `consultation_id`+`created_at`. Auditable.

### Migraciones previstas

| Entrega | Migración | Qué hace |
|---|---|---|
| 4.1 | `…_adjust_consultations_for_phase_4` | `closed_at` y `cancelled_at` a `datetime` (C7); `plan` renombrada a `treatment` y `instructions` nueva (texto, nullable; pregunta 6); el índice `appointment_id` única (un turno, como máximo una consulta) se conserva con un nombre propio (para reconocer su violación, §3). Con su test en MySQL (`MySqlSessionTimezoneTest` y el de la columna) |
| 4.2 | `…_create_consultation_amendments_table` | Las enmiendas (pregunta 4) |
| 4.4 | `…_add_cancellation_to_patient_applications` | Anular aplicaciones (pregunta 12): `cancelled_at` (`datetime`), `cancelled_by`, `cancellation_reason` |
| 4.5 | — | `consultation_attachments` ya alcanza; los archivos en el disco privado `consultation_attachments` (como `patient_photos`) |

Los permisos no van en una migración (§3: las migraciones no llaman a modelos): van en el seeder y en el comando de
[permisos por rol](#permisos-por-rol).

Sin migraciones para los ítems (la tabla alcanza) ni para el historial. Las tablas están vacías en producción (no hay producción):
las migraciones no migran datos.

## El vínculo con el turno

La propuesta de la FASE 3, con lo que este diseño agrega y las preguntas 2, 3 y 12:

* **Abrir desde un turno fichado**: `POST consultations` con `appointment_id`. En una transacción, con el orden de bloqueo de arriba:
  el turno tiene que estar **fichado** (si no, 422 `APPOINTMENT_INVALID_TRANSITION`; uno ya atendido, ver abajo), sin consulta (si no,
  409 `CONSULTATION_ALREADY_EXISTS`) y ser del veterinario que pide (las reglas de `attend`: 403 `APPOINTMENT_NOT_YOURS`). La consulta
  toma el paciente, el cliente (el dueño actual del paciente), la sucursal, el veterinario de la agenda (o la ficha de quien pide, si
  la agenda no tiene) y el motivo. **El turno pasa a atendido en la misma transacción** (`attended_at` = ahora), con su auditoría:
  "atendido" significa que el veterinario lo atendió, aunque la consulta siga abierta.
* **Un turno ya atendido sin consulta** (alguien usó `attend` y después quiere la historia clínica, [pregunta 2](#preguntas-del-diseño)):
  la consulta se puede abrir después, ligada al turno, **el mismo día del turno** (en Asunción; otro día, 422
  `CONSULTATION_APPOINTMENT_NOT_TODAY`), por quien podría haberlo atendido (las reglas de `attend`); el turno no cambia. Un turno
  tiene **como máximo una consulta** (el índice único `appointment_id`; si ya la tiene, 409 `CONSULTATION_ALREADY_EXISTS`).
* **`POST appointments/{appointment}/attend` sigue** para los turnos sin historia clínica (peluquería, una vacuna rápida que se carga
  como aplicación sin consulta, pregunta 12).
* **Consulta sin turno** (urgencia, o alguien que llegó sin turno): `POST consultations` sin `appointment_id`, con `patient_id` (y
  `veterinarian_id` si quien pide no tiene ficha). No crea un turno. Un paciente fallecido no recibe una consulta nueva (422
  `PATIENT_DECEASED`, [pregunta 11](#preguntas-del-diseño)); una abierta antes del fallecimiento se puede cerrar, y su historial
  sigue visible.
* **Deshacer el atendido**: la FASE 3 no lo tiene (atendido es final) y esta fase no lo suma. **Anular la consulta no vuelve el turno
  a fichado**: el turno queda atendido (la atención ocurrió; lo que se anula es el registro). Si el usuario pide poder deshacer el
  atendido, va como una pregunta nueva con su propia transición.
* **Cancelar un turno con consulta**: imposible, porque el turno ya está atendido (final).
* **Lo que el turno muestra**: `ShowAppointmentResource` suma `consultation` (`id`, `code`, `status`) o `null` (aditivo: no rompe al
  frontend), y el listado de turnos y la cola, `has_consultation` (bool), para el botón "Abrir la consulta" o "Ver la consulta" de la
  F4 del frontend.

## Integridad clínica

* **Abierta**: la editan (con `PATCH`, parcial) su veterinario (la ficha de la consulta) y el administrador; otro veterinario, 403
  `CONSULTATION_NOT_YOURS` ([pregunta 8](#preguntas-del-diseño)). Los ítems, los adjuntos y las aplicaciones se suman y se quitan.
* **Cerrar** (`POST consultations/{id}/close`, los mismos que editan): exige el **motivo y el diagnóstico** (si falta alguno, 422
  `CONSULTATION_CLOSE_INCOMPLETE` con `errors` por campo, [pregunta 5](#preguntas-del-diseño)); fija `closed_at` y `closed_by` (quién
  la cerró); copia el peso al paciente si la consulta lo tiene (pregunta 14). Desde ahí **no cambia ningún campo clínico, ítem,
  adjunto ni aplicación** (422 `CONSULTATION_NOT_EDITABLE`). El cierre es la "firma": queda quién y cuándo. **No hay cierre
  automático** ([pregunta 15](#preguntas-del-diseño)): el listado con `status=open` muestra las pendientes de cierre.
* **Corregir una cerrada** ([pregunta 4](#preguntas-del-diseño), la C2 y la D4): una consulta cerrada no se edita: se corrige con
  enmiendas, registros aparte ligados a la consulta; lo escrito nunca cambia. Cada enmienda tiene autor, hora y motivo, es inmutable
  y se muestra con la consulta. No hay reapertura: `consultations.reopen` sale del seeder (abajo).
* **Anular** (`POST consultations/{id}/cancel`, `consultations.cancel`, el administrador): con motivo; abierta o cerrada; la consulta
  queda visible en el historial, marcada "Anulada", con todo su contenido. Sus ítems pasan a `no_charge` (no se cobran) y sus
  aplicaciones se anulan con ella ([pregunta 12](#preguntas-del-diseño)). El turno sigue atendido. Desde la FASE 8, no se anula con un
  ítem facturado.
* **Nunca se borra**: no hay `DELETE consultations` (405, `HttpVerbsTest`). Un adjunto se quita solo con la consulta abierta (la baja
  física del archivo y de la fila, auditada como `"(archivo)"`).
* **Auditoría**: `Auditable` en la consulta, los ítems, las aplicaciones, las enmiendas y los adjuntos (sus rutas, como `"(archivo)"`).
  Guarda los textos clínicos (`old_values` y `new_values`), y su detalle lo ve **solo el administrador**, como hoy
  ([pregunta 16](#preguntas-del-diseño)); la recepción no ve la auditoría de las consultas.

## Permisos por rol

Aprobado (preguntas 4, 7, 8 y 12):

| Acción | Administrador | Veterinario | Recepción |
|---|---|---|---|
| Ver el listado y el detalle de una consulta | Sí, completo | Sí, completo, de toda la clínica | **Solo lo administrativo** ([pregunta 7](#preguntas-del-diseño)): código, fecha, paciente, cliente, veterinario, estado y los servicios para cobrar; sin los textos clínicos, el examen, las enmiendas, los adjuntos ni la auditoría |
| Abrir | Sí (indica el veterinario si no tiene ficha) | Sí: sus turnos y los de agendas sin veterinario; sin turno, con su ficha | No |
| Editar, cerrar, sumar ítems, adjuntos y aplicaciones (abierta) | Sí | La de su ficha (pregunta 8) | No |
| Enmendar (cerrada) | Sí | La de su ficha | No |
| Anular una consulta | Sí | No | No |
| Anular una aplicación | Sí | No (se precisa en la 4.4 si el veterinario anula las suyas) | No |
| Ver el historial del paciente | Sí | Sí | Las aplicaciones y el resumen administrativo de las consultas (sin diagnóstico ni textos clínicos) |
| Ver y descargar adjuntos | Sí | Sí | No |

**Cambios en `RolesAndPermissionsSeeder`** (y en la foto `tests/Fixtures/clinic-roles.json`):

| Permiso | Entrega | Roles |
|---|---|---|
| `consultations.show-clinical` (nuevo) | 4.1 | **Administrador y veterinario**; la recepción, no (ajuste de R-099). El administrador edita, cierra, enmienda y anula, y ve la auditoría con los textos clínicos: sin este permiso cerraría una consulta sin ver su contenido. Lo tiene asignado en su rol (el seeder y `permissions:sync` le dan todos los permisos), no solo por `Gate::before`. Se consulta con `can()` (pasa por la Gate), nunca con `hasPermissionTo()`. Sin él, el listado, el detalle y el historial salen con el Resource administrativo |
| `consultations.amend` (nuevo) | 4.2 | Veterinario (la de su ficha) y el administrador |
| `consultations.reopen` (se quita) | 4.2 | Sin ruta: la corrección es la enmienda |
| `patient-applications.cancel` (nuevo) | 4.4 | El administrador |

La recepción conserva `consultations.index` y `.show` (para cobrar) y `patient-applications.index`. El historial reusa
`consultations.index` y `patient-applications.index`, sin permiso propio.

**Las clínicas que ya existen**: los roles se siembran una vez y no se vuelven a sincronizar solos. La 4.1 suma un comando
`permissions:sync` (idempotente: crea los permisos que faltan, quita los que salieron de `PERMISSIONS`, le da al
administrador todos los permisos y sincroniza los demás roles con `clinicRoles()`, como el seeder), con su test (una base con los
permisos de la FASE 3 queda igual a la foto, con `consultations.show-clinical` en el administrador y en el veterinario y no en la
recepción; correrlo dos veces no cambia nada; los usuarios conservan sus roles). Cada entrega que cambia un permiso lo corre en su validación, y el
[`setup.md`](../../../04-desarrollo/setup.md) lo suma a los pasos de actualización. Es un comando y no una migración porque las
migraciones no llaman a modelos (§3).

Los datos sensibles nunca van a un log ni a una traza.

**Adjuntos** ([pregunta 17](#preguntas-del-diseño)): imágenes (JPEG, PNG, WebP) y PDF, hasta 10 MB cada uno y 20 por consulta; el tipo
se valida **por el contenido**, no por la extensión ni por el `Content-Type` del pedido; se guardan en el disco privado
`consultation_attachments` y se descargan **siempre por el endpoint con permiso** (`consultation-attachments.index`), nunca por una
URL pública; la respuesta no incluye la ruta del archivo.

## Endpoints

Formato de las demás fases: `{success, message, data, meta, errors, error_code}`; fechas con hora en UTC con `Z`; `meta.pagination`
en los listados.

| Método y ruta | Permiso | Qué hace | Errores |
|---|---|---|---|
| `GET consultations` | `consultations.index` | Listado: `patient_id`, `client_id`, `veterinarian_id`, `branch_id`, `status` (uno o lista, `status[]`), `appointment_id`, `from`/`to` (sobre `consulted_at`, hasta 31 días; sin ellos, los últimos 30 días), `search` (código, nombre o código del paciente, cliente). Por `consulted_at` descendente e id | 422 en un filtro |
| `GET consultations/{consultation}` | `consultations.show` | El detalle (`ShowConsultationResource`: el examen, los textos, los ítems, las aplicaciones, los adjuntos sin ruta, las enmiendas, el turno, quién la creó, cerró o anuló); sin `consultations.show-clinical`, solo lo administrativo | 404 |
| `POST consultations` | `consultations.store` | Abrir: con `appointment_id` (desde el turno) o con `patient_id` (sin turno); opcionales `veterinarian_id`, `consulted_at`, `reason` y los campos clínicos. 201 con el detalle | 403 `APPOINTMENT_NOT_YOURS`; 404; 409 `CONSULTATION_ALREADY_EXISTS`; 422 `APPOINTMENT_INVALID_TRANSITION`, `CONSULTATION_APPOINTMENT_NOT_TODAY`, `PATIENT_DECEASED`, `CONSULTATION_NO_BRANCH`, `VALIDATION_ERROR` |
| `PATCH consultations/{consultation}` | `consultations.update` | Editar los campos clínicos y `consulted_at` (solo abierta) | 403 `CONSULTATION_NOT_YOURS`; 422 `CONSULTATION_NOT_EDITABLE`; 409 `CONFLICT` |
| `POST consultations/{consultation}/close` | `consultations.close` | Cerrar (motivo y diagnóstico obligatorios; `closed_by`) | 403 `CONSULTATION_NOT_YOURS`; 422 `CONSULTATION_NOT_CLOSABLE`, `CONSULTATION_CLOSE_INCOMPLETE` |
| `POST consultations/{consultation}/amendments` | `consultations.amend` (nuevo) | Enmienda: `content`, `reason` y opcional `field` | 403 `CONSULTATION_NOT_YOURS`; 422 `CONSULTATION_NOT_AMENDABLE` |
| `POST consultations/{consultation}/cancel` | `consultations.cancel` | Anular con `cancellation_reason` | 422 `CONSULTATION_NOT_CANCELLABLE` |
| `POST consultations/{consultation}/items` | `consultation-items.store` | Un servicio: `service_id`, `quantity`, opcionales `unit_price` (si no, el del catálogo), `discount`, `veterinarian_id`; activo | 422 `CONSULTATION_NOT_EDITABLE`, servicio inactivo |
| `PATCH consultation-items/{item}` / `DELETE consultation-items/{item}` | `.update` / `.destroy` | Cambiar o quitar (abierta) | 422 `CONSULTATION_NOT_EDITABLE` |
| `GET consultations/{consultation}/attachments/{attachment}` | `consultation-attachments.index` | Descargar (el tipo por el contenido) | 404 |
| `POST consultations/{consultation}/attachments` | `consultation-attachments.store` | Subir (multipart): imagen o PDF, el tipo por el contenido, hasta 10 MB y 20 por consulta ([pregunta 17](#preguntas-del-diseño)) | 413 `PAYLOAD_TOO_LARGE`; 422 |
| `DELETE consultation-attachments/{attachment}` | `.destroy` | Quitar (abierta) | 422 `CONSULTATION_NOT_EDITABLE` |
| `GET patients/{patient}/applications` | `patient-applications.index` | Las aplicaciones del paciente (`type`, `from`/`to` sobre `applied_at`) | 404 |
| `POST patients/{patient}/applications` | `patient-applications.store` | Una aplicación: `type`, `name`, `applied_at`, opcionales `consultation_id` (abierta, del mismo paciente), `lot_number`, `lot_expires_at`, `dose`, `route`, `dose_number`, `next_dose_due_at` (posterior a `applied_at`), `notes` | 422 |
| `POST patient-applications/{application}/cancel` | `patient-applications.cancel` (nuevo) | Anular con `cancellation_reason` (la 4.4) | 422 `PATIENT_APPLICATION_NOT_CANCELLABLE`, `CONSULTATION_NOT_EDITABLE` (la de una consulta cerrada se anula con la consulta) |
| `GET patients/{patient}/history` | `consultations.index` y `patient-applications.index` | El historial: consultas (con su resumen: código, fecha, veterinario, motivo, diagnóstico, peso, estado; sin `consultations.show-clinical`, sin el diagnóstico) y aplicaciones, juntas, de la más reciente a la más vieja, paginado de a 20 (hasta 100); también de un fallecido | 404 |

Rutas nuevas: unas 16 (78 → ~94); permisos, 135 → 137 (tres nuevos y `consultations.reopen` fuera); con `scopeBindings()` en las anidadas.

## Entregas

Cada una sale de la anterior (`feature/back-4-N-…`), con su revisión y su cierre (los pasos 4 a 7 de
[`prompts/README.md`](../../prompts/README.md)). El orden cambia el del roadmap ([pregunta 3](#preguntas-del-diseño)): el cierre y la
corrección van en la 4.2, antes que los ítems, porque son la integridad de lo que se escribe desde la 4.1.

| Entrega | Contenido | Migraciones | Tests (principales) | Newman | Estado |
|---|---|---|---|---|---|
| 4.1 | La consulta: abrir desde un turno fichado (y marcarlo atendido), desde uno atendido el mismo día o sin turno; editar abierta; listado y detalle (el Resource administrativo sin `consultations.show-clinical`); `consultation` en el detalle del turno y `has_consultation` en el listado y la cola; `consultations.show-clinical` y el comando `permissions:sync`; `postman:cleanup` borra las consultas de la corrida | La de C7 y C3 | `ConsultationStoreTest` (desde el turno, ya atendido hoy y otro día, con consulta, otro veterinario, fallecido, sin sucursal), `ConsultationUpdateTest` (otra ficha, límites), `ConsultationIndexTest` y `ConsultationShowTest` (filtros, N+1; la vista completa del administrador y del veterinario, la administrativa de la recepción), `ConsultationLockTest` y `ConsultationConcurrencyTest` (dos aperturas del mismo turno: una 201, otra 409; solo MySQL), `PermissionsSyncCommandTest`, `RoleAccessTest`, `RouteBindingTest`, `ClinicIsolation`, la foto de los roles | `syncvet-fase-04-consultas` (nueva) | COMPLETADO (R-100, [resultado](fase-04-resultados.md#entrega-41)), en `feature/back-4-1-consulta` |
| 4.2 | Cierre (motivo y diagnóstico, `closed_by`, el peso al paciente), enmiendas y anulación; `consultations.amend` y sin `consultations.reopen` | Enmiendas | `ConsultationCloseTest`, `ConsultationAmendmentTest`, `ConsultationCancelTest` (el turno sigue atendido), la inmutabilidad (cada ruta de escritura con una cerrada → 422) | La de consultas, ampliada | COMPLETADO (R-101, [resultado](fase-04-resultados.md#entrega-42)), en `feature/back-4-2-cierre` |
| 4.3 | Los servicios usados (ítems de tipo `service`) | — | `ConsultationItemTest` (precio del catálogo, descuento, totales con BCMath, servicio inactivo, cerrada), el uso del servicio (`SERVICE_NAME_NOT_EDITABLE`) | La de consultas | COMPLETADO (R-102, [resultado](fase-04-resultados.md#entrega-43)), en `feature/back-4-3-servicios` |
| 4.4 | Las aplicaciones (vacunas, desparasitaciones y medicamentos), con y sin consulta, y su anulación; `patient-applications.cancel` | La de la anulación | `PatientApplicationTest` (con y sin consulta, próxima dosis, fallecido, cerrada, anular, anuladas con la consulta) | `syncvet-fase-04-aplicaciones` (nueva) | COMPLETADO (R-103, [resultado](fase-04-resultados.md#entrega-44)), en `feature/back-4-4-aplicaciones` |
| 4.5 | Los adjuntos | — | `ConsultationAttachmentTest` (el tipo por el contenido con una extensión que miente, el tamaño, el tope por consulta, la descarga con y sin permiso, el disco privado, `files:prune-orphans`) | La de consultas | PENDIENTE (la siguiente) |
| 4.6 | El historial del paciente | — | `PatientHistoryTest` (consultas y aplicaciones juntas, orden, paginación, anuladas marcadas, el peso de cada consulta, fallecido visible, la vista de la recepción, N+1) | La de aplicaciones | PENDIENTE |

**En cada entrega**: las reglas nuevas en un archivo `docs/02-requisitos/reglas/consultas.md` (y `pacientes.md` para el historial), la
guía de Postman y la colección; la suite con el presupuesto por entrega; MySQL solo si toca migraciones o concurrencia (la 4.1, la
4.2 y la 4.4). **Al cierre de la fase**: `composer test` y `composer test:mysql` en serie, `composer audit`, Newman con **todas** las
colecciones (`node docs/05-pruebas/newman-todas.mjs`, que limpia cada corrida) y la regresión del frontend sobre `main` (la API de la
FASE 3 no cambia, salvo los campos aditivos del turno).

### 4.1 — Pendiente para el cierre

Nada: la 4.1 quedó cerrada en R-100 ([resultado](fase-04-resultados.md#entrega-41), con los desvíos y la validación).

### 4.2 — Pendiente para el cierre

Nada: la 4.2 quedó cerrada en R-101 ([resultado](fase-04-resultados.md#entrega-42), con los desvíos, la validación y lo pendiente de
la 4.4: las aplicaciones de una consulta anulada se anulan con ella).

### 4.3 — Pendiente para el cierre

Nada: la 4.3 quedó cerrada en R-102 ([resultado](fase-04-resultados.md#entrega-43), con los desvíos y la validación).

### 4.4 — Pendiente para el cierre

Nada: la 4.4 quedó cerrada en R-103 ([resultado](fase-04-resultados.md#entrega-44), con la decisión del usuario sobre quién anula, los
desvíos y la validación).

### 4.5 — Pendiente para el cierre

Implementada en `feature/back-4-5-adjuntos` (desde `2fa780c`, el cierre de la 4.4; commit WIP, 2026-11-22); falta la revisión (con la
corrida completa, R-100) y el cierre (su decisión va en `registro/R-101-150.md`). Rutas 91 → 94 (`POST
consultations/{consultation}/attachments`, `GET consultations/{consultation}/attachments/{attachment}` y `DELETE
consultation-attachments/{attachment}`); permisos (137) y migraciones (39), sin cambios: `consultation-attachments.*` ya estaban en el
seeder (el administrador y el veterinario) y `consultation_attachments` ya alcanza. Disco nuevo `consultation_attachments`
(`config/filesystems.php`). Sin `error_code` nuevos. Las reglas, en [`consultas.md`](../../../02-requisitos/reglas/consultas.md).

**Para avisar al usuario (no se cambió `php.ini`):** el PHP de Laragon (`php-8.3.30`, el único: lo usan la consola, Apache y
`php artisan serve`) tiene `upload_max_filesize=8M` y `post_max_size=10M` (`memory_limit=512M`, `max_file_uploads=20`). No alcanzan
para 10 MB: un archivo de más de 8 MB lo corta PHP antes de Laravel y la API responde 413 `PAYLOAD_TOO_LARGE` (el caso está cubierto),
así que en este equipo el límite real es 8 MB. Para los 10 MB, `upload_max_filesize` de al menos `10M` y `post_max_size` de al menos
`11M` (el multipart suma los encabezados); en producción, lo mismo en su PHP y en el límite del servidor web (FASE 10).

Desvíos y precisiones (para revisar en el cierre):

1. **El tope de 20** responde 422 `VALIDATION_ERROR` con el mensaje en `file` (sin un `error_code` nuevo: el diseño dice "422").
2. **El archivo no se vuelve a codificar** (a diferencia de la foto y el logo): es un estudio clínico y se guarda tal cual. La
   protección es el tipo por el contenido (finfo, `mimetypes`), el nombre generado, el disco privado y la descarga con `nosniff`, una
   CSP `default-src 'none'; sandbox` y `Cache-Control: private, no-store`.
3. **La descarga es `attachment`** (la foto es `inline`), con el nombre saneado y **la extensión del tipo detectado** (un PNG subido como
   `estudio.pdf` se descarga como `estudio.png`); `original_name` guarda el nombre subido saneado, tal cual, para mostrar. El nombre
   con tildes va en `filename*` (UTF-8) y en ASCII en `filename`.
4. **El saneado del nombre:** sin carpetas (`/` y `\`), sin caracteres de control ni `: * ? " < > |`, con los espacios juntos, sin
   puntos ni espacios en los bordes y hasta 255 caracteres (conserva la extensión); vacío o con UTF-8 inválido, "adjunto". Las barras
   (y el `%` del nombre ASCII de respaldo, que Laravel quita), además, harían fallar el encabezado `Content-Disposition` (Symfony
   las rechaza).
5. **El archivo se escribe antes de la transacción** (como `PrivateImageStore`): la ficha y el estado se revisan con la consulta
   bloqueada, y si fallan (403, 422, el tope, un deadlock agotado) el archivo se borra. Quitar borra el archivo después del commit; uno
   con una ruta fuera de la carpeta de su consulta no se toca (y la descarga responde 404, con un aviso en el log).
6. **El 404 de un archivo que falta** usa `RESOURCE_NOT_FOUND` (la foto tiene `PATIENT_PHOTO_NOT_FOUND`; acá no hace falta otro código).
7. **Una consulta cerrada o anulada conserva sus adjuntos y se descargan** (la colección lo prueba con la cerrada).
8. **La auditoría:** `ConsultationAttachment` pasa a `Auditable` (con `path` como `"(archivo)"` y oculto en `toArray`), y
   `consultation_attachment` se suma a `AuditLog::AUDITABLE_TYPES` (`AuditableTypesTest`).
9. **`ClinicIsolation`:** no hay un test con ese nombre; el aislamiento por clínica de los adjuntos (subir, descargar por la consulta
   ajena y por la propia con el id ajeno, y quitar: 404, sin cambios ni archivos) está en `RouteBindingTest`, como el de los ítems y las
   aplicaciones.
10. **MySQL no se corrió:** no hay bloqueos de un orden nuevo (la consulta con `lockEditable()` y después el adjunto, como consulta →
    ítem) ni migraciones.
11. **`postman:cleanup`** borra los adjuntos de las consultas de la corrida modelo por modelo (con su auditoría) y sus archivos después
    de la transacción, como las fotos; el resumen suma "adjuntos".
12. **Los tests usan archivos reales** (`Illuminate\Http\UploadedFile` sobre un temporal): `UploadedFile::fake()` informa el tipo por la
    extensión del nombre y no probaría la validación por el contenido.

Validación de la implementación (2026-11-22, R-100: sin la suite completa, que va en la revisión): los tests relacionados
(`ConsultationAttachmentTest`, nuevo, 28 casos; `ConsultationImmutabilityTest`, `RouteBindingTest`, `RoleAccessTest`,
`PostmanCleanupCommandTest`, `PruneOrphanFilesTest` y los que recorren rutas, modelos, mensajes y documentos ficticios), 255 casos y
6436 aserciones, sin fallas. Newman, `syncvet-fase-04-consultas` ampliada: 66 pedidos y 132 aserciones, sin fallas (la primera corrida
tuvo una falla del script de la colección, corregida); la limpieza borró el adjunto que quedaba con su archivo, sin datos residuales.
Mutaciones: validar el tipo por la extensión (la detectan los 5 casos de
`ConsultationAttachmentTest::test_the_type_comes_from_the_content_not_from_the_name`) y descargar sin el permiso (la detectan
`ConsultationAttachmentTest::test_download_with_and_without_permission` y 5 de `RoleAccessTest`); las dos, revertidas con el editor.

## Preguntas del diseño

Respondidas por el usuario el 2026-11-20 (R-099). Las opciones y las recomendaciones, en la propuesta (`7962f97`).

| # | Pregunta | Respuesta |
|---|---|---|
| 1 | ¿Dónde vive el diseño? (C1) | En este archivo; `f4-historia-clinica.md` se borra y sus enlaces pasan acá |
| 2 | ¿Consulta de un turno ya atendido sin consulta? (C8) | Sí, después, ligada al turno, el mismo día del turno; el turno no cambia. Un turno tiene como máximo una consulta (índice único) |
| 3 | ¿El orden de las entregas? (C5) | El propuesto, con el cierre en la 4.2 |
| 4 | ¿Cómo se corrige una consulta cerrada? (C2, D4) | Con enmiendas, registros aparte ligados a la consulta; lo escrito nunca cambia. Sin reapertura |
| 5 | ¿Qué es obligatorio para cerrar? | El motivo y el diagnóstico |
| 6 | ¿"Tratamiento" e "indicaciones"? (C3) | `plan` pasa a `treatment` (texto clínico) y se suma `instructions` (para el tutor) |
| 7 | ¿Qué ve la recepción? (C6) | Solo lo administrativo, sin los textos clínicos ni la auditoría de las consultas; además del seeder, un comando que actualiza los permisos de las clínicas que ya existen, con su test |
| 8 | ¿Quién edita y cierra una abierta? | Su veterinario y el administrador; se registra quién la cerró |
| 9 | ¿Ítems de producto? (C4) | No: sin productos en la FASE 4 |
| 10 | ¿Recetas? (C5) | No: sin recetas en la FASE 4 |
| 11 | ¿Consulta de un paciente fallecido? | No se le abre una nueva; su historial sigue visible |
| 12 | ¿Aplicaciones anulables y sin consulta? | Sí a las dos; al anular la consulta, sus aplicaciones se anulan con ella |
| 13 | ¿Límites? | Los propuestos: textos hasta 10000; temperatura de 30 a 45 °C (la clínica no atiende exóticos); cardíaca 1–400; respiratoria 1–200; condición corporal 1–9 |
| 14 | ¿El peso de la consulta actualiza el del paciente? | Sí (al cerrar); el historial muestra la evolución |
| 15 | ¿Cierre automático? | No |
| 16 | ¿La auditoría guarda los textos clínicos? | Sí, visible solo para el administrador |
| 17 | ¿Qué adjuntos? | Imágenes y PDF, hasta 10 MB y 20 por consulta; el tipo se valida por el contenido (no la extensión) y la descarga va siempre por un endpoint con permiso, nunca por una URL pública |
| 18 | ¿Qué incluye el historial? | Consultas y aplicaciones juntas, paginadas |

## Criterio de cierre

* El flujo turno fichado → consulta (el turno queda atendido) → servicios y aplicaciones → cierre funciona de punta a punta, también
  la consulta sin turno; una consulta cerrada no se edita (se corrige con enmiendas) y una anulada queda visible.
* El administrador y el veterinario ven el contenido clínico (`consultations.show-clinical`); la recepción, solo lo administrativo.
* `composer test` y `composer test:mysql` en serie, en verde; `composer audit` sin avisos; Newman con todas las colecciones, sin
  fallas y sin dejar datos; las reglas en `docs/02-requisitos/reglas/consultas.md`.

## Prompt de implementación (paso 4)

Una entrega por vez, con este diseño aprobado. La 4.1:

```text
Implementa la entrega 4.1 de la FASE 4 según docs/03-plan-desarrollo/fases/backend/fase-04-historia-clinica.md (diseño aprobado, R-099),
en feature/back-4-1-consulta.

- Migración: closed_at y cancelled_at a datetime; plan → treatment; instructions nueva; el índice único de appointment_id con nombre propio.
- ConsultationService: abrir desde un turno fichado (lo pasa a atendido en la misma transacción), desde uno atendido el mismo día (el turno
  no cambia) o sin turno; copia client_id del dueño actual; código CON-; no a un paciente fallecido. Editar solo abierta, su veterinario
  o el administrador. Listado y detalle; sin consultations.show-clinical, el Resource administrativo.
- consultations.show-clinical en el seeder para el administrador y el veterinario (la recepción, no; se consulta con can()) y el
  comando permissions:sync (el administrador con todos los permisos) con su test.
- consultation en el detalle del turno y has_consultation en el listado y la cola; postman:cleanup con las consultas.

Sigue CLAUDE.md. Corre la suite e infórmame. No hagas commit.
```
