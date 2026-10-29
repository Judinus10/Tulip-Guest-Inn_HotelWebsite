import BookingEmailTest from '@/components/ui/BookingEmailTest'
import { useEffect, useRef, useState } from 'react'
import { AlertCircle, Loader2, Monitor, RefreshCw, Smartphone } from 'lucide-react'
import { SectionCard } from '@/components/ui/page-header'
import { previewBookingEmail } from '@/services/bookingEmailSettings'

export default function BookingEmailPreview({ type, content }) {
  const [device, setDevice] = useState('desktop')
  const [scenario, setScenario] = useState('single')
  const [preview, setPreview] = useState(null)
  const [busy, setBusy] = useState(true)
  const [error, setError] = useState('')
  const [refresh, setRefresh] = useState(0)
  const sequence = useRef(0)
  const fingerprint = JSON.stringify({ type, content, scenario })
  useEffect(() => {
    const controller = new AbortController()
    const request = ++sequence.current
    let active = true
    setBusy(true); setError('')
    const timer = setTimeout(async () => {
      try {
        const result = await previewBookingEmail(type, content, scenario, controller.signal)
        if (active && request === sequence.current) setPreview({ ...result, fingerprint })
      } catch (e) {
        if (active && e.name !== 'AbortError' && request === sequence.current) setError(e.message || 'Could not update the email preview.')
      } finally { if (active && request === sequence.current) setBusy(false) }
    }, 400)
    return () => { active = false; clearTimeout(timer); controller.abort() }
  }, [fingerprint, refresh]) // A change to any draft field schedules a fresh, cancellable preview.
  const stale = preview && preview.fingerprint !== fingerprint
  return <SectionCard>
    <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
      <div><h3 className="text-lg font-bold text-text-primary">Live preview</h3><p className="mt-1 text-xs text-text-secondary">Sample booking data. Your saved hotel details.</p></div>
      <div className="flex items-center gap-2">
        <div role="group" aria-label="Preview device" className="flex rounded-lg border border-border bg-slate-50 p-1">
          {[['desktop', 'Desktop', Monitor], ['mobile', 'Mobile', Smartphone]].map(([key, label, Icon]) => <button key={key} type="button" aria-pressed={device === key} onClick={() => setDevice(key)}
            className={`flex items-center gap-1.5 rounded-md px-2.5 py-2 text-xs font-semibold ${device === key ? 'bg-blue-600 text-white shadow-sm' : 'text-text-secondary hover:bg-white'}`}><Icon className="h-3.5 w-3.5" />{label}</button>)}
        </div>
        <button type="button" aria-label="Refresh email preview" title="Refresh hotel details" onClick={() => setRefresh(n => n + 1)} className="rounded-lg border border-border p-2 text-text-secondary hover:bg-slate-50"><RefreshCw className="h-4 w-4" /></button>
      </div>
    </div>
    <div className="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-xl bg-slate-50 p-3">
      <label className="flex items-center gap-2 text-xs font-semibold text-text-secondary" htmlFor="email-sample-scenario">Sample booking
        <select id="email-sample-scenario" value={scenario} onChange={e => setScenario(e.target.value)} className="rounded-lg border border-border bg-white px-2 py-2 text-xs text-text-primary">
          <option value="single">Single room</option><option value="group">Multiple rooms</option>
        </select>
      </label>
      <span role="status" className="inline-flex items-center gap-1.5 text-xs text-text-secondary">{busy ? <><Loader2 className="h-3.5 w-3.5 animate-spin" />Updating...</> : stale ? 'Showing last valid preview' : error ? 'Preview unavailable' : 'Preview up to date'}</span>
    </div>
    {error && <p role="alert" className="mb-4 flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs leading-5 text-amber-900"><AlertCircle className="mt-0.5 h-4 w-4 shrink-0" />{error}</p>}
    {preview ? <>
      <div className="mb-3 rounded-lg border border-border px-3 py-2"><p className="text-xs font-semibold text-text-secondary">Subject</p><p className="mt-1 break-words text-sm text-text-primary">{preview.subject}</p></div>
      <div className="min-w-0 overflow-hidden rounded-xl border border-border bg-slate-100 p-2 sm:p-3">
        <div className={`mx-auto overflow-hidden bg-white shadow-sm ${device === 'mobile' ? 'max-w-[375px]' : 'max-w-[600px]'}`}>
          <iframe title={`Sample ${type === 'received' ? 'booking received' : 'booking confirmed'} email preview`} sandbox="" referrerPolicy="no-referrer" srcDoc={preview.html}
            className="block h-[850px] w-full border-0" />
        </div>
      </div>
      <p className="mt-3 text-xs leading-5 text-text-secondary">Links are inactive in the preview. Previewing does not save changes or send an email.</p>
    </> : <div className="flex min-h-[420px] items-center justify-center rounded-xl border border-dashed border-border bg-slate-50 p-6 text-center text-sm text-text-secondary">{busy ? 'Preparing your email preview...' : 'Complete the required fields to see the email preview.'}</div>}
    <BookingEmailTest type={type} content={content} scenario={scenario} />
  </SectionCard>
}
