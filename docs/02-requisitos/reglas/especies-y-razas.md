# Reglas de negocio: especies y razas

Reglas vigentes del backend (antes, `backend/CLAUDE.md` §6; el índice, en su
[§6](../../../backend/CLAUDE.md#6-reglas-de-negocio-vigentes)). Las referencias `§N` son de
[`backend/CLAUDE.md`](../../../backend/CLAUDE.md); las `§6.N`, de los archivos de esta carpeta. Si una regla contradice al
código, manda el código.

### 6.3 Especies y razas

* Catálogo compartido (§3): las globales (`clinic_id` nulo) y las propias de la clínica. Lo administran el administrador y el
  `veterinario`; el `recepcionista` solo lo lista. Razas anidadas en `species/{species}/breeds`, con los permisos de especie
  (`species.index`, `species.store`, `species.update` y `species.destroy`; R-015).
* Las globales no se editan, no se inactivan ni se eliminan desde una clínica (`CATALOG_GLOBAL_NOT_EDITABLE`, 403, antes de
  validar el cuerpo: lo revisa el `authorize()` del Form Request, y otra vez el Service). Una clínica no oculta una global para sí.
* Nombre único sin distinguir mayúsculas ni acentos ni espacios (`App\Support\CatalogName`): sin los de los extremos y con los
  internos repetidos juntados en uno, también al guardar ("Pastor  Alemán" se guarda y se compara como "Pastor Alemán").
  Comparado en la aplicación para que SQLite y MySQL den lo mismo: una especie, frente a las globales y a las propias no
  eliminadas; una raza, lo mismo **dentro de su especie**. Repetido → 422 `VALIDATION_ERROR` en `name`. El alta y el cambio de
  nombre bloquean antes la fila de la clínica (`CatalogLock`). Hasta 60 caracteres la especie y 80 la raza, con al menos una
  letra (`CatalogMessages::NAME_REGEX`: "--" o "12" → 422).
* `is_active = false` la oculta de los listados (salvo `include_inactive=1`) y de las altas de pacientes; los pacientes que ya
  la usan la siguen mostrando (§6.2). Se inactiva aunque esté en uso. Una raza propia puede ir en una especie global.
* La raza no cambia de especie (`species_id` `prohibited` al editar; en el alta sale de la URL y uno en el cuerpo se ignora). Una
  raza de otra especie en la URL → 404 (`scopeBindings()`).
* Baja (`SoftDeletes`) solo si ningún paciente, tampoco uno eliminado, la usa: `SPECIES_IN_USE` o `BREED_IN_USE` (409). El
  uso se revisa dentro de la transacción de la baja, con la especie y sus razas (o la raza) ya bloqueadas (`lockForUpdate`;
  el paciente toma un `sharedLock`, §6.2 y §3): un alta simultánea de un paciente no deja una especie o raza eliminada en uso.
  La baja de una especie se lleva sus razas (las propias de la clínica) en la misma transacción. Agrega
  `//deleted_<id>_<timestamp>` entero al nombre: las columnas admiten 90 y 110 caracteres.
