# Fases del frontend

El plan del frontend, con el mismo ciclo que las [fases del backend](../backend/): primero el diseño con sus preguntas, después las
entregas. Las decisiones del análisis (Node 26, la sesión, los tipos, las rutas, el proxy, Playwright y el orden) están en R-058.

| Fase | Contenido | Estado |
|---|---|---|
| [F0](f0-base.md) | Base: esqueleto (0.1) y sesión (0.2a, sesión; 0.2b, permisos). Antes de la 0.2, la [entrega de soporte del backend](../backend/front-00-soporte-del-frontend.md) (B1 a B3) | COMPLETADO (R-063): la 0.1, la 0.2a y la 0.2b (R-060 a R-062); integrada a `main` ([cierre](f0-base.md#cierre-de-la-f0)) |
| [F1](f1-clientes-y-pacientes.md) | Clientes y pacientes, sobre la API de la FASE 1 del backend, con la entrega de soporte del backend front-01 | COMPLETADO (R-072): front-01 (R-065) y las entregas 1.1 a 1.5 (R-066 a R-071); integrada a `main` ([cierre](f1-clientes-y-pacientes.md#cierre-de-la-f1)) |
| [F2](f2-datos-maestros.md) | Datos maestros, sobre la API de la FASE 2, con la entrega de soporte del backend front-02 antes de la 2.3 | EN CURSO: diseño aprobado (R-074), con las entregas 2.1, 2.2a, 2.2b, front-02, 2.3, 2.4, 2.5 y 2.6 ([preguntas del diseño](f2-datos-maestros.md#preguntas-del-diseño)); 2.1 COMPLETADO (R-075, [resultado](f2-datos-maestros.md#entrega-21)); 2.2a COMPLETADO (R-076, [resultado](f2-datos-maestros.md#entrega-22a)); 2.2b COMPLETADO (R-077, [resultado](f2-datos-maestros.md#entrega-22b)); front-02 COMPLETADO (R-078, [resultado](f2-datos-maestros.md#entrega-front-02)); 2.3 COMPLETADO (R-079, [resultado](f2-datos-maestros.md#entrega-23)); 2.4 COMPLETADO (R-080, [resultado](f2-datos-maestros.md#entrega-24)); sigue la 2.5 |
| F3 | Agenda y turnos, sobre la API de la FASE 3 | PENDIENTE |

El orden es F0 → F1 → F2 → F3 y después la FASE 4 del backend (R-058).
