import { useEffect, useRef, useState } from 'react'
import { ArrowLeft, Check, CheckCircle2, Clock3, Eye, EyeOff, KeyRound, Loader2, LockKeyhole, Mail, Send, ShieldCheck, X } from 'lucide-react'
import { Link } from 'react-router-dom'
import { Button } from '@/components/ui/button'
import { Input, Label } from '@/components/ui/input'
import { apiFetch, buildApiUrl, readJsonResponse } from '@/services/apiClient'
import { useAuth } from '@/context/AuthContext'

async function request(path, body) {
  const response = await apiFetch(buildApiUrl(`/auth/${path}.php`), { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) })
  return readJsonResponse(response)
}
function PasswordField({ id, label, value, onChange, current = false }) {
  const [visible, setVisible] = useState(false)
  return <div className="space-y-2"><Label htmlFor={id}>{label}</Label><div className="relative"><LockKeyhole className="pointer-events-none absolute left-3.5 top-3.5 h-4 w-4 text-slate-400" /><Input id={id} required type={visible ? 'text' : 'password'} autoComplete={current ? 'current-password' : 'new-password'} value={value} onChange={event => onChange(event.target.value)} placeholder={current ? 'Enter current password' : 'Enter new password'} className="h-11 rounded-xl border-slate-200 pl-10 pr-11" /><button type="button" aria-label={`${visible ? 'Hide' : 'Show'} ${label.toLowerCase()}`} aria-pressed={visible} onClick={() => setVisible(state => !state)} className="absolute right-2 top-1.5 rounded-lg p-2 text-slate-400 hover:bg-slate-100">{visible ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}</button></div></div>
}
function Illustration({ email = false }) {
  const Icon = email ? Mail : ShieldCheck
  return <div aria-hidden="true" className="relative flex h-32 items-center justify-center"><div className={`absolute h-24 w-24 rounded-full blur-xl ${email ? 'bg-emerald-200/70' : 'bg-blue-200/80'}`} /><div className={`relative flex h-20 w-20 -rotate-6 items-center justify-center rounded-[24px] border border-white/60 shadow-xl ${email ? 'bg-gradient-to-br from-emerald-300 to-emerald-600 shadow-emerald-200/70' : 'bg-gradient-to-br from-blue-300 via-blue-500 to-indigo-600 shadow-blue-200/70'}`}><Icon className="h-11 w-11 text-white" strokeWidth={1.5} /></div><div className="absolute bottom-3 left-[calc(50%+14px)] flex h-9 w-9 items-center justify-center rounded-xl border border-white bg-white text-blue-600 shadow-lg">{email ? <Check className="h-5 w-5 text-emerald-600" /> : <LockKeyhole className="h-5 w-5" />}</div></div>
}
function SecurityCard({ email = false, title, description, children }) {
  const Icon = email ? Mail : ShieldCheck
  return <section className="rounded-[24px] border border-slate-100 bg-white p-4 shadow-sm shadow-slate-200/60 sm:p-6"><div className="grid gap-6 lg:grid-cols-[190px_minmax(0,1fr)] lg:gap-7"><aside className={`hidden flex-col justify-center rounded-2xl px-4 py-6 text-center lg:flex ${email ? 'bg-gradient-to-b from-emerald-50 to-blue-50/70' : 'bg-gradient-to-b from-blue-50 to-indigo-50/60'}`}><Illustration email={email} /><h3 className="mt-3 text-sm font-semibold text-slate-900">{email ? 'Update your email' : 'Keep your account secure'}</h3><p className="mt-2 text-xs leading-5 text-slate-500">{email ? 'Verify your new inbox before it becomes your sign-in address.' : 'Use a unique password to protect your administrator access.'}</p></aside><div className="min-w-0 py-1"><div className="mb-6 flex items-start gap-3"><div className={`flex h-11 w-11 shrink-0 items-center justify-center rounded-xl ${email ? 'bg-emerald-50 text-emerald-600' : 'bg-blue-50 text-blue-600'}`}><Icon className="h-6 w-6" /></div><div><h2 className="text-lg font-semibold text-slate-900">{title}</h2><p className="mt-1 text-sm leading-6 text-slate-500">{description}</p></div></div>{children}</div></div></section>
}
function Requirement({ met, children }) {
  return <span className={`inline-flex items-center gap-1.5 text-xs ${met ? 'text-emerald-600' : 'text-slate-500'}`}><span className={`flex h-3.5 w-3.5 items-center justify-center rounded-full border ${met ? 'border-emerald-500 bg-emerald-500 text-white' : 'border-slate-300'}`}>{met && <Check className="h-2.5 w-2.5" />}</span>{children}</span>
}
function VerificationDialog({ challenge, busy, onClose, onVerify, onResend }) {
  const [otp, setOtp] = useState('')
  const [now, setNow] = useState(Date.now())
  const inputRef = useRef(null)
  const seconds = Math.max(0, Math.ceil((challenge.deadline - now) / 1000))
  useEffect(() => {
    setOtp(''); setNow(Date.now()); inputRef.current?.focus()
    const timer = window.setInterval(() => setNow(Date.now()), 250)
    return () => window.clearInterval(timer)
  }, [challenge.token])
  useEffect(() => {
    const previous = document.activeElement; const overflow = document.body.style.overflow
    document.body.style.overflow = 'hidden'
    return () => { document.body.style.overflow = overflow; previous?.focus?.() }
  }, [])
  const keydown = event => {
    if (event.key === 'Escape' && !busy) onClose()
    if (event.key !== 'Tab') return
    const nodes = Array.from(event.currentTarget.querySelectorAll('button:not(:disabled), input:not(:disabled)'))
    const first = nodes[0]; const last = nodes[nodes.length - 1]
    if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last?.focus() }
    else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first?.focus() }
  }
  return <div className="fixed inset-0 z-[80] flex items-center justify-center bg-slate-950/50 px-4 py-6 backdrop-blur-sm"><section role="dialog" aria-modal="true" aria-labelledby="verification-title" onKeyDown={keydown} className="relative w-full max-w-md rounded-3xl bg-white p-6 shadow-2xl sm:p-8"><button type="button" aria-label="Close verification" disabled={Boolean(busy)} onClick={onClose} className="absolute right-4 top-4 rounded-lg p-2 text-slate-400 hover:bg-slate-100"><X className="h-5 w-5" /></button><div className="mb-5 flex h-14 w-14 items-center justify-center rounded-2xl bg-blue-50 text-blue-600"><Mail className="h-7 w-7" /></div><p className="text-xs font-semibold uppercase tracking-widest text-blue-600">Step 2 of 2 · Verification</p><h2 id="verification-title" className="mt-2 text-2xl font-semibold text-slate-900">Check your inbox</h2><p className="mt-2 break-words text-sm leading-6 text-slate-500">Enter the six-digit code sent to <strong className="font-medium text-slate-800">{challenge.email}</strong>.</p><form onSubmit={event => { event.preventDefault(); onVerify(otp) }} className="mt-6 space-y-4"><Label htmlFor="account-otp">Verification code</Label><Input ref={inputRef} id="account-otp" required inputMode="numeric" autoComplete="one-time-code" pattern="[0-9]{6}" maxLength={6} value={otp} onChange={event => setOtp(event.target.value.replace(/\D/g, '').slice(0, 6))} placeholder="000000" className="h-14 rounded-xl text-center text-2xl font-semibold tracking-[0.4em]" disabled={Boolean(busy)} /><div className={`flex items-center gap-2 rounded-xl px-3 py-3 text-sm ${seconds ? 'bg-blue-50 text-blue-700' : 'bg-amber-50 text-amber-700'}`}><Clock3 className="h-4 w-4" />{seconds ? <>Code expires in <strong>{Math.floor(seconds / 60)}:{String(seconds % 60).padStart(2, '0')}</strong></> : 'Code expired. Request a new code.'}</div>{challenge.error && <p role="alert" className="rounded-xl bg-red-50 p-3 text-sm text-red-700">{challenge.error}</p>}<Button type="submit" disabled={Boolean(busy) || !seconds || otp.length !== 6} className="h-11 w-full rounded-xl">{busy === 'verify' ? <Loader2 className="h-4 w-4 animate-spin" /> : <ShieldCheck className="h-4 w-4" />}{busy === 'verify' ? 'Verifying…' : challenge.type === 'email' ? 'Verify & Change Email' : 'Verify & Change Password'}</Button><div className="flex items-center justify-between gap-3 text-xs"><span className="text-slate-400">Didn’t receive a code?</span><button type="button" onClick={onResend} disabled={Boolean(busy) || seconds > 0} className="font-semibold text-blue-600 disabled:text-slate-400">{busy === 'resend' ? 'Sending…' : 'Resend code'}</button></div></form></section></div>
}
export default function AdminPassword() {
  const { user, updateUser } = useAuth()
  const [passwords, setPasswords] = useState({ current_password: '', new_password: '', confirm_password: '' })
  const [emailForm, setEmailForm] = useState({ new_email: '', current_password: '' })
  const [busy, setBusy] = useState('')
  const [challenge, setChallenge] = useState(null)
  const [error, setError] = useState({ type: '', message: '' })
  const [success, setSuccess] = useState('')
  const updatePassword = (key, value) => { setPasswords(current => ({ ...current, [key]: value })); setError({ type: '', message: '' }) }
  const updateEmail = (key, value) => { setEmailForm(current => ({ ...current, [key]: value })); setError({ type: '', message: '' }) }
  const openChallenge = (type, data, snapshot) => setChallenge({ type, token: data.request_token, email: data.email || snapshot.new_email, deadline: Date.now() + Number(data.expires_in ?? 120) * 1000, snapshot, error: '' })
  const requestCode = async (event, type) => {
    event.preventDefault()
    if (busy || challenge) return
    setError({ type: '', message: '' }); setSuccess('')
    if (type === 'password' && (passwords.new_password.length < 8 || passwords.new_password !== passwords.confirm_password || passwords.new_password === passwords.current_password)) { setError({ type, message: 'Use at least 8 characters, choose a different password, and make both new password fields match.' }); return }
    if (type === 'email' && emailForm.new_email.trim().toLowerCase() === user?.email?.toLowerCase()) { setError({ type, message: 'Enter a different email address.' }); return }
    setBusy(type)
    try {
      const snapshot = type === 'email' ? { ...emailForm, new_email: emailForm.new_email.trim() } : { ...passwords }
      const result = await request(type === 'email' ? 'change-email' : 'request-password-otp', type === 'email' ? { action: 'request', ...snapshot } : { current_password: snapshot.current_password })
      openChallenge(type, result.data, snapshot)
    } catch (err) { setError({ type, message: err.message || 'The verification code could not be sent. Please try again.' }) }
    finally { setBusy('') }
  }
  const resend = async () => {
    if (busy || !challenge || Date.now() < challenge.deadline) return
    setBusy('resend')
    try { const result = await request(challenge.type === 'email' ? 'change-email' : 'resend-password-otp', challenge.type === 'email' ? { action: 'request', ...challenge.snapshot } : { request_token: challenge.token }); openChallenge(challenge.type, result.data, challenge.snapshot) }
    catch (err) { setChallenge(current => ({ ...current, error: err.message })) }
    finally { setBusy('') }
  }
  const verify = async otp => {
    if (busy || !challenge || Date.now() >= challenge.deadline) return
    setBusy('verify')
    try {
      const result = await request(challenge.type === 'email' ? 'change-email' : 'verify-password-otp', { ...challenge.snapshot, request_token: challenge.token, otp, ...(challenge.type === 'email' ? { action: 'verify' } : {}) })
      if (challenge.type === 'email') { updateUser({ email: result.data.email }); setEmailForm({ new_email: '', current_password: '' }); setSuccess('Admin email updated. Use your new email the next time you sign in. Other sessions were signed out.') }
      else { setPasswords({ current_password: '', new_password: '', confirm_password: '' }); setSuccess('Password updated successfully. Other sessions were signed out.') }
      setChallenge(null)
    } catch (err) { setChallenge(current => ({ ...current, error: err.message || 'Verification failed. Please try again.' })) }
    finally { setBusy('') }
  }
  return <div className="space-y-5"><div className="relative overflow-hidden rounded-3xl bg-gradient-to-r from-slate-50 via-blue-50/70 to-indigo-50 px-5 py-6 sm:px-7"><div className="pointer-events-none absolute -right-12 -top-24 h-72 w-72 rounded-full border-[30px] border-blue-100/60" /><div className="relative flex items-center justify-between gap-4"><div className="min-w-0"><Link to="/website-settings" className="mb-3 inline-flex items-center gap-2 text-xs font-medium text-slate-500 hover:text-blue-600"><ArrowLeft className="h-3.5 w-3.5" />Settings</Link><h1 className="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">Admin Password</h1><p className="mt-2 text-sm leading-6 text-slate-500">Manage your administrator credentials and keep your account secure.</p><div className="mt-3 inline-flex max-w-full items-center gap-2 rounded-full border border-blue-100 bg-white/80 px-3 py-1.5 text-xs text-slate-600"><ShieldCheck className="h-3.5 w-3.5 shrink-0 text-blue-600" /><span className="truncate">{user?.email || 'Administrator account'}</span></div></div><div className="hidden w-36 shrink-0 sm:block"><Illustration /></div></div></div>
    {success && <div role="status" className="flex items-start gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800"><CheckCircle2 className="mt-0.5 h-5 w-5 shrink-0" /><p className="flex-1">{success}</p><button type="button" onClick={() => setSuccess('')} aria-label="Dismiss success message"><X className="h-4 w-4" /></button></div>}
    <SecurityCard title="Change Password" description="Choose a new password, then verify the 2-minute code sent to your account email."><form onSubmit={event => requestCode(event, 'password')}><fieldset disabled={Boolean(busy) || Boolean(challenge)} className="space-y-5 disabled:opacity-70"><input className="sr-only" type="text" name="username" autoComplete="username" value={user?.email || ''} readOnly tabIndex={-1} aria-label="Account email" /><div className="sm:max-w-[calc(50%-0.5rem)]"><PasswordField id="current_password" label="Current Password" current value={passwords.current_password} onChange={value => updatePassword('current_password', value)} /></div><div className="grid gap-4 sm:grid-cols-2"><PasswordField id="new_password" label="New Password" value={passwords.new_password} onChange={value => updatePassword('new_password', value)} /><PasswordField id="confirm_password" label="Confirm New Password" value={passwords.confirm_password} onChange={value => updatePassword('confirm_password', value)} /></div><div className="flex flex-wrap gap-x-5 gap-y-2"><Requirement met={passwords.new_password.length >= 8}>At least 8 characters</Requirement><Requirement met={Boolean(passwords.new_password) && passwords.new_password !== passwords.current_password}>Different from current password</Requirement><Requirement met={Boolean(passwords.new_password) && passwords.new_password === passwords.confirm_password}>Passwords match</Requirement></div>{error.type === 'password' && <p role="alert" className="rounded-xl bg-red-50 p-3 text-sm text-red-700">{error.message}</p>}<Button type="submit" className="h-11 w-full rounded-xl px-5 sm:w-auto">{busy === 'password' ? <Loader2 className="h-4 w-4 animate-spin" /> : <Send className="h-4 w-4" />}{busy === 'password' ? 'Sending verification code…' : 'Send OTP & Continue'}</Button></fieldset></form></SecurityCard>
    <SecurityCard email title="Change Admin Email" description="Verify your new inbox using your current password. Your sign-in email updates after verification."><form onSubmit={event => requestCode(event, 'email')}><fieldset disabled={Boolean(busy) || Boolean(challenge)} className="space-y-5 disabled:opacity-70"><input className="sr-only" type="text" name="username" autoComplete="username" value={user?.email || ''} readOnly tabIndex={-1} aria-label="Current account email" /><div className="flex flex-wrap items-center gap-2 text-xs text-slate-500"><span>Current email</span><span className="break-all rounded-lg bg-slate-100 px-2.5 py-1 font-medium text-slate-700">{user?.email || 'Administrator account'}</span></div><div className="space-y-2"><Label htmlFor="admin_new_email">New Email Address</Label><div className="relative"><Mail className="pointer-events-none absolute left-3.5 top-3.5 h-4 w-4 text-slate-400" /><Input id="admin_new_email" type="email" required maxLength={190} autoComplete="off" placeholder="Enter your new email address" value={emailForm.new_email} onChange={event => updateEmail('new_email', event.target.value)} className="h-11 rounded-xl border-slate-200 pl-10" /></div></div><PasswordField id="admin_email_password" label="Current Password" current value={emailForm.current_password} onChange={value => updateEmail('current_password', value)} />{error.type === 'email' && <p role="alert" className="rounded-xl bg-red-50 p-3 text-sm text-red-700">{error.message}</p>}<Button type="submit" className="h-11 w-full rounded-xl bg-emerald-600 px-5 hover:bg-emerald-700 sm:w-auto">{busy === 'email' ? <Loader2 className="h-4 w-4 animate-spin" /> : <Mail className="h-4 w-4" />}{busy === 'email' ? 'Sending verification code…' : 'Update Email & Continue'}</Button><p className="flex items-start gap-2 text-xs leading-5 text-slate-400"><KeyRound className="mt-0.5 h-3.5 w-3.5 shrink-0" />Your sign-in email is separate from SMTP sender and hotel notification addresses.</p></fieldset></form></SecurityCard>
    {challenge && <VerificationDialog challenge={challenge} busy={busy} onClose={() => setChallenge(null)} onVerify={verify} onResend={resend} />}
  </div>
}
