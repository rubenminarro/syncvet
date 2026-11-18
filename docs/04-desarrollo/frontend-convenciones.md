# Convenciones del frontend

El detalle de las convenciones del frontend (antes, `frontend/CLAUDE.md` §5; las reglas duras, en una línea cada una, siguen en
su [§5](../../frontend/CLAUDE.md#5-convenciones)). Si una convención contradice al código, manda el código.

* **Textos en español con voseo** ("Ingresá tu email", "Elegí tu sucursal"). El `message` de la API ya viene así: se muestra tal
  cual, nunca se reescribe. `src/test/voseo.test.ts` revisa las cadenas y el texto de JSX de `src/` con las listas de `VoseoTest` del
  backend: un verbo nuevo en un texto se suma a su lista.
* **Decidir por `error_code`**, nunca por el texto ni solo por el HTTP: la [tabla](../../frontend/CLAUDE.md#6-qué-hace-la-interfaz-con-cada-código-de-error) vive
  en `src/lib/api/errors.ts` (`errorTreatment`, `userMessage`).
* **Toda llamada a la API pasa por `src/lib/api/client.ts`** (`api.get`, `api.post`…, `getPage` para los listados): pone
  `Accept: application/json` y el token y convierte cualquier falla en un `ApiError` (`status`, `errorCode`, `message`, `errors`,
  `retryAfter`). Un 204 (o un 2xx sin cuerpo) es un éxito sin datos: `data` es `null` (el endpoint se tipa con `T = null`); un
  2xx con un cuerpo que no es JSON es `HTTP_ERROR`. Una cancelación (la señal abortada) se propaga tal cual, no es
  `NETWORK_ERROR`. ESLint prohíbe en el resto del código `fetch` (también `window.fetch`, `globalThis.fetch` y `self.fetch`) y
  `XMLHttpRequest`, y en todo el código `dangerouslySetInnerHTML`.
* **Lecturas con `useQuery` y cambios con `useMutation`**, con las claves en el `api.ts` de cada módulo. `createQueryClient()`
  (`src/lib/api/query.ts`): una lectura se reintenta una vez solo con una falla de red o un 5xx; una mutación, nunca.
* **Claves de las consultas** (`src/lib/api/query-keys.ts`, desde la 0.2b): `['me']` es el usuario de la sesión; una consulta
  cuyos datos dependen de la sucursal actual arma su clave con `branchKey(idDeLaSucursal, …)`, así los datos de dos sucursales no se
  mezclan y el cambio de sucursal invalida solo esas (`invalidateBranchQueries`); las demás (catálogos, clientes, pacientes: son
  de la clínica) empiezan con su módulo (`['clients', …]`) y no se tocan al cambiar de sucursal. Precisión (R-085, D3 y D4 del
  diseño de la F3): en la API, solo la **cola del día** usa la sucursal actual del usuario; `GET appointments` trae toda la clínica
  salvo `branch_id`. La agenda y la lista de turnos mandan `branch_id` (la sucursal actual de la barra, la que muestran) y llevan
  `branchKey` por eso (`branchKey(10, 'appointments', 'range', { from, to })`). La clave de la cola suma todos sus filtros:
  `['branch', 10, 'appointments', 'queue', { date, all, veterinarian_id, schedule_id }]`. El detalle de un turno no lleva
  `branchKey` (`['appointments', 'detail', id]`).
* **Listados** (desde la 1.1; el modelo es `/clientes`, `features/clients/ClientListPage.tsx`, y sus tests):
  * **Búsqueda, filtros y página en la URL** (`/clientes?buscar=&tipo=&pagina=`): la ruta los valida en `validateSearch` con Zod
    (`features/clients/search.ts`); un valor inválido vuelve al valor por defecto, y los valores por defecto no van en la URL.
    `validateSearch` devuelve **siempre todas sus claves** (`undefined` si el valor se descarta): el router mezcla lo que
    devuelve sobre la búsqueda cruda, así que una clave que falta deja pasar el valor de la URL.
  * **Lo escrito es siempre texto en la URL**: el router lee cada valor como JSON (`?buscar=1e3` sería el número 1000); las
    claves de `TEXT_SEARCH_PARAMS` (`src/app/search-params.ts`, el `parseSearch` y el `stringifySearch` del router) se leen y
    se escriben tal cual. `buscar` ya está y vale para todos los listados; otro parámetro de texto se suma a esa lista (desde la 2.6, `desde` y
    `hasta` de la auditoría). Un parámetro nuevo no reutiliza el nombre de otro listado con otros valores: el router junta las
    búsquedas de todas las rutas en los enlaces sin `from` (por eso el tipo de la auditoría es `entidad` y no `tipo`, que es de
    `/clientes`).
  * **Una lista en la URL** (desde la 3.3, los `estados` de `/turnos`): su clave va en `LIST_SEARCH_PARAMS`
    (`src/app/search-params.ts`), que la lee siempre como una lista de textos (la primera aparición, sin los vacíos) y la escribe
    separada por comas, sin JSON (`?estados=confirmado,fichado`); una lista vacía no va. El `validateSearch` descarta los valores
    desconocidos y los repetidos y la ordena como sus casillas; ninguno válido es `undefined`. A la API va como `clave[]`, una vez
    por elemento (`buildUrl` de `client.ts`: `status: ['confirmed']` → `status[]=confirmed`; una lista vacía no va). Una casilla
    se aplica sobre la búsqueda más reciente (`navigate` con función), para que dos clics seguidos no pierdan uno.
  * **El campo de búsqueda es local** (`SearchField`, con el hook común `useSearchText` de `src/lib/hooks/use-search-text.ts`, desde
    la 1.3a: lo usan `/clientes` y `/pacientes`) y cambia la URL 300 ms después de la última tecla (`useDebouncedValue`,
    `src/lib/hooks/`, solo con primitivos), sin los espacios de los extremos, con `replace: true` y de vuelta en la página 1; el
    filtro y la página cambian la URL con historial ("Atrás" vuelve). El campo toma lo de la URL **solo si el cambio vino de
    afuera** ("Atrás", un enlace): no si es lo que el campo mandó y todavía no llegó, ni si coincide con lo escrito sin los
    espacios de los extremos (así no se pierden el espacio de "Laura " ni la tecla escrita mientras la URL cambiaba). Si sale una
    navegación a otra página (un clic en un resultado) mientras corre la espera, la espera se cancela y la URL no cambia (deuda 23,
    R-073): `useSearchText` compara la ruta de `router.latestLocation`, que cambia apenas sale la navegación, con la del listado.
  * **La consulta** usa `placeholderData: keepPreviousData` (una respuesta vieja no pisa a la nueva: cada búsqueda tiene su
    clave) y 20 por página, fijo. Mientras llega, `SearchField` recibe `busy`: el indicador y "Buscando…" en su región
    `role="status"`, que está siempre en el DOM.
  * **Página fuera de rango** (`current_page > last_page`, por una URL editada o después de borrar): `DataTable` muestra "Esta
    página no existe." con el `firstPageLink` del listado (un `<Link>` con la misma búsqueda, sin `pagina`), no el vacío.
  * **Tests**: la espera con relojes falsos y los 300 ms escritos en el test (299 ms sin URL ni pedido, 300 ms con uno); el
    espacio final ("Laura ", pausa, "M"), la tecla durante la navegación, la respuesta fuera de orden, "Buscando…", la página
    fuera de rango, `?buscar=1e3` y el clic en un resultado durante la espera (R-073).
  * Un **detalle** valida su id en el `loader` (sin llamar a la API si no es un entero positivo) y convierte el 404 de la API en
    `notFound()`, con el `notFoundComponent` de la ruta, dentro del layout.
* **Contraseñas fuera de la caché de mutaciones** (0.2b): una mutación que manda una contraseña (`/ingresar`, `/perfil`) va con
  `gcTime: 0` y `mutation.reset()` en `onSettled`, para que sus variables no queden en la caché de Query. `reset()` descarta los
  callbacks de `mutate()`: los errores se tratan en las opciones de `useMutation`, con lo que necesitan (el formulario) en las
  variables (desde la 2.2a, `features/users/UserFormPages.tsx`); un test del error comprueba que la contraseña no quedó en la
  caché (con un éxito que navega, la página se desmonta y la mutación se borra igual, así que no alcanza).
* **Tipos escritos a mano** en el `types.ts` del módulo, con los nombres de la API (`snake_case`, también `meta.pagination`:
  `per_page`, `current_page`, `last_page`); no se traducen a `camelCase`.
* **Formularios**: React Hook Form con Zod; un 422 se vuelca con `applyValidationErrors` (`src/lib/api/form-errors.ts`): cada clave
  de `errors` a su campo con `setError` y foco en el primero; las demás, a una `Alert` arriba del formulario. Las claves con índice
  de Laravel (`branch_ids.0`) van al campo padre declarado (`branch_ids`), o al de un patrón con `*` (`hours.*.starts_at` recibe
  `hours.1.starts_at`); varias claves de un mismo campo juntan sus mensajes. Desde la 1.2 (el modelo es `features/clients/form.ts`,
  `ClientForm.tsx` y `ClientFormPages.tsx`):
  * **Las reglas de forma de la API, con sus mensajes**, en una función pura con sus tests (cada límite en su borde: pasa en el
    límite y falla pasándolo), comparadas con el Form Request; el largo en caracteres (`Array.from`, como `mb_strlen`). El
    navegador no rechaza nada que la API acepta: el email, solo la forma mínima (`^[^\s@]+@[^\s@]+$`); el formato completo y el
    DNS, la API.
  * **Un solo envío**: Guardar se deshabilita con `formState.isSubmitting` y con la mutación pendiente, y una `ref` deja pasar un
    solo `mutate` a la vez (se suelta en `onSettled`): un doble clic o dos envíos del formulario mandan un solo pedido. Esa `ref`
    es el hook común `useSingleAction` (`src/lib/hooks/use-single-action.ts`, desde la 2.1): `tryStart()` antes de `mutate` y
    `release()` en `onSettled`; también en los diálogos con una acción.
  * **El teléfono** (desde la 2.1): la regla de clientes es común (`src/lib/phone.ts`: `PHONE_PATTERN`, `PHONE_MESSAGE` y
    `normalizePhone`, que quita los espacios y guiones escritos antes de validar y mandar); la usan clientes y sucursales.
  * **Los nombres de personas** (desde la 2.2a): `PersonName` de la API es común (`src/lib/person-name.ts`:
    `PERSON_NAME_PATTERN`); la usan clientes y usuarios.
  * **El largo en caracteres, como la API** (desde la 1.3a): los textos que admiten letras fuera del plano básico (`\p{L}`, o sin
    regla de caracteres) no llevan `maxLength` nativo, que cuenta unidades UTF-16 (un emoji es 2); el largo lo controla Zod con el
    mensaje de la API. Lo conservan los campos de solo caracteres de una unidad (documento, email, microchip).
  * **Una fecha a medio escribir** (`DateField`, desde la 1.3a): el `<input type="date">` da `''` con `validity.badInput`; al
    enviar se lee del campo y se muestra el error de formato de la API, sin mandar (nunca se borra una fecha guardada sin aviso).
  * **Los opcionales vacíos van en `null`** y la edición manda el formulario completo; guardar y "Cancelar" reemplazan la entrada
    del formulario en el historial (`replace`).
* **Componentes de Tabler a través de `src/components/ui`**: las páginas no repiten el marcado de Tabler. Sin Tailwind ni otra
  biblioteca de componentes; sin el JS de Tabler (menús, diálogos y avisos son componentes de React).
* **Confirmación antes de borrar** (desde la 1.2): `ConfirmDialog` (`role="dialog"`, `aria-modal`), con el foco en "Cancelar" al
  abrir. Mientras está abierto escucha las teclas en `document` (Escape cierra; Tab y Shift+Tab recorren solo el diálogo), devuelve
  el foco a "Cancelar" si sale del diálogo o al terminar la espera y bloquea el desplazamiento del fondo; al cerrar, el foco vuelve
  al botón que lo abrió. Los botones inactivos del diálogo usan `aria-disabled` (`Button` con `focusableWhenInactive`): con
  `disabled`, el botón suelta el foco. El error de negocio (`*_IN_USE`) va dentro del diálogo, con la acción que corresponda o solo
  "Cerrar" (`canConfirm={false}`). Desde la 2.3, `dialogError` (`src/lib/api/dialog-error.ts`) decide qué hace un diálogo con el error de su acción: el 404, aviso y al listado; un 422 o un error de negocio, el mensaje y solo "Cerrar"; lo demás, el mensaje y el botón para probar de nuevo (lo usan sucursales, usuarios y veterinarios). Desde la 1.3b: `tone` (`danger` por defecto; `primary` para lo que no borra), `size` (`md`, con
  campos o una lista) y `confirmDisabled` (falta elegir algo); al terminar la espera con un error, el foco va al primer campo con
  error del diálogo (`aria-invalid`) o, si no hay, a "Cancelar".
* **Caché después de borrar o mover un registro hijo** (desde la 1.2 y la 1.3b): los listados y los detalles que lo mostraban y no
  están a la vista se vacían con `resetQueries` (no se invalidan), así al entrar se espera la lista nueva y no se ve un momento la
  vieja: la baja de un paciente vacía el detalle de su dueño, y el cambio de dueño, los de los dos clientes (`resetClientDetails`).
* **Fechas y dinero**: la API manda las fechas con hora en UTC (`Z`); se muestran con `formatDate`, `formatDateTime` y `formatTime`
  (`src/lib/format/dates.ts`, siempre en `America/Asuncion`, nunca con la zona de la PC); se mandan con `toApiDateTime` (la hora de
  pared de Asunción con `TZDate(...).toISOString()`, con su desplazamiento `-03:00`); "hoy" es `todayInAppZone()`. Con segundos, `formatDateTimeSeconds` (desde la 2.6). Los filtros de fecha de la
  auditoría (desde la 2.6, `features/audit/dates.ts`): en la URL, la hora de pared de Asunción sin zona (`2026-11-09` o
  `2026-11-09T13:00`); a la API, en UTC con `TZDate`: la fecha sola es el día completo ("hasta", el comienzo del día siguiente
  menos un segundo, también en un día de 23 o 25 horas) y "hasta" con hora lleva los segundos en 59; "hasta" antes de "desde" se
  compara como texto de la hora de pared, sin pasar por `Date`. Una fecha sin
  hora (`birth_date`, `Y-m-d`) no es un instante: `formatDateOnly` solo cambia el formato del texto, sin pasar por `Date`
  (`formatDate` la reconoce y hace lo mismo). Guaraníes con `formatGuaranies` (`Intl.NumberFormat('es-PY')`, PYG, sin decimales):
  el texto decimal de la API se valida con una expresión regular y se formatea tal cual, sin pasar por un número de coma flotante.
  Un valor vacío (`null`, `undefined`, `''`) o inválido se muestra vacío (`''`), en las fechas y en el dinero (nunca "Gs. 0" ni
  un error que rompa el render).
* **La agenda** (desde la 3.2, `features/appointments/`; el diseño, en
  [`f3-agenda.md`](../03-plan-desarrollo/fases/frontend/f3-agenda.md#decisiones-comunes-de-la-fase)):
  * **Las fechas, en `dates.ts`** (con sus tests en UTC): el día es una fecha `yyyy-MM-dd` de Asunción y se suma con `addDays`
    sobre un `TZDate` al mediodía (nunca con los milisegundos de 24 horas); la semana empieza el lunes; el comienzo real del día
    (`dayStart`, también el 01/10/2023, que empezó a la 01:00) y los días de 23 (01/10/2023) y 25 horas (23/03/2024). La posición en
    la grilla sale de la hora de pared (`wallMinutes`, con `inAppZone`) y el fin, de la duración: la hora repetida de un día de 25
    horas cae en su misma fila.
  * **La grilla, pura en `grid.ts`** (sin React): las columnas, el rango de horas, lo sombreado, el carril de cada turno (los
    superpuestos, lado a lado) y la marca "Dentro de un bloqueo", con la regla de la API (los estados que ocupan el horario,
    `OCCUPYING_STATUSES`, y la superposición estricta). `AgendaGrid.tsx` sigue el patrón *grid* de WAI-ARIA con **un solo punto de
    tabulación**: las flechas recorren las celdas y los turnos, Inicio y Fin (con Ctrl, la grilla), Re Pág y Av Pág cambian de día;
    si el turno con el foco desaparece, el foco va a su celda, y un día sin agendas pasa el foco al aviso. La navegación se calcula
    una vez por grilla (`useMemo`) y cada turno va en `memo`: con cientos de turnos, cada tecla no vuelve a dibujarlos todos.
  * **La fecha va siempre en la URL** (`fecha`, en `TEXT_SEARCH_PARAMS`), también la de hoy: "hoy" cambia a la medianoche y un
    enlace copiado mostraría otro día (la excepción a "los valores por defecto no van en la URL").
  * **La actualización periódica, con pausa común** (`src/lib/hooks/auto-refresh.ts`): lo que se vuelve a pedir cada 60 s lee
    `useAutoRefreshPaused()` y pasa `refetchInterval: false` en pausa; cada diálogo o formulario abierto suma una pausa con
    `usePauseAutoRefresh(abierto)` (ya lo hace `ConfirmDialog`) y la pestaña oculta pausa sola. La actualización no anuncia nada ni
    mueve el foco.
* **Sesión** (0.2a, R-061; el diseño, en [Sesión (0.2)](../03-plan-desarrollo/fases/frontend/f0-base.md#sesión-02)): el
  token y `expires_at` solo en `sessionStorage` (`syncvet.session`), nunca en `localStorage`, la URL ni el log; `me` en la caché de
  Query (`['me']`). Todo pasa por el `SessionManager` (`src/lib/auth/session.ts`), creado una vez por pestaña en `main.tsx`: los
  componentes lo leen con `useSessionManager()` y `useSessionState()`. Entre pestañas, `BroadcastChannel('syncvet-sesion')`
  (`channel.ts`), con cada mensaje validado completo (`isSessionMessage`): una pestaña nueva pide la sesión (`session-request`, 1000
  ms); "Salir" cierra todas (`logout` con `all: true`) y manda el `POST logout` después, sin esperarlo (5 s de límite); el
  vencimiento y un error de sesión (401, `USER_INACTIVE`, `CLINIC_NOT_AVAILABLE`) cierran solo las pestañas con ese token, y solo
  si el pedido salió con el token actual. El vencimiento se revisa con temporizadores, al volver a la pestaña (`visibilitychange`,
  `focus`, `pageshow`) y cada 15 s; el aviso previo, 10 minutos antes, con los minutos que quedan. `volver` pasa siempre por
  `safeRedirect` (solo rutas internas). Un pedido con un token explícito (`token: null` en el login, un texto en el `logout`) no toca
  la sesión.
* **Permisos** (0.2b): `can(me, permiso)` (`src/lib/auth/permissions.ts`, con los nombres de la API en `PERMISSIONS`); en los
  componentes, `useCan()` y `<RequirePermission permission>` (`src/features/auth`). Cada ruta con permiso lo declara con
  `beforeLoad: requirePermission(permiso)`: sin él, `/sin-permiso` (dentro del layout con sesión). El menú (`src/app/menu.ts`)
  lista solo los módulos hechos, con su permiso, y `visibleMenu` lo filtra. La API vuelve a controlar todo (un botón oculto no
  protege): un 403 `FORBIDDEN` al cargar una página muestra la de sin permiso con el `message` de la API
  (`defaultErrorComponent`, `RouteErrorPage`); en una acción, la alerta con el `message`. Los tests usan los permisos reales de
  los tres roles, leídos de `backend/tests/Fixtures/clinic-roles.json` (`src/test/roles.ts`, `meFor(rol)`), nunca inventados.
* **Sucursal actual** (0.2b): el selector de la barra (`BranchSelector`) con `me.branches`; `PATCH me/current-branch` y, con el
  200, `applyBranchChange` (`me` se vuelve a pedir al servidor, no se toma la respuesta del `PATCH`, y se invalidan solo las
  consultas de la sucursal, `invalidateBranchQueries`) y `branch-changed` en el canal (las demás pestañas invalidan `['me']` y
  las consultas de la sucursal). Al volver a la pestaña (`focus`, `visibilitychange`), `SessionLayout` vuelve a pedir `me`: la
  sucursal pudo cambiar en otra PC o un administrador pudo inactivarla. Sin sucursal actual, un aviso pide elegir una.
* **Descargas con token** (desde la 1.4): `api.getBlob(path, { expectedType })` (`src/lib/api/client.ts`) arma el pedido como los
  demás (el token en la cabecera, nunca en la URL) y devuelve el `Blob`; los errores JSON de la API son el `ApiError` de siempre (el
  401 cierra la sesión) y un 2xx en JSON, una página HTML o un tipo distinto de `expectedType` (la foto pide `image/`) es
  `HTTP_ERROR`. Se muestran con `BlobImage` (`src/components/ui`): la ref del `<img>`, con limpieza (React 19), crea la URL de
  objeto al montar y la libera (`revokeObjectURL`) al cambiar el `Blob` o al desmontar, sin estado. La caché de un archivo
  (`['patients', 'photo', id]`: `staleTime: Infinity`, `gcTime` de 5 minutos) se vacía al reemplazarlo (`resetQueries`: se espera
  el nuevo, nunca se ve el viejo) o al borrarlo (`Query.reset()`, sin volver a pedirlo); salir de la sesión la borra con el resto
  (`queryClient.clear()`).
* **Subir un archivo** (desde la 1.4; el modelo es `features/patients/PatientPhoto.tsx` y `photo.ts`): `FileField`, vista previa
  con `BlobImage` y un botón para mandar (`FormData`). El navegador controla como la API, con sus mensajes: desde la 2.5, con el
  control común de `src/lib/files/image.ts` (`imageFileError`, con el límite y los mensajes de cada uso: la foto, 5 MB; el logo, 2
  MB). El tamaño primero (el límite justo pasa, como `max` de Laravel en kilobytes) y el tipo **por el contenido** (los primeros bytes de un JPG, un PNG o un WEBP;
  nunca por la extensión ni el tipo declarado, que la API ignora). Un 422 o 413 con `errors.<campo>` va al campo (con el foco al
  terminar la espera, por un pedido de foco propio y no por la vuelta de `isPending`); un 413 sin `errors`, a una alerta. La
  mutación lleva `gcTime: 0` y `reset()` en `onSettled`: el archivo no queda en la caché de mutaciones. En los tests, el `FormData`
  y el `File` de Node (el `fetch` de Node no lee el `File` de jsdom).
* **Una pantalla por registro** (desde la 1.4): la ruta de un detalle dibuja su página con `key={id}`
  (`<PatientDetailPage key={id} id={id} />`): sin ella, el router reutiliza el componente al cambiar solo el `$id` y el estado local
  (un archivo elegido, un diálogo abierto, un pedido en curso) pasaría de un registro al otro. Toda pantalla de detalle nueva la
  lleva.
* **Tests**: Vitest con MSW (un pedido sin handler hace fallar el test) y Testing Library. Corren en UTC, para que mostrar una fecha
  con la zona de la PC falle. Los `.test.ts` (lógica) corren en `node` y los `.test.tsx` (componentes), en jsdom; un `.test.ts` que
  necesita el DOM lo pide con `// @vitest-environment jsdom` en la primera línea (`client.test.ts`). Se permiten `.each` y los
  bucles. Límites (desde la 1.1): `findBy*` y `waitFor` esperan hasta 3 s (`configure({ asyncUtilTimeout: 3000 })` en
  `src/test/setup.ts`) y cada test de jsdom, hasta 15 s (`testTimeout` del proyecto `dom`): con la PC cargada, el primer render
  con el layout pasaba el segundo. Una espera de tiempo se prueba con relojes falsos (`vi.useFakeTimers()` después de cargar la
  página y `vi.useRealTimers()` en `afterEach`), no con `setTimeout` reales. La cifra de tests del frontend sale de la última corrida de `npm test`, registrada en "Resultado" de la fase con la
  línea ``Corrida de `npm test` del frontend (AAAA-MM-DD): N tests en M archivos, en verde.``: `/validar-docs` la compara con la
  documentación (no cuenta los tests en el código). En las páginas con el layout, consultar por texto con selector: `*ByRole`
  tarda segundos en jsdom con los íconos SVG. Los textos de JSX y las cadenas de una palabra con mayúscula inicial también pasan
  por el test de voseo.
