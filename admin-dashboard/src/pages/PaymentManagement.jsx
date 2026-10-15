import { useEffect, useState } from 'react'
import { CreditCard, Wallet, ShieldCheck, Save, AlertTriangle, LockKeyhole, CheckCircle2, Loader2, RotateCcw, ExternalLink, Check, X } from 'lucide-react'
import { PageHeader } from '@/components/ui/page-header'
import { Button } from '@/components/ui/button'
import { apiFetch, buildApiUrl, readJsonResponse } from '@/services/apiClient'

const endpoint = buildApiUrl('/payment-settings/manage.php')
const initial = { pay_on_arrival: true, payhere_enabled: false, environment: 'sandbox', merchant_id: '', secret_configured: false }
const field = 'w-full rounded-xl border border-border bg-white px-4 py-3 text-sm text-text-primary outline-none transition focus:border-blue-400 focus:ring-4 focus:ring-blue-500/10'

function Toggle({ checked, onChange, label, disabled }) {
  return <button type="button" role="switch" aria-label={label} aria-checked={checked} disabled={disabled} onClick={() => onChange(!checked)} className={`relative inline-flex h-7 w-12 shrink-0 items-center rounded-full transition-colors focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-blue-500/20 disabled:cursor-not-allowed disabled:opacity-50 ${checked ? 'bg-blue-600' : 'bg-slate-300'}`}>
    <span className={`inline-block h-5 w-5 rounded-full bg-white shadow-sm transition-transform ${checked ? 'translate-x-6' : 'translate-x-1'}`} />
  </button>
}

export default function PaymentManagement() {
  const [settings, setSettings] = useState(initial)
  const [secret, setSecret] = useState('')
  const [password, setPassword] = useState('')
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [message, setMessage] = useState('')
  const [error, setError] = useState('')
  const [savedSettings, setSavedSettings] = useState(initial)
  const [noticeVisible, setNoticeVisible] = useState(true)
  useEffect(() => {
    if (!message && !error) return undefined
    const timer = window.setTimeout(() => { setMessage(''); setError('') }, 4500)
    return () => window.clearTimeout(timer)
  }, [message, error])
  const update = (key, value) => setSettings(current => ({ ...current, [key]: value }))

  useEffect(() => {
    let active = true
    apiFetch(endpoint).then(readJsonResponse).then(result => { if (active) { const next = { ...initial, ...result.data }; setSettings(next); setSavedSettings(next) } })
      .catch(e => { if (active) setError(e.message) })
      .finally(() => { if (active) setLoading(false) })
    return () => { active = false }
  }, [])

  const bothDisabled = !settings.pay_on_arrival && !settings.payhere_enabled
  const missingCredentials = settings.payhere_enabled && (!settings.merchant_id?.trim() || (!settings.secret_configured && !secret.trim()))

  async function save(e) {
    e.preventDefault()
    setError(''); setMessage('')
    if (bothDisabled) { setError('At least one payment method must remain enabled.'); return }
    if (missingCredentials) { setError('Enter the PayHere Merchant ID and Merchant Secret before enabling online payments.'); return }
    if (!password) { setError('Enter your admin password to confirm changes.'); return }
    setSaving(true)
    try {
      const response = await apiFetch(endpoint, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ ...settings, merchant_secret: secret, admin_password: password }) })
      const result = await readJsonResponse(response)
      setSettings({ ...initial, ...result.data }); setSavedSettings({ ...initial, ...result.data }); setSecret(''); setPassword('')
      setMessage('Payment settings saved. New public bookings will use the selected payment methods.')
    } catch (e) { setError(e.message || 'Unable to save payment settings.') }
    finally { setSaving(false) }
  }

  const reset = () => { setSettings({ ...savedSettings }); setSecret(''); setPassword(''); setError(''); setMessage('') }
  const status = (enabled) => <span className={`rounded-lg px-3 py-1.5 text-xs font-semibold ${enabled ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600'}`}>{enabled ? 'Enabled' : 'Disabled'}</span>

  return <div className="mx-auto max-w-[1450px] space-y-5 pb-12">
    <div className="flex flex-wrap items-start justify-between gap-3 px-1">
      <PageHeader title="Payment Management" description="Manage how guests pay and configure your online payment gateway." />
      <a href="https://sandbox.payhere.lk/" target="_blank" rel="noopener noreferrer" className="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50"><ExternalLink className="h-4 w-4" /> Open PayHere Sandbox</a>
    </div>
    {noticeVisible && <div className="flex items-center gap-3 rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm text-amber-900"><AlertTriangle className="h-5 w-5 shrink-0 text-amber-600" /><div className="flex-1"><strong>Payment configuration</strong><span className="ml-2">Saved settings apply to new bookings. Test PayHere in Sandbox before accepting live payments.</span></div><button type="button" onClick={() => setNoticeVisible(false)} aria-label="Dismiss notice"><X className="h-4 w-4" /></button></div>}
    {(error || message) && <div className="fixed right-5 top-20 z-[100] w-[min(380px,calc(100vw-2rem))]" aria-live="polite">
      <div role={error ? 'alert' : 'status'} className={`flex items-start gap-3 rounded-xl border bg-white p-4 shadow-xl ${error ? 'border-red-200 text-red-700' : 'border-emerald-200 text-emerald-700'}`}>
        {error ? <AlertTriangle className="mt-0.5 h-5 w-5 shrink-0" /> : <CheckCircle2 className="mt-0.5 h-5 w-5 shrink-0" />}
        <p className="min-w-0 flex-1 text-sm leading-5">{error || message}</p>
        <button type="button" aria-label="Dismiss notification" onClick={() => { setError(''); setMessage('') }} className="rounded p-0.5 hover:bg-slate-100"><X className="h-4 w-4" /></button>
      </div>
    </div>}
    {loading ? <div className="flex items-center justify-center gap-3 rounded-2xl bg-white p-14"><Loader2 className="h-5 w-5 animate-spin" /> Loading payment settings...</div> : <form onSubmit={save} className="space-y-4">
      <div className="grid items-start gap-5 xl:grid-cols-[minmax(0,1fr)_340px]">
        <div className="space-y-5">
          <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <div className="flex items-start gap-4">
              <div className="rounded-2xl bg-blue-50 p-3.5 text-blue-600"><Wallet className="h-7 w-7" /></div>
              <div className="min-w-0 flex-1"><div className="flex flex-wrap items-center gap-2"><h2 className="text-xl font-bold text-slate-900">Pay on Arrival</h2><span className="rounded-lg bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700">Recommended</span></div><p className="mt-1 text-sm text-slate-500">Guests reserve now and pay directly at the property.</p></div>
              <div className="flex flex-col items-end gap-2"><Toggle checked={settings.pay_on_arrival} onChange={value => { setError(''); update('pay_on_arrival', value) }} label="Enable Pay on Arrival" /><span className="text-xs text-slate-400">{settings.pay_on_arrival ? 'Accept on-arrival payments' : 'Currently unavailable'}</span></div>
            </div>
            {settings.pay_on_arrival && <div className="mt-5 space-y-2 rounded-xl bg-blue-50/70 p-4 text-sm text-slate-700">{['Guests can book without online payment','Booking payment status remains Pending until collected','No payment gateway credentials required'].map(item => <div key={item} className="flex items-center gap-2.5"><CheckCircle2 className="h-4 w-4 shrink-0 text-emerald-500" />{item}</div>)}</div>}
          </section>
          <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div className="flex items-start gap-4 p-5 sm:p-6">
              <div className="rounded-2xl bg-pink-50 p-3.5 text-pink-600"><CreditCard className="h-7 w-7" /></div>
              <div className="min-w-0 flex-1"><div className="flex flex-wrap items-center gap-2"><h2 className="text-xl font-bold text-slate-900">Pay Online</h2><span className="rounded-lg bg-blue-50 px-2.5 py-1 text-sm text-blue-600">via <strong>PayHere</strong></span></div><p className="mt-1 text-sm text-slate-500">Accept secure online payments at checkout.</p></div>
              <div className="flex flex-col items-end gap-2"><Toggle checked={settings.payhere_enabled} onChange={value => { setError(''); update('payhere_enabled', value) }} label="Enable PayHere online payments" /><span className="text-xs text-slate-400">{settings.payhere_enabled ? 'Online payments enabled' : 'Enable to configure gateway'}</span></div>
            </div>
            {settings.payhere_enabled && <div className="mx-5 mb-5 rounded-2xl border border-blue-100 bg-slate-50/70 p-4 sm:mx-6 sm:p-5">
              <div className="mb-4 flex flex-wrap items-center justify-between gap-2"><div className="flex items-center gap-2 font-semibold text-slate-900"><LockKeyhole className="h-5 w-5 text-blue-600" /> PayHere Credentials</div>{status(settings.secret_configured)}</div>
              <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_230px]">
                <div className="grid gap-4 sm:grid-cols-2">
                  <label className="space-y-1.5 text-xs font-medium text-slate-600">Merchant ID<input className={field} value={settings.merchant_id || ''} maxLength={100} onChange={e => update('merchant_id', e.target.value)} placeholder="Your merchant ID" autoComplete="off" /></label>
                  <label className="space-y-1.5 text-xs font-medium text-slate-600">Environment<select className={field} value={settings.environment} onChange={e => update('environment', e.target.value)}><option value="sandbox">Sandbox (Testing)</option><option value="live">Live (Production)</option></select></label>
                  <label className="space-y-1.5 text-xs font-medium text-slate-600 sm:col-span-2">Merchant Secret<input className={field} type="password" autoComplete="new-password" value={secret} onChange={e => setSecret(e.target.value)} placeholder={settings.secret_configured ? 'Saved securely — enter only to replace' : 'Enter PayHere Merchant Secret'} /><span className="block text-xs font-normal text-slate-500">{settings.secret_configured ? 'Existing secret is hidden. Leave blank to keep it.' : 'Required to enable PayHere.'}</span></label>
                </div>
                <div className="rounded-xl border border-slate-200 bg-white p-3"><p className="mb-3 text-xs font-semibold text-slate-700">Payment gateway</p><div className="rounded-lg bg-blue-50 px-3 py-2 text-center font-bold text-blue-700">PayHere</div><a href="https://www.payhere.lk/" target="_blank" rel="noopener noreferrer" className="mt-4 flex items-center gap-2 text-xs font-semibold text-blue-600"><ExternalLink className="h-4 w-4" /> PayHere website</a><p className="mt-2 text-xs leading-5 text-slate-500">Configure credentials from your PayHere merchant account.</p></div>
              </div>
              <p className="mt-4 flex items-start gap-2 text-xs leading-5 text-slate-500"><ShieldCheck className="mt-0.5 h-4 w-4 shrink-0" /> Merchant Secrets are stored encrypted on the server and never returned to the browser. Sandbox and Live share one saved credential set in the current backend.</p>
            </div>}
          </section>
        </div>
        <aside className="space-y-4">
          <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><h3 className="mb-4 flex items-center gap-2 text-base font-bold text-slate-900"><CreditCard className="h-5 w-5 text-blue-600" /> Payment Status</h3><div className="space-y-2">{[['Pay on Arrival',settings.pay_on_arrival],['Pay Online (PayHere)',settings.payhere_enabled]].map(([label,enabled]) => <div key={label} className="flex items-center justify-between gap-2 rounded-xl border border-slate-200 px-3 py-3 text-sm"><span className="font-medium text-slate-700">{label}</span>{status(enabled)}</div>)}</div><p className="mt-3 text-xs text-slate-400">Statuses shown reflect unsaved changes until you save.</p></section>
          <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <label htmlFor="payment-admin-password" className="mb-2 flex items-center gap-2 text-sm font-semibold text-slate-900"><LockKeyhole className="h-5 w-5 text-blue-600" /> Confirm Admin Password</label>
            <p className="mb-3 text-xs leading-5 text-slate-500">Enter your admin password to securely save payment settings.</p>
            <input id="payment-admin-password" type="password" required autoComplete="current-password" value={password} onChange={e => setPassword(e.target.value)} placeholder="Enter admin password" className={field} />
          </section>
          <div className="grid grid-cols-1 gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <button type="button" onClick={reset} disabled={saving} className="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-slate-200 bg-slate-100 px-3 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-200 disabled:opacity-50"><RotateCcw className="h-4 w-4" /> Reset Changes</button>
            <Button type="submit" className="w-full justify-center" disabled={saving || bothDisabled || missingCredentials || !password.trim()}><Save className="h-4 w-4" /> {saving ? 'Saving...' : 'Save Settings'}</Button>
          </div>
        </aside>
      </div>
      {bothDisabled && <p className="flex items-center gap-2 text-sm text-red-600"><AlertTriangle className="h-4 w-4" /> At least one payment method must remain enabled.</p>}
    </form>}
  </div>
}
