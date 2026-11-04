# Arquitectura

La arquitectura, las carpetas y el contrato de respuesta están en [`CLAUDE.md`](../../CLAUDE.md), sección 4. Este documento agrega
cómo se relacionan los módulos.

## Módulos y tablas

| Módulo | Tablas | Fase |
|---|---|---|
| Sucursales | branches, branch_user, warehouses | 2 |
| Personas | users, veterinarians, clients | 0, 1, 2 |
| Pacientes | species, breeds, patients | 1 |
| Agenda | services, schedules, schedule_hours, schedule_blocks, appointments | 2, 3 |
| Historia clínica | consultations, consultation_attachments, consultation_items, patient_applications | 4 |
| Documentos | document_templates, patient_documents | 6 |
| Avisos | notifications, reminders, client_notifications | 7 |
| Inventario | products, product_stocks, product_lots, inventory_movements | 5 |
| Caja y facturación | cash_registers, cash_movements, invoice_stamps, invoices, invoice_items, payments, sifen_events | 8, 9 |
| Sistema | audit_logs, system_settings, document_sequences | 0 |

## Flujo principal

```text
appointments → consultations → consultation_items ─┬→ inventory_movements (stock)
                     │                              └→ invoice_items → invoices → payments (caja)
                     └→ patient_applications → reminders → client_notifications
```

La consulta guarda el dueño del paciente en ese momento (`consultations.client_id`): el paciente puede cambiar de dueño después.
