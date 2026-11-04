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

Esquema completo del MVP (27 migraciones), modelos, enums, excepciones, arquitectura base y el módulo de ejemplo de clientes y
pacientes, sin tests todavía. Registro: [`decisions/registro.md`](../decisions/registro.md), sección 0.1.

## Fases del MVP

| Fase | Etapa | Contenido | Estado |
|---|---|---|---|
| [FASE 0](../fases/fase-00-puesta-en-marcha.md) | Base | Puesta en marcha | PENDIENTE |
| [FASE 1](../fases/fase-01-clientes-y-pacientes.md) | Base | Clientes y pacientes | PENDIENTE |
| [FASE 2](../fases/fase-02-datos-maestros.md) | Base | Datos maestros | PENDIENTE |
| [FASE 3](../fases/fase-03-agenda-y-turnos.md) | Atención | Agenda y turnos | PENDIENTE |
| [FASE 4](../fases/fase-04-historia-clinica.md) | Atención | Historia clínica | PENDIENTE |
| [FASE 5](../fases/fase-05-inventario.md) | Atención | Inventario | PENDIENTE |
| [FASE 6](../fases/fase-06-documentos.md) | Comunicación | Documentos | PENDIENTE |
| [FASE 7](../fases/fase-07-recordatorios.md) | Comunicación | Recordatorios y avisos | PENDIENTE |
| [FASE 8](../fases/fase-08-caja-y-facturacion.md) | Cobro | Caja y facturación | PENDIENTE |
| [FASE 9](../fases/fase-09-sifen.md) | Cobro | SIFEN | PENDIENTE |
| [FASE 10](../fases/fase-10-reportes-y-piloto.md) | Lanzamiento | Reportes y piloto | PENDIENTE |

El orden sigue el flujo de la clínica: turno → consulta → lo que se usó → stock → factura → pago → recordatorio. Cada etapa empieza
cuando la anterior está cerrada. El frontend avanza en paralelo según la decisión D2.
