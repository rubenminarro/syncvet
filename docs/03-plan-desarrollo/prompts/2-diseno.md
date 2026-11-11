# Prompt: Diseño de la fase

Después del análisis. Sin código. Reemplazar `NN` por el número de fase y `X` por la entrega.

```text
Diseña la FASE NN en docs/03-plan-desarrollo/fases/backend/fase-NN-*.md. No escribas código todavía.

1. Analiza cada entidad nueva con la regla de oro de docs/00-vision/principios.md: quién la crea, modifica y usa, sus estados, qué depende de ella, impacto económico, qué pasa si se cancela o elimina, permisos, error_code y cómo afecta a la consulta, al stock y a la facturación.
2. Divide el alcance en entregas numeradas (NN.1, NN.2…). Para cada una: reglas de negocio, migraciones nuevas o cambios, endpoints (método, ruta, permiso, request, respuesta, error_code y código HTTP de cada error), enums y excepciones, y la lista de tests.
3. Reutiliza lo existente (Requests, Resources, Enums, excepciones) antes de crear nada.
4. Escribe al final "Preguntas de negocio" con todo lo que no esté decidido. No supongas: pregunta.
5. Muéstrame el diseño y las preguntas, y espera mi respuesta. Mis respuestas van al archivo de la fase con la fecha.
```
