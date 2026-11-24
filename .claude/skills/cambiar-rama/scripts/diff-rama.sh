#!/usr/bin/env bash
# Qué cambia en el entorno al pasar de un commit a otro (skill /cambiar-rama). Solo lectura.
# Uso, desde la raíz:  bash .claude/skills/cambiar-rama/scripts/diff-rama.sh <ref-origen> <ref-destino>
# Salida: una línea por tema, "IGUAL|CAMBIA  <tema>  <detalle>", y la ACCION recomendada para la base.

cd "$(git rev-parse --show-toplevel 2>/dev/null || pwd)" || exit 2
from=${1:?falta la ref de origen}; to=${2:?falta la ref de destino}
git rev-parse --verify -q "$from^{commit}" >/dev/null || { echo "ERROR  ref de origen inexistente: $from"; exit 2; }
git rev-parse --verify -q "$to^{commit}" >/dev/null || { echo "ERROR  ref de destino inexistente: $to"; exit 2; }

line() { printf '%-7s %-14s %s\n' "$1" "$2" "$3"; }
changed() { git diff --quiet "$from" "$to" -- "$@" && return 1 || return 0; }

echo "DESDE   $(git log -1 --format='%h %s' "$from" | cut -c1-90)"
echo "HACIA   $(git log -1 --format='%h %s' "$to" | cut -c1-90)"

changed backend/composer.lock && line CAMBIA composer "cd backend && composer install" || line IGUAL composer ""
changed frontend/package-lock.json && line CAMBIA npm "cd frontend && npm ci" || line IGUAL npm ""

# Migraciones: A (nuevas) alcanza con migrate; D o M (borradas o editadas) piden recrear la base.
mig=$(git diff --name-status "$from" "$to" -- backend/database/migrations)
added=$(echo "$mig" | grep -c '^A')
other=$(echo "$mig" | grep -Ec '^[DMR]')
if [ -z "$mig" ]; then line IGUAL migraciones ""
else
  line CAMBIA migraciones "$added nueva(s), $other borrada(s) o editada(s)"
  echo "$mig" | sed 's|backend/database/migrations/||' | head -n 15 | sed 's/^/          /'
fi

changed backend/database/seeders && line CAMBIA seeders "$(git diff --name-only "$from" "$to" -- backend/database/seeders | sed 's|.*/||' | tr '\n' ' ')" || line IGUAL seeders ""
changed backend/database/seeders/RolesAndPermissionsSeeder.php && line CAMBIA permisos "php artisan permissions:sync (con datos conservados)" || line IGUAL permisos ""
changed backend/config backend/.env.example && line CAMBIA config "php artisan optimize:clear" || line IGUAL config ""

newk=$(comm -13 <(git show "$from:backend/.env.example" 2>/dev/null | grep -Eo '^[A-Z_]+=' | sort -u) \
                <(git show "$to:backend/.env.example" 2>/dev/null | grep -Eo '^[A-Z_]+=' | sort -u) | tr -d '=' | tr '\n' ' ')
[ -n "$newk" ] && line CAMBIA env "claves nuevas en .env.example: $newk" || line IGUAL env ""
changed frontend/.env.example && line CAMBIA env-front "revisar frontend/.env contra .env.example" || true

# Acción recomendada para la base de desarrollo
if [ "$other" -gt 0 ]; then
  echo "ACCION  recrear: migrate:fresh --seed, DemoSeeder y demo:agenda (hay migraciones borradas o editadas)"
elif [ "$added" -gt 0 ]; then
  echo "ACCION  migrar: migrate y permissions:sync (o recrear, si se prefieren datos limpios)"
else
  echo "ACCION  ninguna en la base"
fi
