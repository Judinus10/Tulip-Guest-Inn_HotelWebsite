import { clearLegacyAuthStorage, getCookie } from '@/utils/auth'

const localApiBaseUrl = 'http://localhost/HotelWebsite/api'

export const API_BASE_URL = (import.meta.env.VITE_API_BASE_URL || localApiBaseUrl).replace(/\/$/, '')

export function buildApiUrl(path) {
  const normalizedPath = String(path || '').startsWith('/') ? path : `/${path}`
  return `${API_BASE_URL}${normalizedPath}`
}

export async function apiFetch(url, options = {}) {
  const headers = new Headers(options.headers || {})
  const method = String(options.method || 'GET').toUpperCase()

  if (!headers.has('Accept')) {
    headers.set('Accept', 'application/json')
  }

  if (!['GET', 'HEAD', 'OPTIONS'].includes(method)) {
    const csrfToken = getCookie('tulip_admin_csrf')
    if (csrfToken) {
      headers.set('X-CSRF-Token', csrfToken)
    }
  }

  const response = await fetch(url, {
    ...options,
    headers,
    credentials: 'include',
  })

  if (response.status === 401) {
    clearLegacyAuthStorage()
    if (!window.location.pathname.includes('/login')) {
      window.location.href = '/login'
    }
  }

  return response
}

export async function readJsonResponse(response) {
  const payload = await response.json().catch(() => null)

  if (!response.ok || !payload?.success) {
    const error = new Error(payload?.message || 'Request failed. Please try again.')
    error.status = response.status
    error.severity = payload?.severity || (response.status >= 500 ? 'error' : 'warning')
    error.code = payload?.error_code || ''
    throw error
  }

  return payload
}
