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
que cambian datos los dejan como estaban (la sucursal y la contraseña). **Sin descargar navegadores:** usa el Edge instalado en
Windows (`channel: 'msedge'` en `frontend/playwright.config.ts`) y no se corre `npx playwright install`; en cada PC hace falta solo
Edge, que ya viene con Windows (con otro, `E2E_BROWSER_CHANNEL=chrome`). Qué más necesita y cómo corre, en
[`setup.md`](setup.md#pruebas-de-punta-a-punta-playwright).
