import { useEffect, useState } from 'react'
import { CreditCard, LockKeyhole, ShieldCheck, Save, AlertTriangle } from 'lucide-react'
import { PageHeader, SectionCard } from '@/components/ui/page-header'
import { Button } from '@/components/ui/button'
import { apiFetch, buildApiUrl, readJsonResponse } from '@/services/apiClient'

const endpoint = buildApiUrl('/payment-settings/manage.php')
const initial = { pay_on_arrival: true, payhere_enabled: false, environment: 'sandbox', merchant_id: '', secret_configured: false }
export default function PaymentManagement() {
  const [settings, setSettings] = useState(initial)
  const [secret, setSecret] = useState('')
  const [password, setPassword] = useState('')
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [message, setMessage] = useState('')
  const [error, setError] = useState('')
  const update = (key, value) => setSettings(current => ({ ...current, [key]: value }))
  useEffect(() => {
    let active = true
    apiFetch(endpoint).then(readJsonResponse).then(result => { if (active) setSettings(result.data) })
      .catch(e => { if (active) setError(e.message) })
      .finally(() => { if (active) setLoading(false) })
    return () => { active = false }
  }, [])
  async function save(e) {
    e.preventDefault()
    setError(''); setMessage('')
    if (!settings.pay_on_arrival && !settings.payhere_enabled) { setError('At least one payment method must remain enabled.'); return }
    if (!password) { setError('Enter your admin password to confirm changes.'); return }
    setSaving(true)
    try {
      const response = await apiFetch(endpoint, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ ...settings, merchant_secret: secret, admin_password: password }) })
      const result = await readJsonResponse(response)
      setSettings(result.data); setSecret(''); setPassword('')
      setMessage('Payment settings saved. Public checkout integration must be completed before these switches control customer bookings.')
    } catch (e) { setError(e.message || 'Unable to save payment settings.') }
    finally { setSaving(false) }
  }
  return <div className="space-y-6">
    <PageHeader title="Payment Management" description="Configure booking payment methods and PayHere credentials." />
    <div className="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 flex items-start gap-3"><AlertTriangle className="h-5 w-5 shrink-0" /><p><strong>Configuration stage:</strong> These settings are stored securely but are not yet connected to the public checkout and payment-processing endpoints. Do not rely on these switches to block bookings until integration is completed.</p></div>
    {error && <p role="alert" className="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">{error}</p>}
    {message && <p role="status" className="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-700">{message}</p>}
    {loading ? <SectionCard>Loading payment configuration...</SectionCard> : <form onSubmit={save} className="space-y-6">
      <SectionCard>
        <div className="mb-5 flex items-center gap-3"><div className="rounded-xl bg-blue-50 p-3 text-blue-600"><CreditCard className="h-5 w-5" /></div><div><h2 className="text-lg font-semibold">Booking Payment Methods</h2><p className="text-sm text-text-secondary">At least one method must remain enabled.</p></div></div>
        <div className="space-y-4">{[
          ['pay_on_arrival', 'Pay on Arrival', 'Guests reserve online and pay at the property.'],
          ['payhere_enabled', 'Pay Online — PayHere', 'Guests pay through the PayHere payment gateway.']
        ].map(([key, title, description]) => <label key={key} className="flex cursor-pointer items-center justify-between gap-4 rounded-xl border border-border p-4"><span><span className="block text-sm font-semibold text-text-primary">{title}</span><span className="mt-1 block text-xs text-text-secondary">{description}</span></span><input type="checkbox" className="h-5 w-5 accent-blue-600" checked={Boolean(settings[key])} onChange={e => update(key, e.target.checked)} /></label>)}</div>
      </SectionCard>
      <SectionCard>
        <div className="mb-5 flex items-center gap-3"><div className="rounded-xl bg-blue-50 p-3 text-blue-600"><ShieldCheck className="h-5 w-5" /></div><div><h2 className="text-lg font-semibold">PayHere Configuration</h2><p className="text-sm text-text-secondary">Credentials are encrypted on the server and never returned to the browser.</p></div></div>
        <div className="grid gap-5 md:grid-cols-2">
          <label className="block space-y-2 text-sm font-medium">Environment<select value={settings.environment} onChange={e => update('environment', e.target.value)} className="block w-full rounded-xl border border-border bg-white px-4 py-3"><option value="sandbox">Sandbox (testing)</option><option value="live">Live</option></select></label>
          <label className="block space-y-2 text-sm font-medium">Merchant ID<input value={settings.merchant_id || ''} maxLength={100} onChange={e => update('merchant_id', e.target.value)} className="block w-full rounded-xl border border-border px-4 py-3" placeholder="PayHere Merchant ID" /></label>
          <label className="block space-y-2 text-sm font-medium md:col-span-2">Merchant Secret <span className="font-normal text-text-secondary">({settings.secret_configured ? 'Configured — leave blank to keep existing' : 'Not configured'})</span><input type="password" autoComplete="new-password" value={secret} onChange={e => setSecret(e.target.value)} className="block w-full rounded-xl border border-border px-4 py-3" placeholder="Enter a new Merchant Secret to set or replace" /></label>
        </div>
        <p className="mt-4 flex items-start gap-2 text-xs text-text-secondary"><LockKeyhole className="h-4 w-4 shrink-0" />Stored secrets cannot be revealed. Switching between Sandbox and Live requires separately configured credentials for each environment; this version maintains one active credential set.</p>
      </SectionCard>
      <SectionCard>
        <label className="block space-y-2 text-sm font-medium">Confirm with admin password<input required type="password" autoComplete="current-password" value={password} onChange={e => setPassword(e.target.value)} className="block w-full rounded-xl border border-border px-4 py-3" placeholder="Your current admin password" /></label>
        <div className="mt-5 flex justify-end"><Button type="submit" disabled={saving || (!settings.pay_on_arrival && !settings.payhere_enabled)}><Save className="h-4 w-4" />{saving ? 'Saving...' : 'Save Payment Settings'}</Button></div>
      </SectionCard>
    </form>}
  </div>
}
