# F3 — Agenda y turnos (frontend): resultados

El resultado de cada entrega, el cierre de la fase y lo que vino después. El diseño y las decisiones, en [`f3-agenda.md`](f3-agenda.md).

## Resultado

### Entrega 3.1a

Estado: **COMPLETADO** el 2026-11-18 (R-086). Las agendas en `/configuracion/agendas` (listado, detalle, alta, edición con el
horario semanal, inactivar o activar y baja), solo para el administrador, en `feature/front-3-1a-agendas` (desde
`docs/front-f3-diseno` en `df2588a`), sin integrar a `main` (se integra al cerrar la F3). Commits: `549f1c6` (WIP de la
implementación, el 2026-11-17), `f0f463a` (WIP de la revisión, el 2026-11-18) y el de este cierre (R-086: la documentación). Sin
cambios en el backend ni paquetes nuevos.

**Hecho.** `features/schedules` (tipos, API, búsqueda, reglas del formulario, listado, detalle, formulario con el horario semanal,
inactivar o activar y baja), las cuatro rutas de `/configuracion/agendas` con `requirePermission` (`schedules.update`, `.store`;
la baja con `schedules.destroy`), la tarjeta "Agendas" en `/configuracion`, tres permisos en `PERMISSIONS`, el ayudante de
Playwright `e2e/support/api.ts` (sesión por la API en `syncvet_e2e` y limpieza de las agendas de un spec) y `e2e/agendas.spec.ts`;
`e2e/permisos.spec.ts` suma la tarjeta y el caso del recepcionista y el veterinario en `/configuracion/agendas` (R-084).

**Desvíos** (los 9, aceptados por el usuario; ninguno cambia el comportamiento del diseño):

| # | Desvío | Motivo |
|---|---|---|
| 1 | El error de un día del horario (tramos superpuestos, más de 6) va en el tramo que lo provoca (su "Desde"), no en una clave `hours` del día | Así el foco y el mensaje quedan en el tramo a corregir; la clave `hours` que mande la API (y `hours.N.weekday`) va a la alerta de arriba, porque no hay un campo del día |
| 2 | El color tiene una casilla "Con color en la agenda" | `<input type="color">` no puede quedar vacío; sin la casilla el color va en `null` |
| 3 | El veterinario se elige entre los activos de la clínica de un solo pedido (`GET veterinarians?per_page=100`, `['veterinarians', 'options']`), filtrados en el navegador por la sucursal elegida y el usuario activo; cambiar la sucursal suelta el veterinario que no trabaja en ella | El diseño decía `GET veterinarians?branch_id=`: así no hay un pedido por cada cambio de sucursal. Los servicios, igual (`['services', 'options']`). En la edición, el veterinario o el servicio que ya no está activo se muestra con "(actual)" |
| 4 | "Copiar el lunes a los días hábiles" queda deshabilitado si el lunes no tiene tramos | Copiar un lunes vacío borraría los tramos del martes al viernes sin aviso |
| 5 | La mutación 4 (no soltar el veterinario al cambiar de sucursal) pasó la primera vez: el test miraba el `<select>`, que se ve vacío sin la opción aunque el formulario conserve el valor | Se reforzó el test para mirar el cuerpo del `PATCH` (`veterinarian_id: null`); con eso la mutación falla |
| 6 | El control 2 en Edge encontró que "Sumar un tramo" no llevaba el foco al "Desde" del tramo nuevo | Corregido: el tramo se dibuja desde `fields` (antes, desde `useWatch`, un render más tarde) y el foco se mueve con `setFocus` después del render; test nuevo en `SchedulePages.test.tsx` |
| 7 | En la edición recién cargada, el veterinario se veía "(actual)" mientras la lista de veterinarios todavía no había llegado | Corregido: "(actual)" solo con la lista cargada. La captura `05-formulario-375.png` es de antes de la corrección |
| 8 | Durante los controles en Edge, el PHP de Laragon dejó de responder (`syncvet.test` daba 502 y no quedó ningún `php-cgi`) | No se tocó Laragon: los controles siguieron con un `php artisan serve` propio en `:8020` sobre la misma base de la demo y Vite en `:5180`, los dos detenidos por su PID. El usuario reinició Laragon; en la revisión `syncvet.test` respondió y los controles se repitieron contra él (abajo) |
| 9 | Playwright, la primera corrida de `agendas.spec.ts` falló por el script: `getByLabel('Sucursal')` también encontraba el selector "Sucursal actual" de la barra | `exact: true` en las etiquetas; 1 de 1 después |

**Mutaciones** (4 de 4 detectadas, revertidas): el horario vacío sin la clave `hours` (2 fallas); la superposición sin controlar en
el navegador (3 fallas); 23:55 rechazado (2 fallas); el veterinario sin soltar al cambiar de sucursal (1 falla, con el test del
desvío 5).

**En Edge, en la implementación** (sin ventana, `es-PY`, contra la demo con el backend propio del desvío 8; 8 de 8): (1) la tarjeta lleva al listado; (2)
"Sumar un tramo" con Enter deja el foco en "Desde" y las horas se escriben con el teclado; (2b) "Quitar" con Enter devuelve el foco
a "Sumar un tramo al lunes"; (3) el color deshabilitado sin la casilla, mandado `#E8590C` y mostrado en el detalle; (3b) "Copiar el
lunes": cinco tramos de lunes a viernes; (4) a 375 px, el detalle, el formulario y el listado sin desplazamiento horizontal; (5) la
agenda temporal ("Control Edge …") eliminada por la interfaz (0 en la base después); (6) la consola sin errores. Cada corrida
ingresó y salió como `admin-a` (sus tokens revocados; los ingresos quedan en la auditoría, como en la F2); `admin-a`, `recepcion-a`
y `vet-a` sin cambios. Capturas fuera del repositorio (scratchpad de la sesión, `capturas-3-1a`).

**Pruebas.** Vitest de lo tocado: 70 de 70 en `features/schedules` (3 archivos) y `vitest related` del permiso y la tarjeta, 600
de 600 en 28 archivos. Playwright: `agendas.spec.ts` 1 de 1 (repetido después de las correcciones de los desvíos 6 y 7, 1 de 1 en 1,1 min) y `permisos.spec.ts` 7 de 7. Corrida final, el 2026-11-17, desde `frontend/`: `npm test`, 1401 tests en 67 archivos, en verde; `typecheck`, `lint` y `build` sin errores ni avisos; desde `backend/`, `FictitiousDocumentsTest`, 3 de 3.

**Revisión** (2026-11-18, sobre el WIP `549f1c6`, contra `df2588a`, con el presupuesto por entrega). `syncvet.test` respondió después
de que el usuario reiniciara Laragon (la primera petición tardó 23 s; las siguientes, 0,2 s): el desvío 8 queda resuelto.

* **Paridad del horario con la API** (`ScheduleMessages::validateHours`): bien. El mismo orden (la forma de cada hora; si alguna
  falla, nada más; el fin antes del inicio o igual, en su tramo y fuera de su día; por día, más de 6 o, si no, la superposición
  ordenando por inicio y comparando como texto, con tocarse permitido); 23:55 válida; el horario se manda entero en el orden del
  formulario (`hours: []` al vaciarlo) y las claves con índice del 422 caen en su tramo. El total de 42 sale del tope de 6 por día.
* **Veterinario por sucursal**: los activos con el usuario activo de la sucursal elegida (la API devuelve solo los activos sin
  `include_inactive`); al cambiar de sucursal se suelta el que no trabaja en ella, y en la edición se conserva el actual si se vuelve a
  su sucursal. Con turnos, cambiar la sucursal manda `veterinarian_id: null` y la API responde `SCHEDULE_NOT_EDITABLE`, que va a la
  alerta de arriba (con test).
* **Inactivar, activar y baja**: solo `{ is_active }`, con el aviso de los turnos que siguen (la API inactiva aunque haya turnos
  futuros); la baja con `SCHEDULE_IN_USE` muestra el mensaje de la API y "Inactivar" (o solo "Cerrar" si ya está inactiva).
* **Color**: validado por la API (`#RRGGBB`), así que el `style` de la muestra no recibe otra cosa; sin la casilla va en `null`.
* **Permisos**: la tarjeta con `schedules.update` (y `/configuracion` ya pide `system-settings.update`: los otros roles no llegan);
  las cuatro rutas con `requirePermission`; "Nueva agenda" y "Eliminar" con `useCan()`.
* **Foco y teclado**: "Sumar un tramo" lleva el foco a "Desde"; "Quitar" lo devuelve a "Sumar un tramo" del día (con 6 tramos el botón
  sigue enfocable, `focusableWhenInactive`).

**Hallazgo y corrección** (1, menor): el test de permisos de `SchedulePages.test.tsx` recorría la lista, el detalle y el alta, pero
no `/configuracion/agendas/$id/editar`; se sumó (2 casos nuevos: el recepcionista y el veterinario). Sin cambios en `src/` fuera de
los tests: ni el menú ni el permiso de una ruta cambiaron, así que no corresponde Playwright.

**Mutaciones de la revisión** (3 de 3 detectadas, revertidas): tocarse contado como superposición (`<` → `<=`, 4 fallas);
`SCHEDULE_IN_USE` ofrece "Inactivar" aunque ya esté inactiva (1 falla); el color mandado sin la casilla (2 fallas).

**En Edge, contra `syncvet.test`** (los controles del desvío 8, repetidos; sin ventana, `es-PY`, con un Vite propio en `:5180`
detenido por su PID; 8 de 8): (1) la tarjeta lleva al listado; (2) "Sumar un tramo" con Enter deja el foco en "Desde" y las horas se
escriben con el teclado (08:00 a 12:00 y 12:00 a 16:00); (2b) "Quitar" con Enter devuelve el foco a "Sumar un tramo al lunes"; (3) el
color deshabilitado sin la casilla, mandado `#E8590C` y mostrado en el detalle; (3b) "Copiar el lunes": dos tramos de lunes a viernes,
el sábado y el domingo sin tramos; (4) a 375 px, el detalle, el formulario y el listado sin desplazamiento horizontal, y la edición
con "Víctor Acosta" sin "(actual)"; (5) la agenda temporal eliminada por la interfaz (0 en la base); (6) la consola sin errores. La
captura `05-formulario-375.png` se rehízo ya sin "(actual)" (fuera del repositorio, en el scratchpad de la sesión, `capturas-3-1a`).
Nota del script, no de la interfaz: en Edge con `es-PY`, el campo de hora tiene el segmento a. m./p. m. y Tab lo recorre antes de
pasar al campo siguiente. Ingresó y salió `admin-a` (el token revocado; el ingreso queda en la auditoría); `admin-a`, `recepcion-a`
y `vet-a` sin cambios; ninguna agenda "Control Edge" en la demo.

**Corrida final de la revisión** (2026-11-18, desde `frontend/`): `typecheck`, `lint` y `build` sin errores ni avisos; `npm test`,
la primera vez 1 falla en `ClientDetailPage.test.tsx` ("una persona: sus datos…", ajena a la 3.1a; pasó sola), y repetida la suite
completa: 1403 tests en 67 archivos, en verde. Desde `backend/`, `FictitiousDocumentsTest`, 3 de 3.

**Validación.** El usuario aceptó los desvíos y la revisión con su corrección, y pidió el cierre. Claude Code: Edge sin ventana, 8 de 8
en la implementación (con el backend propio del desvío 8) y 8 de 8 repetidos contra `syncvet.test` en la revisión; Playwright,
`e2e/agendas.spec.ts` 1 de 1 y `e2e/permisos.spec.ts` 7 de 7 (en la implementación; la revisión solo tocó un test de Vitest, sin
Playwright); la corrida completa de Playwright, al cerrar la F3.

Corrida de `npm test` del frontend (2026-11-18): 1403 tests en 67 archivos, en verde.

### Entrega 3.1b

Estado: **COMPLETADO** el 2026-11-18 (R-087). Los bloqueos de una agenda (listar, crear con los turnos que quedan adentro y
eliminar), en la tarjeta "Bloqueos" del detalle de la agenda, en `feature/front-3-1b-bloqueos` (desde `913f1d3`, el cierre de la
3.1a), sin integrar a `main` (se integra al cerrar la F3). Commits: `2c30241` (WIP de la implementación), `cbb4663` (WIP de la
revisión) y el de este cierre (R-087: la documentación), todos el 2026-11-18. Sin cambios en el backend ni paquetes nuevos.

**Hecho.** `features/schedules/blocks.ts` (el formulario del alta en Asunción con `TZDate` y `toApiDateTime`, "Todo el día", las
reglas de `StoreScheduleBlockRequest` con sus mensajes y el período de un bloqueo para mostrar) con `blocks.test.ts`;
`ScheduleBlocks.tsx` (la tarjeta "Bloqueos" del detalle con `schedule-blocks.index`, paginada, sin `from` ni `to`; "Nuevo bloqueo"
con `.store` y "Eliminar" con `.destroy`, en `ConfirmDialog` con `useSingleAction` y `dialogError`) con `ScheduleBlocks.test.tsx`;
en `api.ts`, la consulta (`['schedules', id, 'blocks', { from, to, page }]`), el alta, la baja y `afterBlocksChanged` (los bloqueos y
la disponibilidad de la agenda, `['schedules', id]`, y el detalle marcado viejo sin otro pedido); tres permisos en `PERMISSIONS`; el
ayudante de Playwright `createScheduleFor` (la agenda por la API en `syncvet_e2e`) y un caso nuevo en `e2e/agendas.spec.ts`. Sin
rutas nuevas, sin cambios en el menú, en el backend ni en los paquetes.

**Desvíos** (los 11, aceptados por el usuario en la revisión; ninguno cambia el comportamiento del diseño):

| # | Desvío | Motivo |
|---|---|---|
| 1 | Los turnos que quedan adentro se muestran **después** del 201, no antes de confirmar | Es lo que hace la API (pregunta 22 del backend): crea el bloqueo aunque haya turnos, no lo rechaza ni pide confirmación, y los devuelve en `affected_appointments`. No hay una vista previa en la API (armarla con `GET appointments` no sirve: ese listado llega hasta 31 días y un bloqueo, hasta 366). Antes de confirmar, el diálogo avisa: "Si ya hay turnos en ese horario, el bloqueo se crea igual y te mostramos cuáles quedaron adentro…"; con el 201, el diálogo pasa a la lista (día y hora en Asunción, paciente con su dueño, cliente o contacto con su teléfono, y el estado) con solo "Cerrar" |
| 2 | Sin el enlace a cada turno de la lista | `/turnos/$id` llega en la 3.4: se suma ahí (el diseño ya lo dice) |
| 3 | La validación del navegador es una función pura (`blockFormErrors`), no Zod | El diálogo no usa React Hook Form, como los demás diálogos con campos (depósitos y fallecimiento, `deceased.ts`): las mismas reglas y mensajes de la API, con sus tests en los bordes |
| 4 | A la API, `starts_at` y `ends_at` van con `-03:00` (`toApiDateTime`), no en UTC con `Z` | Lo dice el diseño y la convención de `frontend/CLAUDE.md` §5; la API acepta los dos y responde en UTC con `Z`, que se muestra en Asunción |
| 5 | La API no tiene un 409 propio de los bloqueos (ni `error_code` de negocio) | Los tests prueban el 409 `CONFLICT` genérico (el aviso de la F0 y el formulario intacto para probar de nuevo) y el 404 de la baja (otra pestaña ya lo eliminó: aviso y la lista de nuevo). El 404 del alta (la agenda eliminada) va al listado con `useScheduleGone` |
| 6 | El recepcionista y el veterinario se prueban con la tarjeta sola | La ruta del detalle es del administrador (`schedules.update`, 3.1a): hoy solo él ve la tarjeta. El test monta la tarjeta con los permisos reales de los otros roles (lista sin "Nuevo bloqueo" ni "Eliminar") y comprueba que la ruta los lleva a `/sin-permiso` |
| 7 | Los errores del 422 de la API van a la **fecha** ("Fecha desde", "Fecha hasta"); los del navegador, a la hora si el problema es la hora | La API manda una sola clave por instante (`starts_at`, `ends_at`); el navegador sabe qué parte está mal (fuera de la grilla, a la hora; el fin antes del inicio el mismo día, a "Hora hasta"; en otro día o más de 366 días, a "Fecha hasta") |
| 8 | "Todo el día" en un día que empezó con el cambio de horario (01/10/2023) empieza a la 01:00 y en la tabla se muestra con la hora, no como "todo el día" | Las 00:00 no existieron ese día (`TZDate` da la 01:00 `-03:00`, el comienzo real del día; el día dura 23 horas, con test). Solo fechas anteriores al 15/10/2024 |
| 9 | Se corrió `e2e/permisos.spec.ts` (7 de 7) aunque no cambian el menú ni el permiso de una ruta | `PERMISSIONS` suma tres claves (R-084 lo nombra) |
| 10 | Playwright, la primera corrida de `agendas.spec.ts`: 2 fallas | El caso nuevo, por el ayudante: el nombre del veterinario está en `user.name`, no en `name` (corregido). El de la 3.1a falló en la misma corrida y su error quedó fuera de la salida recortada: no se determinó la causa; sin cambios en ese caso, la corrida siguiente dio 2 de 2 |
| 11 | En Edge, la primera corrida del script dio 7 de 8: el control 7 falló por el script (`getByRole` encontraba también el "Eliminar" de la fila, cuyo nombre accesible empieza igual) | `exact: true`; repetido el script entero, 8 de 8. Por eso `admin-a` ingresó dos veces (dos ingresos en la auditoría) |

**Mutaciones** (4 de 4 detectadas, revertidas): la fecha armada con la zona de la PC (`new Date(...).toISOString()`) en lugar de
`toApiDateTime` (7 fallas); "Todo el día" hasta las 00:00 de la fecha hasta, sin el día siguiente (6 fallas); los turnos adentro sin
mostrar (el diálogo se cierra siempre, 1 falla); el límite de 366 días rechazado en el borde (`>=`, 2 fallas).

**En Edge** (sin ventana, `es-PY`, zona `America/Asuncion`, contra `syncvet.test` con un Vite propio en `:5180`, detenido por su PID;
8 de 8; la agenda temporal "Control Edge 3.1b …" creada y eliminada por la API, con sus bloqueos: 0 en la demo después): (1) "Nuevo
bloqueo" con Enter abre con el foco en "Cancelar" y Escape lo cierra y devuelve el foco al botón; (2) la fecha escrita con el teclado
en el selector nativo (dd/mm/aaaa) queda `yyyy-MM-dd` y Tab no sale del diálogo; (3) 09:03 da el mensaje de la grilla y "hasta" antes
de "desde", el del fin, sin pedido; (4) "Todo el día" con la barra espaciadora oculta las horas y manda de las 00:00 `-03:00` a las
00:00 del día siguiente a la fecha hasta, y la tabla dice "Del … al …, todo el día"; (5) la API lo guardó en UTC (03:00 `Z`); (6) a
375 px, el detalle y el diálogo sin desplazamiento horizontal; (7) eliminar con el teclado (el foco empieza en "Cancelar"); (8) la
consola sin errores. Ingresó y salió `admin-a` (tokens revocados); `admin-a`, `recepcion-a` y `vet-a` sin cambios. Capturas fuera del
repositorio (scratchpad de la sesión, `capturas-3-1b`): `01-validacion-1280.png`, `02-todo-el-dia-1280.png`,
`03-detalle-con-bloqueo-1280.png`, `04-detalle-375.png` y `05-dialogo-375.png`.

**Pruebas.** Durante el trabajo, `vitest related` de lo tocado: 627 de 627 en 29 archivos; `blocks.test.ts` 22 y
`ScheduleBlocks.test.tsx` 12. Playwright: `e2e/agendas.spec.ts` 2 de 2 (en la segunda corrida, desvío 10) y `e2e/permisos.spec.ts`
7 de 7. Corrida final (2026-11-18, desde `frontend/`): Prettier de los archivos cambiados, `typecheck`, `lint` y `build` sin errores ni
avisos; `npm test`, 1437 tests en 69 archivos, en verde, a la primera. Desde `backend/`, `FictitiousDocumentsTest`, 3 de 3.

**Para la 3.4:** el enlace a cada turno de la lista de afectados y el caso de Playwright con un turno adentro (con turnos desde la
interfaz).

**Revisión (2026-11-18, sobre `2c30241`, con el diff desde `913f1d3`).** `syncvet.test` respondía (200 en `/` y `/up`).
Decisiones del usuario: **los 11 desvíos, aceptados**; los turnos adentro, como la API (el bloqueo se crea igual y se muestran
después), con la marca "Dentro de un bloqueo" en la grilla y en la lista registrada en la [3.2](f3-agenda.md#32-la-agenda-del-día) y la
[3.3](f3-agenda.md#33-la-semana-y-la-lista-de-turnos); la consulta previa de los turnos que quedarían adentro, como mejora del backend no
urgente ([deuda 25](../../deuda-tecnica.md)).

* **Revisado, sin hallazgos.** Las fechas: el alta arma la hora de pared de Asunción con `TZDate` y la manda con `toApiDateTime`
  (`-03:00`); lo recibido (`Z`) se muestra con `inAppZone`; "Todo el día" va hasta las 00:00 del día siguiente a la fecha hasta (el
  día 32 lo normaliza `TZDate`, también el 31/12), con los días de 23 y 25 horas y el cruce de medianoche probados con la PC en UTC.
  La paridad con `StoreScheduleBlockRequest`: múltiplos de 5 en la hora de Asunción, el fin estrictamente después del inicio
  (`<=` como `lessThanOrEqualTo`), 366 días de calendario sumados en Asunción (`addDays` sobre `TZDate`, como `addDays` de Carbon
  en la hora local; el borde pasa y 5 minutos más no), el motivo sin los espacios de los extremos (como `TrimStrings`) y contado
  en caracteres (`Array.from`, como `mb_strlen`), sin `maxLength` nativo; los mensajes, los de la API. El aviso previo y la lista
  posterior de los afectados (día, hora, quién y estado en Asunción, solo "Cerrar"). El 409 `CONFLICT` en el diálogo con el
  formulario intacto, los 422 a sus campos (y las claves desconocidas a la alerta), el 404 del alta (al listado,
  `useScheduleGone`) y el de la baja (aviso y la lista de nuevo). Los permisos: `schedule-blocks.index`, `.store` y `.destroy`, los
  nombres de `ScheduleBlockController` y del `RolesAndPermissionsSeeder` (el recepcionista y el veterinario, solo `.index`); con
  los permisos reales de los dos roles, la tarjeta sin "Nuevo bloqueo" ni "Eliminar". 375 px: el control 6 de Edge de la
  implementación (sin cambios de pantalla en la revisión, no se repitió).
* **Hallazgo 1 (menor, corregido): la página de bloqueos que se queda vacía.** La paginación se pasaba solo con
  `last_page > 1`: al eliminar el único bloqueo de la página 2, la respuesta daba `last_page` 1 y la tabla decía "La agenda no
  tiene bloqueos desde hoy." aunque la 1 tuviera bloqueos (lo mismo si los de esa página terminaban u otra pestaña los
  eliminaba). Ahora, al eliminar el único de una página que no es la primera, vuelve a la anterior (`onDeleted` de
  `DeleteBlockDialog`), y una página fuera de rango muestra "Esta página no existe." con "Ir a la página 1", como los listados.
  Dos tests nuevos en `ScheduleBlocks.test.tsx`. Sin cambios en la API, los permisos ni los textos de lo que ya andaba.
* **Hallazgo 2, el test frágil (desvío 10): la causa, el límite de 10 s sobre un pedido lento del backend (corregido).** Antes de
  tocar nada, `e2e/agendas.spec.ts` 3 veces seguidas: 2 de 2 en cada una. Se descartaron, con 0 fallas en 27 intentos: los datos
  compartidos (la búsqueda del caso de la 3.1a es el nombre completo y el caso nuevo corre después, en serie, sin crear nada
  antes de fallar en su ayudante), el checkbox "Incluir las inactivas" controlado por la URL (con un spec temporal, 0 de 10
  desmarcados justo después del clic y 0 de 10 fallas de `check()`), el aviso antes de recargar en la edición (la URL ya es la del
  detalle cuando se ve el aviso, 10 de 10), la CPU del navegador frenada ×3 (4 de 4) y una optimización nueva de dependencias de
  Vite (no hubo). Con el código final, el spec completo falló 2 de 3 veces en el caso de la 3.1a (solo, 3 de 3 en verde) y 3 de
  13 corridas en total, siempre en las lentas (2,4 a 2,7 min contra 1,1). Con el tiempo de cada pedido a la API registrado
  (`requestfinished`, un gancho temporal), la falla quedó a la vista: `expect(getByText('Agenda eliminada
  correctamente.')).toBeVisible()` con el límite por defecto de 10 s y `DELETE /api/schedules/1` de **10.803 ms** (la misma baja,
  1,1 a 1,6 s en las corridas en verde; los demás pedidos, de 0,3 a 3,7 s). El aviso llegaba después del límite (en la captura
  está). No es la entrega: es el backend de Playwright (`php artisan serve` en Windows), que a veces tarda más de 10 s en un pedido.
  **Corrección:** los resultados de las acciones de los dos casos (los cinco avisos y la marca "Inactivada") esperan hasta 30 s
  (`SAVED`), con el motivo en el spec; el aviso dura 6 s desde que aparece, así que esperar más no lo pierde. Después, 3 corridas
  seguidas del spec completo: 2 de 2 en cada una. Los specs temporales y el gancho se borraron.

**Mutaciones de la revisión** (4 de 4 detectadas, revertidas; solo los tests del archivo): el motivo contado con `.length` (un
emoji es 2, `blocks.test.ts`, 1 falla); "Nuevo bloqueo" sin `schedule-blocks.store` (`ScheduleBlocks.test.tsx`, 2 fallas: el
recepcionista y el veterinario); `ends_at` del 422 a "Fecha desde" (1 falla); el hallazgo 1 sin volver a la página anterior (1
falla).

**Pruebas de la revisión.** `vitest related` de `ScheduleBlocks.tsx`: 571 de 571 en 27 archivos. Corrida final (2026-11-18, desde
`frontend/`): Prettier de los archivos cambiados, `typecheck`, `lint` y `build` sin errores ni avisos; `npm test`, 1439 tests en 69
archivos, en verde, a la primera (2 más que la implementación: los del hallazgo 1). Desde `backend/`, `FictitiousDocumentsTest`,
3 de 3. Playwright: `e2e/agendas.spec.ts` con la corrección, 3 corridas seguidas, 2 de 2 en cada una (y `eslint` y `tsc` del spec).
No se tocó la demo (`syncvet.test`, solo para comprobar que respondía): Playwright usa `syncvet_e2e`; `admin-a`, `recepcion-a` y
`vet-a`, sin cambios. Los servidores de Playwright los levanta y los detiene Playwright (al final, ningún puerto escuchando en
`:8010` ni `:5174`).

**Validación.** El usuario aceptó los 11 desvíos, decidió los turnos adentro como la API (con la marca de la 3.2 y la 3.3 y la
deuda 25), pidió la revisión y el cierre. Claude Code: Edge sin ventana, 8 de 8 contra `syncvet.test` en la implementación (la
revisión no cambió la pantalla salvo la página fuera de rango, cubierta por Vitest); Playwright, `e2e/agendas.spec.ts` 2 de 2 en
3 corridas seguidas con la corrección del hallazgo 2 y `e2e/permisos.spec.ts` 7 de 7 (en la implementación); la corrida completa
de Playwright, al cerrar la F3.

Corrida de `npm test` del frontend (2026-11-18): 1439 tests en 69 archivos, en verde.

### Entrega front-03

Estado: **COMPLETADO** el 2026-11-18 (R-088). Soporte del backend pedido por la F3 del frontend ([pregunta 1](f3-agenda.md#preguntas-del-diseño)):
`per_page` de 1 a 500 en `GET appointments`, en `feature/front-03-turnos-por-pagina` (desde `c71e71d`, el cierre de la 3.1b), sin
integrar a `main` (se integra al cerrar la F3). Commits: `60b3615` (WIP de la implementación), `4a05e9c` (WIP de la revisión, con
`XDEBUG_MODE: 'off'` en Playwright) y el de este cierre (R-088: la documentación), todos el 2026-11-18. Sin rutas, permisos,
migraciones ni `error_code` nuevos (78, 135, 36); sin paquetes nuevos.

**Hecho.** `IndexAppointmentRequest`: `per_page` de 1 a 500 (`MAX_PER_PAGE`), con su mensaje propio para el entero, el mínimo y el
máximo (`PER_PAGE_OUT_OF_RANGE`, "Pedí de 1 a 500 turnos por página.", uno solo aunque fallen dos reglas); `ListFilters` no cambia
(los demás listados, hasta 100; 10 por defecto en todos). La respuesta es la misma (`meta.pagination` en `snake_case`, con
`total`). El `with` del listado ya traía las seis relaciones de `AppointmentResource` (desde la 3.2a): no hubo que cambiar el
controlador. Sin rutas, permisos, migraciones ni `error_code` nuevos (78, 135, 36).

**Pruebas.** `tests/Feature/Appointments/AppointmentPerPageTest.php` (nuevo, 18 casos): 10, 100 y 500 pasan; 501, 0, -1, `abc` y
`10.5` → 422 con el mensaje (un solo mensaje en `errors.per_page`); los otros diez listados con `ListFilters` (clientes, pacientes,
sucursales, depósitos, usuarios, veterinarios, servicios, agendas, bloqueos y auditoría) aceptan 100 y rechazan 101 y 500; el
`total` con más turnos que los pedidos; y la cantidad de consultas con 1 turno y con 300 (tres agendas, con y sin veterinario o
servicio; con paciente, solo con cliente y con un contacto): **9 y 9**, después de un pedido que llena la caché de permisos (como
front-02). Durante el trabajo: `AppointmentPerPageTest` 18 de 18; `AppointmentIndexTest`, `ValidationTranslationsTest`,
`VoseoTest`, `ListFiltersTest` y `PaginationMetaTest`, 47 de 47. Corrida final: `composer test` (SQLite, en serie),
1485 casos, 1469 pasan y 16 omitidos (solo de MySQL), 14416 aserciones, en 5 min 56 s (frente al cierre de la F2, 18 casos más: los de `AppointmentPerPageTest`). `FictitiousDocumentsTest`, 3 de 3 (también por la colección de Postman). MySQL no se corrió: la entrega no tiene migraciones, concurrencia ni búsqueda de texto.

**Mutaciones** (3 de 3 detectadas, revertidas): el tope en 501 (1 falla: el caso 501); `ListFilters` con 500 para todos los
listados (10 fallas, una por listado); sin `veterinarian.user` ni `patient` en el `with` (de 9 a 506 consultas con 300 turnos).

**EXPLAIN en MySQL 8.4** (la consulta que arma Laravel: `clinic_id` del scope, `branch_id`, `schedule_id` y el rango de
`starts_at` de una semana, `order by starts_at, id limit 500`, y su `count(*)`). En una base temporal propia
(`syncvet_explain_tmp`, `CREATE TABLE LIKE` de `appointments`, con sus índices, y 60.000 turnos de 3 clínicas, 12 sucursales y 48
agendas en 26 días; `ANALYZE TABLE`; borrada después) y en la demo (193 turnos): con `schedule_id`, `range` sobre
`appointments_schedule_id_starts_at_ends_at_index` (336 filas estimadas, con `filesort` de esas filas por el `id` del orden); sin
`schedule_id`, `range` sobre `appointments_branch_id_starts_at_index` (1344 filas, sin `filesort`; `EXPLAIN ANALYZE`, 5,7 ms para
500 filas). Los dos `count(*)`, los mismos índices. **No falta ningún índice: sin migración.**

**Postman.** Colección de turnos: "22a" (`per_page=500`, 200 con los cuatro turnos y `per_page` 500), "22b" (501) y "22c" (0),
422 con el mensaje. Newman, solo esa colección, servidor propio en el puerto 8001 (`XDEBUG_MODE=off php artisan serve`, detenido
por su PID): **61 pedidos y 113 aserciones, sin fallas** (antes, 58 y 107). La guía, con las cifras y los casos.

**El pedido lento de la 3.1b (hallazgo 2 de su revisión): `DELETE /api/schedules/{id}`, 10.803 ms en `e2e/agendas.spec.ts`.**
Medido sin Playwright con un script de Node (en el scratchpad de la sesión, fuera del repositorio): entra con `admin-a`, arma la
agenda como el caso de la 3.1a (dos tramos del lunes, editada a un tramo y el miércoles, inactivada), la elimina y sale; pedidos en
serie, salvo donde se dice. Milisegundos, mediana (máximo):

| Servidor | Baja sola | Baja con 3 `GET` en paralelo | Los demás pedidos |
|---|---|---|---|
| `syncvet.test` (Laragon, Xdebug en `debug` con `start_with_request=trigger`), 5 vueltas | 300 (357) | — | 155 a 523 |
| `php artisan serve` (como Playwright) con Xdebug, 5 y 3 vueltas | 343 (403) | 1162 (1184) | 179 a 491 |
| `php artisan serve` con `XDEBUG_MODE=off` (solo ese proceso), 5 y 3 vueltas | 122 (132) | 575 (658) | 63 a 281 |

**Causa: no es el backend.** La baja hace poco (bloquea la fila, `exists` de turnos, borra 2 tramos y los bloqueos uno por uno con
su auditoría) y tarda 0,1 a 0,4 s. Lo que la estira es el entorno de Playwright: (1) `php artisan serve` en Windows es **un solo
proceso** (`php -S`, sin `PHP_CLI_SERVER_WORKERS`): los pedidos que la pantalla dispara a la vez hacen cola, y la baja con 3 `GET`
delante tarda 3,4 veces más; (2) el `webServer` de Playwright levanta PHP **con Xdebug cargado** (`debug`), unas 2,5 a 3 veces más
lento que sin él; (3) con Playwright, Edge y Vite en la misma PC, todo es más lento (en las corridas de la 3.1b los demás pedidos
tardaban de 0,3 a 3,7 s, y la misma baja, 1,1 a 1,6 s en las verdes). Los 10,8 s no se reprodujeron fuera de Playwright. **Sin
corrección en el backend** (no hay nada lento en la baja). **Para decidir (usuario):** sumar `XDEBUG_MODE: 'off'` al `env` del
`webServer` del backend en `frontend/playwright.config.ts` (una línea del frontend, fuera de esta entrega; bajaría todos los pedidos
de Playwright a menos de la mitad). Los 30 s de `SAVED` de la 3.1b quedan igual.

**Demo.** La medición creó y eliminó sus agendas por la API (0 "Medición baja…" después) y revocó sus tokens (6 ingresos y 6
salidas de `admin-a` en la auditoría, como en la 3.1b); Newman dejó lo que deja cada corrida de la colección de turnos (sus turnos,
clientes y paciente, guía de Postman) y sus 3 tokens se borraron. `admin-a`, `recepcion-a` y `vet-a`, sin cambios (`updated_at`
del 2026-11-12). Ningún servidor propio quedó escuchando (8001 y 8021 libres).

**Desvíos.**

| # | Desvío | Motivo |
|---|---|---|
| 1 | El mensaje de `per_page` es propio del listado de turnos ("Pedí de 1 a 500 turnos por página."), no el genérico de `lang/es/validation.php` que usan los demás listados ("El campo resultados por página no puede ser mayor que 100.") | El diseño dice "con su mensaje" y el pedido, en voseo; uno solo para las tres reglas (`integer`, `min`, `max`), así `abc` no da dos mensajes |
| 2 | No se cambió `AppointmentController`: el `with` ya traía las relaciones del Resource | La carga anticipada es de la 3.2a; el test nuevo la fija (mutación 3) |
| 3 | El EXPLAIN se hizo en una base temporal con datos sintéticos, además de la demo | Con 193 turnos el optimizador elige cualquier plan; la base se creó con `CREATE TABLE LIKE` (los índices de `appointments`, sin las claves foráneas) y se borró |
| 4 | El pedido lento no se corrigió | La causa es el servidor de Playwright, no el código de la baja (arriba); la línea de `playwright.config.ts` queda para decidir |
| 5 | Las mediciones con `php artisan serve` fueron contra la demo (`syncvet`), no contra `syncvet_e2e` | Para medir el mismo código y los mismos datos que `syncvet.test` cambiando solo el servidor y Xdebug |

**Decisiones del usuario (2026-11-18).** Se aceptan los 5 desvíos. Sí a `XDEBUG_MODE: 'off'` en el `env` del `webServer` del
backend en `frontend/playwright.config.ts` (hecho en la revisión, abajo); los 30 s de `SAVED` de la 3.1b quedan como están;
Playwright completo, al cerrar la F3.

**Revisión (2026-11-18)**, sobre el diff `c71e71d..60b3615`, por un revisor que no la escribió:

* **`IndexAppointmentRequest`.** La regla de `per_page` pisa la de `ListFilters` (va después del `...`) y el controlador pagina con
  `$request->integer('per_page')`, la misma entrada que valida el Request: no hay otra vía para pasar más de 500 (el cuerpo de un
  `GET` también pasa por la validación; un arreglo, `per_page[]=600`, falla en `integer`). Bordes probados con el validador:
  `600.5`, `-0.5`, `500.0`, `1e2`, `5e2` y `0500` → 422 con **un solo** mensaje (Laravel deja de revisar `min` y `max` cuando falla
  `integer`); `+500` y ` 500` pasan y dan 500 (inofensivo). El mensaje está en voseo y pasa `VoseoTest`. Ningún otro listado usa
  `IndexAppointmentRequest`.
* **`ListFilters` y los otros 10 listados**, sin cambios en el diff; los 10 controladores siguen paginando con
  `ListFilters::DEFAULT_PER_PAGE`, y `test_the_other_lists_still_stop_at_100` cubre los 10.
* **`AppointmentPerPageTest`.** El conteo falla de verdad si vuelve el N+1: el turno único tiene paciente, cliente, servicio y una
  agenda con veterinario (las seis relaciones con clave, así cada una hace su consulta también con 1 turno) y el pedido previo
  llena la caché de permisos. No depende del orden (300 turnos con `per_page=500` entran en una página) ni de la caché (cada
  `countQueries` registra su propio `DB::listen`). Sin cambios.
* **Postman, "22a" a "22c".** Filtran por la agenda de la corrida (`schedule_id`), así el `total: 4` de "22a" vale también en una
  segunda corrida sin recrear la base; "22b" y "22c" comparan el mensaje exacto. Sin cambios.
* **Documentación**: completa (`backend/CLAUDE.md` §3 y §6.10, la guía de Postman y esta sección). **Hallazgo 1 (menor, corregido):**
  la línea nueva de §6.10 de `backend/CLAUDE.md` pasaba de 130 caracteres; se partió.
* **Mutaciones** (2 de 2 detectadas, revertidas): `per_page` con `numeric` en lugar de `integer` (2 fallas: `10.5` → 200 y `abc`
  con el mensaje genérico); sin `client` en el `with` (de 9 a 208 consultas con 300 turnos).
* **Tests relacionados** (sin Xdebug): `AppointmentPerPageTest`, `AppointmentIndexTest`, `ListFiltersTest`, `VoseoTest` y
  `ValidationTranslationsTest`, 54 de 54 (3399 aserciones). No se tocó código del backend (solo el `CLAUDE.md`): sin `composer
  test` ni Newman en la revisión; valen los de la implementación (1485 casos; 61 pedidos y 113 aserciones).

**`XDEBUG_MODE: 'off'` en Playwright** (`frontend/playwright.config.ts`, con su comentario; `frontend/CLAUDE.md` §8). Con
`--no-reload`, `php artisan serve` pasa el entorno entero a `php -S` (`ServeCommand::startProcess`), así que la variable llega al
servidor. Medición con `npx playwright test e2e/agendas.spec.ts e2e/permisos.spec.ts` (9 casos, todos en verde), dos corridas de
cada lado, alternadas (antes, después, después, antes):

| | Corrida 1 | Corrida 2 | Promedio | `agendas.spec.ts` (promedio) | `permisos.spec.ts` (promedio) |
|---|---|---|---|---|---|
| Antes (con Xdebug) | 101 s | 99 s | 100 s | 37,6 s (39,4 y 35,8) | 39,8 s (40,3 y 39,3) |
| Después (`XDEBUG_MODE=off`) | 102 s | 97 s | 99,5 s | 33,8 s (33,1 y 34,4) | 40,3 s (42,6 y 38,0) |

La mejora en la corrida es chica: `agendas.spec.ts` baja un 10 % (el caso de los bloqueos, de 19,2 y 13,8 s a 11,9 y 12,6 s) y
`permisos.spec.ts` no cambia (casi todo es esperar la interfaz). El total incluye la preparación de `syncvet_e2e` y el arranque de
Vite, que no dependen de Xdebug. La línea queda: no cuesta nada y saca la variable más lenta de los pedidos. `typecheck`, ESLint y
Prettier del archivo, sin errores.

**Demo.** La revisión no la tocó (Playwright usa `syncvet_e2e`); `admin-a`, `recepcion-a` y `vet-a`, con su `updated_at` del
2026-11-12. Los servidores de Playwright (`:8010` y `:5174`) los levantó y los cerró Playwright; ninguno quedó escuchando.

**Validación.** El usuario aceptó los 5 desvíos, decidió `XDEBUG_MODE: 'off'` en Playwright, pidió la revisión y el cierre. Corrida
del cierre (2026-11-18, desde `frontend/`, sobre el código de `4a05e9c`): `typecheck`, `lint` y `build` sin errores ni avisos;
`npm test`, 1439 tests en 69 archivos, en verde, a la primera. El código del backend no cambió después de la corrida final de la
implementación (`composer test` en serie, 1485 casos, 1469 pasan, 16 omitidos, 14416 aserciones; Newman de turnos, 61 pedidos y 113
aserciones, sin fallas): en la revisión, solo su `CLAUDE.md`.

Corrida de `composer test` del backend (2026-11-18): 1485 casos, 1469 pasan y 16 omitidos, 14416 aserciones, en verde.

Corrida de `npm test` del frontend (2026-11-18): 1439 tests en 69 archivos, en verde.

### Entrega 3.2

Estado: **COMPLETADO** el 2026-11-18 (R-089). La agenda del día, de solo lectura, en `feature/front-3-2-agenda-dia` (desde
`d30e578`, el cierre de front-03), sin integrar a `main` (se integra al cerrar la F3). Commits: `cdc184a` (WIP de la
implementación), `91ee463` (WIP de la revisión) y el de este cierre (R-089: la documentación), todos el 2026-11-18. Una ruta nueva
(`/agenda`), un permiso nuevo en `PERMISSIONS` (`appointments.index`, de la API) y "Agenda" en el menú; sin cambios en el backend
ni paquetes nuevos.

Implementación del 2026-11-18 en `feature/front-3-2-agenda-dia` (desde `d30e578`). Sin cambios en el backend ni
paquetes nuevos. El diseño coincide con la API real (`AppointmentResource`, `GET appointments` con `per_page` hasta 500,
`GET schedules/{id}/blocks` con `from`/`to` por superposición, `me.veterinarian.id`): no hubo que frenar.

**Hecho.** `/agenda` (`appointments.index`, los tres roles) y "Agenda" en el menú, después de "Pacientes"
(`PERMISSIONS.appointmentsIndex`). `features/appointments/`: `dates.ts` (el día, sumar días con `addDays` sobre `TZDate` al
mediodía, la semana desde el lunes, el comienzo real del día, los días de 23 y 25 horas, la hora de pared), `grid.ts` (puro: las
columnas, el rango de horas, lo sombreado fuera del horario y por los bloqueos, la fila, el alto y el carril de cada turno, la marca
"Dentro de un bloqueo" con la regla de la API y los estados que ocupan el horario, los filtros y el nombre accesible), `search.ts`
(`validateSearch`), `api.ts` (un pedido del día con `per_page=500` y `branchKey`, las agendas de la sucursal, los bloqueos del día;
`staleTime` de 30 s, cada 60 s sin correr en segundo plano y `keepPreviousData`), `AgendaGrid.tsx` (el patrón *grid* con un solo
punto de tabulación) y `AgendaDayPage.tsx` (la barra, los filtros, el aviso de los turnos que faltan, el anuncio en
`role="status"`, el panel de solo lectura y una agenda por vez por debajo de 768 px). `fecha` en `TEXT_SEARCH_PARAMS`. La pausa de
la actualización, común: `src/lib/hooks/auto-refresh.ts` (`usePauseAutoRefresh`, que ya usa `ConfirmDialog`, y la pestaña oculta).
Playwright: `e2e/agenda-dia.spec.ts` (2 casos) con los ayudantes `createAgendaDay`, `retireAgendasNamed` y `asuncionDay` en
`e2e/support/api.ts`; `e2e/permisos.spec.ts`, con "Agenda" en el menú de los tres roles.

**Desvíos y decisiones para revisar:**

| # | Desvío | Motivo |
|---|---|---|
| 1 | El spec es `e2e/agenda-dia.spec.ts`, no `e2e/agenda.spec.ts` | Lo pidió así el usuario en el pedido de la entrega |
| 2 | La fecha va **siempre** en la URL, también la de hoy ("Hoy" escribe `fecha=<hoy>`); sin `fecha`, la página muestra hoy | Lo pide el pedido ("con la fecha en la URL como texto"). La convención de los listados deja fuera los valores por defecto, pero "hoy" cambia a la medianoche: un enlace copiado mostraría otro día. El test lo encontró: el día siguiente era el de hoy (18/11) y la fecha desaparecía de la URL |
| 3 | `todos=1` en la URL para "Ver toda la sucursal" (la URL del diseño no lo nombra) | Es el nombre que el diseño ya da en la cola (`/agenda/cola?todos=`), con el mismo valor |
| 4 | Además de las agendas activas, se suman al final, marcadas "(inactivada)", las agendas que no están entre ellas pero tienen turnos **visibles** ese día (una con solo cancelados, solo con "Mostrar los cancelados") | El diseño habla de "una columna por agenda activa"; una agenda inactivada conserva sus turnos (pregunta 24 del backend) y se perderían de la grilla sin aviso (decisión 1: nunca cortar en silencio). Con test |
| 5 | El día de 25 horas es el **23/03/2024**, no el 24/03 (H9) | `TZDate`: el atraso fue a las 00:00 del 24 y repitió las 23:00 del 23; el 24 dura 24 horas. Los tests de la 2.6 y la 3.1b ya usaban el 23/03 |
| 6 | Un turno que cruce la medianoche se corta a las 24:00 con "sigue el día siguiente" en su nombre accesible | La API no lo permite (H10); la grilla igual lo trata sin romperse, con test de datos armados |
| 7 | La barra no muestra todavía "Semana" ni "Lista"; `vista=semana` se descarta | Llegan en la 3.3 |
| 8 | Los bloqueos del día también se vuelven a pedir cada 60 s (y con la misma pausa) | La marca "Dentro de un bloqueo" depende de ellos; son pocos pedidos (uno por agenda que se ve) |
| 9 | La pausa por diálogo o formulario se prueba con `pauseAutoRefresh()` en la página y con `ConfirmDialog` en `auto-refresh.test.tsx` | En la 3.2 la agenda no abre diálogos ni formularios propios (el panel del turno es de solo lectura, no es un diálogo y no pausa); la 3.4 y la 3.5 los suman y ya quedan cubiertos por `ConfirmDialog` |
| 10 | Enter en una celda libre no hace nada | El alta desde la grilla es de la 3.4 (con `appointments.store`) |
| 11 | La limpieza de Playwright cancela los turnos, elimina el bloqueo e **inactiva** la agenda (no la elimina) | Los turnos no tienen baja (`DELETE` → 405) y una agenda con turnos no se elimina (`SCHEDULE_IN_USE`). `syncvet_e2e` se vacía en la corrida siguiente; la demo no se toca |
| 12 | Hallazgo en Edge, corregido: un día sin ninguna agenda que mostrar no tiene grilla y, después de Av Pág, el foco caía en `body` (Re Pág ya no volvía) | Ahora el foco pasa al aviso del día vacío, que responde a Re Pág y Av Pág. Test nuevo en `AgendaDayPage.test.tsx` |
| 13 | Ajuste visual en Edge: en un turno de 30 minutos con sobreturno y dentro de un bloqueo, las dos marcas van en una sola línea | En dos líneas no entraban y se cortaban (captura `02-bloqueo-1280.png`, antes y después) |
| 14 | En Edge, la grilla a 1280 px se vio con **3** agendas (no 4) y la de muchas columnas, con 15 | La demo no tiene agendas activas (las de Newman están inactivadas): se usaron los turnos que dejó Newman el 23/11 (tres agendas con turnos visibles; con los cancelados, 15) y un bloqueo temporal, sin crear turnos (no se pueden borrar) |
| 15 | El script de Edge se corrió 6 veces hasta el 8 de 8 | Fallas del script, no de la pantalla: el bloqueo temporal en otra hora (la base guarda la hora de Asunción, no UTC), `waitForURL` espera un `load` que la navegación del router no dispara y a 375 px el nombre del usuario está oculto en la barra; más el hallazgo 12 y el ajuste 13. Por eso hubo 19 ingresos de `admin-a` en la auditoría, y 7 tokens de las corridas cortadas quedaron sin revocar (el "Salir" manda el `logout` sin esperarlo y el navegador se cerraba antes): se borraron de `personal_access_tokens` (solo los de la sesión, ids 675 a 686) |
| 16 | En desarrollo salen dos pedidos de turnos al abrir la agenda, el primero cancelado (`ERR_ABORTED`) | El `StrictMode` de React monta dos veces y Query cancela el primero; queda uno solo. En el build de producción, uno |
| 17 | Mutación 4 (la pausa ignorada): falló el test del diálogo; el de la pestaña oculta siguió en verde | TanStack Query ya no corre el intervalo en segundo plano (`refetchIntervalInBackground: false`): son dos protecciones, y la pestaña oculta queda cubierta igual |

**Mutaciones** (4 de 4 detectadas, revertidas): la hora de pared con la zona de la PC (`new Date(...)` en `wallMinutes`, 11
fallas); la marca de bloqueo con `<=`/`>=` (tocarse cuenta, 1 falla); el aviso de los turnos que faltan apagado (1 falla); la pausa
ignorada (`refreshInterval` siempre 60 s, 1 falla, desvío 17).

**En Edge** (sin ventana, `es-PY`, zona `America/Asuncion`, contra `syncvet.test` (200 en `/` y `/up`) con un Vite propio en
`:5180`, detenido por su PID; 8 de 8 en la corrida final): (1) a 1280 px, una columna por agenda con turnos visibles, un pedido con
`per_page=500` y sin desplazamiento de la página; (2) el bloqueo sombreado con su motivo y los dos turnos adentro con la marca
(texto e ícono); (3) el foco visible con el teclado (borde de 3 px) entrando con Tab en un solo punto; (4) Enter abre el panel de
solo lectura con el foco en el título y "Cerrar" lo devuelve al turno; (5) Av Pág y Re Pág cambian de día y se anuncia
("Martes 24 de noviembre: sin turnos." y "Lunes 23 de noviembre: 15 turnos."); (6) con 15 columnas, la grilla se desplaza en su caja
y la página no; (7) a 375 px, una agenda por vez con "Agenda que se ve", sin desplazamiento horizontal; (8) la consola sin errores.
El bloqueo temporal ("Control Edge 3.2", agenda 105) se creó y se eliminó por la API en cada corrida (0 bloqueos después).
`admin-a`, `recepcion-a` y `vet-a` sin cambios (`updated_at` del 2026-11-12). Capturas fuera del repositorio (scratchpad de la
sesión, `capturas-3-2`): `01-dia-1280.png`, `02-bloqueo-1280.png`, `03-foco-1280.png`, `04-panel-1280.png`,
`05-cancelados-1280.png` y `06-dia-375.png`.

**Pruebas.** Durante el trabajo, `vitest related` de lo tocado (666 en 34 archivos, con las 9 fallas esperadas del menú, corregidas)
y los archivos nuevos: `dates.test.ts` 12, `grid.test.ts` 20, `search.test.ts` 12, `AgendaDayPage.test.tsx` 22 y
`auto-refresh.test.tsx` 4. Playwright: `e2e/agenda-dia.spec.ts` 2 de 2 y `e2e/permisos.spec.ts` 7 de 7, a la primera (9 de 9 en
1,6 min); 34 casos en 17 archivos (`--list`). Corrida final (2026-11-18, desde `frontend/`): Prettier de los archivos cambiados,
`typecheck`, `lint` y `build` sin errores ni avisos; `npm test`, 1510 tests en 74 archivos, en verde, a la primera. Desde
`backend/`, `FictitiousDocumentsTest` falló 1 vez por un comentario de `dates.ts` (los milisegundos de un día, 8 dígitos): se
reescribió el comentario y dio 3 de 3 (con `dates.test.ts`, 12 de 12; solo cambió un comentario, sin repetir la suite).

**Revisión (2026-11-18, sobre el WIP `cdc184a`, `git diff d30e578`).** Decisiones del usuario: se aceptan los 17 desvíos; H9
corregido en el diseño (el día de 25 horas es el 23/03/2024, también en los tests de la 3.2, la 3.3 y la 3.6 del diseño); regla
nueva para los controles en Edge (un ingreso por rol, los tokens revocados siempre en un `finally` y, si falla el script, se
repite solo el control que falló), en `frontend/CLAUDE.md` §8 y en el "Presupuesto por entrega" del `CLAUDE.md` raíz.

Revisado sin hallazgos: las filas de 15 minutos y el turno que no empieza en un múltiplo de 15 (va en su fila, corrido); los
sobreturnos lado a lado (`assignLanes`, por grupos de superpuestos); la medianoche (corte a las 24:00, desvío 6); los días de 23
y 25 horas (la hora de pared y el fin por la duración); la marca "Dentro de un bloqueo", igual a `affectedAppointments` de
`ScheduleBlockService` (`occupyingValues()`, `starts_at < ends_at` y `ends_at > starts_at`, estrictos, con los bloqueos sin
recortar); el aviso de los turnos que faltan; la vista del veterinario y `todos=1`; `auto-refresh.ts` (el intervalo es de Query:
se limpia al desmontar el observador y no se duplica; cada pausa se suelta una sola vez y en la limpieza del efecto, también al
desmontar `ConfirmDialog` abierto; la pestaña, por `visibilitychange`); el foco (el día vacío, desvío 12, y el turno que
desaparece); el anuncio sin la actualización periódica; los permisos del menú y de la ruta (`appointments.index`, los tres
roles, con `schedules.show` y `schedule-blocks.index` para el detalle y los bloqueos); 375 px (una agenda por vez). Detalle
cosmético sin corregir: el 01/10/2023, un bloqueo que empieza con el día se dibuja desde la fila de las 00:00, una hora que ese
día no existió.

| # | Hallazgo | Corrección |
|---|---|---|
| R1 | **Rendimiento con 300 turnos.** `navigation()` buscaba la fila de cada turno recorriendo todas las filas y todos los turnos de la columna (cuadrático) y se recalculaba en cada render, y cada tecla volvía a dibujar todos los turnos (con `formatTime` en la zona). Medido en jsdom con 300 turnos en una columna: 10 flechas, 1232 ms (en 4 columnas, 917 ms) | La fila sale directo del inicio (`startingItemsByRow`), `navigation` en `useMemo` sobre la grilla y `AgendaItem` en `memo` con el `onOpen` de la página: 10 flechas, 143 ms (123 ms en 4 columnas). Medido con un test temporal, borrado (un límite de tiempo en un test sería frágil). Test nuevo: el turno de las 10:05 va en la celda de las 10:00, corrido 8 px, y la flecha lo alcanza |
| R2 | Los selectores "Agenda" y "Agenda que se ve" mostraban la agenda inactivada sin la marca que tiene su columna | `scheduleOptionLabel`: "Consultorio viejo (inactivada)"; el test de la agenda inactivada lo comprueba |

**Mutaciones de la revisión** (3 de 3 detectadas, revertidas): la marca de bloqueo sin filtrar por estado (2 fallas en
`grid.test.ts`); la vista por defecto del veterinario sin las agendas sin veterinario (2 fallas, `grid.test.ts` y
`AgendaDayPage.test.tsx`); la pausa que nunca se suelta (`usePauseAutoRefresh` sin la limpieza: 1 falla, en
`auto-refresh.test.tsx`).

**Pruebas de la revisión.** `vitest related` de `AgendaGrid.tsx` y `AgendaDayPage.tsx`, 595 en 28 archivos. Corrida final
(2026-11-18, desde `frontend/`): Prettier de los archivos cambiados, `typecheck`, `lint` y `build` sin errores ni avisos;
`npm test`, 1511 tests en 74 archivos, en verde, a la primera. Desde `backend/`, `FictitiousDocumentsTest` 3 de 3. Playwright
(se tocó la grilla): `e2e/agenda-dia.spec.ts` 2 de 2 (1,5 min); `permisos.spec.ts` no se corrió (el menú y los permisos no
cambiaron). Sin Edge ni cambios en la demo; `admin-a`, `recepcion-a` y `vet-a` sin tocar; los puertos de Playwright, libres al
terminar.

**Validación.** Claude Code: 8 de 8 controles en Edge contra `syncvet.test` (con el foco del día vacío corregido, desvío 12, y los
7 tokens que quedaron sin revocar, borrados, desvío 15), Playwright de `e2e/agenda-dia.spec.ts` y `e2e/permisos.spec.ts` y la
revisión con sus correcciones (R1 y R2). El usuario aceptó los 17 desvíos, decidió la corrección de H9 y la regla de los controles
en Edge, pidió la revisión y el cierre, y eligió la siguiente: demo-02, antes de la 3.3. La corrida del cierre es la de la revisión (2026-11-18, desde `frontend/`, sobre el código de `91ee463`; después
solo cambió la documentación): `typecheck`, `lint` y `build` sin errores ni avisos; `npm test`, 1511 tests en 74 archivos, en
verde, a la primera; `FictitiousDocumentsTest`, 3 de 3; Playwright, `e2e/agenda-dia.spec.ts` 2 de 2 (en la implementación, con
`e2e/permisos.spec.ts`, 9 de 9; 34 casos en 17 archivos). El código del backend no cambió: vale la corrida de front-03
(`composer test`, 1485 casos). La corrida completa de Playwright, al cerrar la F3.

Corrida de `npm test` del frontend (2026-11-18): 1511 tests en 74 archivos, en verde.

### Entrega demo-02

Estado: **COMPLETADO** el 2026-11-18 (R-090). Soporte del backend para la demo: `php artisan demo:agenda`, en
`feature/demo-02-agenda` (desde `7e2eb5a`, el cierre de la 3.2), sin integrar a `main` (se integra al cerrar la F3). Commits:
`910b4cb` (WIP de la implementación), `93e6fcd` (WIP de la revisión, con la corrección del desvío 5) y el de este cierre (R-090:
la documentación), todos el 2026-11-18. Sin rutas, permisos, migraciones ni `error_code` nuevos (78, 135, 36); sin paquetes
nuevos. Lo que hace el comando, en su [diseño](f3-agenda.md#demo-02-soporte-del-backend-la-agenda-de-la-demo); cómo correrlo, en
[`setup.md`](../../../04-desarrollo/setup.md#agenda-de-la-demo-demoagenda).

**Corrida en la demo, en la implementación** (`syncvet.test`, 2026-11-18 a las 18:28 en Asunción): 5 agendas (Consultorio 1 de Víctor Acosta, 45
turnos; Consultorio 2, 27; Peluquería, 22; Guardia, 57; Vacunación, en "Sucursal de usuarios (Postman)", 20) y 171 turnos: 34 a
confirmar, 33 confirmados, 1 fichado, 53 atendidos, 17 ausentes y 33 cancelados; 30 de hoy, 1 sobreturno, 1 de un contacto y 1
bloqueo ("Demo Guardia", 18/11 19:00–19:30, con 1 turno adentro). La segunda corrida dio las mismas cifras; ningún turno salteado.
`admin-a`, `recepcion-a` y `vet-a` sin cambios (`updated_at` del 12/11 y del 05/11).

**En Edge** (sin ventana, `es-PY`, zona `America/Asuncion`, contra `syncvet.test` con un Vite propio en `:5180`, detenido por su
PID; 5 de 5): (1) `/agenda` de hoy a 1280 px con 4 columnas (las cuatro "Demo …" de Casa central) y 21 turnos visibles, sin
desplazamiento horizontal; (2) el turno fichado de las 19:00 de la guardia con "Dentro de un bloqueo" y el bloqueo con su motivo;
(3) "Mostrar los cancelados": 0 antes y 5 después, con `?cancelados=1` en la URL; (4) a 375 px, sin desplazamiento horizontal;
(5) `vet-a` en su vista por defecto ve "Demo Consultorio 1" y las agendas sin veterinario (4 columnas, 21 turnos). Consola sin
errores. Capturas fuera del repositorio (scratchpad de la sesión, `capturas-demo-02`): `01-dia-1280.png`, `02-bloqueo-1280.png`,
`03-cancelados-1280.png`, `04-dia-375.png` y `05-vet-1280.png`.

**Desvíos** (validados por el usuario el 2026-11-18):

| # | Desvío | Motivo |
|---|---|---|
| 1 | Los turnos que ya empezaron se dan con el reloj de PHP corrido (`Carbon::setTestNow`) a las 07:00 de su día, y cada transición, a la hora en que habría pasado (fichado 10 min antes, atendido 20 min después, ausente 15 min después); el reloj vuelve a su valor después de cada paso | El alta rechaza un día que ya pasó y el fichado exige el día del turno: sin eso, por los Services no hay atendidos ni ausentes de días anteriores. Las fechas de la auditoría y `created_at` de esos turnos son las simuladas |
| 2 | Los turnos de las agendas "Demo …" se borran con el modelo (uno por uno, con su auditoría), no con un Service | La API no tiene baja de turnos. Un turno dado a mano en una agenda "Demo …" también se borra en la corrida siguiente |
| 3 | Crea 4 servicios "Demo …" (se reutilizan, no se borran) | Las agendas sin veterinario necesitan un servicio (P10) y la demo no tiene servicios activos propios |
| 4 | Se niega fuera de `local` y `testing`, no solo en `production` | Los mismos entornos que el `DemoSeeder`; el test cubre `production` |
| 5 | ~~"Demo Vacunación" queda en "Sucursal de usuarios (Postman)", la otra sucursal activa de la demo~~ **Corregido en la revisión** (decisión del usuario): todas las agendas van en `SUC-01` | Los tres usuarios de la demo trabajan solo en Casa central: esa agenda no se veía en su `/agenda` (sí en `/configuracion/agendas`) |
| 6 | El bloqueo va en la peluquería o en la guardia, el primero de hoy por venir: a las 18:28 quedó en la guardia | La peluquería cierra a las 17:00; así el bloqueo se ve en "hoy" a cualquier hora hasta las 20:00 |
| 7 | En Edge, el control 2 falló en la primera corrida por la espera del script (la grilla dibuja los turnos antes de que lleguen los bloqueos); se corrigió la espera y se repitió solo ese control (en total, 2 ingresos de `admin-a` y 1 de `vet-a`, los 3 tokens revocados, 0 al terminar). La captura `01-dia-1280.png` es de antes de que llegaran los bloqueos (sin el sombreado de las 19:00); el bloqueo está en `02-bloqueo-1280.png` | Falla del script, no de la pantalla |
| 8 | El comando se corrió 3 veces en la demo (dos con el código final del alta de turnos y una después de mover el bloqueo a "hoy") | La segunda, para ver la idempotencia con los datos reales |

**Mutaciones** (3, con los tests relacionados): la semana calculada en UTC (falla
`test_the_week_is_the_one_of_asuncion_with_a_fixed_clock`), la limpieza sin el filtro del prefijo (falla
`test_it_does_not_touch_other_data_nor_the_three_demo_users`) y sin la negativa de entornos (falla
`test_it_refuses_to_run_in_production`). Las tres, restauradas.

**Pruebas** (2026-11-18, desde `backend/`, sin Xdebug). Durante el trabajo, `DemoAgendaCommandTest`, 8 de 8 (410 aserciones).
Corrida final, `composer test` en SQLite y en serie: 1493 casos, 1476 pasan, 16 omitidos (solo de MySQL) y 1 falla, 14826
aserciones: `VoseoTest` marcó "crea" en el mensaje de la negativa del comando (lo toma por tuteo); se reescribió el mensaje y se
repitieron solo `VoseoTest` y `DemoAgendaCommandTest`, 10 de 10 (434 aserciones), sin repetir la suite (el Presupuesto por
entrega). `FictitiousDocumentsTest`, 3 de 3. MySQL no se corrió (sin migraciones, concurrencia ni búsqueda de texto); Newman
tampoco (sin rutas nuevas). Desvío 9: la falla de `VoseoTest` en la corrida final, por la que la cifra de `composer test` no es
de una corrida entera en verde.

**Validación del usuario** (2026-11-18): acepta los desvíos 1 a 4 y 6 a 9; el 5 se corrige (todas las agendas en la sucursal del
`DemoSeeder`, identificada por su código, y la "Demo Vacunación" de la sucursal de Postman, fuera) y `setup.md` avisa que un turno
cargado a mano en una agenda "Demo …" se borra en la corrida siguiente.

**Revisión** (2026-11-18, un revisor que no la escribió; el diff `7e2eb5a..910b4cb`, con el Presupuesto por entrega):

* **Services.** Todo pasa por `ScheduleService`, `ScheduleBlockService`, `ServiceCatalogService`, `AppointmentService` y
  `AppointmentTransitionService`; la única baja sin Service es la de los turnos de las agendas "Demo …", modelo por modelo
  (desvío 2, documentado). Sin hallazgos.
* **Lo que borra y regenera.** Solo lo marcado "Demo …": las agendas por el prefijo (con el scope de la clínica: nunca la Clínica
  B), sus turnos y sus bloqueos por `schedule_id` de esas agendas, y los servicios "Demo …" se reutilizan sin borrarse. Los
  turnos no tienen `SoftDeletes`, así que la baja de una agenda "Demo …" vieja no choca con turnos eliminados. **H1 (el desvío 5,
  corregido):** las agendas se creaban en la sucursal **por defecto** y "Demo Vacunación" en **cada otra sucursal activa**; si
  Postman o Playwright dejaban otra sucursal como la por defecto, todas iban a parar a ella. Ahora van solo en `SUC-01` (por su
  código: `DemoAgendaCommand::BRANCH_CODE`, tomado de la constante nueva `ClinicService::FIRST_BRANCH_CODE`, la que usa el alta de
  la clínica); sin `SUC-01` activa, se niega. La "Demo …" que quedó en otra sucursal es una "Demo …" que ya no está en la
  definición y se elimina sola con sus turnos (`deleteStaleSchedules`), sin código aparte.
* **El reloj simulado.** `at()` lo corre y lo devuelve en un `finally`, y `handle()` lo vuelve a devolver en el suyo (dos capas):
  no queda corrido ni si un Service falla a mitad de un paso. Antes no había test; ahora dos (después de una corrida y después de
  una falla del atendido con el reloj corrido).
* **La transacción.** `DB::transaction` dentro de `CurrentClinic::run`; la falla simulada no deja agendas, servicios ni turnos
  (test nuevo). Las excepciones que no son `RuntimeException` también revierten (la transacción) y devuelven el reloj y el
  usuario (`finally`).
* **La negativa** fuera de `local` y `testing` (`DemoSeeder::ENVIRONMENTS`), antes de leer nada. Sin hallazgos.
* **La semana en Asunción.** `now()` en `app.timezone` y `startOfWeek(MONDAY)`; los instantes van con su desplazamiento al alta.
  Sin hallazgos.
* **Los tests y la hora real.** Los que corren el comando fijan el reloj (`travelTo`); los dos que no (la negativa y sin la
  clínica) no generan nada. Sin hallazgos.
* **H2 (menor, corregido):** el contador de turnos salteados (`$skipped`) no se ponía en 0 al empezar; como Artisan reutiliza la
  instancia del comando en el mismo proceso, una segunda corrida en el mismo proceso (los tests) sumaba los de la anterior. Ahora
  se pone en 0 en `handle()`. Sin cambio en una corrida normal.

**Mutaciones de la revisión** (2, con los tests relacionados): (1) la sucursal elegida otra vez por `is_default` en lugar del
código: falla `test_it_only_uses_the_demo_branch_and_removes_its_old_agendas_elsewhere`; (2) sin devolver el reloj en las dos
capas (el `finally` de `at()` y el de `handle()`): falla `test_the_clock_is_restored_and_nothing_is_left_after_a_failure`. Quitar
solo la de `handle()` no falla ningún test: es redundante con la de `at()`, a propósito. Las dos, restauradas.

**Corrida en la demo después de la corrección** (`syncvet.test`, 2026-11-18): 5 agendas, todas en "Casa central" (Consultorio 1
de Víctor Acosta, 45 turnos; Consultorio 2, 27; Peluquería, 22; Guardia, 57; Vacunación, 20) y 171 turnos: 34 a confirmar, 33
confirmados, 1 fichado, 53 atendidos, 17 ausentes y 33 cancelados; 30 de hoy, 1 sobreturno, 1 de un contacto y 1 bloqueo ("Demo
Guardia", 18/11 19:00–19:30, con 1 turno adentro). La "Demo Vacunación" de la sucursal de Postman (id 43) se eliminó sola con sus
turnos; la nueva, en Casa central, es la 44. Ninguna "Demo …" fuera de `SUC-01`; las agendas de Postman de Casa central, sin
cambios. `admin-a`, `recepcion-a` y `vet-a` sin cambios (`updated_at` del 12/11, del 05/11 y del 12/11). Con la quinta agenda en
Casa central, `/agenda` de hoy muestra 5 columnas (antes 4): no se volvió a mirar en Edge (sin cambios de pantalla).

**Pruebas de la revisión** (desde `backend/`, sin Xdebug): `DemoAgendaCommandTest`, 11 de 11 (475 aserciones).
Corrida final, `composer test` en SQLite y en serie, **en verde**: 1496 casos, 1480 pasan, 16 omitidos (solo de MySQL), 0 fallas,
14891 aserciones (4 min 19 s); esta es la cifra de una corrida entera en verde que faltaba (desvío 9). `FictitiousDocumentsTest`,
3 de 3 (69 aserciones). MySQL, Newman y Playwright no se corrieron (sin migraciones, concurrencia, búsqueda de texto, rutas ni
pantallas nuevas).

**Validación.** Claude Code: 5 de 5 controles en Edge contra `syncvet.test` con los datos del comando (en la implementación) y la
revisión con sus correcciones (H1, el desvío 5, y H2, el contador de salteados). El usuario aceptó los desvíos 1 a 4 y 6 a 9 (el 9:
la falla de `VoseoTest` en la corrida final de la implementación, por "crea" en el mensaje de la negativa, corregida reescribiendo
el mensaje), decidió la corrección del desvío 5 (solo la sucursal del
`DemoSeeder`, por su código, y la "Demo Vacunación" de la sucursal de Postman, fuera) y el aviso de `setup.md`, y pidió la
revisión y el cierre. La corrida del cierre es la de la revisión (2026-11-18, desde `backend/`, sobre el código de `93e6fcd`;
después solo cambió la documentación): `composer test` en SQLite y en serie, 1496 casos, 1480 pasan, 16 omitidos (solo de
MySQL), 14891 aserciones, en verde; `DemoAgendaCommandTest`, 11 de 11; `FictitiousDocumentsTest`, 3 de 3. El frontend no
cambió: vale la corrida de la 3.2 (`npm test`, 1511 tests en 74 archivos).

Corrida de `composer test` del backend (2026-11-18): 1496 casos, 1480 pasan y 16 omitidos, 14891 aserciones, en verde.

Corrida de `npm test` del frontend (2026-11-18): 1511 tests en 74 archivos, en verde (sin cambios desde el cierre de la 3.2).
