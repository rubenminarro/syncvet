# Fases del frontend

El plan del frontend, con el mismo ciclo que las [fases del backend](../backend/): primero el diseño con sus preguntas, después las
entregas. Las decisiones del análisis (Node 26, la sesión, los tipos, las rutas, el proxy, Playwright y el orden) están en R-058.

| Fase | Contenido | Estado |
|---|---|---|
| [F0](f0-base.md) | Base: esqueleto (0.1) y sesión (0.2a, sesión; 0.2b, permisos). Antes de la 0.2, la [entrega de soporte del backend](../backend/front-00-soporte-del-frontend.md) (B1 a B3) | COMPLETADO (R-063): la 0.1, la 0.2a y la 0.2b (R-060 a R-062); integrada a `main` ([cierre](f0-base.md#cierre-de-la-f0)) |
| [F1](f1-clientes-y-pacientes.md) | Clientes y pacientes, sobre la API de la FASE 1 del backend, con la entrega de soporte del backend front-01 | COMPLETADO (R-072): front-01 (R-065) y las entregas 1.1 a 1.5 (R-066 a R-071); integrada a `main` ([cierre](f1-clientes-y-pacientes.md#cierre-de-la-f1)) |
| [F2](f2-datos-maestros.md) | Datos maestros, sobre la API de la FASE 2, con la entrega de soporte del backend front-02 antes de la 2.3 | COMPLETADO (R-083): front-02 (R-078) y las entregas 2.1, 2.2a, 2.2b y 2.3 a 2.6 (R-075 a R-077 y R-079 a R-082); integrada a `main` ([cierre](f2-datos-maestros.md#cierre-de-la-f2)) |
| [F3](f3-agenda.md) | Agenda y turnos, sobre la API de la FASE 3: las agendas (3.1a y 3.1b), la agenda del día (3.2), la semana y la lista (3.3), el turno (3.4), sus estados (3.5), las series (3.6) y la cola del día (3.7); y la entrega de soporte del backend front-03 | EN CURSO: diseño aprobado (R-085), con las [preguntas](f3-agenda.md#preguntas-del-diseño) respondidas; 3.1a COMPLETADO (R-086, [resultado](f3-agenda.md#entrega-31a)); sigue la 3.1b (bloqueos) |

El orden es F0 → F1 → F2 → F3 y después la FASE 4 del backend (R-058).
