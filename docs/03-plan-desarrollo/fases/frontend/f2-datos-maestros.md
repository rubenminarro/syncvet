# F2 — Datos maestros (frontend)

Etapa: Datos de la clínica. Estado: **APROBADO** por el usuario el 2026-11-16 (R-074), en `docs/front-f2-diseno` (desde `main` en `1daee8c`, el merge de
`chore/post-f1`), con las [preguntas del diseño](#preguntas-del-diseño) respondidas: todas las recomendaciones, también la entrega
front-02 del backend antes de la 2.3. Cada entrega empieza con la autorización del usuario. El estado vigente de cada fase está en [`docs/03-plan-desarrollo/README.md`](../../README.md).

## Objetivo

Las pantallas de administración de la clínica, sobre la API ya cerrada de la
[FASE 2 del backend](../backend/fase-02-datos-maestros.md): sucursales y depósitos, usuarios (con la inactivación, la baja y la
contraseña de otro usuario), veterinarios, servicios, la configuración de la clínica con el logo y la consulta de la auditoría. Se
usan tal cual las convenciones de la F0 y la F1 ([`frontend/CLAUDE.md`](../../../../frontend/CLAUDE.md) §5): listados con
`useSearchText` y la búsqueda en la URL, formularios con las reglas de la API y un solo envío, `ConfirmDialog`, la `key` en las
pantallas de detalle, las claves de `query-keys.ts`, el trato por `error_code`, las fechas en `America/Asuncion` y el voseo.

## Lo que da la API (resumen del análisis)

Las reglas completas están en [`backend/CLAUDE.md`](../../../../backend/CLAUDE.md) §6.4 a §6.9; acá, solo lo que decide la
interfaz. Permisos leídos de `tests/Fixtures/clinic-roles.json` (el administrador los tiene todos).

| Recurso | Endpoints | Permisos | Notas para el frontend |
|---|---|---|---|
| Sucursales | `GET branches` (`search` en código y nombre, `include_inactive`, paginado), `POST branches`, `GET/PATCH branches/{branch}` (sin baja) | `branches.index`: los tres roles. `.show`, `.store`, `.update`: solo el administrador | El detalle (`ShowBranchResource`) trae **todos** sus depósitos (también los inactivos), el por defecto primero; `created_at` y `updated_at`. El alta exige `warehouse_code` (y `warehouse_name`, "Principal" si no llega). Los códigos se pasan a mayúsculas en la API |
| Depósitos | `GET/POST branches/{branch}/warehouses` (paginado, `include_inactive`), `PATCH warehouses/{warehouse}` (sin detalle ni baja) | `warehouses.index`, `.store`, `.update`: solo el administrador | `branch_id` en la edición → 422 (`prohibited`). Una sucursal inactiva no suma depósitos: 422 en `branch` (una clave que no es un campo del formulario) |
| Usuarios | `GET users` (`search`, `role`, `branch_id`, `include_inactive`; por apellido), `POST users`, `GET/PATCH/DELETE users/{user}`; `GET roles` (sin paginar, `{name, label}`) | `users.*` y `roles.index`: solo el administrador | El listado (`UserResource`) trae `roles` (`{name, label}`), `branches` (`id`, `code`, `name`), `current_branch_id` e `is_active`; **no** trae la ficha de veterinario (ver [H1](#hallazgos-del-análisis)). El detalle suma `veterinarian` (`id`, `code`, o `null`), `created_at` y `updated_at` |
| Perfil | `PATCH me/password`, `PATCH me/current-branch` | Sin permiso | Ya están en la F0 (`/perfil` y el selector de sucursal) |
| Veterinarios | `GET veterinarians` (`search`, `status`, `include_inactive`, `branch_id`; por código), `POST veterinarians`, `GET/PATCH/DELETE veterinarians/{veterinarian}` | `veterinarians.index`: los tres. `.show`, `.store`, `.update`, `.destroy`: solo el administrador | El listado trae `code`, `user` (`id`, `name`, `email`, `is_active`), `license_number`, `specialty`, `status` (`{value, label}`) y `branches` (las del usuario); **sin** `commission_percentage` ni `notes`, que están solo en el detalle (`"12.50"`, texto) |
| Servicios | `GET services` (`search`, `category`, `status`, `include_inactive`; por nombre), `POST services`, `GET/PATCH/DELETE services/{service}` | `services.index` y `.show`: los tres. `.store`, `.update`, `.destroy`: solo el administrador | `standard_price` sale como texto con dos decimales (`"150000.00"`), `tax_rate` como número (0, 5 o 10), `category` y `status` como `{value, label}`. La lista de categorías **no** tiene endpoint (`ServiceCategory`) |
| Configuración | `GET system-settings`, `PATCH`/`PUT system-settings` (parcial) | `system-settings.show` y `.show-logo`: los tres. `.update`, `.upload-logo`, `.delete-logo`: solo el administrador | `clinic` (`name`, `legal_name`, `ruc`, `ruc_dv`), `settings` (`contact_email`, `contact_phone`, `website`, `document_footer`, cada una `null` sin valor) y `has_logo`. `ruc` y `ruc_dv` → 422 (`prohibited`); `settings.<clave>: null` borra la clave |
| Logo | `POST/GET/DELETE system-settings/logo` (campo `logo`, multipart) | Ver los tres; subir y borrar, el administrador | Como la foto del paciente: el `GET` devuelve el archivo (`private, no-store`). JPG, PNG o WEBP de hasta 2 MB por el contenido y 25 megapíxeles; más grande, 413 `PAYLOAD_TOO_LARGE` con `errors.logo`. Sin logo, 404 `CLINIC_LOGO_NOT_FOUND`. `me.clinic.has_logo` dice si hay |
| Auditoría | `GET audit-logs` (`from`, `to`, `user_id`, `event`, `auditable_type`, `auditable_id`, `search`; por fecha descendente), `GET audit-logs/{auditLog}` | `audit-logs.index` y `.show`: solo el administrador | El listado trae `event` (`{value, label}`), `auditable_type` (el tipo corto: `"patient"`), `auditable_id`, `user` (`id`, `name`, o `null`), `ip_address` y `created_at` (UTC, `Z`). El detalle suma `old_values`, `new_values` y `user_agent`. Las listas de eventos (`AuditEvent`) y de tipos (`AuditLog::AUDITABLE_TYPES`) **no** tienen endpoint |

Todos los listados aceptan `per_page` de 1 a 100 (10 por defecto) y `search` de hasta 100. Ninguna consulta de la fase depende de
la sucursal actual: las claves empiezan con su módulo, sin `branchKey`.

`error_code` de negocio de la fase (todos van por el trato `business` de la tabla de la F0, con lo que suma cada entrega):

| `error_code` | HTTP | Mensaje de la API | Trato propio en la F2 |
|---|---|---|---|
| `BRANCH_ESTABLISHMENT_NOT_EDITABLE` | 422 | "El código de establecimiento no se puede cambiar: la sucursal ya tiene facturas." | Al campo `establishment` (no a la alerta) |
| `BRANCH_DEFAULT_NOT_DEACTIVATABLE`, `WAREHOUSE_DEFAULT_NOT_DEACTIVATABLE` | 409 | "La sucursal (el depósito) por defecto no se puede inactivar: marcá otra (otro) como … por defecto primero." | En el diálogo, con "Cerrar"; el navegador ya oculta "Inactivar" en la por defecto |
| `USER_SELF_ACTION` | 409 | "No podés inactivarte ni darte de baja a vos mismo." | En el diálogo, con "Cerrar"; el navegador ya oculta los dos botones en el propio usuario |
| `USER_LAST_ADMIN` | 409 | "La clínica tiene que tener al menos un administrador activo." | En el formulario (alerta arriba) o en el diálogo de inactivar o de baja, con "Cerrar". El navegador no lo puede saber antes: lo dice la API |
| `USER_IN_USE` | 409 | "El usuario tiene registros a su nombre y no se puede eliminar: inactivalo." | En el diálogo de baja, el mensaje y "Inactivar" (si está activo) |
| `USER_IS_VETERINARIAN` | 409 | "El usuario tiene una ficha de veterinario: eliminala antes de quitarle el rol." | Alerta arriba del formulario, con el enlace a la ficha (`veterinarian.code` del detalle) |
| `VETERINARIAN_IN_USE` | 409 | "El veterinario tiene registros y no se puede eliminar: inactivalo." | En el diálogo de baja, el mensaje y "Inactivar" |
| `SERVICE_NAME_NOT_EDITABLE` | 422 | "El servicio ya se usó y no puede cambiar de nombre: creá otro e inactivá este." | Al campo `name` |
| `SERVICE_IN_USE` | 409 | "El servicio ya se usó y no se puede eliminar: inactivalo." | En el diálogo de baja, el mensaje y "Inactivar" |
| `CLINIC_LOGO_NOT_FOUND` | 404 | "La clínica no tiene logo." | Al ver: el lugar del logo vacío, sin error; al borrar: aviso y se vuelve a pedir la configuración |
| `PAYLOAD_TOO_LARGE` | 413 | (el logo) | Ya está en la tabla de la F0: `errors.logo` al campo; sin `errors`, aviso |

## Hallazgos del análisis

| # | Hallazgo | Propuesta |
|---|---|---|
| H1 | El alta de un veterinario elige un usuario activo con el rol `veterinario` y **sin ficha**, pero `GET users` no dice quién ya tiene ficha (`UserResource` no trae `veterinarian`; solo `ShowUserResource`). El selector ofrecería usuarios que la API rechaza con 422 "Ese usuario ya es veterinario." | [Pregunta 1](#preguntas-del-diseño): una entrega de soporte del backend chica (**front-02**) que pase `veterinarian` (`id`, `code`, o `null`) de `ShowUserResource` a `UserResource`, con la relación cargada en el listado (sin N+1). Sin ella, el selector ofrece todos y el 422 va al campo |
| H2 | Las categorías de servicio (`ServiceCategory`), los eventos de la auditoría (`AuditEvent`) y los tipos auditables (`AuditLog::AUDITABLE_TYPES`) no tienen endpoint: los filtros y los selectores necesitan la lista y su texto | Constantes en el `types.ts` de cada módulo, con el texto en castellano; un test de Vitest lee el archivo PHP del backend (como `src/test/roles.ts` lee `clinic-roles.json` y el test de voseo lee `VoseoTest`) y falla si la lista cambia. Sin endpoints nuevos |
| H3 | `PATCH users/{user}` con `password` (también `null` o vacía) sobre el propio usuario responde 422 "Para cambiar tu contraseña usá tu perfil."; sobre otro, `null` es "obligatoria" | La edición **no manda la clave `password`** salvo que se cambie (excepción a "los opcionales vacíos van en `null`"); con el propio usuario, el formulario no muestra la contraseña y enlaza a `/perfil` |
| H4 | `is_default: false` en la sucursal o el depósito por defecto → 422; "el frontend manda el formulario completo" (§6.4) | En la por defecto, "Por defecto" se muestra marcado y deshabilitado (se manda `true`); en las demás, marcarlo la pasa a ser la por defecto |
| H5 | Editar el propio usuario (roles, sucursales, nombre), inactivar una sucursal o cambiar el nombre de la clínica o su logo cambia `me` (permisos, `branches`, la sucursal actual, `clinic.has_logo`) | Esas mutaciones invalidan `['me']` (y, si cambió la sucursal actual, `invalidateBranchQueries`), así el menú, el selector de sucursal y el aviso "Elegí tu sucursal" se actualizan sin recargar |
| H6 | La auditoría filtra por `user_id` de un usuario **eliminado**, pero `GET users` no los lista | El selector del filtro usa `GET users?include_inactive=1`; en cada fila del listado, el usuario es un enlace "solo los de este usuario" que pone `?usuario=` (también de un eliminado, que el selector muestra como "Usuario n.º …") |
| H7 | En los filtros de la auditoría, `to` con hora es el instante exacto (`ApiDateTime::endOf`): "hasta las 18:00" deja afuera lo de las 18:00:30 | [Pregunta 10](#preguntas-del-diseño): "hasta" con hora se manda con los segundos en 59 (incluye todo ese minuto) |
| H8 | La foto del paciente (1.4) ya resuelve el control por el contenido, el tamaño, la vista previa y `getBlob`/`BlobImage`; el logo es lo mismo con 2 MB | En la 2.5, el control de `features/patients/photo.ts` pasa a `src/lib/files/image.ts` con el límite y los mensajes como parámetros, y la foto lo usa sin cambiar su comportamiento (sus tests no cambian) |
| H9 | La barra de menú es horizontal (`SessionLayout`): con seis módulos más, al administrador no le entra en una línea | [Pregunta 2](#preguntas-del-diseño): los módulos de administración (sucursales, usuarios, la clínica y la auditoría) bajo `/configuracion`, con una página de entrada; "Veterinarios" y "Servicios", con su ítem, porque los tres roles los listan |

## Decisiones comunes de la fase

* **URL en castellano** ([pregunta 2](#preguntas-del-diseño)):

  | URL | Entrega |
  |---|---|
  | `/configuracion` (la página de entrada, reemplaza a la provisoria de la 0.2b) | 2.1 |
  | `/configuracion/sucursales`, `/nueva`, `/$id`, `/$id/editar` | 2.1 |
  | `/configuracion/usuarios`, `/nuevo`, `/$id`, `/$id/editar` | 2.2a y 2.2b |
  | `/veterinarios`, `/nuevo`, `/$id`, `/$id/editar` | 2.3 |
  | `/servicios`, `/nuevo`, `/$id`, `/$id/editar` | 2.4 |
  | `/configuracion/clinica` | 2.5 |
  | `/configuracion/auditoria`, `/$id` | 2.6 |

  Las ediciones con `_` (`$id_.editar.tsx`), como en la F1. Cada detalle con su `loader` (el id, entero positivo, sin llamar a la
  API si no; el 404 a `notFound()`) y su página con `key={id}`.
* **Listados**: el modelo de `/clientes` (búsqueda local con `useSearchText`, filtros y página en la URL validados en
  `validateSearch` con todas sus claves, `placeholderData: keepPreviousData`, 20 por página fijo, "Esta página no existe." fuera de
  rango). "Incluir las inactivas" o "los inactivos", como en `/especies` (`?inactivas=1`, `?inactivos=1`). Los filtros de estado de
  veterinarios y servicios (`?estado=activos|inactivos|todos`) se traducen a `status` o `include_inactive=1` en `api.ts`.
* **Formularios**: React Hook Form con Zod, las reglas de forma de cada Form Request con sus mensajes (función pura con tests en el
  borde de cada límite), el largo en caracteres sin `maxLength` nativo donde la API admite `\p{L}`, `applyValidationErrors` para el
  422, Guardar bloqueado con `isSubmitting`, la mutación pendiente y la `ref` de un solo `mutate`, los opcionales vacíos en `null`
  (salvo `password`, H3) y guardar y "Cancelar" con `replace`. Los códigos (`code` de sucursal y depósito) y la matrícula se pasan a
  mayúsculas y sin espacios de los extremos antes de validar y mandar, como la API; el email, a minúsculas.
* **Activar, inactivar y eliminar** ([pregunta 13](#preguntas-del-diseño)): botones en el detalle, con `ConfirmDialog` (`tone`
  `primary` para activar o inactivar, `danger` para eliminar), y no un campo del formulario. El error de negocio va dentro del
  diálogo; con `*_IN_USE`, solo el mensaje de la API y "Inactivar" (sin repetir la pregunta "¿Querés eliminar…?"), como la 1.5. Las
  altas de veterinarios y servicios no muestran el estado (va `active`, el valor por defecto de la API).
* **Claves de las consultas** (`api.ts` de cada módulo):

  | Clave | Qué |
  |---|---|
  | `['branches', 'list', { search, include_inactive, page }]` | `GET branches` |
  | `['branches', 'detail', id]` | `GET branches/{id}` (con sus depósitos) |
  | `['branches', 'options']` | `GET branches?include_inactive=1&per_page=100`, para los selectores y los filtros de usuarios y veterinarios |
  | `['users', 'list', { search, role, branch_id, include_inactive, page }]` | `GET users` |
  | `['users', 'detail', id]` | `GET users/{id}` |
  | `['users', 'options', { role, include_inactive }]` | `GET users?…&per_page=100`: el usuario del veterinario (2.3) y el filtro de la auditoría (2.6) |
  | `['roles']` | `GET roles` (`staleTime: Infinity`: es una lista fija) |
  | `['veterinarians', 'list', { search, status, include_inactive, branch_id, page }]` | `GET veterinarians` |
  | `['veterinarians', 'detail', id]` | `GET veterinarians/{id}` |
  | `['services', 'list', { search, category, status, include_inactive, page }]` | `GET services` |
  | `['services', 'detail', id]` | `GET services/{id}` |
  | `['settings']` | `GET system-settings` |
  | `['settings', 'logo']` | El logo como `Blob` (`staleTime: Infinity`, `gcTime` de 5 minutos, como la foto) |
  | `['audit-logs', 'list', { from, to, user_id, event, auditable_type, auditable_id, search, page }]` | `GET audit-logs` |
  | `['audit-logs', 'detail', id]` | `GET audit-logs/{id}` |

  Invalidaciones (nunca toda la caché): sucursales y depósitos → `['branches']`, `['users']` y `['veterinarians']` (muestran las
  sucursales) y `['me']` (H5); usuarios → `['users']`, `['veterinarians']` (el nombre y `user.is_active`) y, si es el propio,
  `['me']`; veterinarios → `['veterinarians']`, `['users']` (la ficha en el detalle y, con front-02, en el listado) y `['me']` si la
  ficha es del usuario de la sesión (`me.veterinarian`); servicios → `['services']`; configuración → `['settings']` y `['me']`;
  logo → `['settings']`, `['me']` y la caché del archivo (`resetQueries` al reemplazarlo, `Query.reset()` al borrarlo, como la
  foto). La auditoría no se invalida: es de solo lectura (`staleTime` de 0: al volver a entrar, se vuelve a pedir). Los detalles
  borrados que no están a la vista se vacían con `resetQueries`, como en la F1.
* **Permisos.** Cada ruta con `requirePermission`; los botones con `useCan()`. `PERMISSIONS` suma los de la fase con los nombres de
  la API. Los tests, con `meFor(rol)` de los tres roles reales. Las pantallas de `/configuracion/…` son del administrador: aunque el
  recepcionista y el veterinario listan sucursales, no tienen pantalla de sucursales ([pregunta 4](#preguntas-del-diseño)).
* **Menú** ([pregunta 2](#preguntas-del-diseño)): "Servicios" (`services.index`) y "Veterinarios" (`veterinarians.index`), para los
  tres roles, después de "Especies"; "Configuración" (`system-settings.update`, el administrador) al final, como hoy. El ítem queda
  activo en todas las páginas de `/configuracion/…`.
* **Sin paquetes nuevos.** La F2 no instala nada.
* **Código común nuevo** (cada uno en la entrega que lo usa primero): `useSingleAction` en `src/lib/hooks` (2.1, tarea 3c);
  `CheckboxGroupField` en `src/components/ui` (roles y sucursales, 2.2a); `src/lib/files/image.ts` (2.5, H8); la normalización del
  teléfono de clientes, si está en `features/clients`, pasa a `src/lib` en la 2.1 (sucursales) y la usan también la configuración.

## Entregas

| Entrega | Contenido | Necesita el backend | Rama | Estado |
|---|---|---|---|---|
| 2.1 | Sucursales y depósitos, la página de entrada de `/configuracion` y lo que queda de la tarea 3c (`useSingleAction` a `src/lib/hooks` y el diálogo de baja del paciente con `PATIENT_IN_USE`) | No | `feature/front-2-1-sucursales`, desde `docs/front-f2-diseno` | COMPLETADO (R-075, [resultado](#entrega-21)) |
| 2.2a | Usuarios: listado, detalle, alta y edición (roles, sucursales, la contraseña de otro usuario, el propio usuario) | No | `feature/front-2-2a-usuarios`, desde `feature/front-2-1-sucursales` | APROBADA (R-074); sigue |
| 2.2b | Usuarios: inactivar y reactivar, baja, el último administrador y "Incluir los inactivos" | No | `feature/front-2-2b-usuarios` | APROBADA (R-074) |
| front-02 | Soporte del backend: la ficha de veterinario en `GET users` (aprobada con la [pregunta 1](#preguntas-del-diseño)) | — | `feature/front-02-ficha-en-usuarios` | APROBADA (R-074) |
| 2.3 | Veterinarios: listado (los tres roles), detalle, alta desde el usuario, edición, inactivar y baja | front-02 | `feature/front-2-3-veterinarios` | APROBADA (R-074) |
| 2.4 | Servicios: listado y detalle (los tres roles), alta, edición, inactivar y baja; el precio en guaraníes | No | `feature/front-2-4-servicios` | APROBADA (R-074) |
| 2.5 | Configuración de la clínica: datos, claves y logo (y el logo en la barra) | No | `feature/front-2-5-configuracion` | APROBADA (R-074) |
| 2.6 | Auditoría: listado con filtros (fechas con y sin hora en Asunción) y detalle de los cambios | No | `feature/front-2-6-auditoria` | APROBADA (R-074) |

Cada rama sale de la anterior, como en la F1, y todo se integra a `main` al cerrar la F2. Los usuarios van en dos entregas
([pregunta 3](#preguntas-del-diseño)), como en el backend: juntos serían el doble que las demás. front-02 va antes de la 2.3, la
única que la usa. Cada entrega recorre los pasos 4 a 7 de [`prompts/README.md`](../../prompts/README.md).

## Diseño por entrega

### 2.1 Sucursales y depósitos

**Antes, la tarea 3c** (dos commits propios al empezar la entrega):

* `useSingleAction` (hoy en `features/patients/use-patient-action.ts` y repetido en `features/species/CatalogDialogs.tsx`, desvío
  6 de la 1.5) pasa a `src/lib/hooks/use-single-action.ts`, con sus tests; pacientes y especies la importan de ahí, sin cambiar su
  comportamiento. El movimiento del archivo, en un commit sin cambios de contenido.
* El diálogo de baja del paciente (1.3b, `PatientActions.tsx`) con `PATIENT_IN_USE` deja de decir "¿Querés eliminar a …? No se puede
  deshacer.": muestra solo el mensaje de la API, con "Registrar el fallecimiento" (si no está fallecido) y "Cerrar", como la 1.5
  (desvío 12 de la 1.5). Test del texto con y sin el error.

**Pantallas.**

| URL | Permiso | Contenido |
|---|---|---|
| `/configuracion` | `system-settings.update` | La página de entrada: una tarjeta por sección (Sucursales en la 2.1; Usuarios, la Clínica y la Auditoría, cada una en su entrega), solo las que el usuario puede ver |
| `/configuracion/sucursales?buscar=&inactivas=&pagina=` | `branches.show` | Búsqueda ("Buscá por código o nombre"), "Incluir las inactivas"; tabla: código, nombre (enlace), establecimiento, teléfono, "Por defecto" e "Inactiva". "Nueva sucursal" (`branches.store`) |
| `/configuracion/sucursales/nueva` | `branches.store` | El formulario de la sucursal y su depósito por defecto: código y nombre del depósito ("Principal" de ayuda; vacío va `null` y la API pone "Principal") |
| `/configuracion/sucursales/$id` | `branches.show` | Datos, alta y última modificación (`formatDateTime`) y la tarjeta "Depósitos" (del detalle: todos, el por defecto primero, con "Por defecto" e "Inactivo"). Acciones de la sucursal: Editar, "Marcar como por defecto" (si está activa y no lo es) e Inactivar o Activar. De cada depósito: Editar, "Marcar como por defecto" e Inactivar o Activar; "Nuevo depósito" (oculto en una sucursal inactiva) |
| `/configuracion/sucursales/$id/editar` | `branches.update` | El mismo formulario, sin los campos del depósito |

**Formulario.** Código, nombre, establecimiento (tres dígitos), dirección, teléfono y "Sucursal por defecto" (H4: en la por
defecto, marcado y deshabilitado). Los depósitos, en un diálogo (`ConfirmDialog` con `size="md"` y campos, como la 1.3b): código,
nombre y "Depósito por defecto".

**Validación.**

| Campo | Zod (navegador) | Solo la API |
|---|---|---|
| `code`, `warehouse_code` | Obligatorio, hasta 20, `^[A-Z0-9-]+$` después de pasar a mayúsculas y recortar (con los mensajes de `BranchMessages`) | Código en uso en la clínica (también el de un depósito) |
| `name`, `warehouse_name` | 2 a 100, al menos una letra (`CatalogMessages::NAME_REGEX`, como en especies) | Nombre repetido sin mayúsculas, acentos ni espacios (la sucursal en la clínica; el depósito en su sucursal) |
| `establishment` | Obligatorio, `^(?!000)\d{3}$` | En uso; `BRANCH_ESTABLISHMENT_NOT_EDITABLE` (al campo) |
| `address` | Hasta 255, `BranchMessages::ADDRESS_REGEX` | — |
| `phone` | `^\+?[1-9]\d{7,14}$`, sin los espacios y guiones escritos (como clientes) | — |

**Mutaciones.** `POST branches`, `PATCH branches/{id}` (el formulario, `{ is_default: true }` o `{ is_active }`),
`POST branches/{id}/warehouses`, `PATCH warehouses/{id}`; invalidan lo de [Decisiones comunes](#decisiones-comunes-de-la-fase). Al
inactivar la sucursal actual del usuario de la sesión, `me` vuelve sin sucursal actual y el layout pide elegir otra (H5); el diálogo
lo avisa: "Los usuarios que la tienen como sucursal actual van a tener que elegir otra.".

**Errores.** 422 a sus campos (el de `branch`, sucursal inactiva, a la alerta del diálogo); `BRANCH_ESTABLISHMENT_NOT_EDITABLE` al
campo; `*_DEFAULT_NOT_DEACTIVATABLE` en el diálogo con "Cerrar"; 404 en una acción, aviso y vuelta al listado.

**Tests (Vitest).** `validateSearch`; las reglas de Zod en su borde (`.each`); el código en minúsculas mandado en mayúsculas; la
por defecto con el control deshabilitado y `is_default: true` en el cuerpo; el alta con el depósito (`warehouse_name` vacío en
`null`); "Nuevo depósito" oculto en una inactiva; inactivar la por defecto oculto y, con el 409, el mensaje en el diálogo;
`BRANCH_ESTABLISHMENT_NOT_EDITABLE` en su campo; inactivar la sucursal actual invalida `['me']`; el recepcionista y el veterinario
van a `/sin-permiso` y no ven "Configuración"; la tarea 3c (los tests de `useSingleAction` en su lugar nuevo y el diálogo del
paciente).

**Playwright.** Alta de una sucursal con su depósito; marcarla por defecto y ver que la anterior dejó de serlo; volver a la
original; un depósito nuevo, marcado por defecto, y el intento de inactivar la sucursal por defecto (el botón no está) y el depósito
anterior (sí se inactiva).

**Mutaciones a probar.** `is_default: false` mandado en la por defecto; sin invalidar `['me']` al inactivar; "Nuevo depósito" en
una inactiva; el código sin pasar a mayúsculas.

**En el navegador.** El diálogo de depósito con el teclado; inactivar la sucursal actual y ver el aviso "Elegí tu sucursal" sin
recargar; la página de entrada de `/configuracion`; el ancho de tablet.

#### Pendiente para el cierre (2.1)

Vacío: la entrega se cerró (R-075) y todo lo que había acá pasó a su [resultado](#entrega-21).

### 2.2a Usuarios: listado, detalle, alta y edición

**Pantallas.**

| URL | Permiso | Contenido |
|---|---|---|
| `/configuracion/usuarios?buscar=&rol=&sucursal=&inactivos=&pagina=` | `users.index` | Búsqueda ("Buscá por nombre, apellido o email"), rol (de `GET roles`), sucursal (`['branches', 'options']`, las inactivas con "(inactiva)") e "Incluir los inactivos"; tabla: nombre (enlace), email, roles (`label`), sucursales (códigos) e "Inactivo". "Nuevo usuario" (`users.store`) |
| `/configuracion/usuarios/$id` | `users.show` | Datos, roles, sucursales (con la actual marcada), la ficha de veterinario (`veterinarian.code`, enlace a `/veterinarios/$id` desde la 2.3; con el rol y sin ficha, "Crear la ficha de veterinario", también desde la 2.3), alta y última modificación. "(vos)" junto al nombre del usuario de la sesión |
| `/configuracion/usuarios/nuevo` | `users.store` | El formulario |
| `/configuracion/usuarios/$id/editar` | `users.update` | El mismo formulario |

**Formulario.** Nombre, apellido, email, roles (`CheckboxGroupField` con `GET roles`), sucursales (`CheckboxGroupField` con las
activas y, al editar, también las inactivas que el usuario ya tiene, con "(inactiva)") y la contraseña:

* **Alta**: "Contraseña" y "Repetí la contraseña", obligatorias.
* **Edición de otro usuario** ([pregunta 6](#preguntas-del-diseño)): "Cambiar la contraseña" (`CheckboxField`) muestra las dos;
  sin marcar, la clave `password` no se manda (H3). Con el aviso "Se van a cerrar todas sus sesiones." (la API revoca sus tokens).
* **Edición del propio usuario**: sin contraseña, con el texto "Tu contraseña se cambia en tu perfil." y el enlace a `/perfil`.

Las mutaciones que llevan una contraseña, con `gcTime: 0` y `reset()` en `onSettled` (convención de la 0.2b).

**Validación.**

| Campo | Zod (navegador) | Solo la API |
|---|---|---|
| `first_name`, `last_name` | Obligatorios, 2 a 100, `PersonName` (la regla de clientes) | — |
| `email` | Obligatorio, hasta 150, solo ASCII, la forma mínima; a minúsculas y recortado antes de mandar | Email en uso en **toda** la base, también de otra clínica o de un eliminado ("Ya hay un usuario con ese email."); el DNS con `email_dns` |
| `roles` | Al menos uno | `USER_LAST_ADMIN`, `USER_IS_VETERINARIAN` |
| `branch_ids` | Al menos una | Una sucursal inactivada mientras tanto (422, `branch_ids.0` va al campo) |
| `password` | 12 o más caracteres, mayúsculas y minúsculas, un número y un símbolo (`Password::defaults()`, con los mensajes de `UserPassword`); la repetición igual | Filtrada (Have I Been Pwned) |

**Mutaciones.** `POST users` (a su detalle), `PATCH users/{id}` (el formulario completo, sin `password` si no cambia).

**El propio usuario** (H5, [pregunta 7](#preguntas-del-diseño)): guardar invalida `['me']`. Si el administrador se quita el rol
`administrador` (con otro administrador activo, si no la API responde `USER_LAST_ADMIN`), primero un `ConfirmDialog`: "Vas a dejar
de ser administrador y no vas a poder volver a esta pantalla."; con el 200, `me` se vuelve a pedir y se va a `/`. Si cambian sus
sucursales y la actual sale, `invalidateBranchQueries`.

**Errores.** 422 a sus campos (`password` de un propio usuario no puede llegar: el campo no está); `USER_LAST_ADMIN`, alerta
arriba; `USER_IS_VETERINARIAN`, alerta con el enlace a la ficha; 404, aviso y vuelta al listado.

**Tests (Vitest).** `validateSearch` (rol desconocido, sucursal que no es un entero); la contraseña en el borde de cada regla
(11 y 12 caracteres, sin símbolo, sin mayúscula); la edición sin "Cambiar la contraseña" no manda `password` (ni `null`); el propio
usuario sin el campo y con el enlace a `/perfil`; la sucursal inactiva que ya tenía en las opciones; el email en minúsculas en el
cuerpo; 422 del email y de `branch_ids.0` a sus campos; `USER_LAST_ADMIN` y `USER_IS_VETERINARIAN`; quitarse el rol de administrador
(el diálogo, `['me']` y la vuelta a `/`); la contraseña fuera de la caché de mutaciones; el recepcionista sin la pantalla.

**Playwright.** Alta de un recepcionista con una sucursal; entrar con él en otro contexto y ver su menú; el administrador le cambia
la contraseña y la sesión del otro contexto termina con "Tu sesión terminó. Ingresá de nuevo."; el email repetido (422 en su campo).

**Mutaciones a probar.** `password: null` en la edición sin cambiarla; la contraseña visible en el propio usuario; sin invalidar
`['me']` al editarse a sí mismo.

**En el navegador.** El formulario con muchas sucursales; el diálogo de quitarse el rol; la pestaña Red: el cuerpo de la edición sin
`password`.

#### Pendiente para el cierre (2.2a)

Vacío.

### 2.2b Usuarios: inactivar, reactivar y baja

**Acciones en el detalle** (`users.update` y `users.destroy`), ocultas en el propio usuario:

* **Inactivar** (`ConfirmDialog`, `tone="primary"`): "Se van a cerrar todas sus sesiones y no va a poder ingresar hasta que lo
  reactives."; `PATCH users/{id}` con `{ is_active: false }`. Errores en el diálogo con "Cerrar": `USER_LAST_ADMIN` (el último
  administrador activo) y `USER_SELF_ACTION` (si llegara, por un detalle viejo de otra pestaña).
* **Reactivar**: `{ is_active: true }`, con el aviso "Va a tener que ingresar de nuevo." (reactivar no devuelve los tokens).
* **Eliminar** (`tone="danger"`): `DELETE users/{id}`; con el 200, al listado (el detalle se vacía con `resetQueries`). Con
  `USER_IN_USE`, el mensaje de la API y "Inactivar" (si está activo), que cierra este diálogo y abre el de inactivar; con
  `USER_LAST_ADMIN` o `USER_SELF_ACTION`, el mensaje y "Cerrar".

El listado ya tiene "Incluir los inactivos" desde la 2.2a; acá se prueba el ida y vuelta con estas acciones.

**Tests (Vitest).** Los botones ocultos en el propio usuario; inactivar y reactivar (el cuerpo parcial, el aviso, la invalidación de
`['users']` y `['veterinarians']`); la baja con el 200 (a la lista), con `USER_IN_USE` (el botón "Inactivar" y el otro diálogo) y con
`USER_LAST_ADMIN`; el inactivo con su marca en el listado y en el detalle.

**Playwright.** Inactivar al usuario creado en el caso de la 2.2a con su sesión abierta en otro contexto: la sesión termina y el
login responde el mensaje de `USER_INACTIVE`; reactivarlo y entrar; eliminarlo; eliminar al usuario de `vet-a` del `DemoSeeder` (tiene
ficha): `USER_IN_USE` y "Inactivar" en el diálogo (sin confirmarlo).

**Mutaciones a probar.** Los botones visibles en el propio usuario; `USER_IN_USE` sin el botón "Inactivar"; la baja sin vaciar el
detalle.

**En el navegador.** El paso del diálogo de baja al de inactivar con el teclado (el foco); el inactivo en el listado con y sin el
filtro.

#### Pendiente para el cierre (2.2b)

Vacío.

### front-02 Soporte del backend: la ficha de veterinario en el listado de usuarios

**Aprobada con la [pregunta 1](#preguntas-del-diseño) (R-074).** `veterinarian` (`id`, `code`, o `null` sin ficha o con la ficha
eliminada) pasa de `ShowUserResource` a `UserResource`, y `UserController::RELATIONS` suma `veterinarian`: el listado, el alta, el
detalle y la edición lo traen. Sin rutas, permisos, migraciones ni `error_code` nuevos; aditivo. Tests: el listado con y sin ficha y
con la ficha eliminada (`null`), sin N+1 (cantidad de consultas fija con 1 y con 5 usuarios). Postman: una aserción en la colección
de usuarios. `backend/CLAUDE.md` §6.5 y la [guía de Postman](../../../05-pruebas/guia-postman.md). Al cerrar: `composer test` y Newman
de la colección de usuarios.

#### Pendiente para el cierre (front-02)

Vacío.

### 2.3 Veterinarios

**Pantallas.**

| URL | Permiso | Contenido |
|---|---|---|
| `/veterinarios?buscar=&estado=&sucursal=&pagina=` | `veterinarians.index` (los tres) | Búsqueda ("Buscá por código, nombre o matrícula"), estado (Activos, Inactivos, Todos) y sucursal; tabla: código, nombre (enlace al detalle solo con `veterinarians.show`), matrícula, especialidad, sucursales, estado y "Usuario inactivo" (`user.is_active`). "Nuevo veterinario" (`veterinarians.store`) |
| `/veterinarios/$id` | `veterinarians.show` (el administrador) | Código, el usuario (enlace a `/configuracion/usuarios/$id`; "Usuario inactivo"), matrícula, especialidad, comisión ("12,5 %"), sucursales, estado, notas, alta y última modificación |
| `/veterinarios/nuevo?usuario=` | `veterinarians.store` | El formulario con el usuario; `?usuario=` lo deja elegido (el botón "Crear la ficha de veterinario" del detalle del usuario) |
| `/veterinarios/$id/editar` | `veterinarians.update` | El mismo formulario, con el usuario y el código solo para leer |

**El código `VET-` y el usuario.** El código no se escribe ni se manda nunca (`code` → 422): se muestra al volver del 201. El
usuario se elige en el alta de `['users', 'options', { role: 'veterinario' }]` (activos, no eliminados) **sin ficha** (con front-02,
`veterinarian === null`); sin ninguno, "No hay usuarios con el rol veterinario sin ficha." con el enlace a "Nuevo usuario". Si el
usuario tenía una ficha eliminada, la API la restaura con el mismo código (201): el detalle muestra el código de siempre. El
`user_id` no se manda al editar. En el detalle del usuario (2.2a) queda el enlace a la ficha o el botón para crearla.

**Validación.**

| Campo | Zod (navegador) | Solo la API |
|---|---|---|
| `user_id` | Obligatorio | Inactivo, sin el rol o ya veterinario (422 al campo; mensajes de `VeterinarianMessages`) |
| `license_number` | Hasta 30; a mayúsculas y recortada antes de mandar (vacía, `null`) | Repetida en la clínica ("Ya hay un veterinario con esa matrícula.") |
| `specialty` | Hasta 100 | — |
| `commission_percentage` | De 0 a 100 con hasta 2 decimales; acepta coma o punto y se manda como texto con punto (como el peso del paciente); vacío, `0` | — |
| `notes` | Hasta 1000 | — |

**Acciones en el detalle.** Inactivar o Activar (`PATCH` con `{ status }`) y Eliminar (`VETERINARIAN_IN_USE`: el mensaje y
"Inactivar"). Con el 200 de la baja, al listado.

**Tests (Vitest).** `validateSearch` y el estado traducido (`status=inactive`, `include_inactive=1`); el enlace del listado solo
para el administrador; el recepcionista en `/veterinarios/$id` va a `/sin-permiso`; el selector sin los usuarios con ficha y con
`?usuario=`; la matrícula en mayúsculas; la comisión con coma; `code` y `user_id` nunca en el cuerpo de la edición; 422 de la
matrícula; la baja con `VETERINARIAN_IN_USE`.

**Playwright.** Desde el detalle de un usuario nuevo con el rol veterinario, "Crear la ficha de veterinario" y ver el código
`VET-`; la matrícula de `vet-a` en minúsculas (422); inactivar y ver la ficha en "Inactivos"; eliminarla y volver a crearla (el
mismo código); el recepcionista ve el listado sin enlaces ni "Nuevo veterinario".

**Mutaciones a probar.** El selector con los usuarios con ficha; `user_id` mandado al editar; el enlace al detalle para el
recepcionista.

**En el navegador.** El paso del usuario a su ficha y de vuelta; el listado con el estado y la sucursal; el ancho de tablet.

#### Pendiente para el cierre (2.3)

Vacío.

### 2.4 Servicios

**Pantallas.**

| URL | Permiso | Contenido |
|---|---|---|
| `/servicios?buscar=&categoria=&estado=&pagina=` | `services.index` (los tres) | Búsqueda ("Buscá por código o nombre"), categoría y estado; tabla: código, nombre (enlace), categoría, duración ("30 min"), precio (`formatGuaranies`), IVA ("10 %") y estado. "Nuevo servicio" (`services.store`) |
| `/servicios/$id` | `services.show` (los tres) | Los mismos datos, la descripción, alta y última modificación; las acciones, solo para el administrador |
| `/servicios/nuevo`, `/servicios/$id/editar` | `services.store`, `services.update` | El formulario |

**El precio en guaraníes** ([pregunta 8](#preguntas-del-diseño)). Un campo de texto (`inputMode="numeric"`), no `type="number"`:
acepta dígitos y los puntos de miles ("150.000"), que se sacan antes de validar; se manda **como texto de solo dígitos**
(`"150000"`), nunca como número de coma flotante. Rechaza en el navegador, con el mensaje de `WholeAmount` ("El precio debe ser un
número entero, sin decimales, de 0 a 999.999.999.999."), la coma, los decimales, el signo y más de 12 dígitos. Al editar, el
`"150000.00"` de la API se muestra "150.000" (los `.00` se quitan como texto, sin pasar por un número; si viniera con decimales
distintos de cero, se muestra tal cual y la API decide).

**Formulario.** Nombre, categoría (`SelectField` con las constantes de H2), duración (de 5 a 720, de a 5; 30 por defecto), precio,
IVA (`RadioGroup`: 0, 5 o 10 %; 10 por defecto) y descripción.

**Validación.**

| Campo | Zod (navegador) | Solo la API |
|---|---|---|
| `name` | 2 a 120, `ServiceMessages::NAME_REGEX` (en JavaScript con `u` y `s`: al menos una letra, sin `//`, letras, números, espacios y `. , - ' ( ) / + %`) | Repetido sin mayúsculas ni acentos ("Ya hay un servicio con ese nombre."); `SERVICE_NAME_NOT_EDITABLE` (al campo) |
| `category` | Una de la lista | — |
| `duration_minutes` | Entero de 5 a 720, múltiplo de 5 | — |
| `standard_price` | Obligatorio; lo de arriba | — |
| `tax_rate` | 0, 5 o 10 (número en el cuerpo) | — |
| `description` | Hasta 1000 | — |

**Acciones en el detalle.** Inactivar o Activar (`{ status }`) y Eliminar (`SERVICE_IN_USE`: el mensaje y "Inactivar").

**Tests (Vitest).** El precio: "150.000" y "150000" mandan `"150000"`; "150000,50", "150.000,00", "-1", "1e5" y 13 dígitos rechazados
sin pedido; "999.999.999.999" pasa; `"150000.00"` mostrado "150.000" en el formulario y "Gs. 150.000" en la tabla; las reglas del
nombre en su borde ("Vacuna 5+1" y "Castración macho/hembra" pasan, "a//b" no); la duración (4, 5, 722, 720); `SERVICE_NAME_NOT_EDITABLE`
en `name`; la baja con `SERVICE_IN_USE`; el recepcionista ve el detalle sin acciones; la lista de categorías igual a la del backend
(H2).

**Playwright.** Alta de un servicio con el precio "150.000" y verlo "Gs. 150.000" después de recargar; el precio con coma rechazado
sin pedido; cambiar el nombre de un servicio usado por los turnos del `DemoSeeder`, si lo hay (`SERVICE_NAME_NOT_EDITABLE`), o la
baja de uno recién creado.

**Mutaciones a probar.** El precio mandado como número (`Number(...)`); los puntos de miles sin sacar; `tax_rate` como texto.

**En el navegador.** El teclado numérico en el ancho de un celular; el precio largo en la tabla; la pestaña Red con el cuerpo del
alta.

#### Pendiente para el cierre (2.4)

Vacío.

### 2.5 Configuración de la clínica y logo

**Pantalla.** `/configuracion/clinica` (`system-settings.update`), con su tarjeta en `/configuracion`:

* **Datos**: nombre (2 a 100) y razón social (2 a 150); el RUC (`ruc`-`ruc_dv`) solo para leer, con "El RUC no se cambia desde
  acá.". Nunca se mandan `ruc` ni `ruc_dv`.
* **Contacto y documentos**: correo de contacto (hasta 150, a minúsculas, la forma mínima del email), teléfono (la regla de
  clientes), sitio web (hasta 150; empieza con `https://` y `new URL()` lo acepta; el mensaje de la API) y pie de recetas y
  comprobantes (hasta 500). Vacío → `null` (la API borra la clave).
* `PATCH system-settings` con el formulario completo (`name`, `legal_name` y `settings` con las cuatro claves). Con el 200, aviso y
  la invalidación de `['settings']` y `['me']`.

**Logo** (H8). La tarjeta del logo, como la foto de la 1.4: `BlobImage` con `api.getBlob('system-settings/logo', { expectedType: 'image/' })`
si `has_logo`; `FileField` con vista previa y "Subir el logo"; "Quitar el logo" con `ConfirmDialog`. El navegador controla como la
API: 2 MB justos pasan (`2 * 1024 * 1024`, `MAX_KILOBYTES = 2048`) y el tipo por el contenido, con los mensajes de
`UploadClinicLogoRequest`. 413 con `errors.logo` al campo. `CLINIC_LOGO_NOT_FOUND` al ver: el lugar vacío; al borrar: aviso y se
vuelve a pedir la configuración. La mutación con `gcTime: 0` y `reset()`.

**El logo en la barra** ([pregunta 9](#preguntas-del-diseño)). `Brand` muestra el logo (alto de 32 px, `alt` con el nombre de la
clínica) si `me.clinic.has_logo`, para los tres roles (`system-settings.show-logo`), con la misma clave `['settings', 'logo']`: subir
o quitar el logo lo cambia en la barra sin recargar. Sin logo o con un error, la marca de siempre (nunca una imagen rota). Otra
pestaña ve el logo nuevo al recargar (la caché del archivo es de cada pestaña).

**Tests (Vitest).** `image.ts` con el límite como parámetro (la foto sigue con 5 MB: sus tests no cambian); el logo de 2 MB justos
y de un byte más; el tipo por el contenido; el formulario: `ruc` fuera del cuerpo, las claves vacías en `null`, el email en
minúsculas, el sitio con `http://` rechazado; el 422 de `settings.website` a su campo; `Brand` con y sin logo y con el 404; la caché
del logo vaciada al reemplazarlo y al borrarlo.

**Playwright.** Cambiar el teléfono de contacto y verlo después de recargar (y dejarlo como estaba); subir un logo (`logo-clinica.png` de
`docs/05-pruebas/postman/archivos`, como la foto de la 1.4), verlo en la barra después de recargar, reemplazarlo
(`logo-clinica.webp`) y quitarlo.

**Mutaciones a probar.** `ruc` mandado; el límite de 5 MB en el logo; la barra sin invalidar al quitar el logo.

**En el navegador.** El logo en la barra con un logo ancho y uno alto (sin deformarse); la consola sin errores; el ancho de tablet.

#### Pendiente para el cierre (2.5)

Vacío.

### 2.6 Auditoría

**Pantallas.**

| URL | Permiso | Contenido |
|---|---|---|
| `/configuracion/auditoria?desde=&hasta=&usuario=&evento=&tipo=&registro=&buscar=&pagina=` | `audit-logs.index` | Filtros y tabla: fecha y hora (`formatDateTime`), usuario (enlace "solo los de este usuario", H6; "—" sin usuario), evento (`label`), registro (el tipo en castellano y "n.º 12", enlace que filtra por ese registro) e IP |
| `/configuracion/auditoria/$id` | `audit-logs.show` | Fecha y hora con segundos, usuario, evento, registro, IP, navegador (`user_agent`) y los cambios |

**Filtros de fecha** (en Asunción, nunca con la zona de la PC). "Desde" y "Hasta", cada una con `DateField` y una hora opcional
(`TimeField`):

* **Fecha sola**: se manda `2026-11-09` tal cual; la API la toma como el día entero de Asunción (de 00:00 a 23:59:59).
* **Con hora**: se manda con `toApiDateTime` (`2026-11-09T13:00:00.000-03:00`); "hasta" con los segundos en 59 (H7, pregunta 10).
* En la URL, la hora de pared sin zona (`?desde=2026-11-09T13:00`); `desde` y `hasta` se suman a `TEXT_SEARCH_PARAMS` y
  `validateSearch` descarta lo que no tenga esa forma.
* Zod repite las reglas de la API con sus mensajes: la forma ("Ingresá una fecha (2026-11-09) o una fecha con hora
  (2026-11-09T13:00:00Z).") y "hasta" no anterior a "desde" ("La fecha hasta no puede ser anterior a la fecha desde."), comparando
  las horas de pared como texto (una fecha sola, como 00:00 en "desde" y 23:59:59 en "hasta"), sin pasar por `Date`.

**Los demás filtros.** Usuario (`['users', 'options', { include_inactive: true }]`), evento y tipo (las constantes de H2, con el
texto en castellano), registro (n.º; exige el tipo, con el mensaje de la API "Para filtrar por el id del registro, indicá también su
tipo.") y la búsqueda ("Buscá por el nombre o el email del usuario").

**Los cambios y lo que nunca se muestra.** Una tabla campo, antes y después (`created`: solo después; `deleted`: solo antes); el
campo con el nombre de la API ([pregunta 11](#preguntas-del-diseño)), y cada valor como texto: `null` es "(vacío)", una lista o un
objeto, su JSON con sangría en un `<pre>` (nunca como HTML). La API ya cambia las contraseñas y los tokens por `********` y las rutas
de archivos por `"(archivo)"` (§6.9); el frontend las muestra tal cual y, además ([pregunta 12](#preguntas-del-diseño)), una función
pura `maskAuditValues` cambia por `********` el valor de una clave con `password` o `token` y por `"(archivo)"` el de una clave que
termina en `_path` o un texto con la forma `clinics/{id}/…`, también anidados: si la API un día dejara pasar una, la pantalla no la
muestra.

**Tests (Vitest).** `validateSearch` (fechas mal formadas, `registro` sin `tipo`, un evento o un tipo desconocido); el pedido con la
fecha sola, con hora (`-03:00`) y "hasta" con `:59`, con la PC en UTC; "hasta" antes de "desde" sin pedido; la fila con el usuario
`null`; el filtro por un usuario eliminado ("Usuario n.º …"); el detalle de `created`, `updated` y `deleted`; `maskAuditValues` con
`password`, `remember_token`, `photo_path`, `logo_path` y una ruta anidada; ningún `********` ni ruta reemplazados dos veces; las
listas de eventos y tipos iguales a las del backend (H2); el recepcionista sin la pantalla.

**Playwright.** Editar las notas de un cliente, buscar en la auditoría por hoy, el tipo cliente y su n.º, y ver en el detalle las
notas antes y después; el mismo filtro con una hora "desde" posterior al cambio (la lista vacía); el alta de un usuario en la
auditoría sin su contraseña.

**Mutaciones a probar.** La fecha mandada con `toISOString()` de un `Date` local; "hasta" sin `:59`; `maskAuditValues` sin recorrer
los valores anidados.

**En el navegador.** Los filtros de fecha y hora en el selector nativo; un detalle con `branch_ids` (una lista) y uno largo; la URL
con filtros recargada.

#### Pendiente para el cierre (2.6)

Vacío.

## Documentación por entrega

| Entrega | Actualizar |
|---|---|
| front-02 | `backend/CLAUDE.md` §6.5, la [guía de Postman](../../../05-pruebas/guia-postman.md) y su colección, este documento, el roadmap y el handoff |
| 2.1 a 2.6 | `frontend/CLAUDE.md` (las convenciones nuevas: `useSingleAction` común, `CheckboxGroupField`, `image.ts`, el precio en guaraníes, los filtros de fecha; la tabla de errores si cambia), este documento (Resultado), el roadmap, `modulos.md` y el handoff |

## Preguntas del diseño

| # | Pregunta | Opciones | Recomendación | Respuesta |
|---|---|---|---|---|
| 1 | El listado de usuarios no dice quién ya tiene ficha de veterinario (H1). ¿Se suma en el backend? | (a) Entrega de soporte front-02, chica y aditiva; (b) el selector ofrece a todos y el 422 va al campo | **(a)**: el alta ofrece solo a quien se puede elegir y el listado de usuarios puede mostrar el código `VET-` | Aprobada (usuario, 2026-11-16) |
| 2 | ¿Dónde van las pantallas de administración? (H9) | (a) Bajo `/configuracion/…` con una página de entrada, y "Veterinarios" y "Servicios" con su ítem; (b) un ítem del menú por módulo; (c) un menú desplegable "Administración" | **(a)**: la barra horizontal no admite diez ítems, el ítem "Configuración" ya es del administrador, y veterinarios y servicios los listan los tres roles. (c) necesita un desplegable propio (sin el JS de Tabler) | Aprobada (usuario, 2026-11-16) |
| 3 | ¿Usuarios en dos entregas (2.2a y 2.2b)? | Dos; una | **Dos**, como en el backend: el formulario con las contraseñas y el propio usuario por un lado, la inactivación y la baja con sus 409 por el otro | Aprobada (usuario, 2026-11-16) |
| 4 | El recepcionista y el veterinario pueden listar las sucursales (`branches.index`). ¿Tienen pantalla? | Solo el administrador; los tres, solo la lista | **Solo el administrador**: los otros ven sus sucursales en el selector de la barra; el listado de la API queda para los selectores de la F3 | Aprobada (usuario, 2026-11-16) |
| 5 | ¿Los depósitos tienen pantalla propia? | Dentro del detalle de la sucursal, con diálogos; `/configuracion/sucursales/$id/depositos/…` | **Dentro del detalle**: son pocos campos, no hay `GET` de un depósito y el detalle de la sucursal ya los trae todos | Aprobada (usuario, 2026-11-16) |
| 6 | La contraseña de otro usuario al editarlo (H3) | (a) "Cambiar la contraseña" que muestra los campos, con la repetición, el aviso de que se cierran sus sesiones y las reglas de forma en Zod; (b) los campos siempre visibles y opcionales; (c) una acción aparte en el detalle | **(a)**: no se manda nada por error y el aviso aparece justo cuando hace falta. El control de filtraciones, solo la API | Aprobada (usuario, 2026-11-16) |
| 7 | El administrador se quita su propio rol de administrador (con otro activo) | Con confirmación y vuelta a `/`; sin confirmación | **Con confirmación**: pierde el acceso a la pantalla en la que está | Aprobada (usuario, 2026-11-16) |
| 8 | El precio de los servicios | (a) Texto que acepta los puntos de miles ("150.000") y se manda como texto de solo dígitos; (b) solo dígitos, sin puntos | **(a)**: en Paraguay el precio se escribe con puntos; la coma y los decimales se rechazan con el mensaje de la API | Aprobada (usuario, 2026-11-16) |
| 9 | ¿El logo se muestra en la barra? | Sí, para los tres roles; solo en `/configuracion/clinica` | **Sí**: los tres pueden verlo y es la forma de saber en qué clínica se está | Aprobada (usuario, 2026-11-16) |
| 10 | "Hasta" con hora en la auditoría (H7) | Con los segundos en 59 (todo ese minuto); el instante exacto | **Con 59**: "hasta las 18:00" incluye lo de las 18:00 | Aprobada (usuario, 2026-11-16) |
| 11 | Los campos en el detalle de la auditoría | El nombre de la API (`first_name`); un texto en castellano por campo | **El nombre de la API** en la F2: es una pantalla del administrador y una tabla de textos por modelo es mucho para lo que da; se puede sumar después | Aprobada (usuario, 2026-11-16) |
| 12 | ¿El frontend oculta también las contraseñas y las rutas de la auditoría (`maskAuditValues`)? | Sí, además de la API; solo la API | **Sí**: es una función pura chica con sus tests y protege si la API un día deja pasar una | Aprobada (usuario, 2026-11-16) |
| 13 | ¿Activar, inactivar y eliminar como botones del detalle (también el estado de veterinarios y servicios)? | Botones con diálogo; el estado como campo del formulario | **Botones**, como especies: un cambio de estado tiene sus avisos y sus 409, y el formulario queda para los datos | Aprobada (usuario, 2026-11-16) |

## Criterio de cierre

* Las entregas aprobadas cerradas, con Vitest y Playwright en verde, `typecheck`, `lint` y `build` sin errores ni avisos.
* Por front-02: `composer test` y la suite del backend completa en MySQL y en serie, al cerrar la fase.
* Newman de **todas** las colecciones de `docs/05-pruebas/postman` desde la raíz (R-057), sin fallas.

## Resultado

### Entrega 2.1

Estado: **COMPLETADO** el 2026-11-16 (R-075). Sucursales y depósitos, la página de entrada de `/configuracion` y lo que quedaba de
la tarea 3c, en `feature/front-2-1-sucursales` (desde `docs/front-f2-diseno` en `16aec1d`), sin integrar a `main` (se integra al
cerrar la F2). Commits: `7de78f3` y `7e27822` (la tarea 3c), `626759b` (WIP de la implementación), `b30db49` (WIP de la
revisión), `a31b6ef` (WIP del depósito inactivo) y el de este cierre (R-075: la documentación).

**Implementación.**

* **Tarea 3c, resuelta.** `useSingleAction` sale de `features/patients/use-patient-action.ts` a `src/lib/hooks/use-single-action.ts`
  (`7de78f3`), sin cambiar su cuerpo y con dos tests propios; pacientes y especies la importan de ahí, y se borra la copia de
  `features/species/CatalogDialogs.tsx` (desvío 6 de la 1.5). El diálogo de baja del paciente con `PATIENT_IN_USE` muestra solo el
  mensaje de la API, sin "¿Querés eliminar…? No se puede deshacer." (`7e27822`; desvío 12 de la 1.5), con su test con y sin el
  error.
* **Pantallas** (todas del administrador): `/configuracion`, la página de entrada (`frontend/src/app/ConfigurationPage.tsx`), con una tarjeta por
  sección que el usuario puede ver (Sucursales, por ahora); `/configuracion/sucursales` (`branches.show`), con la búsqueda por
  código o nombre, "Incluir las inactivas" y la página en la URL, como `/clientes`; `/nueva` (`branches.store`), con el código y
  el nombre del depósito por defecto (vacío, `null`: la API pone "Principal"); `/$id`, con los datos, el alta y la última
  modificación y la tarjeta "Depósitos"; `/$id/editar` (`branches.update`). En `features/branches`. `PERMISSIONS` suma
  `branchesShow`, `branchesStore`, `branchesUpdate`, `warehousesStore` y `warehousesUpdate`.
* **Reglas.** Las de forma de `StoreBranchRequest`, `UpdateBranchRequest`, los de depósitos y `BranchMessages`, con sus mensajes
  (`features/branches/form.ts`): los códigos sin los espacios de los extremos y en mayúsculas solo ASCII, como `strtoupper`;
  el establecimiento de tres dígitos, de `001` a `999`; el teléfono con la regla de clientes, común en `src/lib/phone.ts`. La
  sucursal y el depósito por defecto: la casilla marcada y deshabilitada, `is_default: true` en el cuerpo, sin "Inactivar"; los
  409 `*_DEFAULT_NOT_DEACTIVATABLE` y los 422 en el diálogo, con solo "Cerrar". Una sucursal o un depósito inactivo: la casilla
  sin marcar y deshabilitada, con "Activá la sucursal para marcarla como la por defecto." o "Activá el depósito para marcarlo
  como el por defecto."; la sucursal inactiva, sin "Nuevo depósito". `BRANCH_ESTABLISHMENT_NOT_EDITABLE`, en su campo; un 404 en
  una acción de la sucursal, aviso y de vuelta al listado; el de un depósito, aviso y la sucursal pedida de nuevo.
* **Caché.** Cada cambio (`afterBranchChanged`) invalida `['branches']`, `['users']`, `['veterinarians']` y `['me']` (el selector
  de la barra usa `me.branches`) y, si cambió la sucursal actual, las consultas de la sucursal: al inactivar la actual, el layout
  pide elegir otra sin recargar.
* **Pruebas.** Vitest: `form.test.ts`, `search.test.ts`, `BranchPages.test.tsx` (con los 6 tests de la revisión y el del depósito
  inactivo), `use-single-action.test.tsx` y el diálogo del paciente. Playwright: `e2e/sucursales.spec.ts` (nuevo: alta con su
  depósito, edición, un depósito nuevo marcado por defecto, el anterior inactivado y la sucursal inactivada) y el caso "el
  administrador entra a /configuracion" de `permisos.spec.ts`, 2 de 2. Mutaciones de la implementación: 4 de 4 detectadas
  (`is_default: false` en la por defecto, sin invalidar `['me']`, "Nuevo depósito" en una inactiva y el código sin pasar a
  mayúsculas).

| # | Desvío | Motivo |
|---|---|---|
| 1 | `useSingleAction` no fue un movimiento de archivo: se sacó de `use-patient-action.ts` (donde sigue `usePatientGone`) en un commit propio, con dos tests nuevos | El hook no tenía archivo propio |
| 2 | `/configuracion` pasó a `frontend/src/routes/_sesion/configuracion/index.tsx`, con el id de la ruta cambiado en el mismo commit | El movimiento solo no compilaba |
| 3 | "Inactivada" e "Inactivado" en lugar de "Inactiva" e "Inactivo" | El test de voseo toma "Inactiva" por el imperativo de "inactivar" (como en especies, 1.5) |
| 4 | El listado junta "Por defecto" e "Inactivada" en una columna "Estado" | Dos columnas de marcas casi vacías |
| 5 | Playwright siguió el caso del pedido de la entrega, no el del diseño (marcar la sucursal por defecto y volver a la original); se tocó también el caso de `/configuracion` de `permisos.spec.ts` | Marcar la por defecto cambia la de toda la base de prueba; lo cubre Vitest |
| 6 | Navegador: 5 controles, no los cuatro temas del diseño; "inactivar la sucursal actual" quedó en Vitest y Playwright | No se inactiva nada en la demo |
| 7 | El diálogo de depósito no se manda con Enter | Dos campos y el botón en el pie, como el del fallecimiento (1.3b) |
| 8 | Los diálogos de estado con un 422 o un error de negocio muestran solo el mensaje y "Cerrar"; el 404 de un depósito, aviso y la sucursal de nuevo | Como la 1.5; el depósito no tiene detalle |
| 9 | `afterBranchChanged` invalida `['users']` y `['veterinarians']`, que todavía no existen | Llegan en la 2.2a y la 2.3 |
| 10 | Cambiado en la revisión: en la edición de una sucursal inactiva, "Sucursal por defecto" queda sin marcar y deshabilitada, con su explicación (antes, habilitada, con el 422 de la API en el campo); la misma regla para el depósito inactivo | Decisión del usuario |

**Revisión (2026-11-16).** Sobre `7de78f3`, `7e27822` y `626759b`, con el presupuesto por entrega: sin hallazgos que cambien el
comportamiento (el movimiento de `useSingleAction`, el diálogo del paciente, la por defecto, el alta con su depósito, la paridad con
la API, la invalidación de `me`, los permisos según `clinic-roles.json` y los diálogos). Hallazgos menores, corregidos en `b30db49`:
el desvío 10 y seis tests que faltaban en `BranchPages.test.tsx` (el doble clic en la confirmación de la sucursal, el doble clic en
"Crear el depósito", el 404 en una acción de la sucursal, el 404 de un depósito, el teclado en el diálogo de depósito y la edición
de una sucursal inactiva). Mutaciones: antes de esos tests, las tres sobrevivían; después, sin la guarda de un solo pedido en
`StatusDialog` y sin la vuelta al listado de `useBranchGone`, detectadas. **Sin la guarda de `WarehouseDialog`, sigue sin
detectarse**: es una mutación equivalente en jsdom, porque el clic llama a `setErrors`, React dibuja el botón en espera en el mismo
evento y `Button` ignora el segundo clic; la `ref` queda como segunda defensa. Después, en `a31b6ef`, la misma regla para el
depósito inactivo, con su test (decisión del usuario).

**Validación.** El usuario dio la entrega por validada con la revisión y sus correcciones, los desvíos 1 a 9 aceptados y el 10
cambiado (R-075). Evidencia: Claude Code, 5 de 5 en Edge sin ventana contra `syncvet.test` (la demo quedó sin cambios); Playwright,
`e2e/sucursales.spec.ts` y el caso de `/configuracion` de `permisos.spec.ts`, 2 de 2; desde `frontend/`, `typecheck`, `lint` y
`build` sin errores ni avisos. La última corrida completa de `npm test` fue sobre `b30db49`; el test del depósito inactivo
(`a31b6ef`) se verificó con `vitest related` (393 tests en 19 archivos, en verde) y ESLint de lo tocado. La sucursal `E2E-…` del caso
de Playwright quedó inactivada en `syncvet_e2e`, que se vacía en cada corrida.

Corrida de `npm test` del frontend (2026-11-16): 909 tests en 46 archivos, en verde.
