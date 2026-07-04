import { createContext, useContext, useEffect, useMemo, useState } from 'react'
import { clearStoredSession, getStoredToken, getStoredUser, storeSession } from '@/utils/auth'
import { loginAdmin, logoutAdmin, verifyAdminSession } from '@/services/authApi'

const AuthContext = createContext(null)

export function AuthProvider({ children }) {
  const [user, setUser] = useState(null)
  const [token, setToken] = useState(null)
  const [initializing, setInitializing] = useState(true)

  useEffect(() => {
    let cancelled = false

    async function restoreSession() {
      const storedToken = getStoredToken()
      const storedUser = getStoredUser()

      if (!storedToken || !storedUser) {
        clearStoredSession()
        if (!cancelled) setInitializing(false)
        return
      }

      const verifiedUser = await verifyAdminSession(storedToken)

      if (cancelled) return

      if (verifiedUser) {
        setUser(verifiedUser)
        setToken(storedToken)
        storeSession({ user: verifiedUser, token: storedToken, rememberMe: localStorage.getItem('jebal_admin_storage_mode') === 'local' })
      } else {
        clearStoredSession()
        setUser(null)
        setToken(null)
      }

      setInitializing(false)
    }

    restoreSession()

    return () => {
      cancelled = true
    }
  }, [])

  const login = async ({ email, password, rememberMe }) => {
    const payload = await loginAdmin({ email, password })
    const nextUser = payload.data.user
    const nextToken = payload.data.token

    storeSession({ user: nextUser, token: nextToken, rememberMe })
    setUser(nextUser)
    setToken(nextToken)

    return nextUser
  }

  const logout = async () => {
    const currentToken = token || getStoredToken()
    clearStoredSession()
    setUser(null)
    setToken(null)
    await logoutAdmin(currentToken)
  }

  const value = useMemo(
    () => ({
      user,
      token,
      initializing,
      isAuthenticated: Boolean(user && token),
      login,
      logout,
    }),
    [user, token, initializing]
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
