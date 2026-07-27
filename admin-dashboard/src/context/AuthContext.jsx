import { createContext, useContext, useEffect, useMemo, useState } from 'react'
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
      const verifiedUser = await verifyAdminSession()

      if (cancelled) return

      setUser(verifiedUser)
      setInitializing(false)
    }

    restoreSession()

    return () => {
      cancelled = true
    }
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
    }),
    [user, initializing]
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
