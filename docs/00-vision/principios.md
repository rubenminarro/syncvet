# Regla de oro del proyecto

> **No diseñar cada módulo como una funcionalidad aislada.**

Cada nueva entidad debe analizarse considerando:

```text
¿Quién la crea?
¿Quién la modifica?
¿Quién la utiliza?
¿En qué estado puede estar?
¿Qué entidades dependen de ella?
¿Qué impacto económico tiene?
¿Qué pasa si se cancela?
¿Qué pasa si se elimina?
¿Qué permisos requiere?
¿Qué error_code necesita?
¿Cómo afecta a la consulta?
¿Cómo afectará al stock, a la facturación y a los recordatorios?
```

El objetivo no es tener muchos CRUDs, sino un **sistema coherente de gestión de clínica veterinaria**, donde:

```text
Turno
    ↓
Fichado (llegada)
    ↓
Consulta
    ↓
Diagnóstico y plan
    ↓
Servicios + productos usados + aplicaciones
    ↓
Stock
    ↓
Cierre de la consulta
    ↓
Factura (SIFEN)
    ↓
Pago en caja
    ↓
Recordatorio de la próxima dosis o control
    ↓
Próximo turno
```

Cada fase se diseña mirando este flujo completo, aunque implemente solo una parte.
