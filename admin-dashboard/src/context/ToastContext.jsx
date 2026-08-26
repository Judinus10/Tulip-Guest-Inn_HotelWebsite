import { createContext, useCallback, useContext, useEffect, useMemo, useRef, useState } from 'react'
import { AlertCircle, AlertTriangle, CheckCircle2, Info, X } from 'lucide-react'
import { getUserFriendlyError, TOAST_EVENT } from '@/utils/notifications'

const ToastContext = createContext(null)
const icons = { success: CheckCircle2, error: AlertCircle, warning: AlertTriangle, info: Info }
const tones = {
  success: 'border-emerald-200 bg-emerald-50 text-emerald-900',
  error: 'border-red-200 bg-red-50 text-red-900',
  warning: 'border-amber-200 bg-amber-50 text-amber-900',
  info: 'border-blue-200 bg-blue-50 text-blue-900',
}

function normalizeToast(value) {
  if (!value) return null
  if (typeof value === 'string') {
    const looksLikeError = /^(unable|failed|could not|cannot|error|invalid|please check)/i.test(value)
    return { type: looksLikeError ? 'error' : 'success', message: value }
  }
  const type = value.type || (value.title?.toLowerCase().includes('error') ? 'error' : 'success')
  const message = value.message || value.title
  if (!message) return null
  return { ...value, type, message }
}

export function ToastProvider({ children }) {
  const [toasts, setToasts] = useState([])
  const timers = useRef(new Map())

  const dismiss = useCallback((id) => {
    window.clearTimeout(timers.current.get(id))
    timers.current.delete(id)
    setToasts((items) => items.filter((item) => item.id !== id))
  }, [])

  const showToast = useCallback((value) => {
    const toast = normalizeToast(value)
    if (!toast) return null
    const id = `${Date.now()}-${Math.random().toString(16).slice(2)}`
    setToasts((items) => [...items.slice(-3), { id, ...toast }])
    timers.current.set(id, window.setTimeout(() => dismiss(id), toast.duration || 4500))
    return id
  }, [dismiss])

  useEffect(() => {
    const handleToast = (event) => showToast(event.detail)
    window.addEventListener(TOAST_EVENT, handleToast)
    const activeTimers = timers.current
    return () => {
      window.removeEventListener(TOAST_EVENT, handleToast)
      activeTimers.forEach((timer) => window.clearTimeout(timer))
    }
  }, [showToast])

  const value = useMemo(() => ({
    showToast,
    dismiss,
    success: (message, options) => showToast({ type: 'success', message, ...options }),
    error: (error, options) => showToast({ type: 'error', message: getUserFriendlyError(error), ...options }),
    warning: (message, options) => showToast({ type: 'warning', message, ...options }),
    info: (message, options) => showToast({ type: 'info', message, ...options }),
  }), [dismiss, showToast])

  return (
    <ToastContext.Provider value={value}>
      {children}
      <div className="pointer-events-none fixed right-4 top-4 z-[100] flex w-[min(24rem,calc(100vw-2rem))] flex-col gap-3" aria-live="polite">
        {toasts.map((toast) => {
          const Icon = icons[toast.type] || Info
          return (
            <div key={toast.id} role={toast.type === 'error' ? 'alert' : 'status'} className={`pointer-events-auto flex items-start gap-3 rounded-xl border px-4 py-3 shadow-lg ${tones[toast.type] || tones.info}`}>
              <Icon className="mt-0.5 h-5 w-5 shrink-0" />
              <div className="min-w-0 flex-1">
                {toast.title && <p className="font-semibold">{toast.title}</p>}
                <p className="text-sm leading-5">{toast.message}</p>
              </div>
              <button type="button" onClick={() => dismiss(toast.id)} className="rounded p-0.5 opacity-60 transition hover:opacity-100" aria-label="Dismiss notification">
                <X className="h-4 w-4" />
              </button>
            </div>
          )
        })}
      </div>
    </ToastContext.Provider>
  )
}

export function useToast() {
  const context = useContext(ToastContext)
  if (!context) throw new Error('useToast must be used inside ToastProvider')
  return context
}

// Compatibility hook lets existing pages use their current setToast calls while
// rendering every notification through the single global viewport.
export function useToastState(emptyValue = null) {
  const { showToast } = useToast()
  const setToast = useCallback((value) => {
    const resolved = typeof value === 'function' ? value(null) : value
    showToast(resolved)
  }, [showToast])
  return [emptyValue, setToast]
}
