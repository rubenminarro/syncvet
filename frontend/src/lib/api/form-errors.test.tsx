import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { http } from 'msw';
import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { describe, expect, it } from 'vitest';
import { Alert, TextField } from '../../components/ui';
import { failure } from '../../test/api';
import { server } from '../../test/server';
import { api } from './client';
import { ApiError, userMessage } from './errors';
import { applyValidationErrors } from './form-errors';

interface ClientForm {
  name: string;
  email: string;
}

/** Un formulario de prueba con el manejo de errores que van a usar los módulos. */
function TestForm() {
  const [alert, setAlert] = useState<string[]>([]);
  const {
    register,
    handleSubmit,
    setError,
    formState: { errors },
  } = useForm<ClientForm>({ defaultValues: { name: '', email: '' } });

  const onSubmit = handleSubmit(async (values) => {
    try {
      await api.post('clients', values);
    } catch (error) {
      if (!(error instanceof ApiError)) throw error;
      const others = applyValidationErrors(error, setError, ['name', 'email']);
      setAlert(others ?? [userMessage(error)]);
    }
  });

  return (
    <form onSubmit={(event) => void onSubmit(event)}>
      {alert.length > 0 ? <Alert>{alert.join(' ')}</Alert> : null}
      <TextField label="Nombre" error={errors.name?.message} {...register('name')} />
      <TextField label="Email" error={errors.email?.message} {...register('email')} />
      <button type="submit">Guardar</button>
    </form>
  );
}

describe('errores de validación a los campos', () => {
  it('cada clave de errors va a su campo; la que no es de un campo, a la alerta', async () => {
    server.use(
      http.post('/api/clients', () =>
        failure(422, 'VALIDATION_ERROR', 'Revisá los datos.', {
          errors: {
            email: ['El email ya está registrado.'],
            name: ['Ingresá el nombre.'],
            document_number: ['El documento ya existe.'],
          },
        }),
      ),
    );
    render(<TestForm />);

    await userEvent.click(screen.getByRole('button', { name: 'Guardar' }));

    const email = await screen.findByLabelText('Email');
    expect(email).toHaveAttribute('aria-invalid', 'true');
    expect(email).toHaveAccessibleDescription('El email ya está registrado.');
    expect(screen.getByLabelText('Nombre')).toHaveAccessibleDescription('Ingresá el nombre.');
    expect(screen.getByRole('alert')).toHaveTextContent('El documento ya existe.');
    // Foco en el primer campo con error (el orden de errors).
    expect(email).toHaveFocus();
  });

  it('un error que no es de validación no toca los campos y va a la alerta con su mensaje', async () => {
    server.use(
      http.post('/api/clients', () =>
        failure(409, 'CLIENT_IN_USE', 'El cliente tiene pacientes activos.'),
      ),
    );
    render(<TestForm />);

    await userEvent.click(screen.getByRole('button', { name: 'Guardar' }));

    expect(await screen.findByRole('alert')).toHaveTextContent(
      'El cliente tiene pacientes activos.',
    );
    expect(screen.getByLabelText('Email')).not.toHaveAttribute('aria-invalid');
  });
});
