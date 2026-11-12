# Instalación en una computadora nueva

Requisitos: PHP 8.3+ con `bcmath`, `pdo_mysql`, `pdo_sqlite`, `intl`; Composer; MySQL 8.

```bash
git clone https://github.com/rubenminarro/syncvet.git syncvet
cd syncvet/backend
composer install
cp .env.example .env
php artisan key:generate
# Crear la base en utf8mb4 (ver README.md) y revisar DB_* en .env
php artisan migrate --seed
php artisan test
```

El repositorio es un monorepo (R-057): los comandos de Laravel se corren desde `backend/`; Claude Code, las skills y Newman, desde la
raíz. El detalle de las variables de entorno está en el [`README.md`](../../backend/README.md) del backend.

En Laragon, el vhost de nginx tiene que apuntar a `backend/public`: ver el paso 5 de [Migración al monorepo](#migración-al-monorepo-r-057).

En Laragon, `mysql.exe` no está en el `PATH`: está en `C:\laragon\bin\mysql\<versión>\bin\`.

## Frontend

Requisitos: **Node 26 LTS** con npm 11, en todas las PC (`frontend/.nvmrc`; con `engine-strict`, `npm ci` se niega a instalar con
otra versión). Con nvm para Windows: `nvm install 26` y `nvm use 26`.

```bash
cd frontend
npm ci
npm run dev    # http://localhost:5173
```

El servidor de Vite manda `/api` al backend de `API_PROXY_TARGET` (por defecto `http://syncvet.test`, el vhost de Laragon): para otro,
copiar `frontend/.env.example` a `frontend/.env` y cambiarlo. El detalle, en [`frontend/README.md`](../../frontend/README.md).

### Pruebas de punta a punta (Playwright)

Desde la F0.2b, contra el backend real y la base **`syncvet_e2e`** (MySQL), que se borra y se vuelve a crear en cada corrida:

```bash
cd frontend
npm run test:e2e        # playwright test; el informe queda en playwright-report/ (npx playwright show-report)
```

Qué necesita:

* MySQL andando (Laragon) con el usuario de `backend/.env`, y `php` en el `PATH`. La base `syncvet_e2e` no hace falta crearla:
  la preparación la crea si falta.
* El backend contra `syncvet_e2e` en `http://127.0.0.1:8010`. Playwright lo levanta solo (`php artisan serve --no-reload` desde
  `backend/`, con `DB_DATABASE=syncvet_e2e` en el entorno); si ya hay uno levantado en ese puerto, usa ese, que tiene que usar
  `syncvet_e2e`. También levanta Vite en `http://localhost:5174` con `/api` hacia ese backend (no choca con `npm run dev`).
* Edge instalado: Playwright usa el navegador de la PC (`channel: 'msedge'`) y no descarga ninguno. Con otro,
  `E2E_BROWSER_CHANNEL=chrome npm run test:e2e`.

La preparación (`frontend/e2e/global-setup.ts`) **se niega a correr si la base no es `syncvet_e2e`**: controla el nombre pedido y
el que resuelve Laravel con ese entorno (una configuración cacheada daría otro). Después hace `migrate:fresh --seed` y el
`DemoSeeder` con una contraseña generada en cada corrida (`DEMO_PASSWORD` solo para esa ejecución; la de `backend/.env` no se
usa), ingresa con ella en el backend de `:8010` (si no puede, ese backend no usa la base recién preparada y se detiene) y crea por
la API la sucursal "Sucursal Norte" para `admin-a`. No toca la base `syncvet` de desarrollo.

## Antivirus de Windows

Microsoft Defender revisa cada archivo que se escribe: `npm ci` (decenas de miles de archivos en `frontend/node_modules`) y
`composer install` (`backend/vendor`) tardan varios minutos más, y también Vitest y Vite al leerlos. Se recomienda excluir **solo
esas dos carpetas** (no todo el repositorio ni el proceso `node.exe`): son dependencias que se bajan de npm y de Packagist y se
pueden volver a instalar en cualquier momento.

Con la interfaz:

1. Abrir **Seguridad de Windows** → **Protección antivirus y contra amenazas**.
2. En **Configuración de antivirus y protección contra amenazas**, **Administrar la configuración**.
3. Bajar hasta **Exclusiones** → **Agregar o quitar exclusiones** (pide permiso de administrador).
4. **Agregar una exclusión** → **Carpeta** y elegir `C:\laragon\www\syncvet\frontend\node_modules` (la carpeta tiene que existir:
   correr antes `npm ci`).
5. Repetir con `C:\laragon\www\syncvet\backend\vendor`.

Con PowerShell, abierto como administrador (con la ruta del repositorio de esa PC):

```powershell
Add-MpPreference -ExclusionPath "C:\laragon\www\syncvet\frontend\node_modules"
Add-MpPreference -ExclusionPath "C:\laragon\www\syncvet\backend\vendor"
Get-MpPreference | Select-Object -ExpandProperty ExclusionPath   # verificar
```

Si la PC la administra la empresa, la política puede no permitir exclusiones propias: en ese caso las agrega el área de sistemas.
Para sacar una exclusión, `Remove-MpPreference -ExclusionPath "<ruta>"`.

## Datos de demostración y cambio de rama

Para probar la API a mano ([guía de Postman](../05-pruebas/guia-postman.md)), el `.env` necesita `DEMO_PASSWORD`, la contraseña de
los usuarios del `DemoSeeder`, **entre comillas simples**:

```dotenv
DEMO_PASSWORD='<al menos 12 caracteres, con mayúsculas, minúsculas, números y símbolos>'
```

Las comillas simples dejan el valor literal: sin ellas, un `#` puede leerse como el comienzo de un comentario y un `$` como una
variable. Un `.env` copiado antes de la entrega 0.4 no tiene la línea: agregarla (está en `.env.example`). Sin ella, el `DemoSeeder`
se detiene con "Definí DEMO_PASSWORD en .env".

**Después de cambiar a una rama con migraciones nuevas, recrear la base** antes de probar:

```bash
php artisan migrate:fresh --seed
php artisan db:seed --class=DemoSeeder
```

Con una base vieja, la API falla de formas que no dicen la causa. En la validación de la entrega 1.2 (FASE 1), el login respondió
500: la base `syncvet` tenía el esquema anterior a la entrega 0.2, sin `audit_logs.clinic_id`, y el login fallido no pudo
registrarse en la auditoría. `php artisan migrate:status` muestra las migraciones pendientes. `migrate:fresh` borra todos los
datos: usarlo solo en la base de desarrollo.

## Certificados de PHP en Laragon

La regla de contraseñas consulta Have I Been Pwned por HTTPS (R-009). Si `curl.cainfo` apunta a un `cacert.pem` que no existe, toda
consulta HTTPS de PHP falla (cURL error 77) y la regla acepta cualquier contraseña, también las filtradas. Pasó con el `php.ini` de
Laragon, que traía la ruta de la máquina donde se armó el instalador (`D:\Projects\Laragon-installer\…`), R-011.

1. Ubicar el `php.ini` en uso: `php --ini` (en Laragon, `C:\laragon\bin\php\<versión>\php.ini`).
2. Apuntar las dos directivas al `cacert.pem` de Laragon:

   ```ini
   curl.cainfo="C:\laragon\etc\ssl\cacert.pem"
   openssl.cafile="C:\laragon\etc\ssl\cacert.pem"
   ```

   `openssl.cafile` suele estar comentada (`;openssl.cafile=`): agregar la línea debajo.
3. Reiniciar Laragon (o el servidor que use ese `php.ini`) y comprobar:

   ```bash
   php -r "echo ini_get('curl.cainfo'), PHP_EOL;"
   php artisan tinker --execute="var_dump(App\Support\UserPassword::error('Password123!'));"
   ```

   El segundo comando debe mostrar el mensaje de contraseña filtrada, no `NULL`. Con `NULL`, `storage/logs/laravel.log` tiene el aviso
   de Have I Been Pwned con el `curl_error`.

## Tamaño de las subidas (foto del paciente y logo de la clínica)

La foto del paciente admite hasta 5 MB (FASE 1, entrega 1.5) y el logo de la clínica, hasta 2 MB (FASE 2, entrega 2.5). PHP corta
antes que Laravel si el archivo o el pedido superan sus límites, así que las dos directivas del `php.ini` en uso (`php --ini`)
tienen que pasar de 5 MB:

```ini
upload_max_filesize = 8M
post_max_size = 10M
```

`post_max_size` va por encima de `upload_max_filesize`: el pedido lleva, además del archivo, los encabezados del formulario. Con
límites más bajos, una foto válida responde 413 `PAYLOAD_TOO_LARGE` (o el servidor web la rechaza antes, con su propio 413). En
producción, el servidor web también debe aceptar el tamaño (en Nginx, `client_max_body_size 10M;`). Comprobar con:

```bash
php -r "echo ini_get('upload_max_filesize'), ' ', ini_get('post_max_size'), PHP_EOL;"
```

Las fotos se guardan en su propio disco, `patient_photos` (`storage/app/private/patient-photos`, fuera de `public/`). Ningún disco
tiene `serve` (`config/filesystems.php`; R-027): Laravel no publica la ruta `/storage/{path}`, ni con una URL firmada, y
`storage:link` solo enlaza el disco `public`. La foto se descarga únicamente con `GET api/patients/{patient}/photo`, con token y
dentro de la clínica.

El logo de cada clínica va en otro disco, `clinic_logos` (`storage/app/private/clinic-logos`, en `clinics/{clinic_id}/logo/`),
también sin `serve`: se descarga únicamente con `GET api/system-settings/logo`. Las dos carpetas se crean solas con la primera
subida; en un respaldo van junto con la base (las rutas guardadas en `patients.photo_path` y en la clave `logo_path` de
`system_settings` apuntan a ellas).

## Limpieza de archivos huérfanos

`php artisan files:prune-orphans` (FASE 2, entrega 2.5; deuda 14) borra, en las carpetas de fotos y de logos de cada clínica, los
archivos de más de 24 horas que ninguna fila usa: ni el `photo_path` de un paciente (también eliminado) ni el `logo_path` de su
clínica. Quedan de un reemplazo cuyo borrado falló o de un proceso que murió antes del commit. Nunca borra fuera de esas carpetas.

```bash
php artisan files:prune-orphans --dry-run   # lista lo que borraría, sin borrar
php artisan files:prune-orphans
```

Cada borrado queda en el log (`storage/logs`). Termina con código 1 si una clínica no se pudo revisar (en esa no borra nada y sigue
con las demás) o si un borrado falló. Está programado a diario a las 03:00 (`routes/console.php`): en desarrollo no corre solo
(se puede probar con `php artisan schedule:list` y `php artisan schedule:run`); en producción necesita el cron del *scheduler*
(`* * * * * php artisan schedule:run`), que se configura en el despliegue (FASE 10, entrega 10.5).

## GD (foto del paciente y logo de la clínica)

Al subir la foto, la imagen se vuelve a codificar con GD en su mismo formato (R-027): así se pierden los metadatos (EXIF, también
el GPS) y todo lo que venga pegado después de la imagen. La extensión `gd` de PHP es **obligatoria**, con soporte de JPEG, PNG y
WEBP; sin ella, o sin alguno de los tres formatos, toda foto de ese formato responde 422. La extensión `exif` se usa para aplicar
la orientación de la foto antes de descartar el EXIF: sin ella, una foto vertical de un teléfono puede quedar acostada. En el
`php.ini` (`php --ini`), las dos sin `;` adelante:

```ini
extension=gd
extension=exif
```

Comprobar con:

```bash
php -r "\$g = gd_info(); var_dump(\$g['JPEG Support'], \$g['PNG Support'], \$g['WebP Support'], extension_loaded('exif'));"
```

Las cuatro deben dar `bool(true)`. Una foto se decodifica en memoria (unos 4 bytes por píxel; el límite es 25 megapíxeles, unos
100 MB): `memory_limit` de al menos 256M (Laragon trae 512M).

## Zona horaria de Paraguay (base de zonas de PHP)

Desde el 15/10/2024, `America/Asuncion` es UTC-3 todo el año, sin horario de verano (ley promulgada el 14/10/2024). La base de
zonas de la IANA lo incluye desde la versión **2025a**. Las fechas con hora se convierten con esa zona de PHP (`AppDateTime` y
`ApiDateTime`, FASE 2, entrega 2.6; deuda 13): con una base anterior, de marzo a octubre PHP sigue usando `-04:00` y toda fecha con
hora queda una hora corrida. PHP 8.3.16, por ejemplo, trae la 2024.2.

Comprobar con:

```bash
php -r "echo timezone_version_get(), PHP_EOL;"
php -r "echo (new DateTime('2026-07-01 12:00', new DateTimeZone('America/Asuncion')))->format('P'), PHP_EOL;"
```

La primera debe dar `2025.1` o posterior (con la base del sistema, en los paquetes de Linux, da `0.system`: vale la segunda), y la
segunda `-03:00`. Si no, actualizar PHP a una versión que traiga la base 2025a o instalar la extensión `timezonedb` de PECL
(`extension=timezonedb` en el `php.ini`), que reemplaza la base de PHP. `tests/Unit/AsuncionTimezoneTest` falla con una base vieja.

### `timezonedb` en Laragon (Windows)

Así se instaló en el entorno de desarrollo (PHP 8.3.16 de Laragon, que trae la base 2024.2; con la extensión, 2026.6):

1. Ver qué DLL hace falta. Laragon trae PHP **Thread Safe** (TS), de 64 bits, compilado con Visual C++ 2019 (`vs16`):

   ```bash
   php -r "echo PHP_VERSION, ' ', PHP_ZTS ? 'TS' : 'NTS', ' ', PHP_INT_SIZE * 8, ' bits', PHP_EOL;"
   php -i | findstr /C:"Compiler" /C:"Loaded Configuration File" /C:"extension_dir"
   ```

   Tiene que dar `8.3.x TS 64 bits`; anotar la ruta del `php.ini` y la carpeta `ext`
   (`C:\laragon\bin\php\php-8.3.16-Win32-vs16-x64\ext`).
2. Descargar la última versión de <https://pecl.php.net/package/timezonedb>: en la versión, el enlace **DLL**, y ahí el zip
   **8.3 Thread Safe (TS) x64**. Tiene que coincidir con lo del paso 1: con una NTS o de 32 bits, PHP avisa al arrancar
   ("Unable to load dynamic library") y no la carga.
3. Copiar `php_timezonedb.dll` del zip a la carpeta `ext` del PHP en uso (las demás DLL del zip no hacen falta).
4. En ese `php.ini` (Laragon: menú **PHP → php.ini**, el mismo que usa la consola), agregar una línea:

   ```ini
   extension=timezonedb
   ```

5. Reiniciar Laragon (**Stop** y **Start All**) para que Apache la tome; la consola la toma en el siguiente `php`.
6. Comprobar:

   ```bash
   php -m | findstr timezonedb                                   # timezonedb
   php -r "echo timezone_version_get(), PHP_EOL;"                # 2025.1 o posterior
   php -i | findstr /C:"Timezone Database"                       # Timezone Database => external
   php artisan test tests/Unit/AsuncionTimezoneTest.php          # 2 tests en verde
   ```

Al cambiar de versión de PHP en Laragon hay que repetir los pasos con la DLL de esa versión. Cuando la IANA publique un cambio que
afecte a Paraguay, actualizar la DLL con la versión nueva de PECL.

## Zona de la sesión de MySQL

Las columnas `timestamp` de MySQL se convierten con la zona de la sesión al guardar y al leer. Para que no dependan de la zona del
servidor, las conexiones `mysql` y `mariadb` fijan `'timezone' => env('DB_TIMEZONE') ?: '-03:00'` (`config/database.php`; deuda 16,
R-047 y R-049), y Laravel manda `SET time_zone='-03:00'` al conectar y al reconectar. En el `.env`:

```dotenv
DB_TIMEZONE=-03:00
```

Es la zona de la sesión de MySQL, no la de la aplicación (`America/Asuncion`, `config/app.php`). Va un desplazamiento fijo y no
`America/Asuncion` porque MySQL de Laragon no tiene cargadas las zonas con nombre (`mysql.time_zone_name` vacía) y, si las tuviera,
podrían traer el horario de verano viejo de Paraguay. No dejarla vacía: hoy una variable vacía toma `-03:00`, pero otro valor
inválido rompe toda conexión. Las conversiones de las fechas siguen en PHP (`AppDateTime`), y las horas de negocio las pone PHP, nunca
el reloj de MySQL (`CLAUDE.md` §3).

Para comprobarlo:

```bash
php artisan tinker --execute="echo DB::scalar('SELECT @@session.time_zone'), PHP_EOL;"   # -03:00
composer test:mysql                                                                       # MySqlSessionTimezoneTest, en verde
```

## Migración al monorepo (R-057)

En cada PC que ya tenía el repositorio con el backend en la raíz, una sola vez, después del merge de `chore/monorepo` a `main` (o
antes, con `git switch chore/monorepo`):

1. Cerrar Laragon (Stop All), VS Code y todo `php artisan serve`: si algo tiene tomada una carpeta, Git no la puede mover. Después,
   `git pull`.
2. Mover `.env` a `backend/.env`. Si hay fotos o logos subidos, mover `storage/app/private/*` a `backend/storage/app/private/`.
3. Revisar lo que quedó en la raíz con `git status --ignored`: borrar `vendor/` y `.phpunit.result.cache`, y `bootstrap/`,
   `database/` y `storage/` si solo les quedan archivos ignorados (cachés, bases de prueba, logs).
4. `cd backend && composer install && php artisan optimize:clear`.
5. Laragon: en `C:\laragontc
ginx\sites-enabled\`, copiar `auto.syncvet.test.conf` como `syncvet.test.conf`, cambiar `root` a
   `"C:/laragon/www/syncvet/backend/public"` y borrar el `auto.` (Laragon lo regenera con la raíz
   vieja). Iniciar Laragon: `http://syncvet.test/api/me` responde 401 en JSON.
6. Servidor para Postman: `cd backend && php artisan serve --port=8001`. Newman, desde la raíz.
7. Postman: la carpeta de trabajo sigue siendo la raíz del repositorio; volver a importar las colecciones de `docs/05-pruebas/postman/`
   (el Environment no cambia).
8. Claude Code, siempre desde la raíz.
