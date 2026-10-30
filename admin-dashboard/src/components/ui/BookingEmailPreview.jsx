import { notifyError } from '@/utils/notifications'
import BookingEmailTest from '@/components/ui/BookingEmailTest'
import { applyEmailBrandingPreview } from '@/services/emailBrandingPreview'
import { useEffect, useMemo, useRef, useState } from 'react'
import { Loader2, Monitor, RefreshCw, Smartphone } from 'lucide-react'
import { previewBookingEmail } from '@/services/bookingEmailSettings'

export default function BookingEmailPreview({ type, content, brandingRevision = 0, branding = null }) {
  const [device, setDevice] = useState('desktop')
  const [scenario, setScenario] = useState('single')
  const [preview, setPreview] = useState(null)
  const [busy, setBusy] = useState(true)
  const [error, setError] = useState('')
  const [refresh, setRefresh] = useState(0)
  const stage = useRef(null)
  const [stageWidth, setStageWidth] = useState(600)
  const sequence = useRef(0)
  const lastNotifiedError = useRef('')
  useEffect(() => {
    const refreshAppearance = () => setRefresh(value => value + 1)
    window.addEventListener('tulip:appearance-saved', refreshAppearance)
    return () => window.removeEventListener('tulip:appearance-saved', refreshAppearance)
  }, [])
  const isDaily = type === 'admin_daily_summary'
  const [summaryScenario, setSummaryScenario] = useState('single')
  const selectedScenario = isDaily ? summaryScenario : scenario
  const fingerprint = JSON.stringify({ type, content, scenario: selectedScenario })
  useEffect(() => {
    const controller = new AbortController()
    const request = ++sequence.current
    let active = true
    setBusy(true); setError('')
    const timer = setTimeout(async () => {
      try {
        const result = await previewBookingEmail(type, content, selectedScenario, controller.signal)
        if (active && request === sequence.current) { setPreview({ ...result, fingerprint }); lastNotifiedError.current = '' }
      } catch (e) {
        if (active && e.name !== 'AbortError' && request === sequence.current) {
          setError(e.message || 'Could not update the email preview.')
          const key = `${type}:${refresh}:${e.status || ''}:${e.message || ''}`
          if (lastNotifiedError.current !== key) { lastNotifiedError.current = key; notifyError(e, { duration: 6000 }) }
        }
      } finally { if (active && request === sequence.current) setBusy(false) }
    }, 400)
    return () => { active = false; clearTimeout(timer); controller.abort() }
  }, [fingerprint, refresh, brandingRevision]) // A change to any draft field schedules a fresh, cancellable preview.
  const previewHtml = useMemo(() => applyEmailBrandingPreview(preview?.html || '', branding), [preview?.html, branding?.logo, branding?.width])
  const isContact = type.startsWith('contact_')
  const stale = preview && preview.fingerprint !== fingerprint
  useEffect(() => {
    const node = stage.current
    if (!node) return undefined
    const measure = () => { const width = node.getBoundingClientRect().width; if (width > 0) setStageWidth(width) }
    measure()
    if (typeof ResizeObserver === 'undefined') { window.addEventListener('resize', measure); return () => window.removeEventListener('resize', measure) }
    const observer = new ResizeObserver(measure)
    observer.observe(node)
    return () => observer.disconnect()
  }, [Boolean(preview)])
  const frameWidth = device === 'mobile' ? 375 : 600
  const scale = Math.min(1, stageWidth / frameWidth)
  return <section className="booking-email-card booking-email-preview" aria-label="Email live preview">
    <div className="booking-email-preview-heading">
      <div className="booking-email-card-heading" style={{ margin: 0 }}><h3>Live preview</h3></div>
      <div className="booking-email-preview-controls">
        <div role="group" aria-label="Preview device" className="booking-email-device-tabs">
          {[['desktop', 'Desktop', Monitor], ['mobile', 'Mobile', Smartphone]].map(([key, label, Icon]) => <button key={key} type="button" aria-pressed={device === key} onClick={() => setDevice(key)}><Icon className="h-3.5 w-3.5" />{label}</button>)}
        </div>
        <label className="sr-only" htmlFor="email-sample-scenario">Sample booking</label>
        {isDaily ? <select aria-label="Sample daily summary" value={summaryScenario} onChange={e => setSummaryScenario(e.target.value)} className="booking-email-sample-select">
          <option value="single">Sample day</option><option value="no_arrivals">No arrivals</option><option value="no_departures">No departures</option><option value="empty">No stays scheduled</option>
        </select> : isContact ? <span className="booking-email-sample-select">Sample enquiry</span> : <select id="email-sample-scenario" value={scenario} onChange={e => setScenario(e.target.value)} className="booking-email-sample-select" title="Select sample booking">
          <option value="single">Pay on arrival · one room</option><option value="group">Pay on arrival · multiple rooms</option>
          <option value="single_online">Online · one room</option><option value="group_online">Online · multiple rooms</option>
        </select>}
      </div>
    </div>
    <div className="booking-email-preview-meta">
      <p title={preview?.subject || ''}>{preview ? <>Subject: {preview.subject}</> : 'Sample booking data · Saved hotel details'}</p>
      <span role="status" className="inline-flex shrink-0 items-center gap-1">{busy ? <><Loader2 className="h-3 w-3 animate-spin" />Updating...</> : stale ? 'Showing last valid preview' : error ? 'Preview unavailable' : branding?.preparing ? 'Preparing logo…' : branding?.dirty ? 'Unsaved logo preview' : 'Up to date'}</span>
      <button type="button" aria-label="Refresh email preview" title="Refresh hotel details" onClick={() => setRefresh(n => n + 1)}><RefreshCw className="h-3.5 w-3.5" /></button>
    </div>
    {preview ? <>
      <div className="booking-email-preview-stage">
        <div ref={stage}>
          <div className="booking-email-preview-frame" style={{ width: frameWidth * scale, height: 820 * scale }}>
            <iframe title={`Sample ${isContact ? 'contact enquiry' : isDaily ? 'daily admin summary' : type.startsWith('admin_') ? 'admin notification' : type.replaceAll('_', ' ')} email preview`} sandbox="" referrerPolicy="no-referrer" srcDoc={previewHtml}
              style={{ display: 'block', border: 0, width: frameWidth, height: 820, transform: `scale(${scale})`, transformOrigin: 'top left' }} />
          </div>
        </div>
      </div>
      <p className="booking-email-preview-note">{isDaily ? 'Sample daily operations data.' : isContact ? 'Sample enquiry data.' : 'Sample booking data.'} Links are inactive. Previewing does not save or send an email.</p>
    </> : <div className="flex min-h-[420px] items-center justify-center rounded-lg border border-dashed border-border bg-slate-50 p-6 text-center text-sm text-text-secondary">{busy ? 'Preparing your email preview...' : 'Complete the required fields to see the email preview.'}</div>}
    <BookingEmailTest type={type} content={content} scenario={selectedScenario} brandingUnsaved={Boolean(branding?.dirty || branding?.preparing)} />
  </section>
}
