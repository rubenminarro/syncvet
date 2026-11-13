# Fases del frontend

El plan del frontend, con el mismo ciclo que las [fases del backend](../backend/): primero el diseño con sus preguntas, después las
entregas. Las decisiones del análisis (Node 26, la sesión, los tipos, las rutas, el proxy, Playwright y el orden) están en R-058.

| Fase | Contenido | Estado |
|---|---|---|
| [F0](f0-base.md) | Base: esqueleto (0.1) y sesión (0.2a, sesión; 0.2b, permisos). Antes de la 0.2, la [entrega de soporte del backend](../backend/front-00-soporte-del-frontend.md) (B1 a B3) | COMPLETADO (R-063): la 0.1, la 0.2a y la 0.2b (R-060 a R-062); integrada a `main` ([cierre](f0-base.md#cierre-de-la-f0)) |
| [F1](f1-clientes-y-pacientes.md) | Clientes y pacientes, sobre la API de la FASE 1 del backend | PROPUESTO: diseño en `docs/front-f1-diseno`, a la espera de las preguntas del diseño |
| F2 | Datos maestros, sobre la API de la FASE 2 | PENDIENTE |
| F3 | Agenda y turnos, sobre la API de la FASE 3 | PENDIENTE |

El orden es F0 → F1 → F2 → F3 y después la FASE 4 del backend (R-058).
