# Documentación de SyncVet

Punto de entrada de la documentación del backend. **Para retomar el desarrollo, empezar por [`handoff.md`](handoff.md).**

## Dónde está cada cosa

| Quiero saber… | Documento |
|---|---|
| Dónde está el proyecto y cuál es el siguiente paso | [`handoff.md`](handoff.md) |
| Qué es el proyecto | [`project/overview.md`](project/overview.md) |
| Cómo está construido | [`project/architecture.md`](project/architecture.md) |
| Qué tecnologías y versiones usa | [`project/technology-stack.md`](project/technology-stack.md) |
| Qué principios guían el diseño | [`project/principios.md`](project/principios.md) |
| Qué módulos están terminados y cuáles no | [`modules/README.md`](modules/README.md) |
| Qué se hizo y qué falta, por fase | [`roadmap/README.md`](roadmap/README.md) |
| Qué se va a hacer en cada fase | [`fases/`](fases/) |
| Qué prompt usar en cada paso | [`prompts/README.md`](prompts/README.md) |
| Qué deudas y discrepancias siguen abiertas | [`roadmap/deuda-tecnica.md`](roadmap/deuda-tecnica.md) |
| Qué se decidió y por qué | [`decisions/README.md`](decisions/README.md) |
| Cómo instalar en una computadora nueva | [`development/setup.md`](development/setup.md) |
| Cómo correr los tests | [`development/testing.md`](development/testing.md) |
| Qué se sabe del despliegue | [`development/deployment.md`](development/deployment.md) |
| Cómo probar la API a mano | [`testing/guia-postman.md`](testing/guia-postman.md) |

## Fuente de verdad de cada tema

Cada dato vive en un solo lugar. Los demás documentos enlazan, no copian.

| Tema | Fuente de verdad |
|---|---|
| Comportamiento real | El código y los tests. Si un documento lo contradice, manda el código: registrar la discrepancia en [`roadmap/deuda-tecnica.md`](roadmap/deuda-tecnica.md) |
| Reglas operativas, convenciones y reglas de negocio vigentes | [`CLAUDE.md`](../CLAUDE.md) |
| Instalación detallada, variables de entorno y datos de prueba | [`README.md`](../README.md) de la raíz |
| Estado de cada módulo | [`modules/README.md`](modules/README.md) |
| Estado de cada fase y entrega | [`roadmap/README.md`](roadmap/README.md) |
| Diseño de cada fase | [`fases/`](fases/) |
| Punto de continuidad | [`handoff.md`](handoff.md) |
| Deuda técnica | [`roadmap/deuda-tecnica.md`](roadmap/deuda-tecnica.md) |
| Decisiones, con su motivo | [`decisions/registro.md`](decisions/registro.md) |
| Contrato manual de la API | [`testing/guia-postman.md`](testing/guia-postman.md) |

## Estructura

```text
docs/
├── README.md            Este índice
├── handoff.md           Documento principal para retomar
├── project/             Qué es y cómo está construido
├── modules/             Estado por módulo
├── roadmap/             Estado por fase y deuda técnica
├── fases/               Diseño y resultado de cada fase
├── prompts/             Prompts de cada paso para Claude Code
├── decisions/           Índice y registro de decisiones
├── development/         Instalación, tests y despliegue
└── testing/             Guía de pruebas manuales con Postman
```

## Mantenimiento

| Al… | Actualizar |
|---|---|
| Terminar una entrega | [`handoff.md`](handoff.md) (punto de continuidad), [`modules/README.md`](modules/README.md), [`roadmap/README.md`](roadmap/README.md) y la sección Resultado de su fase |
| Tomar una decisión | Una sección nueva `N.M` en [`decisions/registro.md`](decisions/registro.md) |
| Responder una pregunta de negocio | La tabla de preguntas de su fase, y el registro de decisiones |
| Detectar una deuda o discrepancia | [`roadmap/deuda-tecnica.md`](roadmap/deuda-tecnica.md) y `CLAUDE.md`, sección 7 |
| Cambiar la API | [`testing/guia-postman.md`](testing/guia-postman.md) |
| Cambiar una regla de negocio | `CLAUDE.md`, sección 6 |

`decisions/registro.md` solo crece: no se corrigen secciones viejas para reflejar el estado actual.
