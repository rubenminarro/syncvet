# F0 — Base del frontend

Etapa: Base. Estado: **DISEÑO APROBADO** por el usuario el 2026-11-12 (R-058), en `docs/frontend-analisis`, con las decisiones
del análisis y las [preguntas del diseño](#preguntas-del-diseño) respondidas. El estado vigente de cada fase está en
[`docs/03-plan-desarrollo/README.md`](../../README.md).

## Objetivo

Dejar `frontend/` listo para construir pantallas: el proyecto con sus herramientas y convenciones (0.1, sin backend) y la sesión
contra la API real (0.2): ingresar, salir, la sucursal actual y el menú según los permisos. Ninguna pantalla de negocio: las de
clientes y pacientes empiezan en la F1.

## Entregas

| Entrega | Contenido | Necesita el backend | Rama | Estado |
|---|---|---|---|---|
| 0.1 | Esqueleto: proyecto, herramientas, rutas, capa de la API con la tabla de errores, componentes base y tests; sin llamar a la API real | No | `feature/front-0-1-esqueleto`, desde `feature/front-00-me-sucursales` (R-059) | PENDIENTE |
| 0.2 | Sesión: ingresar, salir, `me`, sucursal actual, cierre en todas las pestañas, vencimiento, menú por permisos y pruebas de punta a punta | Sí: la [entrega de soporte del backend](../backend/front-00-soporte-del-frontend.md) (B1 a B3) | `feature/frontend-f0-02-sesion` | PENDIENTE |

El orden es F0 → F1 → F2 → F3 y después la FASE 4 del backend (R-058, decisión D2); las tres últimas fases del frontend van sobre la API ya cerrada de las fases 1 a 3 del
backend. Cada entrega recorre los pasos 4 a 7 de [`prompts/README.md`](../../prompts/README.md) y deja los tests en verde.

### Orden con la entrega del backend

```text
main ── feature/front-00-me-sucursales (B1–B3, cerrada, R-059)
           └─ feature/front-0-1-esqueleto (0.1)
                └─ feature/frontend-f0-02-sesion (0.2) ── merge a main al cerrar la F0
```

**Decisión del usuario (R-059).** La entrega del backend se cerró primero y no se integra a `main` todavía: la 0.1 sale de su rama y
todo se integra a `main` al cerrar la F0. Reemplaza la propuesta de R-058: dos ramas desde `main`, integradas antes de la 0.2.

La 0.1 no llama a la API real (los tests usan MSW con el contrato ya en `snake_case`, como queda después de B3), así que **puede ir
antes o en paralelo** con la entrega del backend: tocan carpetas distintas (`frontend/` y `backend/`) y no se pisan. La 0.2 necesita
las dos integradas. Propuesta: primero la entrega del backend (es corta y cierra el contrato que la 0.1 tipa), después la 0.1; si
se prefiere avanzar en el frontend, la 0.1 primero no cambia nada, porque sus tipos ya nacen con `per_page`, `current_page` y
`last_page`.

## Stack

El aprobado en el informe de análisis del 2026-11-11 (R-058, con la alternativa descartada de cada elección). Las versiones exactas se fijan en la 0.1 y quedan en el `package.json` del frontend y en
[`technology-stack.md`](../../../01-arquitectura/technology-stack.md).

| Tema | Elección |
|---|---|
| Entorno | Node 26 LTS en todas las PC, con `frontend/.nvmrc` y `engines` en `package.json`; npm 11 con `package-lock.json` |
| Base | React 19, Vite 8 con `@vitejs/plugin-react`, TypeScript 6 en modo estricto (no TS 7) |
| Interfaz | Tabler oficial: `@tabler/core` (solo sus estilos, sin el JS de Tabler) y `@tabler/icons-react`, con componentes propios en `src/components/ui`. Sin Tailwind |
| Rutas | TanStack Router, con las URL en castellano |
| Datos del servidor | TanStack Query (reintentos solo ante fallas de red o 5xx) sobre un cliente HTTP propio sobre `fetch`, sin Axios |
| Formularios | React Hook Form con Zod; el 422 se vuelca a los campos con `setError` |
| Fechas | date-fns 4 con `@date-fns/tz` (`TZDate` en `America/Asuncion`) |
| Tipos de la API | Escritos a mano en cada módulo, con el contrato común en `src/lib/api/types.ts`; sin generarlos (Scramble descartado por ahora) |
| Tests | Vitest con Testing Library, MSW y jsdom; Playwright para punta a punta contra la base `syncvet_e2e` con el `DemoSeeder` |
| Calidad | ESLint (con `typescript-eslint` y las reglas de hooks) y Prettier; `tsc` sin errores |
| Desarrollo | El proxy de Vite manda `/api` al backend: mismo origen, sin CORS en desarrollo |

## Estructura

```text
frontend/
├── .nvmrc                  26
├── .env.example            VITE_API_PROXY_TARGET (el backend del proxy de desarrollo)
├── package.json            engines.node, scripts: dev, build, preview, lint, typecheck, test, test:e2e
├── vite.config.ts          React, el plugin de TanStack Router y el proxy de /api
├── playwright.config.ts    (0.2)
├── e2e/                    (0.2) pruebas de punta a punta y su preparación de la base
└── src/
    ├── main.tsx            Arranque: estilos de Tabler, QueryClient y router
    ├── routeTree.gen.ts    Generado por el plugin de TanStack Router (se versiona)
    ├── routes/             Una ruta por archivo; el nombre del archivo es la URL en castellano
    ├── app/                Proveedores, layouts (público y con sesión), menú y páginas de error
    ├── lib/
    │   ├── api/            client.ts (sobre fetch), types.ts (contrato), errors.ts (ApiError y la tabla de error_code)
    │   ├── auth/           (0.2) sesión, vencimiento, canal entre pestañas y permisos
    │   └── format/         Fechas en la zona de Asunción y guaraníes
    ├── components/ui/      Capa propia sobre Tabler: botón, campo, alerta, aviso, tabla, paginación, página de error
    ├── features/<módulo>/  api.ts, types.ts, componentes y páginas de cada módulo (desde la F1; en la F0, auth)
    └── test/               Configuración de Vitest y los handlers de MSW
```

Rutas de la F0: `/ingresar` (pública), `/` (inicio, con sesión), `/perfil` (0.2), `/sin-permiso` y la página de no encontrado. Las de los módulos
siguen la misma regla: `/clientes`, `/clientes/$id`, `/pacientes`, `/agenda`, `/turnos`, `/configuracion`.

## Convenciones

Se escriben en [`frontend/CLAUDE.md`](../../../../frontend/CLAUDE.md) en la 0.1; este es su contenido.

* **Textos en español con voseo**, como la API ("Ingresá tu email", "Elegí tu sucursal"). El `message` de la API ya viene en
  español y con voseo: se muestra tal cual, nunca se reescribe.
* **Decidir por `error_code`, nunca por el texto ni solo por el código HTTP** (ver la [tabla](#qué-hace-la-interfaz-con-cada-código-de-error)).
* **Toda llamada a la API pasa por `src/lib/api/client.ts`**: pone el token, el `Accept: application/json` y convierte cualquier
  error en un `ApiError` (`status`, `errorCode`, `message`, `errors`, `retryAfter`). Ningún componente llama a `fetch` directo.
* **Lecturas con `useQuery` y cambios con `useMutation`**, con las claves en el `api.ts` de cada módulo. Una mutación no se reintenta
  nunca; una lectura se reintenta una vez solo si falló la red o hubo 5xx, nunca con un 4xx.
* **Tipos escritos a mano** en el `types.ts` del módulo, con los nombres de campo de la API (`snake_case`): no se traducen a
  `camelCase` en el cliente.
* **Componentes de Tabler a través de `src/components/ui`**: las páginas no repiten el marcado de Tabler. Sin Tailwind ni otra
  biblioteca de componentes; sin el JS de Tabler (los menús, diálogos y avisos son componentes propios de React).
* **Fechas y dinero**: la API manda fechas con hora en UTC (`Z`); se muestran en `America/Asuncion` con date-fns y `TZDate`, nunca con
  la zona del navegador. Guaraníes sin decimales, con separador de miles.
* **Permisos**: el menú y los botones se muestran según `me.permissions`; la API vuelve a controlar todo (un botón oculto no es una
  protección).
* **Descargas con token** (foto, logo; desde la F1): se piden con el cliente como `blob` y se muestran con una URL de objeto, porque un
  `<img src>` no manda el token.

## Sesión (0.2)

| Tema | Diseño |
|---|---|
| Dónde vive el token | `sessionStorage` (`syncvet.session`: `token` y `expires_at`); el perfil, en la caché de TanStack Query (`['me']`). Nada en `localStorage` ni en cookies (las cookies de Sanctum quedan para la FASE 10, R-058) |
| Ingresar | `POST /api/login`; con el 200 se guarda la sesión, se pone `data.user` en `['me']` y se vuelve a la ruta pedida (`/ingresar?volver=…`, solo rutas internas) o a `/` |
| Al abrir la aplicación | Si hay una sesión guardada sin vencer, `GET /api/me` la valida; si venció, se borra sin llamar a la API |
| Aviso previo | 10 minutos antes de `expires_at`, un aviso que queda visible: "Tu sesión vence en 10 minutos. Guardá lo que estés haciendo." (P2) |
| Vencimiento | Un temporizador hasta `expires_at` (los tokens duran 12 horas y la API no los renueva) cierra la sesión en todas las pestañas con "Tu sesión venció. Ingresá de nuevo."; además se revisa al volver a la pestaña (`visibilitychange`), porque los temporizadores se frenan con la PC suspendida |
| Pestaña nueva | Sin sesión propia, publica `session-request` en `BroadcastChannel('syncvet-sesion')` y espera la respuesta un instante (alrededor de 300 ms); una pestaña con sesión responde `session-share` con el token y `expires_at`, y la nueva lo guarda en su `sessionStorage`. Sin respuesta, va a `/ingresar` (P1). Todas las pestañas comparten así un mismo token |
| Salir | `POST /api/logout` (si falla, igual se borra la sesión local), se limpia la caché de Query y se publica `logout` |
| Todas las pestañas | Al recibir `logout`, cada pestaña borra su sesión y su caché y va a `/ingresar`. El mensaje lleva el id del token (la parte anterior a `\|` del token de Sanctum, que no es secreta): si una pestaña tiene otro token (dos ingresos simultáneos), hace también su propio `POST /api/logout`. Al cambiar de sucursal, `branch-changed`: las demás pestañas vuelven a pedir `me` e invalidan sus consultas |
| 401 | En cualquier pedido con sesión: como el token es compartido, se cierra la sesión en todas las pestañas (`logout` en el canal, sin `POST /api/logout`) y se va a `/ingresar?volver=…` con el aviso de sesión vencida (P1) |
| Perfil | `/perfil`: los datos del usuario y el cambio de la contraseña propia (`PATCH /api/me/password`, con la actual y la nueva; 422 a los campos, 429 con la cuenta regresiva). La API conserva el token en uso y revoca los demás del usuario (P3) |
| Sucursal actual | Selector en la barra superior con `me.branches` (B1); `PATCH /api/me/current-branch`, actualiza `['me']` e invalida el resto de las consultas (los datos dependen de la sucursal). Sin sucursal actual, el inicio pide elegir una |
| Rutas protegidas | El layout con sesión exige una sesión válida (`beforeLoad`); cada ruta declara el permiso que necesita y, sin él, muestra `/sin-permiso` |
| Menú | Solo los módulos ya hechos y permitidos por `me.permissions`; en la F0, Inicio |

## Qué hace la interfaz con cada código de error

La regla general: **se muestra el `message` de la API** y se decide qué hacer por el `error_code`. `NETWORK_ERROR` es el único código
propio del frontend (sin respuesta del servidor). La tabla vive en `src/lib/api/errors.ts` y la usan el cliente y los formularios;
cada módulo puede sumar el trato especial de sus códigos (columna "Más adelante").

| `error_code` | HTTP | Qué hace la interfaz |
|---|---|---|
| `UNAUTHENTICATED` | 401 | Cierra la sesión en todas las pestañas y lleva a `/ingresar?volver=…` con "Tu sesión venció. Ingresá de nuevo." |
| `USER_INACTIVE` | 403 | En el login, el mensaje en el formulario; con sesión, como 401 pero con el mensaje de la API |
| `CLINIC_NOT_AVAILABLE` | 403 | En el login, el mensaje en el formulario; con sesión, borra la sesión y muestra el mensaje en `/ingresar` |
| `FORBIDDEN` | 403 | Al cargar una página: `/sin-permiso`. En una acción: aviso con el mensaje; el formulario queda como estaba |
| `RESOURCE_NOT_FOUND` | 404 | Al cargar una página de detalle: la página de no encontrado. En una acción: aviso con el mensaje e invalidar la lista |
| `VALIDATION_ERROR` | 422 | Cada clave de `errors` va a su campo (React Hook Form `setError`); las que no son de un campo del formulario, a una alerta arriba. Foco en el primer campo con error |
| `INVALID_CREDENTIALS` | 422 | Mensaje en el formulario de ingreso; se borra la contraseña |
| `AUTH_TOO_MANY_ATTEMPTS`, `TOO_MANY_REQUESTS` | 429 | Mensaje en el formulario y el botón deshabilitado con la cuenta regresiva de `Retry-After` |
| `CONFLICT` | 409 | Aviso "Alguien cambió estos datos mientras guardabas. Revisalos y probá de nuevo." con el `message`; se vuelven a pedir los datos |
| `PAYLOAD_TOO_LARGE` | 413 | El error en el campo del archivo (`errors.photo`, `errors.logo`); sin `errors`, aviso |
| Códigos de negocio (`*_IN_USE`, `*_NOT_EDITABLE`, `USER_LAST_ADMIN`, `APPOINTMENT_*`, …) | 409, 422, 403 | Por defecto, alerta con el `message` en el formulario o el diálogo de la acción. Más adelante: `*_IN_USE` ofrece inactivar; `APPOINTMENT_OVERLAP` ofrece el sobreturno si el usuario tiene `appointments.overbook`; `APPOINTMENT_SERIES_CONFLICT` lista `errors.conflicts`; `APPOINTMENT_QUEUE_NO_BRANCH` abre el selector de sucursal |
| `INTERNAL_SERVER_ERROR`, `SERVICE_UNAVAILABLE` | 500, 503 | Aviso "No se pudo completar. Probá de nuevo en unos minutos."; una lectura se reintenta una vez |
| `NETWORK_ERROR` (propio) | — | Aviso "No hay conexión con el servidor."; una lectura se reintenta una vez |
| `BAD_REQUEST`, `METHOD_NOT_ALLOWED`, `NOT_ACCEPTABLE`, `UNSUPPORTED_MEDIA_TYPE`, `HTTP_ERROR` | 400, 405, 406, 415, otros | Son errores de programación: aviso genérico y `console.error` con el detalle en desarrollo |
| Un código que la tabla no conoce | cualquiera | Como un código de negocio: alerta con el `message` |

## Diseño por entrega

### 0.1 Esqueleto

**Alcance.**

1. Proyecto Vite 8 con `@vitejs/plugin-react`, React 19 y TypeScript 6 estricto en `frontend/`; `.nvmrc` con `26` y `engines`
   (`node` `>=26 <27`, `npm` `>=11`).
2. Tabler (los estilos de `@tabler/core`, sin su JS, y `@tabler/icons-react`) y la capa `src/components/ui` con lo que usan la F0 y la F1: botón, campo de texto
   con su error, alerta, avisos (*toasts*), tarjeta, tabla con paginación (`meta.pagination` en `snake_case`), página de error y
   cargando.
3. TanStack Router (plugin de Vite, rutas por archivo): layout público y layout con sesión (sin la sesión real todavía), `/ingresar`
   como maqueta, `/`, `/sin-permiso` y no encontrado.
4. `src/lib/api`: el cliente propio sobre `fetch` (base `/api`, `Accept: application/json`, el token si hay sesión), los tipos del contrato
   (`ApiSuccess<T>`, `ApiFailure`, `Pagination`) y `ApiError` con la tabla de `error_code`. TanStack Query con los reintentos de las
   [convenciones](#convenciones).
5. `src/lib/format`: fechas en `America/Asuncion` con date-fns 4 y `@date-fns/tz` (`TZDate`) y guaraníes.
6. El proxy de Vite (`/api` → `VITE_API_PROXY_TARGET`, por defecto `http://syncvet.test`).
7. Vitest con Testing Library, MSW y jsdom; ESLint y Prettier; scripts `dev`, `build`, `preview`, `lint`, `format`, `typecheck`,
   `test`.
8. Documentación: `frontend/CLAUDE.md` (estado, comandos, estructura y convenciones) y `frontend/README.md` (instalación con Node 26);
   corregir "repositorio aparte" en [`overview.md`](../../../00-vision/overview.md) y
   [`technology-stack.md`](../../../01-arquitectura/technology-stack.md) (R-057 lo dejó pendiente para la F0); el stack con sus
   versiones en `technology-stack.md`; la instalación del frontend en [`setup.md`](../../../04-desarrollo/setup.md).

**Fuera de alcance.** Llamadas a la API real, la sesión, Playwright.

**Tests.**

| Qué | Cómo |
|---|---|
| El cliente convierte cada respuesta de error del contrato en `ApiError` con `status`, `errorCode`, `message`, `errors` y `retryAfter` | Vitest con MSW: 401, 403, 404, 409, 413, 422 con `errors`, 429 con `Retry-After`, 500, y sin respuesta (`NETWORK_ERROR`) |
| La tabla de `error_code` | Un caso por fila; un código desconocido cae en el trato de negocio |
| Reintentos | Una lectura con 500 o sin red se reintenta una vez; con 4xx, ninguna; una mutación, nunca |
| Errores de validación a los campos | Un formulario de prueba con un 422: cada clave a su campo y la que no es de un campo, a la alerta |
| Formato | Una fecha UTC de las 02:00 se muestra el día anterior a las 23:00 en Asunción; guaraníes con miles y sin decimales |
| Rutas | Una URL desconocida muestra no encontrado; `/sin-permiso` se ve |
| Paginación | La tabla lee `per_page`, `current_page` y `last_page` |

**Mutaciones (las protecciones críticas).** Sacar la conversión a `ApiError` en el cliente; reintentar los 4xx; ignorar el campo en
`setError`; formatear con la zona del navegador; leer `perPage` en lugar de `per_page`. Cada una tiene que hacer fallar un test.

**Qué revisar en el navegador.** `npm run dev` desde `frontend/`: la maqueta de `/ingresar` con Tabler, una URL inexistente muestra no
encontrado, `/sin-permiso` se ve, el ancho de tablet (768 px) no rompe el layout y la consola no tiene errores ni avisos de React.
`npm run build` sin errores y `npm run preview` sirve lo mismo.

### 0.2 Sesión

**Requisito.** La entrega del backend (B1 a B3) integrada a `main`.

**Alcance.**

1. `src/lib/auth`: la sesión en `sessionStorage`, el aviso previo y el temporizador de vencimiento, el canal `syncvet-sesion` (con
   la sesión compartida a las pestañas nuevas) y `can(permiso)`, según la
   [sesión](#sesión-02).
2. `/ingresar` real (email y contraseña, con los errores de la tabla); salir desde el menú del usuario.
3. Layout con sesión: barra con la clínica, el usuario, sus roles y el selector de sucursal actual (B1); menú por permisos; inicio con
   la sucursal actual o el pedido de elegir una.
4. Las rutas protegidas con su permiso y `/sin-permiso`.
5. `/perfil`: los datos del usuario y el cambio de la contraseña propia.
6. Playwright: `e2e/` y `playwright.config.ts`; antes de correr, la base `syncvet_e2e` se recrea con `migrate:fresh` y el
   `DemoSeeder`, con el backend levantado desde `backend/` en un puerto propio y las variables de la base pasadas por el entorno (el
   `DemoSeeder` corre solo en `local` y `testing`). **La preparación se niega a correr si la base no termina en `_e2e`.**
7. Documentación: `frontend/CLAUDE.md` (sesión, comandos de Playwright), la base `syncvet_e2e` en `setup.md` y en
   [`testing.md`](../../../04-desarrollo/testing.md).

**Tests.**

| Qué | Cómo |
|---|---|
| Sesión guardada | Se guarda y se lee; una sesión vencida al abrir se borra sin llamar a la API |
| Vencimiento | Con temporizadores falsos: 10 minutos antes, el aviso; al llegar a `expires_at` se cierra la sesión en todas las pestañas; al volver a la pestaña con la hora pasada, también |
| Entre pestañas | Una pestaña nueva recibe la sesión de una abierta (`session-request` y `session-share`) y, sin respuesta, va a `/ingresar`; al salir se publica `logout` y las demás borran su sesión sin otro `POST logout`, salvo la que tiene otro token; `branch-changed` invalida las consultas |
| 401 | Un 401 en cualquier pedido cierra la sesión en todas las pestañas y lleva a `/ingresar?volver=…`; `volver` con una URL externa se ignora |
| Ingresar | 422 `INVALID_CREDENTIALS`, 422 `VALIDATION_ERROR` en los campos, 429 con cuenta regresiva, 403 `USER_INACTIVE` y `CLINIC_NOT_AVAILABLE`: cada uno con su mensaje |
| Permisos | Una ruta sin el permiso muestra `/sin-permiso`; el menú oculta lo no permitido |
| Sucursal | El selector lista `me.branches`; el cambio llama a `PATCH me/current-branch`, actualiza `me` e invalida las consultas |
| Perfil | El cambio de contraseña llama a `PATCH me/password`; 422 en `current_password` y en `password` van a sus campos; 429 con la cuenta regresiva; con el 200, la sesión sigue |
| Punta a punta (Playwright) | Ingresar con un usuario del `DemoSeeder` y ver el inicio; credenciales incorrectas; una ruta protegida sin sesión lleva a `/ingresar` y, después de ingresar, vuelve; cambiar de sucursal; una pestaña nueva toma la sesión de la abierta; salir en una pestaña cierra la otra; cambiar la contraseña propia |

**Mutaciones.** No cerrar todas las pestañas con un 401; no responder `session-share`; no publicar `logout` en el canal; no programar el temporizador; no invalidar las
consultas al cambiar de sucursal; la preparación de Playwright sin el control del nombre de la base.

**Qué revisar en el navegador.** Con el backend en `syncvet.test` y `npm run dev`: ingresar con `admin-a`, `recepcion-a` y `vet-a` del
`DemoSeeder` y ver el menú de cada uno; credenciales incorrectas cinco veces y la cuenta regresiva; cambiar de sucursal y ver el cambio
en otra pestaña; abrir una pestaña nueva y ver que entra sin ingresar; salir en una pestaña y ver que la otra vuelve a `/ingresar`; borrar `expires_at` a mano en el almacenamiento de la
sesión (DevTools) y ver el cierre; adelantar `expires_at` a menos de 10 minutos y ver el aviso previo; cambiar la contraseña en `/perfil`; inactivar al usuario desde otra sesión de administrador y ver que el siguiente pedido lleva a
`/ingresar`; la consola sin errores.

## Documentación por entrega

| Entrega | Actualizar |
|---|---|
| 0.1 | `frontend/CLAUDE.md`, `frontend/README.md`, `technology-stack.md`, `overview.md`, `setup.md`, este documento (Resultado), el roadmap, `modulos.md` si corresponde y el handoff |
| 0.2 | `frontend/CLAUDE.md`, `setup.md` y `testing.md` (`syncvet_e2e` y Playwright), este documento, el roadmap y el handoff |

## Preguntas del diseño

Respondidas por el usuario el 2026-11-12 (R-058).

| # | Pregunta | Respuesta |
|---|---|---|
| 1 | Con `sessionStorage`, una pestaña nueva (no duplicada) no tiene sesión: ¿pide ingresar o le pide la sesión a una abierta? | Se la pide a una pestaña abierta por el `BroadcastChannel`; con el token compartido, un 401 cierra todas las pestañas |
| 2 | ¿Un aviso antes de que venza la sesión? | Sí, 10 minutos antes |
| 3 | ¿La contraseña propia va en la 0.2 o en la F2? | En la 0.2, en `/perfil` |

## Criterio de cierre

* La 0.1 y la 0.2 cerradas, con los tests de Vitest y de Playwright en verde, `lint`, `typecheck` y `build` sin errores.
* Con la base `syncvet_e2e`: ingresar, cambiar de sucursal, salir en todas las pestañas y el vencimiento, probados de punta a punta.
* Las colecciones de Postman de la entrega del backend, en verde (R-057: todas, al cerrar la fase).

## Resultado

Sin entregas todavía.
