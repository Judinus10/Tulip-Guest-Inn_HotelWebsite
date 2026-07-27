const LEGACY_AUTH_KEYS = [
  'jebal_admin_user',
  'jebal_admin_token',
  'jebal_admin_storage_mode',
]

export function clearLegacyAuthStorage() {
  try {
    LEGACY_AUTH_KEYS.forEach((key) => {
      localStorage.removeItem(key)
      sessionStorage.removeItem(key)
    })
  } catch {
    // Storage may be unavailable in hardened/private browser contexts.
  }
}

export function getCookie(name) {
  const prefix = `${encodeURIComponent(name)}=`
  const cookie = document.cookie.split('; ').find((item) => item.startsWith(prefix))
  return cookie ? decodeURIComponent(cookie.slice(prefix.length)) : ''
}
