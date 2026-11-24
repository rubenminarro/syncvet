# CLAUDE.md — SyncVet

> **Nunca `cat > archivo` ni `cat >> archivo` sin entrada.** Sin un heredoc (`<<`) o una tubería, `cat` espera el teclado para
> siempre y el resto del comando no corre (pasó en la 1.1 y en la 1.3b, con tareas colgadas durante horas). Los archivos se
> escriben con el editor (Edit/Write) o, si son temporales, en el scratchpad. Lo hace cumplir el hook PreToolUse de
> `.claude/settings.json` (`.claude/hooks/block-cat-stdin.js`), que rechaza el comando.

Instrucciones permanentes comunes a todo el repositorio (monorepo: backend y frontend). Las reglas de cada proyecto están en su
propio `CLAUDE.md`.

| Documento | Para qué |
|---|---|
| `CLAUDE.md` (este) | Proceso y reglas comunes |
| [`backend/CLAUDE.md`](backend/CLAUDE.md) | Estado, convenciones, arquitectura y reglas de negocio del backend (Laravel) |
| [`frontend/CLAUDE.md`](frontend/CLAUDE.md) | Estado, comandos, stack, convenciones y tabla de `error_code` del frontend (React) |
| [`docs/README.md`](docs/README.md) | Índice, fuente de verdad de cada tema y qué actualizar en cada cambio |
| [`docs/03-plan-desarrollo/handoff.md`](docs/03-plan-desarrollo/handoff.md) | Punto de continuidad y próximas tareas |
| [`docs/03-plan-desarrollo/README.md`](docs/03-plan-desarrollo/README.md) | Estado por fase; el diseño de cada fase está en `docs/03-plan-desarrollo/fases/` |
| [`docs/03-plan-desarrollo/prompts/README.md`](docs/03-plan-desarrollo/prompts/README.md) | Los pasos de cada fase y su prompt |
| [`docs/03-plan-desarrollo/deuda-tecnica.md`](docs/03-plan-desarrollo/deuda-tecnica.md) | Deuda técnica y discrepancias |
| [`docs/05-pruebas/guia-postman.md`](docs/05-pruebas/guia-postman.md) | Pruebas manuales de la API, por fase |

Si una regla contradice al código, **el código manda**: registrar la discrepancia y avisar, en lugar de "corregir" por cuenta propia.

---

## 1. Estructura y cómo trabajar en ella

```text
syncvet/
├── CLAUDE.md        Este archivo: proceso y reglas comunes
├── .claude/skills/  /retomar, /cambiar-pc, /cerrar-entrega y /validar-docs, para todo el repositorio
├── .claude/hooks/   El hook que rechaza `cat >` sin entrada (configurado en .claude/settings.json)
├── backend/         API Laravel, con su CLAUDE.md y su README.md (instalación)
├── frontend/        React 19 + TypeScript + Vite + Tabler, con su CLAUDE.md y su README.md (instalación)
└── docs/            Documentación de todo el proyecto (índice en docs/README.md)
```

* **Claude Code se lanza siempre desde la raíz del repositorio** (las skills y la memoria dependen de esa carpeta).
* **Antes de tocar, diseñar o revisar algo del backend, leer [`backend/CLAUDE.md`](backend/CLAUDE.md)**; del frontend,
  [`frontend/CLAUDE.md`](frontend/CLAUDE.md). Claude Code carga solo el `CLAUDE.md` de una subcarpeta al leer archivos de ella: en una
  tarea de diseño o de documentación hay que leerlo a mano.
* Los comandos de cada proyecto se corren desde su carpeta (`cd backend && composer test`). Los de la documentación y las skills, desde
  la raíz.
* **Rutas en la documentación:** entre comillas invertidas, `docs/…` se lee desde la raíz; `app/`, `tests/`, `config/`, `routes/`,
  `database/`, `lang/` y `bootstrap/`, desde `backend/`; lo del frontend lleva el prefijo `frontend/`. Los enlaces de Markdown son
  siempre relativos al archivo que los contiene.

---

## 2. Metodología de trabajo

1. Inspeccionar el código existente relacionado.
2. Identificar las reglas de negocio y los módulos afectados.
3. Proponer el cambio mínimo y explicar su motivo. Las decisiones de negocio se consultan, no se suponen.
4. Implementar solo el alcance autorizado.
5. Ejecutar los tests pertinentes y la suite completa antes de dar algo por terminado.
6. Verificar que no cambien contratos, permisos, `error_code` ni códigos HTTP existentes.
7. Informar archivos modificados, tests ejecutados y resultados.

Los informes, resúmenes y mensajes al usuario van siempre en español (voseo), aunque el código y los comentarios técnicos estén en
inglés.

En fases nuevas: primero diseño funcional y de datos (con las preguntas de negocio, en `docs/03-plan-desarrollo/fases/`), después
código. Ninguna entrega empieza sin autorización del usuario y ninguna fase se declara cerrada sin evidencia (código + tests).

### Presupuesto por entrega

**Manda sobre las demás reglas de velocidad y de ritmo** (las de este archivo, `backend/CLAUDE.md` y `frontend/CLAUDE.md`): si
alguna pide más corridas, vale esta.

R-100 (2026-11-20, al cerrar la 4.1 del backend):

* **En la implementación, solo los tests relacionados**: `npx vitest related <archivos> --run` en el frontend;
  `php artisan test --filter <test>` (sin Xdebug) en el backend. Las entregas del frontend corren además, desde `backend/`,
  `php artisan test --filter=FictitiousDocumentsTest`: revisa `frontend/src` y `frontend/e2e` (ningún número de 7 a 9 dígitos con
  forma de cédula o RUC real).
* **Una sola corrida completa por entrega, en la revisión**, del lado que cambió: en el frontend, `typecheck`, `lint`, `test` y
  `build`; en el backend, `composer test`. **Tiene que dar todo verde en una sola corrida:** si algo falla, se corrige y se repite la
  corrida completa, para que la cifra registrada sea siempre la de una corrida real en verde.
* **La salida de los tests, siempre a un archivo**; se leen solo el resumen y las fallas.
* **MySQL** (solo si la entrega toca migraciones, concurrencia o búsqueda de texto, y solo los tests afectados), **Newman** (solo la
  colección del módulo tocado) y **mutaciones**: una vez, en la implementación. La revisión no los repite, salvo que toque lo que
  cubren (la migración, la concurrencia, lo que usa la colección o la regla mutada). La suite completa en MySQL y en serie, Newman
  con todas las colecciones y Playwright completo, al cerrar la fase ([Cierre de fase](#cierre-de-fase)).
* **Mutaciones**: dos por entrega, sobre las reglas críticas, revertidas con el editor ([Ritmo de las entregas](#ritmo-de-las-entregas)).
* **Tests compactos**: *data providers* para los límites y las validaciones; no se repite lo que ya cubren `RoleAccessTest` (el acceso
  por rol a cada ruta) y el aislamiento por clínica (`RouteBindingTest` y los tests de `tests/Feature/Tenancy`).
* **`/validar-docs` y las cifras de los documentos** (tests, rutas, permisos, migraciones), solo en `/cerrar-entrega`.
* **Informes** al usuario, en listas simples, sin tablas.
* **Playwright**: solo los casos nuevos o tocados de la entrega; la corrida completa, al cerrar la fase. **Toda entrega que
  cambie el menú o el permiso de una ruta** (`src/app/menu.ts`, un `requirePermission` o un permiso de `PERMISSIONS`) corre
  también `e2e/permisos.spec.ts`, además de su propio spec (R-084: en la F2, la 2.3 y la 2.4 sumaron ítems al menú y ese spec
  recién falló en el cierre de la fase).
* **Navegador** (Edge sin ventana): como máximo 10 controles, y capturas solo si cambió una pantalla. Si un caso de Playwright ya
  cubre un flujo, los controles en Edge son solo para lo que Playwright no ve (capturas o detalles visuales). El script de los
  controles ingresa **una sola vez por rol**, **revoca siempre sus tokens al terminar** (en un `finally`, también si falla) y, si
  falla por un error del propio script, se corrige y se repite **solo el control que falló**, no la corrida entera (en la 3.2, seis
  corridas dejaron 19 ingresos y 7 tokens sin revocar, desvío 15).
* **Revisión**: lee el diff, corrige y hace la corrida completa de la entrega; no repite Playwright ni el navegador.
* **Esperas**: mientras corre algo largo, no se consulta en bucle; se espera el aviso de fin.

### Ritmo de las entregas

* En la implementación, dos mutaciones de las protecciones críticas de la entrega (el presupuesto); la revisión no las repite.
* En cada mutación se corren solo los tests relacionados; la suite completa, una vez, en la revisión.
* **Las mutaciones se revierten con el editor** (Edit/Write), nunca con `git checkout`, `git restore` ni `git stash` sobre archivos
  con cambios propios sin commitear: devuelven el archivo al último commit y se llevan el trabajo de la entrega (front-04, desvío 1;
  el detalle, en `docs/04-desarrollo/testing.md`).
* Si una entrega junta temas distintos, proponer dividirla en el diseño.
* Lo propio de cada proyecto (bases de datos, velocidad de la suite, auditoría de dependencias) está en su `CLAUDE.md`.

### Cierre de fase

* En cada cierre de fase se corren con Newman **todas** las colecciones de `docs/05-pruebas/postman`, no solo las de la fase (R-057),
  desde la raíz y con el servidor levantado desde `backend/`. Una falla se corrige o se registra como deuda antes de cerrar.
* También al cerrar la fase (y no en cada entrega; ver [Presupuesto por entrega](#presupuesto-por-entrega)): la suite del backend
  completa en MySQL y en serie, y Playwright completo.

### Documentación por cambio

Cada entrega actualiza, en la misma rama, lo que indica la tabla **Mantenimiento** de [`docs/README.md`](docs/README.md). La skill
`/cerrar-entrega` lo hace sobre una entrega ya validada y termina siempre con el commit y el push del cierre ("git status limpio,
commit y push confirmados"); `/validar-docs` audita enlaces y cifras; `/retomar` informa el estado al
empezar una sesión; `/cambiar-pc` pone al día la PC al llegar de otra (diagnóstico del entorno y, con confirmación, `pull`,
dependencias y migraciones).

Los desvíos, decisiones pendientes y notas para el cierre se escriben en el repositorio (sección "Pendiente para el cierre" de la
entrega en el documento de la fase) y se suben con el commit WIP, nunca solo en la memoria de Claude Code: el usuario trabaja en más
de una PC.

### Procesos

* Claude Code solo detiene los procesos que él mismo levantó (un servidor para Newman, Vite, Playwright…), por su PID o por la
  tarea en segundo plano que los lanzó. Nunca cierra procesos por nombre (`php.exe`, `nginx`, `node`…) ni los de Laragon o del
  usuario.

### Git

* Nunca trabajar ni commitear en `main`. Una rama por tarea: `feature/…`, `fix/…`, `docs/…`, `test/…`, `chore/…`.
* Commit y push solo cuando el usuario lo pide (invocar `/cerrar-entrega` es ese pedido para el cierre). Mensajes en español,
  describiendo el porqué.
* Un movimiento de archivos va en un commit propio, sin cambios de contenido, para que Git conserve la historia; los merges a `main`,
  con `--no-ff` y sin squash.

---

## 3. Estado

* **Backend:** FASE 0 a FASE 4 completadas e integradas a `main`; la FASE 4 (historia clínica), con sus seis entregas (R-100 a
  R-105), cerrada en R-106. El detalle, en [`backend/CLAUDE.md`](backend/CLAUDE.md) §1.
* **Frontend:** F0 a F3 completadas e integradas a `main` (R-063, R-072, R-083 y R-098; la F3, con el merge `5f180f3`). Sigue
  decidir con el usuario entre el frontend de la historia clínica y la FASE 5 del backend. El detalle, en
  [`frontend/CLAUDE.md`](frontend/CLAUDE.md) §1 y en el [handoff](docs/03-plan-desarrollo/handoff.md).
* El estado por fase está en [`docs/03-plan-desarrollo/README.md`](docs/03-plan-desarrollo/README.md).
