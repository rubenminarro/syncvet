import { zodResolver } from '@hookform/resolvers/zod';
import { IconKey } from '@tabler/icons-react';
import { useMutation, useQuery } from '@tanstack/react-query';
import { Fragment, useState } from 'react';
import { useForm } from 'react-hook-form';
import { Alert, Button, Card, PasswordField } from '../../components/ui';
import { errorTreatment, isApiError, MESSAGES, userMessage } from '../../lib/api/errors';
import { applyValidationErrors } from '../../lib/api/form-errors';
import { useCountdown } from '../../lib/hooks/use-countdown';
import { meQueryOptions, updateOwnPassword } from './api';
import { passwordSchema, type PasswordForm } from './schemas';
import type { Me } from './types';

/** La espera de un 429 que llega sin Retry-After (como en /ingresar). */
const DEFAULT_RETRY_AFTER_SECONDS = 60;

const PASSWORD_CHANGED =
  'Cambiaste tu contraseña. Esta sesión sigue abierta; las de otros dispositivos se cerraron.';

type Feedback = { tone: 'success' | 'danger'; text: string } | null;

/** /perfil: los datos del usuario y el cambio de la contraseña propia. */
export function ProfilePage() {
  const { data: me } = useQuery({ ...meQueryOptions(), enabled: false });

  return (
    <>
      <div className="page-header mb-3">
        <h2 className="page-title">Tu perfil</h2>
      </div>
      <div className="row row-cards">
        <div className="col-md-6">{me ? <ProfileData me={me} /> : null}</div>
        <div className="col-md-6">
          <PasswordCard />
        </div>
      </div>
    </>
  );
}

function ProfileData({ me }: { me: Me }) {
  const rows: [string, string][] = [
    ['Nombre', me.name],
    ['Email', me.email],
    ['Clínica', me.clinic.name],
    ['Roles', me.roles.join(', ')],
    ['Sucursal actual', me.current_branch?.name ?? 'Sin sucursal actual'],
    ['Sucursales', me.branches.map((branch) => branch.name).join(', ') || 'Ninguna'],
  ];

  if (me.veterinarian !== null) {
    rows.push(['Ficha de veterinario', me.veterinarian.code]);
  }

  return (
    <Card title="Tus datos">
      <dl className="row mb-0 profile-data">
        {rows.map(([label, value]) => (
          <Fragment key={label}>
            <dt className="col-5">{label}</dt>
            <dd className="col-7">{value}</dd>
          </Fragment>
        ))}
      </dl>
    </Card>
  );
}

function PasswordCard() {
  const countdown = useCountdown();
  const [feedback, setFeedback] = useState<Feedback>(null);
  const {
    register,
    handleSubmit,
    setError,
    reset,
    formState: { errors },
  } = useForm<PasswordForm>({
    resolver: zodResolver(passwordSchema),
    defaultValues: { current_password: '', password: '', password_confirmation: '' },
  });

  const mutation = useMutation({
    mutationFn: updateOwnPassword,
    // Las contraseñas no quedan en la caché de mutaciones (M1 de la revisión de la 0.2b): al terminar, reset() suelta la
    // mutación y, con gcTime 0, la caché la descarta enseguida (con sus variables).
    gcTime: 0,
    onSettled: () => {
      mutation.reset();
    },
    onMutate: () => {
      setFeedback(null);
    },
    onSuccess: () => {
      reset();
      setFeedback({ tone: 'success', text: PASSWORD_CHANGED });
    },
    onError: (error) => {
      if (!isApiError(error)) {
        setFeedback({ tone: 'danger', text: MESSAGES.programming });

        return;
      }

      switch (errorTreatment(error.errorCode)) {
        case 'validation': {
          const others =
            applyValidationErrors(error, setError, ['current_password', 'password']) ?? [];
          setFeedback(others.length > 0 ? { tone: 'danger', text: others.join(' ') } : null);
          break;
        }
        case 'throttled':
          setFeedback({ tone: 'danger', text: error.message });
          countdown.start(error.retryAfter ?? DEFAULT_RETRY_AFTER_SECONDS);
          break;
        default:
          // FORBIDDEN y el resto: el mensaje de la API (los errores de sesión ya cerraron la sesión).
          setFeedback({ tone: 'danger', text: userMessage(error) });
      }
    },
  });

  const onSubmit = handleSubmit((values) => {
    mutation.mutate(values);
  });
  const waiting = countdown.remaining > 0;

  return (
    <Card title="Cambiar la contraseña">
      {feedback !== null ? <Alert tone={feedback.tone} title={feedback.text} /> : null}
      <form onSubmit={(event) => void onSubmit(event)} noValidate>
        <PasswordField
          label="Contraseña actual"
          autoComplete="current-password"
          required
          error={errors.current_password?.message}
          {...register('current_password')}
        />
        <PasswordField
          label="Contraseña nueva"
          autoComplete="new-password"
          required
          hint="Al menos 12 caracteres, con mayúsculas, minúsculas, números y símbolos."
          error={errors.password?.message}
          {...register('password')}
        />
        <PasswordField
          label="Repetí la contraseña nueva"
          autoComplete="new-password"
          required
          error={errors.password_confirmation?.message}
          {...register('password_confirmation')}
        />
        <Button
          type="submit"
          loading={mutation.isPending}
          disabled={waiting}
          icon={<IconKey size={18} className="me-2" aria-hidden="true" />}
        >
          {waiting
            ? `Esperá ${countdown.remaining} ${countdown.remaining === 1 ? 'segundo' : 'segundos'}`
            : 'Cambiar la contraseña'}
        </Button>
      </form>
    </Card>
  );
}
