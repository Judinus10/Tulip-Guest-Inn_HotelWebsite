export const TOAST_EVENT = 'tulip:toast'

const unsafeTechnicalMessage = /(sqlstate|fatal error|stack trace|unexpected token|<br\s*\/?|\/var\/www|xampp\\htdocs)/i

export function getUserFriendlyError(error, fallback = 'Something went wrong. Please try again.') {
  const status = Number(error?.status || error?.response?.status || 0)
  const rawMessage = String(error?.message || '').trim()

  if (typeof navigator !== 'undefined' && navigator.onLine === false) {
    return 'You appear to be offline. Check your internet connection and try again.'
  }
  if (error?.name === 'AbortError') return 'The request took too long. Please try again.'
  if (status === 401) return 'Your session has expired. Please sign in again.'
  if (status === 403) return 'You do not have permission to perform this action.'
  if (status === 404) return 'The requested record could not be found.'
  if (status === 409) return rawMessage || 'This change conflicts with the latest saved data. Refresh and try again.'
  if (status === 413) return 'The selected file is too large. Choose a smaller file and try again.'
  if (status === 422) return rawMessage || 'Please check the entered details and try again.'
  if (status === 429) return 'Too many requests were sent. Please wait a moment and try again.'
  if (status >= 500) return 'The server could not complete the request. Please try again shortly.'
  if (error instanceof TypeError && /fetch|network|load/i.test(rawMessage)) {
    return 'Unable to connect to the server. Check your connection and try again.'
  }
  if (rawMessage && !unsafeTechnicalMessage.test(rawMessage)) return rawMessage
  return fallback
}

export function notify(type, message, options = {}) {
  const cleanMessage = String(message || '').trim()
  if (!cleanMessage || typeof window === 'undefined') return
  window.dispatchEvent(new CustomEvent(TOAST_EVENT, {
    detail: { type, message: cleanMessage, ...options },
  }))
}

export const notifySuccess = (message, options) => notify('success', message, options)
export const notifyError = (error, options) => notify('error', getUserFriendlyError(error), options)
export const notifyWarning = (message, options) => notify('warning', message, options)
export const notifyInfo = (message, options) => notify('info', message, options)
