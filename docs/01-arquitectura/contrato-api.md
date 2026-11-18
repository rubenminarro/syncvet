# Contrato de respuesta de la API

El formato de las respuestas del backend (antes, `backend/CLAUDE.md` §4). Las referencias `§N` son de
[`backend/CLAUDE.md`](../../backend/CLAUDE.md); las `§6.N`, de las reglas de negocio de
[`docs/02-requisitos/reglas/`](../02-requisitos/README.md#reglas-de-negocio-vigentes), con su índice en `backend/CLAUDE.md` §6.

```json
{ "success": true, "status": 200, "message": "...", "data": {}, "errors": null, "meta": null, "timestamp": "..." }
{ "success": false, "status": 422, "message": "...", "data": null, "errors": null, "error_code": "..." }
```

Listados: `meta.pagination` (`total`, `per_page`, `current_page`, `last_page`), armado siempre con `paginationMeta()` de
`App\Traits\ApiResponse` (entrega front-00, B3; antes en `camelCase`).

`me` (`GET me`, `data.user` de `POST login` y `PATCH me/current-branch`, todos con `MeResource`): usuario, `clinic` (con
`has_logo`), `current_branch`, `branches` (las sucursales activas asignadas al usuario, por `code`, cada una con `id`, `code` y
`name`: exactamente las que acepta `PATCH me/current-branch`, también para el administrador; sin ninguna, `[]`; entrega front-00, B1),
`roles`, `permissions` y `veterinarian`.

CORS (`config/cors.php`, entrega front-00, B2): solo `api/*`, los orígenes de `CORS_ALLOWED_ORIGINS` (separados por comas; vacía,
ninguno), solo orígenes exactos `esquema://host[:puerto]`, normalizados en minúsculas y sin la barra final; lo demás, todo `*`
incluido, se descarta con un aviso en el log, como máximo uno por hora (`App\Support\CorsOrigins`; revisión, H1, H6 y H7), sin
credenciales, `Retry-After` expuesto y `max_age` de 600 (`CorsTest`).

Las únicas respuestas exitosas sin `ApiResponse` son las descargas de un archivo (`GET patients/{patient}/photo` y
`GET system-settings/logo`, las dos con `PrivateImageStore`): el cuerpo es el archivo, con `Content-Type` según el tipo guardado,
`X-Content-Type-Options: nosniff`, `Content-Security-Policy: default-src 'none'; sandbox` y `Cache-Control: private, no-store`. Sus
errores sí usan el contrato.

`error_code` genéricos (`bootstrap/app.php`; los de negocio salen de cada excepción de dominio):

| HTTP | `error_code` | Cuándo |
|---|---|---|
| 400 | `BAD_REQUEST` | Solicitud mal formada |
| 401 | `UNAUTHENTICATED` | Sin token, o vencido o revocado |
| 403 | `FORBIDDEN` | Falta el permiso de Spatie, o la autorización (`Gate`, `abort(403)`) la niega |
| 404 | `RESOURCE_NOT_FOUND` | Ruta o modelo inexistente |
| 405 | `METHOD_NOT_ALLOWED` | Método HTTP no admitido en la ruta |
| 406 | `NOT_ACCEPTABLE` | Formato de respuesta no aceptable |
| 409 | `CONFLICT` | `abort(409)` u otro conflicto HTTP sin excepción de dominio |
| 413 | `PAYLOAD_TOO_LARGE` | El pedido supera `post_max_size` de PHP (`docs/04-desarrollo/setup.md`); la foto de más de 5 MB y el logo de más de 2 MB responden lo mismo, con `errors.photo` o `errors.logo` |
| 415 | `UNSUPPORTED_MEDIA_TYPE` | Tipo de contenido no soportado |
| 422 | `VALIDATION_ERROR` | Falla un Form Request (`errors` trae los campos) |
| 429 | `TOO_MANY_REQUESTS` | Límite de pedidos (conserva `Retry-After`) |
| 503 | `SERVICE_UNAVAILABLE` | Servicio no disponible |
| Otro 4xx | `HTTP_ERROR` | Error HTTP sin código propio; conserva su estado |
| 500 | `INTERNAL_SERVER_ERROR` | Error inesperado, sin traza |

`error_code` de autenticación, usuarios, veterinarios, servicios, la configuración, las agendas y los turnos que no son de un solo
módulo (2.2a, 2.2b, 2.3, 2.4, 2.5, 3.1, 3.2a, 3.2b, 3.3 y 3.4):

| HTTP | `error_code` | Excepción | Cuándo |
|---|---|---|---|
| 403 | `USER_INACTIVE` | `AuthException` | Login de un usuario inactivo con la contraseña correcta, o su token vigente en una ruta con `clinic` (§3) |
| 409 | `USER_SELF_ACTION` | `UserException` | El administrador se inactiva o se da de baja a sí mismo |
| 409 | `USER_IN_USE` | `UserException` | Baja de un usuario con registros a su nombre (`UserService::USAGE`): se inactiva |
| 409 | `USER_LAST_ADMIN` | `UserException` | La clínica se queda sin un administrador activo (§6.5) |
| 409 | `USER_IS_VETERINARIAN` | `UserException` | Se le quita el rol `veterinario` a un usuario con una ficha de veterinario no eliminada (§6.6) |
| 409 | `VETERINARIAN_IN_USE` | `VeterinarianException` | Baja de un veterinario con registros (`VeterinarianService::USAGE`): se inactiva (§6.6) |
| 422 | `SERVICE_NAME_NOT_EDITABLE` | `ServiceException` | Cambio de nombre de un servicio usado (`ServiceCatalogService::USAGE`): se crea otro y se inactiva este (§6.7) |
| 409 | `SERVICE_IN_USE` | `ServiceException` | Baja de un servicio usado (`ServiceCatalogService::USAGE`): se inactiva (§6.7) |
| 404 | `CLINIC_LOGO_NOT_FOUND` | `ClinicException` | Ver o borrar el logo de una clínica sin logo; verlo con un `logo_path` fuera de la carpeta de la clínica o sin archivo (§6.8) |
| 409 | `SCHEDULE_IN_USE` | `ScheduleException` | Baja de una agenda con turnos, en cualquier estado: se inactiva (§6.10) |
| 422 | `SCHEDULE_NOT_EDITABLE` | `ScheduleException` | Cambio de sucursal o de veterinario de una agenda con turnos, en cualquier estado (§6.10) |
| 409 | `CONFLICT` | `ScheduleException` | El veterinario de una agenda cambió entre la lectura y el bloqueo en 3 intentos seguidos de la edición (§3, orden de bloqueo) |
| 409 | `APPOINTMENT_OVERLAP` | `AppointmentException` | El turno se superpone con otro que ocupa el horario en la agenda o del mismo veterinario en otra agenda, sin `overbook: true` (§6.10) |
| 422 | `APPOINTMENT_OUTSIDE_HOURS` | `AppointmentException` | El turno está fuera del horario semanal de la agenda (§6.10) |
| 422 | `APPOINTMENT_IN_BLOCK` | `AppointmentException` | El turno se superpone con un bloqueo de la agenda (§6.10) |
| 422 | `APPOINTMENT_NOT_EDITABLE` | `AppointmentException` | Se edita o se mueve un turno que no está a confirmar ni confirmado (§6.10) |
| 409 | `APPOINTMENT_SERIES_CONFLICT` | `AppointmentException` | Una o más fechas de una serie fuera del horario, en un bloqueo o superpuestas: no se crea ningún turno; `errors.conflicts` trae cada fecha con `starts_at`, `reason` (`outside_hours`, `in_block` u `overlap`) y `message` (§6.10) |
| 409 | `CONFLICT` | `AppointmentException` | El alta (también de una serie), la edición o una transición de un turno: la agenda o el turno cambiaron entre la lectura y el bloqueo, o un *deadlock*, en 3 intentos seguidos; o una espera vencida de MySQL (1205), sin reintentar (§3, orden de bloqueo) |
| 422 | `APPOINTMENT_INVALID_TRANSITION` | `AppointmentException` | Una transición que el estado del turno no admite, también repetir la misma: "El turno está «:estado» y no admite ese cambio." (§6.10) |
| 422 | `APPOINTMENT_PATIENT_REQUIRED` | `AppointmentException` | Fichar un turno sin paciente (§6.10) |
| 422 | `APPOINTMENT_CHECK_IN_NOT_TODAY` | `AppointmentException` | Fichar un turno que no es de hoy en Asunción (§6.10) |
| 422 | `APPOINTMENT_NO_SHOW_TOO_EARLY` | `AppointmentException` | Marcar ausente un turno que todavía no empezó (§6.10) |
| 422 | `APPOINTMENT_NO_SHOW_NOT_UNDOABLE` | `AppointmentException` | Deshacer el ausente de un turno que no es de hoy en Asunción (§6.10) |
| 403 | `APPOINTMENT_NOT_YOURS` | `AppointmentException` | Un veterinario ficha o marca atendido un turno que no es de su ficha ni de una agenda sin veterinario: "Ese turno no es de tu agenda." (§6.10) |
| 422 | `APPOINTMENT_QUEUE_NO_BRANCH` | `AppointmentException` | La cola del día de un usuario sin sucursal actual: "Elegí tu sucursal actual para ver la cola del día." (§6.10) |
