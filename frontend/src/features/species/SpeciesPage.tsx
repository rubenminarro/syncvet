import { IconChevronDown, IconChevronRight, IconPlus } from '@tabler/icons-react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useRef, useState } from 'react';
import { Alert, Button, Card, Loading, useToast } from '../../components/ui';
import { errorTreatment, isApiError, MESSAGES, userMessage } from '../../lib/api/errors';
import type { ApiSuccess } from '../../lib/api/types';
import { PERMISSIONS } from '../../lib/auth/permissions';
import { useCan } from '../auth/permissions';
import { speciesKeys } from '../patients/api';
import type { Breed, Species } from '../patients/types';
import {
  afterCatalogChanged,
  catalogQueryOptions,
  createBreed,
  createSpecies,
  deleteBreed,
  deleteSpecies,
  updateBreed,
  updateSpecies,
} from './api';
import { DeleteDialog, NameDialog, type CatalogItem } from './CatalogDialogs';

type Dialog =
  | { type: 'new-species' }
  | { type: 'new-breed'; species: Species }
  | { type: 'rename'; target: CatalogItem }
  | { type: 'delete'; target: CatalogItem };

function save(
  target: CatalogItem,
  payload: { name?: string; is_active?: boolean },
): Promise<ApiSuccess<Species | Breed>> {
  return target.kind === 'species'
    ? updateSpecies(target.item.id, payload)
    : updateBreed(target.item, payload);
}

/** "Común" (del catálogo compartido, `is_global`) o "Propia" (de la clínica), e "Inactivada" (el test de voseo toma "Inactiva" por el imperativo de tú). */
function Badges({ item }: { item: Species | Breed }) {
  return (
    <>
      {item.is_global ? (
        <span className="badge bg-azure-lt catalog-global">Común</span>
      ) : (
        <span className="badge bg-green-lt catalog-own">Propia</span>
      )}
      {item.is_active ? null : (
        <span className="badge bg-secondary-lt catalog-inactive">Inactivada</span>
      )}
    </>
  );
}

/**
 * /especies (1.5): las especies con sus razas, en una lista que se despliega. Quien administra el catálogo (administrador y
 * veterinario: `species.store`, `.update` y `.destroy`) ve también las inactivas y, solo en las propias, renombrar, activar o
 * inactivar y eliminar; "Nueva especie" y "Nueva raza" (también en una especie común). El recepcionista solo lista las activas.
 */
export function SpeciesPage() {
  const can = useCan();
  const queryClient = useQueryClient();
  const toast = useToast();
  const canStore = can(PERMISSIONS.speciesStore);
  const canUpdate = can(PERMISSIONS.speciesUpdate);
  const canDestroy = can(PERMISSIONS.speciesDestroy);
  const manages = canStore || canUpdate || canDestroy;
  const catalog = useQuery(catalogQueryOptions(manages));
  const [expanded, setExpanded] = useState<ReadonlySet<number>>(new Set());
  const [dialog, setDialog] = useState<Dialog | null>(null);
  const toggling = useRef(false);
  const close = () => {
    setDialog(null);
  };

  // Activar o inactivar no pide confirmación (se deshace con el mismo botón); un solo pedido a la vez.
  const toggle = useMutation({
    mutationFn: (target: CatalogItem) => save(target, { is_active: !target.item.is_active }),
    onSettled: () => {
      toggling.current = false;
    },
    onSuccess: (response) => {
      afterCatalogChanged(queryClient);
      toast.show(response.message, 'success');
    },
    onError: (error) => {
      if (isApiError(error) && errorTreatment(error.errorCode) === 'not-found') {
        void queryClient.invalidateQueries({ queryKey: speciesKeys.all });
      }

      // CATALOG_GLOBAL_NOT_EDITABLE es un código de negocio, no FORBIDDEN: su mensaje, sin ir a /sin-permiso.
      toast.show(isApiError(error) ? userMessage(error) : MESSAGES.programming, 'danger');
    },
  });

  const actions = (target: CatalogItem) => {
    if (target.item.is_global || !(canUpdate || canDestroy)) {
      return null;
    }

    const prefix = target.kind === 'species' ? 'species' : 'breed';

    return (
      <div className="d-flex flex-wrap gap-1 ms-auto">
        {canUpdate ? (
          <>
            <Button
              variant="secondary"
              className={`btn-sm ${prefix}-rename`}
              onClick={() => {
                setDialog({ type: 'rename', target });
              }}
            >
              Renombrar
            </Button>
            <Button
              variant="secondary"
              className={`btn-sm ${prefix}-toggle-active`}
              disabled={toggle.isPending}
              onClick={() => {
                if (!toggling.current) {
                  toggling.current = true;
                  toggle.mutate(target);
                }
              }}
            >
              {target.item.is_active ? 'Inactivar' : 'Activar'}
            </Button>
          </>
        ) : null}
        {canDestroy ? (
          <Button
            variant="danger"
            className={`btn-sm ${prefix}-delete`}
            onClick={() => {
              setDialog({ type: 'delete', target });
            }}
          >
            Eliminar
          </Button>
        ) : null}
      </div>
    );
  };

  let body;

  if (catalog.isPending) {
    body = <Loading label="Cargando las especies…" />;
  } else if (catalog.isError) {
    body = (
      <div className="card-body">
        <Alert>
          {isApiError(catalog.error) ? userMessage(catalog.error) : MESSAGES.programming}
        </Alert>
      </div>
    );
  } else if (catalog.data.length === 0) {
    body = <div className="card-body text-secondary">No hay especies cargadas.</div>;
  } else {
    body = (
      <div className="list-group list-group-flush species-list">
        {catalog.data.map((species) => {
          const open = expanded.has(species.id);
          const breedsId = `species-${String(species.id)}-breeds`;

          return (
            <div
              key={species.id}
              className="list-group-item species-item"
              data-species={species.name}
            >
              <div className="d-flex flex-wrap align-items-center gap-2">
                <button
                  type="button"
                  className="btn btn-link px-0 text-reset fw-bold species-expand"
                  aria-expanded={open}
                  aria-controls={breedsId}
                  onClick={() => {
                    setExpanded((current) => {
                      const next = new Set(current);

                      if (open) {
                        next.delete(species.id);
                      } else {
                        next.add(species.id);
                      }

                      return next;
                    });
                  }}
                >
                  {open ? (
                    <IconChevronDown size={18} className="me-1" aria-hidden="true" />
                  ) : (
                    <IconChevronRight size={18} className="me-1" aria-hidden="true" />
                  )}
                  {species.name}
                </button>
                <Badges item={species} />
                <span className="text-secondary small">
                  {species.breeds.length === 1
                    ? '1 raza'
                    : `${String(species.breeds.length)} razas`}
                </span>
                {actions({ kind: 'species', item: species })}
              </div>
              {open ? (
                <div id={breedsId} className="ms-4 mt-2 breed-list">
                  {species.breeds.length === 0 ? (
                    <p className="text-secondary mb-2">Sin razas.</p>
                  ) : (
                    <ul className="list-unstyled mb-2">
                      {species.breeds.map((breed) => (
                        <li
                          key={breed.id}
                          className="d-flex flex-wrap align-items-center gap-2 py-1 breed-item"
                          data-breed={breed.name}
                        >
                          <span>{breed.name}</span>
                          <Badges item={breed} />
                          {actions({ kind: 'breed', item: breed })}
                        </li>
                      ))}
                    </ul>
                  )}
                  {canStore ? (
                    <Button
                      variant="link"
                      className="px-0 breed-new"
                      icon={<IconPlus size={16} className="me-1" aria-hidden="true" />}
                      onClick={() => {
                        setDialog({ type: 'new-breed', species });
                      }}
                    >
                      Nueva raza
                    </Button>
                  ) : null}
                </div>
              ) : null}
            </div>
          );
        })}
      </div>
    );
  }

  return (
    <>
      <div className="page-header mb-3">
        <div className="row g-2 align-items-center">
          <div className="col">
            <h2 className="page-title">Especies y razas</h2>
          </div>
          {canStore ? (
            <div className="col-auto">
              <Button
                className="species-new"
                icon={<IconPlus size={18} className="me-2" aria-hidden="true" />}
                onClick={() => {
                  setDialog({ type: 'new-species' });
                }}
              >
                Nueva especie
              </Button>
            </div>
          ) : null}
        </div>
      </div>
      <Card flush>{body}</Card>
      {dialog?.type === 'new-species' ? (
        <NameDialog
          kind="species"
          title="Nueva especie"
          confirmLabel="Crear la especie"
          save={(name) => createSpecies({ name })}
          onClose={close}
        />
      ) : null}
      {dialog?.type === 'new-breed' ? (
        <NameDialog
          kind="breed"
          title="Nueva raza"
          confirmLabel="Crear la raza"
          intro={
            <>
              La raza nueva va en <strong>{dialog.species.name}</strong>.
            </>
          }
          save={(name) => createBreed(dialog.species.id, { name })}
          onClose={close}
        />
      ) : null}
      {dialog?.type === 'rename' ? (
        <NameDialog
          kind={dialog.target.kind}
          title={dialog.target.kind === 'species' ? 'Renombrar la especie' : 'Renombrar la raza'}
          initialName={dialog.target.item.name}
          confirmLabel="Guardar el nombre"
          save={(name) => save(dialog.target, { name })}
          onClose={close}
        />
      ) : null}
      {dialog?.type === 'delete' ? (
        <DeleteDialog
          target={dialog.target}
          canInactivate={canUpdate}
          remove={() =>
            dialog.target.kind === 'species'
              ? deleteSpecies(dialog.target.item.id)
              : deleteBreed(dialog.target.item)
          }
          inactivate={() => save(dialog.target, { is_active: false })}
          onClose={close}
        />
      ) : null}
    </>
  );
}
