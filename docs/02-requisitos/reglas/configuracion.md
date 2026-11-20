# Reglas de negocio: configuración de la clínica, logo y archivos huérfanos

Reglas vigentes del backend (antes, `backend/CLAUDE.md` §6; el índice, en su
[§6](../../../backend/CLAUDE.md#6-reglas-de-negocio-vigentes)). Las referencias `§N` son de
[`backend/CLAUDE.md`](../../../backend/CLAUDE.md); las `§6.N`, de los archivos de esta carpeta. Si una regla contradice al
código, manda el código.

### 6.8 Configuración de la clínica, logo y archivos huérfanos

* `system-settings` (`GET`, y `PATCH`/`PUT` con semántica parcial; `SystemSettingsService`): la leen los tres roles
  (`system-settings.show`) y la edita el administrador (`system-settings.update`; los demás, 403). La respuesta
  (`SystemSettingsResource`) trae `clinic` (`name`, `legal_name`, `ruc`, `ruc_dv`), `settings` (cada clave editable, `null` sin
  valor) y `has_logo`.
* Datos de la clínica: `name` (2 a 100) y `legal_name` (2 a 150), editables; `ruc` y `ruc_dv` se muestran y no se editan (422,
  `prohibited`).
* Claves (`App\Enums\SystemSettingKey`, lista cerrada, cada una con su regla): `contact_email` (email en minúsculas, hasta 150),
  `contact_phone` (la regla de teléfonos de clientes), `website` (URL `https`, hasta 150) y `document_footer` (texto, hasta 500).
  `settings.<clave>: null` borra la fila; una clave sin valor no tiene fila. Una clave fuera de la lista → 422 en `settings.<clave>`,
  "La clave … no existe en la configuración.", sin cambios a medias. Dos claves son internas (no se editan ni salen en la respuesta):
  `logo_path` (la ruta del logo) y `demo_data` (la marca del `DemoSeeder`, R-010).
* Una fila con una clave que no está en el enum (cargada sin la API) se ignora al leer, con un aviso en el log, y no se borra: la
  lectura toma las filas sin el modelo, porque el cast de `key` lanzaría un `ValueError` (un 500; R-041). La clave se compara exacta
  al leer; al escribir, una fila con la clave en otras mayúsculas (MySQL no las distingue, tampoco su índice único) se toma y queda
  con la clave del enum.
* **Logo** (`system-settings/logo`: subir con `POST`, ver con `GET`, borrar con `DELETE`; permisos `system-settings.upload-logo`,
  `system-settings.show-logo` y `system-settings.delete-logo`): lo sube y lo borra el administrador; lo ven los tres roles. Las
  reglas de la foto del paciente (§6.2, `PrivateImageStore`), con su propio límite: JPG, PNG o WEBP de hasta 2 MB por el contenido
  (`UploadClinicLogoRequest`), vuelto a codificar sin EXIF y de hasta 25 megapíxeles; más grande → 413 `PAYLOAD_TOO_LARGE` con
  `errors.logo`. Disco propio `clinic_logos` (`storage/app/private/clinic-logos`, sin `serve`), en
  `clinics/{clinic_id}/logo/{uuid}.{ext}`. El anterior se borra después del commit; si la transacción falla, se borra el nuevo.
  Responde `SystemSettingsResource`.
* La ruta va en la clave interna `logo_path` y nunca sale de la API: `has_logo` (`logo_path !== null`, como `has_photo`) en la
  configuración y en `me` (`clinic.has_logo`). Tampoco queda en la auditoría: `SystemSetting::auditValues()` guarda `"(archivo)"`
  en lugar de la ruta en el alta, la modificación y la baja, y `auditContext()` suma siempre la clave (R-041).
* Ver el logo usa `logo_path` solo si es un archivo de la carpeta de la clínica; si no, o sin archivo, 404
  `CLINIC_LOGO_NOT_FOUND` ("La clínica no tiene logo.") y un aviso en el log. Borrarlo sin logo → 404; con un `logo_path` fuera de
  la carpeta, limpia la referencia (200) sin tocar ningún archivo (R-041; la foto no lo hace, deuda 15). Al reemplazar, el
  archivo ajeno no se borra.
* Concurrencia (§3): editar la configuración y subir o borrar el logo bloquean la clínica (`CatalogLock`) antes de leer las claves
  o `logo_path`.
* **Archivos huérfanos** (`php artisan files:prune-orphans`, con `--dry-run`; `OrphanFileService`; programado a diario a las 03:00
  en `routes/console.php`, y en producción necesita el cron del *scheduler*, FASE 10): por cada clínica (`CurrentClinic::run`)
  borra los archivos de `clinics/{id}/patients/` (disco `patient_photos`) y de `clinics/{id}/logo/` (disco `clinic_logos`) que no
  sean el `photo_path` de un paciente de la clínica (también eliminado) ni su `logo_path`, y que tengan más de 24 horas. Nunca
  borra fuera de esas carpetas: las rutas de la base solo sirven para conservar. Cada borrado queda en el log. Una clínica que no
  se puede revisar (un enlace simbólico, un error del disco) queda en el log, no se borra nada en ella y se sigue con las demás.
  Termina con 1 si una clínica falló o un borrado no se pudo hacer. `--dry-run` informa lo mismo que borraría, sin borrar.
