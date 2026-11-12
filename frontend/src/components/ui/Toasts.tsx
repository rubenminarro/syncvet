import { useCallback, useMemo, useRef, useState, type ReactNode } from 'react';
import { ToastContext, type ToastTone } from './toast-context';

interface Toast {
  id: number;
  tone: ToastTone;
  message: string;
}

const DURATION_MS = 6000;

/** Avisos (toasts) propios, sin el JS de Tabler: se cierran solos o con la cruz. */
export function ToastProvider({ children }: { children: ReactNode }) {
  const [toasts, setToasts] = useState<Toast[]>([]);
  const nextId = useRef(1);

  const dismiss = useCallback((id: number) => {
    setToasts((current) => current.filter((toast) => toast.id !== id));
  }, []);

  const show = useCallback(
    (message: string, tone: ToastTone = 'danger') => {
      const id = nextId.current++;
      setToasts((current) => [...current, { id, tone, message }]);
      window.setTimeout(() => {
        dismiss(id);
      }, DURATION_MS);
    },
    [dismiss],
  );

  const value = useMemo(() => ({ show }), [show]);

  return (
    <ToastContext value={value}>
      {children}
      <div className="toast-container position-fixed bottom-0 end-0 p-3" aria-live="polite">
        {toasts.map((toast) => (
          <div key={toast.id} className={`toast show border-${toast.tone}`} role="status">
            <div className="toast-body d-flex align-items-start">
              <span className="flex-fill">{toast.message}</span>
              <button
                type="button"
                className="btn-close ms-2"
                aria-label="Cerrar"
                onClick={() => {
                  dismiss(toast.id);
                }}
              />
            </div>
          </div>
        ))}
      </div>
    </ToastContext>
  );
}
