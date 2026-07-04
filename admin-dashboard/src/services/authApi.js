import { API_BASE_URL } from '@/services/apiClient'

async function readJsonResponse(response) {
  const payload = await response.json().catch(() => null)

  if (!response.ok || !payload?.success) {
    throw new Error(payload?.message || 'Request failed. Please try again.')
  }

  return payload
}

export async function loginAdmin({ email, password }) {
  const response = await fetch(`${API_BASE_URL}/auth/login.php`, {
    method: 'POST',
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({ email, password }),
  })

  return readJsonResponse(response)
}

export async function logoutAdmin(token) {
  if (!token) return

  await fetch(`${API_BASE_URL}/auth/logout.php`, {
    method: 'POST',
    headers: {
      Accept: 'application/json',
      Authorization: `Bearer ${token}`,
    },
  }).catch(() => null)
}

export async function verifyAdminSession(token) {
  if (!token) {
    return null
  }

  const response = await fetch(`${API_BASE_URL}/auth/me.php`, {
    method: 'GET',
    headers: {
      Accept: 'application/json',
      Authorization: `Bearer ${token}`,
    },
  })

  const payload = await response.json().catch(() => null)

  if (!response.ok || !payload?.success) {
    return null
  }

  return payload.data?.user || null
}
