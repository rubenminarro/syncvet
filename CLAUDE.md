# CLAUDE.md — SyncVet

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
├── .claude/skills/  /retomar, /cerrar-entrega y /validar-docs, para todo el repositorio
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

### Ritmo de las entregas

* En la implementación, mutaciones solo de las protecciones críticas de la entrega (alrededor de 5); la revisión hace las suyas.
* En cada mutación se corren solo los tests relacionados; la suite completa, al final.
* Si una entrega junta temas distintos, proponer dividirla en el diseño.
* Lo propio de cada proyecto (bases de datos, velocidad de la suite, auditoría de dependencias) está en su `CLAUDE.md`.

### Cierre de fase

* En cada cierre de fase se corren con Newman **todas** las colecciones de `docs/05-pruebas/postman`, no solo las de la fase (R-057),
  desde la raíz y con el servidor levantado desde `backend/`. Una falla se corrige o se registra como deuda antes de cerrar.

### Documentación por cambio

Cada entrega actualiza, en la misma rama, lo que indica la tabla **Mantenimiento** de [`docs/README.md`](docs/README.md). La skill
`/cerrar-entrega` lo hace sobre una entrega ya validada; `/validar-docs` audita enlaces y cifras; `/retomar` informa el estado al
empezar una sesión.

Los desvíos, decisiones pendientes y notas para el cierre se escriben en el repositorio (sección "Pendiente para el cierre" de la
entrega en el documento de la fase) y se suben con el commit WIP, nunca solo en la memoria de Claude Code: el usuario trabaja en más
de una PC.

### Procesos

* Claude Code solo detiene los procesos que él mismo levantó (un servidor para Newman, Vite, Playwright…), por su PID o por la
  tarea en segundo plano que los lanzó. Nunca cierra procesos por nombre (`php.exe`, `nginx`, `node`…) ni los de Laragon o del
  usuario.

### Git

* Nunca trabajar ni commitear en `main`. Una rama por tarea: `feature/…`, `fix/…`, `docs/…`, `test/…`, `chore/…`.
* Commit y push solo cuando el usuario lo pide. Mensajes en español, describiendo el porqué.
* Un movimiento de archivos va en un commit propio, sin cambios de contenido, para que Git conserve la historia; los merges a `main`,
  con `--no-ff` y sin squash.

---

## 3. Estado

* **Backend:** FASE 0 a FASE 3 completadas e integradas a `main`; el detalle, en [`backend/CLAUDE.md`](backend/CLAUDE.md) §1.
* **Frontend:** F0 completada (R-063) e integrada a `main`, con la entrega de soporte del backend (front-00). F1 (clientes y
  pacientes) en curso: diseño aprobado (R-064), la entrega de soporte del backend front-01 (R-065) y la 1.1 (clientes: listado y
  detalle, R-066) cerradas; el detalle, en [`frontend/CLAUDE.md`](frontend/CLAUDE.md) §1.
* El estado por fase está en [`docs/03-plan-desarrollo/README.md`](docs/03-plan-desarrollo/README.md).
