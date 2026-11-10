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
