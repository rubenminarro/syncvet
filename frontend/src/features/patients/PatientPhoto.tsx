import { IconPaw, IconTrash, IconUpload } from '@tabler/icons-react';
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
import { errorTreatment, isApiError, MESSAGES, userMessage } from '../../lib/api/errors';
import { PERMISSIONS } from '../../lib/auth/permissions';
import { useCan } from '../auth/permissions';
import {
  afterPhotoChanged,
  deletePatientPhoto,
  markWithoutPhoto,
  patientKeys,
  patientPhotoQueryOptions,
  uploadPatientPhoto,
} from './api';
import { usePatientGone, useSingleAction } from './use-patient-action';
import { PHOTO_ACCEPT, PHOTO_MESSAGES, photoFileError } from './photo';
import type { PatientDetail } from './types';

/** La foto no está (otra persona la quitó, o la referencia apunta a un archivo que no existe): el lugar vacío, sin error. */
const PATIENT_PHOTO_NOT_FOUND = 'PATIENT_PHOTO_NOT_FOUND';

function isPhotoMissing(error: unknown): boolean {
  return isApiError(error) && error.errorCode === PATIENT_PHOTO_NOT_FOUND;
}

/** El texto de un error que no es de un campo: el `message` de la API o el propio del trato. */
function messageOf(error: unknown): string {
  return isApiError(error) ? userMessage(error) : MESSAGES.programming;
}

/**
 * La foto del paciente en su detalle (1.4): se descarga como Blob (con el token) solo aquí, no en los listados, y se muestra
 * con una URL de objeto que se libera al cambiar o al desmontar. Subirla (o reemplazarla): elegir el archivo, ver la vista
 * previa y "Subir la foto" (pregunta 9 del diseño); el navegador frena lo que no es JPG, PNG o WEBP y lo de más de 5 MB.
 * Quitarla, con confirmación. Cada acción con su permiso (los tres roles; también con el paciente fallecido).
 */
export function PatientPhoto({ patient }: { patient: PatientDetail }) {
  const can = useCan();
  const queryClient = useQueryClient();
  const toast = useToast();
  const onGone = usePatientGone(patient);
  const single = useSingleAction();
  const canShow = can(PERMISSIONS.patientsShowPhoto);
  const canUpload = can(PERMISSIONS.patientsUploadPhoto);
  const canDelete = can(PERMISSIONS.patientsDeletePhoto);
  const inputRef = useRef<HTMLInputElement>(null);
  const [file, setFile] = useState<File | null>(null);
  const [fieldError, setFieldError] = useState<string | undefined>(undefined);
  const [alert, setAlert] = useState<string | null>(null);
  const [confirmingDelete, setConfirmingDelete] = useState(false);
  const [deleteError, setDeleteError] = useState<string | null>(null);
  // El foco va al campo con el error de la API cuando termina la espera (mientras sube, el campo está deshabilitado). Es un
  // pedido propio y no la vuelta de isPending: con reset() al terminar, React puede no dibujar nunca la espera.
  const [focusRequest, setFocusRequest] = useState(0);
  const focusedRequest = useRef(0);
  // El control del archivo lee sus primeros bytes (asíncrono): solo cuenta el de la última elección.
  const choice = useRef(0);

  const photo = useQuery({
    ...patientPhotoQueryOptions(patient.id),
    enabled: canShow && patient.has_photo,
  });

  const clearFile = () => {
    setFile(null);

    if (inputRef.current !== null) {
      inputRef.current.value = '';
    }
  };

  const upload = useMutation({
    mutationFn: (chosen: File) => uploadPatientPhoto(patient.id, chosen),
    // El archivo no queda en la caché de mutaciones (revisión de la 1.4, como las contraseñas): gcTime 0 y reset() al terminar.
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
      afterPhotoChanged(queryClient, response.data);
      toast.show(response.message, 'success');
    },
    onError: (error) => {
      // 422 (no es una imagen, demasiados megapíxeles) y 413 con errors.photo: al campo, sin el archivo rechazado.
      const photoError = isApiError(error) ? error.fieldErrors().photo?.join(' ') : undefined;

      if (photoError !== undefined) {
        clearFile();
        setFieldError(photoError);
        setFocusRequest((current) => current + 1);

        return;
      }

      if (isApiError(error) && errorTreatment(error.errorCode) === 'not-found') {
        onGone(error.message);

        return;
      }

      setAlert(messageOf(error));
    },
  });

  const remove = useMutation({
    mutationFn: () => deletePatientPhoto(patient.id),
    onMutate: () => {
      setDeleteError(null);
    },
    onSettled: () => {
      single.release();
    },
    onSuccess: (response) => {
      afterPhotoChanged(queryClient, response.data);
      toast.show(response.message, 'success');
      setConfirmingDelete(false);
    },
    onError: (error) => {
      if (isPhotoMissing(error)) {
        // Ya no tenía foto (la quitó otra persona): aviso y el paciente pedido de nuevo (has_photo).
        toast.show(isApiError(error) ? error.message : MESSAGES.programming);
        setConfirmingDelete(false);
        markWithoutPhoto(queryClient, patient.id);
        void queryClient.invalidateQueries({ queryKey: patientKeys.detail(patient.id) });

        return;
      }

      if (isApiError(error) && errorTreatment(error.errorCode) === 'not-found') {
        onGone(error.message);

        return;
      }

      setDeleteError(messageOf(error));
    },
  });

  // Con 404 PATIENT_PHOTO_NOT_FOUND al verla (has_photo decía que había; el archivo falta): el detalle pasa a sin foto, así
  // no se ofrecen "Cambiar" ni "Quitar" sobre una foto que no está (revisión de la 1.4).
  const photoMissing = isPhotoMissing(photo.error);

  useEffect(() => {
    if (photoMissing) {
      markWithoutPhoto(queryClient, patient.id);
    }
  }, [photoMissing, queryClient, patient.id]);

  const uploading = upload.isPending;

  useEffect(() => {
    if (!uploading && focusRequest !== focusedRequest.current) {
      focusedRequest.current = focusRequest;
      inputRef.current?.focus();
    }
  }, [focusRequest, uploading]);

  if (!canShow && !canUpload && !canDelete) {
    return null;
  }

  const busy = upload.isPending || remove.isPending;

  let picture;

  if (file !== null) {
    picture = (
      <figure className="mb-3 patient-photo-preview">
        <BlobImage blob={file} alt="Vista previa de la foto nueva" className="img-fluid rounded" />
        <figcaption className="text-secondary small mt-1">
          Vista previa: todavía no se subió.
        </figcaption>
      </figure>
    );
  } else if (canShow && patient.has_photo && photo.data !== undefined) {
    picture = (
      <BlobImage
        blob={photo.data}
        alt={`Foto de ${patient.name}`}
        className="img-fluid rounded mb-3 patient-photo-image"
      />
    );
  } else if (canShow && patient.has_photo && photo.isError && !isPhotoMissing(photo.error)) {
    picture = <p className="text-secondary patient-photo-error">{messageOf(photo.error)}</p>;
  } else if (canShow && patient.has_photo && !photo.isError) {
    picture = <Loading label="Cargando la foto…" />;
  } else {
    picture = (
      <div className="d-flex flex-column align-items-center text-secondary py-4 mb-3 patient-photo-empty">
        <IconPaw size={48} aria-hidden="true" />
        <span>{patient.has_photo && !canShow ? 'Tiene foto' : 'Sin foto'}</span>
      </div>
    );
  }

  return (
    <Card title="Foto">
      {picture}
      {alert !== null ? <Alert>{alert}</Alert> : null}
      {canUpload ? (
        <form
          className="patient-photo-form"
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
            name="photo"
            label={patient.has_photo ? 'Cambiar la foto' : 'Elegir una foto'}
            accept={PHOTO_ACCEPT}
            hint="JPG, PNG o WEBP, hasta 5 MB."
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

              void photoFileError(chosen).then(
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
                    setFieldError(PHOTO_MESSAGES.notAnImage);
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
                className="patient-photo-upload"
                loading={upload.isPending}
                icon={<IconUpload size={18} className="me-2" aria-hidden="true" />}
              >
                Subir la foto
              </Button>
              <Button variant="secondary" disabled={busy} onClick={clearFile}>
                Cancelar
              </Button>
            </div>
          ) : null}
        </form>
      ) : null}
      {canDelete && patient.has_photo && file === null ? (
        <Button
          variant="link"
          className="text-danger px-0 patient-photo-delete"
          disabled={busy}
          icon={<IconTrash size={18} className="me-2" aria-hidden="true" />}
          onClick={() => {
            setDeleteError(null);
            setConfirmingDelete(true);
          }}
        >
          Quitar la foto
        </Button>
      ) : null}
      <ConfirmDialog
        open={confirmingDelete}
        title="Quitar la foto"
        confirmLabel="Quitar la foto"
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
        ¿Querés quitar la foto de <strong>{patient.name}</strong>? No se puede deshacer.
      </ConfirmDialog>
    </Card>
  );
}
