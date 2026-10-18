import { useEffect, useMemo, useState } from 'react'
import { CheckCircle2, KeyRound, Mail, ShieldCheck, X } from 'lucide-react'
import { PageHeader, SectionCard } from '@/components/ui/page-header'
import { Button } from '@/components/ui/button'
import { Input, Label } from '@/components/ui/input'
import { apiFetch, buildApiUrl, readJsonResponse } from '@/services/apiClient'
import { useToastState } from '@/context/ToastContext'
import { useAuth } from '@/context/AuthContext'

function ChangeAdminEmail() {
  const { user } = useAuth()
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [otp, setOtp] = useState('')
  const [token, setToken] = useState('')
  const [busy, setBusy] = useState(false)
  const [message, setMessage] = useState('')
  const [error, setError] = useState('')
  const endpoint = buildApiUrl('/auth/change-email.php')

  const send = async (event) => {
    event.preventDefault()
    setBusy(true); setError(''); setMessage('')
    try {
      const response = await apiFetch(endpoint, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'request', new_email: email, current_password: password }) })
      const result = await readJsonResponse(response)
      setToken(result.data.request_token)
      setMessage('Verification code sent to your new email. It expires in 10 minutes.')
    } catch (err) { setError(err.message || 'Unable to send the code.') }
    finally { setBusy(false) }
  }

  const verify = async (event) => {
    event.preventDefault()
    setBusy(true); setError(''); setMessage('')
    try {
      const response = await apiFetch(endpoint, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'verify', request_token: token, otp, current_password: password }) })
      await readJsonResponse(response)
      setToken(''); setOtp(''); setPassword('')
      setMessage('Email changed. Use the new address next time you sign in. Other sessions were signed out.')
    } catch (err) { setError(err.message || 'Unable to verify the code.') }
    finally { setBusy(false) }
  }

  return <SectionCard>
    <h2 className="text-lg font-semibold text-text-primary">Change Admin Email</h2>
    <p className="mt-1 text-sm text-text-secondary">Current email: {user?.email || 'Admin account'}. Confirm your password and verify the new address.</p>
    <form onSubmit={token ? verify : send} className="mt-5 max-w-2xl space-y-4">
      <div className="space-y-2"><Label htmlFor="admin_new_email">New Email</Label><Input id="admin_new_email" type="email" required maxLength={190} autoComplete="email" value={email} onChange={e => { setEmail(e.target.value); setToken('') }} /></div>
      <div className="space-y-2"><Label htmlFor="admin_email_password">Current Password</Label><Input id="admin_email_password" type="password" required autoComplete="current-password" value={password} onChange={e => setPassword(e.target.value)} /></div>
      {token && <div className="space-y-2"><Label htmlFor="admin_email_code">Code sent to {email}</Label><Input id="admin_email_code" inputMode="numeric" pattern="[0-9]{6}" maxLength={6} required value={otp} onChange={e => setOtp(e.target.value.replace(/\D/g, '').slice(0, 6))} /></div>}
      {error && <p role="alert" className="text-sm text-red-700">{error}</p>}
      {message && <p role="status" className="text-sm text-emerald-700">{message}</p>}
      <Button type="submit" disabled={busy}>{busy ? 'Please wait...' : token ? 'Verify and Change Email' : 'Send Verification Code'}</Button>
      {token && <button type="button" onClick={() => { setToken(''); setOtp('') }} className="ml-4 text-sm text-blue-700">Start again</button>}
    </form>
  </SectionCard>
}

function Toast({ message, type = 'success', onClose }) {
  if (!message) return null

  const tone = type === 'error' ? 'border-red-200 text-red-700' : 'border-emerald-200 text-emerald-700'

  return (
    <div className={`fixed right-4 top-4 z-50 flex max-w-sm items-center gap-3 rounded-xl border bg-white px-4 py-3 text-sm font-medium shadow-lg ${tone}`}>
      <CheckCircle2 className="h-5 w-5" />
      <span>{message}</span>
      <button type="button" onClick={onClose} className="ml-2 text-xs text-slate-400 hover:text-slate-700">
        Close
      </button>
    </div>
  )
}

function FieldWithIcon({ icon: Icon, children }) {
  return (
    <div className="relative">
      <Icon className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
      {children}
    </div>
  )
}

function OtpModal({ email, otp, setOtp, secondsLeft, canResend, loading, error, onClose, onResend, onVerify }) {
  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/55 px-4 backdrop-blur-sm" onMouseDown={onClose}>
      <div
        className="w-full max-w-md rounded-2xl bg-white p-5 shadow-2xl"
        onMouseDown={(event) => event.stopPropagation()}
      >
        <div className="mb-4 flex items-start justify-between gap-4">
          <div className="flex items-start gap-3">
            <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-50 text-primary-600">
              <Mail className="h-5 w-5" />
            </div>
            <div>
              <h2 className="text-base font-semibold text-text-primary">Verify OTP</h2>
              <p className="mt-1 text-sm text-text-secondary">
                Enter the OTP sent to {email || 'the admin email'}.
              </p>
            </div>
          </div>
          <button type="button" onClick={onClose} className="rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-700">
            <X className="h-4 w-4" />
          </button>
        </div>

        <div className="space-y-4">
          <div className="space-y-2">
            <Label htmlFor="password_otp">6-digit OTP</Label>
            <Input
              id="password_otp"
              type="text"
              inputMode="numeric"
              maxLength={6}
              value={otp}
              onChange={(event) => setOtp(event.target.value.replace(/\D/g, '').slice(0, 6))}
              className="text-center text-lg font-semibold tracking-[0.45em]"
              placeholder="000000"
              autoFocus
            />
          </div>

          <div className="rounded-lg bg-slate-50 px-3 py-2 text-sm text-text-secondary">
            {secondsLeft > 0 ? (
              <span>OTP expires in <strong className="text-text-primary">{secondsLeft}s</strong>. Resend will unlock after expiry.</span>
            ) : (
              <span className="font-medium text-amber-700">OTP expired. Click resend to get a new code.</span>
            )}
          </div>

          {error && (
            <p className="rounded-lg bg-red-50 px-3 py-2 text-sm font-medium text-red-700">
              {error}
            </p>
          )}

          <div className="flex items-center justify-between gap-3">
            <Button type="button" variant="outline" onClick={onResend} disabled={!canResend || loading}>
              Resend OTP
            </Button>
            <Button type="button" onClick={onVerify} disabled={loading || otp.length !== 6 || secondsLeft <= 0}>
              <ShieldCheck className="h-4 w-4" />
              {loading ? 'Verifying...' : 'Verify & Change'}
            </Button>
          </div>
        </div>
      </div>
    </div>
  )
}

export default function AdminPassword() {
  const [form, setForm] = useState({
    current_password: '',
    new_password: '',
    confirm_password: '',
  })

  const [error, setError] = useState('')
  const [toast, setToast] = useToastState({ message: '', type: 'success' })
  const [loading, setLoading] = useState(false)
  const [otpModalOpen, setOtpModalOpen] = useState(false)
  const [otp, setOtp] = useState('')
  const [otpError, setOtpError] = useState('')
  const [otpRequest, setOtpRequest] = useState({ token: '', email: '', expiresAt: 0 })
  const [now, setNow] = useState(Date.now())

  const secondsLeft = useMemo(() => Math.max(0, Math.ceil((otpRequest.expiresAt - now) / 1000)), [otpRequest.expiresAt, now])
  const canResend = otpModalOpen && otpRequest.token && secondsLeft <= 0

  useEffect(() => {
    if (!otpModalOpen) return undefined

    const timer = window.setInterval(() => setNow(Date.now()), 1000)
    return () => window.clearInterval(timer)
  }, [otpModalOpen])

  const updateForm = (field, value) => {
    setForm((current) => ({ ...current, [field]: value }))
  }

  const showToast = (message, type = 'success') => {
    setToast({ message, type })
    window.setTimeout(() => setToast({ message: '', type: 'success' }), 2500)
  }

  const validatePasswordForm = () => {
    if (!form.current_password.trim()) {
      return 'Current password is required.'
    }

    if (!form.new_password.trim()) {
      return 'New password is required.'
    }

    if (form.new_password.length < 8) {
      return 'New password must be at least 8 characters.'
    }

    if (form.new_password !== form.confirm_password) {
      return 'New password and confirm password must match.'
    }

    if (form.current_password === form.new_password) {
      return 'New password must be different from current password.'
    }

    return ''
  }

  const startOtpFlow = async (event) => {
    event.preventDefault()
    setError('')
    setOtpError('')

    const validationError = validatePasswordForm()

    if (validationError) {
      setError(validationError)
      return
    }

    try {
      setLoading(true)

      const response = await apiFetch(buildApiUrl('/auth/request-password-otp.php'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ current_password: form.current_password }),
      })

      const payload = await readJsonResponse(response)
      const data = payload.data || {}

      setOtp('')
      setOtpRequest({
        token: data.request_token || '',
        email: data.email || '',
        expiresAt: Date.now() + Number(data.expires_in || 60) * 1000,
      })
      setNow(Date.now())
      setOtpModalOpen(true)
      showToast('OTP sent to admin email.')
    } catch (err) {
      setError(err.message || 'Unable to send OTP right now.')
    } finally {
      setLoading(false)
    }
  }

  const resendOtp = async () => {
    if (!otpRequest.token || secondsLeft > 0) return

    try {
      setLoading(true)
      setOtpError('')

      const response = await apiFetch(buildApiUrl('/auth/resend-password-otp.php'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ request_token: otpRequest.token }),
      })

      const payload = await readJsonResponse(response)
      const data = payload.data || {}

      setOtp('')
      setOtpRequest({
        token: data.request_token || '',
        email: data.email || otpRequest.email || '',
        expiresAt: Date.now() + Number(data.expires_in || 60) * 1000,
      })
      setNow(Date.now())
      showToast('New OTP sent to admin email.')
    } catch (err) {
      setOtpError(err.message || 'Unable to resend OTP right now.')
    } finally {
      setLoading(false)
    }
  }

  const verifyOtpAndChangePassword = async () => {
    setOtpError('')

    if (otp.length !== 6) {
      setOtpError('Enter the 6-digit OTP.')
      return
    }

    const validationError = validatePasswordForm()

    if (validationError) {
      setOtpError(validationError)
      return
    }

    try {
      setLoading(true)

      const response = await apiFetch(buildApiUrl('/auth/verify-password-otp.php'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          request_token: otpRequest.token,
          otp,
          current_password: form.current_password,
          new_password: form.new_password,
          confirm_password: form.confirm_password,
        }),
      })

      await readJsonResponse(response)

      setForm({ current_password: '', new_password: '', confirm_password: '' })
      setOtp('')
      setOtpRequest({ token: '', email: '', expiresAt: 0 })
      setOtpModalOpen(false)
      showToast('Password changed successfully.')
    } catch (err) {
      setOtpError(err.message || 'Unable to change password right now.')
    } finally {
      setLoading(false)
    }
  }

  return (
    <div>
      <Toast
        message={toast.message}
        type={toast.type}
        onClose={() => setToast({ message: '', type: 'success' })}
      />

      <PageHeader
        title="Admin Password"
        description="Change the dashboard administrator password."
      />

      <SectionCard>
        <div className="mb-6 flex items-start gap-3">
          <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-blue-50 text-primary-600">
            <ShieldCheck className="h-5 w-5" />
          </div>
          <div>
            <h2 className="text-lg font-semibold text-text-primary">Change Password</h2>
            <p className="mt-1 text-sm text-text-secondary">
              Enter the password details first. Then verify the 1-minute OTP sent to the admin email.
            </p>
          </div>
        </div>

        <form onSubmit={startOtpFlow} className="max-w-2xl space-y-5">
          <div className="space-y-2">
            <Label htmlFor="current_password">Current Password</Label>
            <FieldWithIcon icon={KeyRound}>
              <Input
                id="current_password"
                type="password"
                autoComplete="current-password"
                value={form.current_password}
                onChange={(event) => updateForm('current_password', event.target.value)}
                className="pl-9"
                placeholder="Enter current password"
              />
            </FieldWithIcon>
          </div>

          <div className="grid gap-4 sm:grid-cols-2">
            <div className="space-y-2">
              <Label htmlFor="new_password">New Password</Label>
              <FieldWithIcon icon={KeyRound}>
                <Input
                  id="new_password"
                  type="password"
                  autoComplete="new-password"
                  value={form.new_password}
                  onChange={(event) => updateForm('new_password', event.target.value)}
                  className="pl-9"
                  placeholder="Minimum 8 characters"
                />
              </FieldWithIcon>
            </div>

            <div className="space-y-2">
              <Label htmlFor="confirm_password">Confirm New Password</Label>
              <FieldWithIcon icon={KeyRound}>
                <Input
                  id="confirm_password"
                  type="password"
                  autoComplete="new-password"
                  value={form.confirm_password}
                  onChange={(event) => updateForm('confirm_password', event.target.value)}
                  className="pl-9"
                  placeholder="Re-enter new password"
                />
              </FieldWithIcon>
            </div>
          </div>

          {error && (
            <p className="rounded-lg bg-red-50 px-3 py-2 text-sm font-medium text-red-700">
              {error}
            </p>
          )}

          <Button type="submit" disabled={loading}>
            <KeyRound className="h-4 w-4" />
            {loading ? 'Sending OTP...' : 'Send OTP & Continue'}
          </Button>
        </form>
      </SectionCard>

      <ChangeAdminEmail />

      {otpModalOpen && (
        <OtpModal
          email={otpRequest.email}
          otp={otp}
          setOtp={setOtp}
          secondsLeft={secondsLeft}
          canResend={canResend}
          loading={loading}
          error={otpError}
          onClose={() => {
            setOtpModalOpen(false)
            setOtpError('')
          }}
          onResend={resendOtp}
          onVerify={verifyOtpAndChangePassword}
        />
      )}
    </div>
  )
}
