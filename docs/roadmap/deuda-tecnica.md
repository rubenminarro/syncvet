# Deuda técnica

Deudas y discrepancias abiertas, numeradas. La misma lista resumida está en `CLAUDE.md`, sección 7. Una deuda resuelta pasa a
"Resueltas" con la sección del registro que la cerró.

## Abiertas

| # | Deuda | Origen | Resolver en |
|---|---|---|---|
| 1 | El módulo de clientes y pacientes no tiene tests de PHPUnit; los Form Requests y las rutas no se probaron | Registro R-001 | FASE 1 |
| 2 | Los modelos fuera de clientes y pacientes se generaron desde el esquema: revisar relaciones y casts al usarlos | Registro R-001 | Fase de cada módulo |
| 3 | Códigos SIFEN de `PaymentMethod::sifenCode()` e `invoice_items.unit_of_measure` sin verificar contra el Manual Técnico | Registro R-001 | FASE 9 |

## Resueltas

| # | Deuda | Cerrada por |
|---|---|---|
| 4 | Sin límite de intentos en el login | R-004: se adelanta a la entrega del `AuthController` de la FASE 0 (5 intentos por minuto por email e IP) |
