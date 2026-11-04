# Prompt: Cierre

Cuando la revisión queda limpia y validaste la entrega. Reemplazar `NN` por el número de fase y `X` por la entrega.

```text
La entrega NN.X está validada: la suite pasó en esta sesión y la probé con Postman. Ejecuta /cerrar-entrega NN.X.

Además de lo que indica la skill:
- Completa "Resultado" en docs/fases/fase-NN-*.md: archivos, endpoints, tests y aserciones, desvíos del diseño y por qué.
- Agrega la sección siguiente en docs/decisions/registro.md, con las decisiones y quién las tomó.
- Actualiza el punto de continuidad de docs/handoff.md con el commit y los tests de esta sesión.
- Ejecuta /validar-docs.

Muéstrame el resumen y el mensaje de commit propuesto (en español, con el porqué). No hagas commit hasta que te lo pida.
```
