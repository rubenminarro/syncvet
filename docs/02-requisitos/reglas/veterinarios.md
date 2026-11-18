# Reglas de negocio: veterinarios

Reglas vigentes del backend (antes, `backend/CLAUDE.md` §6; el índice, en su
[§6](../../../backend/CLAUDE.md#6-reglas-de-negocio-vigentes)). Las referencias `§N` son de
[`backend/CLAUDE.md`](../../../backend/CLAUDE.md); las `§6.N`, de los archivos de esta carpeta. Si una regla contradice al
código, manda el código.

### 6.6 Veterinarios

* ABM en `veterinarians` (`apiResource`; permisos `veterinarians.index`, `.store`, `.show`, `.update` y `.destroy`). Lo administra el
  administrador; el `recepcionista` y el `veterinario` solo listan (`veterinarians.index`): el detalle, con la comisión y las notas,
  es solo del administrador, y el listado (`VeterinarianResource`) no muestra `commission_percentage` ni `notes` (R-037).
* Una ficha por usuario (`veterinarians.user_id` es único en toda la base, también entre eliminadas). `user_id` obligatorio en el
  alta: un usuario activo y no eliminado de la clínica (si no, 422 "El usuario no existe o está inactivo."), con el rol
  `veterinario` (422 "El usuario no tiene el rol veterinario.") y sin una ficha no eliminada (422 "Ese usuario ya es
  veterinario."). El Service lo vuelve a revisar con el usuario bloqueado (§3). `user_id` es `prohibited` al editar.
* Código `VET-` de la serie (`DocumentSeries::VETERINARIAN`); `code` en el alta o en la edición → 422 (`prohibited`).
* **Restauración** (P6): si el usuario tiene una ficha eliminada, el alta la restaura (misma fila y mismo código, sin usar la serie)
  con los datos del pedido; los que no llegan vuelven a su valor por defecto, como en un alta. Responde 201, igual que un alta, y la
  auditoría guarda `updated` (si cambian datos) y `restored`.
* `license_number` opcional, hasta 30, única en la clínica: se pasa a mayúsculas y sin espacios en los extremos **antes de validar**
  (vacía, nula), así que `mv-1001` choca con `MV-1001` en SQLite y en MySQL (R-037). Repetida → 422 "Ya hay un veterinario con esa
  matrícula." (el índice único es el respaldo de dos pedidos simultáneos: el mismo 422). `specialty` hasta 100;
  `commission_percentage` de 0 a 100 con hasta 2 decimales, 0 por defecto, sin cálculos (P7); `notes` hasta 1000; `status`
  `active` o `inactive` (`VeterinarianStatus`), `active` por defecto.
* Sucursales (P5): las de su usuario (`branch_user`); la respuesta las muestra y el listado filtra por `branch_id` (una sucursal de
  la clínica, activa o no).
* Se inactiva (`status: inactive`) aunque esté en uso. Un usuario inactivo no cambia su ficha; la respuesta muestra
  `user.is_active` (las fases 3 y 4 no lo asignan).
* Baja (`SoftDeletes`) solo sin uso: una fila de `schedules`, `appointments`, `consultations`, `consultation_items`,
  `patient_applications` o `patient_documents` (`VeterinarianService::USAGE`, con `DB::table`; un test exige que cubra toda clave
  foránea a `veterinarians`). Con uso → 409 `VETERINARIAN_IN_USE`, "El veterinario tiene registros y no se puede eliminar:
  inactivalo." Al eliminar, `//deleted_<id>_<timestamp>` entero en la matrícula: la columna admite 60. La ficha eliminada cuenta
  como uso de su usuario (§6.5, `USER_IN_USE`).
* Quitarle el rol `veterinario` (`users/{user}`) a un usuario con una ficha no eliminada → 409 `USER_IS_VETERINARIAN`, "El usuario
  tiene una ficha de veterinario: eliminala antes de quitarle el rol."; con la ficha eliminada, se permite. `UserResource` (el
  listado, desde la entrega front-02, y el detalle) muestra `veterinarian` (`id`, `code`), o `null` sin ficha o con la ficha
  eliminada.
* Listado paginado con `ListFilters`: solo los activos; `status` filtra por un estado y manda; `include_inactive=1` sin `status`
  trae activos e inactivos (nunca los eliminados); `search` en el código, el nombre y el apellido del usuario y la matrícula; orden
  por código.
