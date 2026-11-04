# FASE 6 — Documentos

Etapa: Comunicación. Estado: **PENDIENTE**. El estado vigente de cada fase está en [`docs/roadmap/README.md`](../roadmap/README.md).

## Objetivo

Recetas, certificados, órdenes y constancias en PDF, desde plantillas editables por la clínica.

## Alcance inicial

El diseño (paso 2) completa cada entrega con reglas de negocio, datos, endpoints, errores y tests.

| Entrega | Contenido | Estado |
|---|---|---|
| 6.1 | Plantillas por tipo | PENDIENTE |
| 6.2 | Emisión desde la consulta o el paciente; documento inmutable | PENDIENTE |
| 6.3 | Constancia de vacunación y certificado de desparasitación | PENDIENTE |
| 6.4 | PDF con los datos de la clínica | PENDIENTE |
| 6.5 | QR de verificación pública | PENDIENTE |
| 6.6 | Anulación | PENDIENTE |

## Preguntas de negocio

Las respuestas se anotan con la fecha y pasan a [`docs/decisions/registro.md`](../decisions/registro.md).

| # | Pregunta | Respuesta | Fecha |
|---|---|---|---|
| 1 | ¿La receta lleva firma digital o firma escaneada? | | |
| 2 | ¿Qué muestra la verificación pública? | | |
| 3 | ¿A4 o media hoja? | | |

## Diseño

PENDIENTE: lo completa el paso 2 ([`docs/prompts/2-diseno.md`](../prompts/2-diseno.md)), aplicando la regla de oro de
[`docs/project/principios.md`](../project/principios.md).

## Criterio de cierre

Un documento emitido no cambia aunque se edite la plantilla; la descarga queda auditada.

## Prompt de implementación (paso 4)

Usar solo con el diseño aprobado.

```text
Implementa la FASE 6 según docs/fases/fase-06-documentos.md (diseño aprobado).

- DocumentTemplateService: CRUD de plantillas con variables documentadas (paciente, tutor, clínica, veterinario, consulta).
- PatientDocumentService: emitir desde una consulta o un paciente; guarda el contenido renderizado y el PDF; inmutable (solo se anula con motivo).
- Constancia de vacunación y certificado de desparasitación desde patient_applications.
- PDFs con vistas Blade en resources/views/pdf y los datos de la clínica de system_settings.
- GET público documents/verify/{verification_code}: solo los datos que defina la respuesta de negocio.
- Descargas con nosniff y registro en audit_logs.

Sigue CLAUDE.md. Corre la suite e infórmame. No hagas commit.
```

## Resultado

PENDIENTE: lo completa el cierre ([`docs/prompts/5-cierre.md`](../prompts/5-cierre.md)): archivos, endpoints, tests y aserciones,
desvíos del diseño y por qué.
