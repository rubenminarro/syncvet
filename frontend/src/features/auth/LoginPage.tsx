import { zodResolver } from '@hookform/resolvers/zod';
import { IconLogin2 } from '@tabler/icons-react';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { useNavigate, useSearch } from '@tanstack/react-router';
import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { Alert, Button, PasswordField, TextField } from '../../components/ui';
import { errorTreatment, isApiError, MESSAGES, userMessage } from '../../lib/api/errors';
import { applyValidationErrors } from '../../lib/api/form-errors';
import { safeRedirect } from '../../lib/auth/redirect';
import { useSessionManager, useSessionState } from '../../lib/auth/use-session';
import { useCountdown } from '../../lib/hooks/use-countdown';
import { login, meQueryOptions } from './api';
import { loginSchema, type LoginForm } from './schemas';

/** La espera de un 429 que llega sin Retry-After. */
const DEFAULT_RETRY_AFTER_SECONDS = 60;

/**
 * Ingresar: POST /api/login; con el 200 guarda la sesión, deja el usuario en ['me'] y vuelve a la ruta pedida (`volver`,
 * solo interna) o al inicio. Los errores, según la tabla de errors.ts.
 */
export function LoginPage() {
  const session = useSessionManager();
  const { notice } = useSessionState();
  const queryClient = useQueryClient();
  const navigate = useNavigate();
  const { volver } = useSearch({ from: '/_publico/ingresar' });
  const countdown = useCountdown();
  const [alert, setAlert] = useState<string | null>(null);
  // El aviso de la sesión cerrada se muestra hasta el primer intento (antes, mutation.isIdle: con reset() vuelve a idle).
  const [attempted, setAttempted] = useState(false);
  const {
    register,
    handleSubmit,
    setError,
    setValue,
    setFocus,
    formState: { errors },
  } = useForm<LoginForm>({
    resolver: zodResolver(loginSchema),
    defaultValues: { email: '', password: '' },
  });

  const mutation = useMutation({
    mutationFn: login,
    // La contraseña no queda en la caché de mutaciones (M1 de la revisión de la 0.2b): reset() al terminar y gcTime 0.
    gcTime: 0,
    onSettled: () => {
      mutation.reset();
    },
    onMutate: () => {
      setAlert(null);
      setAttempted(true);
    },
    onSuccess: async (data) => {
      session.start({ token: data.token, expires_at: data.expires_at });
      queryClient.setQueryData(meQueryOptions().queryKey, data.user);
      await navigate({ href: safeRedirect(volver) });
    },
    onError: (error) => {
      if (!isApiError(error)) {
        setAlert(MESSAGES.programming);

        return;
      }

      switch (errorTreatment(error.errorCode)) {
        case 'validation': {
          const others = applyValidationErrors(error, setError, ['email', 'password']) ?? [];
          setAlert(others.length > 0 ? others.join(' ') : null);
          break;
        }
        case 'invalid-credentials':
          setAlert(error.message);
          setValue('password', '');
          setFocus('password');
          break;
        case 'throttled':
          setAlert(error.message);
          // Sin Retry-After (o uno que no se entiende), una espera prudente.
          countdown.start(error.retryAfter ?? DEFAULT_RETRY_AFTER_SECONDS);
          break;
        default:
          // USER_INACTIVE, CLINIC_NOT_AVAILABLE y el resto: el mensaje en el formulario.
          setAlert(userMessage(error));
      }
    },
  });

  const onSubmit = handleSubmit((values) => {
    mutation.mutate(values);
  });
  const waiting = countdown.remaining > 0;

  return (
    <div className="card card-md">
      <div className="card-body">
        <h2 className="h2 text-center mb-4">Ingresá a tu cuenta</h2>
        {notice !== null && !attempted ? <Alert tone="warning" title={notice} /> : null}
        {alert !== null ? <Alert title={alert} /> : null}
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
          <PasswordField
            label="Contraseña"
            autoComplete="current-password"
            required
            error={errors.password?.message}
            {...register('password')}
          />
          <div className="form-footer">
            <Button
              type="submit"
              block
              loading={mutation.isPending}
              disabled={waiting}
              icon={<IconLogin2 size={18} className="me-2" aria-hidden="true" />}
            >
              {waiting
                ? `Esperá ${countdown.remaining} ${countdown.remaining === 1 ? 'segundo' : 'segundos'}`
                : 'Ingresar'}
            </Button>
          </div>
        </form>
      </div>
    </div>
  );
}
