# Reglas de negocio: usuarios

Reglas vigentes del backend (antes, `backend/CLAUDE.md` §6; el índice, en su
[§6](../../../backend/CLAUDE.md#6-reglas-de-negocio-vigentes)). Las referencias `§N` son de
[`backend/CLAUDE.md`](../../../backend/CLAUDE.md); las `§6.N`, de los archivos de esta carpeta. Si una regla contradice al
código, manda el código.

### 6.5 Usuarios

* Alta, edición, detalle y listado (`users`) y el listado de roles (`roles`, sin paginar): solo el administrador (P2; permisos
  `users.index`, `users.store`, `users.show`, `users.update`, `users.destroy` y `roles.index`). El recepcionista y el veterinario
  reciben 403. Un usuario de otra clínica → 404. `clinic_id` no se recibe: lo fija `UserService`.
* `first_name` y `last_name` de 2 a 100, con la regla de nombres de personas (`App\Support\PersonName`, §6.1); `name` lo arma el
  Service (`first_name last_name`).
* Email obligatorio, solo ASCII, en minúsculas, hasta 150 (deja lugar al sufijo de baja), con la validación de los clientes
  (`EMAIL_DNS_CHECK`), y **único en toda la base**, también entre usuarios eliminados (es el login, R-029): lo revisa el Service, no
  una regla `unique`; el índice único es el respaldo de dos altas simultáneas en clínicas distintas. Repetido → 422 "Ya hay un
  usuario con ese email."
* Contraseña con `UserPassword` (R-009): obligatoria en el alta, opcional al editar. Si llega al editar a otro usuario, se cambia y se
  revocan todos los tokens de ese usuario (aunque sea la misma); el token de quien hace el cambio sigue. La propia no se cambia por
  `users/{user}`: 422 en `password`, "Para cambiar tu contraseña usá tu perfil.", también vacía o nula (se cambia con
  `PATCH me/password`, que pide la actual y conserva el token en uso; R-033).
* Inactivar (`is_active` en `UpdateUserRequest`, 2.2b): revoca todos los tokens del usuario; reactivar no los devuelve (§3). El
  administrador no se inactiva ni se da de baja a sí mismo: 409 `USER_SELF_ACTION`, "No podés inactivarte ni darte de baja a vos
  mismo." (reenviar `is_active: true` a sí mismo no es inactivarse).
* Baja (`DELETE users/{user}`, P1): solo sin uso. Uso: la ficha de veterinario (también eliminada) o cualquier fila de una tabla
  de negocio que lo referencie (`UserService::USAGE`, todas las claves foráneas a `users` salvo `audit_logs` y `branch_user`; un
  test exige que la lista las cubra). Con uso → 409 `USER_IN_USE`, "El usuario tiene registros a su nombre y no se puede eliminar:
  inactivalo." Al eliminar: `//deleted_<id>_<timestamp>` en el email (queda libre para un alta nueva), tokens revocados y
  `branch_user` conservado; la auditoría guarda `updated` (el email) y `deleted`.
* Perfil (2.2b; cualquier usuario autenticado y activo, sin permiso: `ROUTES_WITHOUT_PERMISSION`):
  * `PATCH me/current-branch` con `branch_id`: una sucursal activa de la clínica asignada al usuario (si no, 422 en `branch_id`); el
    Service la vuelve a revisar con la clínica bloqueada (§3). Responde `MeResource`, que trae en `branches` justamente las
    sucursales que esta ruta acepta (entrega front-00; `CurrentBranchTest`). La regla "activa y asignada" vive solo en
    `Branch::selectableBy($user)`, que usan el Request (con la regla `App\Rules\SelectableBranch`), `AuthService::setCurrentBranch` y
    `AuthService::profile` (revisión, H8).
  * `PATCH me/password` con `current_password` (incorrecta → 422 en `current_password`) y `password` (`UserPassword`, distinta de la
    actual). Conserva el token del pedido y revoca los demás del usuario. Límite: 5 intentos por minuto por usuario, contados antes
    de verificar la actual (el sexto, 429 `AUTH_TOO_MANY_ATTEMPTS` con `Retry-After`, aunque sea correcta); los errores de
    validación no cuentan y un cambio correcto limpia el contador (R-034).
* `roles`: 1 o más de `administrador`, `recepcionista` y `veterinario` (`App\Enums\UserRole`, P3), sin repetidos; `syncRoles`, y el
  cambio queda en la auditoría como `ROLES_SYNCED`. La respuesta los muestra como `{name, label}`, igual que `GET roles`.
* `branch_ids`: 1 o más sucursales activas de la clínica, sin repetidas; al editar se acepta también una inactiva que el usuario ya
  tiene. El Service las vuelve a leer con la clínica bloqueada (una inactivada entre la validación y el guardado → 422). El cambio
  queda en la auditoría como `updated` con `branch_ids` antes y después.
* Sucursal actual (`current_branch_id`): en el alta, la por defecto si está en `branch_ids`; si no, la primera activa de la lista;
  sin ninguna activa, ninguna. Al editar `branch_ids`, si el usuario no tiene sucursal actual o la actual sale de la lista, se elige
  con la misma regla (R-033).
* Último administrador (P4): si un administrador activo deja de serlo (pierde el rol, se inactiva o se da de baja) y no queda otro
  administrador activo de la clínica → 409 `USER_LAST_ADMIN`, "La clínica tiene que tener al menos un administrador activo.",
  también cuando se quita el rol a sí mismo. Cuentan solo los administradores activos (`is_active`) y no eliminados de la clínica
  (R-034); quitarle el rol a un administrador inactivo no cambia nada (200).
* Listado paginado con `ListFilters`: los usuarios activos de la clínica (`inClinic()`), sin eliminados, salvo
  `include_inactive=1`; `search` en nombre, apellido y email; `role` y `branch_id` (una sucursal de la clínica, activa o no); orden por apellido, nombre e id.
  Cada usuario trae su ficha de veterinario, `veterinarian` (`id`, `code`, o `null` sin ficha o con la ficha eliminada), en
  `UserResource`, cargada con `UserController::RELATIONS`, sin N+1 (entrega front-02; `UserVeterinarianTest`). El detalle, el alta,
  la edición y `me` conservan sus claves y su orden.
* Concurrencia: el alta, la edición, la inactivación y la baja bloquean la clínica (`CatalogLock`) y, salvo el alta, después el
  usuario (`lockForUpdate`), antes de contar los administradores; la baja revisa el uso dentro de su transacción (§3).
