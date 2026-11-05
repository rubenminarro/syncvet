# FASE 1 — Clientes y pacientes

Etapa: Base. Estado: **PENDIENTE**, con el diseño aprobado el 2026-11-05 (R-016). El estado vigente de cada fase está en
[`docs/roadmap/README.md`](../roadmap/README.md).

## Objetivo

Cerrar con tests el módulo de ejemplo que trae el paquete, sumar la administración de especies y razas y la foto del paciente, y
dejar los mensajes de la API en voseo y sin claves de traducción crudas.

## Entregas

El alcance inicial tenía cuatro entregas (1.1 a 1.4). El diseño las reordena en cinco: los mensajes y las traducciones van primero,
porque cambian lo que afirman los tests de las demás. Cada entrega recorre los pasos 4 a 7 y deja la suite en verde.

| Entrega | Contenido | Antes era | Estado |
|---|---|---|---|
| 1.1 | Traducciones de validación (`lang/es`), voseo de los mensajes existentes, `PUT` y `PATCH` con la misma semántica | Nueva (R-015) | PENDIENTE |
| 1.2 | Clientes: tests de cada regla de `CLAUDE.md` 6.1 y dígito verificador del RUC | 1.1 | PENDIENTE |
| 1.3 | Pacientes: tests de cada regla de `CLAUDE.md` 6.2 y cambio de dueño; sin `custom_fields` en la API | 1.2 | PENDIENTE |
| 1.4 | ABM de especies y razas: `is_active`, `SoftDeletes`, nombres únicos, Resource, `insert()`/`upsert()` y deuda 8 | 1.3 | PENDIENTE |
| 1.5 | Foto del paciente en disco privado | 1.4 | PENDIENTE |

## Preguntas de negocio

Las respuestas se anotan con la fecha y pasan a [`docs/decisions/registro.md`](../decisions/registro.md).

| # | Pregunta | Respuesta | Fecha |
|---|---|---|---|
| 1 | ¿Un paciente puede tener más de un tutor? | Un solo tutor responsable por paciente en el MVP; se revisa después del piloto (R-015) | 2026-11-05 |
| 2 | ¿Qué campos personalizados quieren las clínicas? | Postergados: `custom_fields` queda en la base, sin API ni pantalla, hasta que una clínica real los pida. Los Requests dejan de aceptarlos (R-015) | 2026-11-05 |
| 3 | ¿Se valida el dígito verificador del RUC? | Sí, cuando el documento tiene la forma `NNNNNNN-D`, con el cálculo de `ClinicService`. Una cédula sin guion no se valida (R-015) | 2026-11-05 |
| 4 | ¿Cómo se dan de baja especies y razas? | `is_active` las oculta de las altas nuevas (los pacientes que las usan las siguen mostrando); `SoftDeletes` solo si nunca se usaron, 409 si están en uso (R-015) | 2026-11-05 |
| 5 | ¿Una clínica puede repetir el nombre de una especie o raza global? | No: nombres únicos sin distinguir mayúsculas, frente a los globales y a los propios; tampoco se repiten entre globales (R-015) | 2026-11-05 |
| 6 | ¿Quién administra el catálogo y cómo son las rutas de razas? | El administrador y el veterinario. Razas anidadas en `species/{species}/breeds`, con los permisos de especie; test de `scopeBindings()` y cierre de la deuda 8 (R-015) | 2026-11-05 |
| 7 | ¿Cómo es la foto del paciente? | JPG, PNG o WEBP de hasta 5 MB, disco privado, descarga por la API con `nosniff`, sin redimensionar. La suben el recepcionista y el veterinario. Permiso = nombre de la ruta (R-015) | 2026-11-05 |
| 8 | ¿Quién elimina clientes y pacientes? | Solo el administrador. `CLIENT_IN_USE` y `PATIENT_IN_USE` se prueban con un rol propio con esos permisos (R-015) | 2026-11-05 |

Las preguntas que dejó el diseño, ya respondidas, están al final, en [Preguntas del diseño](#preguntas-del-diseño).

## Regla de oro aplicada

Análisis con [`docs/project/principios.md`](../project/principios.md). Clientes y pacientes ya existen; se analizan las piezas nuevas.

| Pregunta | Especie / raza propia | Foto del paciente |
|---|---|---|
| ¿Quién la crea? | Administrador o veterinario de la clínica. Las globales, solo `SpeciesSeeder` | Recepcionista, veterinario o administrador |
| ¿Quién la modifica? | Los mismos; una global no se modifica (`CATALOG_GLOBAL_NOT_EDITABLE`, 403) | Se reemplaza con otra subida |
| ¿Quién la usa? | El alta y la edición del paciente; más adelante, la consulta, las aplicaciones (vacunas por especie) y los recordatorios | La ficha del paciente; más adelante, documentos y certificados (FASE 6) |
| Estados | Activa / inactiva (`is_active`) / eliminada (`SoftDeletes`) | Con foto / sin foto |
| ¿Qué depende de ella? | Pacientes (`species_id`, `breed_id`); las razas dependen de su especie | Nada |
| Impacto económico | Ninguno directo. Más adelante, servicios o productos por especie (a definir en la FASE 2) | Ninguno; ocupa disco |
| ¿Qué pasa si se cancela? | Se inactiva: no aparece en altas nuevas, los pacientes que la usan la siguen mostrando | — |
| ¿Qué pasa si se elimina? | Solo si nunca se usó (contando pacientes eliminados); si no, 409 | Se borra el archivo y `photo_path` queda nulo |
| Permisos | `species.index`, `species.store`, `species.update` y `species.destroy` (nuevo), también para las razas | `patients.upload-photo`, `patients.show-photo`, `patients.delete-photo` (nuevos) |
| `error_code` | `CATALOG_GLOBAL_NOT_EDITABLE` (403, existe), `SPECIES_IN_USE` y `BREED_IN_USE` (409, nuevos) | `PATIENT_PHOTO_NOT_FOUND` (404, nuevo), `PATIENT_DECEASED` (422, existe; ver pregunta 12) |
| Consulta, stock, facturación, recordatorios | La consulta muestra la especie del paciente aunque esté inactiva. Sin efecto en stock ni facturación | Ninguno |

## Diseño por entrega

### 1.1 Traducciones, voseo y `PUT`/`PATCH`

**Traducciones.** Hoy no existe `lang/` y el idioma es `es` (también el de respaldo), así que una regla sin mensaje propio devuelve la
clave cruda: `validation.string` (comprobado en el análisis). Pasa con `color.string`, `microchip.string`, `allergies.string` y
`notes.string` de los pacientes, entre otras.

| Pieza | Detalle |
|---|---|
| `lang/es/validation.php` | Todas las claves del `validation.php` en inglés del framework (`vendor/laravel/framework/src/Illuminate/Translation/lang/en`), incluidas las anidadas (`min.string`, `password.mixed`…), traducidas y con voseo donde haya imperativo. `attributes` con el nombre en español de cada campo de los Requests (`document_number` → "número de documento"). Sin paquetes nuevos |
| Otros archivos de `lang/es` | Solo los que use la API. `auth.php`, `passwords.php` y `pagination.php` no se usan hoy (los mensajes del login son propios): no se crean |
| Mensajes propios | Siguen mandando sobre `validation.php`; no se borran |

**Voseo** (R-004). Los mensajes que quedaron en tú o usted:

| Dónde | Hoy | Pasa a |
|---|---|---|
| `bootstrap/app.php` (dos lugares, `FORBIDDEN`) | No tienes permisos para realizar esta acción. | No tenés permisos para realizar esta acción. |
| `PatientService::delete` (`PATIENT_IN_USE`) | … Si falleció, registre la fecha de fallecimiento. | … Si falleció, registrá la fecha de fallecimiento. |
| `ClientMessages` (`first_name.prohibited`, `last_name.prohibited`) | Una empresa no lleva nombre: use la razón social. | Una empresa no lleva nombre: usá la razón social. |
| `ClientController::index` (`kind`) | El tipo de cliente debe ser person o company. | El mismo texto que `ClientMessages`: "… person (persona) o company (empresa)." |

No cambian `error_code` ni códigos HTTP.

**`PUT` y `PATCH`.** `apiResource` registra los dos verbos en la misma ruta y el mismo método del Controller. Los Requests de edición
ya usan `sometimes` y no distinguen el verbo: los dos son parciales. Se deja escrito y probado; `CLAUDE.md` §3 pasa a decir:
"Actualizaciones parciales con `PATCH`. `apiResource` también registra `PUT`, con la misma semántica parcial: no reemplaza el
recurso ni borra los campos que no se envían".

**Tests**

| Test | Qué verifica |
|---|---|
| `tests/Feature/Localization/ValidationTranslationsTest` | Cada clave del `validation.php` del framework existe en `lang/es/validation.php` (recursivo), ninguna queda en inglés igual a la original y cada `:placeholder` del original sigue presente |
| Ídem, reglas de los Requests | Recorre cada clase de `app/Http/Requests`, arma sus reglas con una clínica en contexto (y el modelo de la ruta cuando lo pide) y, para cada regla (texto u objeto: `Enum`, `Unique`, `Exists`, `RequiredIf`, `ProhibitedIf`, `Password`), comprueba que tenga mensaje propio o traducción. Una regla nueva sin mensaje hace fallar el test |
| Ídem, de punta a punta | Pedidos inválidos a cada endpoint con Request: ningún mensaje de `errors` empieza por `validation.` |
| `tests/Unit/VoseoTest` | Los textos de `app/`, `bootstrap/app.php` y `lang/es` no contienen formas de tú ni de usted de una lista cerrada (`tienes`, `puedes`, `debes`, `ingresa `, `registre`, `use la`…) |
| `tests/Feature/HttpVerbsTest` | `PUT` y `PATCH` parciales dan el mismo resultado en clientes y pacientes (los campos no enviados no cambian) |
| `ErrorResponseTest` | Se actualiza el texto del 403 |

### 1.2 Clientes

**Reglas nuevas.**

| Regla | Detalle |
|---|---|
| Dígito verificador del RUC | Si `document_number` tiene la forma `^\d+-\d$`, el dígito debe ser `ClinicService::rucCheckDigit(<número>)`. Regla `App\Rules\RucCheckDigit` en `StoreClientRequest` y `UpdateClientRequest`, para persona y empresa. Mensaje: "El RUC no es válido: revisá el número y el dígito verificador." (el de `clinic:create`). Una cédula sin guion o un documento con letras no se valida |

**Error a confirmar con un test (sufijo de baja).** `ClientService::delete` guarda `substr(document_number . '//deleted_<timestamp>', 0, 30)`.
Un documento de más de 10 caracteres pierde parte o todo el timestamp: con 20 caracteres, el sufijo queda en `//deleted_` y la segunda
baja del mismo documento choca con el único `(clinic_id, document_number)` y da 500. `PatientService::delete` hace lo mismo con
`microchip` (30): un chip ISO de 15 dígitos conserva 5 dígitos del timestamp y choca si se da de baja dos veces el mismo día. Si los
tests lo confirman, la corrección propuesta es una migración nueva que lleva `clients.document_number` y `patients.microchip` a 50
caracteres (20 o 30 más los 20 del sufijo) y quita el `substr` (pregunta 15).

**Endpoints:** sin cambios de ruta ni de permiso.

**Tests** (`tests/Feature/Clients`, con el rol `recepcionista`; las bajas, con un rol de test `baja-clientes` que tiene `clients.destroy`):

| Test | Reglas |
|---|---|
| `ClientStoreTest` | Persona por defecto; empresa con `business_name`; empresa con `first_name` o `last_name` → 422; persona con `business_name` → 422; `display_name` de cada tipo; documento y email obligatorios y únicos en la clínica; email en minúsculas; teléfono válido e inválido (`+?[1-9]\d{7,14}`); RUC con dígito válido (201) e inválido (422); cédula sin guion sin validar |
| `ClientUpdateTest` | Cambio de tipo sin facturas: limpia los campos del otro tipo; con facturas → 422 `CLIENT_KIND_NOT_EDITABLE`; únicos ignorando al propio cliente; RUC en la edición |
| `ClientDeleteTest` | Baja sin uso; el documento y el email quedan libres (se crea otro cliente con los mismos); con pacientes, consultas o facturas → 409 `CLIENT_IN_USE` (uno por caso); dos bajas del mismo documento largo (error de arriba); el recepcionista recibe 403 |
| `ClientSearchTest` | `search` por nombre, apellido, razón social, documento, teléfono, email, nombre de la mascota y código de la mascota; filtro `kind` (y 422 con un valor inválido); `meta.pagination` |
| `ClientShowTest` | El detalle trae sus pacientes con especie y raza |

Las consultas y facturas de prueba se insertan con un helper de tests (`InteractsWithClinicalRecords`) que crea las filas mínimas que
exigen sus claves foráneas, sin Services de fases futuras.

### 1.3 Pacientes

**Cambios.**

| Cambio | Detalle |
|---|---|
| Sin `custom_fields` en la API | Se quita de `StorePatientRequest`, `UpdatePatientRequest` y `PatientMessages`. Si llega, se ignora como cualquier campo desconocido. La columna y el cast del modelo quedan. Respuesta: ver pregunta 14 |
| Especie o raza inactiva en la edición | Las reglas `exists` exigen `is_active`. Un paciente con una especie o raza que se inactivó no podría guardarse si el formulario reenvía la misma (`PUT`). Regla: en `UpdatePatientRequest`, `species_id` y `breed_id` aceptan una activa **o la que el paciente ya tiene**. En el alta, solo activas |
| Voseo | El de `PATIENT_IN_USE` (1.1) |

**Endpoints:** sin cambios de ruta ni de permiso.

**Tests** (`tests/Feature/Patients`, con el rol `recepcionista`; la baja con un rol de test `baja-pacientes` que tiene `patients.destroy`):

| Test | Reglas |
|---|---|
| `PatientStoreTest` | Alta en `clients/{client}/patients`: el dueño sale de la URL (un `client_id` en el cuerpo no lo cambia); código `PAC-…` correlativo por clínica; raza de otra especie → 422 `PATIENT_BREED_SPECIES_MISMATCH`; especie o raza inactiva → 422; `code` en el cuerpo → 422; peso con más de 2 decimales, cero o más de 2000 → 422; fecha de nacimiento futura → 422; microchip único en la clínica; `custom_fields` se ignora |
| `PatientUpdateTest` | `client_id` → 422 (`prohibited`); cambiar la especie dejando una raza de la anterior → 422 `PATIENT_BREED_SPECIES_MISMATCH`; conservar una especie o raza inactiva que ya tenía → 200; elegir otra inactiva → 422 |
| `PatientChangeOwnerTest` | Cambio de dueño → 200 con el nuevo `client`; el mismo dueño → 422 `PATIENT_SAME_OWNER`; cliente de otra clínica o eliminado → 422; la consulta anterior conserva su `client_id`; el veterinario recibe 403 |
| `PatientDeceasedTest` | Con `deceased_at`, solo se editan `deceased_at` y `notes` (otro campo → 422 `PATIENT_DECEASED`); quitar la fecha lo reactiva; fecha futura → 422; el listado lo oculta salvo `include_deceased=1` |
| `PatientDeleteTest` | Baja sin uso; con consultas, aplicaciones o turnos → 409 `PATIENT_IN_USE` (uno por caso); el microchip queda libre; dos bajas del mismo chip el mismo día (error de 1.2); el recepcionista recibe 403 |
| `PatientSearchTest` | `search` por nombre, código, microchip y datos del dueño; filtro `species_id`; `meta.pagination`; listado anidado `clients/{client}/patients` solo con los de ese cliente |

### 1.4 Especies y razas

**Migración nueva** `2026_11_05_000001_add_soft_deletes_to_species_and_breeds_table.php`: `deleted_at` en `species` y `breeds`. Con
ella (y la de la pregunta 15, si se aprueba) el esquema pasa de 28 a 29 o 30 migraciones.

**Modelos.** `Species` y `Breed` con `SoftDeletes` y `Auditable` (como los demás datos maestros). Las reglas `exists` de los Requests
de pacientes agregan `whereNull('deleted_at')`.

**Reglas de negocio.**

| Regla | Detalle |
|---|---|
| Nombre único | Sin distinguir mayúsculas ni acentos (pregunta 9), sin espacios al inicio ni al final. Especie: frente a las globales y a las propias no eliminadas. Raza: lo mismo, **dentro de su especie** (pregunta 10: "Mestizo" existe en Canino y en Felino). Se compara en PHP (`Str::lower(Str::ascii(trim()))`) sobre los nombres que ve la clínica, que son pocos; así da igual en SQLite y en MySQL. Error de validación: 422 `VALIDATION_ERROR` en `name` |
| Concurrencia | Alta y cambio de nombre en `DB::transaction` con `lockForUpdate` sobre la fila de la clínica: dos altas simultáneas del mismo nombre no pasan las dos. Orden de bloqueo nuevo en `CLAUDE.md` §3: clínica → catálogo |
| Globales | Siguen sin editarse ni eliminarse (`CATALOG_GLOBAL_NOT_EDITABLE`, 403). Un test exige que `SpeciesSeeder::CATALOG` no repita nombres de especie ni de raza dentro de su especie con la misma comparación |
| Inactivar | `is_active = false`: no aparece en `GET species` por defecto y no se acepta en altas de pacientes. Se puede inactivar aunque esté en uso. Una raza propia se puede agregar a una especie global |
| Eliminar | Solo si ningún paciente, incluidos los eliminados, usa la especie o la raza; si no, 409 `SPECIES_IN_USE` o `BREED_IN_USE`. Al eliminar una especie propia se eliminan sus razas propias en la misma transacción (pregunta 18). Al eliminar se agrega `//deleted_<timestamp>` al nombre; los largos máximos (60 para especie y 80 para raza, con columnas de 80 y 100) dejan lugar al sufijo completo |
| Mover una raza | `species_id` no se edita (`prohibited`): se crea otra raza |

**Endpoints** (todos con `auth:sanctum` y `clinic`):

| Método | Ruta | Nombre | Permiso | Request | Respuesta | Errores |
|---|---|---|---|---|---|---|
| GET | `species` | `species.index` | `species.index` | `include_inactive` (bool) | 200, `SpeciesResource` con `breeds` | 422 `VALIDATION_ERROR` |
| POST | `species` | `species.store` | `species.store` | `StoreSpeciesRequest`: `name` (obligatorio, 2–60), `is_active` (bool) | 201, `SpeciesResource` | 422 `VALIDATION_ERROR` |
| PATCH/PUT | `species/{species}` | `species.update` | `species.update` | `UpdateSpeciesRequest`: `name`, `is_active` (`sometimes`) | 200 | 403 `CATALOG_GLOBAL_NOT_EDITABLE`; 404; 422 |
| DELETE | `species/{species}` | `species.destroy` | `species.destroy` (nuevo) | — | 200 | 403 `CATALOG_GLOBAL_NOT_EDITABLE`; 404; 409 `SPECIES_IN_USE` |
| GET | `species/{species}/breeds` | `species.breeds.index` | `species.index` | `include_inactive` | 200, `BreedResource` | 404 |
| POST | `species/{species}/breeds` | `species.breeds.store` | `species.store` | `StoreBreedRequest`: `name` (2–80), `is_active` | 201, `BreedResource` | 404; 422 |
| PATCH/PUT | `species/{species}/breeds/{breed}` | `species.breeds.update` | `species.update` | `UpdateBreedRequest`: `name`, `is_active`; `species_id` `prohibited` | 200 | 403 `CATALOG_GLOBAL_NOT_EDITABLE`; 404 (también si la raza es de otra especie); 422 |
| DELETE | `species/{species}/breeds/{breed}` | `species.breeds.destroy` | `species.destroy` | — | 200 | 403; 404; 409 `BREED_IN_USE` |

`SpeciesResource`: `id`, `name`, `is_active`, `is_global`, `breeds` (cuando están cargadas). `BreedResource`: `id`, `species_id`, `name`,
`is_active`, `is_global`. Sin `Show…Resource`: no hay detalle. `GET species` mantiene su forma actual (`id`, `name`, `breeds[].id`,
`breeds[].name`) y suma campos: el frontend no se rompe.

**Permisos.** Las cuatro rutas de razas van a `ROUTES_WITH_ANOTHER_PERMISSION` con el motivo "R-015: las razas usan los permisos de
especie" (la regla del nombre daría `breeds.*`). Nuevo `species.destroy` en `PERMISSIONS` (119 → 120 en esta entrega). El
`veterinario` suma `species.store`, `species.update` y `species.destroy`; el `recepcionista` sigue solo con `species.index`. Se
actualiza la foto de los roles (`tests/Fixtures/clinic-roles.json`).

**Piezas.** `SpeciesService` y `BreedService` (reglas de negocio), `CatalogMessages` (mensajes compartidos), `SpeciesController`
reescrito con Resource y `BreedController`; `CatalogException` con los códigos nuevos.

**`SharedCatalogBuilder`: altas masivas.** Hoy `insert()` sin `clinic_id` crearía un registro global. Se cubren todos los caminos de
alta del builder de Laravel 13: `insert`, `insertOrIgnore`, `insertGetId`, `fillAndInsert`, `fillAndInsertOrIgnore`,
`fillAndInsertGetId` y `upsert`. A cada fila se le fija `clinic_id` = clínica en contexto; si trae otra (o nula de forma explícita),
`ClinicContextMissingException`. En `upsert`, las columnas a actualizar no pueden incluir `clinic_id`. `insertUsing` e
`insertOrIgnoreUsing` (alta desde una consulta, sin filas que revisar) lanzan siempre la excepción. Sin clínica en contexto, falla.

**Deuda 8.** Con `species/{species}/breeds/{breed}` aparece la primera ruta con dos parámetros anidados: test que pide una raza de
otra especie de la misma clínica y espera 404, y que falla sin `scopeBindings()`. Se cierra la deuda 8.

**Tests** (`tests/Feature/Catalog`, con el rol `veterinario`):

| Test | Qué verifica |
|---|---|
| `SpeciesIndexTest` | Por defecto, activas con razas activas; `include_inactive=1` trae todas; `is_global`; no trae las de otra clínica ni las eliminadas |
| `SpeciesWriteTest` | Alta, edición, inactivación; nombre repetido frente a una global, a una propia, con otras mayúsculas o acentos → 422; una global → 403; el recepcionista → 403 |
| `SpeciesDeleteTest` | Sin uso: se elimina con sus razas y libera el nombre; con un paciente (también eliminado) → 409 `SPECIES_IN_USE` |
| `BreedWriteTest` / `BreedDeleteTest` | Lo mismo para razas, con unicidad dentro de la especie; raza propia en una especie global; `species_id` → 422; 409 `BREED_IN_USE` |
| `NestedBindingTest` | Raza de otra especie → 404; falla sin `scopeBindings()` (deuda 8) |
| `PatientCatalogTest` | Un paciente con especie o raza inactiva la sigue mostrando; no se puede dar de alta con una inactiva ni con una eliminada |
| `SharedCatalogTest` (se amplía) | Cada método de alta masiva fija la clínica, rechaza otra o nula, y falla sin clínica; `upsert` no cambia `clinic_id`; `insertUsing` falla |
| `SpeciesSeederTest` | El catálogo global no repite nombres con la comparación de la regla |
| Concurrencia (solo MySQL) | Dos altas simultáneas del mismo nombre: una 201 y otra 422 |

### 1.5 Foto del paciente

**Endpoints.**

| Método | Ruta | Nombre y permiso | Request | Respuesta | Errores |
|---|---|---|---|---|---|
| POST | `patients/{patient}/photo` | `patients.upload-photo` | `UploadPatientPhotoRequest`, `multipart/form-data`: `photo` obligatorio, `image`, `mimetypes:image/jpeg,image/png,image/webp` (por contenido, no por la extensión) y `max:5120` | 200, `ShowPatientResource` | 404; 422 `VALIDATION_ERROR`; 422 `PATIENT_DECEASED` (pregunta 12); 413 (pregunta 16) |
| GET | `patients/{patient}/photo` | `patients.show-photo` | — | 200, el archivo: `Content-Type` según el tipo guardado, `X-Content-Type-Options: nosniff`, `Content-Disposition: inline; filename="PAC-000001.jpg"`, `Cache-Control: private, no-store` | 404 `PATIENT_PHOTO_NOT_FOUND` |
| DELETE | `patients/{patient}/photo` | `patients.delete-photo` | — | 200, `ShowPatientResource` | 404 `PATIENT_PHOTO_NOT_FOUND` |

La descarga es la única respuesta de la API que no usa `ApiResponse` (es un archivo); sus errores sí. Se deja escrito en `CLAUDE.md` §4.

**Reglas.**

| Regla | Detalle |
|---|---|
| Almacenamiento | Disco `local` (privado, fuera de `public/`). Ruta `clinics/{clinic_id}/patients/{patient_id}/{uuid}.{ext}`, con la extensión según el tipo detectado, nunca el nombre del archivo subido |
| Reemplazo | Se guarda el archivo nuevo, se actualiza `photo_path` en una transacción y, después del commit, se borra el anterior. Si la transacción falla, se borra el nuevo |
| Sin redimensionar | El archivo se guarda tal cual (R-015) |
| Baja del paciente | `SoftDeletes`: el archivo queda con el registro |
| Archivo perdido | `photo_path` sin archivo en disco → 404 `PATIENT_PHOTO_NOT_FOUND` y un aviso en el log |
| Auditoría | El cambio de `photo_path` queda en `audit_logs` (`Auditable`) |

**Contrato.** `ShowPatientResource` hoy expone `photo_path` (la ruta interna del disco). Propuesta: reemplazarlo por `has_photo` (bool),
también en `PatientResource` (pregunta 14).

**Permisos.** Tres nuevos (120 → 123). `recepcionista` y `veterinario`: los tres (pregunta 13). Rutas: 16 hoy, 23 con la 1.4 y 26 con
la 1.5.

**Piezas.** `PatientPhotoService`, `PatientPhotoController` y `PatientException` con `PATIENT_PHOTO_NOT_FOUND`.

**Tests** (`tests/Feature/Patients/PatientPhotoTest`, con `Storage::fake('local')`, con el rol `recepcionista` y repetidos con
`veterinario`): subida de JPG, PNG y WEBP; un archivo de texto con extensión `.jpg` → 422; un SVG → 422; más de 5 MB → 422;
reemplazo borra el anterior; ruta del archivo sin el nombre original; descarga con `nosniff`, `Content-Type` y `Cache-Control`; sin
foto → 404; borrado; paciente de otra clínica → 404; archivo perdido → 404; paciente fallecido según la pregunta 12; el JSON no
expone la ruta interna.

## Documentación por entrega

| Entrega | Además de lo de `docs/README.md` (Mantenimiento) |
|---|---|
| 1.1 | `CLAUDE.md` §3 (`PUT`/`PATCH`) y §3 (mensajes: se quita "Los mensajes del paquete base todavía usan tú") |
| 1.2 | `CLAUDE.md` §6.1 (dígito verificador); la guía de Postman con un RUC inválido |
| 1.3 | `CLAUDE.md` §6.2 (`custom_fields`, especie o raza inactiva); deuda 1 resuelta al cerrar 1.2 y 1.3 |
| 1.4 | `CLAUDE.md` §3 (orden de bloqueo) y una sección nueva §6.3 de especies y razas (las pendientes de diseño pasan a §6.4); deuda 8 resuelta; guía de Postman del ABM |
| 1.5 | `CLAUDE.md` §4 (la descarga como excepción a `ApiResponse`) y §6.2 (foto); guía de Postman con la subida y la descarga |

## Preguntas del diseño

Lo que el diseño no podía decidir solo, con su propuesta y la respuesta del usuario (R-016).

| # | Pregunta | Propuesta | Respuesta | Fecha |
|---|---|---|---|---|
| 9 | ¿"Ángora" y "Angora" cuentan como el mismo nombre? MySQL (`utf8mb4_unicode_ci`) ya los iguala en el índice único | Sí: sin distinguir mayúsculas **ni acentos**, igual que MySQL | Sí, sin distinguir mayúsculas ni acentos. Sin depender de la collation: se compara en la aplicación el nombre normalizado (minúsculas y sin acentos), para que SQLite y MySQL den el mismo resultado. Test en las dos bases | 2026-11-05 |
| 10 | ¿La unicidad de las razas es dentro de su especie? El catálogo global ya tiene "Mestizo" en Canino y en Felino | Sí, dentro de la especie | Sí, únicos dentro de cada especie | 2026-11-05 |
| 11 | ¿Una clínica puede ocultar para sí una especie o raza global (por ejemplo, Bovino en una clínica de pequeños animales)? Hoy los globales no se editan; hacerlo exige una tabla nueva | No en el MVP | No en el MVP | 2026-11-05 |
| 12 | ¿Se puede subir o borrar la foto de un paciente fallecido? La regla 6.2 solo deja editar la fecha de fallecimiento y las notas | Sí: la foto no es un dato clínico; se exceptúa de `PATIENT_DECEASED` | Sí: la foto queda fuera del bloqueo `PATIENT_DECEASED` | 2026-11-05 |
| 13 | ¿Quién borra y quién ve la foto? | La borran los mismos que la suben (recepcionista y veterinario) y la ven los tres roles | Igual que la propuesta | 2026-11-05 |
| 14 | Contrato del paciente: ¿se quita `custom_fields` de `ShowPatientResource` y se reemplaza `photo_path` por `has_photo`? | Sí a los dos ("sin API" incluye la respuesta, y la ruta interna no debe salir) | Sí a los dos | 2026-11-05 |
| 15 | Si el test confirma el choque del sufijo de baja, ¿se amplían `clients.document_number` y `patients.microchip` a 50 con una migración nueva? | Sí | Sí, si el test confirma el choque | 2026-11-05 |
| 16 | Un archivo que supera `post_max_size` de PHP da 413 con `error_code` `HTTP_ERROR`. ¿Se agrega `413 PAYLOAD_TOO_LARGE` a los genéricos de `bootstrap/app.php`? | Sí, y anotar en `development/setup.md` que `upload_max_filesize` y `post_max_size` deben pasar de 5 MB | Sí: `413 PAYLOAD_TOO_LARGE`, y `development/setup.md` documenta `upload_max_filesize` y `post_max_size` | 2026-11-05 |
| 17 | ¿Se puede cambiar el dueño de un paciente fallecido? Hoy se permite | Se mantiene: corrige errores de carga | Sí | 2026-11-05 |
| 18 | Al eliminar una especie propia sin uso, ¿se eliminan también sus razas propias sin uso? | Sí, en la misma transacción | Sí | 2026-11-05 |
| 19 | Las entregas pasan de cuatro a cinco y cambian de número (la 1.1 nueva es la de traducciones) | Aprobar con el diseño | Diseño aprobado con las cinco entregas | 2026-11-05 |

## Criterio de cierre

Cada regla de `CLAUDE.md` 6.1, 6.2 y de la nueva sección de especies y razas tiene su test, probado con el rol de la operación
(`recepcionista` en clientes, pacientes y foto; `veterinario` en el catálogo; un rol de test para las bajas). Ninguna regla de los
Requests devuelve una clave cruda. Suite en verde en SQLite y en MySQL. Deudas 1 y 8 resueltas.

## Prompt de implementación (paso 4)

Usar solo con el diseño aprobado, una entrega por vez.

```text
Implementa la entrega 1.X de la FASE 1 según docs/fases/fase-01-clientes-y-pacientes.md (diseño aprobado).

- El código de clientes y pacientes ya existe: no lo reescribas. Si un test encuentra un error en el código existente, corrígelo y anótalo en el Resultado de la fase.
- Respeta las respuestas de R-015 y las de las preguntas del diseño.
- Sigue CLAUDE.md. Corre la suite completa en SQLite (y en MySQL si la entrega toca migraciones, búsquedas, concurrencia o autenticación) e infórmame. No hagas commit.
```

## Resultado

PENDIENTE: lo completa el cierre ([`docs/prompts/7-cierre.md`](../prompts/7-cierre.md)): archivos, endpoints, tests y aserciones,
desvíos del diseño y por qué.
