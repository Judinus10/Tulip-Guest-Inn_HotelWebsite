import { createContext, useCallback, useContext, useMemo, useRef, useState } from 'react';
import { AlertCircle, AlertTriangle, CheckCircle2, Info, X } from 'lucide-react';

type ToastType = 'success' | 'error' | 'warning' | 'info';
type ToastInput = { message: string; type?: ToastType; title?: string; duration?: number };
type ToastItem = Required<Pick<ToastInput, 'message' | 'type'>> & { id: number; title?: string };
type ToastApi = {
  show: (input: string | ToastInput, options?: Omit<ToastInput, 'message'>) => number | null;
  dismiss: (id: number) => void;
  success: (message: string, options?: Omit<ToastInput, 'message' | 'type'>) => number | null;
  error: (message: string, options?: Omit<ToastInput, 'message' | 'type'>) => number | null;
  warning: (message: string, options?: Omit<ToastInput, 'message' | 'type'>) => number | null;
  info: (message: string, options?: Omit<ToastInput, 'message' | 'type'>) => number | null;
};

const ToastContext = createContext<ToastApi | null>(null);
const designs = {
  success: { icon: CheckCircle2, accent: 'border-emerald-500', iconClass: 'text-emerald-600', title: 'Success' },
  error: { icon: AlertCircle, accent: 'border-red-500', iconClass: 'text-red-600', title: 'Something needs attention' },
  warning: { icon: AlertTriangle, accent: 'border-amber-500', iconClass: 'text-amber-600', title: 'Please note' },
  info: { icon: Info, accent: 'border-sky-500', iconClass: 'text-sky-600', title: 'Information' },
};
const durations: Record<ToastType, number> = { success: 5000, error: 7000, warning: 7000, info: 5500 };

export function ToastProvider({ children }: { children: React.ReactNode }) {
  const [toasts, setToasts] = useState<ToastItem[]>([]);
  const nextId = useRef(1);
  const timers = useRef(new Map<number, number>());

  const dismiss = useCallback((id: number) => {
    const timer = timers.current.get(id);
    if (timer) window.clearTimeout(timer);
    timers.current.delete(id);
    setToasts((current) => current.filter((toast) => toast.id !== id));
  }, []);

  const show = useCallback((input: string | ToastInput, options: Omit<ToastInput, 'message'> = {}) => {
    const toast: ToastInput = typeof input === 'string' ? { message: input, ...options } : input;
    if (!toast.message) return null;
    const type: ToastType = toast.type && toast.type in designs ? toast.type : 'info';
    const id = nextId.current++;
    setToasts((current) => {
      if (current.some((item) => item.type === type && item.message === toast.message)) return current;
      return [...current.slice(-3), { id, type, title: toast.title, message: toast.message }];
    });
    const duration = toast.duration ?? durations[type];
    if (duration > 0) timers.current.set(id, window.setTimeout(() => dismiss(id), duration));
    return id;
  }, [dismiss]);

  const api = useMemo<ToastApi>(() => ({
    show,
    dismiss,
    success: (message, options) => show(message, { ...options, type: 'success' }),
    error: (message, options) => show(message, { ...options, type: 'error' }),
    warning: (message, options) => show(message, { ...options, type: 'warning' }),
    info: (message, options) => show(message, { ...options, type: 'info' }),
  }), [dismiss, show]);

  return <ToastContext.Provider value={api}>
    {children}
    <div className="pointer-events-none fixed right-4 top-4 z-[1000] flex w-[calc(100%-2rem)] max-w-sm flex-col gap-3 sm:right-6 sm:top-6" aria-live="polite">
      {toasts.map((toast) => {
        const design = designs[toast.type];
        const Icon = design.icon;
        return <div key={toast.id} role={toast.type === 'error' ? 'alert' : 'status'} className={`pointer-events-auto border-l-4 ${design.accent} bg-white p-4 shadow-luxury ring-1 ring-black/5`}>
          <div className="flex items-start gap-3">
            <Icon size={20} className={`mt-0.5 shrink-0 ${design.iconClass}`} />
            <div className="min-w-0 flex-1"><p className="text-sm font-semibold text-dark">{toast.title || design.title}</p><p className="mt-1 whitespace-pre-line text-sm leading-5 text-gray-500">{toast.message}</p></div>
            <button type="button" onClick={() => dismiss(toast.id)} className="shrink-0 p-1 text-gray-400 hover:text-dark" aria-label="Dismiss message"><X size={17} /></button>
          </div>
        </div>;
      })}
    </div>
  </ToastContext.Provider>;
}

export function useToast() {
  const value = useContext(ToastContext);
  if (!value) throw new Error('useToast must be used inside ToastProvider');
  return value;
}
