# Documentación de SyncVet

Punto de entrada de la documentación de todo el proyecto (backend y frontend). **Para retomar el desarrollo, empezar por
[`handoff.md`](03-plan-desarrollo/handoff.md).**

## Dónde está cada cosa

| Quiero saber… | Documento |
|---|---|
| Dónde está el proyecto y cuál es el siguiente paso | [`03-plan-desarrollo/handoff.md`](03-plan-desarrollo/handoff.md) |
| Qué es el proyecto | [`00-vision/overview.md`](00-vision/overview.md) |
| Qué principios guían el diseño | [`00-vision/principios.md`](00-vision/principios.md) |
| Cómo está construido | [`01-arquitectura/architecture.md`](01-arquitectura/architecture.md) |
| Qué tecnologías y versiones usa | [`01-arquitectura/technology-stack.md`](01-arquitectura/technology-stack.md) |
| Qué se decidió y por qué | [`01-arquitectura/decisiones/README.md`](01-arquitectura/decisiones/README.md) |
| Qué reglas de negocio rigen | [`02-requisitos/README.md`](02-requisitos/README.md) |
| Qué módulos están terminados y cuáles no | [`03-plan-desarrollo/modulos.md`](03-plan-desarrollo/modulos.md) |
| Qué se hizo y qué falta, por fase | [`03-plan-desarrollo/README.md`](03-plan-desarrollo/README.md) |
| Qué se va a hacer en cada fase del backend | [`03-plan-desarrollo/fases/backend/`](03-plan-desarrollo/fases/backend/) |
| Qué se va a hacer en cada fase del frontend | [`03-plan-desarrollo/fases/frontend/`](03-plan-desarrollo/fases/frontend/README.md) |
| Qué prompt usar en cada paso | [`03-plan-desarrollo/prompts/README.md`](03-plan-desarrollo/prompts/README.md) |
| Qué deudas y discrepancias siguen abiertas | [`03-plan-desarrollo/deuda-tecnica.md`](03-plan-desarrollo/deuda-tecnica.md) |
| Cómo instalar en una computadora nueva | [`04-desarrollo/setup.md`](04-desarrollo/setup.md) |
| Cómo correr los tests | [`04-desarrollo/testing.md`](04-desarrollo/testing.md) |
| Qué se sabe del despliegue | [`04-desarrollo/deployment.md`](04-desarrollo/deployment.md) |
| Cómo probar la API a mano | [`05-pruebas/guia-postman.md`](05-pruebas/guia-postman.md) |

## Fuente de verdad de cada tema

Cada dato vive en un solo lugar. Los demás documentos enlazan, no copian.

| Tema | Fuente de verdad |
|---|---|
| Comportamiento real | El código y los tests. Si un documento lo contradice, manda el código: registrar la discrepancia en [`03-plan-desarrollo/deuda-tecnica.md`](03-plan-desarrollo/deuda-tecnica.md) |
| Proceso y reglas comunes (metodología, voseo, Git, documentación por cambio) | [`CLAUDE.md`](../CLAUDE.md) de la raíz |
| Reglas operativas, convenciones y reglas de negocio vigentes del backend | [`backend/CLAUDE.md`](../backend/CLAUDE.md) |
| Reglas del frontend | [`frontend/CLAUDE.md`](../frontend/CLAUDE.md) |
| Instalación detallada del backend, variables de entorno y datos de prueba | [`backend/README.md`](../backend/README.md) |
| Estado de cada módulo | [`03-plan-desarrollo/modulos.md`](03-plan-desarrollo/modulos.md) |
| Estado de cada fase y entrega | [`03-plan-desarrollo/README.md`](03-plan-desarrollo/README.md) |
| Diseño de cada fase | [`03-plan-desarrollo/fases/`](03-plan-desarrollo/fases/backend/) |
| Punto de continuidad | [`03-plan-desarrollo/handoff.md`](03-plan-desarrollo/handoff.md) |
| Deuda técnica | [`03-plan-desarrollo/deuda-tecnica.md`](03-plan-desarrollo/deuda-tecnica.md) |
| Decisiones, con su motivo | [`01-arquitectura/decisiones/registro.md`](01-arquitectura/decisiones/registro.md) |
| Contrato manual de la API | [`05-pruebas/guia-postman.md`](05-pruebas/guia-postman.md) |

## Estructura

```text
docs/
├── README.md               Este índice
├── 00-vision/              Qué es el producto, sus principios y la competencia (referencias/)
├── 01-arquitectura/        Cómo está construido, tecnologías y decisiones (decisiones/)
├── 02-requisitos/          Dónde están las reglas de negocio
├── 03-plan-desarrollo/     Roadmap, handoff, estado por módulo, deuda técnica, fases (backend/ y frontend/) y prompts
├── 04-desarrollo/          Instalación, tests y despliegue
└── 05-pruebas/             Guía de pruebas manuales con Postman y sus colecciones (postman/)
```

Las rutas entre comillas invertidas siguen la convención del [`CLAUDE.md`](../CLAUDE.md) de la raíz: `docs/…` desde la raíz del
repositorio; `app/`, `tests/`, `config/`, `routes/`, `database/`, `lang/` y `bootstrap/`, desde `backend/`.

## Mantenimiento

| Al… | Actualizar |
|---|---|
| Terminar una entrega | [`handoff.md`](03-plan-desarrollo/handoff.md) (punto de continuidad), [`modulos.md`](03-plan-desarrollo/modulos.md), el [roadmap](03-plan-desarrollo/README.md) y la sección Resultado de su fase |
| Tomar una decisión | Una sección nueva `R-NNN` en [`registro.md`](01-arquitectura/decisiones/registro.md) |
| Responder una pregunta de negocio | La tabla de preguntas de su fase, y el registro de decisiones |
| Detectar una deuda o discrepancia | [`deuda-tecnica.md`](03-plan-desarrollo/deuda-tecnica.md) y `backend/CLAUDE.md`, sección 7 |
| Cambiar la API | [`guia-postman.md`](05-pruebas/guia-postman.md) |
| Cambiar una regla de negocio | `backend/CLAUDE.md`, sección 6 |
| Cambiar el proceso o una regla común | El [`CLAUDE.md`](../CLAUDE.md) de la raíz |

`01-arquitectura/decisiones/registro.md` solo crece: no se corrigen secciones viejas para reflejar el estado actual (sí sus enlaces,
que tienen que funcionar).
