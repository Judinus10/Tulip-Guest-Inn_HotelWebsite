import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react'
import { clearLegacyAuthStorage, getCookie } from '@/utils/auth'
import { loginAdmin, logoutAdmin, verifyAdminSession } from '@/services/authApi'

const AuthContext = createContext(null)

export function AuthProvider({ children }) {
  const [user, setUser] = useState(null)
  const [initializing, setInitializing] = useState(true)

  useEffect(() => {
    let cancelled = false

    async function restoreSession() {
      clearLegacyAuthStorage()
      let verifiedUser = null
      try { verifiedUser = await verifyAdminSession() } catch { /* Login stays available if the API is unreachable. */ }

      if (cancelled) return

      setUser(verifiedUser)
      setInitializing(false)
    }

    restoreSession()

    return () => {
      cancelled = true
    }
  }, [])

  const updateUser = useCallback((patch) => {
    setUser(current => current ? { ...current, ...patch } : current)
    try { localStorage.setItem('tulip-account-updated', String(Date.now())) } catch { /* Storage can be disabled. */ }
  }, [])

  useEffect(() => {
    let active = true
    const refresh = async (event) => {
      if (event.key !== 'tulip-account-updated') return
      try { const current = await verifyAdminSession(); if (active) setUser(current) } catch { /* Retain the last verified profile on network failure. */ }
    }
    window.addEventListener('storage', refresh)
    return () => { active = false; window.removeEventListener('storage', refresh) }
  }, [])

  const login = async ({ email, password, rememberMe }) => {
    const payload = await loginAdmin({ email, password, rememberMe })
    const nextUser = payload.data.user
    setUser(nextUser)
    return nextUser
  }

  const logout = async () => {
    const csrfToken = getCookie('tulip_admin_csrf')
    clearLegacyAuthStorage()
    setUser(null)
    await logoutAdmin(csrfToken)
  }

  const value = useMemo(
    () => ({
      user,
      initializing,
      isAuthenticated: Boolean(user),
      login,
      logout,
      updateUser,
    }),
    [user, initializing, updateUser]
  )

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}

export function useAuth() {
  const context = useContext(AuthContext)

  if (!context) {
    throw new Error('useAuth must be used inside AuthProvider')
  }

  return context
}
