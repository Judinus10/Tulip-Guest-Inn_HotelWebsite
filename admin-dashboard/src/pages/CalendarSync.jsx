import { useEffect, useState } from 'react'
import { CalendarSync as CalendarIcon, RefreshCw, CheckCircle2, AlertTriangle, Loader2, PauseCircle } from 'lucide-react'
import { PageHeader } from '@/components/ui/page-header'
import { Button } from '@/components/ui/button'
import { apiFetch, buildApiUrl, readJsonResponse } from '@/services/apiClient'

const endpoint = buildApiUrl('/calendar/manage.php')

export default function CalendarSync() {
  const [data, setData] = useState(null)
  const [loading, setLoading] = useState(true)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState('')
  const [message, setMessage] = useState('')
  async function refresh() {
    const result = await readJsonResponse(await apiFetch(endpoint))
    setData(result.data)
  }
  useEffect(() => { refresh().catch(e => setError(e.message)).finally(() => setLoading(false)) }, [])
  async function act(action, enabled) {
    setBusy(true); setError(''); setMessage('')
    try {
      const result = await readJsonResponse(await apiFetch(endpoint, {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action, ...(action === 'toggle' ? { enabled } : {}) }),
      }))
      setMessage(result.message || (action === 'sync' ? 'Sync finished.' : 'Settings saved.'))
      await refresh()
    } catch (e) { setError(e.message || 'Operation failed.') }
    finally { setBusy(false) }
  }
  return <div className="mx-auto max-w-4xl space-y-6 pb-12">
    <PageHeader title="Calendar Synchronization" description="One master control for Booking.com calendar imports across every configured room." />
    {error && <div role="alert" className="rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">{error}</div>}
    {message && <div role="status" className="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">{message}</div>}
    {loading ? <div className="flex justify-center p-16"><Loader2 className="animate-spin" /></div> : data && <section className="rounded-2xl border border-border bg-white p-6 shadow-sm sm:p-8">
      <div className="flex items-start gap-4">
        <div className="rounded-xl bg-blue-50 p-3 text-blue-600"><CalendarIcon className="h-6 w-6" /></div>
        <div className="min-w-0 flex-1"><h2 className="text-lg font-semibold text-text-primary">Automatic Calendar Sync</h2><p className="mt-1 text-sm text-text-secondary">Enable or pause Booking.com imports for all configured rooms.</p></div>
        <button type="button" role="switch" aria-label="Automatic calendar synchronization" aria-checked={!!data.enabled} disabled={busy} onClick={() => act('toggle', !data.enabled)} className={`relative h-7 w-12 shrink-0 rounded-full transition-colors disabled:opacity-50 ${data.enabled ? 'bg-blue-600' : 'bg-slate-300'}`}><span className={`absolute top-1 left-1 h-5 w-5 rounded-full bg-white shadow transition-transform ${data.enabled ? 'translate-x-5' : ''}`} /></button>
      </div>
      <div className="mt-7 space-y-4 border-t border-border pt-6 text-sm">
        <div className="flex justify-between"><span className="text-text-secondary">Status</span><span className={`font-semibold ${data.enabled ? 'text-emerald-700' : 'text-amber-700'}`}>{data.enabled ? 'Enabled' : 'Paused'}</span></div>
        <div className="flex justify-between"><span className="text-text-secondary">Configured room calendars</span><span className="font-semibold">{data.configured_rooms}</span></div>
        <div className="flex justify-between"><span className="text-text-secondary">Last completed sync</span><span className="font-medium">{data.last_sync || 'Not yet synced'}</span></div>
        {data.failed_rooms > 0 && <div className="flex items-center gap-2 text-amber-700"><AlertTriangle className="h-4 w-4" />{data.failed_rooms} room(s) reported sync failures</div>}
      </div>
      {!data.legacy_enabled && <div className="mt-6 rounded-xl bg-amber-50 p-4 text-sm text-amber-800">ICS_SYNC_ENABLED is off in the server configuration. Enable it before using this switch. Room calendar URLs are managed in Rooms → Edit Room.</div>}
      {!data.configured_rooms && <div className="mt-6 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">No Booking.com calendars found. Open Rooms → Edit Room and save a Booking.com iCal URL for at least one room.</div>}
      <div className="mt-6 flex flex-wrap items-center justify-between gap-3 border-t border-border pt-6"><span className="flex items-center gap-2 text-xs text-text-secondary">{data.enabled ? <CheckCircle2 className="h-4 w-4" /> : <PauseCircle className="h-4 w-4" />}{data.enabled ? 'Scheduled imports permitted' : 'New imports paused; existing reservations remain blocked'}</span><Button type="button" disabled={!data.enabled || busy || !data.configured_rooms} onClick={() => act('sync')}><RefreshCw className={`mr-2 h-4 w-4 ${busy ? 'animate-spin' : ''}`} />Sync Now</Button></div>
    </section>}
  </div>
}
