# Roadmap

Fuente de verdad del estado por fase y entrega. Las fases conservan su numeración y no se renumeran. El detalle por módulo está en
[`modules/README.md`](../modules/README.md). El plan con los prompts está en [`docs/prompts/README.md`](../prompts/README.md).

## Estados

| Estado | Significa |
|---|---|
| **COMPLETADO** | Código y rutas presentes, con tests propios que pasan en la suite |
| **EN DESARROLLO** | Parte implementada y parte pendiente |
| **PENDIENTE** | Sin código |
| **PENDIENTE DE VERIFICACIÓN** | No se pudo comprobar con certeza |

## Antes de las fases: paquete base

Esquema completo del MVP (27 migraciones, antes de la tabla `clinics` de la entrega 0.2), modelos, enums, excepciones, arquitectura base y el módulo de ejemplo de clientes y
pacientes, sin tests todavía. Registro: [`decisions/registro.md`](../decisions/registro.md), sección R-001.

## Fases del MVP

| Fase | Etapa | Contenido | Estado |
|---|---|---|---|
| [FASE 0](../fases/fase-00-puesta-en-marcha.md) | Base | Puesta en marcha | COMPLETADO (R-014): 0.1 (R-006), 0.2 (R-007), 0.3 (R-008), 0.4 (R-012) y 0.5 (R-013, R-014) |
| [FASE 1](../fases/fase-01-clientes-y-pacientes.md) | Base | Clientes y pacientes | COMPLETADO (R-028): 1.1 (R-017), 1.2 (R-018, R-019), 1.3 (R-020 a R-022), 1.4 (R-023 a R-025) y 1.5 (R-026 a R-028) |
| [FASE 2](../fases/fase-02-datos-maestros.md) | Base | Datos maestros | COMPLETADO (R-044): 2.1 (R-030, R-031), 2.2a (R-032, R-033), 2.2b (R-034, R-035), 2.3 (R-036, R-037), 2.4 (R-038, R-039), 2.5 (R-040, R-041) y 2.6 (R-042, R-043); diseño (R-029) |
| [FASE 3](../fases/fase-03-agenda-y-turnos.md) | Atención | Agenda y turnos | PENDIENTE: diseño aprobado (R-047, R-048) con las entregas 3.0 (deuda 16), 3.1, 3.2a, 3.2b, 3.3 y 3.4; la 3.0 (R-049), la 3.1 (agendas, R-050), la 3.2a (turnos únicos, R-051) y la 3.2b (estados del turno, R-053) cerradas; sigue la 3.3 (turnos recurrentes) |
| [FASE 4](../fases/fase-04-historia-clinica.md) | Atención | Historia clínica | PENDIENTE |
| [FASE 5](../fases/fase-05-inventario.md) | Atención | Inventario | PENDIENTE |
| [FASE 6](../fases/fase-06-documentos.md) | Comunicación | Documentos | PENDIENTE |
| [FASE 7](../fases/fase-07-recordatorios.md) | Comunicación | Recordatorios y avisos | PENDIENTE |
| [FASE 8](../fases/fase-08-caja-y-facturacion.md) | Cobro | Caja y facturación | PENDIENTE |
| [FASE 9](../fases/fase-09-sifen.md) | Cobro | SIFEN | PENDIENTE |
| [FASE 10](../fases/fase-10-reportes-y-piloto.md) | Lanzamiento | Reportes y piloto | PENDIENTE |

El orden sigue el flujo de la clínica: turno → consulta → lo que se usó → stock → factura → pago → recordatorio. Cada etapa empieza
cuando la anterior está cerrada. El frontend avanza en paralelo según la decisión D2.
