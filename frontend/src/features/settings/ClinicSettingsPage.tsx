import { zodResolver } from '@hookform/resolvers/zod';
import { IconDeviceFloppy } from '@tabler/icons-react';
import { useMutation, useQueryClient, useSuspenseQuery } from '@tanstack/react-query';
import { Link } from '@tanstack/react-router';
import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { Alert, Button, Card, TextareaField, TextField, useToast } from '../../components/ui';
import { isApiError, MESSAGES, userMessage } from '../../lib/api/errors';
import { applyValidationErrors } from '../../lib/api/form-errors';
import { useSingleAction } from '../../lib/hooks/use-single-action';
import { afterSettingsChanged, settingsKeys, settingsQueryOptions, updateSettings } from './api';
import { ClinicLogo } from './ClinicLogo';
import {
  CONTACT_EMAIL_MAX,
  RUC_HINT,
  SETTINGS_FORM_FIELDS,
  settingsFormSchema,
  settingsFormToPayload,
  settingsToForm,
  type SettingsFormValues,
} from './form';

/**
 * /configuracion/clinica (2.5, solo el administrador): los datos de la clínica (el RUC, solo para leer), las claves de
 * contacto y documentos, y el logo. Guardar manda el formulario completo (PATCH system-settings) y vuelve a pedir `me` (el
 * nombre en la barra).
 */
export function ClinicSettingsPage() {
  const queryClient = useQueryClient();
  const toast = useToast();
  const single = useSingleAction();
  const { data } = useSuspenseQuery(settingsQueryOptions());
  const [alert, setAlert] = useState<string | null>(null);
  const form = useForm<SettingsFormValues>({
    resolver: zodResolver(settingsFormSchema),
    defaultValues: settingsToForm(data),
  });
  const {
    register,
    handleSubmit,
    formState: { errors, isSubmitting },
  } = form;

  const mutation = useMutation({
    mutationFn: (values: SettingsFormValues) => updateSettings(settingsFormToPayload(values)),
    onMutate: () => {
      setAlert(null);
    },
    onSettled: () => {
      single.release();
    },
    onSuccess: (response) => {
      afterSettingsChanged(queryClient, response.data);
      form.reset(settingsToForm(response.data));
      toast.show(response.message, 'success');
    },
    onError: (error) => {
      if (!isApiError(error)) {
        setAlert(MESSAGES.programming);

        return;
      }

      const others = applyValidationErrors(error, form.setError, SETTINGS_FORM_FIELDS);

      if (others === null) {
        // No es de validación (un 403, un 409, la red): la alerta, y los datos se vuelven a pedir si alguien los cambió.
        setAlert(userMessage(error));

        if (error.errorCode === 'CONFLICT') {
          void queryClient.invalidateQueries({ queryKey: settingsKeys.all, exact: true });
        }

        return;
      }

      setAlert(others.length > 0 ? others.join(' ') : null);
    },
  });

  const submit = handleSubmit((values) => {
    if (single.tryStart()) {
      mutation.mutate(values);
    }
  });

  const saving = isSubmitting || mutation.isPending;

  return (
    <>
      <div className="page-header mb-3">
        <div className="page-pretitle">
          <Link to="/configuracion">Configuración</Link>
        </div>
        <h2 className="page-title">Datos de la clínica</h2>
      </div>
      <div className="row row-cards">
        <div className="col-lg-8">
          <form className="settings-form" onSubmit={(event) => void submit(event)} noValidate>
            <Card title="Datos">
              {alert !== null ? <Alert title={alert} /> : null}
              <div className="row">
                <div className="col-md-6">
                  <TextField
                    label="Nombre"
                    required
                    autoComplete="off"
                    hint="El nombre de fantasía: el de la barra."
                    error={errors.name?.message}
                    {...register('name')}
                  />
                </div>
                <div className="col-md-6">
                  <TextField
                    label="Razón social"
                    required
                    autoComplete="off"
                    error={errors.legal_name?.message}
                    {...register('legal_name')}
                  />
                </div>
                <div className="col-md-6">
                  {/* Solo para leer: no se registra en el formulario y nunca se manda (la API responde 422 prohibited). */}
                  <TextField
                    label="RUC"
                    value={`${data.clinic.ruc}-${data.clinic.ruc_dv}`}
                    readOnly
                    className="settings-ruc"
                    hint={RUC_HINT}
                  />
                </div>
              </div>
            </Card>
            <div className="mt-3">
              <Card title="Contacto y documentos">
                <div className="row">
                  <div className="col-md-6">
                    <TextField
                      label="Correo de contacto"
                      type="email"
                      maxLength={CONTACT_EMAIL_MAX}
                      autoComplete="off"
                      error={errors.settings?.contact_email?.message}
                      {...register('settings.contact_email')}
                    />
                  </div>
                  <div className="col-md-6">
                    <TextField
                      label="Teléfono de contacto"
                      type="tel"
                      autoComplete="off"
                      hint="Con el código del país y sin el 0 inicial: +595211100001."
                      error={errors.settings?.contact_phone?.message}
                      {...register('settings.contact_phone')}
                    />
                  </div>
                  <div className="col-12">
                    <TextField
                      label="Sitio web"
                      type="url"
                      autoComplete="off"
                      hint="Empieza con https://."
                      error={errors.settings?.website?.message}
                      {...register('settings.website')}
                    />
                  </div>
                  <div className="col-12">
                    <TextareaField
                      label="Pie de recetas y comprobantes"
                      rows={3}
                      hint="Hasta 500 caracteres."
                      error={errors.settings?.document_footer?.message}
                      {...register('settings.document_footer')}
                    />
                  </div>
                </div>
                <div className="d-flex justify-content-end">
                  <Button
                    type="submit"
                    className="settings-save"
                    loading={saving}
                    icon={<IconDeviceFloppy size={18} className="me-2" aria-hidden="true" />}
                  >
                    Guardar
                  </Button>
                </div>
              </Card>
            </div>
          </form>
        </div>
        <div className="col-lg-4">
          <ClinicLogo settings={data} />
        </div>
      </div>
    </>
  );
}
