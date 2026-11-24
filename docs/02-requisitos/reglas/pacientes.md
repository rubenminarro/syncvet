# Reglas de negocio: pacientes

Reglas vigentes del backend (antes, `backend/CLAUDE.md` §6; el índice, en su
[§6](../../../backend/CLAUDE.md#6-reglas-de-negocio-vigentes)). Las referencias `§N` son de
[`backend/CLAUDE.md`](../../../backend/CLAUDE.md); las `§6.N`, de los archivos de esta carpeta. Si una regla contradice al
código, manda el código.

### 6.2 Pacientes

* Código `PAC-…`. Alta en `clients/{client}/patients` (el dueño sale de la URL; un `client_id` en el cuerpo se ignora). El
  código lo asigna la serie: `code` en el alta o en la edición → 422 (`prohibited`).
* `custom_fields` no forma parte de la API (R-015): los Requests no lo aceptan (si llega, se ignora) y la respuesta no lo
  muestra. La columna y el cast del modelo quedan.
* La raza debe ser de la especie (`PATIENT_BREED_SPECIES_MISMATCH`, 422).
* Especie y raza: globales o de la clínica, activas y no eliminadas. Al editar se acepta también la que el paciente ya tiene
  aunque se haya inactivado (el formulario la reenvía); otra inactiva → 422. El alta y la edición las vuelven a buscar con un
  bloqueo compartido (`sharedLock`) dentro de su transacción: una baja simultánea de la especie o la raza responde 422 en
  `species_id` o `breed_id` (§6.3).
* El dueño se cambia solo con `POST patients/{patient}/change-owner` (`client_id` es `prohibited` en el PATCH); el mismo
  dueño → `PATIENT_SAME_OWNER` (422). Las consultas guardan el dueño de ese momento (`consultations.client_id`). El dueño
  de un paciente fallecido también se cambia (corrige errores de carga). El cambio va en una transacción, con el paciente y después
  los dos clientes bloqueados (§3); si el dueño nuevo se eliminó entre la validación y el bloqueo, 422 en `client_id`. Un turno de
  ese paciente toma el dueño nuevo cuando se le reenvía el paciente o el cliente (§6.10).
* Paciente fallecido (`deceased_at`): solo cambian la fecha de fallecimiento y las notas (`PATIENT_DECEASED`, 422). Los
  demás campos pueden llegar con el valor que ya tienen (el frontend manda el formulario completo): se rechaza solo si
  cambian, comparados con los casts del modelo (R-021). Los dos listados, `patients` y `clients/{client}/patients`, los
  ocultan salvo `include_deceased=1`. `deceased_at` llega en UTC (o con su desplazamiento) y se guarda en la hora de
  `America/Asuncion` (el cast `AppDateTime`, §3; antes, `PatientService`), y sale en UTC con `Z`.
* `deceased_at` no es anterior a `birth_date` (la que llega en el pedido o la que el paciente ya tiene), comparando el día en
  la hora de Asunción; si lo es, 422 en `deceased_at` (R-021).
* No se elimina con consultas, aplicaciones o turnos (`PATIENT_IN_USE`, 409): se registra el fallecimiento. El uso se revisa dentro
  de la transacción de la baja, con la fila del paciente bloqueada (§3).
* `microchip` único en la clínica, de hasta 30 caracteres. Dos altas o ediciones simultáneas con el mismo microchip: una entra y
  la otra recibe el 422 de la regla `unique` en `microchip`, nunca 500 (la violación del índice se traduce en `PatientService`,
  deuda 22). La baja agrega `//deleted_<id>_<timestamp>` entero:
  `patients.microchip` admite 60 caracteres (30 del chip y 21 más el id del sufijo, con ids de hasta 9 dígitos).
* El listado `GET patients` trae el dueño de cada paciente, `client` con `id` y `display_name` (entrega front-01, R-065), cargado
  con el `with` del listado, sin N+1. `PatientResource` lo saca solo si la relación está cargada (`whenLoaded`): el listado de un
  cliente (`clients/{client}/patients`) y los pacientes del detalle del cliente no lo traen. El detalle del paciente
  (`ShowPatientResource`) lo reemplaza por el suyo, con `phone`, `email` y `deleted`, en el mismo lugar de las claves. Un dueño
  eliminado se muestra igual (la relación es `withTrashed`).
* **Foto** (`patients/{patient}/photo`: subir con `POST`, ver con `GET`, borrar con `DELETE`; permisos `patients.upload-photo`,
  `patients.show-photo` y `patients.delete-photo`). La suben y la borran el recepcionista y el veterinario; la ven los tres roles.
  JPG, PNG o WEBP de hasta 5 MB, por el contenido del archivo, no por la extensión (`UploadPatientPhotoRequest`); otro tipo →
  422; más grande → 413 `PAYLOAD_TOO_LARGE`. La imagen se vuelve a codificar con GD en su mismo formato (`ImageReencoder`,
  R-027): se pierden los metadatos (EXIF, también el GPS) y lo que venga pegado después de la imagen, y la orientación del EXIF
  se aplica a los píxeles. Si GD no la decodifica → 422; de más de 25 megapíxeles → 422, antes de decodificar. GD con JPEG, PNG y
  WEBP es obligatoria (`docs/04-desarrollo/setup.md`). Disco propio `patient_photos` (`storage/app/private/patient-photos`; ningún
  disco tiene `serve`), en `clinics/{clinic_id}/patients/{patient_id}/{uuid}.{ext}` con la extensión del tipo detectado, sin
  redimensionar (`PatientPhotoService`). `photo_path` no es asignable en masa: solo lo cambia el Service. Ver, borrar o
  reemplazar usan `photo_path` solo si es un archivo de la carpeta del paciente; si no, 404 `PATIENT_PHOTO_NOT_FOUND` (al
  reemplazar, el archivo ajeno no se borra) y un aviso en el log (deuda 15: borrarla no limpia esa referencia). Reemplazar o
  borrar lee `photo_path` con la fila del paciente bloqueada y borra el archivo anterior después del commit (si el borrado falla,
  aviso en el log, y lo levanta `files:prune-orphans`, §6.8); si la transacción falla, se borra el nuevo. Sin foto, o con
  `photo_path` y sin archivo (aviso en el log) → 404 `PATIENT_PHOTO_NOT_FOUND`. Un paciente fallecido también sube y borra la
  foto (no es un dato clínico). La baja del paciente deja el archivo. La respuesta muestra `has_photo` (`photo_path !== null`),
  nunca `photo_path`.
* **Imágenes privadas compartidas** (2.5): lo común de la foto y del logo de la clínica (§6.8) vive en `App\Support\PrivateImageStore`
  (guardar, reemplazar después del commit, validar la ruta contra la carpeta del dueño y descargar con sus cabeceras) y en
  `App\Http\Requests\PrivateImageRequest` (tipo por el contenido, 413, megapíxeles y la imagen vuelta a codificar). La foto mantiene
  sus respuestas, códigos, mensajes y rutas.
* **Aplicaciones** (FASE 4, entrega 4.4; `PatientApplicationService`; con una consulta, las reglas de
  [`consultas.md`](consultas.md)): vacunas, desparasitaciones internas y externas y medicamentos del paciente.
  * **Listar** (`GET patients/{patient}/applications`, `patient-applications.index`: los tres roles, también la recepción): de la más
    reciente a la más vieja por `applied_at` e id, paginado (`per_page` de 1 a 100); filtros `type` (uno o varios) y `from`/`to` sobre
    `applied_at` (una fecha sola es el día entero de Asunción; sin tope de días; sin ellos, todas). Las anuladas aparecen, marcadas
    (`cancelled`, `cancelled_at`, `cancelled_by` y `cancellation_reason`). También de un paciente fallecido. Sin N+1.
  * **Cargar** (`POST patients/{patient}/applications`, `patient-applications.store`: el administrador y el veterinario; la recepción,
    no): `type`, `name` (2 a 200) y `applied_at` (un instante, no futuro); opcionales `consultation_id`, `lot_number` (50),
    `lot_expires_at` (fecha), `dose` (50), `route` (30), `dose_number` (1 a 255), `next_dose_due_at` (fecha, **posterior** al día de
    aplicación en Asunción) y `notes` (1000). Un paciente **fallecido** no recibe aplicaciones (422 `PATIENT_DECEASED`). El veterinario
    de la aplicación es el de la consulta; sin consulta, la ficha de quien la carga (o `null`). Auditada.
  * **Anular** (`POST patient-applications/{application}/cancel`, `patient-applications.cancel`: el administrador y el veterinario;
    decisión del usuario en la 4.4): con `cancellation_reason` (3 a 500). Ya anulada, 422 `PATIENT_APPLICATION_NOT_CANCELLABLE`. **Con
    consulta:** solo abierta (cerrada, 422 `CONSULTATION_NOT_EDITABLE`: se anula con la consulta), por su veterinario o el
    administrador (otro, 403 `CONSULTATION_NOT_YOURS`). **Sin consulta:** el administrador; el veterinario, solo la que cargó él el
    mismo día en Asunción (si no, 403 `PATIENT_APPLICATION_NOT_YOURS`). No se borra: queda visible, marcada.
  * Al anular una consulta, sus aplicaciones se anulan con ella ([`consultas.md`](consultas.md)). La próxima dosis
    (`next_dose_due_at`) la usarán los recordatorios (FASE 7).
* **Historial del paciente** (FASE 4, entrega 4.6; pregunta 18): `GET patients/{patient}/history`, sin permiso propio: exige
  `consultations.index` **y** `patient-applications.index` (los dos; con uno solo, 403 `FORBIDDEN`); los tienen los tres roles.
  * Sus consultas y sus aplicaciones **juntas**, de la más reciente a la más vieja por su fecha (`consulted_at` de la consulta,
    `applied_at` de la aplicación); en la misma fecha, la consulta antes que la aplicación, y después el id mayor. De toda la clínica
    (no de la sucursal actual). Paginado de a **20** por defecto (`per_page` de 1 a 100, `page`), con `meta.pagination`; sin otros
    filtros. Sin N+1.
  * Cada elemento: `type` (`consultation` o `application`), `occurred_at` (la fecha, en UTC con `Z`), `cancelled` (anulada) y el
    elemento en `consultation` o en `application` (el otro, `null`). La consulta, con el resumen de su listado (código, fecha, estado,
    sucursal, paciente, cliente, veterinario, turno y motivo) y, con `consultations.show-clinical` (el administrador y el veterinario),
    además el **diagnóstico** y el **peso** (`weight_kg`, para ver su evolución); la recepción no los recibe (las claves no están). La
    aplicación, como en su listado, para todos.
  * Las anuladas aparecen, marcadas. También el historial de un paciente **fallecido** (pregunta 11). Un paciente de otra clínica o
    eliminado, 404. Solo lectura: no bloquea nada.
