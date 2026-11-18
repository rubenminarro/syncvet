# F2 — Datos maestros (frontend): resultados

El resultado de cada entrega, el cierre de la fase y lo que vino después. El diseño y las decisiones, en [`f2-datos-maestros.md`](f2-datos-maestros.md).

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

### Entrega 2.2a

Estado: **COMPLETADO** el 2026-11-16 (R-076). Usuarios: listado, detalle, alta y edición, en `feature/front-2-2a-usuarios` (desde
`feature/front-2-1-sucursales`, en el cierre de la 2.1, `0f7b80b`), sin integrar a `main` (se integra al cerrar la F2). Commits:
`fff201c` (WIP de la implementación), `e8ecdeb` (WIP de la revisión) y el de este cierre (R-076: la documentación).

**Implementación.**

* **Pantallas** (todas del administrador): la tarjeta "Usuarios" en `/configuracion`; `/configuracion/usuarios` (`users.index`),
  con la búsqueda por nombre, apellido o email, el rol (de `GET roles`), la sucursal (`['branches', 'options']`, las inactivas con
  "(inactivada)"), "Incluir los inactivos" y la página en la URL, como `/clientes`; `/$id` (`users.show`), con los datos, los
  roles, las sucursales con la actual marcada, el código de la ficha de veterinario (sin enlace hasta la 2.3) y "(vos)" en el
  usuario de la sesión; `/nuevo` (`users.store`) y `/$id/editar` (`users.update`). En `features/users`. `PERMISSIONS` suma
  `usersIndex`, `usersShow`, `usersStore`, `usersUpdate` y `rolesIndex`.
* **Código común nuevo.** `CheckboxGroupField` en `src/components/ui` (controlado, con `Controller`), para los roles y las
  sucursales; `branchOptionsQueryOptions` (`['branches', 'options']`, `GET branches?include_inactive=1&per_page=100`) en
  `features/branches/api.ts`; la regla de nombres de personas en `src/lib/person-name.ts` (`PERSON_NAME_PATTERN`), que clientes
  importa de ahí sin cambiar su comportamiento.
* **Reglas.** Las de forma de `StoreUserRequest`, `UpdateUserRequest`, `UserMessages` y `UserPassword`, con sus mensajes
  (`features/users/form.ts`): los nombres con `PersonName`, de 2 a 100 caracteres; el email solo ASCII, hasta 150, sin los
  espacios de los extremos y en minúsculas; al menos un rol y una sucursal (las activas y, al editar, también las inactivas que el
  usuario ya tiene); la contraseña con `Password::defaults()` (12 o más caracteres, mayúsculas y minúsculas, un número y un
  símbolo) y su repetición. La contraseña: obligatoria en el alta; al editar a otro usuario, detrás de "Cambiar la contraseña", con
  el aviso "Se van a cerrar todas sus sesiones.", y sin la clave `password` si no se cambia (ni `null`, H3); al editarse a sí
  mismo, sin los campos, con el enlace a `/perfil`.
* **Errores.** 422 a sus campos (`branch_ids.0` a `branch_ids`; al editarse a sí mismo, el de `password` a la alerta, porque el
  campo no está); `USER_LAST_ADMIN`, alerta arriba; `USER_IS_VETERINARIAN`, alerta con el código de la ficha; 404 al guardar,
  aviso y vuelta al listado.
* **El propio usuario** (H5). Guardar invalida `['me']` y, si la sucursal actual cambió, las consultas de la sucursal
  (`afterUserChanged`). Quitarse el rol de administrador pide antes la confirmación ("Vas a dejar de ser administrador y no vas a
  poder volver a esta pantalla."); con el 200, `me` de nuevo y la vuelta a `/`.
* **Contraseña fuera de la caché.** El alta y la edición con `gcTime: 0` y `reset()` en `onSettled`; los errores se tratan en las
  opciones de `useMutation` (desvío 4).
* **Pruebas.** Vitest: `form.test.ts`, `search.test.ts` y `UserPages.test.tsx` (con los 3 tests de la revisión). Playwright:
  `e2e/usuarios.spec.ts` (nuevo: alta con rol y sucursal, edición de sus roles, cambio de su contraseña con el ingreso con la nueva
  en otro contexto, donde la anterior ya no sirve, y `USER_LAST_ADMIN` al quitarse el rol el único administrador), 1 de 1. Mutaciones de la implementación: 4 de 4
  detectadas (`password` mandado sin "Cambiar la contraseña", la contraseña visible en el propio usuario, sin invalidar `['me']` al
  editarse a sí mismo y sin la confirmación de quitarse el rol de administrador).

| # | Desvío | Motivo |
|---|---|---|
| 1 | Playwright siguió el caso del pedido de la entrega (alta con rol y sucursal, edición de sus roles, cambio de su contraseña con el ingreso con la nueva en otro contexto, y `USER_LAST_ADMIN` al quitarse el rol el único administrador), no el del diseño (el menú del usuario nuevo, la sesión del otro contexto cerrada y el email repetido) | Pedido de la entrega; el 422 del email y el de `branch_ids.0` están en Vitest |
| 2 | "Inactivado" (la marca del usuario) y "(inactivada)" (la sucursal en el filtro y en el formulario), no "Inactivo" ni "(inactiva)" | El test de voseo toma "Inactiva" por el imperativo (desvío 3 de la 2.1) |
| 3 | `USER_IS_VETERINARIAN`: la alerta muestra el código de la ficha como texto ("Ficha de veterinario: VET-…."), sin enlace; el detalle, el código sin enlace y sin "Crear la ficha de veterinario" | `/veterinarios/$id` llega en la 2.3 |
| 4 | Los errores del alta y la edición se tratan en las opciones de `useMutation`, no en las de `mutate()`, y las variables llevan el formulario (`{ values, form }`) | `reset()` en `onSettled` (la contraseña fuera de la caché, `gcTime: 0`) descarta los callbacks de `mutate()`: el error no llegaba al formulario |
| 5 | La regla de nombres de personas pasó a `src/lib/person-name.ts` (`PERSON_NAME_PATTERN`), y clientes la importa de ahí sin cambiar su comportamiento | Es la misma regla (`PersonName`) en clientes y usuarios |
| 6 | Los `loader` del alta y la edición traen también los roles y las sucursales: un 403 o un 5xx de esos pedidos va a la página de error de la ruta | El formulario no se dibuja sin las casillas |
| 7 | La columna "Nombre" del listado muestra "Apellido, Nombre" | El listado de la API ordena por apellido |
| 8 | "Incluir los inactivos" ya está en el listado de la 2.2a (el pedido nombraba solo el rol y la sucursal) | Está en la URL de la 2.2a del diseño |
| 9 | `e2e/support/session.ts`: `User` pasa a ser `{ email, name }` (antes, solo los tres del `DemoSeeder`) | Para entrar con el usuario creado en el caso |
| 10 | Edge: el usuario temporal se creó con un email `@mailinator.com` y se borró por la API (`DELETE users/61`, 200; queda eliminado con el sufijo de baja, como toda baja). Dos corridas del script fallaron antes sin cambiar la demo: con `@example.com` (422: la demo tiene `EMAIL_DNS_CHECK=true` y ese dominio no tiene MX) y con el id de `admin-a` supuesto (el de los tests) | La demo valida el DNS del email; `admin-a`, `recepcion-a` y `vet-a` solo se leyeron |
| 11 | Después de la corrida de Playwright, `e2e/support/session.ts` cambió solo en el tipo de `User` (desvío 9); no se repitió | Sin cambios de comportamiento |

**Revisión (2026-11-16).** Sobre `fff201c`, contra el cierre de la 2.1 (`0f7b80b`), con el presupuesto por entrega: sin hallazgos
que cambien el comportamiento (la contraseña nunca mandada sin "Cambiar la contraseña" ni como `null` y fuera de la caché, el
propio usuario sin los campos y con el enlace a `/perfil`, `USER_LAST_ADMIN` y `USER_IS_VETERINARIAN`, la confirmación al quitarse
el rol, la invalidación de `['me']` por el id del usuario de la sesión, la paridad con `StoreUserRequest`, `UpdateUserRequest`,
`UserMessages` y `Password::defaults()`, `PERSON_NAME_PATTERN` igual carácter por carácter a la expresión que tenía clientes y los
permisos). Corregido en `e8ecdeb` (menor): un 422 de `password` al editarse a sí mismo ("Para cambiar tu contraseña usá tu
perfil."; no debería llegar, porque no se manda) caía en un campo que no se dibuja; ahora va a la alerta. Tres tests nuevos y una
comprobación en uno existente: el 422 del alta con la contraseña fuera de la caché de mutaciones (el desvío 4), el 422 de la
contraseña filtrada al cambiar la de otro usuario (en su campo y fuera de la caché), el 422 de `password` del propio usuario (en
la alerta) y editarse sacando la sucursal actual (`['me']` de nuevo e `invalidateBranchQueries`, que no tenía test). Mutaciones: 3
de 3 detectadas; dos de ellas, solo por los tests nuevos: sin `reset()` en la edición (con el 200, la página se desmonta y
`gcTime: 0` borra la mutación igual, así que el test de éxito no la detectaba) y sin `invalidateBranchQueries` al cambiar la
sucursal actual. La tercera, las sucursales inactivas que el usuario ya tiene fuera de las opciones, la detectaba un test que ya
estaba.

**Validación.** El usuario aceptó los 11 desvíos y dio la entrega por validada al pedir su cierre (R-076). Evidencia: Claude Code,
5 de 5 en Edge sin ventana contra `syncvet.test` (el usuario temporal, borrado por la API; desvío 10), con capturas fuera del
repositorio, en la PC de la validación (`%TEMP%\claude\…\scratchpad\capturas-2-2a`: `01-configuracion.png`, `02-listado.png`,
`03-detalle-vet.png`, `04-alta.png`, `05-edicion-cambiar-contrasena.png`, `06-quitarse-el-rol.png` y
`07-edicion-propia-768.png`); Playwright, `e2e/usuarios.spec.ts`, 1 de 1 (en la implementación; la revisión no tocó su caso):
el usuario entra con la contraseña nueva en otro contexto y la anterior ya no sirve; desde
`frontend/`, sobre `e8ecdeb`, `typecheck`, `lint` y `build` sin errores ni avisos.

Corrida de `npm test` del frontend (2026-11-16): 982 tests en 49 archivos, en verde.

### Entrega 2.2b

Estado: **COMPLETADO** el 2026-11-16 (R-077). Usuarios: inactivar, reactivar y baja, en `feature/front-2-2b-usuarios` (desde
`feature/front-2-2a-usuarios`, en el cierre de la 2.2a, `0bf3ed0`), sin integrar a `main` (se integra al cerrar la F2). Commits:
`2cd6dd4` (WIP de la implementación), `3265903` (WIP de la revisión) y el de este cierre (R-077: la documentación).

**Implementación.**

* **Acciones en el detalle** (`features/users/UserActions.tsx`, nuevo): "Inactivar" o "Reactivar" (`users.update`) y "Eliminar"
  (`users.destroy`), ocultos en el propio usuario. `PERMISSIONS` suma `usersDestroy`.
* **Inactivar y reactivar** (`UserStatusDialog`, `tone="primary"`): `PATCH users/{id}` con solo `{ is_active }` (`setUserActive`),
  con el aviso "Se van a cerrar todas sus sesiones y no va a poder ingresar hasta que lo reactives." o "Va a tener que ingresar de
  nuevo."; con el 200, `afterUserChanged` (el detalle toma la respuesta; `['users']` y `['veterinarians']` invalidados).
* **Baja** (`DeleteUserDialog`, `tone="danger"`): `DELETE users/{id}` (`deleteUser`); con el 200, `afterUserGone` (los listados
  vaciados, usuarios y veterinarios invalidados) y al listado, donde el detalle sale de la caché (desvío 1). Con `USER_IN_USE`, el
  mensaje de la API y "Inactivar" (en azul, si está activo y con `users.update`), que cierra este diálogo y abre el de inactivar; en
  un inactivo, el mensaje y "Cerrar".
* **Errores en los diálogos.** `USER_LAST_ADMIN` y `USER_SELF_ACTION` (si llegara, por un detalle viejo de otra pestaña) y un 422,
  el mensaje y solo "Cerrar"; el 404, aviso y vuelta al listado (`useUserGone`); lo demás (el servidor, la red, `FORBIDDEN`), el
  mensaje y el botón para probar de nuevo. Un solo pedido con `useSingleAction` en los dos diálogos.
* **Pruebas.** Vitest: 14 tests nuevos en `UserPages.test.tsx` (13 de la implementación y 1 de la revisión). Playwright:
  `e2e/usuarios-estado.spec.ts` (nuevo), 1 de 1 en 54,5 s: la sesión abierta en otro contexto termina al inactivar, el login
  responde el mensaje de `USER_INACTIVE`, la reactivación deja entrar de nuevo, la baja del usuario y la de `vet-a` (con ficha)
  ofrece "Inactivar" sin confirmarlo. Mutaciones de la implementación: 4 de 4 detectadas (los botones en el propio usuario,
  `USER_IN_USE` sin "Inactivar", la baja sin sacar el detalle de la caché y sin invalidar usuarios y veterinarios al inactivar).

| # | Desvío | Motivo |
|---|---|---|
| 1 | Con el 200 de la baja, el detalle sale de la caché con `removeQueries` al llegar al listado (como la baja del paciente), no con `resetQueries`; los listados, sí con `resetQueries` | Mientras navega, el detalle todavía está a la vista: vaciarlo antes lo volvería a pedir (un 404) |
| 2 | `dialogError` de `UserActions.tsx` repite el de `BranchDialogs.tsx` (no exportado) | Llevarlo a código común es otro tema; el usuario decidió hacerlo en la 2.3 (R-077, anotado en su [diseño](f2-datos-maestros.md#23-veterinarios)) |
| 3 | En el diálogo de baja con `USER_IN_USE`, el botón dice solo "Inactivar" (el diseño); el del diálogo de inactivar, "Inactivar a …" | El primero solo abre el otro diálogo |
| 4 | Un 422 al inactivar o eliminar (no debería llegar) muestra sus mensajes en el diálogo con "Cerrar" | Como la 2.1 (desvío 8) |
| 5 | Playwright: la sesión del otro contexto, al recargar, termina con "Tu sesión terminó. Ingresá de nuevo." (401: la API revocó sus tokens), y el mensaje de `USER_INACTIVE` aparece al volver a ingresar | Es lo que responde la API; el caso lo comprueba así |
| 6 | Edge: `USER_IN_USE` simulado (el `DELETE` del usuario temporal interceptado con un 409) para la captura y el paso con el teclado al diálogo de inactivar | No se intenta la baja de `vet-a` en la demo; la real está en Playwright contra `syncvet_e2e` |
| 7 | La captura `04-baja-en-uso.png` muestra "Inactivar" en un rojo pálido: es la transición de color de Tabler (0,3 s) tomada a mitad; un test comprueba que el botón es `btn-primary` | Captura tomada apenas llegó el mensaje |
| 8 | Prettier cambió el formato de `e2e/usuarios-estado.spec.ts` después de su corrida; no se repitió | Solo formato |

**Revisión (2026-11-16).** Sobre `2cd6dd4`, contra el cierre de la 2.2a (`0bf3ed0`), con el presupuesto por entrega y
`backend/CLAUDE.md` §6.5: sin hallazgos que cambien el comportamiento (los botones ocultos en el propio usuario y
`USER_SELF_ACTION` bien mostrado si llegara; `USER_LAST_ADMIN`; `USER_IN_USE` con "Inactivar" solo en un activo; el cuerpo
`{ is_active }` y los avisos; la invalidación de usuarios y veterinarios; la baja sin volver a pedir el detalle; un solo pedido;
Escape y el clic afuera no cierran mientras espera; los permisos). Corregido en `3265903` (solo tests, en `UserPages.test.tsx`):

| # | Hallazgo | Corrección |
|---|---|---|
| R1 | El 200 de la baja no comprobaba el desvío 1 (que el detalle no se vuelve a pedir) ni la invalidación de veterinarios | El test cuenta los `GET users/7` y revisa `['veterinarians']` invalidado |
| R2 | El doble clic se probaba solo al inactivar | El mismo test de la baja hace doble clic: un solo `DELETE` |
| R3 | Sin test del foco al pasar de la baja con `USER_IN_USE` al diálogo de inactivar, ni de Escape | Test nuevo: el foco en "Cancelar" del diálogo de inactivar; Escape lo cierra sin pedido y el foco vuelve a "Eliminar" |

Mutaciones de la revisión: 3 detectadas de 4 probadas (los errores de negocio con el botón para probar de nuevo, 4 tests; la baja
sin invalidar `['veterinarians']`, R1; la baja sin `useSingleAction`, R2). No detectada: `resetQueries` del detalle antes de
navegar (lo que el desvío 1 evita): en jsdom el detalle no se vuelve a pedir, así que el conteo de R1 no protege ese caso; queda
anotada, sin otro test (el 404 solo se vería contra la API real).

**Validación.** El usuario aceptó los 8 desvíos y dio la entrega por validada al pedir su cierre (R-077). Evidencia: Claude Code,
5 de 5 en Edge sin ventana contra `syncvet.test` (un usuario temporal, borrado por la API al terminar, `DELETE users/62`, 200;
`USER_IN_USE` simulado, desvío 6), con capturas fuera del repositorio, en la PC de la validación (entre ellas
`04-baja-en-uso.png`); Playwright, `e2e/usuarios-estado.spec.ts`, 1 de 1 (en la implementación; la revisión no tocó su caso);
desde `frontend/`, sobre `3265903`, `typecheck`, `lint` y `build` sin errores ni avisos.

Corrida de `npm test` del frontend (2026-11-16): 996 tests en 49 archivos, en verde.

### Entrega front-02

Estado: **COMPLETADO** el 2026-11-16 (R-078). Soporte del backend: la ficha de veterinario en `GET users`, en
`feature/front-02-usuarios-veterinario` (desde `feature/front-2-2b-usuarios`, en el cierre de la 2.2b, `85ebf3a`), sin integrar a
`main` (se integra al cerrar la F2). Commits: `bf98d8d` (WIP de la implementación), `f0218e6` (el teléfono de ejemplo de
`branches/form.test.ts` y el presupuesto por entrega) y el de este cierre (R-078: la documentación).

**Implementación.**

* `veterinarian` (`id` y `code`, o `null` sin ficha o con la ficha eliminada) pasó de `ShowUserResource` a `UserResource`, al final
  de sus claves (el detalle conserva su orden), y `UserController::RELATIONS` suma `veterinarian`: el listado la carga con el `with`,
  sin N+1. El alta y la edición ya la respondían; `me` (`MeResource`) no se tocó. Sin rutas, permisos, migraciones ni `error_code`
  nuevos (78, 135, 36).
* **Pruebas.** `tests/Feature/Users/UserVeterinarianTest.php` (nuevo): con y sin ficha y con la ficha eliminada; sin N+1 con 1 y 5
  usuarios (después de un pedido que llena la caché de permisos de Spatie, como `AppointmentQueueTest`); las claves del listado, el
  detalle, el alta y la edición. `MeTest`: las claves de `me`. `countQueries` quedó en el trait `Tests\Concerns\InspectsQueries`
  (las tres copias de la deuda 21 siguen: se unifican cuando se toquen esos tests). Mutaciones: 3 de 3 detectadas (sin
  `veterinarian` en el `with`, 6 → 10 consultas; la ficha cambiada en el detalle; la relación con `withTrashed()`). Postman: dos
  aserciones en la colección de usuarios (pedidos 19 y 44).

| # | Desvío | Motivo |
|---|---|---|
| 1 | `FictitiousDocumentsTest` fallaba en la rama desde la 2.1 (`626759b`) por `'1234567'` como teléfono corto en `frontend/src/features/branches/form.test.ts` (7 dígitos, el rango de las cédulas reales). Corregido en esta rama (`f0218e6`) con `'9'.repeat(7)`, armado en el código, sin cambiar lo que prueba | Las entregas del frontend no corrían la suite del backend; desde `f0218e6`, el presupuesto por entrega del `CLAUDE.md` de la raíz les suma ese test (pedido del usuario) |
| 2 | El test sin N+1 hace un pedido antes de contar | El primero llena la caché de permisos de Spatie (2 consultas más) y no es N+1; igual que `AppointmentQueueTest` |
| 3 | `countQueries` quedó como método del trait `Tests\Concerns\InspectsQueries`; las tres copias privadas de la deuda 21 siguen | Se unifican cuando se toquen esos tests |

**Revisión (2026-11-16).** Sobre `bf98d8d` (diff con `85ebf3a`), con el presupuesto por entrega: sin hallazgos. La ficha sale en
el listado con la forma del diseño, cargada con el `with` (`UserResource` se usa solo en `UserController`); la ficha eliminada,
`null` (`Veterinarian` con `SoftDeletes`); el detalle, el alta, la edición y `me`, con las mismas claves en el mismo orden; los
tests fallan con la regla rota (las 3 mutaciones de la implementación). `composer test:fast`: 1467 casos, 1451 pasan y 16
omitidos (solo MySQL), sin fallas.

**Validación.** El usuario dio la entrega por validada (`composer test` en verde, Newman de usuarios y la revisión) y aceptó los 3 desvíos al pedir el cierre (R-078). Evidencia: desde `backend/`, `composer test` en serie
sobre `f0218e6`, 1467 casos, 1451 pasan, 16 omitidos (solo MySQL) y 14303 aserciones; Newman de la colección de usuarios, 54
pedidos y 138 aserciones, sin fallas (en la implementación, sobre `bf98d8d`; el código del backend no cambió después); desde
`frontend/`, sobre `f0218e6`, `typecheck`, `lint` y `build` sin errores ni avisos.

Corrida de `npm test` del frontend (2026-11-16): 996 tests en 49 archivos, en verde.

### Entrega 2.3

Estado: **COMPLETADO** el 2026-11-16 (R-079). Veterinarios: listado (los tres roles), detalle, alta desde el usuario, edición,
inactivar y baja, en `feature/front-2-3-veterinarios` (desde `feature/front-02-usuarios-veterinario`, en el cierre de front-02,
`0ab4dc7`), sin integrar a `main` (se integra al cerrar la F2). Commits: `fcf6667` (`dialogError` a código común), `72975a6` (WIP
de la implementación), `359b2d7` (WIP de la revisión) y el de este cierre (R-079: la documentación).

**Implementación** (el 2026-11-16). Primero, `fcf6667`: `dialogError` pasó a `src/lib/api/dialog-error.ts`, con sus tests;
sucursales y usuarios la importan sin cambiar su comportamiento (sus tests siguieron en verde sin tocarlos).

* **Hecho.** `features/veterinarians` (nuevo): el menú "Veterinarios" (`veterinarians.index`, después de "Especies"), `/veterinarios`
  (búsqueda, `?estado=inactivos|todos` traducido a `status=inactive` o `include_inactive=1`, `?sucursal=`; el enlace al detalle y
  "Nuevo veterinario" solo para el administrador; sin la comisión ni las notas), `/veterinarios/$id` (la comisión "12,5 %", el
  usuario con su enlace, "Usuario inactivo"; Editar, Inactivar o Activar y Eliminar), `/veterinarios/nuevo?usuario=` (el usuario
  entre los activos con el rol veterinario y `veterinarian === null`, de `['users', 'options', { role, include_inactive }]`) y
  `/veterinarios/$id/editar` (el código y el usuario solo para leer; sin `user_id`, `code` ni `status` en el cuerpo). La matrícula
  en mayúsculas y recortada; la comisión con coma o punto, mandada con punto (vacía, `"0"`). La baja con `VETERINARIAN_IN_USE`: el
  mensaje y "Inactivar" (en una inactiva, solo "Cerrar"). Crear, editar, activar o inactivar invalidan `['veterinarians']` y
  `['users']` (el detalle toma la respuesta) y `['me']` si la ficha es del usuario de la sesión; la baja, igual, con los listados
  vaciados y el detalle fuera de la caché al llegar al listado. En usuarios: `User` suma `veterinarian` (front-02); el detalle
  enlaza a la ficha o muestra "Crear la ficha de veterinario" (activo, con el rol y sin ficha); la alerta de `USER_IS_VETERINARIAN`
  enlaza a la ficha. `PERMISSIONS` suma los cinco de `veterinarians.*`.
* **Pruebas.** Vitest: `dialog-error.test.ts` (8), `veterinarians/search.test.ts` y `form.test.ts` (44, cada regla en su borde) y
  `VeterinarianPages.test.tsx` (25: listado de los dos roles, estado y sucursal en la URL y en el pedido, `/sin-permiso`, el
  selector sin los usuarios con ficha y con `?usuario=`, la ficha restaurada con su código, 422 de la matrícula, la edición sin
  `user_id`, inactivar, activar, la baja con el 200 y con `VETERINARIAN_IN_USE`, el detalle del usuario). Mutaciones: 4 de 4
  detectadas (el selector con los usuarios con ficha; `user_id` en la edición; el enlace al detalle para el recepcionista; sin
  invalidar `['users']`). Playwright: `e2e/veterinarios.spec.ts` (nuevo), 1 de 1 en 50,4 s. Desde `backend/`,
  `FictitiousDocumentsTest`, 3 de 3.

| # | Desvío | Motivo |
|---|---|---|
| 1 | El filtro del listado es el del diseño (`?estado=activos\|inactivos\|todos` y `?sucursal=`), no "Incluir los inactivos" (`?inactivos=1`) del pedido de la entrega | Lo decidió el usuario al empezar la entrega ("Todos" incluye a los inactivos) |
| 2 | El estado no es un campo del formulario de edición: se cambia con "Inactivar" o "Activar" en el detalle | El diseño (pregunta 13): activar e inactivar son botones con su confirmación |
| 3 | En el alta con `?usuario=`, "Cancelar" vuelve al detalle de ese usuario (sin `?usuario=`, al listado). Un `?usuario=` que no se puede elegir (con ficha, sin el rol, inactivo) no queda elegido | Se vuelve a donde se vino; el selector solo ofrece lo que la API acepta |
| 4 | El selector del alta pide 100 usuarios (`per_page` máximo de la API): con más de 100 usuarios con el rol veterinario, los que pasan de la primera página no se ofrecen | Una clínica no llega a eso; si hiciera falta, una búsqueda en el selector |
| 5 | La ficha restaurada muestra el mensaje de la API ("Veterinario creado correctamente.", el mismo 201 del alta) y el detalle con su código de siempre; no hay un aviso propio de "restaurada" | La API no distingue la restauración en la respuesta; el mensaje se muestra tal cual |
| 6 | Tests tocados por la entrega (no por el commit de `dialogError`): el menú con "Veterinarios" en `frontend/src/app/permissions.test.tsx` y `frontend/src/lib/auth/permissions.test.ts`; en `UserPages.test.tsx`, el código de la ficha ahora es un enlace (2 tests) | Cambios de comportamiento de la 2.3 |
| 7 | La corrida final de `npm test` dio 6 fallas en `frontend/src/lib/auth/permissions.test.ts` (el menú sin "Veterinarios"); se corrigió y se repitió solo ese archivo, 12 de 12. La cifra total, 1073 tests en 53 archivos (996 más 77 nuevos), sale de la cuenta, no de una corrida completa en verde: queda para la revisión o el cierre | El presupuesto por entrega: se repite solo el paso que falló |
| 8 | Después de la validación en Edge, el selector del usuario sumó `className="mb-3"` (la pista quedaba pegada a "Matrícula", como los selectores del paciente); `typecheck`, `lint` y sus tests relacionados (455) en verde, sin repetir Edge ni el build | Solo un margen |
| 9 | Edge: la primera corrida del control 5 falló al crear el usuario temporal con `@example.com` (422: la demo valida el DNS, como el desvío 10 de la 2.2a), sin cambiar la demo; se repitió solo ese control con `@mailinator.com` (usuario 39, borrado por la API, `DELETE users/39`, 200). `VETERINARIAN_IN_USE` se simuló interceptando el `DELETE` de `vet-a` (nunca llegó a la API); la ficha de `vet-a` solo se leyó (igual antes y después) | La demo queda como estaba; la baja real está en Playwright contra `syncvet_e2e` |

**Validación en Edge** (sin ventana, contra `syncvet.test` con Vite en `:5180`, detenido por su PID): 5 de 5. (1) El listado con
"Todos" y al ancho de tablet, sin desplazamiento horizontal; (2) el detalle de `vet-a` ("0 %"); (3) la edición con la comisión con
coma ("0,00") y el código y el usuario solo para leer, sin guardar; (4) la baja con `VETERINARIAN_IN_USE` simulado: el mensaje y
"Inactivar", sin confirmar; (5) del usuario temporal a "Crear la ficha de veterinario", con el usuario elegido, y "Cancelar" de
vuelta al usuario, sin guardar. Capturas fuera del repositorio, en la PC de la validación
(`%TEMP%\claude\…\scratchpad\capturas-2-3`: `01-listado.png`, `02-listado-tablet.png`, `03-detalle.png`, `04-edicion.png`,
`05-baja-en-uso.png`, `06-usuario-sin-ficha.png`, `07-alta.png`).

**Revisión** (2026-11-16, sobre `fcf6667` y `72975a6`, contra `0ab4dc7`; el usuario aceptó los 9 desvíos). Antes de revisar,
`npm test` completo: 1073 tests en 53 archivos, en verde (la cifra del desvío 7 queda confirmada por una corrida).

* **Revisado, sin hallazgos.** El listado no muestra la comisión ni las notas: el tipo `Veterinarian` no las tiene, las columnas
  son fijas y la caché guarda solo lo que manda `VeterinarianResource` (sin `commission_percentage` ni `notes`, R-037); el único
  `setQueryData` es el del detalle (`afterVeterinarianChanged`), que solo el administrador pide; el recepcionista y el veterinario
  no piden el detalle (`requirePermission` antes del `loader`, con test) y salir de la sesión vacía la caché. La matrícula,
  recortada y en mayúsculas antes de validar y de mandar, como `VeterinarianMessages::normalizeLicense` ("sin espacios" es en los
  extremos, como la API). La comisión, texto de punta a punta (`Number` solo para el rango): coma o punto, `numeric`,
  `min`/`max` y `decimal:0,2` en el orden y con los mensajes de la API, también el exponente (`1e1`: numérico, pero no decimal).
  El alta ofrece solo usuarios activos con el rol y `veterinarian === null`; `?usuario=` que no se puede elegir no queda elegido;
  la restauración muestra el código de siempre. `VETERINARIAN_IN_USE` ofrece "Inactivar" (solo en una activa). La alerta de
  `USER_IS_VETERINARIAN` enlaza a la ficha. Las invalidaciones son las del diseño (`['veterinarians']`, `['users']` y `['me']` si
  es la propia; usuarios y sucursales ya invalidaban `['veterinarians']`). `dialogError` se movió sin cambios (el cuerpo es
  idéntico en las dos copias borradas y en `src/lib/api/dialog-error.ts`). Permisos: las cuatro rutas con su permiso; el enlace al
  detalle, "Nuevo veterinario", Editar, Inactivar y Eliminar con `useCan()`.
* **Corregido (tests que faltaban).** En `VeterinarianPages.test.tsx`, 2 tests: inactivar la ficha propia (un administrador que
  también es veterinario) vuelve a pedir `me`; inactivar una ficha que otra pestaña eliminó (404) avisa y vuelve al listado
  (`useVeterinarianGone`, sin test hasta ahora). En `form.test.ts`, el caso `1e1` (paridad con `decimal:0,2`).
* **Notas, sin cambio.** El test "el recepcionista: … sin la comisión" es débil (la respuesta simulada nunca la trae): la protección
  real es la API, y en el frontend, el tipo y las columnas fijas. Con un 404 en una acción, `useVeterinarianGone` no vuelve a pedir
  `me` si la ficha era la propia; `SessionLayout` lo vuelve a pedir al volver a la pestaña, y el caso (la propia ficha borrada en
  otra pestaña) no cambia permisos.
* **Mutaciones** (3 de 3 detectadas, revertidas con `git checkout`): sin volver a pedir `me` en la ficha propia (lo detecta el test
  nuevo); la matrícula sin pasar a mayúsculas (3 tests); `VETERINARIAN_IN_USE` sin "Inactivar" (1 test).
* **Cifras.** Tests tocados (`vitest run` de los dos archivos): 55 de 55. Corrida final: `typecheck` y `lint` sin errores ni avisos; `npm test`,
  1076 tests en 53 archivos, en verde (1073 más los 3 nuevos); `build` sin errores; desde `backend/`, `FictitiousDocumentsTest`,
  3 de 3. Playwright no se repitió: los cambios son solo de tests de Vitest.

**Validación.** El usuario aceptó los 9 desvíos, pidió la revisión y, con ella, el cierre (R-079). Evidencia: Claude Code, 5 de 5
en Edge sin ventana contra `syncvet.test` (arriba; el usuario temporal, borrado por la API, `DELETE users/39`, 200;
`VETERINARIAN_IN_USE` simulado, desvío 9); Playwright, `e2e/veterinarios.spec.ts`, 1 de 1 (en la implementación; la revisión no
tocó su caso; 26 casos en total), con el recepcionista que ve el listado sin la comisión, sin enlaces ni "Nuevo veterinario"; desde `frontend/`, sobre `359b2d7`, `typecheck`, `lint` y `build` sin errores ni avisos; desde
`backend/`, `FictitiousDocumentsTest`, 3 de 3.

Corrida de `npm test` del frontend (2026-11-16): 1076 tests en 53 archivos, en verde.

### Entrega 2.4

Estado: **COMPLETADO** el 2026-11-17 (R-080). Servicios: listado y detalle (los tres roles), alta, edición, inactivar y baja, con el
precio en guaraníes, en `feature/front-2-4-servicios` (desde `feature/front-2-3-veterinarios`, en el cierre de la 2.3, `0743a72`),
sin integrar a `main` (se integra al cerrar la F2). Commits: `0686f94` (WIP de la implementación), `b0bab2b` (WIP de la revisión) y
el de este cierre (R-080: la documentación).

**Implementación** (el 2026-11-16). Antes, en la misma
rama, el `CLAUDE.md` de la raíz suma al presupuesto por entrega (pedido del usuario): si `npm test` de la corrida final falla en un
archivo, después de corregirlo se repite la suite completa, para que la cifra registrada sea siempre la de una corrida real.

* **Hecho.** `features/services` (nuevo): el menú "Servicios" (`services.index`, después de "Especies"), `/servicios` (búsqueda,
  categoría con las etiquetas de `ServiceCategory`, `?estado=inactivos|todos` traducido a `status=inactive` o `include_inactive=1`;
  el precio con `formatGuaranies`, la duración "30 min" y el IVA "10 %"; el enlace al detalle con `services.show` y "Nuevo
  servicio" con `services.store`), `/servicios/$id` (los tres roles, con `key={id}`; Editar, Inactivar o Activar y Eliminar solo
  para el administrador), `/servicios/nuevo` y `/servicios/$id/editar` (el código solo para leer). El formulario: nombre con
  `ServiceMessages::NAME_REGEX` (acepta `/ , + ( ) . - ' %`, rechaza `//`), categoría, precio (texto con `inputMode="numeric"`:
  "150.000" se manda `"150000"`; la coma, los decimales, el signo, el exponente y más de 12 dígitos, con el mensaje de
  `WholeAmount` y sin pedido; `"150000.00"` de la API vuelve "150.000"), duración (5 a 720, múltiplo de 5; 30 por defecto), IVA
  (`RadioGroup` 0, 5 o 10; 10 por defecto, número en el cuerpo) y descripción. `SERVICE_NAME_NOT_EDITABLE` al campo nombre (con el
  foco); la baja con `SERVICE_IN_USE`: el mensaje y "Inactivar" (en uno inactivo, solo "Cerrar"); los errores de los diálogos con
  `dialogError`; un solo pedido con `useSingleAction`. Crear, editar, activar o inactivar invalidan `['services']` (el detalle toma
  la respuesta); la baja y el 404 vacían los listados y sacan el detalle de la caché al llegar al listado. `PERMISSIONS` suma los
  cinco de `services.*`.
* **Pruebas.** Vitest: `services/form.test.ts` y `search.test.ts` (87: cada regla en su borde, el precio con sus casos del diseño,
  la duración 4, 5, 720 y 722, y las categorías leídas de `app/Enums/ServiceCategory.php`, H2) y `ServicePages.test.tsx` (23:
  listado de los dos roles, categoría y estado en la URL y en el pedido, el detalle sin acciones para el recepcionista,
  `/sin-permiso`, el alta con "150.000" y doble clic, los precios rechazados sin pedido, el nombre con `//`, 422 del nombre,
  la edición con "150.000" y el cuerpo sin `code` ni `status`, `SERVICE_NAME_NOT_EDITABLE`, inactivar, activar, la baja con el
  200, con `SERVICE_IN_USE` en uno activo y en uno inactivo, y el 404 en una acción). Mutaciones: 4 de 4 detectadas (el precio
  mandado como número, 11 fallas; los puntos de miles sin sacar, 10; el IVA como texto, 3; `SERVICE_NAME_NOT_EDITABLE` fuera del
  campo, 1). Playwright: `e2e/servicios.spec.ts` (nuevo), 1 de 1 en 1,5 min (27 casos en total). Corrida final, desde `frontend/`:
  `typecheck`, `lint` y `build` sin errores ni avisos; `npm test`, 1186 de 1186 en 56 archivos, en verde (sin fallas, en la primera
  corrida); desde `backend/`, `FictitiousDocumentsTest`, 3 de 3.

| # | Desvío | Motivo |
|---|---|---|
| 1 | El filtro del listado es el del diseño (`?estado=activos\|inactivos\|todos`), no "Incluir los inactivos" (`?inactivos=1`) del pedido de la entrega | Lo decidió el usuario al empezar la entrega, como en la 2.3 |
| 2 | El estado no es un campo del formulario: se cambia con "Inactivar" o "Activar" en el detalle y el alta queda `active` | Lo decidió el usuario al empezar la entrega (el diseño, pregunta 13) |
| 3 | `formatGs` del pedido es `formatGuaranies` (`src/lib/format/money.ts`) | Es el nombre de la función común |
| 4 | Los puntos se sacan solo si agrupan miles ("150.000", "12.500.000.000"); "1500.00" se rechaza (no es "150000"). Los ceros de adelante no cuentan para los 12 dígitos y no se mandan ("007" → `"7"`), como `WholeAmount::normalize` | Paridad con la API sin adivinar lo escrito |
| 5 | La duración es un campo de texto con `inputMode="numeric"` (no `type="number"`, como el precio) y acepta el signo como `integer` de Laravel ("+30") | Paridad con la API; el teclado numérico en el celular |
| 6 | Playwright: el caso del pedido (crear con "150.000", el cuerpo con `"150000"`, "Gs. 150.000" después de recargar, editar, el recepcionista sin acciones, inactivar y borrar), con el precio con coma rechazado sin pedido; no el cambio de nombre de un servicio usado | El `DemoSeeder` no crea servicios ni turnos: no hay un servicio usado. `SERVICE_NAME_NOT_EDITABLE` está en Vitest |
| 7 | Tests tocados por la entrega: el menú con "Servicios" en `frontend/src/app/permissions.test.tsx` y `frontend/src/lib/auth/permissions.test.ts` | Cambio de comportamiento de la 2.4 |
| 8 | `FictitiousDocumentsTest` tomó "1.000.000" (7 dígitos) de `form.test.ts` y de un comentario de `form.ts` como una cédula; los ejemplos pasaron a "12.500.000.000" | Ningún número con forma de cédula en el código del frontend |
| 9 | Edge: la primera corrida del script falló al ingresar (la etiqueta "Contraseña" también es la del botón para mostrarla), sin crear nada; se repitió entera con la etiqueta exacta | Un detalle del script, fuera del repositorio |
| 10 | Edge, control 1: el script lo marcó MAL por dos motivos. El campo del precio no tiene el atributo `type` (`null`: un `<input>` sin `type` es de texto, lo que se quería; el criterio del script era estricto). Y a 375 px la barra del menú se sale de la pantalla (la página mide unos 820 px): **hallazgo anterior a la entrega**, el menú horizontal de `SessionLayout` ya medía unos 700 px sin "Servicios"; no se corrigió (es el layout común) | **Decisión del usuario pendiente:** corregirlo en una entrega aparte o registrarlo como deuda |

**Validación en Edge** (sin ventana, contra `syncvet.test` con Vite en `:5180`, detenido por su PID): 4 de 5 según el script, con
el control 1 explicado en el desvío 10. (1) El alta al ancho de un celular: el precio y la duración con `inputmode="numeric"`; el
menú desborda a 375 px; (2) el detalle con "Gs. 999.999.999.999" (el 201 del alta); (3) el listado con el precio largo en una
sola línea; (4) el listado a 768 px sin desplazamiento horizontal de la página; (5) la edición con "999.999.999.999" en el
precio, sin guardar. El servicio temporal (`SRV-000031`) se borró por la API al terminar (`DELETE services/31`, 200); la demo
quedó como estaba. Capturas fuera del repositorio, en la PC de la validación (`%TEMP%\claude\…\scratchpad\capturas-2-4`:
`01-alta-celular.png`, `02-detalle.png`, `03-listado.png`, `04-listado-tablet.png`, `05-edicion.png`).

**Revisión** (2026-11-16, sobre `0686f94`, contra el cierre de la 2.3, `0743a72`). El usuario aceptó los 10 desvíos; el 10 (el
menú que se sale de la pantalla a 375 px) se corrige en la 2.5, junto con el logo en la barra: anotado en el
[diseño de la 2.5](f2-datos-maestros.md#25-configuración-de-la-clínica-y-logo) y en el handoff (el menú se pliega detrás de un botón en pantallas
chicas, accesible con el teclado y con lector de pantalla, sin el JS de Tabler).

* **Revisado, sin hallazgos que cambien el comportamiento.** El precio: los puntos se sacan solo si agrupan miles; se valida y se
  manda como texto de solo dígitos (`wholeAmount`, sin `Number`, `parseFloat` ni decimales en ningún paso), de 0 a
  999.999.999.999 con los ceros de adelante fuera de la cuenta, como `WholeAmount::normalize`; `"150000.00"` vuelve "150.000" por
  una expresión regular y se muestra con `formatGuaranies` desde el texto. La duración: entero (con el signo de `integer`), de 5 a
  720 y múltiplo de 5, en el orden de la API, mandada como número. El nombre: `SERVICE_NAME_PATTERN` es `ServiceMessages::NAME_REGEX`
  con `u` y `s` (PHP compila `/u` con UCP: `\s`, `\pL` y `\pN` son de Unicode en los dos), de 2 a 120 caracteres sin los espacios de
  los extremos. `SERVICE_NAME_NOT_EDITABLE` va al campo nombre con el foco. `SERVICE_IN_USE` ofrece "Inactivar" solo en uno activo.
  El IVA va como número. Las acciones del detalle y "Nuevo servicio" dependen de `useCan()`; las rutas de alta y edición, de
  `requirePermission`. Los diálogos usan `dialogError` común; las dos rutas del detalle llevan `key={id}`.
* **Corregido (tests que faltaban).** En `ServicePages.test.tsx`, el listado y el detalle sin acciones se probaban solo con el
  recepcionista: ahora con `it.each` del recepcionista y del veterinario (2 tests más).
* **Mutaciones** (3 de 3 detectadas, revertidas con `git checkout`): el nombre sin `(?!.*//)` (2 fallas: `a//b` en `form.test.ts`
  y en el alta); la duración sin el múltiplo de 5 (2 fallas); las acciones del detalle sin mirar el permiso (2 fallas, el
  recepcionista y el veterinario). La primera necesitó tres intentos: dos veces `sed` y `perl` no cambiaron la expresión (las
  barras escapadas) y los tests pasaron sin la mutación aplicada; se aplicó con un script de Node fuera del repositorio y ahí
  falló, como debía.
* **Cifras.** `npm test` completo, una sola corrida, en verde a la primera: 1188 de 1188 en 56 archivos (los 1186 de la
  implementación y los 2 nuevos); desde `backend/`, `FictitiousDocumentsTest`, 3 de 3. Playwright no se repitió (solo cambiaron
  tests de Vitest).

**Validación.** El usuario aceptó los 10 desvíos (el 10, el menú que se sale de la pantalla a 375 px, se corrige en la 2.5), pidió la
revisión y, con ella, el cierre (R-080). Evidencia: Claude Code, 4 de 5 en Edge sin ventana contra `syncvet.test` (arriba; el control
1, explicado en el desvío 10; el servicio temporal, borrado por la API, `DELETE services/31`, 200); Playwright, `e2e/servicios.spec.ts`,
1 de 1 (en la implementación; la revisión no tocó su caso; 27 casos en total: "150.000" se manda `"150000"` y se ve
"Gs. 150.000" después de recargar). Corrida del cierre (2026-11-17, en otra PC, sobre
`b0bab2b`), desde `frontend/`: `typecheck`, `lint` y `build` sin errores ni avisos; `npm test`, 1188 tests en 56 archivos, en verde
(la misma cifra que la revisión); desde `backend/`, `FictitiousDocumentsTest`, 3 de 3.

Corrida de `npm test` del frontend (2026-11-17): 1188 tests en 56 archivos, en verde.

### Entrega 2.5

Estado: **COMPLETADO** el 2026-11-17 (R-081). Configuración de la clínica (datos, claves y logo), el logo en la barra para los tres
roles y el menú plegable en pantallas chicas, en `feature/front-2-5-configuracion` (desde `feature/front-2-4-servicios`, en el
cierre de la 2.4, `37ac3cf`), sin integrar a `main` (se integra al cerrar la F2). Commits: `11dabf4` (WIP de la implementación),
`26e4339` (WIP de la revisión) y el de este cierre (R-081: la documentación).

**Implementación** (el 2026-11-17).

* **Hecho.** `src/lib/files/image.ts` (nuevo, H8): el control de una imagen (el tipo por el contenido y el tamaño primero) con el
  límite y los mensajes como parámetros; `features/patients/photo.ts` lo usa con 5 MB y conserva sus exportaciones (sus tests no
  cambiaron). `features/settings` (nuevo): `/configuracion/clinica` (`system-settings.update`) con su tarjeta "Clínica" en
  `/configuracion`; el formulario (nombre, razón social, correo, teléfono, sitio web y pie) con las reglas y los mensajes de
  `UpdateSystemSettingsRequest`, el RUC (`ruc`-`ruc_dv`) solo para leer y fuera del formulario, `PATCH system-settings` con el
  formulario completo (sin `ruc` ni `ruc_dv`, las claves vacías en `null`, el correo en minúsculas, el teléfono sin espacios ni
  guiones), un solo pedido con `useSingleAction`, la respuesta en `['settings']` y `['me']` invalidado (el nombre en la barra). La
  tarjeta del logo, como la foto: vista previa, "Subir el logo", reemplazar y "Quitar el logo" con `ConfirmDialog`; 2 MB justos
  pasan (`logo.ts`, con los mensajes de `UploadClinicLogoRequest`); el 413 o el 422 con `errors.logo`, al campo con el foco; el 413
  sin `errors`, a la alerta; `CLINIC_LOGO_NOT_FOUND` al ver, el lugar vacío; al quitar, aviso y la configuración pedida de nuevo. La
  mutación con `gcTime: 0` y `reset()`. `Brand` muestra el logo (32 px de alto, `object-fit: contain`, el nombre de la clínica en
  `alt`) si `me.clinic.has_logo` y `system-settings.show-logo` (los tres roles), con la clave `['settings', 'logo']` de la
  configuración: subir o quitar lo cambia sin recargar; sin logo, mientras llega o con un error, la marca SyncVet. El menú
  plegable: `src/app/MainMenu.tsx` (nuevo) con `useMediaQuery` (`src/lib/hooks/use-media-query.ts`, nuevo); en pantallas chicas,
  el botón "Menú" con `aria-expanded` y `aria-controls`, la lista con `hidden` y en columna; Enter o Espacio lo abren, Escape lo
  cierra con el foco de vuelta en el botón, elegir un ítem lo cierra. `PERMISSIONS` suma `system-settings.show-logo`,
  `.upload-logo` y `.delete-logo`.
* **Pruebas.** Vitest: `lib/files/image.test.ts` (10: el límite como parámetro en su borde, el tipo por el contenido y el logo de 2
  MB justos y de un byte más), `settings/form.test.ts` (21: cada regla en su borde, el sitio con `http://`, sin protocolo, `ftp://`
  o con un espacio rechazado y `HTTPS://` aceptado, el cuerpo sin `ruc`, las claves vacías en `null` y las claves comparadas con
  `app/Enums/SystemSettingKey.php`), `settings/ClinicSettingsPage.test.tsx` (25: la tarjeta, el recepcionista y el veterinario a
  `/sin-permiso`, el RUC solo para leer, el cuerpo completo y `me` pedido de nuevo, borrar una clave, `http://` sin pedido, el 422 de
  `settings.website` a su campo con el foco, el doble clic; el logo: sin logo sin descarga, subir con la vista previa y el
  multipart con `logo`, 2 MB justos y uno más, un texto con extensión de imagen, el 413 con y sin `errors`, el 422, reemplazar con
  una descarga nueva, quitar sin volver a pedirlo y con `me` pedido de nuevo, quitar con `CLINIC_LOGO_NOT_FOUND`, el 404 al verlo;
  la barra con el logo para los tres roles y con un 500) y `frontend/src/app/MainMenu.test.tsx` (5: ancho sin botón; al ancho de un celular
  plegado con `aria-expanded` y `aria-controls`; abrir con Enter y con Espacio, Escape desde un ítem y desde el botón con el foco de
  vuelta; elegir un ítem; ensanchar la pantalla). Ningún test existente se tocó. Mutaciones: 4 de 4 detectadas, revertidas desde
  una copia fuera del repositorio (los archivos son nuevos): `ruc` mandado en el cuerpo (1 falla); el límite de 5 MB en el logo (1
  falla, la del límite; después, el test de la página usa los 2 MB escritos y no la constante, y también falla); `me` sin
  invalidar después de subir o quitar el logo (1 falla, la de subir; después, el test de quitar también comprueba que `me` se
  vuelve a pedir); Escape sin devolver el foco al botón (1 falla). Playwright: `e2e/configuracion-clinica.spec.ts` (nuevo), 1 de 1
  en 18 s (28 casos en total): el teléfono con espacios y guiones mandado sin ellos y visto después de recargar, el cuerpo sin
  `ruc`, subir `logo-clinica.png`, verlo en la barra después de recargar, reemplazarlo por `logo-clinica.webp` y quitarlo, y el
  menú a 375 px (plegado, abierto con Enter, Escape con el foco de vuelta, elegir un ítem, sin desplazamiento horizontal); lo
  guardado al empezar se restaura por la API. Corrida final, desde `frontend/`: `typecheck`, `lint` y `build` sin errores ni avisos;
  `npm test`, 1249 de 1249 en 60 archivos, en verde (en la segunda corrida completa, ver el desvío 9); desde `backend/`,
  `FictitiousDocumentsTest`, 3 de 3 (en la segunda corrida, ver el desvío 8).

| # | Desvío | Motivo |
|---|---|---|
| 1 | El pedido de la entrega decía que la pantalla "la ven los tres roles; solo el administrador la edita"; se siguió el diseño: `/configuracion/clinica` solo para el administrador (`system-settings.update`). El recepcionista y el veterinario ven el logo en la barra | Lo decidió el usuario al empezar la entrega (la pregunta, con el diseño como recomendación) |
| 2 | El menú se pliega por debajo de 992 px (el `lg` de Tabler), no de 768 px (`md`) | La barra del administrador mide unos 820 px: a 768 px tampoco entra. En Edge, a 768 px, plegado y sin desplazamiento horizontal |
| 3 | Plegado o no lo decide `matchMedia` en React (`useMediaQuery`, nuevo en `src/lib/hooks`), no una clase de CSS; sin `matchMedia`, la barra ancha | Así jsdom lo prueba (el test simula el ancho con `matchMedia`) y el estado de `aria-expanded` sigue al ancho real |
| 4 | Con `has_logo` y el 404 `CLINIC_LOGO_NOT_FOUND` al verlo, el lugar queda vacío pero se sigue ofreciendo "Quitar el logo" (la foto, en ese caso, pasa a "sin foto") | Quitar limpia la referencia en la API (§6.8); si responde el 404, el aviso y la configuración pedida de nuevo |
| 5 | Mientras se descarga el logo, la barra muestra la marca SyncVet (un instante, al entrar o al reemplazarlo) | Nunca un hueco ni una imagen rota; el diseño pide la marca "sin logo o con un error" |
| 6 | Guardar los datos pone la respuesta en `['settings']` (`setQueryData`) en lugar de invalidarla; `['me']` sí se invalida | La respuesta del `PATCH` ya es la configuración completa: es lo mismo sin un pedido más |
| 7 | Los valores del formulario van anidados como el cuerpo (`settings.website`); el teléfono se manda sin espacios ni guiones (`normalizePhone`, como clientes y sucursales), aunque esta API no los quita; el sitio acepta `https://` en mayúsculas (como `url:https` de Laravel) y `new URL()` decide el resto | `applyValidationErrors` lleva cada clave de la API a su campo sin traducirla (una clave que no es un campo, a la alerta); el navegador no rechaza nada que la API acepta |
| 8 | `FictitiousDocumentsTest` tomó "99400001-1", el RUC de ejemplo del test de la página, como un RUC real (el dígito no es el de un 999999xx); los tests usan el de la demo, "99999901-0" | Ningún número con forma de cédula o RUC real en el código del frontend |
| 9 | La primera corrida completa de `npm test` falló en un test de `BranchPages.test.tsx` (el alta de una sucursal, por tiempo con la PC cargada: pasó aislado y la entrega no toca sucursales); después de corregir el desvío 8 se repitió la suite completa, en verde | La regla del presupuesto: la cifra es la de una corrida completa real |
| 10 | La corrida final de `lint` marcó dos detalles (`isLogoMissing` exportado desde un componente y una aserción en `MainMenu.test.tsx`); se corrigieron y se repitieron `lint`, esos tests y `typecheck` | La regla del presupuesto: solo el paso que falló y los tests del archivo tocado |

**Validación en Edge** (sin ventana, contra `syncvet.test` con Vite en `:5180`, detenido por su PID): 5 de 5. (1)
`/configuracion/clinica` en escritorio: el RUC "99999901-0" solo para leer, sin desplazamiento horizontal; (2) un logo ancho (600 ×
100, generado en el navegador) en la barra: 192 × 32, sin deformarse; (3) uno alto (100 × 300): 11 × 32, sin deformarse; (4) a 375
px, con el logo ancho: el menú plegado detrás de "Menú" (`aria-expanded="false"`), abierto con los 7 ítems, Escape con el foco de
vuelta, sin desplazamiento horizontal; (5) a 768 px: el menú plegado, cerrado y abierto, sin desplazamiento horizontal. La consola,
sin errores. La demo quedó como estaba: los datos y las claves guardados al empezar se restauraron por la API (`PATCH`, 200; iguales
a los de antes) y el logo, que no había, se quitó (`DELETE system-settings/logo`, 200). Capturas fuera del repositorio, en la PC de
la validación (`%TEMP%\claude\…\scratchpad\capturas-2-5`: `01-configuracion-clinica.png`, `02-barra-logo-ancho.png`,
`02b-configuracion-con-logo.png`, `03-barra-logo-alto.png`, `04-celular-menu-cerrado.png`, `04b-celular-menu-abierto.png`,
`05-tablet-configuracion.png`, `05b-tablet-menu-abierto.png`). En la captura 04b, el botón "Menú" se ve gris: es el estilo de
foco de `btn-ghost-secondary` de Tabler, no un botón deshabilitado.

**Revisión** (2026-11-17, sobre `11dabf4`, con el presupuesto por entrega). El usuario aceptó los 10 desvíos.

* **Sin cambios.** El RUC nunca va en el pedido (`settingsFormToPayload` arma el cuerpo con `name`, `legal_name` y las cuatro
  claves; el RUC es un campo de solo lectura sin `register`). Las claves vacías van en `null`, y las reglas y los mensajes son los
  de `UpdateSystemSettingsRequest` y `SystemSettingKey::rules()` (los largos con `Array.from` sobre el texto sin espacios en los
  extremos, como `TrimStrings` y `mb_strlen`; el correo en minúsculas antes del largo, como `prepareForValidation`; `https://`
  sin distinguir mayúsculas, como `url:https`). `src/lib/files/image.ts` conserva el comportamiento de la foto: la misma firma de
  bytes, el tamaño antes que el contenido y el límite con `>`; `photo.ts` conserva sus exportaciones y sus tests no cambiaron. El
  logo: 2 MB justos pasan; el 413 o el 422 con `errors.logo`, al campo con el foco; el 413 sin `errors`, a la alerta;
  `CLINIC_LOGO_NOT_FOUND` al verlo, el lugar vacío; al quitarlo, aviso y la configuración pedida de nuevo; `BlobImage` libera la URL
  de objeto. La barra: el logo con `system-settings.show-logo` (los tres roles) y la misma clave de la caché. Los permisos: la ruta
  con `system-settings.update`; subir y quitar, cada uno con su permiso.
* **Hallazgo 1 (menor, corregido): el menú plegado quedaba abierto al navegar sin elegir un ítem** (el enlace de la marca, el
  usuario a `/perfil`, "Atrás"), y al ensanchar la pantalla con el menú abierto y volver a achicarla, reaparecía abierto. Con el foco
  en un ítem de la barra ancha, achicar la ventana dejaba el foco en un elemento oculto. `MainMenu.tsx`: el menú se cierra al
  cambiar la ruta (`useRouterState`) o el ancho (el estado se ajusta durante el render) y, al plegarse con el foco en la lista, el
  foco pasa al botón (`useLayoutEffect`). Dos tests nuevos en `MainMenu.test.tsx`, que fallan con el menú original.
* **Hallazgo 2 (menor, corregido): el test de reemplazar el logo no comprobaba que no se viera el viejo.** La mutación
  "`invalidateQueries` en lugar de `resetQueries` al reemplazarlo" (el viejo se ve mientras baja el nuevo) sobrevivía. El test
  ahora retiene la descarga nueva y comprueba que, mientras tanto, ni la tarjeta ni la barra muestran un logo y que la URL vieja se
  liberó; con la mutación, falla.
* **Desvío 9, la causa (el test era frágil, corregido).** "Los códigos en mayúsculas…; al detalle de la nueva"
  (`BranchPages.test.tsx`) esperaba `findByText('Datos de la sucursal')`, pero el formulario del alta tiene una tarjeta con ese
  mismo título: la espera se cumplía en el acto, antes del `POST`, y el test pasaba solo si el pedido y la navegación terminaban en
  los pocos ciclos que siguen (con la PC cargada, no). No era el reloj ni el orden: era una espera que no esperaba nada. Ahora espera
  la URL del detalle y su encabezado (`h2` con el nombre). Comprobado con el `POST` demorado 500 ms a mano: el test viejo falla
  ("…/nueva") y el nuevo pasa. El archivo, 5 corridas seguidas: 26 de 26 en cada una.
* **Mutaciones** (3, cada una con sus tests relacionados y revertida con `git checkout`): el sitio vacío mandado como `''` en lugar
  de `null` (1 falla); reemplazar el logo con `invalidateQueries` (sobrevivió; detectada después del hallazgo 2, 1 falla); el límite
  justo rechazado (`>=` en `image.ts`: 6 fallas, del logo y de la foto).
* **Corrida final**, desde `frontend/`: `typecheck` y `build` sin errores ni avisos; `lint` marcó una aserción de tipo en
  `MainMenu.test.tsx`, corregida, y se repitieron `lint` y ese archivo (7 de 7); `npm test`, 1251 de 1251 en 60 archivos, en verde;
  desde `backend/`, `FictitiousDocumentsTest`, 3 de 3. Playwright (el menú a 375 px está en su caso):
  `e2e/configuracion-clinica.spec.ts`, 1 de 1.

**Validación.** El usuario aceptó los 10 desvíos, pidió la revisión y, con ella, el cierre (R-081). El desvío 10 de la 2.4 (a 375 px el menú se salía de la pantalla) queda resuelto con el menú plegable, validado en Edge a 375 y 768 px y en Playwright a 375 px. Evidencia: Claude Code, 5 de 5
en Edge sin ventana contra `syncvet.test` (arriba, en la implementación; la demo quedó como estaba); Playwright,
`e2e/configuracion-clinica.spec.ts`, 1 de 1 en la implementación y de nuevo después de la corrección del menú (28 casos en total: el
teléfono visto después de recargar, el cuerpo sin `ruc`, el logo subido, visto en la barra después de recargar, reemplazado y
quitado, y el menú a 375 px). Corrida del cierre (2026-11-17, en la revisión, sobre el código de `26e4339`), desde `frontend/`:
`typecheck` y `build` sin errores ni avisos; `lint`, en verde después de corregir una aserción de un test (repetidos `lint` y ese
archivo); `npm test`, 1251 tests en 60 archivos, en verde; desde `backend/`, `FictitiousDocumentsTest`, 3 de 3.

Corrida de `npm test` del frontend (2026-11-17): 1251 tests en 60 archivos, en verde.

### Entrega 2.6

Estado: **COMPLETADO** el 2026-11-17 (R-082). La auditoría (listado con los filtros de fecha en Asunción y el detalle de los
cambios), solo para el administrador y de solo lectura, en `feature/front-2-6-auditoria` (desde `feature/front-2-5-configuracion`,
en el cierre de la 2.5, `1af859b`), sin integrar a `main` (se integra al cerrar la F2). Commits: `120739f` (WIP de la
implementación), `9a17e41` (WIP de la revisión) y el de este cierre (R-082: la documentación).

**Implementación** (el 2026-11-17). Antes de empezar se
comparó el diseño con la API real (`IndexAuditLogRequest`, `ApiDateTime::FILTER_FORMAT`, `AuditLogResource`,
`ShowAuditLogResource`, `AuditEvent`, `AuditLog::AUDITABLE_TYPES`, `AuditLogger::MASKED` y los permisos): coinciden.

* **Hecho.** `features/audit` (nuevo): `/configuracion/auditoria` (`audit-logs.index`) con su tarjeta "Auditoría" en
  `/configuracion`, y `/configuracion/auditoria/$id` (`audit-logs.show`, con `loader`, `notFound()` y `key={id}`); solo lectura.
  El listado: fecha y hora en Asunción (`formatDateTime`, enlace al detalle), el usuario (enlace que filtra por él; "—" sin
  usuario), el evento, el registro ("Cliente n.º 12", enlace que filtra por el tipo y el número) y la IP; los filtros en la URL
  (`?desde=&hasta=&usuario=&evento=&entidad=&registro=&buscar=&pagina=`), validados en `validateAuditListSearch` con todas sus
  claves; `desde` y `hasta` suman a `TEXT_SEARCH_PARAMS`; la búsqueda con `useSearchText` (300 ms); el usuario con
  `GET users?include_inactive=1` y uno que la lista no trae como "Usuario n.º …"; "Esta página no existe." fuera de rango. Las
  fechas (`features/audit/dates.ts`): en la URL, la hora de pared de Asunción (`2026-11-09` o `2026-11-09T13:00`); a la API, en
  UTC con `TZDate` (nunca la zona de la PC): la fecha sola es el día completo ("desde" a las 00:00:00 y "hasta" a las 23:59:59, el
  comienzo del día siguiente menos un segundo, así también en un día de 23 o 25 horas) y con hora, "desde" con :00 y "hasta" con
  :59. "Desde", "Hasta" (con su hora) y el n.º de registro se aplican con "Filtrar" (`filters.ts`: las reglas de la API con sus
  mensajes, en el campo y con el foco, sin cambiar la URL ni pedir con un error). El detalle: la fecha con segundos
  (`formatDateTimeSeconds`, nuevo en `lib/format/dates.ts`), el usuario, el evento, el registro, la IP, el navegador y los cambios
  (el campo con el nombre de la API; "antes" y "después" según los lados que manda la API; `null` y `''`, "(vacío)"; una lista o
  un objeto, su JSON en un `<pre>`; todo como texto). `maskAuditValues` (`features/audit/mask.ts`) cambia por `********` el valor
  de una clave con `password` o `token` y por `"(archivo)"` el de una clave `*_path` o un texto `clinics/{id}/…`, en cualquier
  nivel. `PERMISSIONS` suma `audit-logs.index` y `.show`.
* **Pruebas.** Vitest: `audit/dates.test.ts` (31: el día completo, con hora, "hasta" con :59, el invierno de 2024 en UTC-4, el
  día de 25 horas del 23/03/2024 y el de 23 horas del 01/10/2023, las formas inválidas y la comparación de la hora de pared),
  `audit/mask.test.ts` (8), `audit/search.test.ts` (24: `validateSearch`, el pedido, el formulario con los mensajes comparados con
  `IndexAuditLogRequest.php` y las listas de eventos y tipos comparadas con `AuditEvent.php` y `AuditLog.php`) y
  `audit/AuditPages.test.tsx` (17, con MSW: la tarjeta, el recepcionista y el veterinario a `/sin-permiso` sin pedir la
  auditoría, la fila en Asunción, el pedido con la fecha sola y con hora, "hasta" antes de "desde" sin pedido, el registro sin el
  tipo, los filtros de la URL, los enlaces de la fila, la espera de 299 y 300 ms con `?buscar=1e3`, la página fuera de rango y el
  detalle de `updated`, `created` (con contraseña, token, ruta y HTML que la API dejara pasar), `deleted`, un ingreso, el 404 y la
  vuelta al listado con los filtros). Ningún test existente se tocó. Mutaciones: 4 de 4 detectadas, revertidas desde una copia
  fuera del repositorio (los archivos son nuevos): "hasta" sin :59 (4 fallas); la conversión con la zona de la PC (`new Date`
  local en lugar de `TZDate`: 12 fallas); `maskAuditValues` sin recorrer lo anidado (2 fallas); el detalle sin `maskAuditValues`
  (1 falla). Playwright: `e2e/auditoria.spec.ts` (nuevo), 1 de 1 en 30,5 s (29 casos en total): cambiar las notas de Carlos Duarte,
  filtrar la auditoría por hoy (solo fecha, el pedido en UTC de 03:00:00Z a 02:59:59Z), el tipo cliente y su n.º, abrir el detalle
  con las notas antes y después, volver con los filtros y, con "desde" un minuto después del cambio, la lista vacía; las notas
  quedan como estaban. Corrida final, desde `frontend/`: `typecheck`, `lint` y `build` sin errores ni avisos; `npm test`, 1331 de
  1331 en 64 archivos, en verde (en la primera corrida completa); desde `backend/`, `FictitiousDocumentsTest`, 3 de 3.

| # | Desvío | Motivo |
|---|---|---|
| 1 | La fecha sola no se manda tal cual (`2026-11-09`): el frontend arma el día completo de Asunción con `TZDate` y lo manda como dos instantes | Lo pidió el usuario al empezar la entrega; la API da el mismo resultado (en Edge, el 16/11: 445 registros en la pantalla y 445 contados por su `created_at` en Asunción) |
| 2 | Las fechas con hora van en UTC (`2026-11-09T16:00:00.000Z`), no con `toApiDateTime` (`-03:00`) | El mismo instante; el pedido de la entrega decía "la conversión a UTC". `toApiDateTime` no cambió |
| 3 | El tipo del registro va en la URL como `entidad`, no como `tipo` | `tipo` ya es de `/clientes` (`persona`, `empresa`) y el router junta las búsquedas de todas las rutas en los enlaces sin `from`: con `tipo`, `typecheck` fallaba en `ClientListPage.tsx`. `entidad` no toca otros módulos |
| 4 | "Desde", "Hasta", sus horas y el n.º de registro se aplican con "Filtrar"; la búsqueda, el usuario, el evento y el tipo cambian la URL en el acto | Una fecha a medio elegir no es un filtro, y así las reglas (la forma, "hasta" antes de "desde", el registro sin el tipo) se muestran antes de cambiar la URL y sin pedir nada |
| 5 | Con la URL editada, "hasta" anterior a "desde" se descarta en `validateSearch` (queda "desde"), sin un mensaje | La regla de los listados: un valor inválido de la URL vuelve al valor por defecto |
| 6 | `maskAuditValues`: `null` queda `null` ("(vacío)"); una clave con `password` o `token` en cualquier parte del nombre y sin distinguir mayúsculas (`api_token`, `password_confirmation`); una lista o un objeto bajo esa clave se tapa entero | Un valor vacío no tiene nada que ocultar; el resto, para tapar también lo que la API no lista hoy |
| 7 | Sumados: "Quitar los filtros" (con algún filtro) y los usuarios inactivos marcados "(inactivo)" en el selector. Los nombres de los tipos en castellano son del frontend (la API manda solo el tipo corto): el test compara con el PHP solo los valores | Comodidad del listado; sin endpoint de tipos (H2) |
| 8 | Playwright no tiene el caso "el alta de un usuario en la auditoría sin su contraseña" del diseño | El pedido de la entrega era un caso; lo cubren el test de la página con MSW (una contraseña, un token y una ruta que la API dejara pasar) y el control 3 en Edge (un registro real con `password` en `********`) |
| 9 | En Edge, el control 1 falló en la primera corrida por el script (bajó los 2000 registros más recientes y eligió un día del que tenía solo una parte: 159 de 1016); se corrigió el script para elegir un día completo y se repitió entero, 8 de 8 | Una falla del script, no de la pantalla |
| 10 | Después de la corrida final, en Edge, "Todos los eventos" se veía cortado a 1280 px: los selectores pasaron a `col-lg-3` y se repitieron Prettier, ESLint y los tests relacionados de `AuditListPage.tsx` (531 de 531 en 25 archivos), más un control en Edge (el 9). A 1280 px, el texto de ejemplo de la búsqueda queda cortado al final ("…o el emai"); la etiqueta accesible está completa | La regla del presupuesto: solo el paso y los tests del archivo tocado |

**Validación en Edge** (sin ventana, contra `syncvet.test` con Vite en `:5180`, detenido por su PID): 9 de 9, de solo lectura. (1)
fecha sola en el borde del día: el 16/11, 445 registros en la API y 445 contados por su `created_at` en Asunción, pedido de
`2026-11-16T03:00:00.000Z` a `2026-11-17T02:59:59.000Z`; (2) con hora: "desde" y "hasta" a las 10:52, el registro de las
10:52:58 incluido (`to` con :59); (3) un detalle con `password` en `********`, sin un hash en la página; (4) un detalle con
`roles` (listas) en `<pre>`, sin desbordes; (5) a 375 px, el listado y el detalle sin desplazamiento horizontal de la página (la
tabla se desplaza dentro de su tarjeta, como los demás listados); (6) la URL con filtros recargada: los campos con lo de la URL;
(7) la consola sin errores; (8) el recepcionista: el listado y el detalle a `/sin-permiso`; (9) los selectores sin cortar a 1280 px,
después del desvío 10. La demo no cambió: no se escribió nada (los ingresos y salidas del script quedan en la auditoría, como en
las validaciones anteriores; su token de la API se revocó); `admin-a`, `recepcion-a` y `vet-a`, sin tocar. Capturas fuera del
repositorio, en la PC de la validación (`%TEMP%\claude\…\scratchpad\capturas-2-6`: `01-listado-fecha-sola.png`,
`01b-listado-escritorio.png`, `02-listado-con-hora.png`, `03-detalle-sensibles.png`, `04-detalle-lista.png`,
`05-celular-listado.png`, `05b-celular-detalle.png`).

**Revisión** (el 2026-11-17, sobre el WIP `120739f`, diff con `1af859b`; el usuario aceptó los 10 desvíos). Se revisaron las
fechas (la fecha sola como día completo de Asunción, "hasta" con :59, los días de 23 y 25 horas, todo con `TZDate` y nunca con la
zona de la PC), que una fecha inválida o "desde" posterior a "hasta" no llegue a la API (ni desde el formulario ni desde la URL),
`maskAuditValues` (claves sensibles en cualquier nivel y en listas, sin distinguir mayúsculas; en los modelos de hoy, ninguna clave
no sensible tiene `password`, `token` o `_path`: las únicas son `password`, `remember_token`, `token`, `photo_path`, `kude_path`,
`pdf_path` y `xml_path`), que todo valor se muestre como texto (React lo escapa; JSON en `<pre>`), los filtros en la URL (recarga,
"Atrás" con el formulario vuelto a montar por `key`, página fuera de rango, la espera de la búsqueda con `useSearchText`, que se
cancela al navegar a otra página), `entidad` sin chocar con `tipo` de `/clientes`, los permisos (las dos rutas con
`requirePermission`, la tarjeta con `audit-logs.index`, el ítem "Configuración" del menú solo para el administrador) y 375 px.
Ningún hallazgo cambia el comportamiento.

* **Corregido.** (1) Desvío 10, el texto de ejemplo de la búsqueda: "Buscá por el nombre o el email del usuario" se cortaba; a
  1280 px el campo deja 147 px de texto, y "Buscá por nombre o email" (163 px) también se cortaba, así que quedó
  **"Nombre o email"** (101 px a 1280 y 115 de 203 a 375; la etiqueta accesible sigue "Buscar en la auditoría"). (2) A 375 px,
  "Filtrar" quedaba alineado con la etiqueta del n.º de registro y no con el campo (el ajuste de `.audit-filter-submit` era solo
  desde 768 px; en un celular comparte la fila con ese campo): ahora vale en todos los anchos (`styles.css`).
* **Mutaciones** (3 de 3 detectadas, revertidas con `git checkout`): las claves sensibles distinguiendo mayúsculas (`mask.test.ts`,
  1 falla); `registro` sin `entidad` aceptado en la URL (`search.test.ts`, 4 fallas); "hasta" con fecha sola como las 23:59:59 de
  ese día en lugar del comienzo del siguiente menos un segundo (`dates.test.ts`, 4 fallas: el día de 25 horas).
* **En Edge** (sin ventana, contra `syncvet.test` con Vite en `:5180`, detenido por su PID; 3 controles): el texto de ejemplo
  medido a 1280 y a 375 px, sin desplazamiento horizontal de la página, y "Filtrar" alineado a 375 px. Cada control ingresó y
  salió como `admin-a` (quedan en la auditoría); el primer intento falló por el script (esperaba visible el nombre de la barra,
  oculto a ese ancho) antes de salir, así que ese token queda hasta su vencimiento (12 horas). Capturas fuera del repositorio
  (`%TEMP%\claude\…\scratchpad`: `revision-buscador-1280.png`, `revision-buscador-375.png`).
* **Pruebas.** `vitest related` de `AuditListPage.tsx` y `styles.css`: 531 de 531 en 25 archivos. Corrida final, desde
  `frontend/`: `typecheck`, `lint` y `build` sin errores ni avisos; `npm test`, 1331 de 1331 en 64 archivos, en verde; desde
  `backend/`, `FictitiousDocumentsTest`, 3 de 3. Playwright no se corrió: `auditoria.spec.ts` no usa el texto de ejemplo y corre a
  ancho de escritorio.

**Validación.** El usuario aceptó los 10 desvíos, pidió la revisión y, con ella, el cierre (R-082). Evidencia: Claude Code, 9 de
9 en Edge sin ventana contra `syncvet.test` en la implementación y 3 controles en la revisión (de solo lectura; solo los ingresos
y las salidas quedan en la auditoría); Playwright, `e2e/auditoria.spec.ts`, 1 de 1 en la implementación (29 casos en total; la revisión no tocó lo que cubre).
Corrida del cierre (2026-11-17, en la revisión, sobre el código de `9a17e41`), desde `frontend/`: `typecheck`, `lint` y `build`
sin errores ni avisos; `npm test`, 1331 tests en 64 archivos, en verde; desde `backend/`, `FictitiousDocumentsTest`, 3 de 3.

Corrida de `npm test` del frontend (2026-11-17): 1331 tests en 64 archivos, en verde.

### Cierre de la F2

**COMPLETADA el 2026-11-17 (R-083)**, con la entrega de soporte del backend (front-02). Las ramas se encadenaron
(`docs/front-f2-diseno` → `feature/front-2-1-sucursales` → `feature/front-2-2a-usuarios` → `feature/front-2-2b-usuarios` →
`feature/front-02-usuarios-veterinario` → `feature/front-2-3-veterinarios` → `feature/front-2-4-servicios` →
`feature/front-2-5-configuracion` → `feature/front-2-6-auditoria`), todas en GitHub, y se integran a `main` con un merge `--no-ff`
desde `feature/front-2-6-auditoria`, autorizado por el usuario (R-083); el hash del merge, en el
[handoff](../../handoff.md#punto-de-continuidad). Las ramas se conservan.

**Qué se construyó.** Las pantallas de administración de la clínica, sobre la API de la FASE 2 del backend y con las convenciones de
la F0 y la F1: `/configuracion` como página de entrada, con una tarjeta por sección, solo para el administrador; sucursales y sus
depósitos (2.1); usuarios, con sus roles, sus sucursales y la contraseña de otro usuario (2.2a), y su inactivación, reactivación y
baja (2.2b); `/veterinarios` (2.3) y `/servicios` (2.4) en el menú de los tres roles, con las acciones del administrador; la
configuración de la clínica con el logo, el logo en la barra y el menú plegado por debajo de 992 px (2.5); la consulta de la
auditoría con los filtros de fecha en Asunción y el detalle de los cambios (2.6). Código común nuevo: `useSingleAction`
(`src/lib/hooks`), `dialogError` (`src/lib/api/dialog-error.ts`), `CheckboxGroupField`, `src/lib/phone.ts`,
`src/lib/person-name.ts`, `src/lib/files/image.ts`, `MainMenu` y `formatDateTimeSeconds`. Sin paquetes nuevos. Del lado del
backend, solo front-02 (la ficha de veterinario en `GET users`, sin N+1). En la cadena, contra `main`: 154 archivos de `frontend/`
(33 de tests de Vitest y de Playwright) y 7 de `backend/` (`UserController`, `UserResource`, `ShowUserResource`, `backend/CLAUDE.md`
y 3 de tests). Rutas, permisos y migraciones de la API sin cambios: 78, 135 y 36.

| Entrega | Contenido | Commits | Al cerrar |
|---|---|---|---|
| Diseño | Análisis y diseño de la F2 y de front-02 | `beaa2c4`, `16aec1d` (R-074) | — |
| [2.1](#entrega-21) | Sucursales y depósitos, con la tarea 3c | `7de78f3`, `7e27822` (3c), `626759b`, `b30db49`, `a31b6ef` (cierre `0f7b80b`) | Frontend: 909 tests en 46 archivos |
| [2.2a](#entrega-22a) | Usuarios: listado, detalle, alta y edición | `fff201c`, `e8ecdeb` (cierre `0bf3ed0`) | Frontend: 982 tests en 49 archivos |
| [2.2b](#entrega-22b) | Usuarios: inactivar, reactivar y baja | `2cd6dd4`, `3265903` (cierre `85ebf3a`) | Frontend: 996 tests en 49 archivos |
| [front-02](#entrega-front-02) | La ficha de veterinario en `GET users` | `bf98d8d`, `f0218e6` (cierre `0ab4dc7`) | Backend: 1467 casos; 14303 aserciones en SQLite |
| [2.3](#entrega-23) | Veterinarios | `fcf6667` (`dialogError`), `72975a6`, `359b2d7` (cierre `0743a72`) | Frontend: 1076 tests en 53 archivos |
| [2.4](#entrega-24) | Servicios | `0686f94`, `b0bab2b` (cierre `37ac3cf`) | Frontend: 1188 tests en 56 archivos |
| [2.5](#entrega-25) | Configuración de la clínica y logo, con el menú plegable | `11dabf4`, `26e4339` (cierre `1af859b`) | Frontend: 1251 tests en 60 archivos |
| [2.6](#entrega-26) | Auditoría | `120739f`, `9a17e41` (cierre `bbe6e20`) | Frontend: 1331 tests en 64 archivos |

**Regresión del cierre** (2026-11-17, en `feature/front-2-6-auditoria` sobre `bbe6e20`, más el ajuste de Playwright de este
cierre), una vez cada paso y en este orden:

* **Backend**, desde `backend/`, en serie y sin cambios de código: `composer test` (SQLite), 1467 casos, 1451 en verde y 16
  omitidos (solo de MySQL), 14303 aserciones (5 min 27 s); después `composer test:mysql` (`syncvet_test_mysql`), 1467 casos en
  verde, sin omitidos, 14389 aserciones (6 min 36 s). Frente al cierre de la F1 (1463 casos; 14274 y 14360 aserciones), 4 casos
  más, los de front-02.
* **Newman** 6.2.3, desde la raíz, las 14 colecciones de `docs/05-pruebas/postman` (R-057), con el servidor levantado desde
  `backend/` en el puerto 8001 contra la base de desarrollo (detenido por su tarea, con el puerto libre después): 487 pedidos y
  1082 aserciones, sin fallas (tabla de abajo; desvío 2).
* **Frontend**, desde `frontend/`: `npm test`, 1331 de 1331 en 64 archivos (74 s); `npm run typecheck`, `npm run lint` y
  `npm run build`, sin errores ni avisos; desde `backend/`, `FictitiousDocumentsTest`, 3 de 3.
* **Playwright** (`npm run test:e2e`, Edge, contra `syncvet_e2e`): la primera corrida, 26 de 29 (desvío 1); con el test
  ajustado, 29 de 29 en 7,2 min, y `typecheck`, `prettier` y `eslint` del archivo tocado, sin errores.
* **Dependencias**: `composer audit`, "No security vulnerability advisories found."; `npm audit`, 9 avisos moderados, todos el
  mismo, `minimatch` (GHSA-g3hv-75wg-m249; [deuda 19](../../deuda-tecnica.md), sin cambios).

| # | Desvío | Motivo |
|---|---|---|
| 1 | `e2e/permisos.spec.ts`: los tres casos del menú esperaban el menú sin "Servicios" y "Veterinarios"; se suman, en el orden de `src/app/menu.ts` (el de `permissions.test.tsx` de Vitest) | La 2.3 y la 2.4 sumaron los ítems al menú y, con el presupuesto por entrega, solo corrieron su spec: el caso de la F0 quedó viejo, como el desvío 1 del cierre de la F1. Solo el test |
| 2 | Newman: dos intentos fallidos del script de la corrida antes de la vuelta válida. El primero, con `base_url` sin `/api`: todos los pedidos respondieron 404 de ruta, sin cambiar nada; el segundo no llegó a correr Newman (la carpeta de salida era relativa). La vuelta registrada es la tercera, completa | Error del script, no de la API ni de las colecciones |

| Colección | Pedidos | Aserciones | Fallas |
|---|---|---|---|
| Clientes | 24 | 48 | 0 |
| Especies | 45 | 99 | 0 |
| Foto | 23 | 60 | 0 |
| Pacientes | 30 | 63 | 0 |
| Auditoría | 16 | 47 | 0 |
| Configuración | 24 | 67 | 0 |
| Servicios | 27 | 64 | 0 |
| Sucursales | 44 | 123 | 0 |
| Usuarios | 54 | 138 | 0 |
| Veterinarios | 32 | 75 | 0 |
| Agendas | 35 | 68 | 0 |
| Disponibilidad | 50 | 81 | 0 |
| Series | 25 | 42 | 0 |
| Turnos | 58 | 107 | 0 |

Frente al cierre de la F1 (483 pedidos y 1074 aserciones): especies, 4 pedidos y 6 aserciones más (la limpieza de
`chore/post-f1`, R-073), y usuarios, 2 aserciones más (la ficha de veterinario de front-02).

**Datos de la demo.** Antes de la regresión se borró el token que dejó abierto la revisión de la 2.6 (id 604, `admin-a`, creado el
2026-11-17 a las 11:20:34 y sin usar), solo ese. Después de Newman se revocaron los 36 tokens que abrió esa corrida (ids 605 a
651); las colecciones dejan, como siempre, sus usuarios temporales inactivos o dados de baja. `admin-a`, `recepcion-a` y `vet-a`,
sin cambios (la misma contraseña y la misma fecha de modificación).

**Criterio de cierre**, punto por punto:

| Criterio | Evidencia |
|---|---|
| Las entregas aprobadas cerradas | 2.1, 2.2a, 2.2b, front-02, 2.3, 2.4, 2.5 y 2.6: R-075 a R-082, cada una con su revisión, sus correcciones y la validación del usuario ([Resultado](#resultado)) |
| Vitest en verde | `npm test`, 1331 de 1331 en 64 archivos (corrida de este cierre) |
| Playwright en verde | `npm run test:e2e`, 29 de 29, con el desvío 1 de este cierre (solo en el test) |
| `typecheck`, `lint` y `build` sin errores ni avisos | Sin errores ni avisos (corridas de este cierre; `typecheck` repetido con el test de Playwright tocado) |
| Por front-02: `composer test` y la suite en MySQL, en serie | SQLite, 1467 casos y 14303 aserciones; MySQL, 1467 casos y 14389 aserciones |
| Newman de todas las colecciones, sin fallas | Las 14: 487 pedidos y 1082 aserciones, sin fallas |

**Deudas** ([`deuda-tecnica.md`](../../deuda-tecnica.md)). La F2 no abrió ni resolvió ninguna; la 21 sigue (front-02 llevó
`countQueries` a `Tests\Concerns\InspectsQueries`, pero las tres copias privadas siguen). Abiertas al cerrar: del backend, 2, 3, 5,
6, 15, 17 y 21; del frontend, 19 y 20.

**Decisiones de la fase.** R-074 (diseño de la F2 y de front-02, con las 13 preguntas respondidas con la recomendación), R-075 a
R-082 (el cierre de cada entrega) y R-083 (este cierre y el merge), en el
[registro](../../../01-arquitectura/decisiones/registro.md).

**Pendiente para la F3** (agenda y turnos, sobre la API de la FASE 3 del backend: agendas con su horario semanal y sus bloqueos,
turnos únicos y en series, sus estados, la disponibilidad y la cola del día): el análisis y el diseño, con sus preguntas, antes de
cualquier código, en `docs/front-f3-diseno` (desde `main` en el merge de la F2). Lo que la F2 deja preparado: las consultas de
sucursales, veterinarios y servicios (el `api.ts` de `features/branches`, `features/veterinarians` y `features/services`), para los
selectores de la agenda y del turno; las claves de la sucursal actual (`branchKey`, `src/lib/api/query-keys.ts`); los filtros de
fecha y hora en Asunción (`features/audit/dates.ts`) y `dialogError` para las acciones con 409.

Corrida de `composer test` del backend (2026-11-17): 1467 casos, 1451 pasan y 16 omitidos, 14303 aserciones, en verde.

Corrida de `composer test:mysql` del backend (2026-11-17): 1467 casos, 1467 pasan, 14389 aserciones, en verde.

Corrida de Newman (2026-11-17): 14 colecciones, 487 pedidos y 1082 aserciones, sin fallas.

Corrida de `typecheck`, `lint` y `build` del frontend (2026-11-17): sin errores ni avisos.

Corrida de `FictitiousDocumentsTest` (2026-11-17): 3 de 3, en verde.

Corrida de Playwright (2026-11-17): 29 de 29, en verde.

Corrida de `composer audit` (2026-11-17): sin avisos.

Corrida de `npm audit` (2026-11-17): 9 avisos moderados, todos de `minimatch` (deuda 19).

Corrida de `npm test` del frontend (2026-11-17): 1331 tests en 64 archivos, en verde.
