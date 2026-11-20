# Roadmap

Fuente de verdad del estado por fase y entrega. Las fases conservan su numeración y no se renumeran. El detalle por módulo está en
[`modulos.md`](modulos.md). El plan con los prompts está en [`docs/03-plan-desarrollo/prompts/README.md`](prompts/README.md).

## Estados

| Estado | Significa |
|---|---|
| **COMPLETADO** | Código y rutas presentes, con tests propios que pasan en la suite |
| **EN DESARROLLO** | Parte implementada y parte pendiente |
| **PENDIENTE** | Sin código |
| **PENDIENTE DE VERIFICACIÓN** | No se pudo comprobar con certeza |

## Antes de las fases: paquete base

Esquema completo del MVP (27 migraciones, antes de la tabla `clinics` de la entrega 0.2), modelos, enums, excepciones, arquitectura base y el módulo de ejemplo de clientes y
pacientes, sin tests todavía. Registro: [`decisions/registro.md`](../01-arquitectura/decisiones/registro.md), sección R-001.

## Fases del MVP

| Fase | Etapa | Contenido | Estado |
|---|---|---|---|
| [FASE 0](fases/backend/fase-00-puesta-en-marcha.md) | Base | Puesta en marcha | COMPLETADO (R-014): 0.1 (R-006), 0.2 (R-007), 0.3 (R-008), 0.4 (R-012) y 0.5 (R-013, R-014) |
| [FASE 1](fases/backend/fase-01-clientes-y-pacientes.md) | Base | Clientes y pacientes | COMPLETADO (R-028): 1.1 (R-017), 1.2 (R-018, R-019), 1.3 (R-020 a R-022), 1.4 (R-023 a R-025) y 1.5 (R-026 a R-028) |
| [FASE 2](fases/backend/fase-02-datos-maestros.md) | Base | Datos maestros | COMPLETADO (R-044): 2.1 (R-030, R-031), 2.2a (R-032, R-033), 2.2b (R-034, R-035), 2.3 (R-036, R-037), 2.4 (R-038, R-039), 2.5 (R-040, R-041) y 2.6 (R-042, R-043); diseño (R-029) |
| [FASE 3](fases/backend/fase-03-agenda-y-turnos.md) | Atención | Agenda y turnos | COMPLETADO (R-056): 3.0 (deuda 16, R-049), 3.1 (R-050), 3.2a (R-051), 3.2b (R-053), 3.3 (R-054) y 3.4 (R-055); diseño (R-047, R-048) |
| [FASE 4](fases/backend/fase-04-historia-clinica.md) | Atención | Historia clínica | PENDIENTE |
| [FASE 5](fases/backend/fase-05-inventario.md) | Atención | Inventario | PENDIENTE |
| [FASE 6](fases/backend/fase-06-documentos.md) | Comunicación | Documentos | PENDIENTE |
| [FASE 7](fases/backend/fase-07-recordatorios.md) | Comunicación | Recordatorios y avisos | PENDIENTE |
| [FASE 8](fases/backend/fase-08-caja-y-facturacion.md) | Cobro | Caja y facturación | PENDIENTE |
| [FASE 9](fases/backend/fase-09-sifen.md) | Cobro | SIFEN | PENDIENTE |
| [FASE 10](fases/backend/fase-10-reportes-y-piloto.md) | Lanzamiento | Reportes y piloto | PENDIENTE |

El orden sigue el flujo de la clínica: turno → consulta → lo que se usó → stock → factura → pago → recordatorio. Cada etapa empieza
cuando la anterior está cerrada. El frontend recorre F0 a F3 y después sigue la FASE 4 (D2, R-058).

## Fases del frontend

El plan está en [`fases/frontend/`](fases/frontend/README.md), con las decisiones del análisis en R-058. Orden: F0 → F1 → F2 → F3 y después la FASE 4 del backend.

| Fase | Contenido | Estado |
|---|---|---|
| [F0](fases/frontend/f0-base.md) | Base: esqueleto (0.1) y sesión (0.2a y 0.2b) | COMPLETADO (R-063): 0.1 (R-060), 0.2a (R-061) y 0.2b (R-062); diseño (R-058). Integrada a `main` con un merge `--no-ff` desde `feature/front-0-2b-permisos` ([cierre](fases/frontend/f0-resultados.md#cierre-de-la-f0)) |
| [Soporte del backend](fases/backend/front-00-soporte-del-frontend.md) | B1 a B3: sucursales en `me`, CORS y paginación en `snake_case`; antes de la F0.2 | COMPLETADO (R-059); diseño (R-058). Integrada a `main` con la F0 (R-063) |
| [F1](fases/frontend/f1-clientes-y-pacientes.md) | Clientes y pacientes, sobre la API de la FASE 1, con la entrega de soporte del backend front-01 (el dueño en `GET patients`) | COMPLETADO (R-072): front-01 (R-065), 1.1 (R-066), 1.2 (R-067), 1.3a (R-068), 1.3b (R-069), 1.4 (R-070) y 1.5 (R-071); diseño (R-064). Integrada a `main` con un merge `--no-ff` desde `feature/front-1-5-especies` ([cierre](fases/frontend/f1-resultados.md#cierre-de-la-f1)) |
| [F2](fases/frontend/f2-datos-maestros.md) | Datos maestros, sobre la API de la FASE 2, con la entrega de soporte del backend front-02 (la ficha de veterinario en `GET users`) | COMPLETADO (R-083): 2.1 (R-075), 2.2a (R-076), 2.2b (R-077), front-02 (R-078), 2.3 (R-079), 2.4 (R-080), 2.5 (R-081) y 2.6 (R-082); diseño (R-074). Integrada a `main` con un merge `--no-ff` desde `feature/front-2-6-auditoria` ([cierre](fases/frontend/f2-resultados.md#cierre-de-la-f2)) |
| F3 | Agenda y turnos, sobre la API de la FASE 3 | EN CURSO: diseño aprobado por el usuario el 2026-11-17 (R-085) en [`f3-agenda.md`](fases/frontend/f3-agenda.md), en `docs/front-f3-diseno`, con front-03 y las entregas 3.1a a 3.7; la regla de `e2e/permisos.spec.ts` (R-084). 3.1a (agendas) COMPLETADO (R-086), 3.1b (bloqueos) COMPLETADO (R-087) y front-03 (soporte del backend: `per_page` hasta 500 en `GET appointments`) COMPLETADO (R-088) , 3.2 (la agenda del día) COMPLETADO (R-089), demo-02 (soporte del backend: `php artisan demo:agenda`, agendas y turnos ficticios en la demo) COMPLETADO (R-090), 3.3 (la semana y la lista de turnos) COMPLETADO (R-092) , 3.4 (el turno: detalle, alta, edición y reprogramación) COMPLETADO (R-093), 3.5 (los estados del turno) COMPLETADO (R-094), front-04 (soporte del backend: con `series_id` y sin fechas, la serie entera en `GET appointments`) COMPLETADO (R-095), 3.6 (las series) COMPLETADO (R-096) y 3.7 (la cola del día) COMPLETADO (R-097). Sigue el cierre de la F3 |
