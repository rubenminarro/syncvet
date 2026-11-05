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
