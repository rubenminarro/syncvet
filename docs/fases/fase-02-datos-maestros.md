# FASE 2 — Datos maestros

Etapa: Base. Estado: **EN DESARROLLO**: diseño aprobado el 2026-11-06, con las preguntas 16 a 31 según su propuesta (R-029); entregas 2.1 (R-030 y R-031) y 2.2a (R-032 y R-033) cerradas; sigue la 2.2b. El estado vigente de
cada fase está en [`docs/roadmap/README.md`](../roadmap/README.md).

## Objetivo

Todo lo que las demás fases necesitan configurar: sucursales y depósitos, usuarios con sus roles y sucursales, veterinarios,
catálogo de servicios, datos y logo de la clínica, y la consulta de la auditoría. Además deja resueltas las deudas 13 (fechas con
hora, antes de la FASE 3) y 14 (archivos huérfanos).

## Entregas

El alcance inicial tenía seis entregas con los usuarios primero. El diseño pone primero las sucursales, porque los usuarios se
asignan a sucursales, y divide los usuarios en dos (R-029). Cada entrega recorre los pasos 4 a 7 y deja la suite en verde.

| Entrega | Contenido | Antes era | Estado |
|---|---|---|---|
| 2.1 | Sucursales y depósitos: ABM sin baja, sucursal y depósito por defecto, `establishment` | 2.2 | COMPLETADO (R-030, R-031) |
| 2.2a | Usuarios: alta, edición, detalle y listado; roles y sucursales; último administrador | 2.1 | COMPLETADO (R-032, R-033) |
| 2.2b | Usuarios: inactivos (migración, login, tokens), baja sin uso, sucursal actual y contraseña propia | 2.1 | PENDIENTE |
| 2.3 | Veterinarios (`VET-`): uno por usuario, restauración, inactivación en uso | 2.3 | PENDIENTE |
| 2.4 | Catálogo de servicios (`SRV-`): nombre fijo una vez usado, baja sin uso | 2.4 | PENDIENTE |
| 2.5 | Datos y configuración de la clínica (`system_settings`), logo y limpieza de archivos huérfanos (deuda 14) | 2.5 | PENDIENTE |
| 2.6 | Consulta de auditoría y fechas con hora en un lugar común (deuda 13) | 2.6 | PENDIENTE |

## Preguntas de negocio

Las tres del alcance inicial (aquí P5, P8 y P11) y las que sumó el análisis, respondidas por el usuario el 2026-11-06 (R-029).

| # | Pregunta | Respuesta | Fecha |
|---|---|---|---|
| P1 | Usuarios inactivos: ¿`SoftDeletes` o un estado? (pregunta 5 de la FASE 0) | `users.is_active` por migración. Inactivo con la contraseña correcta → 403 "usuario inactivo"; con una incorrecta, el error genérico. Al inactivarlo se revocan sus tokens. Baja con sufijo `//deleted_` solo sin uso; con uso, 409 | 2026-11-06 |
| P2 | ¿Quién gestiona los usuarios? | Solo el administrador | 2026-11-06 |
| P3 | ¿Un usuario tiene varios roles? | Sí | 2026-11-06 |
| P4 | ¿Un administrador puede inactivarse o darse de baja, o dejar la clínica sin administradores? | No: 409 | 2026-11-06 |
| P5 | ¿Un veterinario trabaja en varias sucursales? | Sí: en las sucursales de su usuario (`branch_user`), sin una tabla aparte | 2026-11-06 |
| P6 | ¿El veterinario necesita un usuario? ¿Cómo se da de baja? | Un usuario con el rol `veterinario`. En uso se inactiva; sin uso se da de baja, y un alta del mismo usuario restaura el anterior | 2026-11-06 |
| P7 | ¿Se usa la comisión? | Se guarda; no se calcula hasta los reportes | 2026-11-06 |
| P8 | ¿Hay precios distintos por sucursal? | No: un precio único por servicio; la factura guardará su precio | 2026-11-06 |
| P9 | ¿`establishment` se edita? ¿Se eliminan sucursales? | Se edita solo sin facturas (preparado; hoy siempre). Sucursales y depósitos solo se inactivan; los por defecto, no | 2026-11-06 |
| P10 | ¿Varios depósitos por sucursal? | Sí, uno por defecto | 2026-11-06 |
| P11 | ¿Qué datos de la clínica salen en recetas y facturas? | Razón social, nombre de fantasía y RUC de la clínica; dirección y teléfono de cada sucursal. Timbrado, actividad económica y punto de expedición, en la fase de SIFEN | 2026-11-06 |
| P12 | ¿Cómo se muestra el logo antes del login? | No se muestra: va en `me` y en la configuración. Sin slug en `clinics` | 2026-11-06 |
| P13 | ¿Qué guarda `system_settings`? | Una lista cerrada de claves (Enum), con validación por clave | 2026-11-06 |
| P14 | ¿Quién ve la auditoría y qué muestra? | Solo el administrador; filtros por fecha, usuario, evento y registro; sin valores sensibles; por clínica; sin los `LOGIN_FAILED` sin clínica | 2026-11-06 |
| P15 | ¿Qué pasa con la foto de un paciente dado de baja? | Se conserva. La limpieza solo borra archivos que no sean el `photo_path` ni el logo de nadie | 2026-11-06 |
| — | Email del usuario | Único en toda la base (es el login): una persona en dos clínicas necesita dos emails en el MVP | 2026-11-06 |

Las preguntas que deja el diseño están al final, en [Preguntas del diseño](#preguntas-del-diseño).

## Regla de oro aplicada

Análisis con [`docs/project/principios.md`](../project/principios.md).

| Pregunta | Sucursal / depósito | Usuario | Veterinario | Servicio | Configuración y logo |
|---|---|---|---|---|---|
| ¿Quién la crea? | Administrador; la primera, `clinic:create` | Administrador; el primero, `clinic:create` | Administrador | Administrador | Administrador |
| ¿Quién la modifica? | Administrador | Administrador; la sucursal actual y la contraseña propia, el mismo usuario | Administrador | Administrador | Administrador |
| ¿Quién la usa? | Usuarios (sucursal actual), agenda, consultas, caja, timbrado, facturas; depósitos: stock | Todo registro con `created_by`, `issued_by`, `cancelled_by`…; la caja (`user_id`) | Agenda, consultas, ítems, aplicaciones y documentos | Agenda, turnos, ítems de consulta y, después, facturas | Recetas, documentos y comprobantes no fiscales (FASE 6); el frontend |
| Estados | Activa / inactiva; por defecto | Activo / inactivo / eliminado | Activo / inactivo / eliminado | Activo / inactivo / eliminado | — |
| ¿Qué depende de ella? | Depósitos, `branch_user`, `current_branch_id` y las tablas de las fases 3 a 9 | Veterinario, auditoría, autoría de documentos | Fases 3, 4 y 6 | Fases 3, 4 y 8 | — |
| Impacto económico | `establishment` numera las facturas (SIFEN): no cambia con facturas | Ninguno directo | Comisión (sin cálculo en el MVP) | Precio y tasa de IVA de lo que se factura | Ninguno |
| ¿Qué pasa si se cancela? | Se inactiva: sale de las asignaciones y de la sucursal actual | Se inactiva: no entra y pierde sus tokens | Se inactiva: no se asigna en las fases siguientes | Se inactiva: no se ofrece en altas nuevas | — |
| ¿Qué pasa si se elimina? | No se elimina | Solo sin uso; el email queda libre (sufijo) | Solo sin uso; un alta del mismo usuario lo restaura | Solo sin uso; el nombre queda libre (sufijo) | El logo se borra; la configuración no se elimina |
| Permisos | `branches.*`, `warehouses.*` (existen) | `users.*`, `roles.index` (existen) | `veterinarians.*` (existen) | `services.*` (existen) | `system-settings.update` (existe) y cuatro nuevos |
| Consulta, stock, facturación | La consulta y la factura guardan su sucursal; el stock vive en el depósito | La consulta guarda quién la cerró | La consulta guarda su veterinario | La consulta y la factura guardan su precio | La factura lee los datos de la clínica al emitirse |

## Decisiones comunes

| Tema | Decisión |
|---|---|
| Permisos | Los de la FASE 2 ya existen en `RolesAndPermissionsSeeder::PERMISSIONS`, salvo los cuatro de la configuración y el logo (2.5): **123 → 127**. Lo administra el administrador (`Gate::before`); los roles de la clínica suman solo lecturas (pregunta 19 y 2.5) |
| Rutas | Hoy 26. Suman 7 (2.1), 5 (2.2a), 3 (2.2b), 5 (2.3), 5 (2.4), 5 (2.5) y 2 (2.6): **58** al cerrar la fase (con `PATCH me/password`, pregunta 20) |
| Migraciones | Dos nuevas: `users.is_active` (2.2b) y `veterinarians.license_number` a 60 caracteres (2.3). **32 → 34** |
| Bajas | Sufijo `//deleted_<id>_<timestamp>` (`DeletedSuffix`) en los únicos de usuarios (email), veterinarios (matrícula) y servicios (nombre). El largo que se valida deja lugar al sufijo entero (30 caracteres con ids de hasta 9 dígitos) |
| Nombres únicos | Sucursal y servicio: sin mayúsculas, acentos ni espacios repetidos (`CatalogName`), comparados en la aplicación con la fila de la clínica bloqueada (`CatalogLock`), como especies y razas |
| Listados | Form Request con los filtros de `ListFilters` (`search` literal con `whereContains`, `per_page`, `page`) y los propios de cada recurso |
| Resources | `XResource` para listados y `ShowXResource` para el detalle; siempre con `ApiResponse` (salvo la descarga del logo) |
| Auditoría | Los modelos nuevos o tocados (`Branch`, `Warehouse`, `User`, `Veterinarian`, `Service`, `SystemSetting`, `Clinic`) usan `Auditable`. Los cambios de roles quedan como `ROLES_SYNCED` |
| Deuda 2 | Cada entrega revisa las relaciones y los casts del modelo que usa (`Branch`, `Warehouse`, `User`, `Veterinarian`, `Service`, `AuditLog`) y lo anota en su resultado |
| Deuda 5 | No se agregan claves foráneas compuestas: cada referencia nueva (`branch_user`, `warehouses.branch_id`, `veterinarians.user_id`, `audit_logs.user_id` en los filtros) se valida con `exists` por clínica y tiene su test de aislamiento (`tests/Feature/Tenancy`) |
| Orden de bloqueo | Se suman a `CLAUDE.md` §3: clínica → sucursal → depósito; clínica → usuario → veterinario → serie; clínica → servicio → serie; clínica → configuración |

## Diseño por entrega

### 2.1 Sucursales y depósitos

**Alcance.** ABM de sucursales y de sus depósitos, sin baja (P9). La sucursal `SUC-01` y el depósito `DEP-01` que crea
`clinic:create` son los por defecto.

**Migraciones:** ninguna (`branches`, `warehouses` y `branch_user` existen).

**Reglas.**

| Regla | Detalle |
|---|---|
| Código | Lo escribe el administrador (pregunta 16): obligatorio, hasta 20, `^[A-Z0-9-]+$` (se guarda en mayúsculas), único en la clínica, editable. Igual en depósitos |
| Nombre | Obligatorio, 2 a 100, único en la clínica con `CatalogName` (sucursales); en depósitos, único dentro de su sucursal |
| `establishment` | Tres dígitos (`^\d{3}$`, de `001` a `999`), único en la clínica. Se edita solo si la sucursal no tiene facturas (`invoices.branch_id`): si tiene, 422 `BRANCH_ESTABLISHMENT_NOT_EDITABLE`. En esta fase la API no crea facturas: se prueba con una fila insertada por el helper de tests (P9) |
| Dirección y teléfono | `address` hasta 255; `phone` con la regla de clientes (`+?[1-9]\d{7,14}`), opcionales (P11) |
| Sucursal por defecto | Siempre una por clínica. `is_default: true` en otra sucursal (activa) la pasa a ser la por defecto y la anterior deja de serlo, en la misma transacción. `is_default: false` se rechaza (422 en `is_default`: "Siempre tiene que haber una sucursal por defecto: marcá otra."). Una inactiva no puede ser la por defecto (422) |
| Inactivar | `is_active: false`. La por defecto no: 409 `BRANCH_DEFAULT_NOT_DEACTIVATABLE`. Una sucursal inactiva no se asigna a usuarios ni se elige como actual; los usuarios que la tenían como actual quedan sin sucursal actual (pregunta 18). Sus depósitos no cambian |
| Alta de sucursal | Crea en la misma transacción su depósito por defecto, con `warehouse_code` (obligatorio) y `warehouse_name` ("Principal" si no llega) (pregunta 17). Al administrador que la crea no se le asigna sola |
| Depósito | Alta anidada en la sucursal (la sucursal sale de la URL; `branch_id` en el cuerpo se ignora) y edición plana; `branch_id` `prohibited` al editar. Uno por defecto en cada sucursal, con las mismas reglas que la sucursal (cambiar, no quitar, no inactivar: 409 `WAREHOUSE_DEFAULT_NOT_DEACTIVATABLE`) |
| Listados | Activas por defecto; `include_inactive=1` trae todas. `search` en código y nombre |

**Endpoints** (todos con `auth:sanctum` y `clinic`):

| Método | Ruta | Nombre y permiso | Request | Respuesta | Errores |
|---|---|---|---|---|---|
| GET | `branches` | `branches.index` | `IndexBranchRequest`: `ListFilters`, `include_inactive` | 200, `BranchResource` paginado | 422 |
| POST | `branches` | `branches.store` | `StoreBranchRequest`: `code`, `name`, `establishment`, `address`, `phone`, `is_default`, `warehouse_code`, `warehouse_name` | 201, `ShowBranchResource` con sus depósitos | 422 |
| GET | `branches/{branch}` | `branches.show` | — | 200, `ShowBranchResource` | 404 |
| PATCH/PUT | `branches/{branch}` | `branches.update` | `UpdateBranchRequest` (`sometimes`) | 200 | 404; 409 `BRANCH_DEFAULT_NOT_DEACTIVATABLE`; 422 `BRANCH_ESTABLISHMENT_NOT_EDITABLE`; 422 |
| GET | `branches/{branch}/warehouses` | `branches.warehouses.index` → `warehouses.index` | `IndexWarehouseRequest` | 200, `WarehouseResource` | 404 |
| POST | `branches/{branch}/warehouses` | `branches.warehouses.store` → `warehouses.store` | `StoreWarehouseRequest`: `code`, `name`, `is_default` | 201 | 404; 422 |
| PATCH/PUT | `warehouses/{warehouse}` | `warehouses.update` | `UpdateWarehouseRequest`; `branch_id` `prohibited` | 200 | 404; 409 `WAREHOUSE_DEFAULT_NOT_DEACTIVATABLE`; 422 |

Las rutas anidadas siguen la regla de los dos últimos tramos: no necesitan excepción en `ROUTES_WITH_ANOTHER_PERMISSION`.

**Errores** (`BranchException`, nueva, para sucursales y depósitos):

| `error_code` | HTTP | Mensaje |
|---|---|---|
| `BRANCH_DEFAULT_NOT_DEACTIVATABLE` | 409 | La sucursal por defecto no se puede inactivar: marcá otra como sucursal por defecto primero. |
| `BRANCH_ESTABLISHMENT_NOT_EDITABLE` | 422 | El código de establecimiento no se puede cambiar: la sucursal ya tiene facturas. |
| `WAREHOUSE_DEFAULT_NOT_DEACTIVATABLE` | 409 | El depósito por defecto no se puede inactivar: marcá otro como depósito por defecto primero. |

**Concurrencia.** Alta y edición en `DB::transaction`: primero la fila de la clínica (`CatalogLock`, nombres y sucursal por
defecto), después la sucursal (`lockForUpdate`, depósito por defecto). Orden nuevo: clínica → sucursal → depósito.

**Tests** (`tests/Feature/Branches`, con un rol de test con `branches.*` y `warehouses.*`; el `recepcionista` para el 403 de escritura):

| Test | Qué verifica |
|---|---|
| `BranchStoreTest` | Alta con su depósito por defecto; código en mayúsculas y único; nombre repetido con otras mayúsculas o acentos → 422; `establishment` mal formado o repetido → 422; teléfono inválido → 422 |
| `BranchUpdateTest` | Cambio de la por defecto (la anterior deja de serlo); `is_default: false` → 422; inactivar la por defecto → 409; inactiva como por defecto → 422; `establishment` con facturas → 422 y sin facturas → 200; usuarios con la sucursal actual inactivada quedan sin sucursal actual |
| `WarehouseTest` | Alta anidada (un `branch_id` en el cuerpo se ignora), uno por defecto por sucursal, no se inactiva el por defecto, `branch_id` al editar → 422, depósito de otra clínica → 404 |
| `BranchIndexTest` | Activas por defecto, `include_inactive`, `search`, paginación; no trae las de otra clínica |
| Acceso y aislamiento | Las rutas nuevas en `tests/Feature/Access` (por rol) y una sucursal de otra clínica → 404 en cada ruta |

**Mutaciones críticas:** quitar el chequeo de la por defecto al inactivar; quitar el paso de `is_default` a la anterior; quitar el
chequeo de facturas de `establishment`; quitar el bloqueo de la clínica en el alta (test con `for update` en MySQL, como
`CatalogLockTest`); quitar el filtro de clínica del `exists` de la sucursal.

**Postman:** `syncvet-fase-02-sucursales` (alta con depósito, cambio de la por defecto, inactivar la por defecto → 409, depósitos), repetible con su rango de corrida.

### 2.2a Usuarios: ABM, roles y sucursales

**Alcance.** Alta, edición, detalle y listado de usuarios de la clínica, con sus roles y sucursales. Solo el administrador (P2).

**Migraciones:** ninguna.

**Reglas.**

| Regla | Detalle |
|---|---|
| Datos | `first_name` y `last_name` obligatorios (2 a 100); `name` lo arma el Service (`first_name last_name`), como `ClinicService` |
| Email | Obligatorio, formato email, solo ASCII (como el login), en minúsculas, hasta 150 (deja lugar al sufijo de baja en la columna de 255), **único en toda la base** contando eliminados (R-029): 422 "Ya hay un usuario con ese email." |
| Contraseña | Obligatoria en el alta, opcional al editar: `UserPassword` (R-009), con `#[\SensitiveParameter]`. Un cambio de contraseña por el administrador revoca los tokens del usuario (pregunta 20) |
| Roles | `roles`: lista de 1 o más de `administrador`, `recepcionista` y `veterinario` (P3), sin repetidos. Se sincronizan con `syncRoles` y quedan en la auditoría como `ROLES_SYNCED` |
| Sucursales | `branch_ids`: lista de 1 o más sucursales activas de la clínica (`exists` por clínica). Al editar se acepta también una que el usuario ya tiene aunque se haya inactivado |
| Sucursal actual | Al crear: la por defecto si está en `branch_ids`, si no la primera. Al editar, si la actual sale de `branch_ids`, pasa a ser la misma regla |
| Último administrador (P4) | Quitarle el rol `administrador` al último administrador activo de la clínica → 409 `USER_LAST_ADMIN` |
| `clinic_id` | No se recibe: lo fija el Service con la clínica en contexto |
| Listado | `search` en nombre, apellido y email; filtros `role` y `branch_id` |

**Endpoints:**

| Método | Ruta | Nombre y permiso | Request | Respuesta | Errores |
|---|---|---|---|---|---|
| GET | `users` | `users.index` | `IndexUserRequest`: `ListFilters`, `role`, `branch_id` | 200, `UserResource` | 422 |
| POST | `users` | `users.store` | `StoreUserRequest`: `first_name`, `last_name`, `email`, `password`, `roles[]`, `branch_ids[]` | 201, `ShowUserResource` | 422 |
| GET | `users/{user}` | `users.show` | — | 200, `ShowUserResource` | 404 (también de otra clínica) |
| PATCH/PUT | `users/{user}` | `users.update` | `UpdateUserRequest` (`sometimes`) | 200 | 404; 409 `USER_LAST_ADMIN`; 422 |
| GET | `roles` | `roles.index` | — | 200, `RoleResource` (`name`, `label`), sin paginar | — |

`UserResource`: `id`, `first_name`, `last_name`, `name`, `email`, `roles`, `branches` (`id`, `code`, `name`), `current_branch_id`.
`ShowUserResource` suma `veterinarian` (desde la 2.3) y las fechas. Nunca `password` ni `remember_token`. Con la 2.2b suman
`is_active`.

**Errores** (`UserException`, nueva):

| `error_code` | HTTP | Mensaje |
|---|---|---|
| `USER_LAST_ADMIN` | 409 | La clínica tiene que tener al menos un administrador activo. |

**Concurrencia.** Alta y edición en `DB::transaction`; la edición que toca los roles bloquea la fila de la clínica antes de contar
los administradores activos (dos pedidos simultáneos no dejan la clínica sin administrador). Orden: clínica → usuario.

**Tests** (`tests/Feature/Users`, con el administrador; el `recepcionista` y el `veterinario` para el 403):

| Test | Qué verifica |
|---|---|
| `UserStoreTest` | Alta con roles y sucursales; email en minúsculas; email con acentos → 422; email de otra clínica o de un usuario eliminado → 422; contraseña débil o filtrada → 422; sucursal de otra clínica o inactiva → 422; rol inexistente → 422; sucursal actual según la regla; la contraseña no sale en la respuesta ni en la auditoría |
| `UserUpdateTest` | Cambio de roles (`ROLES_SYNCED`); quitar el rol al último administrador → 409; con otro administrador activo → 200; cambio de sucursales mueve la actual; cambio de contraseña revoca los tokens del usuario |
| `UserIndexTest` | `search`, `role`, `branch_id`, paginación; no trae usuarios de otra clínica |
| `RolesIndexTest` | Los tres roles con su nombre en español |
| Acceso y aislamiento | Usuario de otra clínica → 404 en cada ruta |

**Mutaciones críticas:** quitar el chequeo del último administrador; quitar el bloqueo de la clínica al contar administradores;
quitar `withTrashed()` del email único; quitar el filtro de clínica del `exists` de las sucursales; quitar la revocación de tokens al
cambiar la contraseña.

**Postman:** `syncvet-fase-02-usuarios` (con la 2.2b), repetible: emails únicos por corrida.

### 2.2b Usuarios: inactivos, baja, sucursal actual y contraseña propia

**Alcance.** P1 y P4, la sucursal actual y el cambio de la contraseña propia (pregunta 20).

**Migración nueva** `2026_11_xx_000000_add_is_active_to_users`: `is_active` boolean, por defecto `true`, después de `password`.
Probada en MySQL: subir, bajar y subir.

**Reglas.**

| Regla | Detalle |
|---|---|
| Inactivar | `is_active` en `UpdateUserRequest`. Al pasar a `false`, el modelo revoca todos los tokens del usuario (como al eliminarlo). Reactivar no devuelve tokens |
| Login de un inactivo | Contraseña correcta → 403 `USER_INACTIVE` "Tu usuario está inactivo: consultá con el administrador de la clínica.", `LOGIN_FAILED` con el motivo "usuario inactivo" y no cuenta como intento fallido (como la clínica suspendida). Contraseña incorrecta → 422 `INVALID_CREDENTIALS`, como siempre: no revela que la cuenta existe. Se revisa después de la clínica suspendida |
| Token de un inactivo | Defensa en profundidad: `SetCurrentClinic` responde 403 `USER_INACTIVE` si el usuario está inactivo (por ejemplo, un token creado justo antes de inactivarlo). `logout` sigue funcionando |
| A sí mismo (P4) | El administrador no se inactiva ni se da de baja a sí mismo: 409 `USER_SELF_ACTION` |
| Último administrador (P4) | Inactivar o dar de baja al último administrador activo → 409 `USER_LAST_ADMIN` |
| Baja | `DELETE users/{user}`, solo sin uso (P1). Uso: una ficha de veterinario (también eliminada) o cualquier referencia en `created_by`, `issued_by`, `cancelled_by`, `closed_by`, `uploaded_by` o `cash_registers.user_id` de las tablas de negocio (lista en `UserService::USAGE`, con un test que exige que cubra toda clave foránea a `users`, salvo `audit_logs` y `branch_user`). Con uso → 409 `USER_IN_USE`. Al eliminar: sufijo en el email, tokens revocados (evento `deleted`, ya existe) y `branch_user` se conserva |
| Sucursal actual | `PATCH me/current-branch` con `branch_id`: una sucursal activa asignada al usuario (si no, 422 en `branch_id`). Responde `MeResource` |
| Contraseña propia | `PATCH me/password` con `current_password` (correcta, si no 422 en `current_password`) y `password` (`UserPassword`, distinta de la actual). Revoca los demás tokens del usuario y conserva el actual |
| Listado | `UserResource` suma `is_active`; `IndexUserRequest` suma `include_inactive` (activos por defecto) |

**Endpoints:**

| Método | Ruta | Nombre y permiso | Request | Respuesta | Errores |
|---|---|---|---|---|---|
| DELETE | `users/{user}` | `users.destroy` | — | 200 | 404; 409 `USER_IN_USE`, `USER_SELF_ACTION`, `USER_LAST_ADMIN` |
| PATCH | `me/current-branch` | `auth.current-branch`, sin permiso (`ROUTES_WITHOUT_PERMISSION`: cualquier usuario autenticado elige su sucursal) | `UpdateCurrentBranchRequest`: `branch_id` | 200, `MeResource` | 401; 403 `USER_INACTIVE`; 422 |
| PATCH | `me/password` | `auth.password`, sin permiso (`ROUTES_WITHOUT_PERMISSION`) | `UpdateOwnPasswordRequest`: `current_password`, `password` | 200 | 401; 403; 422 |

Las dos rutas de `me` llevan `auth:sanctum` y `clinic`.

**Errores:**

| `error_code` | HTTP | Excepción | Mensaje |
|---|---|---|---|
| `USER_INACTIVE` | 403 | `AuthException` | Tu usuario está inactivo: consultá con el administrador de la clínica. |
| `USER_SELF_ACTION` | 409 | `UserException` | No podés inactivarte ni darte de baja a vos mismo. |
| `USER_IN_USE` | 409 | `UserException` | El usuario tiene registros a su nombre y no se puede eliminar: inactivalo. |
| `USER_LAST_ADMIN` | 409 | `UserException` | La clínica tiene que tener al menos un administrador activo. |

**Concurrencia.** Inactivar, quitar el rol y dar de baja bloquean la fila de la clínica antes de contar los administradores
activos, y después la del usuario. La baja revisa el uso dentro de su transacción.

**Tests** (`tests/Feature/Users` y `tests/Feature/Auth`):

| Test | Qué verifica |
|---|---|
| `UserDeactivateTest` | Inactivar revoca todos los tokens (el token viejo da 401); reactivar; a sí mismo → 409; al último administrador → 409; listado sin inactivos salvo `include_inactive=1` |
| `LoginInactiveUserTest` | Inactivo con la contraseña correcta → 403 `USER_INACTIVE`, sin token y con `LOGIN_FAILED`; con una incorrecta → 422 `INVALID_CREDENTIALS`; no cuenta para el límite de intentos |
| `InactiveUserTokenTest` | Un token vigente de un usuario inactivado sin revocar (fila editada a mano) → 403 en una ruta con `clinic`; `logout` funciona |
| `UserDeleteTest` | Sin uso → 200, email libre para un alta nueva; con ficha de veterinario o con una fila con `created_by` → 409; a sí mismo → 409; `UserService::USAGE` cubre toda clave foránea a `users` |
| `CurrentBranchTest` | Sucursal asignada y activa → 200 con `MeResource`; no asignada, inactiva o de otra clínica → 422 |
| `OwnPasswordTest` | Contraseña actual incorrecta → 422; nueva débil → 422; cambio correcto revoca los otros tokens y conserva el actual |

**Mutaciones críticas:** quitar la revocación de tokens al inactivar; quitar el chequeo de `is_active` del login (o ponerlo antes
de verificar la contraseña); quitar el chequeo de `SetCurrentClinic`; quitar el chequeo de uso de la baja; quitar el chequeo de
"a sí mismo".

**Postman:** la colección de usuarios suma inactivar, el login del inactivo (403 y 422), la sucursal actual y la contraseña propia.

### 2.3 Veterinarios

**Alcance.** ABM de veterinarios con código `VET-` (P5 a P7).

**Migración nueva** `2026_11_xx_000001_widen_veterinarians_license_number`: `license_number` de 30 a 60 caracteres, para el sufijo
de baja (pregunta 29). Se valida hasta 30.

**Reglas.**

| Regla | Detalle |
|---|---|
| Usuario | `user_id` obligatorio en el alta: un usuario activo de la clínica con el rol `veterinario` (P6). Si ya tiene una ficha no eliminada → 422 en `user_id` "Ese usuario ya es veterinario." `user_id` `prohibited` al editar |
| Restauración (P6) | Si el usuario tiene una ficha eliminada, el alta la restaura con los datos del pedido y conserva su código `VET-`: 201, igual que un alta |
| Código | `VET-…` de `DocumentSequenceService` (`DocumentSeries::VETERINARIAN`); `code` en el cuerpo → 422 (`prohibited`), como en pacientes |
| Datos | `license_number` hasta 30, opcional, única en la clínica; `specialty` hasta 100; `commission_percentage` de 0 a 100 con hasta 2 decimales, sin cálculos (P7); `notes` hasta 1000; `status` `active` o `inactive` (`VeterinarianStatus`) |
| Sucursales (P5) | Las de su usuario: la respuesta las muestra y el listado filtra por `branch_id` |
| Baja | Solo sin uso: `schedules`, `appointments`, `consultations`, `consultation_items`, `patient_applications` y `patient_documents` (también eliminados). Con uso → 409 `VETERINARIAN_IN_USE`. Al eliminar, sufijo en `license_number` |
| Rol del usuario | Quitarle el rol `veterinario` (2.2a) a un usuario con una ficha no eliminada → 409 `USER_IS_VETERINARIAN` |
| Usuario inactivo | La ficha no cambia; la respuesta muestra `user.is_active` (las fases 3 y 4 no lo asignan) |
| Listado | `search` en código, nombre del usuario y matrícula; filtros `status` y `branch_id`; activos por defecto |

**Endpoints** (`apiResource('veterinarians')`):

| Método | Ruta | Nombre y permiso | Respuesta | Errores |
|---|---|---|---|---|
| GET | `veterinarians` | `veterinarians.index` | 200, `VeterinarianResource` | 422 |
| POST | `veterinarians` | `veterinarians.store` | 201, `ShowVeterinarianResource` | 422 |
| GET | `veterinarians/{veterinarian}` | `veterinarians.show` | 200 | 404 |
| PATCH/PUT | `veterinarians/{veterinarian}` | `veterinarians.update` | 200 | 404; 422 |
| DELETE | `veterinarians/{veterinarian}` | `veterinarians.destroy` | 200 | 404; 409 `VETERINARIAN_IN_USE` |

`MeResource` ya muestra `veterinarian` (`id`, `code`): sin cambios.

**Errores:**

| `error_code` | HTTP | Excepción | Mensaje |
|---|---|---|---|
| `VETERINARIAN_IN_USE` | 409 | `VeterinarianException` | El veterinario tiene registros y no se puede eliminar: inactivalo. |
| `USER_IS_VETERINARIAN` | 409 | `UserException` | El usuario tiene una ficha de veterinario: eliminala antes de quitarle el rol. |

**Concurrencia.** Alta y restauración bloquean la fila del usuario (`lockForUpdate`) antes de buscar su ficha (también eliminada);
la serie va última. Quitar el rol `veterinario` bloquea la misma fila. Orden: usuario → veterinario → serie. La baja revisa el uso
con la ficha bloqueada; las fases 3 y 4 tomarán un `sharedLock` sobre el veterinario al usarlo, como el paciente con la especie.

**Tests** (`tests/Feature/Veterinarians`):

| Test | Qué verifica |
|---|---|
| `VeterinarianStoreTest` | Alta con `VET-` correlativo; usuario sin el rol, inactivo, de otra clínica o que ya es veterinario → 422; `code` → 422; matrícula repetida → 422; comisión fuera de rango → 422 |
| `VeterinarianRestoreTest` | Alta de un usuario con ficha eliminada: misma fila y mismo código, datos nuevos, `RESTORED` en la auditoría |
| `VeterinarianDeleteTest` | Sin uso → 200 y matrícula libre; con un turno, consulta o aplicación (filas del helper) → 409 |
| `VeterinarianUpdateTest` | Inactivar en uso; `user_id` → 422; quitar el rol `veterinario` al usuario → 409 |
| `VeterinarianIndexTest` | `search`, `status`, `branch_id` por las sucursales del usuario; la recepción lo lista (permiso existente) |

**Mutaciones críticas:** quitar la restauración (crear siempre); quitar el chequeo del rol; quitar el chequeo de uso; quitar el
bloqueo del usuario (test con `for update` en MySQL); quitar el chequeo de `USER_IS_VETERINARIAN`.

**Postman:** `syncvet-fase-02-veterinarios` (crea su usuario veterinario en la corrida, alta, baja, restauración).

### 2.4 Servicios

**Alcance.** Catálogo de servicios con código `SRV-` (P8).

**Migraciones:** ninguna (`services.name` de 150 admite 120 más el sufijo).

**Reglas.**

| Regla | Detalle |
|---|---|
| Código | `SRV-…` de `DocumentSequenceService` (`DocumentSeries::SERVICE`); `code` → 422 (`prohibited`) |
| Nombre | Obligatorio, 2 a 120, único en la clínica con `CatalogName` frente a los no eliminados (el índice `(clinic_id, name)` lo refuerza). **Un servicio usado no cambia de nombre**: 422 `SERVICE_NAME_NOT_EDITABLE` (uso: `schedules`, `appointments` y `consultation_items`) |
| Precio (P8) | `standard_price` único, de 0 a 999.999.999.999, **sin decimales** mientras la moneda sea PYG (pregunta 21). Se edita siempre: lo usado guarda su precio |
| IVA | `tax_rate`: 0, 5 o 10 (`TaxRate`), 10 por defecto |
| Otros | `category` (`ServiceCategory`), `description` hasta 1000, `duration_minutes` de 5 a 720 en múltiplos de 5 (pregunta 22), `status` (`ServiceStatus`) |
| Baja | Solo sin uso (mismas tablas, también eliminados) → si no, 409 `SERVICE_IN_USE`. Sufijo en el nombre |
| Listado | `search` en código y nombre; filtros `category` y `status`; activos por defecto |

**Endpoints** (`apiResource('services')`): `services.index`, `services.store`, `services.show`, `services.update` y
`services.destroy`, con `ServiceResource` y `ShowServiceResource`. Errores: 404; 409 `SERVICE_IN_USE`; 422 `SERVICE_NAME_NOT_EDITABLE`;
422 `VALIDATION_ERROR`. La recepción y el veterinario listan y ven (permisos existentes).

**Errores** (`ServiceException`, existente):

| `error_code` | HTTP | Mensaje |
|---|---|---|
| `SERVICE_NAME_NOT_EDITABLE` | 422 | El servicio ya se usó y no puede cambiar de nombre: creá otro e inactivá este. |
| `SERVICE_IN_USE` | 409 | El servicio ya se usó y no se puede eliminar: inactivalo. |

**Concurrencia.** Alta y cambio de nombre bloquean la clínica (`CatalogLock`); la edición y la baja bloquean el servicio antes de
revisar su uso; la serie va última. Las fases 3 y 4 tomarán un `sharedLock` sobre el servicio al usarlo.

**Tests** (`tests/Feature/Services`): alta con `SRV-`; nombre repetido con otras mayúsculas o acentos → 422; precio con decimales,
negativo o enorme → 422; duración fuera de rango → 422; cambio de nombre usado → 422 y sin uso → 200; cambio de precio usado → 200;
baja con uso → 409 y sin uso libera el nombre; filtros y paginación; 403 para la recepción al escribir.

**Mutaciones críticas:** quitar el chequeo del nombre usado; quitar el chequeo de uso de la baja; quitar el sufijo de baja; quitar
la regla de enteros del precio; quitar el bloqueo de la clínica en el alta.

**Postman:** `syncvet-fase-02-servicios`.

### 2.5 Configuración de la clínica, logo y archivos huérfanos (deuda 14)

**Alcance.** Datos de la clínica (P11), configuración en `system_settings` (P13), logo (P12) y el comando de limpieza (P15).

**Migraciones:** ninguna (`clinics` y `system_settings` existen). Modelo nuevo `SystemSetting` (`BelongsToClinic`, `Auditable`).

**Reglas.**

| Regla | Detalle |
|---|---|
| Datos de la clínica | `name` (nombre de fantasía, 2 a 100) y `legal_name` (razón social, 2 a 150), editables. El RUC se muestra pero no se edita por la API (pregunta 24) |
| Claves (P13) | Enum `SystemSettingKey` con su regla por clave (pregunta 23): `contact_email` (email, hasta 150), `contact_phone` (la regla de teléfonos), `website` (URL `https`, hasta 150) y `document_footer` (texto, hasta 500). Una clave fuera de la lista → 422. `null` borra el valor |
| Logo (P12) | JPG, PNG o WEBP de hasta 2 MB por el contenido, vuelto a codificar con `ImageReencoder` (sin EXIF), hasta 25 megapíxeles; más grande → 413 `PAYLOAD_TOO_LARGE` (pregunta 25). Disco propio `clinic_logos` (`storage/app/private/clinic-logos`, sin `serve`), en `clinics/{clinic_id}/logo/{uuid}.{ext}`. La ruta va en la clave interna `logo_path` (fuera de la lista editable) y nunca sale de la API: `has_logo` en la configuración y en `me` (`clinic.has_logo`) |
| Reemplazo y archivo ajeno | Las reglas de la foto del paciente (§6.2): se borra el anterior después del commit; si la transacción falla, el nuevo; una ruta fuera de la carpeta de la clínica o sin archivo → 404 `CLINIC_LOGO_NOT_FOUND` y un aviso en el log |
| Reutilización | Lo común con `PatientPhotoService` (guardar, reemplazar después del commit, validar la ruta, descargar con `nosniff`, CSP y `no-store`) pasa a una clase compartida (`PrivateImageStore`); los tests de la foto no cambian |
| Limpieza (deuda 14, P15) | Comando `files:prune-orphans` con `--dry-run`: por cada clínica (`CurrentClinic::run`) borra los archivos de las carpetas de fotos y del logo que no sean el `photo_path` de un paciente (también eliminado) ni el `logo_path` de la clínica, y que tengan más de 24 horas (protege una subida en curso; pregunta 26). Informa y registra en el log lo borrado. Programado a diario a las 03:00 en `routes/console.php`; en producción necesita el cron del *scheduler* (FASE 10, despliegue) |

**Endpoints:**

| Método | Ruta | Nombre y permiso | Request | Respuesta | Errores |
|---|---|---|---|---|---|
| GET | `system-settings` | `system-settings.show` (**nuevo**) | — | 200, `SystemSettingsResource`: `clinic` (`name`, `legal_name`, `ruc`, `ruc_dv`), `settings` (todas las claves, `null` si no tienen valor), `has_logo` | — |
| PATCH/PUT | `system-settings` | `system-settings.update` | `UpdateSystemSettingsRequest`: `name`, `legal_name`, `settings.<clave>` (`sometimes`); `ruc` y `ruc_dv` `prohibited` | 200 | 422 |
| POST | `system-settings/logo` | `system-settings.upload-logo` (**nuevo**) | `UploadClinicLogoRequest` (`multipart/form-data`, `logo`) | 200, `SystemSettingsResource` | 413; 422 |
| GET | `system-settings/logo` | `system-settings.show-logo` (**nuevo**) | — | 200, el archivo (como la foto) | 404 `CLINIC_LOGO_NOT_FOUND` |
| DELETE | `system-settings/logo` | `system-settings.delete-logo` (**nuevo**) | — | 200 | 404 `CLINIC_LOGO_NOT_FOUND` |

**Permisos:** cuatro nuevos (123 → 127). `recepcionista` y `veterinario` suman `system-settings.show` y
`system-settings.show-logo` (los documentos y recibos llevan los datos y el logo). `CLAUDE.md` §4 suma la descarga del logo a la
excepción de `ApiResponse`.

**Errores** (`ClinicException`, existente):

| `error_code` | HTTP | Mensaje |
|---|---|---|
| `CLINIC_LOGO_NOT_FOUND` | 404 | La clínica no tiene logo. |

**Concurrencia.** Editar la configuración y subir o borrar el logo bloquean la fila de la clínica antes de leer `logo_path` o las
claves. Orden: clínica → configuración.

**Tests** (`tests/Feature/Settings`, con `Storage::fake`):

| Test | Qué verifica |
|---|---|
| `SystemSettingsTest` | Lectura por los tres roles; edición por el administrador y 403 para los demás; clave desconocida → 422; regla de cada clave; `null` borra; `ruc` → 422; auditoría del cambio |
| `ClinicLogoTest` | Subida de los tres tipos, sin EXIF; texto con extensión `.png` o SVG → 422; más de 2 MB → 413; reemplazo borra el anterior después del commit; descarga con sus cabeceras; sin logo → 404; ruta ajena → 404 sin borrar; `has_logo` en `me` y en la configuración; la ruta no sale de la API |
| `PruneOrphanFilesTest` | Borra los huérfanos viejos de fotos y logo; conserva el `photo_path` de un paciente eliminado, el logo vigente y los archivos de menos de 24 horas; `--dry-run` no borra; no toca archivos de otra clínica fuera de su carpeta |
| `PatientPhotoTest` | Sin cambios, en verde con `PrivateImageStore` |

**Mutaciones críticas:** quitar la validación de la ruta del logo; quitar el umbral de 24 horas; no contar los pacientes eliminados
en la limpieza; quitar el bloqueo de la clínica al reemplazar el logo; aceptar una clave fuera de la lista.

**Postman:** `syncvet-fase-02-configuracion` con dos archivos en `docs/testing/postman/archivos`.

### 2.6 Auditoría (y deuda 13)

**Alcance.** Consulta de `audit_logs` por el administrador (P14) y la conversión de las fechas con hora en un lugar común (deuda 13).

**Migraciones:** ninguna: los índices `(clinic_id, created_at)`, `(clinic_id, event, created_at)`, `(user_id, created_at)` y
`(auditable_type, auditable_id)` cubren los filtros.

**Reglas.**

| Regla | Detalle |
|---|---|
| Clínica | `AuditLog` ya tiene el `ClinicScope` (corrige el análisis, que decía lo contrario): el listado y el *route binding* solo ven la clínica en contexto, y los `LOGIN_FAILED` sin clínica (`clinic_id` nulo) nunca aparecen. Un test lo exige |
| Filtros | `from` y `to` (fecha y hora; `to` no anterior a `from`), `user_id` (de la clínica, también eliminado), `event` (`AuditEvent`), `auditable_type` (lista cerrada: los tipos de los modelos con `Auditable`), `auditable_id` (exige `auditable_type`) y `search` literal en el nombre o el email del usuario; más `per_page` y `page` |
| Orden | `created_at` y `id` descendentes |
| Valores sensibles | `AuditLogger` ya enmascara `password`, `remember_token` y `token`. El Resource además quita `photo_path` y el valor de `logo_path` (rutas internas). El listado no trae `old_values` ni `new_values`: solo el detalle (pregunta 27) |
| Inmutable | Sin rutas de escritura; el modelo ya rechaza modificar y eliminar (`AUDIT_LOG_IMMUTABLE`) |
| Deuda 13 | Cast propio `App\Casts\AppDateTime`: al guardar convierte un valor con zona (`Z` o desplazamiento) a `America/Asuncion`; sin zona, se toma como hora de Asunción (como hoy, pregunta 28). Lo usan `Patient::deceased_at` (se quita la conversión de `PatientService`) y todo atributo con hora asignable de los modelos generados (`Appointment`, `Consultation`, `ScheduleBlock`…). Los filtros con hora (`from` y `to`, y el `after()` de `UpdatePatientRequest`) usan el mismo `App\Support\ApiDateTime::toLocal()`. Un test recorre los modelos y exige el cast en todo atributo de `$fillable` con `datetime`. La salida sigue en UTC (`Z`) |

**Endpoints:**

| Método | Ruta | Nombre y permiso | Request | Respuesta | Errores |
|---|---|---|---|---|---|
| GET | `audit-logs` | `audit-logs.index` | `IndexAuditLogRequest` | 200, `AuditLogResource`: `id`, `event` y su `label`, `auditable_type`, `auditable_id`, `user` (`id`, `name`), `ip_address`, `created_at` | 422 |
| GET | `audit-logs/{auditLog}` | `audit-logs.show` | — | 200, `ShowAuditLogResource`: suma `old_values`, `new_values` y `user_agent` | 404 (también de otra clínica o sin clínica) |

Solo el administrador (P14): ningún rol de la clínica recibe estos permisos.

**Errores:** solo los genéricos (`VALIDATION_ERROR`, `RESOURCE_NOT_FOUND`, `FORBIDDEN`).

**Tests** (`tests/Feature/Audit` y `tests/Unit`):

| Test | Qué verifica |
|---|---|
| `AuditLogIndexTest` | Cada filtro; `to` antes de `from` → 422; `auditable_id` sin tipo → 422; tipo fuera de la lista → 422; orden; paginación; no trae registros de otra clínica ni `LOGIN_FAILED` sin clínica; el recepcionista y el veterinario → 403 |
| `AuditLogShowTest` | Detalle con valores; contraseña enmascarada; sin `photo_path` ni `logo_path`; de otra clínica o sin clínica → 404 |
| `AppDateTimeTest` | `Z`, `-03:00` y sin zona guardan la hora correcta de Asunción, en SQLite y MySQL; `deceased_at` sigue igual de punta a punta |
| `DateTimeCastCoverageTest` | Todo atributo con hora de `$fillable` usa `AppDateTime` |

**Mutaciones críticas:** quitar el `ClinicScope` del listado (con `withoutGlobalScopes`); quitar el ocultamiento de `photo_path`;
volver a `datetime` en `deceased_at`; quitar la conversión de zona del filtro `from`; aceptar `auditable_id` sin tipo.

**Postman:** `syncvet-fase-02-auditoria` (genera registros con las otras colecciones y los filtra).

## Documentación por entrega

| Entrega | Además de lo de `docs/README.md` (Mantenimiento) |
|---|---|
| 2.1 | `CLAUDE.md` §3 (orden de bloqueo clínica → sucursal → depósito) y una sección nueva §6.4 de sucursales y depósitos (las pendientes de diseño pasan a §6.x) |
| 2.2a | `CLAUDE.md` §6 (usuarios: email único global, roles, último administrador) |
| 2.2b | `CLAUDE.md` §3 (usuarios inactivos) y §4 (`USER_INACTIVE`); guía de Postman con el login de un inactivo; deuda 6 sin cambios |
| 2.3 | `CLAUDE.md` §3 (orden usuario → veterinario → serie) y §6 (veterinarios) |
| 2.4 | `CLAUDE.md` §6 (servicios) |
| 2.5 | `CLAUDE.md` §4 (descarga del logo) y §6 (configuración); `development/setup.md` (disco `clinic_logos` y el cron del *scheduler*); deuda 14 resuelta |
| 2.6 | `CLAUDE.md` §3 (fechas con hora: `AppDateTime`); deuda 13 resuelta; módulo de auditoría COMPLETADO |

## Preguntas del diseño

Lo que el diseño no podía decidir solo, con su propuesta y la respuesta del usuario (R-029): todas aprobadas según la propuesta.

| # | Pregunta | Propuesta | Respuesta | Fecha |
|---|---|---|---|---|
| 16 | ¿Quién pone el código de sucursales y depósitos (`SUC-01`, `DEP-01`)? | Lo escribe el administrador: único en la clínica, en mayúsculas, editable (no son documentos ni salen de una serie) | Aprobada (usuario) | 2026-11-06 |
| 17 | ¿El alta de una sucursal crea su depósito por defecto? | Sí, en la misma transacción, con `warehouse_code` obligatorio y `warehouse_name` ("Principal" por defecto) | Aprobada (usuario) | 2026-11-06 |
| 18 | Al inactivar una sucursal, ¿qué pasa con los usuarios que la tienen como actual? | Quedan sin sucursal actual (`current_branch: null` en `me`) hasta que elijan otra; su asignación se conserva | Aprobada (usuario) | 2026-11-06 |
| 19 | ¿La recepción y el veterinario listan sucursales? | Sí, solo `branches.index` (la agenda las necesita en la FASE 3) | Aprobada (usuario) | 2026-11-06 |
| 20 | Contraseñas: ¿el cambio por el administrador revoca los tokens del usuario? ¿Se agrega `PATCH me/password` para la contraseña propia? | Sí a los dos (en la 2.2b, sin permiso, con la contraseña actual) | Aprobada (usuario) | 2026-11-06 |
| 21 | ¿El precio de un servicio admite decimales? | No mientras la moneda sea PYG: entero de 0 a 999.999.999.999 (la columna sigue en `decimal(14,2)`) | Aprobada (usuario) | 2026-11-06 |
| 22 | ¿Qué duración admite un servicio? | De 5 a 720 minutos, en múltiplos de 5 (la agenda de la FASE 3 usa bloques de 5) | Aprobada (usuario) | 2026-11-06 |
| 23 | ¿Qué claves tiene `system_settings`? | `contact_email`, `contact_phone`, `website` y `document_footer` (pie de recetas y comprobantes no fiscales); `logo_path`, interna | Aprobada (usuario) | 2026-11-06 |
| 24 | ¿El RUC de la clínica se edita por la API? | No: se muestra; un error de carga se corrige con un comando más adelante (lo crea `clinic:create`) | Aprobada (usuario) | 2026-11-06 |
| 25 | ¿Cómo es el logo? | JPG, PNG o WEBP de hasta 2 MB, vuelto a codificar como la foto, sin redimensionar; más grande → 413 | Aprobada (usuario) | 2026-11-06 |
| 26 | ¿Qué antigüedad debe tener un archivo huérfano para borrarlo? ¿Se programa el comando? | 24 horas; diario a las 03:00, con el cron en el despliegue (FASE 10) | Aprobada (usuario) | 2026-11-06 |
| 27 | ¿El listado de la auditoría trae los valores anteriores y nuevos? | No: solo el detalle, para que el listado sea liviano; las rutas internas (`photo_path`, `logo_path`) nunca salen | Aprobada (usuario) | 2026-11-06 |
| 28 | Una fecha con hora sin zona, ¿se acepta? | Sí, como hora de Asunción (es lo que pasa hoy con `deceased_at`: no cambia el contrato) | Aprobada (usuario) | 2026-11-06 |
| 29 | ¿Se amplía `veterinarians.license_number` a 60 para el sufijo de baja? | Sí, con una migración; se valida hasta 30 | Aprobada (usuario) | 2026-11-06 |
| 30 | ¿Se puede quitar el rol `veterinario` a un usuario con ficha de veterinario? | No: 409 `USER_IS_VETERINARIAN` hasta que se elimine la ficha (si está en uso, el usuario se inactiva) | Aprobada (usuario) | 2026-11-06 |
| 31 | Entregas: la 2.2 se divide en 2.2a y 2.2b, y las sucursales pasan a ser la 2.1 | Aprobar con el diseño | Aprobada (usuario) | 2026-11-06 |

## Criterio de cierre

Un administrador deja la clínica lista para operar sin tocar la base: sucursales y depósitos, usuarios con roles y sucursales,
veterinarios, servicios, datos y logo de la clínica, y consulta de la auditoría. Cada regla tiene su test con el rol de la
operación, y cada ruta nueva su acceso por rol y su aislamiento por clínica. Suite en verde en SQLite y en MySQL. Deudas 13 y 14
resueltas; la 2 revisada en los modelos de la fase.

## Prompt de implementación (paso 4)

Usar solo con el diseño aprobado, una entrega por vez.

```text
Implementa la entrega 2.X de la FASE 2 según docs/fases/fase-02-datos-maestros.md (diseño aprobado).

- Respeta las respuestas de R-029 y las de las preguntas del diseño.
- Reutiliza lo existente (CatalogLock, CatalogName, DeletedSuffix, DocumentSequenceService, ImageReencoder, ListFilters, SearchTerm).
- Sigue CLAUDE.md. Corre la suite completa en SQLite (y en MySQL si la entrega toca migraciones, búsquedas, concurrencia o autenticación) e infórmame. No hagas commit.
```

## Resultado

Lo completa el cierre de cada entrega ([`docs/prompts/7-cierre.md`](../prompts/7-cierre.md)): archivos, endpoints, tests y
aserciones, desvíos del diseño y por qué.

### Entrega 2.1

Implementación en `feature/fase-02-01-sucursales`, creada desde `docs/fase-02-diseno` (`405ea96`), revisada en una sesión de
Claude Code aparte de la implementación, corregida ([correcciones de la revisión](#correcciones-de-la-revisión-r-031)) y
validada por el usuario ([validación del usuario](#validación-del-usuario-r-031)). Implementación, correcciones y cierre van en
un solo commit. Decisiones en [R-030](../decisions/registro.md) y R-031.

**Archivos.**

| Tipo | Archivos |
|---|---|
| Nuevos | `BranchController`, `BranchWarehouseController` y `WarehouseController`; `IndexBranchRequest`, `StoreBranchRequest`, `UpdateBranchRequest`, `IndexWarehouseRequest`, `StoreWarehouseRequest`, `UpdateWarehouseRequest` y `BranchMessages`; `BranchResource`, `ShowBranchResource` y `WarehouseResource`; `BranchService` y `WarehouseService`; `BranchException`; `tests/Feature/Branches` (`BranchIndexTest`, `BranchStoreTest`, `BranchUpdateTest`, `WarehouseTest`, `BranchLockTest`) y `tests/Concerns/InteractsWithBranches`; `docs/testing/postman/syncvet-fase-02-sucursales.postman_collection.json` |
| Cambiados | `routes/api.php` (`apiResource` de sucursales sin `destroy`, depósitos anidados con `index` y `store`, y `warehouses` con `update`), `Branch` y `Warehouse` (deuda 2, abajo), `RolesAndPermissionsSeeder` (`branches.index` al recepcionista y al veterinario, pregunta 19), `lang/es/validation.php` (`establishment`, `is_default`, `warehouse_code`, `warehouse_name`, `branch_id`), `ClinicCreateCommand` (descripción "Alta de una clínica…", por el verbo `crear` de `VoseoTest`) |
| Tests cambiados | `RoleAccessTest` (parámetros `branch` y `warehouse`), `RouteBindingTest` (sucursal y depósito de otra clínica → 404 en cada ruta, sin cambios), `ValidationTranslationsTest` (pedidos inválidos a las rutas nuevas), `VoseoTest` (verbo `crear`), `tests/Fixtures/clinic-roles.json` |
| Documentación | `CLAUDE.md` §1, §3 (orden de bloqueo clínica → sucursal → depósito o usuario) y §6.4 nueva (las pendientes de diseño pasan a §6.5); la guía de Postman; este documento, el registro (R-030 y R-031), la deuda técnica (2, revisada en `Branch` y `Warehouse`), el handoff y los documentos de estado |

**Endpoints.** Las rutas pasan de 26 a 33; los permisos siguen en 123.

| Método | Ruta | Permiso | Respuesta | Errores |
|---|---|---|---|---|
| GET | `branches` | `branches.index` | 200, activas (la por defecto primero); `include_inactive=1`, todas | 422 |
| POST | `branches` | `branches.store` | 201, `ShowBranchResource` con su depósito por defecto | 422 |
| GET | `branches/{branch}` | `branches.show` | 200, con todos sus depósitos | 404 |
| PATCH/PUT | `branches/{branch}` | `branches.update` | 200 | 404; 409 `BRANCH_DEFAULT_NOT_DEACTIVATABLE`; 422 `BRANCH_ESTABLISHMENT_NOT_EDITABLE`; 422 |
| GET | `branches/{branch}/warehouses` | `warehouses.index` | 200, paginado; activos salvo `include_inactive=1` | 404; 422 |
| POST | `branches/{branch}/warehouses` | `warehouses.store` | 201 | 404; 422 (también en `branch`: sucursal inactiva) |
| PATCH/PUT | `warehouses/{warehouse}` | `warehouses.update` | 200 | 404; 409 `WAREHOUSE_DEFAULT_NOT_DEACTIVATABLE`; 422 |

**Tests.** Suite del 2026-11-07, después de las correcciones: 664 casos con los *data providers*; en SQLite, 7525 aserciones con 2
omitidos (solo de MySQL); contra MySQL 8.4.3 (`syncvet_test_mysql`), 7541 aserciones sin omitidos. Frente al cierre de la FASE 1
(616 casos; 6756 y 6772 aserciones): 48 casos y 769 aserciones más en las dos bases. La tabla cuenta casos (SQLite).

| Clase | Tests | Aserciones | Verifica |
|---|---|---|---|
| `BranchStoreTest` | 13 | 98 | Alta con su depósito por defecto (códigos en mayúsculas, "Principal" o el nombre enviado, sin asignar la sucursal a quien la crea); `is_default` mueve la por defecto; la primera de una clínica sin ninguna es la por defecto; código y nombre únicos (sin mayúsculas, acentos ni espacios repetidos); código inválido; establecimiento mal formado o repetido; teléfono; código del depósito obligatorio y único; los de otra clínica están libres; el recepcionista lista y no crea |
| `BranchUpdateTest` | 13 | 75 | Cambio de la por defecto; `is_default: false` → 422 en la por defecto y sin efecto en otra; inactivar la por defecto → 409; inactiva como por defecto → 422; inactivar y reactivar otra (sus depósitos no cambian); establecimiento con facturas → 422 y sin facturas → 200; únicos al editar; usuarios sin la sucursal actual inactivada, con su asignación; `PATCH` y `PUT` parciales; el detalle con todos los depósitos |
| `WarehouseTest` | 9 | 67 | Alta anidada (un `branch_id` del cuerpo se ignora); uno por defecto por sucursal; el por defecto no se inactiva ni se desmarca; inactivo como por defecto → 422; `branch_id` al editar → 422; código único en la clínica y nombre en la sucursal; listado con `include_inactive` y `search` por nombre y por código; sucursal inactiva → 422 en `branch` y, reactivada, 201; otra clínica → 404 |
| `BranchIndexTest` | 3 | 31 | Activas de la clínica con la por defecto primero; `include_inactive`; `search` literal en código y nombre; paginación y filtros mal formados |
| `BranchLockTest` | 4 | 33 | Orden de los bloqueos dentro de la transacción del Service: clínica antes de leer las sucursales en el alta; clínica → sucursal en la edición; clínica → sucursal → usuarios al inactivar; clínica → sucursal → depósito en el alta y la edición de un depósito (`for update` en MySQL) |

**Protecciones quitadas de a una.** En la implementación, las cinco del diseño, cada una con un test que falla: el chequeo de la
por defecto al inactivar, el paso de `is_default` a la anterior, el chequeo de facturas de `establishment`, el bloqueo de la
clínica en el alta (en SQLite y en MySQL) y el scope de la clínica en el *route binding* de la sucursal (la 2.1 no tiene un
`exists` de sucursal). En las correcciones, el chequeo de la sucursal inactiva y el voseo de "creá" (abajo).

**Deuda 2 (`Branch` y `Warehouse`).** Revisados: los casts (`is_default`, `is_active`) y las relaciones existentes (`warehouses`,
`users`, `branch`) coinciden con las claves foráneas. Suman valores por defecto (`is_default` falso, `is_active` verdadero), el
código en mayúsculas y el nombre limpio de espacios (`CatalogName::clean`) al guardar, y `Branch::invoices()`.

**Desvíos del diseño.**

| # | Desvío | Motivo | Quién |
|---|---|---|---|
| 1 | `is_default: false` responde 422 solo en la sucursal o el depósito que es el por defecto; en otro no cambia nada | El frontend manda el formulario completo | Implementación; aceptado por el usuario |
| 2 | Si la clínica no tiene sucursal por defecto, la que se crea pasa a serlo; igual el primer depósito de una sucursal sin depósito por defecto | Siempre hay una por defecto; solo pasa en una clínica sin sucursales, como las de `ClinicFactory` | Implementación; aceptado por el usuario |
| 3 | Código, nombre y establecimiento únicos los revisa el Service con la clínica bloqueada, no reglas `unique` del Form Request | Dos altas simultáneas no terminan en un 500 por el índice único; la respuesta es la misma, 422 `VALIDATION_ERROR` | Implementación; aceptado por el usuario |
| 4 | El mismo `establishment` con facturas responde 200 | No es un cambio | Implementación; aceptado por el usuario |
| 5 | El listado de depósitos está paginado, con `ListFilters` e `include_inactive` | Como los demás listados | Implementación; aceptado por el usuario |
| 6 | Los nombres usan `CatalogMessages::NAME_REGEX` (al menos una letra) | El diseño no fijaba el formato; es el de especies y razas | Implementación; aceptado por el usuario |
| 7 | Un depósito se podía crear en una sucursal inactiva | El diseño no lo mencionaba | Implementación; **rechazado** por el usuario: 422 en `branch` ("La sucursal está inactiva."), corregido en la revisión |
| 8 | Orden de bloqueo nuevo: clínica → sucursal → usuario | Inactivar una sucursal vacía `current_branch_id` usuario por usuario, para la auditoría | Implementación; aceptado por el usuario |

#### Correcciones de la revisión (R-031)

La revisión de la 2.1, en una sesión aparte de la implementación, encontró un hallazgo de gravedad media (el desvío 7, rechazado)
y cuatro de baja: tests que pasaban con la regla rota. El usuario pidió corregirlos. Decisiones en
[R-031](../decisions/registro.md).

| # | Gravedad | Hallazgo | Corrección | Test que falla sin la protección |
|---|---|---|---|---|
| 1 | Media | Se podía crear un depósito en una sucursal inactiva (desvío 7) | `WarehouseService::create()` revisa `is_active` sobre la sucursal ya bloqueada (ve una inactivación simultánea): 422 en `branch`, "La sucursal está inactiva.". La colección de Postman suma el pedido 24b | `WarehouseTest::test_a_warehouse_is_not_created_in_an_inactive_branch`: sin el chequeo, 201 en lugar de 422 |
| 2 | Baja | La búsqueda de depósitos por código no tenía test: sin `whereContains('code', …)` la suite pasaba | `?search=dep-08` encuentra el depósito por su código | `WarehouseTest::test_the_list_shows_the_active_warehouses_of_the_branch` |
| 3 | Baja | Nada comprobaba que al usuario que crea una sucursal no se le asigna | Aserción de `branch_user` vacío para la sucursal nueva | `BranchStoreTest::test_a_branch_is_created_with_its_default_warehouse` |
| 4 | Baja | El verbo `crear` ("creá uno nuevo") no estaba en `VoseoTest`: "crea uno nuevo" pasaba | `crear` en `VERBS`. La descripción de `clinic:create` ("Crea una clínica…", tercera persona) pasa a "Alta de una clínica…" para no dar una falsa alarma | `VoseoTest::test_messages_use_voseo` con "crea uno nuevo" |
| 5 | Baja | El orden clínica → sucursal → usuario (desvío 8) no tenía test | Test de orden al inactivar una sucursal que un usuario tiene como actual | `BranchLockTest::test_deactivating_a_branch_locks_clinic_branch_and_then_the_users` |

La revisión confirmó además: la lógica en los Services; los Controllers con `HasMiddleware` y permisos que existen en el seeder;
errores con `BranchException` o 422 de validación, sin 500; transacciones y bloqueos en el orden del diseño; sin migraciones;
contratos existentes sin cambios; el recepcionista y el veterinario listan sucursales y reciben 403 en las otras seis rutas
(`RoleAccessTest`); y `ValidationTranslationsTest`, frente a `origin/main`, solo suma los pedidos de las rutas nuevas (el
implementador lo había vaciado por error y lo restauró).

#### Validación del usuario (R-031)

El 2026-11-07 el usuario corrió en Postman (Collection Runner, una iteración) la colección `syncvet-fase-02-sucursales`, con el
pedido 24b de las correcciones: 107 de 107 pruebas en verde, sin errores (37 requests). Suite sobre el mismo código: la de las
correcciones (664 casos; 7525 aserciones en SQLite con 2 omitidos y 7541 en MySQL 8.4.3). Con este cierre el módulo de sucursales
y depósitos queda COMPLETADO; la FASE 2 sigue con la 2.2a (usuarios).

### Entrega 2.2a

Implementación en `feature/fase-02-02a-usuarios`, creada desde `feature/fase-02-01-sucursales`, revisada en una sesión de Claude
Code aparte de la implementación, corregida ([correcciones de la revisión](#correcciones-de-la-revisión-r-033)) y validada por el
usuario ([validación del usuario](#validación-del-usuario-r-033)). Implementación, correcciones y cierre van en un solo commit.
Decisiones en [R-032](../decisions/registro.md) y R-033.

**Archivos.**

| Tipo | Archivos |
|---|---|
| Nuevos | `UserController` y `RoleController`; `IndexUserRequest`, `StoreUserRequest`, `UpdateUserRequest` y `UserMessages`; `UserResource`, `ShowUserResource` y `RoleResource`; `UserService`; `UserException`; `App\Enums\UserRole`; `App\Support\PersonName` (R-033); `tests/Feature/Users` (`UserStoreTest`, `UserUpdateTest`, `UserIndexTest`, `UserLockTest`, `RolesIndexTest`) y `tests/Concerns/InteractsWithUsers`; `docs/testing/postman/syncvet-fase-02-usuarios.postman_collection.json` |
| Cambiados | `routes/api.php` (`apiResource` de usuarios sin `destroy` y `GET roles`), `lang/es/validation.php` (`roles`, `roles.*`, `role`, `branch_ids`, `branch_ids.*`), `StoreClientRequest`, `UpdateClientRequest` y `ClientMessages` (nombres de personas, R-033), `.gitignore` (`*.code-workspace`) |
| Tests cambiados | `RoleAccessTest` (parámetro `user`), `RouteBindingTest` (usuario de otra clínica → 404 en `GET`, `PATCH` y `PUT`, sin cambios), `ValidationTranslationsTest` (pedidos inválidos a las rutas nuevas; el nombre de un campo con comodín, `roles.*`, se busca como lo hace el validador), `ClientStoreTest` y `ClientUpdateTest` (nombres con apóstrofo y guion) |
| Documentación | `CLAUDE.md` §1, §3 (orden de bloqueo clínica → usuario), §6.1 (nombres de personas) y §6.5 nueva, usuarios (las pendientes de diseño pasan a §6.6); la guía de Postman; este documento, el registro (R-032 y R-033), la deuda técnica (2, revisada en `User`), el handoff y los documentos de estado |

**Endpoints.** Las rutas pasan de 33 a 38; los permisos siguen en 123.

| Método | Ruta | Permiso | Respuesta | Errores |
|---|---|---|---|---|
| GET | `users` | `users.index` | 200, paginado; `search`, `role` y `branch_id` | 422 |
| POST | `users` | `users.store` | 201, `ShowUserResource` | 422 |
| GET | `users/{user}` | `users.show` | 200, `ShowUserResource` | 404 (también de otra clínica) |
| PATCH/PUT | `users/{user}` | `users.update` | 200 | 404; 409 `USER_LAST_ADMIN`; 422 (también `password` sobre el propio usuario) |
| GET | `roles` | `roles.index` | 200, los tres roles con `name` y `label`, sin paginar | — |

**Tests.** Suite del 2026-11-07, después de las correcciones: 706 casos con los *data providers*; en SQLite, 8277 aserciones con 2
omitidos (solo de MySQL); contra MySQL (`syncvet_test_mysql`), 8293 aserciones sin omitidos. Frente al cierre de la 2.1 (664 casos;
7525 y 7541 aserciones): 42 casos y 752 aserciones más en las dos bases. La tabla cuenta casos (SQLite).

| Clase | Tests | Aserciones | Verifica |
|---|---|---|---|
| `UserStoreTest` | 13 | 131 | Alta con roles y sucursales (email en minúsculas, la por defecto como sucursal actual, sin tokens); la contraseña y su hash fuera de la auditoría y `ROLES_SYNCED`; la primera activa si la por defecto no está; email ASCII y de hasta 150; email único en toda la base, también de otra clínica o eliminado, sin intentar guardar; el email tomado entre el chequeo y el guardado → 422; contraseña débil o filtrada; sucursal de otra clínica, inactiva o inexistente; roles válidos y sin repetir; el mensaje de una lista vacía; nombres con apóstrofo y guion; `clinic_id` del cuerpo ignorado; el recepcionista y el veterinario → 403 en las 5 rutas |
| `UserUpdateTest` | 15 | 114 | `ROLES_SYNCED` (sin otro registro si no cambian); el último administrador → 409, con un eliminado y uno de otra clínica que no cuentan, sin cambios a medias; con otro administrador → 200; un administrador real que se quita su propio rol (solo, 409; con otro, 200); la sucursal actual que sale de la lista y la del usuario que no tiene una; la sucursal inactiva que ya tiene; el cambio de contraseña revoca los tokens del usuario y no los de otro; con tokens reales, el del administrador sigue y la contraseña no llega a la auditoría; la propia → 422 (también vacía o nula) y la sesión sigue; email único; `PATCH` y `PUT` parciales; listas vacías y nulos → 422; el detalle sin secretos |
| `UserIndexTest` | 5 | 42 | Solo los de la clínica y sin eliminados; `search` literal en nombre, apellido y email; `role` y `branch_id` (también una sucursal inactiva); filtros mal formados y sucursal de otra clínica → 422; paginación |
| `UserLockTest` | 2 | 11 | La edición bloquea la clínica y después el usuario antes de contar los administradores; el alta, la clínica antes de leer las sucursales |
| `RolesIndexTest` | 2 | 7 | Los tres roles con su etiqueta; el Enum coincide con los roles del seeder |

En `tests/Feature/Clients`, `ClientStoreTest` y `ClientUpdateTest` suman un test cada uno (nombres con apóstrofo y guion, R-033).

**Mutaciones.** Las cinco del diseño, en la implementación (R-032), cada una con un test que falla: quitar el chequeo del último
administrador, el bloqueo de la clínica al contar, `withTrashed()` del email único, el filtro de clínica del `exists` de las
sucursales y la revocación de tokens. En las correcciones, quitar el chequeo de la contraseña propia hace fallar
`test_the_administrator_does_not_change_the_own_password_here` (200 en lugar de 422).

**Deuda 2.** `User` revisado contra la tabla y las claves foráneas: casts (`password` → `hashed`) y relaciones (`clinic`,
`currentBranch`, `branches`, `veterinarian`) correctos, sin cambios.

**Desvíos del diseño.** Los nueve de la implementación, aceptados por el usuario (R-032): el bloqueo de la clínica en toda alta y
edición (1), la primera sucursal activa como actual (2), el email único en el Service (3), la revocación con cualquier `password`
(4), el cambio de sucursales en la auditoría (5), `roles` como `{name, label}` (6), "administrador activo" como no eliminado hasta
la 2.2b (7), `EMAIL_DNS_CHECK` en el email (8) y el orden del listado (9). **El 7 queda condicionado:** la 2.2b cuenta solo los
administradores con `is_active` y lo prueba con un test (un administrador inactivo no evita el 409). Las correcciones suman uno que
cambia una regla de la FASE 1: los nombres de personas (R-033).

#### Correcciones de la revisión (R-033)

La revisión no encontró errores en el código. Lo que el usuario decidió corregir:

| # | Gravedad | Hallazgo | Corrección | Test |
|---|---|---|---|---|
| 1 | Media | El administrador que cambiaba su propia contraseña por `users/{user}` revocaba también el token en uso (el pedido siguiente, 401), al revés que el `PATCH me/password` de la 2.2b | `password` sobre el propio usuario → 422, "Para cambiar tu contraseña usá tu perfil.", también vacía o nula | `UserUpdateTest::test_the_administrator_does_not_change_the_own_password_here` |
| 2 | Baja | Un usuario sin sucursal actual que recibía sucursales seguía sin una | La regla del alta también cuando no tiene sucursal actual | `UserUpdateTest::test_a_user_without_current_branch_gets_one_with_the_rule_of_the_store` |
| 3 | Baja | Sin test con un administrador real que se quita su propio rol | Test nuevo | `UserUpdateTest::test_a_real_administrator_removes_the_own_role` |
| 4 | Baja | La revocación se probaba sin tokens guardados para quien hace el cambio, y la auditoría de la contraseña solo en el alta | Test con tokens reales y la auditoría de la edición | `UserUpdateTest::test_a_password_change_keeps_the_token_of_the_administrator_and_stays_out_of_the_audit` |
| 5 | Baja | `UserIndexTest` fallaba a veces: el apellido al azar del usuario que hace el pedido podía ordenarse primero | `actAsUserManager` le fija nombre, apellido y email | `UserIndexTest` |
| 6 | — | Nombres de personas (pedido del usuario; cambia la FASE 1) | Apóstrofo y guion entre letras y la `ü` en clientes, con `App\Support\PersonName` | `ClientStoreTest`, `ClientUpdateTest` y `UserStoreTest::test_person_names_take_an_apostrophe_or_a_hyphen_between_letters` |

La revisión confirmó además: la lógica en los Services; los Controllers con `HasMiddleware` y permisos que existen en el seeder;
errores con `UserException` o 422 de validación, sin 500; el orden de bloqueo clínica → usuario; la contraseña con
`#[\SensitiveParameter]`, fuera de las respuestas y de la auditoría; login, `me` y logout sin cambios; un usuario de otra clínica no
aparece en el listado ni cuenta como administrador; el recepcionista y el veterinario reciben 403 en las 5 rutas.

#### Validación del usuario (R-033)

El 2026-11-07 el usuario corrió en Postman (Collection Runner, una iteración) la colección `syncvet-fase-02-usuarios`, con el pedido
16b de las correcciones (30 requests): sin errores. Número de pruebas: PENDIENTE DE VERIFICACIÓN (informado como "XX de XX"). Suite
sobre el mismo código: la del cierre (706 casos; 8277 aserciones en SQLite con 2 omitidos y 8293 en MySQL). La FASE 2 sigue con la
2.2b (usuarios inactivos, baja, sucursal actual y contraseña propia).
