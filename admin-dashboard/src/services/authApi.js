import { API_BASE_URL } from '@/services/apiClient'

async function readJsonResponse(response) {
  const payload = await response.json().catch(() => null)

  if (!response.ok || !payload?.success) {
    throw new Error(payload?.message || 'Request failed. Please try again.')
  }

  return payload
}

export async function loginAdmin({ email, password, rememberMe = false }) {
  const response = await fetch(`${API_BASE_URL}/auth/login.php`, {
    method: 'POST',
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
    },
    credentials: 'include',
    body: JSON.stringify({ email, password, remember_me: rememberMe }),
  })

  return readJsonResponse(response)
}

export async function logoutAdmin(csrfToken) {
  return fetch(`${API_BASE_URL}/auth/logout.php`, {
    method: 'POST',
    headers: {
      Accept: 'application/json',
      ...(csrfToken ? { 'X-CSRF-Token': csrfToken } : {}),
    },
    credentials: 'include',
  }).catch(() => null)
}

export async function verifyAdminSession() {
  const response = await fetch(`${API_BASE_URL}/auth/me.php`, {
    method: 'GET',
    headers: {
      Accept: 'application/json',
    },
    credentials: 'include',
  })

  const payload = await response.json().catch(() => null)

  if (!response.ok || !payload?.success) {
    return null
  }

  return payload.data?.user || null
}
