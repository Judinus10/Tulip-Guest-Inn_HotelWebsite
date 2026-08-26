import { clearLegacyAuthStorage, getCookie } from '@/utils/auth'
import { getUserFriendlyError } from '@/utils/notifications'

export class ApiError extends Error {
  constructor(message, { status = 0, code = '', fieldErrors = null, cause = null } = {}) {
    super(message)
    this.name = 'ApiError'
    this.status = status
    this.code = code
    this.fieldErrors = fieldErrors
    this.cause = cause
  }
}

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

  let response
  try {
    response = await fetch(url, { ...options, headers, credentials: 'include' })
  } catch (cause) {
    throw new ApiError(getUserFriendlyError(cause, 'Unable to connect to the server. Please try again.'), { cause })
  }

  if (response.status === 401) {
    clearLegacyAuthStorage()
    if (!window.location.pathname.includes('/login')) {
      window.location.href = '/login'
    }
  }

  return response
}

export async function readJsonResponse(response) {
  const contentType = response.headers.get('content-type') || ''
  const payload = contentType.includes('application/json')
    ? await response.json().catch(() => null)
    : null

  if (!response.ok || !payload?.success) {
    const source = new ApiError(payload?.message || 'Request failed.', {
      status: response.status,
      code: payload?.code || '',
      fieldErrors: payload?.errors || null,
    })
    source.message = getUserFriendlyError(source)
    throw source
  }

  return payload
}
