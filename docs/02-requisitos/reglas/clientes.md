# Reglas de negocio: clientes

Reglas vigentes del backend (antes, `backend/CLAUDE.md` §6; el índice, en su
[§6](../../../backend/CLAUDE.md#6-reglas-de-negocio-vigentes)). Las referencias `§N` son de
[`backend/CLAUDE.md`](../../../backend/CLAUDE.md); las `§6.N`, de los archivos de esta carpeta. Si una regla contradice al
código, manda el código.

### 6.1 Clientes

* Persona o empresa (`ClientKind`). Una empresa lleva `business_name` y no `first_name`/`last_name` (`prohibited`); una
  persona, al revés. El tipo cambia solo si el cliente no tiene facturas (`CLIENT_KIND_NOT_EDITABLE`, 422); al cambiarlo,
  los campos del tipo nuevo son obligatorios y los del anterior se vacían.
* Nombre para mostrar: siempre `Client::display_name`, nunca concatenando nombre y apellido.
* `first_name` y `last_name` de 2 a 100, con la regla de nombres de personas que comparten clientes y usuarios
  (`App\Support\PersonName`, R-033): letras (también `ñ`, vocales con tilde y `ü`) separadas por espacios, o por un apóstrofo o un
  guion entre letras (D'Alessandro, Ana-María); el apóstrofo y el guion, nunca al principio, al final ni repetidos.
* `document_number` y `email` únicos en la clínica y obligatorios; el email, en minúsculas. Teléfono `+?[1-9]\d{7,14}`.
* El documento es único por su **número base** (`clients.document_base`, `App\Support\DocumentBase`, R-018): los dígitos
  antes del guion, sin ceros adelante. En Paraguay el RUC de una persona es su cédula más el dígito verificador, así que
  `99999903`, `099999903` y `99999903-6` son el mismo documento (`UniqueClientDocument`, 422 con el mensaje de documento
  en uso). Un documento con letras es su propia base, en mayúsculas. El modelo completa la base al guardar; la baja le
  agrega el mismo sufijo.
* La edición valida como el alta: un campo obligatorio enviado vacío responde solo "es obligatorio", y los campos del otro
  tipo admiten `null` (el frontend manda todos) y rechazan cualquier valor.
* Un documento con la forma de un RUC (`NNNNNNN-D`) debe tener su dígito verificador correcto (`App\Rules\RucCheckDigit`,
  con el cálculo de `ClinicService`; R-015). Una cédula sin guion o un documento con letras no se valida.
* La baja agrega `//deleted_<id>_<timestamp>` entero al documento y al email: `clients.document_number` admite 50
  caracteres (20 del documento y 21 más el id del sufijo, con ids de hasta 9 dígitos).
* No se elimina con pacientes (no eliminados), turnos, consultas o facturas (`CLIENT_IN_USE`, 409, "El cliente tiene pacientes,
  turnos, consultas o facturas y no puede eliminarse."); el uso se revisa dentro de la transacción de la baja, con la fila del
  cliente bloqueada (§3).
* La búsqueda (`search`) también encuentra al dueño por el nombre o el código de su mascota.
* Dos altas o ediciones simultáneas con el mismo documento (también la misma base) o el mismo email pasan las dos la
  validación: la segunda choca con el índice único y responde el 422 de la regla `unique` en `document_number` o `email`,
  nunca 500 (`ClientService`, por el nombre del índice; R-067). `ClientConcurrencyTest` (MySQL) lo prueba con dos procesos.
