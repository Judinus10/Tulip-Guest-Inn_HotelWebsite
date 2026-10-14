import { useEffect, useState } from 'react'
import { CreditCard, Wallet, ShieldCheck, Save, AlertTriangle, ChevronDown, LockKeyhole, CheckCircle2, CircleHelp, Loader2 } from 'lucide-react'
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
  const [detailsOpen, setDetailsOpen] = useState(false)
  const update = (key, value) => setSettings(current => ({ ...current, [key]: value }))

  useEffect(() => {
    let active = true
    apiFetch(endpoint).then(readJsonResponse).then(result => { if (active) setSettings({ ...initial, ...result.data }) })
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
      setSettings({ ...initial, ...result.data }); setSecret(''); setPassword('')
      setMessage('Payment settings saved successfully. Public checkout integration is still required before these options affect customer bookings.')
    } catch (e) { setError(e.message || 'Unable to save payment settings.') }
    finally { setSaving(false) }
  }

  return <div className="mx-auto max-w-5xl space-y-6 pb-12">
    <PageHeader title="Payment Management" description="Choose how guests pay and manage your online payment gateway." />

    <div className="flex items-start gap-3 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
      <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0" />
      <p><strong>Integration pending.</strong> These settings can be saved, but they will not control public bookings until checkout and payment endpoints are connected.</p>
    </div>
    {error && <div role="alert" className="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{error}</div>}
    {message && <div role="status" className="flex items-start gap-2 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"><CheckCircle2 className="h-4 w-4 shrink-0" />{message}</div>}

    {loading ? <div className="flex items-center justify-center gap-3 rounded-2xl border border-border bg-white p-14 text-sm text-text-secondary"><Loader2 className="h-5 w-5 animate-spin" />Loading payment settings...</div> : <form onSubmit={save} className="space-y-6">
      <section className="rounded-2xl border border-border bg-white p-5 shadow-sm sm:p-7">
        <div className="mb-6 flex items-start gap-3">
          <div className="rounded-xl bg-blue-50 p-3 text-blue-600"><CreditCard className="h-5 w-5" /></div>
          <div><h2 className="text-lg font-semibold text-text-primary">Accepted payment methods</h2><p className="mt-1 text-sm text-text-secondary">Enable one or both options. At least one must always be available.</p></div>
        </div>

        <div className="space-y-3">
          <div className={`rounded-2xl border p-4 transition-colors sm:p-5 ${settings.pay_on_arrival ? 'border-blue-200 bg-blue-50/40' : 'border-border bg-white'}`}>
            <div className="flex items-center gap-4">
              <div className="rounded-xl bg-white p-3 text-slate-700 shadow-sm"><Wallet className="h-5 w-5" /></div>
              <div className="min-w-0 flex-1"><h3 className="font-semibold text-text-primary">Pay on Arrival</h3><p className="mt-1 text-xs leading-5 text-text-secondary sm:text-sm">Guests reserve now and pay directly at the property.</p></div>
              <div className="flex shrink-0 flex-col items-end gap-1"><Toggle checked={settings.pay_on_arrival} onChange={value => { setError(''); update('pay_on_arrival', value) }} label="Enable Pay on Arrival" /><span className="text-xs text-text-secondary">{settings.pay_on_arrival ? 'Enabled' : 'Disabled'}</span></div>
            </div>
          </div>

          <div className={`overflow-hidden rounded-2xl border transition-colors ${settings.payhere_enabled ? 'border-blue-300 bg-blue-50/40' : 'border-border bg-white'}`}>
            <div className="flex items-center gap-4 p-4 sm:p-5">
              <div className="rounded-xl bg-white p-3 text-blue-600 shadow-sm"><CreditCard className="h-5 w-5" /></div>
              <div className="min-w-0 flex-1"><h3 className="font-semibold text-text-primary">Pay Online <span className="ml-1 text-xs font-normal text-text-secondary">via PayHere</span></h3><p className="mt-1 text-xs leading-5 text-text-secondary sm:text-sm">Accept secure online payments at checkout.</p></div>
              <div className="flex shrink-0 flex-col items-end gap-1"><Toggle checked={settings.payhere_enabled} onChange={value => { setError(''); update('payhere_enabled', value) }} label="Enable PayHere online payments" /><span className="text-xs text-text-secondary">{settings.payhere_enabled ? 'Enabled' : 'Disabled'}</span></div>
            </div>

            {settings.payhere_enabled && <div className="border-t border-blue-200 bg-white px-4 py-5 sm:px-6 sm:py-6">
              <div className="mb-5 flex items-start justify-between gap-3">
                <div className="flex items-start gap-2"><ShieldCheck className="mt-0.5 h-5 w-5 text-blue-600" /><div><h4 className="font-semibold text-text-primary">Gateway credentials</h4><p className="mt-1 text-xs leading-5 text-text-secondary">Stored securely on the server. Existing secrets are never displayed.</p></div></div>
                <span className={`shrink-0 rounded-full px-2.5 py-1 text-xs font-medium ${settings.secret_configured ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700'}`}>{settings.secret_configured ? 'Secret saved' : 'Setup required'}</span>
              </div>
              <div className="grid gap-5 sm:grid-cols-2">
                <label className="space-y-2 text-sm font-medium text-text-primary"><span className="block">Environment</span><select className={field} value={settings.environment} onChange={e => update('environment', e.target.value)}><option value="sandbox">Sandbox — Testing</option><option value="live">Live — Real payments</option></select></label>
                <label className="space-y-2 text-sm font-medium text-text-primary"><span className="block">Merchant ID</span><input className={field} value={settings.merchant_id || ''} maxLength={100} onChange={e => update('merchant_id', e.target.value)} placeholder="Enter PayHere Merchant ID" autoComplete="off" /></label>
                <label className="space-y-2 text-sm font-medium text-text-primary sm:col-span-2"><span className="flex items-center justify-between gap-2">Merchant Secret <span className="text-xs font-normal text-text-secondary">{settings.secret_configured ? 'Leave blank to keep existing' : 'Required for setup'}</span></span><input className={field} type="password" autoComplete="new-password" value={secret} onChange={e => setSecret(e.target.value)} placeholder={settings.secret_configured ? 'Enter a new secret only to replace it' : 'Enter your PayHere Merchant Secret'} /></label>
              </div>
              <div className="mt-5 flex items-start gap-2 rounded-xl bg-slate-50 p-3 text-xs leading-5 text-slate-600"><LockKeyhole className="mt-0.5 h-4 w-4 shrink-0" /><span>Saved secrets cannot be viewed. This version uses one credential set; changing Sandbox/Live does not automatically switch to separate credentials.</span></div>
            </div>}
          </div>
        </div>
        {bothDisabled && <p className="mt-4 flex items-center gap-2 text-sm text-red-600"><AlertTriangle className="h-4 w-4" />Enable at least one payment method to save.</p>}
      </section>

      <section className="rounded-2xl border border-border bg-white shadow-sm">
        <button type="button" className="flex w-full items-center justify-between gap-4 p-5 text-left sm:px-7" onClick={() => setDetailsOpen(v => !v)} aria-expanded={detailsOpen}>
          <span className="flex items-center gap-3"><span className="rounded-xl bg-slate-100 p-2.5 text-slate-600"><CircleHelp className="h-5 w-5" /></span><span><span className="block font-semibold text-text-primary">Security & setup information</span><span className="mt-1 block text-xs text-text-secondary">How your payment credentials are protected</span></span></span>
          <ChevronDown className={`h-5 w-5 text-text-secondary transition-transform ${detailsOpen ? 'rotate-180' : ''}`} />
        </button>
        {detailsOpen && <div className="border-t border-border px-5 py-5 text-sm leading-7 text-text-secondary sm:px-7"><p>PayHere credentials are submitted only to the authenticated backend. Merchant Secrets are encrypted before database storage and are never returned in API responses.</p><p className="mt-2">A server-side encryption key must be configured before saving secrets. Protect the admin account and do not share credentials by email or chat.</p></div>}
      </section>

      <section className="rounded-2xl border border-border bg-white p-5 shadow-sm sm:p-7">
        <div className="mb-5 flex items-start gap-3"><div className="rounded-xl bg-slate-100 p-3 text-slate-700"><LockKeyhole className="h-5 w-5" /></div><div><h2 className="text-base font-semibold text-text-primary">Confirm changes</h2><p className="mt-1 text-sm text-text-secondary">For security, enter your admin password before saving.</p></div></div>
        <label className="block max-w-md space-y-2 text-sm font-medium text-text-primary"><span className="block">Admin password</span><input required type="password" autoComplete="current-password" className={field} value={password} onChange={e => setPassword(e.target.value)} placeholder="Enter your current password" /></label>
        <div className="mt-6 flex justify-end border-t border-border pt-5"><Button type="submit" disabled={saving || bothDisabled || missingCredentials}><Save className="h-4 w-4" />{saving ? 'Saving changes...' : 'Save Changes'}</Button></div>
      </section>
    </form>}
  </div>
}
