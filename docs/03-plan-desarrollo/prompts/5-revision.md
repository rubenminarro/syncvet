# Prompt: Revisión

Después de implementar, en una sesión nueva de Claude Code. Reemplazar `NN` por el número de fase y `X` por la entrega.

```text
Revisa la implementación de la entrega NN.X como un revisor que no la escribió. No corrijas nada todavía.

1. Lee docs/03-plan-desarrollo/fases/backend/fase-NN-*.md y CLAUDE.md, y compara con git diff origin/main...HEAD.
2. Verifica: lógica en Services, Controllers con HasMiddleware y permisos que existen en el seeder, estados con enums, errores con excepción de dominio (nunca 500), dinero con BCMath, transacciones y lockForUpdate donde hay concurrencia (con el orden de bloqueo de CLAUDE.md), nombres de índices de hasta 64 caracteres, contratos existentes sin cambios.
3. Busca reglas de la especificación sin test, y tests que pasarían aunque la regla estuviera rota.
4. Prueba con un rol que no sea administrador (Gate::before oculta errores de permisos).
5. Corre la suite completa.

Entrégame los hallazgos ordenados por gravedad, cada uno con archivo, línea y cómo reproducirlo.
```
