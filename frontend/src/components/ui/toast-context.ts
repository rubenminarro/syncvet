import { createContext, use } from 'react';

export type ToastTone = 'danger' | 'warning' | 'info' | 'success';

export interface ToastApi {
  show: (message: string, tone?: ToastTone) => void;
}

export const ToastContext = createContext<ToastApi | null>(null);

export function useToast(): ToastApi {
  const context = use(ToastContext);

  if (context === null) {
    throw new Error('useToast necesita un ToastProvider.');
  }

  return context;
}
