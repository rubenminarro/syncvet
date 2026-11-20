# Tests

Configurados en la entrega 0.1 de la FASE 0 (`phpunit.xml`, `tests/bootstrap.php`, `tests/TestCase.php`).

| Regla | Detalle |
|---|---|
| Comando | `composer test`: SQLite, en serie y sin Xdebug; es la suite completa antes de cerrar cualquier entrega y la de las cifras del cierre. `composer test:mysql`: lo mismo en `syncvet_test_mysql`. `composer test:fast`: SQLite en paralelo, durante el trabajo. Ver [Velocidad](#velocidad) |
| Base | SQLite en `database/testing.sqlite` (lo crea `tests/bootstrap.php`; ignorado por Git). `RefreshDatabase` con `$seed = true` en `TestCase`: migra y siembra (`DatabaseSeeder`: roles, permisos y especies) una vez por ejecución; cada test en una transacción |
| Caché de permisos | `TestCase::setUp` limpia la caché de Spatie en cada test |
| Red | Los tests no usan red: `Http::preventStrayRequests()` en `TestCase` (un pedido HTTP sin simular falla), Have I Been Pwned simulado en `TestCase` y `EMAIL_DNS_CHECK=false` en `phpunit.xml`. La rama con DNS la cubre `EmailDnsCheckTest`: usa un dominio reservado (`.invalid`), que la validación rechaza sin consultar al DNS. WhatsApp y SIFEN se simularán con `Http::fake` cuando existan |
| Colas y mail | Cola `sync` y mailer `array` en los tests |
| Permisos | Probar con un rol propio, no con el administrador (`Gate::before` lo deja pasar todo) |
| MySQL | Cada migración nueva se prueba también en MySQL (límite de 64 caracteres en nombres de índices) |
| Concurrencia | Numeración, turnos y stock: el bloqueo real solo se reproduce en MySQL |
| SQLite y MySQL, nunca a la vez | Correr la suite de SQLite y la de MySQL (`composer test:mysql`) una después de la otra. Comparten los discos falsos de `Storage::fake` (`storage/framework/testing/disks`), que cada test vacía al empezar: en paralelo, una suite borra los archivos de la otra y fallan tests que no tienen ningún error (en la revisión de la 2.6, `RoleAccessTest` con la foto y el logo respondiendo 404). Esto no pasa entre los procesos de una misma suite con `--parallel`: cada uno tiene sus discos ([Velocidad](#velocidad)) |
| Reloj | Un test que compara una hora que calcula la aplicación (el sufijo de baja, una fecha de auditoría) congela el reloj (`freezeTime()`): con la máquina cargada, el segundo puede cambiar entre la acción y la aserción |

Organización: `tests/Feature/<Módulo>` por módulo, `tests/Feature/Access` para permisos por rol.

## Velocidad

Medido el 2026-11-10 en la máquina de desarrollo (Windows 11, Laragon, PHP 8.3 con Xdebug 3.4 en modo `debug`, 12 núcleos lógicos),
sobre el código del cierre de la FASE 2 (988 casos, más los 3 de `EmailDnsCheckTest`):

| Suite | Antes (`php artisan test`, con Xdebug) | En serie sin Xdebug (`composer test`, `composer test:mysql`) | En paralelo sin Xdebug |
|---|---|---|---|
| SQLite | 7 min 40 s a 8 min 55 s | 3 min 17 s | 73 a 81 s (`composer test:fast`) |
| MySQL | 9 min 53 s | 4 min 50 s a 6 min 22 s | 3 min 30 s a 4 min 10 s (el comando de abajo) |

* **Xdebug apagado** (es lo que más ganó): Laragon lo carga con `xdebug.mode=debug`, y así la suite de SQLite tarda casi 3 veces
  más. Los scripts de composer fijan `XDEBUG_MODE=off` (`@putenv`), una variable de entorno que heredan también los procesos de
  `--parallel`. Xdebug sigue instalado para depurar. A mano: `XDEBUG_MODE=off php artisan test` (en PowerShell, antes
  `$env:XDEBUG_MODE='off'`). Para la cobertura hay que prenderlo (`XDEBUG_MODE=coverage`).
* **En paralelo** (`brianium/paratest`, `php artisan test --parallel`): un proceso por núcleo. Laravel le da a cada proceso su propia
  base (`database/testing.sqlite_test_N`, ignoradas por Git; en MySQL, `syncvet_test_mysql_test_N`, que crea el usuario `root` de
  Laragon) y sus propios discos falsos (`storage/framework/testing/disks/<disco>_test_N`), así que los procesos no se pisan. Probado
  en Windows: 6 corridas en verde (3 en SQLite y 3 en MySQL), más la de `composer test:fast`. En MySQL:
  `XDEBUG_MODE=off DB_CONNECTION=mysql DB_DATABASE=syncvet_test_mysql php artisan test --parallel`.
* **Las cifras del cierre salen de la suite en serie** (`composer test` y `composer test:mysql`). En paralelo se cuentan 2 aserciones
  menos, con los mismos casos: `ExceptionArgumentsTest::test_a_trace_does_not_carry_the_arguments` hace una aserción por cada marco
  de la traza, y en el proceso de paratest la traza tiene 2 marcos menos.
* **Lo que no cambió, a propósito:** SQLite sigue en archivo. Con `:memory:`, `RefreshDatabase` migraría y sembraría en cada test,
  porque la base en memoria se pierde cada vez que se recrea la aplicación; con un archivo se migra una sola vez por proceso.
  `BCRYPT_ROUNDS` ya era 4. Las imágenes de los tests de la foto y del logo ya eran mínimas (menos de 1 KB), y el caso de más de 25
  megapíxeles solo trae la cabecera.
* **Picos esperables:** en MySQL, el test que corre después de `VeterinarianLicenseMigrationTest` vuelve a migrar la base, porque el
  `ALTER TABLE` confirma la transacción del test. Son unos 30 s, una vez por proceso.

## Dependencias

En cada cierre de fase, `composer audit` tiene que quedar sin avisos (`CLAUDE.md` §2). Un aviso se corrige con
`composer update <paquete> --with-dependencies`, sin tocar las restricciones de `composer.json`. Después se corren
`composer test:fast` y, para el cierre, `composer test` y `composer test:mysql`, una después de la otra. Lo actualizado queda en el
registro de decisiones (R-046, la primera vez).

## Frontend

Vitest con Testing Library, MSW y jsdom (solo los tests de componentes; la lógica corre en `node`), desde `frontend/`: `npm test` (también `npm run typecheck`, `npm run lint` y
`npm run build` antes de terminar una entrega). Los tests no usan red (MSW con `onUnhandledFrame: 'error'`), corren en UTC para que
una fecha mostrada con la zona de la PC falle y comprueban que `America/Asuncion` sea UTC-3 todo el año, como
`AsuncionTimezoneTest`. Las reglas (el entorno de cada test, consultas en jsdom, la velocidad) están en
[`frontend/CLAUDE.md`](../../frontend/CLAUDE.md) §5 y §8. La cifra de tests del frontend sale de la última corrida de `npm test`
registrada en "Resultado" de la fase, con la línea ``Corrida de `npm test` del frontend (AAAA-MM-DD): N tests en M archivos, en verde.``
(`/validar-docs` la compara con las cifras de la documentación).

Playwright (desde la F0.2b): `npm run test:e2e`, contra el backend real y la base `syncvet_e2e`, recreada en cada corrida con el
`DemoSeeder`; la preparación se niega a correr con otra base. Corre en serie (un *worker*: la base es compartida) y los casos
que cambian datos los dejan como estaban (la sucursal y la contraseña). **Ningún spec depende de lo que deja otro ni del orden**
(cierre de la F3, R-098): los que crean turnos usan un ayudante de `e2e/support/api.ts` que busca por la API la hora libre del
veterinario en todas sus agendas (`freeStart`; un atendido no se cancela y queda ocupando), nunca horas fijas, y limpian en su
`afterAll` (`retireAgendasNamed`); los datos llevan un sufijo propio de la corrida. **Sin descargar navegadores:** usa el Edge instalado en
Windows (`channel: 'msedge'` en `frontend/playwright.config.ts`) y no se corre `npx playwright install`; en cada PC hace falta solo
Edge, que ya viene con Windows (con otro, `E2E_BROWSER_CHANNEL=chrome`). Qué más necesita y cómo corre, en
[`setup.md`](setup.md#pruebas-de-punta-a-punta-playwright).

## Velocidad del frontend

El detalle de las reglas de velocidad del frontend (antes, `frontend/CLAUDE.md` §8; lo esencial sigue en su
[§8](../../frontend/CLAUDE.md#8-velocidad)).

Reglas para que una entrega no se vaya en esperas (la F0.1 tardó más de 50 minutos, casi la mitad en npm y en verificaciones
repetidas):

* **Versiones fijas en el diseño y un solo `npm ci`.** Las versiones exactas de los paquetes nuevos se deciden y se anotan en el
  diseño de la entrega; la implementación las instala de una vez (`npm install <a>@x <b>@y …`) y después solo `npm ci`. Nada de
  consultar paquete por paquete con `npm view`.
* **`environment: node` para los tests sin DOM.** Preparar jsdom es la mayor parte del tiempo de la suite: los `.test.ts` corren
  en `node` (proyecto `node` de `vite.config.ts`) y solo los `.test.tsx`, o un `.test.ts` que lo pide, en jsdom.
* **Manda el [Presupuesto por entrega](../../CLAUDE.md#presupuesto-por-entrega)** del `CLAUDE.md` de la raíz (Playwright, Newman,
  navegador, mutaciones y revisión).
* **Durante el trabajo, `npx vitest related <archivos tocados> --run`**, o el archivo de test puntual. La suite completa,
  `typecheck`, `lint` y `build`, una sola vez al final de la entrega.
* **Playwright**: en una entrega, solo los casos nuevos o tocados (`npx playwright test e2e/<archivo>.spec.ts`); `npm run test:e2e`
  completo, al cerrar la fase. Si la entrega cambia el menú (`src/app/menu.ts`) o el permiso de una ruta (`requirePermission`, un
  permiso de `PERMISSIONS`), corre también `e2e/permisos.spec.ts` (`npx playwright test e2e/<archivo>.spec.ts e2e/permisos.spec.ts`;
  R-084). **Navegador** (Edge sin ventana): hasta 10 controles, con capturas solo si cambió una pantalla.
* **El script de los controles en Edge** (revisión de la 3.2): ingresa **una sola vez por rol** (un token por rol para toda la
  corrida, no uno por control); **revoca siempre sus tokens al terminar**, en un `finally` que espera el `POST logout` de cada uno
  (también si un control o el script fallan: no alcanza el "Salir" de la interfaz, que lo manda sin esperarlo, ni cerrar el
  navegador); y, si falla por un error del propio script (un selector, una espera, un dato de prueba), se corrige y se repite
  **solo el control que falló**, no la corrida entera. En la 3.2, seis corridas completas dejaron 19 ingresos de `admin-a` en la
  auditoría y 7 tokens sin revocar (desvío 15). Para eso el script **recibe los controles a correr** (`node controles.mjs 5`, o
  `5,6`; sin argumentos, todos) y entra solo con los roles que esos controles declaran: repetir el control 5 de la recepción no
  ingresa con `vet-a`. En la 3.5 el script no tenía esa opción y entraba siempre con los dos roles: una corrida completa y tres
  parciales dejaron 8 ingresos (desvío 10).
* **Los scripts de Edge reutilizan los selectores y ayudantes de Playwright que ya pasaron** (revisión de la 3.6): el ingreso,
  la espera de la pantalla y cada selector de un flujo que un spec ya cubre se copian del spec en verde (o se importan de
  `e2e/support`), no se escriben de nuevo. En la 3.6, el script falló por un `getByLabel('Cantidad de turnos')` sin `exact` que
  el spec ya había corregido, por capturas antes del render y por errores de consola acumulados (desvíos 4 y 6): 6 corridas y
  9 ingresos para 7 controles.
* **Las mutaciones se revierten con el editor** (Edit/Write), nunca con `git checkout`, `git restore` ni `git stash` sobre
  archivos con cambios propios sin commitear: esos comandos devuelven el archivo al último commit y borran también el trabajo de
  la entrega (front-04, desvío 1: el permiso automático rechazó un `git checkout --`). La regla común, en el
  [`CLAUDE.md`](../../CLAUDE.md#ritmo-de-las-entregas) de la raíz.
* **El backend de Playwright corre sin Xdebug** (`XDEBUG_MODE: 'off'` en el `env` de su `webServer`, front-03; con `--no-reload`,
  `php artisan serve` pasa el entorno entero a `php -S`). Laragon carga Xdebug en modo `debug` y cada pedido tardaba de 2,5 a 3
  veces más (la medición de front-03, fuera de Playwright). En la corrida, la mejora es chica porque manda la interfaz:
  `e2e/agendas.spec.ts` y `e2e/permisos.spec.ts` juntos, dos corridas de cada lado, 100 s antes y 99,5 s después en total (con la
  preparación y Vite); `agendas.spec.ts`, de 37,6 a 33,8 s (−10 %), y `permisos.spec.ts`, de 39,8 a 40,3 s (sin cambio: casi no
  hace pedidos lentos). Un servidor ya levantado en `:8010` se reutiliza (`reuseExistingServer`) con su propio Xdebug.
* **Prettier solo sobre los archivos cambiados**: `npx prettier --write <archivos>` (por ejemplo, los de
  `git diff --name-only -- frontend`), no `npm run format` sobre todo el proyecto en cada vuelta.
* **Una entrega, un tema.** El validador de la documentación, las skills y la documentación general (roadmap, handoff,
  `technology-stack.md`) van en entregas o cierres separados, no mezclados con el código del frontend.
* En Windows, excluir `frontend/node_modules` del antivirus (los pasos, en
  [`setup.md`](setup.md#antivirus-de-windows)): sin eso, una instalación tarda varios minutos más.
* **Un Vite levantado para una prueba se detiene por el PID del proceso `vite.js`**, no deteniendo la tarea que lo lanzó
  (`npx` y `timeout` son envoltorios: cortarlos deja vivo a Vite escuchando en su puerto). El PID se busca por el puerto
  (`netstat -ano`) y se confirma con su línea de comando (`vite.js --port <puerto>`) antes de cerrarlo; después se comprueba
  que el puerto quedó libre. Así quedaron vivos el de `:5180` de la 1.2 y, probablemente, el de `:5173` de la 1.1 (revisión
  de la 1.2).
