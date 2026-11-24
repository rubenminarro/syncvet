---
name: cerrar-fase
description: Cierra una fase ya entregada: corre la regresión completa de cierre (backend en SQLite y en MySQL en serie, composer audit, frontend completo, Playwright y Newman con todas las colecciones), documenta el cierre, lo commitea y lo sube, y con autorización hace el merge --no-ff a main.
disable-model-invocation: true
argument-hint: "<backend N | frontend FN>"
allowed-tools: Read Grep Glob Bash(git status *) Bash(git status) Bash(git log *) Bash(git diff *) Bash(git branch *) Bash(git rev-parse *) Bash(git rev-list *) Bash(git fetch *) Bash(git add *) Bash(git commit *) Bash(git push *) Bash(php .claude/skills/validar-docs/scripts/check-docs.php*)
---

# Cerrar una fase

Fase a cerrar: $ARGUMENTS (por ejemplo `backend 5` o `frontend F4`). Sin argumento, preguntar cuál.

Invocar esta Skill es el pedido del usuario de correr la regresión de cierre y de commitear y subir el cierre. **El merge a `main`
no**: se pide aparte, en el paso 6. Las reglas que aplica son las del `CLAUDE.md` de la raíz, §2 (**Presupuesto por entrega** y
**Cierre de fase**); esta Skill no las cambia.

Todas las salidas de los tests van a un archivo del scratchpad; se leen solo el resumen y las fallas. Lo largo corre en segundo
plano y se espera el aviso de fin, sin consultar en bucle. Solo se detienen los procesos que levantó esta Skill, por su tarea o su
PID (nunca por nombre).

## 1. Comprobar que la fase esté lista

Si algo falta, **detenerse e informar qué falta**.

* Se está en la última rama de la fase, no en `main`, con el árbol limpio y al día con su remota (`git status -sb`).
* En la tabla **Entregas** del diseño de la fase (`docs/03-plan-desarrollo/fases/backend/fase-NN-*.md` o
  `fases/frontend/fN-*.md`), todas las entregas están `COMPLETADO (R-NNN, …)`. Leer solo esa tabla y el "Pendiente para el cierre"
  de la última entrega, no el diseño entero.
* La base de desarrollo (`syncvet`) está migrada y con el `DemoSeeder` (Newman la usa): `php artisan migrate:status` desde `backend/`,
  sin pendientes. Si hay pendientes, proponer `/cambiar-rama` o `migrate` y detenerse.

## 2. Regresión de cierre (los dos proyectos)

Se corren **los dos lados**, cualquiera sea la fase: en el cierre de la FASE 4 del backend, solo la suite del frontend detectó los
tipos auditables que faltaban (R-106). En este orden, cada paso con su salida a un archivo:

**Backend**, desde `backend/`:

1. `composer test`: SQLite en paralelo.
2. `composer test:mysql`: MySQL **en serie** (`syncvet_test_mysql`), en segundo plano. Es la corrida más larga.
3. `composer audit`: tiene que decir "No security vulnerability advisories found.". Si hay un aviso, seguir
   `docs/04-desarrollo/testing.md` §"Dependencias" y consultarlo con el usuario antes de actualizar nada.

**Frontend**, desde `frontend/`:

4. `npm run typecheck`, `npm run lint`, `npm test` y `npm run build`: sin errores ni avisos.
5. Desde `backend/`, `php artisan test --filter=FictitiousDocumentsTest`.
6. `npm audit`: informar los avisos y compararlos con la deuda 19 (`deuda-tecnica.md`); no actualizar sin el usuario.
7. `npm run test:e2e`: Playwright completo, en segundo plano. Levanta su propio backend en `:8010` contra `syncvet_e2e`.

**Newman**, desde la raíz (`docs/05-pruebas/guia-postman.md`, primeras líneas, si hace falta el detalle):

8. Levantar el servidor en segundo plano, desde `backend/`, sin Xdebug: `XDEBUG_MODE=off php artisan serve --port=8001`. Anotar la
   tarea para detenerla después.
9. `node docs/05-pruebas/newman-todas.mjs`: **todas** las colecciones de `docs/05-pruebas/postman`, cada una con su limpieza.
   Anotar pedidos y aserciones de cada colección. No usar la demo en el navegador mientras corre (la limpieza revoca sus tokens).
10. Detener el servidor del paso 8 (por su tarea). Siempre, también si Newman falló.

**Si algo falla:** mostrar la falla. Corregirla en un commit aparte (en el cierre de la FASE 4, "Suma a la auditoría del frontend
los tipos…"), o registrarla como deuda si el usuario lo decide. Después, **repetir la corrida completa del lado que cambió**, para que
la cifra registrada sea la de una corrida real en verde. Un cambio de código en el cierre se consulta antes con el usuario si toca una
regla de negocio, un contrato, un permiso o un `error_code`.

## 3. Reunir los hechos

* Las cifras salen de las corridas del paso 2, nunca de memoria.
* **Del backend:**
  * Rutas: `php artisan route:list --path=api --json`, contadas.
  * Permisos: los de `RolesAndPermissionsSeeder`.
  * Migraciones: los archivos de `database/migrations`.
  * Compararlas con el cierre anterior, que figura en `backend/CLAUDE.md` §1.
* **Por entrega:** el commit de código y el de cierre, y la suite al cerrar. Salen de la sección de cada entrega en el
  `…-resultados.md` de la fase (solo las líneas de "Commits" y "Suite"), o de `git log`.
* **Las deudas** nuevas, resueltas y abiertas, de `deuda-tecnica.md`.
* **Las decisiones** de la fase, del índice del registro.

## 4. Documentar el cierre

Valen las reglas de la tabla del paso 4 de [`cerrar-entrega`](../cerrar-entrega/SKILL.md) (no inventar, una fuente, el registro
solo crece, el handoff se reemplaza) y la tabla **Mantenimiento** de `docs/README.md`. Si algo no encaja, preguntar antes de editar.

| Documento | Qué cambia |
|---|---|
| `…-resultados.md` de la fase | Sección final `## Cierre de la FASE N` (o `## Cierre de la FN`), con el modelo del [cierre de la FASE 4](../../../docs/03-plan-desarrollo/fases/backend/fase-04-resultados.md#cierre-de-la-fase-4). Lleva: qué hace la clínica ahora (lista corta); las ramas y el merge; la tabla de entregas (contenido, commits, suite); rutas, permisos y migraciones antes → después; la suite en SQLite y en MySQL; `composer audit`; la tabla de Newman por colección; la regresión del frontend, con sus desvíos; el criterio de cierre del diseño, punto por punto, con su evidencia; las deudas; las decisiones; y lo pendiente para lo que sigue |
| Registro | `R-NNN Cierre de la FASE N y merge a main (AAAA-MM-DD)` al final del archivo de su rango, y su línea al final del índice |
| Diseño de la fase | La tabla de entregas, sin cambios si ya está completa; el estado de la fase, `COMPLETADA (R-NNN)` |
| Roadmap (`docs/03-plan-desarrollo/README.md`) | El estado de la fase: `COMPLETADO (R-NNN, merge a main)` y sus entregas |
| `modulos.md` | El estado de los módulos de la fase |
| `handoff.md` | Se reemplazan el punto de continuidad (checkpoint con las cifras de las dos suites, Newman y Playwright) y las próximas tareas. La siguiente tarea es decidir con el usuario lo que sigue |
| `CLAUDE.md` raíz §3, `backend/CLAUDE.md` §1 y `frontend/CLAUDE.md` §1 | Se reemplaza el estado: la fase cerrada, las cifras nuevas (rutas, permisos, migraciones y casos; tests de Vitest y de Playwright) y lo que sigue |
| `deuda-tecnica.md` | Solo si la regresión dejó una deuda nueva o resolvió una |

Después, desde la raíz: `php .claude/skills/validar-docs/scripts/check-docs.php`. Corregir lo que rompió el cierre (enlaces, cifras)
e informar el resto sin tocarlo.

## 5. Commit y push del cierre (siempre)

En la rama de la fase, nunca en `main`:

1. `git status --short`: solo los documentos del cierre (y, en su propio commit anterior, las correcciones del paso 2). Si aparece
   otra cosa, detenerse e informar.
2. `git add` por nombre y el commit `Cierra la FASE N del <proyecto>: <tema> (R-NNN)`, con las líneas de atribución de la sesión.
3. `git push` y comprobar que `git rev-parse HEAD` sea igual a `git rev-parse @{u}`.

## 6. Merge a `main` (solo con autorización)

Preguntar al usuario si integra la fase a `main`. Solo con un sí explícito:

```bash
git switch main
git pull --ff-only
git merge --no-ff <rama de la fase> -m "Merge FASE N (<tema>) del <proyecto>"
git push origin main
```

Sin squash ni rebase. Con conflictos, detenerse e informar: no resolverlos por cuenta propia. Las ramas de la fase se conservan. El
hash del merge no se anota en el handoff en este paso: se anota al empezar lo siguiente ("el hash, en `git log origin/main`").

## 7. Informe

Lista simple, en español (voseo), sin tablas:

* La fase cerrada y su R.
* Backend: casos y aserciones en SQLite y en MySQL, y `composer audit`.
* Frontend: Vitest (tests y archivos), `typecheck`, `lint`, `build`, `npm audit` y Playwright (n de n).
* Newman: colecciones, pedidos y aserciones.
* Los desvíos y cómo se resolvieron, y las deudas nuevas.
* Los archivos del cierre y el resultado de `check-docs.php`.
* La línea final: "git status limpio, commit y push confirmados: `<hash>` en `origin/<rama>`." y, si se hizo el merge, "Merge
  `<hash>` en `origin/main`.".
