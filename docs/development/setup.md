# Instalación en una computadora nueva

Requisitos: PHP 8.3+ con `bcmath`, `pdo_mysql`, `pdo_sqlite`, `intl`; Composer; MySQL 8.

```bash
git clone https://github.com/rubenminarro/syncvet.git syncvet
cd syncvet
composer install
cp .env.example .env
php artisan key:generate
# Crear la base en utf8mb4 (ver README.md) y revisar DB_* en .env
php artisan migrate --seed
php artisan test
```

El detalle de las variables de entorno está en el [`README.md`](../../README.md) de la raíz.

En Laragon, `mysql.exe` no está en el `PATH`: está en `C:\laragon\bin\mysql\<versión>\bin\`.

## Datos de demostración y cambio de rama

Para probar la API a mano ([guía de Postman](../testing/guia-postman.md)), el `.env` necesita `DEMO_PASSWORD`, la contraseña de
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

## Tamaño de las subidas (foto del paciente)

La foto del paciente admite hasta 5 MB (FASE 1, entrega 1.5). PHP corta antes que Laravel si el archivo o el pedido superan sus
límites, así que las dos directivas del `php.ini` en uso (`php --ini`) tienen que pasar de 5 MB:

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

## GD (foto del paciente)

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
