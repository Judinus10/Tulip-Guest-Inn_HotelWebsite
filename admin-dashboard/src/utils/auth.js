export const AUTH_USER_KEY = 'jebal_admin_user'
export const AUTH_TOKEN_KEY = 'jebal_admin_token'
export const AUTH_STORAGE_MODE_KEY = 'jebal_admin_storage_mode'

function getStorage() {
  try {
    const mode = localStorage.getItem(AUTH_STORAGE_MODE_KEY)
    return mode === 'local' ? localStorage : sessionStorage
  } catch {
    return sessionStorage
  }
}

export function getStoredUser() {
  try {
    const rawUser = getStorage().getItem(AUTH_USER_KEY)
    return rawUser ? JSON.parse(rawUser) : null
  } catch {
    return null
  }
}

export function getStoredToken() {
  try {
    return getStorage().getItem(AUTH_TOKEN_KEY)
  } catch {
    return null
  }
}

export function storeSession({ user, token, rememberMe = false }) {
  clearStoredSession()
  const storage = rememberMe ? localStorage : sessionStorage
  storage.setItem(AUTH_USER_KEY, JSON.stringify(user))
  storage.setItem(AUTH_TOKEN_KEY, token)
  localStorage.setItem(AUTH_STORAGE_MODE_KEY, rememberMe ? 'local' : 'session')
}

export function clearStoredSession() {
  try {
    localStorage.removeItem(AUTH_USER_KEY)
    localStorage.removeItem(AUTH_TOKEN_KEY)
    localStorage.removeItem(AUTH_STORAGE_MODE_KEY)
    sessionStorage.removeItem(AUTH_USER_KEY)
    sessionStorage.removeItem(AUTH_TOKEN_KEY)
  } catch {
    // Ignore storage errors.
  }
}

export function isAuthenticated() {
  return Boolean(getStoredUser() && getStoredToken())
}
