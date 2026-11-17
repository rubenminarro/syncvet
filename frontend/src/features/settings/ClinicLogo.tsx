import { IconPhoto, IconTrash, IconUpload } from '@tabler/icons-react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useEffect, useRef, useState } from 'react';
import {
  Alert,
  BlobImage,
  Button,
  Card,
  ConfirmDialog,
  FileField,
  Loading,
  useToast,
} from '../../components/ui';
import { isApiError, MESSAGES, userMessage } from '../../lib/api/errors';
import { PERMISSIONS } from '../../lib/auth/permissions';
import { useSingleAction } from '../../lib/hooks/use-single-action';
import { useCan } from '../auth/permissions';
import { afterLogoChanged, deleteLogo, logoQueryOptions, markWithoutLogo, uploadLogo } from './api';
import { CLINIC_LOGO_NOT_FOUND, LOGO_ACCEPT, LOGO_MESSAGES, logoFileError } from './logo';
import type { SystemSettings } from './types';

function isLogoMissing(error: unknown): boolean {
  return isApiError(error) && error.errorCode === CLINIC_LOGO_NOT_FOUND;
}

/** El texto de un error que no es de un campo: el `message` de la API o el propio del trato. */
function messageOf(error: unknown): string {
  return isApiError(error) ? userMessage(error) : MESSAGES.programming;
}

/**
 * El logo de la clínica en /configuracion/clinica (2.5), como la foto del paciente (1.4): se descarga como Blob (con el token,
 * la misma clave que la barra) y se muestra con una URL de objeto que se libera. Subirlo o reemplazarlo: elegir el archivo, la
 * vista previa y "Subir el logo"; el navegador frena lo que no es JPG, PNG o WEBP y lo de más de 2 MB. Quitarlo, con
 * confirmación. Cada acción con su permiso (subir y quitar, el administrador).
 */
export function ClinicLogo({ settings }: { settings: SystemSettings }) {
  const can = useCan();
  const queryClient = useQueryClient();
  const toast = useToast();
  const single = useSingleAction();
  const canShow = can(PERMISSIONS.settingsShowLogo);
  const canUpload = can(PERMISSIONS.settingsUploadLogo);
  const canDelete = can(PERMISSIONS.settingsDeleteLogo);
  const hasLogo = settings.has_logo;
  const inputRef = useRef<HTMLInputElement>(null);
  const [file, setFile] = useState<File | null>(null);
  const [fieldError, setFieldError] = useState<string | undefined>(undefined);
  const [alert, setAlert] = useState<string | null>(null);
  const [confirmingDelete, setConfirmingDelete] = useState(false);
  const [deleteError, setDeleteError] = useState<string | null>(null);
  // El foco va al campo con el error de la API cuando termina la espera (como la foto: un pedido propio, no la vuelta de
  // isPending, que con reset() puede no dibujarse nunca).
  const [focusRequest, setFocusRequest] = useState(0);
  const focusedRequest = useRef(0);
  // El control del archivo lee sus primeros bytes (asíncrono): solo cuenta el de la última elección.
  const choice = useRef(0);

  const logo = useQuery({ ...logoQueryOptions(), enabled: canShow && hasLogo });

  const clearFile = () => {
    setFile(null);

    if (inputRef.current !== null) {
      inputRef.current.value = '';
    }
  };

  const upload = useMutation({
    mutationFn: (chosen: File) => uploadLogo(chosen),
    // El archivo no queda en la caché de mutaciones (como la foto): gcTime 0 y reset() al terminar.
    gcTime: 0,
    onMutate: () => {
      setAlert(null);
      setFieldError(undefined);
    },
    onSettled: () => {
      single.release();
      upload.reset();
    },
    onSuccess: (response) => {
      clearFile();
      afterLogoChanged(queryClient, response.data);
      toast.show(response.message, 'success');
    },
    onError: (error) => {
      // 422 (no es una imagen, demasiados megapíxeles) y 413 con errors.logo: al campo, sin el archivo rechazado.
      const logoError = isApiError(error) ? error.fieldErrors().logo?.join(' ') : undefined;

      if (logoError !== undefined) {
        clearFile();
        setFieldError(logoError);
        setFocusRequest((current) => current + 1);

        return;
      }

      setAlert(messageOf(error));
    },
  });

  const remove = useMutation({
    mutationFn: () => deleteLogo(),
    onMutate: () => {
      setDeleteError(null);
    },
    onSettled: () => {
      single.release();
    },
    onSuccess: (response) => {
      afterLogoChanged(queryClient, response.data);
      toast.show(response.message, 'success');
      setConfirmingDelete(false);
    },
    onError: (error) => {
      if (isLogoMissing(error)) {
        // Ya no tenía logo (lo quitó otra persona): aviso y la configuración pedida de nuevo (has_logo).
        toast.show(isApiError(error) ? error.message : MESSAGES.programming);
        setConfirmingDelete(false);
        markWithoutLogo(queryClient);

        return;
      }

      setDeleteError(messageOf(error));
    },
  });

  const uploading = upload.isPending;

  useEffect(() => {
    if (!uploading && focusRequest !== focusedRequest.current) {
      focusedRequest.current = focusRequest;
      inputRef.current?.focus();
    }
  }, [focusRequest, uploading]);

  const busy = upload.isPending || remove.isPending;

  let picture;

  if (file !== null) {
    picture = (
      <figure className="mb-3 clinic-logo-preview">
        <BlobImage blob={file} alt="Vista previa del logo nuevo" className="clinic-logo-image" />
        <figcaption className="text-secondary small mt-1">
          Vista previa: todavía no se subió.
        </figcaption>
      </figure>
    );
  } else if (canShow && hasLogo && logo.data !== undefined) {
    picture = (
      <div className="mb-3">
        <BlobImage
          blob={logo.data}
          alt={`Logo de ${settings.clinic.name}`}
          className="clinic-logo-image clinic-logo-current"
        />
      </div>
    );
  } else if (canShow && hasLogo && logo.isError && !isLogoMissing(logo.error)) {
    picture = <p className="text-secondary clinic-logo-error">{messageOf(logo.error)}</p>;
  } else if (canShow && hasLogo && !logo.isError) {
    picture = <Loading label="Cargando el logo…" />;
  } else {
    // Sin logo, o el 404 CLINIC_LOGO_NOT_FOUND al verlo: el lugar vacío, sin error.
    picture = (
      <div className="d-flex flex-column align-items-center text-secondary py-4 mb-3 clinic-logo-empty">
        <IconPhoto size={48} aria-hidden="true" />
        <span>Sin logo</span>
      </div>
    );
  }

  return (
    <Card title="Logo">
      {picture}
      {alert !== null ? <Alert>{alert}</Alert> : null}
      {canUpload ? (
        <form
          className="clinic-logo-form"
          noValidate
          onSubmit={(event) => {
            event.preventDefault();

            if (file !== null && single.tryStart()) {
              upload.mutate(file);
            }
          }}
        >
          <FileField
            ref={inputRef}
            name="logo"
            label={hasLogo ? 'Cambiar el logo' : 'Elegir un logo'}
            accept={LOGO_ACCEPT}
            hint="JPG, PNG o WEBP, hasta 2 MB. Se muestra en la barra, con 32 px de alto."
            error={fieldError}
            disabled={busy}
            onChange={(event) => {
              const chosen = event.target.files?.[0] ?? null;
              const current = ++choice.current;
              setAlert(null);

              if (chosen === null) {
                setFile(null);
                setFieldError(undefined);

                return;
              }

              void logoFileError(chosen).then(
                (error) => {
                  if (current !== choice.current) {
                    return;
                  }

                  setFieldError(error ?? undefined);

                  if (error === null) {
                    setFile(chosen);
                  } else {
                    clearFile();
                  }
                },
                () => {
                  // El navegador no pudo leer el archivo (se movió o se borró después de elegirlo).
                  if (current === choice.current) {
                    setFieldError(LOGO_MESSAGES.notAnImage);
                    clearFile();
                  }
                },
              );
            }}
          />
          {file !== null ? (
            <div className="d-flex gap-2 mb-2">
              <Button
                type="submit"
                className="clinic-logo-upload"
                loading={upload.isPending}
                icon={<IconUpload size={18} className="me-2" aria-hidden="true" />}
              >
                Subir el logo
              </Button>
              <Button variant="secondary" disabled={busy} onClick={clearFile}>
                Cancelar
              </Button>
            </div>
          ) : null}
        </form>
      ) : null}
      {canDelete && hasLogo && file === null ? (
        <Button
          variant="link"
          className="text-danger px-0 clinic-logo-delete"
          disabled={busy}
          icon={<IconTrash size={18} className="me-2" aria-hidden="true" />}
          onClick={() => {
            setDeleteError(null);
            setConfirmingDelete(true);
          }}
        >
          Quitar el logo
        </Button>
      ) : null}
      <ConfirmDialog
        open={confirmingDelete}
        title="Quitar el logo"
        confirmLabel="Quitar el logo"
        busy={remove.isPending}
        error={deleteError}
        onConfirm={() => {
          if (single.tryStart()) {
            remove.mutate();
          }
        }}
        onClose={() => {
          setConfirmingDelete(false);
        }}
      >
        ¿Querés quitar el logo de <strong>{settings.clinic.name}</strong>? La barra vuelve a mostrar
        la marca SyncVet.
      </ConfirmDialog>
    </Card>
  );
}
