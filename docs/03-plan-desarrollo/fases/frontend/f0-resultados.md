# F0 — Base del frontend: resultados

El resultado de cada entrega, el cierre de la fase y lo que vino después. El diseño y las decisiones, en [`f0-base.md`](f0-base.md).

## Resultado

### Entrega 0.1

**COMPLETADA el 2026-11-12 (R-060)**, en `feature/front-0-1-esqueleto`: la implementación en `72e75bc` (WIP), las correcciones de
la revisión en `b5d3ecf` (WIP) y el commit de cierre (solo documentación). Sin merge a `main`: todo se integra al cerrar la F0
(R-059).

**Qué se hizo.** `frontend/` con el stack de R-058 (Node 26, Vite 8, React 19, TypeScript 6 estricto, TanStack Router y Query,
React Hook Form con Zod, date-fns 4 con `@date-fns/tz`, Tabler sin su JS, Vitest con MSW, ESLint y Prettier): el cliente sobre
`fetch` con `ApiError` y la tabla de `error_code`, las fechas en `America/Asuncion` (con las fechas sin hora aparte), los guaraníes,
la capa de componentes sobre Tabler, `/ingresar` como maqueta, el layout, `/sin-permiso` y la página de no encontrado, el test de
voseo con las listas del backend y las cifras del frontend en `/validar-docs`. Las reglas quedaron en
[`frontend/CLAUDE.md`](../../../../frontend/CLAUDE.md).

**Validación del usuario (2026-11-12, en el navegador con `npm run dev`).** `/ingresar` se ve bien con Tabler (logo, título
"Ingresá a tu cuenta", campos obligatorios, botón con ícono) y una URL inventada muestra la página de no encontrado en voseo con
"Volver al inicio". El resto de "Qué revisar en el navegador" (`/sin-permiso`, el ancho de tablet, la consola sin avisos y
`npm run preview`) no figura en la validación: PENDIENTE DE VERIFICACIÓN (el ancho de tablet y la consola vuelven a revisarse en la
0.2b).

**Sugerencias del usuario para la 0.2.** Un botón para mostrar la contraseña en `/ingresar` (0.2a) y en `/perfil` (0.2b); evaluar
"Correo electrónico" en lugar de "Email" (pregunta 4, en el diseño de la 0.2a).

**Cifras.** La última corrida, después de las correcciones de la revisión y sobre el mismo código del cierre (desde `b5d3ecf` solo
cambió la documentación), desde `frontend/`: `npm run typecheck`, `npm run lint` y `npm run build` sin errores ni avisos, y:

Corrida de `npm test` del frontend (2026-11-12): 93 tests en 9 archivos, en verde.

`/validar-docs`: sin hallazgos; sus propios tests, 55 de 55. El backend no cambió en esta entrega (ningún archivo de `backend/`): su
suite no se volvió a correr.

**Tiempo de la implementación.** Medido en el registro de la sesión de Claude Code: 66 minutos desde el inicio (con el renombrado
de la rama del paso 0), unos 124 mil tokens de salida y 19 millones leídos del contexto en caché.

| Rubro | Tiempo aproximado | Detalle |
|---|---|---|
| npm (red e instalación) | ~22 min | Una instalación de las dependencias de la aplicación, 13,9 min; la de desarrollo, 4,7 min; un intento con 404 (`@tabler/core` 1.7.5), 2,6 min; consultar las versiones paquete por paquete, 2,8 min |
| Escribir los archivos | ~19 min | 53 archivos nuevos y 37 ediciones |
| Verificaciones repetidas | ~22 min | `prettier --write .`, `tsc`, `lint` y la suite completa, muchas veces; preparar jsdom era el 63 % de cada corrida de la suite |
| El validador y las skills | ~5 min | Otro tema, en la misma entrega |

De ahí salen las reglas de la sección [Velocidad](../../../../frontend/CLAUDE.md#8-velocidad) de `frontend/CLAUDE.md` (versiones
fijas en el diseño y un solo `npm ci`; `environment: node` para los tests sin DOM; `vitest related` durante el trabajo y la suite,
`typecheck` y `lint` una vez al final; Prettier solo sobre los archivos cambiados; validador, skills y documentación general en
entregas o cierres aparte) y la exclusión de `frontend/node_modules` y `backend/vendor` del antivirus en
[`setup.md`](../../../04-desarrollo/setup.md#antivirus-de-windows). La división de la suite en `node` y jsdom ahorró poco (9,97 s
contra 9,55 s con las mismas 93 pruebas): casi todo el tiempo de jsdom es de los tests de componentes.

**Desvíos y decisiones de la implementación.** Las versiones instaladas están en
[`technology-stack.md`](../../../01-arquitectura/technology-stack.md#frontend).

| # | Desvío o decisión | Motivo | Estado al cerrar |
|---|---|---|---|
| 1 | La variable del proxy es `API_PROXY_TARGET`, no `VITE_API_PROXY_TARGET` | Pedido de la implementación. Sin el prefijo `VITE_`, Vite no la expone al código del navegador: solo la lee `vite.config.ts` (la revisión comprobó que no llega a `dist/`) | Aceptado (usuario) |
| 2 | `@tabler/core` 1.7.4, no la 1.7.5 (la última) | La 1.7.5 se publicó el mismo día y npm respondía 404 al bajar su paquete; la 1.7.4 es del 2026-11-08. El resto, la última versión de cada paquete dentro de lo pedido (TypeScript 6.0.3, porque la última es la 7) | Aceptado (usuario). Pendiente para la 0.2a: subir a la 1.7.5 |
| 3 | `npm audit`: 1 aviso moderado, `minimatch` 10.2.6 (GHSA-g3hv-75wg-m249), que traen `eslint` 10 y `typescript-eslint` 8.72.2 | Solo en las herramientas de desarrollo (ESLint sobre el código propio), no llega al build. No hay arreglo compatible: `npm audit fix --force` baja ESLint a la 9 | Registrado como [deuda 19](../../deuda-tecnica.md), prioridad baja (usuario) |
| 4 | Un `it()` por caso, sin `.each` ni `it()` dentro de bucles, controlado por ESLint (así quedó la implementación) | Cambiado por el usuario en la revisión: se permiten `.each` y los bucles, y la cifra de tests del frontend sale de la última corrida de `npm test` registrada en "Resultado" de la fase (H13) | Resuelto (H13) |
| 5 | Los tests corren con `TZ=UTC` y sin la división del código de las rutas | En UTC, mostrar una fecha con la zona de la PC hace fallar los tests aunque la PC esté en hora de Paraguay (esta lo está: sin eso, la mutación de la zona sobrevivía). Sin dividir el código, el primer render de las rutas no espera la carga de los pedazos, que con la suite en paralelo pasaba los 5 segundos | Aceptado (usuario) |
| 6 | `tsconfig.test.json` aparte, con los tipos de Node | El código de la aplicación no ve `process` ni `node:fs`; los tests (el de voseo lee `src/`) sí | Informativo |
| 7 | `frontend/.editorconfig` (sangría de 2) y `frontend/.gitattributes` (fin de línea LF) | Prettier toma la sangría de 4 del `.editorconfig` de la raíz (la del backend); con LF fijo, `prettier --check` da lo mismo en todas las PC | Informativo |
| 8 | `toApiDateTime` manda la hora con el desplazamiento (`2026-07-01T09:00:00.000-03:00`), como da `TZDate(...).toISOString()` | Lo pedido. Que la API lo acepte en cada campo de fecha y hora se verifica con la primera llamada real (0.2a o F3) | VERIFICADO en la 1.3b de la F1 (2026-11-14), con `deceased_at`, el primer envío de una fecha con hora: se mandó `2026-11-10T10:30:00.000-03:00`, la API devolvió `2026-11-10T13:30:00.000000Z` y al recargar se ve 10:30 (Playwright `e2e/pacientes-estado.spec.ts` contra `syncvet_e2e` y Edge sin ventana contra `syncvet.test`). Los campos de fecha y hora de la F3 usan la misma función |
| 9 | `/sin-permiso` va dentro del layout con sesión; la página de no encontrado, fuera de todo layout | El usuario sin permiso ya tiene sesión: ve la barra y el menú. Una URL desconocida puede no tener sesión | Confirmado en la 0.2b (pedido del usuario al implementarla, 2026-11-12): una ruta sin permiso lleva a `/sin-permiso` con la barra y el menú a la vista, probado con Vitest y con Playwright |
| 10 | Scripts además de los del diseño: `format:check` y `test:watch`; `test:e2e` avisa que llega en la 0.2 (0.2b) y sale con error | Preparar la 0.2 sin que un `test:e2e` vacío parezca en verde | Informativo |
| 11 | El validador de la documentación (`check-docs.php`) separa las cifras: las de una cláusula que nombra al frontend o de un documento de `frontend/` se comparan con los tests del frontend; la cifra de tests de un archivo de test citado (entre comillas invertidas, con la cifra entre paréntesis) se compara con ese archivo; el resumen dice "Backend:" y "Frontend:". Una cifra del frontend que no es de tests (de rutas, por ejemplo) se excluye y se lista en `--detalle` | Pedido de la entrega; antes toda cifra del frontend se excluía como "de otro proyecto" | Informativo |

Mutaciones de la implementación: las cinco del diseño, cada una detectada por sus tests (la conversión a `ApiError`, reintentar
los 4xx, ignorar el campo en `setError`, formatear con la zona de la PC y leer `perPage`).

**Revisión del 2026-11-12 y correcciones (autorizadas por el usuario), en `b5d3ecf`.** Un revisor que no escribió la entrega encontró 15
hallazgos; todos se corrigieron en la misma rama, cada uno con su test:

| # | Hallazgo | Corrección | Estado |
|---|---|---|---|
| H1 | `formatDate('2020-05-10')` mostraba `09/05/2020`: una fecha sin hora (`birth_date`) pasaba por `Date` como medianoche UTC; un valor inválido rompía el render (`RangeError`) | `formatDateOnly` solo cambia el formato del texto (valida año, mes y día, con los bisiestos); `formatDate` la reconoce. Fechas vacías o inválidas: `''` | Corregido |
| H2 | Un 422 con `branch_ids.0` no llegaba al campo `branch_ids`: `applyValidationErrors` buscaba la clave exacta | La clave va al campo con el mismo nombre, al de un patrón con `*` (`hours.*.starts_at`) o al campo padre; las de un mismo campo juntan sus mensajes | Corregido |
| H3 | Un 204 (o un 2xx sin cuerpo) terminaba en `HTTP_ERROR` | Decisión del usuario: es un éxito sin datos (`data = null`). Un 2xx con un cuerpo que no es JSON sigue siendo `HTTP_ERROR` | Corregido |
| H4 | ESLint no frenaba `window.fetch`, `globalThis.fetch`, `self.fetch` ni `XMLHttpRequest` | `no-restricted-properties` y `XMLHttpRequest` en `no-restricted-globals` | Corregido |
| H5 | El test de voseo no revisaba los textos de una palabra ("Ingrese" en un botón, `'Elija'`) | Revisa todo texto de JSX y las cadenas de una palabra con mayúscula inicial; no da falsos positivos en `src/` | Corregido |
| H6 | `formatGuaranies` convertía el texto con `Number` (coma flotante): `''` mostraba "Gs. 0" y `'12345678901234567'`, `…568` | Decisión del usuario: el texto se valida con `/^-?\d+(?:\.\d+)?$/` y va tal cual a `Intl.NumberFormat`. Vacío o inválido (`null`, `''`, `' '`, `'abc'`, `'12abc'`, `'1e3'`, `NaN`, `Infinity`): `''` | Corregido |
| H7 | Sin test: una cancelación convertida en `NETWORK_ERROR` | El cliente mira la señal (`signal.aborted`), no el tipo del error (`fetch` rechaza con el motivo de `abort()`); test | Corregido |
| H8 | Sin test: un 2xx con `success: false` | Test | Corregido |
| H9 | Sin test que distinga `last_page` de `total` en "Siguiente"; una página fuera de rango mostraba "Mostrando 61 a 32" | Test de la última página con `total` mayor que `last_page`; fuera de rango, "Mostrando 0 a 0 de 32" | Corregido |
| H10 | Sin test: los parámetros `null`, `undefined` o vacíos no van en la URL | Test | Corregido |
| H11 | En el informe figuraba como "`Retry-After` con `parseInt`", pero la mutación que sobrevivió era otra: guaraníes con `parseFloat` (acepta `"12abc"`) | La cubre H6 (`'12abc'` da `''`) | Corregido (por H6) |
| H12 | Ninguna regla prohibía `dangerouslySetInnerHTML` (no se usa) | `no-restricted-syntax` sobre el atributo de JSX | Corregido |
| H13 | Desvío 4 | Decisión del usuario: se permiten `.each` y los bucles (sin las reglas de ESLint). La cifra de tests del frontend sale de la última corrida de `npm test` registrada en "Resultado" de la fase, con la línea de formato fijo ``Corrida de `npm test` del frontend (AAAA-MM-DD): N tests en M archivos, en verde.``. `/validar-docs` toma la de fecha más reciente; informa si la cantidad de archivos de test no coincide o si no hay corrida; ya no compara las cifras de un archivo de test. `check-docs-test.php`, casos 7 y 8 (55 de 55) | Corregido |
| H14 | Desvío 3 | [Deuda 19](../../deuda-tecnica.md), prioridad baja | Registrado |
| H15 | Desvíos 1, 2 y 5 | Aceptados por el usuario; `@tabler/core` 1.7.5, pendiente para la 0.2a | Registrado |

Además, a pedido del usuario: la sección **Velocidad** de [`frontend/CLAUDE.md`](../../../../frontend/CLAUDE.md#8-velocidad)
(versiones fijas en el diseño y un solo `npm ci`; `environment: node` para los tests sin DOM; `vitest related` durante el trabajo y
la suite, `typecheck` y `lint` una vez al final; Prettier solo sobre los archivos cambiados; validador, skills y documentación general
en entregas aparte) y cómo excluir `frontend/node_modules` y `backend/vendor` del antivirus en
[`setup.md`](../../../04-desarrollo/setup.md#antivirus-de-windows). Vitest se divide en dos proyectos: `node` (los `.test.ts`) y
`dom` (los `.test.tsx`, jsdom); `client.test.ts` pide jsdom con `// @vitest-environment jsdom`.

Mutaciones de la revisión, cada una detectada por sus tests (solo los relacionados): la fecha sin hora por `Date`; sin el campo
padre (`branch_ids.0`); el 204 como error; la cancelación como `NETWORK_ERROR`; los guaraníes con `Number()`; el voseo sin las
palabras sueltas; "Siguiente" por `total`; un 2xx con `success: false` como éxito; los parámetros vacíos en la URL.

Corrida final, una vez, el 2026-11-12: `typecheck`, `lint` y `build` sin errores ni avisos; `npm test` del frontend, 93 tests en 9 archivos, en
verde, en 9,55 s. Tiempo de la suite según el entorno: antes de la revisión (61 tests, todo en jsdom), 9,35 s (32,9 s en la primera
corrida de la revisión, en frío); con las 93 pruebas todo en jsdom, 9,97 s (preparar el entorno, 46 %); con `node` para los
`.test.ts`, 9,55 s (40 %). La ganancia es chica (4 %): casi todo el tiempo de jsdom es de los `.test.tsx`, que lo necesitan.

### Entrega 0.2a

**COMPLETADA el 2026-11-12 (R-061)**, en `feature/front-0-2a-sesion`: la implementación en `93e347c` (WIP), las correcciones de
la revisión en `ece1faf` (WIP) y el commit de cierre (solo documentación). Sin merge a `main`: todo se integra al cerrar la F0
(R-059).

**Qué se hizo.** La sesión contra la API real, según el [diseño](f0-base.md#02a-sesión) (los flujos 3 a 6, actualizados en el cierre): el
token en `sessionStorage` (`src/lib/auth/storage.ts`), el `SessionManager` (`session.ts`: aviso previo con los minutos que quedan,
vencimiento con temporizadores, `visibilitychange`, `focus`, `pageshow` y un intervalo de 15 s, y el cierre ante un error de
sesión solo con el token actual), el canal entre pestañas (`channel.ts`: `session-request`, `session-share` y `logout` con
`token_id` y `all`, cada mensaje validado completo), `safeRedirect` para `volver`, `/ingresar` real con los errores por trato, el
botón para mostrar la contraseña y la espera del 429 (60 s sin `Retry-After`), y el layout con sesión (barra con el usuario, la
clínica y la sucursal actual, "Salir" y el aviso previo). `@tabler/core` 1.7.5 (desvío 2 de la 0.1). Las reglas quedaron en
[`frontend/CLAUDE.md`](../../../../frontend/CLAUDE.md).

**Validación en el navegador (2026-11-12),** con el backend real en `syncvet.test` y `npm run dev`:

* **Del usuario,** las 5 pruebas en verde: ingreso con `admin-a`, `recepcion-a` y `vet-a` (nombre, clínica y sucursal en la barra);
  contraseña incorrecta, con el mensaje de la API; la cuenta regresiva del 429; una segunda pestaña que entra sola; salir en una
  pestaña cierra la otra.
* **De Claude Code, a pedido del usuario,** el resto de "Qué revisar en el navegador": Edge sin ventana, manejado por el protocolo
  de DevTools con un script de Node (sin dependencias nuevas), sobre `ece1faf`. Las 8 pruebas en verde:
  1. El botón muestra y oculta la contraseña (`password` → `text` → `password`).
  2. `expires_at` ya pasado en `syncvet.session` y volver a la pestaña: `/ingresar` con "Tu sesión venció. Ingresá de nuevo.".
  3. Una sesión que vence en 20 s, sin ningún evento: abierta hasta el vencimiento y cerrada sola a los 20 s, con el mismo aviso.
  4. `syncvet.session` borrada a mano y volver a la pestaña: `/ingresar` con el mismo aviso.
  5. `expires_at` a 4 min 5 s: el aviso "Tu sesión vence en 5 minutos. Guardá los cambios pendientes.".
  6. Sin ningún evento, el aviso pasa solo a "4 minutos".
  7. "Salir": `/ingresar` sin `volver` ni aviso.
  8. `recepcion-a` con sesión, inactivada por `admin-a` con `PATCH users/{id}` desde otra sesión (la API, porque el frontend todavía
     no tiene la gestión de usuarios): al recargar, `/ingresar` con "Tu sesión venció. Ingresá de nuevo.". Inactivar revoca los
     tokens del usuario (`backend/CLAUDE.md` §3), así que el siguiente pedido recibe un 401, no un 403 `USER_INACTIVE`: el aviso es
     el del 401. El trato del 403 `USER_INACTIVE` con sesión lo cubren los tests. Al terminar, `recepcion-a` quedó reactivada.

  La consola del navegador, sin errores ni avisos de la aplicación: solo el registro de red del 401 esperado de la prueba 8
  (`Failed to load resource … 401` en `/api/me`).

  Las pruebas vieron el texto de entonces, "Tu sesión venció. Ingresá de nuevo.". Después, en el cierre, el usuario lo cambió por
  "Tu sesión terminó. Ingresá de nuevo.", porque a un usuario inactivado no se le venció la sesión (R-061); se corrieron los tests
  relacionados (139 en 10 archivos) y el de voseo, en verde.

**Cifras.** La corrida del cierre, sobre `ece1faf` (el código de las correcciones; el cierre solo cambia la documentación), desde
`frontend/`: `npm run typecheck`, `npm run lint` y `npm run build` sin errores ni avisos, y `npm test` en 22,1 s:

Corrida de `npm test` del frontend (2026-11-12): 195 tests en 15 archivos, en verde.

El backend no cambió en esta entrega (ningún archivo de `backend/` entre `76047b2` y `ece1faf`): su suite no se volvió a correr.

**Desvíos y decisiones de la implementación** (1 a 11) **y de la corrección** (12 a 15).

| # | Desvío o decisión | Motivo | Estado al cerrar |
|---|---|---|---|
| 1 | El aviso previo dice "Tu sesión vence en N minutos. Guardá los cambios pendientes.", con los minutos que quedan, y no el texto fijo del diseño ("…en 10 minutos. Guardá lo que estés haciendo.") | "estés" está en la lista de formas de tú del test de voseo (`TU_OR_USTED_WORDS`). Y una sesión abierta con menos de 10 minutos, o una PC que vuelve de la suspensión, no tiene 10 minutos: el aviso dice los que quedan ("1 minuto" en singular) y, desde la revisión (B2), se actualizan | Aceptado (usuario, R-061) |
| 2 | Un `CLINIC_NOT_AVAILABLE` con sesión también publica `logout`, como el 401 y `USER_INACTIVE`; desde la revisión (M3), los tres cierran solo las pestañas con el mismo token | La tabla solo decía "borra la sesión"; con el token compartido, las demás pestañas fallarían igual en su siguiente pedido. La decisión M3 nombra el vencimiento y el 401; una pestaña con otro token recibe su propio 403 en el siguiente pedido | Aceptado (usuario, R-061) |
| 3 | El `POST logout` está en el `SessionManager` (`src/lib/auth/session.ts`), no como `logout(token)` en `features/auth/api.ts`; el hook es `useSessionManager()` y `useSessionState()`, no `useSession()` | `src/lib` no importa de `features/`: el `SessionManager` lo usa para salir y para el logout de una pestaña con otro token | Informativo |
| 4 | El `beforeLoad` de `_sesion` usa `queryClient.query(meQueryOptions())`, no `ensureQueryData`, y manda a `/ingresar` si la sesión quedó cerrada, sin mirar el error | `ensureQueryData` está deprecado en TanStack Query 5.107 (`no-deprecated` de ESLint). El cierre limpia la caché y cancela el `me` en curso: el error que llega es la cancelación, no el `ApiError` (lo encontró un test) | Informativo |
| 5 | ESLint: `only-throw-error` permite el `Redirect` de `@tanstack/router-core` | TanStack Router corta una navegación con `throw redirect(...)`, que no es un `Error`; sin esto haría falta un comentario por cada `redirect` | Informativo |
| 6 | Fuera del alcance escrito: `/ingresar` con una sesión abierta va a `/`; una falla de red o un 5xx al cargar `me` muestra "No se pudo cargar tu sesión" con "Reintentar" y no cierra la sesión | Sin lo primero, el botón "Atrás" después de ingresar mostraba el formulario con la sesión abierta; sin lo segundo, se veía el error genérico del router | Aceptado (usuario, R-061) |
| 7 | `App` recibe `queryClient` y `session` (obligatorios); `main.tsx` los crea una vez por pestaña | La sesión escucha el canal y los temporizadores fuera de React; cada test crea los suyos y los descarta (`src/test/session.tsx`) | Informativo |
| 8 | `frontend/.gitignore` suma `.tanstack` | El plugin del router dejó una carpeta temporal (`.tanstack/tmp`) al generar las rutas | Informativo |
| 9 | Al terminar la implementación, la suite del frontend tardó 34,9 s, cuando tenía 155 tests en 14 archivos, contra 9,55 s de la 0.1 con 93 | Los tests nuevos de componentes montan la aplicación entera (jsdom) y el del 429 espera 1 s real la cuenta regresiva. No se midió aparte qué parte es de la PC | Revisado en el cierre: 22,1 s, con los 195 tests del frontend en 15 archivos (24 s en la corrida de la corrección). Informativo |
| 10 | `typecheck` y `lint` corrieron dos veces al final de la implementación, no una | El primer `lint` encontró los `throw redirect(...)` y `ensureQueryData` (desvíos 4 y 5); después del arreglo se repitieron, junto con la suite y el build | Informativo |
| 11 | El desvío 8 de la 0.1 (`toApiDateTime` con `-03:00`) sigue PENDIENTE DE VERIFICACIÓN | La 0.2a no manda fechas a la API | Pasa a la F3 |
| 12 | El `EXPIRES_AT` fijo de `LoginPage.test.tsx` (`2026-11-13T00:00:00.000Z`) pasa a ser relativo a la hora de la corrida | Con una fecha fija, desde el 2026-11-13 la sesión del login quedaba vencida al guardarse y los tests habrían empezado a fallar | Informativo |
| 13 | `SessionManager.end()` pasa a ser privado (`close(notice, publish)`, con `all`, `token` o `none`) | Nadie lo llamaba desde afuera, y así cada cierre dice a qué pestañas avisa | Informativo |
| 14 | Los flujos 3 a 6 del diseño detallado, las filas "Aviso previo", "Vencimiento", "Salir", "Todas las pestañas" y "401" de [Sesión (0.2)](f0-base.md#sesión-02) y la tabla de errores describían el comportamiento anterior a la revisión | La corrección solo escribió en "Pendiente para el cierre" | Actualizados en el cierre |
| 15 | En la corrida final de la corrección, `lint` encontró un error en `channel.test.ts` (`non-nullable-type-assertion-style`); se corrigió y se repitieron `eslint`, `tsc` y el test de ese archivo, no la corrida entera | Solo cambió una constante del test | Cubierto: la corrida del cierre pasó entera |

**Mutaciones de la implementación**, cada una de a una con `vitest related` sobre `src/lib/auth`, `SessionLayout.test.tsx` y
`LoginPage.test.tsx` (62 tests): no publicar `logout` con un 401 (falla 1 test); no responder `session-share` (2); no publicar
`logout` al cerrar (4); sin el temporizador de vencimiento (1); aceptar un `volver` externo (9). Todas detectadas. Corrida final de
la implementación: `typecheck`, `lint` y `build` sin errores ni avisos; `npm test` del frontend, que entonces tenía 155 tests en 14
archivos, en verde (34,9 s).

**Revisión del 2026-11-12 y correcciones (autorizadas por el usuario), en `ece1faf`.** Un revisor que no escribió la entrega encontró 5
hallazgos de gravedad media (M1 a M5) y 6 de gravedad baja (B1 a B6). M3 lo decidió el usuario; los demás se corrigieron como los
pidió, cada uno con su test.

| # | Hallazgo | Corrección | Estado |
|---|---|---|---|
| M1 | Un 401 que llegaba tarde, de un pedido con el token viejo, cerraba la sesión nueva (después de volver a ingresar) | El cliente le pasa al manejador el token con el que salió el pedido (`client.ts`); el `SessionManager` cierra solo si es el de la sesión actual | Corregido |
| M2 | Con el `POST logout` colgado, "Salir" no limpiaba nada hasta que el navegador cortara el pedido | `logout()` cierra primero (almacenamiento, caché y `logout` en el canal) y después manda el POST sin esperarlo, con `AbortSignal.timeout(LOGOUT_TIMEOUT_MS)` (5 s). El botón ya no muestra la carga: la sesión se cierra en el mismo clic | Corregido |
| M3 | El vencimiento o el 401 del token T1 cerraban también una pestaña con otro token (T2), que además lo revocaba | **Decisión del usuario:** el vencimiento y el 401 cierran solo las pestañas con ese mismo token; "Salir" a propósito sigue cerrando todas y revocando sus tokens. El mensaje `logout` suma `all` (`true` solo con "Salir"); con `all: false`, una pestaña con otro `token_id` lo ignora. Los 403 de sesión, igual (desvío 2) | Corregido |
| M4 | Con la PC suspendida y la pestaña a la vista no llegaba `visibilitychange`: la sesión seguía abierta pasada la hora de vencer | La revisión también corre con `focus` y `pageshow`, y un intervalo de `CHECK_INTERVAL_MS` (15 s) compara `Date.now()` con `expires_at` | Corregido |
| M5 | 8 de 10 mutaciones de la revisión sobrevivían; sin tests del botón Atrás, del 429 sin `Retry-After` ni de `expires_at` con el formato de la API | Un test por cada mutación que sobrevivía. Además: el botón Atrás después de salir, el 429 sin `Retry-After`, `expires_at` con 6 decimales y `BroadcastSessionChannel` sobre el `BroadcastChannel` real (`channel.test.ts`, en `node`). `sessionFor()` usa el formato de la API (`apiDateTime`) | Corregido |
| B1 | El canal solo miraba `type`: un `logout` sin `token_id` revocaba el token propio y un `notice` que no era texto rompía el render | `isSessionMessage` valida el formato completo de cada mensaje (los campos exactos y sus tipos; la sesión compartida, como la guardada) y el canal descarta lo demás. El canal en memoria de los tests usa la misma validación | Corregido |
| B2 | El aviso previo quedaba con los minutos del momento en que se encendió | El intervalo actualiza `warningMinutes` (los minutos que quedan, redondeados para arriba; nunca menos de 1) | Corregido |
| B3 | Un 429 sin `Retry-After` dejaba el botón habilitado | El botón queda deshabilitado 60 s (`DEFAULT_RETRY_AFTER_SECONDS` en `LoginPage.tsx`), con la misma cuenta regresiva | Corregido |
| B4 | `safeRedirect('/..//otro.sitio')` devolvía `//otro.sitio` (el router lo convertía en `/otro.sitio`: no sacaba del sitio) | Se revisa la ruta ya normalizada: rechaza la que empieza con `//` y `/ingresar` sin importar mayúsculas ni la barra final | Corregido |
| B5 | Una pestaña nueva podía guardar la sesión compartida de un token cuyo `logout` ya había llegado | El `SessionManager` recuerda los `token_id` cerrados por el canal y descarta un `session-share` de esos tokens | Corregido |
| B6 | Los tests usaban `expires_at` con 3 decimales; la API manda 6 (Carbon `toISOString()`) | Cubierto en M5 | Corregido |

**Mutaciones de la corrección**, cada una de a una, con `vitest run` sobre `src/lib/auth`, `src/lib/api`, `src/features/auth` y
`SessionLayout.test.tsx` (152 tests); las 18 detectadas:

* Las 8 que sobrevivían en la revisión: no vaciar la caché al cerrar (fallan 2); aceptar una sesión compartida vencida (1);
  compartir la sesión propia vencida (1); no cerrar si la sesión se borró del almacenamiento (1); sin el chequeo del token
  explícito (`usesSessionToken`) (1); no borrar el almacenamiento con un `logout` sin sesión (1); el canal real sin validar (1); el
  `session-share` sin validar (3).
* Las de la corrección: el 401 de un token viejo (M1, 1); esperar el POST antes de cerrar (M2, 1); sin tiempo límite en el POST
  (M2, 1); cerrar las pestañas de otro token (M3, 2); sin `focus` (M4, 1); sin el intervalo (M4, 2); los minutos del aviso fijos
  (B2, 1); el 429 sin espera (B3, 1); aceptar `//` en `volver` (B4, 2); aceptar la sesión de un token cerrado (B5, 1).

Corrida final de la corrección: `typecheck` sin errores, `npm test` del frontend con 195 tests en 15 archivos, en verde (24 s), `build` sin avisos
y `lint` con el error del desvío 15, corregido.

### Entrega 0.2b

**COMPLETADA el 2026-11-12 (R-062)**, en `feature/front-0-2b-permisos`: la implementación en `68c68b6` (WIP), la primera
validación en el navegador en `023a90e` (WIP), las correcciones de la revisión en `e77f64a`, `91dca14` y `b1bc017` (WIP) y el
commit de cierre (solo documentación). Sin merge a `main`: todo se integra al cerrar la F0 (R-059).

**Qué se hizo.** Según el [diseño](f0-base.md#02b-permisos-sucursal-perfil-y-playwright) (actualizado en el cierre con la revisión): `can()`,
`useCan()`, `<RequirePermission>` y `requirePermission()` en el `beforeLoad` de cada ruta, con el menú filtrado y
`/configuracion` provisoria solo para el administrador; un 403 `FORBIDDEN` al cargar muestra la página de sin permiso con el
`message` de la API; el selector de la sucursal actual en la barra (`PATCH me/current-branch`, `me` vuelto a pedir al servidor,
las consultas de la sucursal invalidadas por la convención de `query-keys.ts` y `branch-changed` entre pestañas), `me` vuelto a
pedir al volver a la pestaña; `/perfil` con los datos y el cambio de la contraseña propia (la actual, la nueva y la repetición,
cada una con el botón para mostrarla; 422 por campo, 429 con la cuenta regresiva, la sesión sigue), sin contraseñas en la caché de
mutaciones; y Playwright 1.56.1 con el Edge instalado contra `syncvet_e2e`, 11 casos. La pestaña nueva espera la sesión de la
abierta 1000 ms (antes 300). Las reglas quedaron en [`frontend/CLAUDE.md`](../../../../frontend/CLAUDE.md).

**Validación.** No hubo pruebas manuales del usuario: a su pedido, las hizo Claude Code en Edge sin ventana contra el backend real
(`syncvet.test`) y `npm run dev`, dos veces (las 14 pruebas de la implementación y las 11 de la corrección, en las tablas de
abajo), con los datos de la demo restaurados y verificados. Playwright, **11 de 11 en dos corridas seguidas** sobre el código final
(`b1bc017`).

**Cifras.** La corrida del cierre, sobre `b1bc017` (el cierre solo cambia la documentación), desde `frontend/`, sola en la PC:
`npm run typecheck`, `npm run lint` y `npm run build` sin errores ni avisos, y `npm test` en 29,9 s:

Corrida de `npm test` del frontend (2026-11-12): 269 tests en 21 archivos, en verde.

Una corrida anterior, en paralelo con `composer test:fast`, tuvo una falla: el test de B7 de `ProfilePage.test.tsx` (escribe seis
campos con `userEvent`) pasó los 5 s del límite por la carga de la PC; sola, la corrida pasó entera (desvío 15). El backend no cambió
en esta entrega (ningún archivo de `backend/` desde el cierre de la 0.2a): `composer test:fast`, después de la limpieza de la raíz,
1445 casos, 1431 en verde y 14 omitidos (solo de MySQL).

| # | Desvío del cierre | Motivo | Estado |
|---|---|---|---|
| 15 | La primera corrida completa del cierre falló en un test por tiempo (el de B7, 5 s) | Corría en paralelo con la suite del backend; sola, 269 de 269 | Informativo |
| 16 | La espera de la pestaña nueva pasa de 300 a 1000 ms (`SESSION_REQUEST_TIMEOUT_MS`), y Playwright espera a que la sesión llegue (`openSecondTab`) | El caso de vencimiento falló una vez en la primera corrida en una PC nueva; en la PC de una recepción el equipo puede ser lento | Decisión del usuario (R-062) |

Implementación del 2026-11-12, en `feature/front-0-2b-permisos` (commit WIP), según el diseño detallado de arriba. Sin cambios en
`backend/`, en el validador ni en las skills. El roadmap y el handoff quedan para el cierre (`frontend/CLAUDE.md` §8).

**Cifras de la implementación** (desde `frontend/`, una vez al final, salvo lo que dice el desvío 11):

* `npm run typecheck`, `npm run lint` y `npm run build`: sin errores ni avisos.
* `npm test` del frontend: 248 tests en 20 archivos, en verde (24,4 s; 29 s en la repetición del desvío 14). Antes, 195 en 15:
  suma 53 casos y 5 archivos (`permissions.test.ts`, `permissions.test.tsx`, `BranchSelector.test.tsx`, `ProfilePage.test.tsx` y
  `src/test/e2e-database.test.ts`). La línea de la corrida se registra en "Resultado" en el cierre: hasta entonces, `/validar-docs`
  marca esta cifra y la cantidad de archivos (20 contra los 15 de la corrida registrada de la 0.2a), como en todo WIP.
* `npm run test:e2e` (Playwright, Edge, contra `syncvet_e2e`): 9 de 9 en verde, en 1 min 34 s en total (la preparación de la base
  es más de la mitad; los casos, unos 50 s). La primera corrida, antes del desvío 8, también 9 de 9 (2 min 37 s, en frío).
* Con `E2E_DB_DATABASE=syncvet npm run test:e2e`, la preparación se niega antes de tocar nada ("La preparación de Playwright solo
  corre contra la base syncvet_e2e y recibió \"syncvet\": no se toca nada.").

**Mutaciones**, cada una de a una con solo sus tests, las 5 detectadas: el menú sin filtrar por permisos (fallan 4 de 23); la ruta
`/configuracion` sin `requirePermission` (2 de 14); no invalidar las consultas al cambiar de sucursal (1 de 7); no publicar
`branch-changed` (1 de 7); la preparación de Playwright sin controlar el nombre de la base (8 de 9).

**Desvíos y decisiones de la implementación.**

| # | Desvío o decisión | Motivo | Estado |
|---|---|---|---|
| 1 | `/configuracion` provisoria ("En construcción"), con `system-settings.update`: el administrador la ve en el menú y en el inicio; recepción y veterinario van a `/sin-permiso` | En la F0 no había ninguna ruta con permiso: los tres roles veían el mismo menú | Decisión del usuario (2026-11-12) |
| 2 | La preparación exige el nombre exacto `syncvet_e2e` (el diseño decía "termina en `_e2e`"); además controla la base que resuelve Laravel con ese entorno (`artisan tinker`) y que el backend de `:8010` use la base recién preparada: la contraseña del `DemoSeeder` se genera al azar en cada corrida y solo ese backend la acepta | El nombre exacto lo pidió el usuario; los otros dos controles cubren una configuración cacheada y un backend levantado contra otra base, que el nombre solo no ve | Aceptado en la revisión, con la contraseña en `e2e/.e2e-credentials` (ver Revisión y correcciones) |
| 3 | La preparación crea por la API una segunda sucursal ("Sucursal Norte", `NOR`) y se la asigna a `admin-a` | El `DemoSeeder` da una sola sucursal por usuario: sin otra, no hay cambio de sucursal que probar. Sin tocar el backend | Aceptado (usuario, R-062) |
| 4 | Playwright levanta el backend (`php artisan serve --no-reload`, `127.0.0.1:8010`, `DB_DATABASE=syncvet_e2e`) y Vite (`:5174`) como `webServer`; si ya hay un backend en `:8010`, lo usa | El pedido decía "backend levantado contra `syncvet_e2e`": así no hay que levantarlo a mano, y uno levantado a mano también sirve (el control del desvío 2 frena uno contra otra base) | Aceptado (usuario, R-062) |
| 5 | Playwright usa el Edge instalado (`channel: 'msedge'`; `E2E_BROWSER_CHANNEL` para otro) y no descarga navegadores | Sin bajar ~150 MB por PC (`frontend/CLAUDE.md` §8). Edge viene con Windows | Pedido del usuario (2026-11-12), documentado en `frontend/README.md`, `testing.md` y `setup.md` |
| 6 | `npm audit` da ahora 10 avisos moderados, no 1 | Es el mismo aviso de la [deuda 19](../../deuda-tecnica.md) (`minimatch` 10.2.6, GHSA-g3hv-75wg-m249): npm 11 lo cuenta en cada paquete que lo trae (`eslint`, `@eslint/config-array`, los de `typescript-eslint`). `@playwright/test` 1.56.1 no aparece | Informativo; la deuda 19 sigue igual |
| 7 | `/perfil` sin el campo de confirmación de la contraseña nueva | La API no lo pide (`UpdateOwnPasswordRequest`) y los dos campos tienen el botón para mostrarla | Revertido en la revisión: se suma la repetición (ver Revisión y correcciones) |
| 8 | `permissions.tsx` del diseño queda en dos archivos: `permissions.ts` (`useCan`, `requirePermission`) y `RequirePermission.tsx` | ESLint (`react-refresh/only-export-components`): un archivo con un componente no exporta otras cosas | Informativo |
| 9 | Un test de la 0.2a cambió: "sin sucursal actual, la barra lo dice" pasa a ser sin sucursales asignadas; con una asignada, la barra ofrece el selector con "Elegí tu sucursal" (test nuevo) | Es la conducta nueva del selector | Informativo |
| 10 | Playwright no prueba "credenciales incorrectas" ni el vencimiento de la sesión, que estaban en la tabla de tests del diseño original y en el [criterio de cierre](f0-base.md#criterio-de-cierre) de la F0 ("el vencimiento, probado de punta a punta") | El pedido de la entrega listó los casos (roles y menú, ruta sin permiso, sucursal, contraseña, salir); el vencimiento lo cubren Vitest y la validación en el navegador de la 0.2a | Revertido en la revisión: los dos casos sumados (ver Revisión y correcciones) |
| 11 | `lint` corrió dos veces completo y `npm test` dos veces, no una; Playwright, dos veces | El primer `lint` encontró 5 errores (desvío 8 y tres `as` en un test); el primer `npm test`, el test del desvío 9. Playwright pasó entero antes del cambio del desvío 8 y se repitió sobre el código final | Informativo |
| 12 | `src/routeTree.gen.ts` se regeneró solo al crear las rutas nuevas | Lo hizo un proceso de la PC (probablemente un `npm run dev` abierto); el contenido es el del plugin del router | Informativo |
| 14 | El test de la guarda de la base pasa de `e2e/support/database.test.ts` (como decía el diseño) a `src/test/e2e-database.test.ts`; Vitest vuelve a incluir solo `src/` | `/validar-docs` cuenta los archivos de test solo en `frontend/src`: con el test en `e2e/`, la corrida registrada (20 archivos) no habría coincidido nunca con los 19 de `src/`. Se repitió `npm test` (248 en 20) | Informativo |
| 13 | El desvío 9 de la 0.1 (`/sin-permiso` dentro del layout con sesión) queda confirmado | Pedido del usuario; anotado en la tabla de la 0.1 | Confirmado |

**Validación en el navegador de Claude Code (2026-11-12, pedido del usuario),** como en la 0.2a: Edge sin ventana (`channel:
'msedge'`, con el `@playwright/test` ya instalado, sin instalar nada más), contra el backend real (`syncvet.test`, base `syncvet`)
y el `npm run dev` que estaba levantado (`http://localhost:5173`), sobre el código del commit WIP. Un script fuera del repositorio;
las capturas, fuera del repositorio (`%TEMP%\claude\…\scratchpad\capturas`, en la PC de la validación: no se versionan).

| # | Prueba | Resultado | Detalle |
|---|---|---|---|
| 1 | `admin-a`: ingreso y menú completo | OK | Menú "Inicio" y "Configuración"; el botón "Configuración" del inicio, visible; `/configuracion` muestra "En construcción" |
| 2 | `recepcion-a`: menú reducido | OK | Solo "Inicio"; sin el botón del inicio |
| 3 | `vet-a`: menú reducido | OK | Solo "Inicio"; sin el botón del inicio |
| 4 | `recepcion-a` y `vet-a`: URL sin permiso | OK | `/configuracion` lleva a `/sin-permiso`, con la barra y el menú a la vista (desvío 13) |
| 5 | `recepcion-a` y `vet-a`: 403 de la API | Parcial | `GET /api/users` con el token de la sesión: 403 `FORBIDDEN`, "No tenés permisos para realizar esta acción.". En la F0 ninguna pantalla pide algo que el rol no tiene, así que no hay un camino real para ver ese mensaje en la interfaz: lo cubren los tests de Vitest de `RouteErrorPage` (al cargar) y de `/perfil` (en una acción) |
| 6 | Cambio de sucursal desde la barra | OK | `admin-a` con "Casa central" y "Sucursal de usuarios (Postman)" (asignada solo para la prueba): el aviso "Tu sucursal actual es…", la barra, el inicio y `GET /api/me` (`current_branch.id` 4) cambian; otra pestaña abierta también (`branch-changed`); sigue al recargar; vuelta a "Casa central" desde la barra. La primera corrida falló por el script (esperaba que un `<option>` fuera visible); corregida la espera, pasó |
| 7 | Usuario sin sucursal actual | OK | `recepcion-a` con la sucursal 4 como actual y la 4 inactivada (la API le deja la actual en `null`): el aviso "Elegí tu sucursal actual en la barra de arriba para empezar a trabajar.", el inicio con "Todavía no elegiste tu sucursal actual." y el selector en "Elegí tu sucursal"; al elegir "Casa central" desde la barra, el aviso desaparece |
| 8 | `/perfil`: contraseña actual incorrecta | OK | 422 en "Contraseña actual": "La contraseña actual no es correcta."; la nueva, sin marcar |
| 9 | `/perfil`: contraseña nueva débil | OK | 422 en "Contraseña nueva", con los cuatro mensajes de la API juntos (12 caracteres, mayúsculas y minúsculas, símbolo y número) |
| 10 | `/perfil`: cambio correcto | OK | `vet-a` a una contraseña temporal: el aviso de éxito y los campos vacíos; al recargar, la misma sesión sigue abierta |
| 11 | `/perfil`: vuelta a `DEMO_PASSWORD` | OK | Desde `/perfil` (la temporal como actual); después, el ingreso por la API con `DEMO_PASSWORD` responde 200 |
| 12 | `/perfil`: mostrar u ocultar | OK | Un botón por campo: `password` → `text` → `password`, sin tocar el otro campo |
| 13 | Ancho de tablet (768 px), los tres roles | OK | Sin scroll horizontal; la barra (clínica, sucursal, usuario y "Salir") y el menú, enteros. A 768 px el menú se ve sin sus íconos (Tabler los oculta por debajo de `md`) |
| 14 | Consola | OK | Sin errores ni avisos de la aplicación. Solo los registros de red de las respuestas esperadas: el 403 de la prueba 5 (dos) y el 422 de las pruebas 8 y 9 (dos) |

**Datos de la demo.** Se cambiaron por la prueba y se restauraron, todo por la API o desde la interfaz: a `admin-a` y a `recepcion-a`
se les asignó la sucursal 4 ("Sucursal de usuarios (Postman)", activa, creada por las colecciones de Postman) y se les volvió a dejar
solo "Casa central"; la sucursal 4 se inactivó y se volvió a activar; la sucursal actual de `recepcion-a` quedó en `null` y se
volvió a "Casa central" desde la barra; la contraseña de `vet-a` se cambió y se volvió a `DEMO_PASSWORD`. Verificado al final por
la API: los tres con "Casa central" como actual y como única asignada, la sucursal 4 activa y el ingreso de los tres con
`DEMO_PASSWORD` (200). Quedan rastros que no se deshacen: los registros de la auditoría de esos cambios; los tokens de los
ingresos de la prueba se cerraron (y cambiar la contraseña de `vet-a` revocó los demás tokens que tuviera abiertos).

**Observación para la revisión** (no se corrigió: es de la 0.2a). Un campo con botón (`PasswordField`, el `addon` de `TextField`)
con un error dibuja el borde rojo alrededor del grupo entero, también del mensaje de abajo (se ve en la captura de la prueba 8). Le
pasa también a `/ingresar`.

**Para probar a mano el cambio de sucursal:** en la base `syncvet` los usuarios del `DemoSeeder` tienen una sola sucursal; hay que
asignarle otra por la API (como en la prueba 6) o usar `syncvet_e2e` después de un `npm run test:e2e`, donde `admin-a` tiene además
"Sucursal Norte". En `syncvet_e2e` la contraseña de los usuarios **no es la `DEMO_PASSWORD` de `backend/.env`**: se genera en cada
corrida y queda en `frontend/e2e/.e2e-credentials` (ignorado por Git; desvío 2), y hace falta un backend levantado contra esa base
(`DB_DATABASE=syncvet_e2e`, como el de `:8010` que levanta Playwright).

**Revisión y correcciones (2026-11-12).** La revisión de la 0.2b, en una sesión aparte, dejó hallazgos de gravedad media (M1 y M2)
y baja (B1 a B7), y decisiones sobre los desvíos 2, 7 y 10. Se corrigieron según lo que decidió el usuario, en dos sesiones (commit
WIP `e77f64a` y este), la segunda en otra PC:

| # | Hallazgo o decisión | Corrección | Estado |
|---|---|---|---|
| M1 | Las contraseñas de `/ingresar` y de `/perfil` quedaban en la caché de mutaciones de Query (las variables de la mutación) | `gcTime: 0` y `mutation.reset()` en `onSettled` en las dos; el aviso de sesión cerrada de `/ingresar` pasa a depender de un estado propio ("ya se intentó"), no de `mutation.isIdle`. Tests: después del 200 y de cada error, ninguna mutación en la caché tiene la contraseña | Corregido |
| M2 | `me` (`staleTime: Infinity`) no se volvía a pedir: una sucursal inactivada por un administrador o elegida en otra PC no se veía hasta recargar | `SessionLayout` vuelve a pedir `me` (solo la consulta activa, sin cancelar una en curso) con `focus` y `visibilitychange` a la vista. Test con `focus` y con `visibilitychange` | Corregido |
| B1 | El borde rojo del campo con botón (`PasswordField`) encerraba también el mensaje de error (la observación de la validación) | En `TextField`, el mensaje sale del `input-group` y se muestra con `d-block`. Vale para `/perfil` y para `/ingresar`. Test y validación en el navegador | Corregido |
| B2 | El `redirect` a `/sin-permiso` sin `replace` dejaría la ruta prohibida en el historial | Ya tenía `replace: true`: el riesgo no era real. Se suma el test (Atrás vuelve a la página anterior, no a la prohibida) | Sin cambio de código; test nuevo |
| B3, B4, B7 | Mutaciones que sobrevivían: el selector habilitado durante el `PATCH` (B3); un 403 de negocio tratado como `FORBIDDEN` y "Reintentar" que no recargaba la ruta (B4); el aviso de éxito de `/perfil` que no se borraba al volver a intentar (B7) | Un test por cada una | Corregido |
| B5 | Con el 200 del `PATCH me/current-branch`, `me` se tomaba de la respuesta: con dos pestañas cambiando a la vez, una podía quedar con la sucursal que no quedó guardada | `applyBranchChange` vuelve a pedir `me` al servidor (`staleTime: 0`) y no usa la respuesta del `PATCH` | Corregido |
| B6 | Al cambiar de sucursal se invalidaban todas las consultas, también las de la clínica | Convención de claves en `src/lib/api/query-keys.ts` (`branchKey`, `invalidateBranchQueries`), documentada en `frontend/CLAUDE.md` §5: el cambio, en esta pestaña y en las otras (`branch-changed`), invalida `['me']` y solo las consultas de la sucursal | Corregido |
| Desvío 2 | La contraseña de la corrida de Playwright solo vivía en la memoria del proceso: no se podía entrar a mano a `syncvet_e2e` | La preparación (ahora en `e2e/support/prepare.ts`, con dependencias inyectadas y su test `src/test/e2e-prepare.test.ts`) la escribe al final en `frontend/e2e/.e2e-credentials`, ignorado por Git; documentado en `setup.md` y `frontend/CLAUDE.md` | Aceptado con el archivo |
| Desvío 7 | `/perfil` sin la repetición de la contraseña nueva | Decisión del usuario: se suma "Repetí la contraseña nueva", controlada solo en el navegador (Zod; la API no la recibe). Distinta, el error en su campo sin llamar a la API | Revertido |
| Desvío 10 | Playwright sin "credenciales incorrectas" ni el vencimiento | Se suman los dos casos (11 en total): el mensaje de la API, la contraseña borrada y sin sesión; `expires_at` vencido en `sessionStorage` cierra las dos pestañas con ese token | Revertido |

Actualizados en el cierre, en el [diseño detallado](f0-base.md#02b-permisos-sucursal-perfil-y-playwright): los flujos 2 y 3, las filas de
`/perfil`, `api.ts`, `channel.ts` y `session.ts` de la tabla de archivos (con `query-keys.ts`, `e2e/support/prepare.ts` y
`openSecondTab`), la fila "Perfil" de los tests de Vitest y los casos de Playwright, con lo que dejaron el desvío 7, M1, M2, B5 y B6.

B8: el pedido del cierre nombra las correcciones "B1 a B8"; en el repositorio y en los commits de la corrección solo constan B1 a
B7. PENDIENTE DE VERIFICACIÓN.

**Cifras de la corrección** (en la PC nueva, recién clonada; desde `frontend/`):

* `npm run typecheck`, `npm run lint`, `npm test` y `npm run build`, una sola corrida completa: sin errores ni avisos. `npm test`:
  269 tests en 21 archivos, en verde (antes de la corrección, 248 en 20: suma `src/test/e2e-prepare.test.ts` y los tests de la
  tabla de arriba).
* Mutaciones de la corrección: 14, todas detectadas (en la sesión del commit `e77f64a`).
* `npm run test:e2e` (Edge, contra `syncvet_e2e`; la base no existía en esta PC y la creó la preparación): la primera corrida, 10 de
  11. Falló "vencimiento": la segunda pestaña no llegó a mostrar la sesión de la primera en 10 s (la traza quedó cortada). Ese caso
  solo pasó, y la suite completa repetida, **11 de 11** (2,3 min). Es intermitente: la pestaña nueva espera la sesión de la abierta
  300 ms (`SESSION_REQUEST_TIMEOUT_MS`, P1) y la primera corrida en una PC nueva es más lenta (Vite compila en frío y el antivirus
  revisa cada archivo). En esa corrida no se cambió el código; lo resolvió la decisión siguiente.
* **Vencimiento intermitente, decisión del usuario (2026-11-12):** la pestaña nueva espera la sesión de la abierta **1000 ms**, no
  300 (`SESSION_REQUEST_TIMEOUT_MS` en `src/lib/auth/session.ts`): en la PC de una recepción el equipo puede ser lento. Actualizados
  el flujo 2 de la 0.2a, la fila "Pestaña nueva" de [Sesión (0.2)](f0-base.md#sesión-02) y `frontend/CLAUDE.md` §5. Y Playwright ya no supone
  que la sesión llegó: `openSecondTab` (`e2e/support/session.ts`), que usan los tres casos con dos pestañas (sucursal, salir y
  vencimiento), espera con su tiempo límite (`SHARED_SESSION_TIMEOUT_MS`, 20 s) a que la sesión esté en el `sessionStorage` de la
  pestaña nueva (`expect.poll`) y después en la barra; si no llega, falla ahí, con su mensaje, y no en un paso posterior.
  `npx vitest related` sobre lo tocado: 128 tests en 9 archivos, en verde. `npm run test:e2e` dos veces seguidas: **11 de 11** (3,4
  min) y **11 de 11** (2,9 min).

**Validación en el navegador de la corrección (2026-11-12),** como la de arriba: Edge sin ventana, contra `syncvet.test` (base
`syncvet`) y `npm run dev` (`http://localhost:5173`), con un script y las capturas fuera del repositorio.

| # | Prueba | Resultado | Detalle |
|---|---|---|---|
| 1 | `/perfil`: la repetición distinta | OK | "Las dos contraseñas nuevas no coinciden." en "Repetí la contraseña nueva", fuera del grupo con borde; ningún `PATCH` |
| 2 | `/perfil`: la repetición vacía | OK | "Repetí la contraseña nueva."; ningún `PATCH` |
| 3 | `/perfil`: la actual incorrecta | OK | 422 "La contraseña actual no es correcta." en su campo; el borde rojo rodea solo el campo y el mensaje queda abajo, afuera, también con el foco en el campo (B1) |
| 4 | `/perfil`: el cambio correcto | OK | `vet-a` a una contraseña temporal: el aviso de éxito, los tres campos vacíos y la sesión sigue al recargar |
| 5 | `/ingresar`: la contraseña vacía | OK | "Ingresá tu contraseña." fuera del grupo con borde (B1 también en `/ingresar`) |
| 6 | `/perfil`: la vuelta a `DEMO_PASSWORD` | OK | Desde `/perfil`; después, el ingreso por la API con `DEMO_PASSWORD` responde 200 |
| 7 | Cambio de sucursal desde la barra | OK | `admin-a` con "Sucursal de usuarios (Postman)" asignada para la prueba: el aviso, la barra y el inicio cambian, y la otra pestaña también (`branch-changed`) |
| 8 | Sigue al recargar | OK | `GET /api/me` con `current_branch.id` 4 |
| 9 | `me` al volver a la pestaña (M2) | OK | La sucursal se cambió a "Casa central" por la API con otro token (como desde otra PC); la pestaña no se enteró hasta el `focus`, y entonces la barra y el inicio pasaron a "Casa central" |
| 10 | La vuelta a "Casa central" desde la barra | OK | Ida y vuelta en las dos pestañas; "Salir" cerró las dos |
| 11 | Consola | OK | Sin errores ni avisos de la aplicación; solo el registro de red del 422 esperado de la prueba 3 |

**Datos de la demo.** A `admin-a` se le asignó la sucursal 4 y se le volvió a dejar solo "Casa central" (actual y única asignada);
la contraseña de `vet-a` se cambió y se volvió a `DEMO_PASSWORD` desde `/perfil`. Verificado al final en la base: los tres usuarios
de la Clínica A con "Casa central" como actual y como única asignada, y la sucursal 4 activa. Los tokens de la prueba se cerraron.

**Entorno de la PC nueva** (no es del código; para la próxima sesión en esa PC): el vhost `syncvet.test` de Laragon todavía apuntaba
a la raíz del repositorio (`C:/laragon/www/syncvet/public`, antes del monorepo) y respondía 404: se aplicó el paso 5 de
[Migración al monorepo](../../../04-desarrollo/setup.md#migración-al-monorepo-r-057) (`syncvet.test.conf` hacia `backend/public`,
sin el `auto.`) y se reinició nginx. **Limpieza de la raíz (paso 3, decisión del usuario en el cierre):** quedaban los archivos del
backend de antes del monorepo, sin versionar (unos 81 MB: `vendor/` 69 MB con 9118 archivos, `app/`, `tests/`, `database/` con las
bases SQLite de prueba, `config/`, `resources/`, `storage/`, `bootstrap/`, `public/`, `routes/`, `lang/`, `artisan`, `composer.json`,
`composer.lock`, `phpunit.xml`, `.env.example`, `.phpunit.result.cache` y las carpetas viejas de `docs/`: `fases/`, `testing/`,
`references/`, `decisions/`, `prompts/`, `development/`, `project/`, `roadmap/`, `modules/` y `handoff.md`). Antes de borrar se
comprobó que ninguna ruta tuviera archivos versionados y que 551 de los 552 archivos sueltos (sin `vendor/`) fueran idénticos a
alguna versión del historial de Git (el que no, `.phpunit.result.cache`, es una caché); sin fotos ni logos en `storage/app`. El
`.env` viejo (otra `APP_KEY`, sin `CORS_ALLOWED_ORIGINS`) se movió fuera del repositorio, a
`C:\laragon\www\syncvet-raiz-vieja.env`, como respaldo; el resto se borró. `syncvet.code-workspace` quedó. Después: `git status`
sin cambios en lo versionado, `composer test:fast` (desde `backend/`) 1445 casos, 1431 en verde y 14 omitidos (solo de MySQL), y
`npm test` en verde (las cifras del cierre, abajo). En `setup.md`, la ruta del paso 5 estaba rota (un `\e` y un
`\n` convertidos en caracteres de control): corregida. El `backend/.env` de esa PC tiene `DEMO_PASSWORD` entre comillas dobles, no
simples como pide `setup.md`.

### Cierre de la F0

**COMPLETADA el 2026-11-12 (R-063)**, con la entrega de soporte del backend (front-00). Las ramas se encadenaron
(`docs/frontend-analisis` → `feature/front-00-me-sucursales` → `feature/front-0-1-esqueleto` → `feature/front-0-2a-sesion` →
`feature/front-0-2b-permisos`), todas en GitHub, y se integran a `main` con un merge `--no-ff` desde `feature/front-0-2b-permisos`,
autorizado por el usuario (R-063). Las ramas se conservan.

**Qué se construyó.** El frontend nace en `frontend/` con el stack de R-058 (Node 26, React 19, Vite 8, TypeScript 6 estricto,
TanStack Router y Query, React Hook Form con Zod, date-fns con `@date-fns/tz`, Tabler sin su JS, Vitest con MSW y Playwright con el
Edge instalado): el cliente de la API sobre `fetch` con `ApiError` y la tabla de `error_code`, las fechas en `America/Asuncion` y los
guaraníes, la capa de componentes sobre Tabler, el test de voseo con las listas del backend, la sesión contra la API real (token en
`sessionStorage`, aviso previo, vencimiento, cierre por token y entre pestañas, pestaña nueva que pide la sesión), los permisos (menú
y rutas según `me.permissions`), el selector de la sucursal actual y `/perfil` con la contraseña propia. Rutas del frontend:
`/ingresar`, `/`, `/perfil`, `/configuracion` (provisoria, solo el administrador), `/sin-permiso` y la página de no encontrado. Del
lado del backend (front-00): `me.branches`, CORS por `CORS_ALLOWED_ORIGINS` y `meta.pagination` en `snake_case`. En la cadena, contra
`main`: 112 archivos de `frontend/` y 38 de `backend/` (23 de `app/`, `config/`, la documentación del backend y `.env.example`; 15 de
tests).

| Entrega | Contenido | Commits | Al cerrar |
|---|---|---|---|
| Diseño | Análisis del frontend y diseño de la F0 y de front-00 | `db76757` (R-058) | — |
| [front-00](../backend/front-00-soporte-del-frontend.md#resultado) | B1 a B3: sucursales en `me`, CORS y paginación en `snake_case` | `cec996c`, `9495067`, `3615e60`, `bdaa78f` (cierre `ce5da95`) | Backend: 1445 casos; 14183 aserciones en SQLite y 14257 en MySQL |
| [0.1](#entrega-01) | Esqueleto, sin la API real | `72e75bc`, `b5d3ecf` (cierre `76047b2`) | Frontend: 93 tests en 9 archivos |
| [0.2a](#entrega-02a) | Sesión | `93e347c`, `ece1faf` (cierre `6ddb195`) | Frontend: 195 tests en 15 archivos |
| [0.2b](#entrega-02b) | Permisos, sucursal, `/perfil` y Playwright | `68c68b6`, `023a90e`, `e77f64a`, `91dca14`, `b1bc017` (cierre `2cd113a`) | Frontend: 269 tests en 21 archivos; Playwright, 11 casos |

**Regresión del cierre** (2026-11-12, en `feature/front-0-2b-permisos` sobre `2cd113a`; el código es el de `b1bc017`):

* **Backend**, desde `backend/`, en serie: `composer test` (SQLite), 1445 casos, 1431 en verde y 14 omitidos (solo de MySQL), 14183
  aserciones (4 min 33 s); después `composer test:mysql` (`syncvet_test_mysql`), 1445 casos en verde, sin omitidos, 14257 aserciones
  (7 min 15 s). Frente a `main` antes de la F0 (monorepo, R-057: 1395 casos; 13964 y 14038 aserciones), 50 casos más, todos de
  front-00. Rutas, permisos y migraciones sin cambios: 78, 135 y 36. `composer audit`: "No security vulnerability advisories found.".
* **Frontend**, desde `frontend/`: `npm run typecheck`, `npm run lint` y `npm run build`, sin errores ni avisos; `npm test` en 33 s;
  `npm run test:e2e` (Playwright, Edge, contra `syncvet_e2e`, recreada por la preparación), 11 de 11 en 2,3 min. `npm audit`: 10
  avisos moderados, todos el mismo, `minimatch` (GHSA-g3hv-75wg-m249; [deuda 19](../../deuda-tecnica.md)).
* **`npm run preview`** (PENDIENTE DE VERIFICACIÓN desde la 0.1): verificado por HTTP, no en el navegador: sirve `dist/` (`/ingresar`
  responde 200 con el bundle del build, una URL inventada también cae en la aplicación) y `/api/me` pasa por el proxy al backend
  (401 en JSON).

Corrida de `npm test` del frontend (2026-11-12): 269 tests en 21 archivos, en verde.

**Colecciones de Postman** (R-057: todas las de `docs/05-pruebas/postman`). Newman 6.2.3, desde la raíz, con el servidor levantado
desde `backend/` en el puerto 8001 contra la base de desarrollo; una vuelta, sin fallas: 483 pedidos y 1072 aserciones.

| Colección | Pedidos | Aserciones | Fallas |
|---|---|---|---|
| Clientes | 24 | 48 | 0 |
| Especies | 41 | 93 | 0 |
| Foto | 23 | 60 | 0 |
| Pacientes | 30 | 61 | 0 |
| Auditoría | 16 | 47 | 0 |
| Configuración | 24 | 67 | 0 |
| Servicios | 27 | 64 | 0 |
| Sucursales | 44 | 123 | 0 |
| Usuarios | 54 | 136 | 0 |
| Veterinarios | 32 | 75 | 0 |
| Agendas | 35 | 68 | 0 |
| Disponibilidad | 50 | 81 | 0 |
| Series | 25 | 42 | 0 |
| Turnos | 58 | 107 | 0 |

**Criterio de cierre**, punto por punto:

| Criterio | Evidencia |
|---|---|
| La 0.1, la 0.2a y la 0.2b cerradas | R-060, R-061 y R-062, cada una con su revisión, sus correcciones y su validación en el navegador ([0.1](#entrega-01), [0.2a](#entrega-02a), [0.2b](#entrega-02b)) |
| Los tests de Vitest y de Playwright en verde | `npm test`, 269 de 269 en 21 archivos; `npm run test:e2e`, 11 de 11 (corridas de este cierre) |
| `lint`, `typecheck` y `build` sin errores | Sin errores ni avisos (corridas de este cierre) |
| Con `syncvet_e2e`: ingresar | `permisos.spec.ts`: los tres roles ingresan y ven su menú; una ruta protegida sin sesión lleva a `/ingresar` y vuelve después de ingresar; `sesion.spec.ts`: credenciales incorrectas |
| Con `syncvet_e2e`: cambiar de sucursal | `sesion.spec.ts`, "cambio de sucursal": la barra, el inicio y la otra pestaña, y la vuelta a la original |
| Con `syncvet_e2e`: salir en todas las pestañas | `sesion.spec.ts`, "salir en una pestaña cierra la otra" |
| Con `syncvet_e2e`: el vencimiento | `sesion.spec.ts`, "vencimiento": `expires_at` pasado cierra la sesión en las dos pestañas con ese token |
| Las colecciones de Postman en verde (R-057: todas) | Las 14 con Newman, sin fallas (tabla de arriba) |

**Deudas** ([`deuda-tecnica.md`](../../deuda-tecnica.md)). Nuevas en la F0: 19 (`npm audit`, `minimatch`, solo en las herramientas de
desarrollo; R-060) y 20 (el validador no ignora los bloques de código en "Resultado"; 0.2b), las dos de prioridad baja. Ninguna del
backend se abrió ni se cerró. Abiertas al cerrar: del backend, 2, 3, 5, 6, 15 y 17; del frontend, 19 y 20.

**Decisiones de la fase.** R-058 (análisis del frontend: stack, sesión, tipos a mano, rutas en castellano, proxy, Playwright con
`syncvet_e2e`, el orden F0 → F1 → F2 → F3 → FASE 4 y el diseño de la F0 y de front-00), R-059 (cierre de front-00 y la cadena de
ramas, con el merge a `main` al cerrar la F0), R-060 (cierre de la 0.1: la cifra de tests del frontend sale de la corrida registrada,
la deuda 19 y la división de la 0.2 en 0.2a y 0.2b), R-061 (cierre de la 0.2a: el cierre por token, "Tu sesión terminó" y las
cifras de los resultados cerrados fuera de `/validar-docs`), R-062 (cierre de la 0.2b: `/configuracion` provisoria, la preparación
de `syncvet_e2e`, Edge, la repetición de la contraseña y la espera de 1000 ms de la pestaña nueva) y R-063 (este cierre y el merge),
en el [registro](../../../01-arquitectura/decisiones/registro.md).

**Quedan PENDIENTE DE VERIFICACIÓN:** el desvío 8 de la 0.1 (`toApiDateTime` con `-03:00`), que pasa a la F3, la primera que manda
fechas con hora a la API; y B8 de la revisión de la 0.2b (el pedido nombraba B1 a B8; en el repositorio constan B1 a B7).

**Pendiente para la F1** (clientes y pacientes, sobre la API de la FASE 1 del backend): el análisis y el diseño, con sus preguntas,
antes de cualquier código. Lo que la F0 deja preparado y la F1 estrena: los listados con `getPage` y `meta.pagination`
(`DataTable` y la paginación), las claves de consulta de la clínica (`['clients', …]`, que no se invalidan al cambiar de sucursal),
los formularios con el 422 por campo (`applyValidationErrors`), el primer trato propio de un código de negocio (`*_IN_USE` ofrece
inactivar), la foto del paciente como descarga con token (`blob` y una URL de objeto) con `PAYLOAD_TOO_LARGE` en su campo, las
fechas sin hora (`birth_date`, `formatDateOnly`) y la primera entrada de un módulo en el menú, con su permiso.
