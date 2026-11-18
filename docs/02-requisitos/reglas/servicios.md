# Reglas de negocio: servicios

Reglas vigentes del backend (antes, `backend/CLAUDE.md` §6; el índice, en su
[§6](../../../backend/CLAUDE.md#6-reglas-de-negocio-vigentes)). Las referencias `§N` son de
[`backend/CLAUDE.md`](../../../backend/CLAUDE.md); las `§6.N`, de los archivos de esta carpeta. Si una regla contradice al
código, manda el código.

### 6.7 Servicios

* Catálogo en `services` (`apiResource`; permisos `services.index`, `.store`, `.show`, `.update` y `.destroy`), con
  `ServiceCatalogService`. Lo administra el administrador; el `recepcionista` y el `veterinario` listan y ven el detalle
  (`services.index` y `services.show`) y reciben 403 al escribir. Un servicio de otra clínica → 404.
* Código `SRV-` de la serie (`DocumentSeries::SERVICE`); `code` en el alta o en la edición → 422 (`prohibited`).
* Alta: `name`, `category` (`ServiceCategory`) y `standard_price` obligatorios (el precio, para que no quede en 0 por olvido);
  `duration_minutes` (30), `tax_rate` (10) y `status` (`active`) toman su valor por defecto si no llegan (R-038). Edición con
  semántica parcial.
* Nombre de 2 a 120, con al menos una letra, sin `//` (el comienzo del sufijo de baja) y solo letras, números, espacios y
  `. , - ' ( ) / + %` (`ServiceMessages::NAME_REGEX`, propio de servicios: "Castración macho/hembra", "Vacuna 5+1"; R-039).
  `CatalogMessages::NAME_REGEX`, el de sucursales, especies y razas, no cambia. Se guarda limpio (`CatalogName::clean`) y es único
  en la clínica sin mayúsculas, acentos ni espacios repetidos (`CatalogName`), frente a los no eliminados; repetido → 422
  "Ya hay un servicio con ese nombre." (también si lo frena el índice `(clinic_id, name)`, el respaldo de dos pedidos simultáneos).
* **Un servicio usado no cambia de nombre:** 422 `SERVICE_NAME_NOT_EDITABLE`, "El servicio ya se usó y no puede cambiar de nombre:
  creá otro e inactivá este." Cuenta como cambio solo lo que cambia el nombre ya limpio: reenviar el mismo (con espacios de más)
  pasa; cambiar solo las mayúsculas responde 422. El precio, el IVA, la duración, la categoría, la descripción y el estado se editan
  siempre (lo usado guarda su precio).
* `standard_price`: entero de 0 a 999.999.999.999, sin decimales mientras la moneda sea PYG (pregunta 21; `App\Rules\WholeAmount`):
  un entero de JSON o un texto de solo dígitos; se rechazan los decimales (también `"150000.00"` y `150000.0`), los negativos, los
  exponentes, los separadores de miles y los floats de JSON. Se guarda con BCMath (sin float) y la respuesta lo muestra como texto
  con dos decimales (`"150000.00"`). `tax_rate` 0, 5 o 10 (`TaxRate`), como número en la respuesta. `duration_minutes` de 5 a 720 en
  múltiplos de 5 (pregunta 22). `description` hasta 1000. La respuesta muestra `category` y `status` como `{value, label}`.
* Uso (`ServiceCatalogService::USAGE`, con `DB::table`; un test exige que cubra toda clave foránea a `services`): una fila de
  `schedules`, `appointments` o `consultation_items`.
* Se inactiva (`status: inactive`) aunque esté usado. Baja (`SoftDeletes`) solo sin uso: con uso → 409 `SERVICE_IN_USE`, "El
  servicio ya se usó y no se puede eliminar: inactivalo." Al eliminar, `//deleted_<id>_<timestamp>` entero en el nombre (la columna
  admite 150) y el nombre queda libre.
* Listado paginado con `ListFilters`: solo los activos; `status` filtra por un estado y manda; `include_inactive=1` sin `status`
  trae activos e inactivos (nunca los eliminados); `category`; `search` literal en el código y el nombre; orden por nombre e id.
* Concurrencia (§3): el alta y el cambio de nombre bloquean la clínica (`CatalogLock`) antes de leer los nombres; la edición y la
  baja bloquean el servicio antes de revisar el uso; la serie va al final.
