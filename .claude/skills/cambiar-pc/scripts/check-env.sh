#!/usr/bin/env bash
# Diagnóstico del entorno de SyncVet en esta PC (skill /cambiar-pc).
# Solo lectura: no instala, no migra y no muestra ningún valor del .env.
# Uso, desde la raíz del repositorio:  bash .claude/skills/cambiar-pc/scripts/check-env.sh
# Salida: una línea por control, "OK|AVISO|FALLA  <control>  <detalle>", y al final el resumen.

cd "$(git rev-parse --show-toplevel 2>/dev/null || pwd)" || exit 2

fails=0; warns=0
ok()   { printf 'OK     %-22s %s\n' "$1" "$2"; }
warn() { printf 'AVISO  %-22s %s\n' "$1" "$2"; warns=$((warns + 1)); }
fail() { printf 'FALLA  %-22s %s\n' "$1" "$2"; fails=$((fails + 1)); }

# --- Git -------------------------------------------------------------------
branch=$(git branch --show-current)
dirty=$(git status --porcelain | wc -l | tr -d ' ')
[ "$dirty" = 0 ] && ok git "rama $branch, limpia" || warn git "rama $branch, $dirty archivo(s) con cambios"
if git rev-parse --verify -q '@{u}' >/dev/null; then
  read -r ahead behind < <(git rev-list --left-right --count 'HEAD...@{u}')
  if [ "$behind" != 0 ]; then warn git-remoto "$behind commit(s) detrás de $(git rev-parse --abbrev-ref '@{u}') (git pull --ff-only)"
  else ok git-remoto "al día con $(git rev-parse --abbrev-ref '@{u}'), $ahead adelante"; fi
else
  warn git-remoto "la rama $branch no sigue a una remota"
fi

# Restos de la migración al monorepo (R-057) en la raíz
for p in vendor .env storage bootstrap database; do
  [ -e "$p" ] && warn monorepo "queda ./$p en la raíz (setup.md, paso 3 de la migración al monorepo)"
done

# --- PHP -------------------------------------------------------------------
if ! command -v php >/dev/null; then
  fail php "no está en el PATH"
else
  php -r 'exit(version_compare(PHP_VERSION, "8.3.0", ">=") ? 0 : 1);' \
    && ok php "$(php -r 'echo PHP_VERSION, PHP_ZTS ? " TS" : " NTS";')" \
    || fail php "$(php -r 'echo PHP_VERSION;') (hace falta 8.3+)"

  missing=$(php -r 'foreach (["bcmath","pdo_mysql","pdo_sqlite","intl","gd","exif","fileinfo","mbstring","openssl"] as $e) if (!extension_loaded($e)) echo $e, " ";')
  [ -z "$missing" ] && ok php-ext "bcmath pdo_mysql pdo_sqlite intl gd exif fileinfo mbstring openssl" \
    || fail php-ext "faltan: $missing(setup.md, GD)"

  gd=$(php -r 'if (!function_exists("gd_info")) { echo "sin gd"; exit; } $g = gd_info(); foreach (["JPEG Support","PNG Support","WebP Support"] as $k) if (empty($g[$k])) echo $k, " ";')
  [ -z "$gd" ] && ok gd-formatos "JPEG PNG WEBP" || fail gd-formatos "falta: $gd(setup.md, GD)"

  tz=$(php -r 'echo (new DateTime("2026-07-01 12:00", new DateTimeZone("America/Asuncion")))->format("P"), " ", timezone_version_get();')
  [ "${tz%% *}" = "-03:00" ] && ok zona-php "julio en $tz" \
    || fail zona-php "julio en $tz; hace falta -03:00 (setup.md, timezonedb en Laragon)"

  ca=$(php -r 'echo ini_get("curl.cainfo");')
  if [ -z "$ca" ]; then fail curl.cainfo "vacío (setup.md, Certificados de PHP en Laragon)"
  elif [ -f "$ca" ]; then ok curl.cainfo "$ca"
  else fail curl.cainfo "apunta a un archivo que no existe: $ca (setup.md, Certificados)"; fi
  osa=$(php -r 'echo ini_get("openssl.cafile");')
  [ -n "$osa" ] && [ -f "$osa" ] && ok openssl.cafile "$osa" \
    || warn openssl.cafile "vacío o inexistente (setup.md, Certificados)"

  lim=$(php -r '
    $b = function ($v) { $v = trim($v); if ($v === "-1") return PHP_INT_MAX; $n = (int) $v;
      switch (strtolower(substr($v, -1))) { case "g": $n *= 1024; case "m": $n *= 1024; case "k": $n *= 1024; } return $n; };
    $u = $b(ini_get("upload_max_filesize")); $p = $b(ini_get("post_max_size")); $m = $b(ini_get("memory_limit"));
    $mb = 1048576; $err = [];
    if ($u <= 5 * $mb) $err[] = "upload_max_filesize=" . ini_get("upload_max_filesize");
    if ($p < $u || $p <= 5 * $mb) $err[] = "post_max_size=" . ini_get("post_max_size");
    if ($m < 256 * $mb) $err[] = "memory_limit=" . ini_get("memory_limit");
    echo $err ? implode(" ", $err) : "ok " . ini_get("upload_max_filesize") . "/" . ini_get("post_max_size") . "/" . ini_get("memory_limit");')
  case "$lim" in ok*) ok php-limites "${lim#ok }" ;; *) fail php-limites "$lim (setup.md, Tamaño de las subidas)" ;; esac
fi

command -v composer >/dev/null && ok composer "$(composer --version --no-ansi 2>/dev/null | awk '{print $3}')" || fail composer "no está en el PATH"

# --- Node ------------------------------------------------------------------
want=$(tr -d ' \r\n' < frontend/.nvmrc 2>/dev/null)
if command -v node >/dev/null; then
  have=$(node -p 'process.versions.node.split(".")[0]')
  [ "$have" = "$want" ] && ok node "$(node -v), npm $(npm -v 2>/dev/null)" \
    || fail node "$(node -v); hace falta la $want (nvm install $want && nvm use $want)"
  npmv=$(npm -v 2>/dev/null); [ "${npmv%%.*}" = 11 ] || warn npm "npm $npmv; se espera la 11"
else
  fail node "no está en el PATH (hace falta la $want)"
fi

# --- backend/.env (solo nombres de claves, nunca valores) --------------------
envf=backend/.env
if [ ! -f "$envf" ]; then
  fail env "no existe backend/.env (copiar .env.example y php artisan key:generate)"
else
  for k in APP_KEY DB_DATABASE DB_USERNAME DB_TIMEZONE DEMO_PASSWORD; do
    grep -Eq "^${k}=.+" "$envf" || fail env "$k falta o está vacía"
  done
  grep -Eq "^DEMO_PASSWORD='.+'" "$envf" || warn env "DEMO_PASSWORD sin comillas simples (con dobles, $ y \ se interpretan) (setup.md, Datos de demostración)"
  # Claves nuevas de .env.example que este .env no tiene
  newk=$(comm -23 <(grep -Eo '^[A-Z_]+=' backend/.env.example | sort -u) <(grep -Eo '^[A-Z_]+=' "$envf" | sort -u) | tr -d '=' | tr '\n' ' ')
  [ -z "$newk" ] && ok env "claves completas respecto de .env.example" || warn env "claves de .env.example que faltan: $newk"
fi
[ -f backend/bootstrap/cache/config.php ] && warn config-cache "hay configuración cacheada (php artisan optimize:clear; Playwright se niega con ella)"

# --- Dependencias ----------------------------------------------------------
if [ ! -d backend/vendor ]; then fail vendor "no existe (cd backend && composer install)"
else
  out=$(cd backend && composer install --dry-run --no-interaction 2>&1)
  echo "$out" | grep -q 'Nothing to install, update or remove' && ok vendor "al día con composer.lock" \
    || warn vendor "no coincide con composer.lock (cd backend && composer install)"
fi
if [ ! -d frontend/node_modules ]; then fail node_modules "no existe (cd frontend && npm ci)"
else
  (cd frontend && npm ls --depth=0 >/dev/null 2>&1) && ok node_modules "al día con package-lock.json" \
    || warn node_modules "no coincide con package-lock.json (cd frontend && npm ci)"
fi

# --- Base de datos ---------------------------------------------------------
if [ -f "$envf" ] && [ -d backend/vendor ]; then
  st=$(cd backend && php artisan migrate:status --no-ansi 2>&1)
  if [ $? -ne 0 ]; then
    fail mysql "migrate:status falló: $(echo "$st" | grep -m1 -Ei 'error|exception|refused' | cut -c1-120)"
  else
    pend=$(echo "$st" | grep -c 'Pending')
    [ "$pend" = 0 ] && ok migraciones "sin pendientes" || warn migraciones "$pend pendiente(s) (migrate + permissions:sync, o migrate:fresh --seed)"
    stz=$(cd backend && php artisan tinker --execute="echo DB::scalar('SELECT @@session.time_zone');" 2>/dev/null | tr -d '\r\n')
    [ "$stz" = "-03:00" ] && ok zona-mysql "$stz" || fail zona-mysql "sesión en '$stz' (setup.md, Zona de la sesión de MySQL)"
  fi
fi

# --- Laragon (nginx) y Edge -----------------------------------------------
conf=/c/laragon/etc/nginx/sites-enabled
if [ -d "$conf" ]; then
  [ -f "$conf/auto.syncvet.test.conf" ] && warn vhost "existe auto.syncvet.test.conf (setup.md, paso 5 de la migración al monorepo)"
  if [ -f "$conf/syncvet.test.conf" ]; then
    grep -q 'syncvet/backend/public' "$conf/syncvet.test.conf" && ok vhost "syncvet.test -> backend/public" \
      || fail vhost "syncvet.test.conf no apunta a backend/public"
  else
    fail vhost "falta syncvet.test.conf (setup.md, paso 5 de la migración al monorepo)"
  fi
fi
code=$(curl -s -o /dev/null -m 5 -w '%{http_code}' http://syncvet.test/api/me 2>/dev/null)
[ "$code" = 401 ] && ok api "http://syncvet.test/api/me responde 401" \
  || warn api "http://syncvet.test/api/me responde '$code' (¿Laragon iniciado?)"

edge="/c/Program Files (x86)/Microsoft/Edge/Application/msedge.exe"
[ -f "$edge" ] && ok edge "instalado (Playwright, channel msedge)" || warn edge "no se encontró msedge.exe (E2E_BROWSER_CHANNEL=chrome)"

echo "RESUMEN  fallas=$fails avisos=$warns"
[ "$fails" = 0 ]
