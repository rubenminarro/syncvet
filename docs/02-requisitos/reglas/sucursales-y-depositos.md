# Reglas de negocio: sucursales y depósitos

Reglas vigentes del backend (antes, `backend/CLAUDE.md` §6; el índice, en su
[§6](../../../backend/CLAUDE.md#6-reglas-de-negocio-vigentes)). Las referencias `§N` son de
[`backend/CLAUDE.md`](../../../backend/CLAUDE.md); las `§6.N`, de los archivos de esta carpeta. Si una regla contradice al
código, manda el código.

### 6.4 Sucursales y depósitos

* Sin baja: solo se inactivan (`is_active`). Lo administra el administrador; el `recepcionista` y el `veterinario` solo listan
  sucursales (`branches.index`). Depósitos anidados en `branches/{branch}/warehouses` (listado y alta; la sucursal sale de la URL
  y un `branch_id` en el cuerpo se ignora) y editados en `warehouses/{warehouse}` (`branch_id` `prohibited`).
* Código (`code`) que escribe el administrador: obligatorio, hasta 20, `^[A-Z0-9-]+$`, se guarda en mayúsculas, único en la
  clínica (también el de un depósito) y editable. Nombre de 2 a 100 con al menos una letra (`CatalogMessages::NAME_REGEX`),
  único sin mayúsculas, acentos ni espacios repetidos (`CatalogName`): la sucursal en la clínica, el depósito dentro de su
  sucursal. Los únicos los revisa el Service con la clínica bloqueada (`CatalogLock`): repetido → 422 `VALIDATION_ERROR`.
* `establishment` (SIFEN): tres dígitos de `001` a `999`, único en la clínica. Cambia solo si la sucursal no tiene facturas
  (`BRANCH_ESTABLISHMENT_NOT_EDITABLE`, 422); el mismo valor no es un cambio. `address` hasta 255 y `phone` con la regla de
  clientes, opcionales.
* Siempre una sucursal por defecto en la clínica y un depósito por defecto en cada sucursal. `is_default: true` en otra (activa)
  la pasa a ser la por defecto y la anterior deja de serlo, en la misma transacción. `is_default: false` en la por defecto → 422
  en `is_default` (en otra no cambia nada: el frontend manda el formulario completo). Una inactiva no es la por defecto (422). La
  por defecto no se inactiva: `BRANCH_DEFAULT_NOT_DEACTIVATABLE` o `WAREHOUSE_DEFAULT_NOT_DEACTIVATABLE` (409). Si la clínica no
  tiene sucursal por defecto (o la sucursal, depósito por defecto), la que se crea pasa a serlo.
* El alta de una sucursal crea su depósito por defecto en la misma transacción (`warehouse_code` obligatorio, `warehouse_name`
  "Principal" si no llega); al usuario que la crea no se le asigna. Una sucursal inactiva no suma depósitos: 422 en `branch`
  ("La sucursal está inactiva.").
* Inactivar una sucursal deja sin sucursal actual (`current_branch_id` nulo, usuario por usuario para la auditoría) a quienes la
  tenían como actual; su asignación (`branch_user`) y sus depósitos no cambian.
* Listados paginados con `ListFilters`: solo los activos, salvo `include_inactive=1`; `search` en código y nombre.
