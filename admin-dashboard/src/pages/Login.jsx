import { useEffect, useState } from 'react'
import { Navigate, useLocation, useNavigate } from 'react-router-dom'
import { Eye, EyeOff, Lock, Mail } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Input, Label } from '@/components/ui/input'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { useAuth } from '@/context/AuthContext'
import { useToastState } from '@/context/ToastContext'
import logo from '@/assets/logo.jpeg'
import companyLogo from '@/assets/company_logo.png'

const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/

export default function Login() {
  const navigate = useNavigate()
  const location = useLocation()
  const { initializing, isAuthenticated, login } = useAuth()

  const [email, setEmail] = useState('admin@jebalhomes.com')
  const [password, setPassword] = useState('')
  const [rememberMe, setRememberMe] = useState(true)
  const [showPassword, setShowPassword] = useState(false)
  const [errors, setErrors] = useState({})
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [toast, setToast] = useToastState('')

  const redirectTo = location.state?.from?.pathname || '/dashboard'

  useEffect(() => {
    if (!toast) return undefined

    const timer = window.setTimeout(() => setToast(''), 2500)
    return () => window.clearTimeout(timer)
  }, [toast])

  if (!initializing && isAuthenticated) {
    return <Navigate to="/dashboard" replace />
  }

  const validateForm = () => {
    const nextErrors = {}
    const cleanEmail = email.trim()

    if (!cleanEmail) {
      nextErrors.email = 'Email is required.'
    } else if (!EMAIL_PATTERN.test(cleanEmail)) {
      nextErrors.email = 'Enter a valid email address.'
    }

    if (!password.trim()) {
      nextErrors.password = 'Password is required.'
    }

    setErrors(nextErrors)
    return Object.keys(nextErrors).length === 0
  }

  const handleSubmit = async (e) => {
    e.preventDefault()

    if (!validateForm()) return

    setIsSubmitting(true)
    setErrors({})

    try {
      await login({ email: email.trim(), password, rememberMe })
      setToast('Login successful. Redirecting...')
      navigate(redirectTo, { replace: true })
    } catch (error) {
      setErrors({ form: error.message || 'Invalid login details.' })
    } finally {
      setIsSubmitting(false)
    }
  }

  return (
    <div className="flex min-h-screen bg-slate-50">
      {toast && (
        <div className="fixed right-5 top-5 z-50 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 shadow-lg">
          {toast}
        </div>
      )}

      <div className="relative hidden w-1/2 overflow-hidden bg-slate-900 p-12 text-white lg:flex lg:flex-col lg:justify-between">
        <div className="absolute inset-0 bg-[radial-gradient(circle_at_top_right,_rgba(96,165,250,0.22),_transparent_35%),linear-gradient(135deg,_#0F172A_0%,_#1E3A8A_55%,_#0F172A_100%)]" />
        <div className="absolute -right-24 top-24 h-72 w-72 rounded-full bg-blue-400/20 blur-3xl" />
        <div className="absolute -bottom-24 -left-24 h-72 w-72 rounded-full bg-blue-900/40 blur-3xl" />

        <div className="relative z-10 flex items-center gap-4">
          <img
            src={logo}
            alt="Tulip Guest Inn"
            className="h-16 w-auto rounded-xl bg-white p-2 shadow-lg"
          />

          <div>
            <p className="text-2xl font-bold tracking-tight">
              Tulip Guest Inn
            </p>
            <p className="text-xs font-medium uppercase tracking-[0.25em] text-blue-200">
              Admin Dashboard
            </p>
          </div>
        </div>

        <div className="relative z-10 max-w-lg">
          <p className="mb-4 inline-flex rounded-full border border-white/10 bg-white/10 px-3 py-1 text-xs font-medium text-blue-100 backdrop-blur">
            Secure administrator access
          </p>
          <h2 className="text-4xl font-bold leading-tight tracking-tight">
            Manage bookings, rooms, payments, and guests from one clean dashboard.
          </h2>
          <p className="mt-5 max-w-md text-sm leading-6 text-blue-100/80">
            A modern hotel administration experience for daily guest house operations.
          </p>
        </div>

        <div className="relative z-10 flex items-end justify-between gap-6">
          <p className="text-xs text-blue-100/50">
            © 2026 Tulip Guest Inn. Admin control panel.
          </p>

          <div className="flex translate-y-10 flex-col items-end">
            <span className="text-[11px] uppercase tracking-[0.35em] text-blue-200/70">
              Powered by
            </span>

            <img
              src={companyLogo}
              alt="CompylX"
              className="mt-3 h-14 w-auto object-contain"
            />
          </div>
        </div>
      </div>

      <div className="flex flex-1 items-center justify-center p-6">
        <Card className="w-full max-w-md border-slate-200 shadow-xl shadow-slate-200/70">
          <CardHeader className="text-center">
            <div className="mx-auto mb-4 lg:hidden">
              <img
                src={logo}
                alt="Tulip Guest Inn"
                className="h-20 w-auto rounded-xl bg-white p-2 shadow-lg"
              />
            </div>

            <CardTitle className="text-2xl font-bold tracking-tight">Welcome back</CardTitle>
            <CardDescription>Sign in to continue to your dashboard</CardDescription>
          </CardHeader>

          <CardContent>
            <form onSubmit={handleSubmit} className="space-y-4" noValidate>
              <div className="space-y-2">
                <Label htmlFor="email">Email</Label>
                <div className="relative">
                  <Mail className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-500" />
                  <Input
                    id="email"
                    type="email"
                    value={email}
                    onChange={(e) => {
                      setEmail(e.target.value)
                      if (errors.email) setErrors((prev) => ({ ...prev, email: '' }))
                    }}
                    className="pl-9"
                    placeholder="admin@jebalhomes.com"
                    autoComplete="email"
                    aria-invalid={Boolean(errors.email)}
                  />
                </div>
                {errors.email && <p className="text-xs font-medium text-red-600">{errors.email}</p>}
              </div>

              <div className="space-y-2">
                <Label htmlFor="password">Password</Label>
                <div className="relative">
                  <Lock className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-500" />
                  <Input
                    id="password"
                    type={showPassword ? 'text' : 'password'}
                    value={password}
                    onChange={(e) => {
                      setPassword(e.target.value)
                      if (errors.password) setErrors((prev) => ({ ...prev, password: '' }))
                    }}
                    className="pl-9 pr-10"
                    placeholder="Enter your password"
                    autoComplete="current-password"
                    aria-invalid={Boolean(errors.password)}
                  />
                  <button
                    type="button"
                    onClick={() => setShowPassword((prev) => !prev)}
                    className="absolute right-3 top-1/2 -translate-y-1/2 rounded-md text-slate-500 transition-colors hover:text-slate-900 focus:outline-none focus:ring-2 focus:ring-blue-500/30"
                    aria-label={showPassword ? 'Hide password' : 'Show password'}
                  >
                    {showPassword ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                  </button>
                </div>
                {errors.password && <p className="text-xs font-medium text-red-600">{errors.password}</p>}
              </div>

              <div className="flex items-center justify-between gap-3">
                <label className="flex cursor-pointer items-center gap-2 text-sm text-slate-600">
                  <input
                    type="checkbox"
                    checked={rememberMe}
                    onChange={(e) => setRememberMe(e.target.checked)}
                    className="h-4 w-4 rounded border-slate-300 text-blue-700 focus:ring-blue-600"
                  />
                  Remember me
                </label>
                <button type="button" className="text-sm font-medium text-blue-700 hover:text-blue-800">
                  Forgot password?
                </button>
              </div>

              <Button type="submit" className="w-full" disabled={isSubmitting}>
                {isSubmitting ? (
                  <>
                    <span className="h-4 w-4 animate-spin rounded-full border-2 border-white/40 border-t-white" />
                    Signing in...
                  </>
                ) : (
                  'Sign In'
                )}
              </Button>

              {errors.form && (
                <p className="text-center text-xs font-medium text-red-600">{errors.form}</p>
              )}

              <p className="text-center text-xs text-slate-500">
                Use your assigned Tulip Guest Inn administrator credentials.
              </p>
            </form>
          </CardContent>
        </Card>
      </div>
    </div>
  )
}
