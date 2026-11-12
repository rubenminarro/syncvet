import { z } from 'zod';

export const loginSchema = z.object({
  email: z.email('Ingresá un email válido.'),
  password: z.string().min(1, 'Ingresá tu contraseña.'),
});

export type LoginForm = z.infer<typeof loginSchema>;

/** El cambio de la contraseña propia: la regla de la nueva (12 caracteres, mayúsculas…) la valida la API. */
export const passwordSchema = z.object({
  current_password: z.string().min(1, 'Ingresá tu contraseña actual.'),
  password: z.string().min(1, 'Ingresá la contraseña nueva.'),
});

export type PasswordForm = z.infer<typeof passwordSchema>;
