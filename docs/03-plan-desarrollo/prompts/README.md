# Prompts para Claude Code

Cada fase recorre siete pasos; cinco los hace Claude Code con un prompt y dos los haces tú.

| Paso | Quién | Prompt |
|---|---|---|
| 0. Primera sesión (una sola vez) | Claude Code | [`0-primera-sesion.md`](0-primera-sesion.md) |
| 1. Análisis de lo hecho | Claude Code | [`1-analisis.md`](1-analisis.md) |
| 2. Diseño de la fase | Claude Code | [`2-diseno.md`](2-diseno.md) |
| 3. Aprobación del diseño y respuestas de negocio | Tú | — |
| 4. Implementación | Claude Code | El de la fase, al final de `docs/03-plan-desarrollo/fases/backend/fase-NN-*.md` |
| 5. Revisión (sesión nueva) | Claude Code | [`5-revision.md`](5-revision.md) |
| 6. Validación con Postman y la suite | Tú | — |
| 7. Cierre | Claude Code | [`7-cierre.md`](7-cierre.md) |

Si la revisión encuentra problemas, se vuelve al paso 4. Una fase grande se divide en entregas (NN.1, NN.2…) y cada entrega
recorre los pasos 4 a 7. Commit, push y merge a `main` los pides tú.
