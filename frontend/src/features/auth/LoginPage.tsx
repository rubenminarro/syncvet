import { zodResolver } from '@hookform/resolvers/zod';
import { IconLogin2 } from '@tabler/icons-react';
import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { Alert, Button, TextField } from '../../components/ui';
import { loginSchema, type LoginForm } from './schemas';

/**
 * Ingresar. En la F0.1 es la maqueta: valida el formulario pero todavía no llama a la API (POST /api/login llega en la
 * F0.2, con los errores de la tabla de errors.ts).
 */
export function LoginPage() {
  const [pending, setPending] = useState(false);
  const {
    register,
    handleSubmit,
    formState: { errors },
  } = useForm<LoginForm>({
    resolver: zodResolver(loginSchema),
    defaultValues: { email: '', password: '' },
  });

  const onSubmit = handleSubmit(() => {
    setPending(true);
  });

  return (
    <div className="card card-md">
      <div className="card-body">
        <h2 className="h2 text-center mb-4">Ingresá a tu cuenta</h2>
        {pending ? (
          <Alert tone="info" title="Todavía no se puede ingresar">
            La conexión con el servidor llega en la próxima entrega.
          </Alert>
        ) : null}
        <form onSubmit={(event) => void onSubmit(event)} noValidate>
          <TextField
            label="Email"
            type="email"
            autoComplete="username"
            placeholder="tu@clinica.com.py"
            required
            error={errors.email?.message}
            {...register('email')}
          />
          <TextField
            label="Contraseña"
            type="password"
            autoComplete="current-password"
            required
            error={errors.password?.message}
            {...register('password')}
          />
          <div className="form-footer">
            <Button
              type="submit"
              block
              icon={<IconLogin2 size={18} className="me-2" aria-hidden="true" />}
            >
              Ingresar
            </Button>
          </div>
        </form>
      </div>
    </div>
  );
}
