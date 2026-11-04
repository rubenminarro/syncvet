# Handoff de SyncVet

Documento principal para retomar el desarrollo desde cualquier computadora, con o sin el contexto de sesiones anteriores.

> **Sesión nueva de Claude Code:** ejecutar `/retomar`, o leer este documento y [`CLAUDE.md`](../CLAUDE.md), verificar el estado de
> Git y continuar desde [Próximas tareas](#próximas-tareas). [`README.md`](README.md) indica dónde buscar cada tema.

Si este documento contradice al código, manda el código.

## Punto de continuidad

| Dato | Valor |
|---|---|
| Actualizado | 2026-11-04 |
| Backend | Paquete base, todavía sin repositorio Git |
| Frontend | Sin empezar |
| Última entrega de código | Paquete base: esquema del MVP, arquitectura y módulo de ejemplo (registro 0.1) |
| Tests del backend | Ninguno todavía. Verificación fuera de PHPUnit en el registro 0.1 |
| Último checkpoint validado | Ninguno: el primero lo deja la FASE 0 |

El estado actual siempre se confirma con `git log origin/main` y `php artisan test`.

## Estado del proyecto

SyncVet es la API de gestión de una clínica veterinaria (Paraguay), con el frontend en otro repositorio. El plan del MVP tiene 11
fases ([`roadmap/README.md`](roadmap/README.md)); ninguna empezó.

**El sistema no está listo para producción.**

## Próximas tareas

| Orden | Tarea | Bloqueo |
|---|---|---|
| 1 | FASE 0: análisis, diseño y puesta en marcha ([`fases/fase-00-puesta-en-marcha.md`](fases/fase-00-puesta-en-marcha.md)), incluida la entrega 0.2 que aplica D1 | Ninguno: D1 decidida (registro 0.2) |
| 2 | FASE 1: clientes y pacientes | FASE 0 cerrada |

Ninguna entrega empieza sin autorización del usuario.

## Decisiones técnicas que hay que tener presentes

| Tema | Decisión |
|---|---|
| Arquitectura | Controller → Form Request → Service → Model → Resource → ApiResponse (`CLAUDE.md` §4) |
| Estados | `string(20)` en la base y Enum en PHP; nunca columnas `ENUM` de MySQL |
| Dinero | `decimal(14,2)` con BCMath |
| Zona horaria | `America/Asuncion` en la base; la API envía UTC |
| Separación por clínica | Una base compartida con `clinic_id`, scope global y fallo cerrado (D1, registro 0.2). Se aplica en la FASE 0 |
| Facturación | SIFEN (Paraguay), en las FASES 8 y 9 |
