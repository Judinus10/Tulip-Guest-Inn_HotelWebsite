import { useEffect, useRef, useState } from 'react'
import { CheckCircle2, Loader2, Mail } from 'lucide-react'
import { sendBookingEmailTest } from '@/services/bookingEmailSettings'

export default function BookingEmailTest({ type, content, scenario }) {
  const [recipient, setRecipient] = useState('')
  const [open, setOpen] = useState(false)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState('')
  const [message, setMessage] = useState('')
  const inFlight = useRef(false)
  const active = useRef(true)
  useEffect(() => { active.current = true; return () => { active.current = false } }, [])
  async function send(event) {
    event.preventDefault()
    if (inFlight.current) return
    inFlight.current = true
    setBusy(true); setError(''); setMessage('')
    const address = recipient.trim()
    try {
      const result = await sendBookingEmailTest(type, content, scenario, address)
      if (!result?.queued) throw new Error('The test email could not be queued.')
      if (active.current) setMessage(`Test email queued for ${address}. Delivery follows the email queue schedule.`)
    } catch (e) {
      if (active.current) setError(e.message || 'Could not queue the test email.')
    } finally {
      inFlight.current = false
      if (active.current) setBusy(false)
    }
  }
  return <div className="booking-email-test">
    <button type="button" aria-expanded={open} aria-controls="booking-email-test-form" onClick={() => setOpen(value => !value)} className="booking-email-test-open"><Mail className="h-4 w-4" />Send test email</button>
    {open && <form id="booking-email-test-form" onSubmit={send} className="booking-email-test-form">
    <label htmlFor="booking-email-test-recipient" className="text-sm font-semibold text-text-primary">Send a test email</label>
    <p className="mb-3 mt-1 text-xs leading-5 text-text-secondary">{type === 'admin_daily_summary' ? 'Uses the current draft and sample daily summary. Real guest records and the scheduled summary are not affected.' : type.startsWith('contact_') ? 'Uses the current draft and sample enquiry. This does not save changes or create an enquiry.' : 'Uses the current draft and selected sample booking. This does not save your changes or create a reservation.'}</p>
    <div className="flex flex-wrap gap-2">
      <input id="booking-email-test-recipient" type="email" autoComplete="email" required maxLength={190} value={recipient} disabled={busy}
        onChange={e => { setRecipient(e.target.value); setMessage(''); setError('') }} placeholder="Enter your email address"
        className="h-10 min-w-0 flex-1 rounded-lg border border-border bg-white px-3 text-sm text-text-primary outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-100 disabled:opacity-50" />
      <button type="submit" disabled={busy} className="inline-flex h-10 items-center justify-center gap-2 rounded-lg border border-blue-200 bg-white px-4 text-sm font-semibold text-blue-700 hover:bg-blue-50 disabled:opacity-50">
        {busy ? <Loader2 className="h-4 w-4 animate-spin" /> : <Mail className="h-4 w-4" />}{busy ? 'Queueing...' : 'Send test email'}
      </button>
    </div>
    {message && <p role="status" className="mt-3 flex items-start gap-2 rounded-lg bg-emerald-50 p-3 text-xs leading-5 text-emerald-800"><CheckCircle2 className="mt-0.5 h-4 w-4 shrink-0" />{message}</p>}
    {error && <p role="alert" className="mt-3 rounded-lg bg-red-50 p-3 text-xs leading-5 text-red-700">{error}</p>}
  </form>}
  </div>
}
