import EmailBrandingSettings from '@/components/ui/EmailBrandingSettings'
import './WebsiteBookingEmails.css'
import BookingEmailPreview from '@/components/ui/BookingEmailPreview'
import { useEffect, useRef, useState } from 'react'
import { AlertCircle, CheckCircle2, ChevronDown, Info, Loader2, RotateCcw, Save } from 'lucide-react'
import { SectionCard } from '@/components/ui/page-header'
import { Button } from '@/components/ui/button'
import { Input, Label, Textarea } from '@/components/ui/input'
import BookingEmailTextEditor from '@/components/ui/BookingEmailTextEditor'
import { fetchBookingEmailSettings, saveBookingEmailSettings } from '@/services/bookingEmailSettings'

const types = { received: { title: 'Booking Received', description: 'Sent when a booking request is submitted. This does not confirm the reservation.' },
  confirmed: { title: 'Booking Confirmed', description: 'Sent when the property confirms the reservation.' },
  contact_customer: { title: 'Customer acknowledgement', description: 'Sent to the customer after a contact enquiry is received.' },
  contact_admin: { title: 'Admin enquiry notification', description: 'Sent to the hotel with enquiry details. Reply goes to the customer.' },
  admin_new_booking: { title: 'New booking', description: 'Sent to the hotel when a booking is received.' },
  admin_payment_received: { title: 'Payment received', description: 'Sent to the hotel after a successful payment.' },
  admin_payment_failed: { title: 'Payment failed', description: 'Sent to the hotel after a failed payment attempt.' } }
const categoryOf = key => key.startsWith('contact_') ? 'contact' : key.startsWith('admin_') ? 'admin' : 'guest'
const fieldLabels = { subject: 'Subject', greeting: 'Greeting', introduction_html: 'Introduction', closing_html: 'Closing message', footer_note: 'Footer note' }
const limits = { subject: 200, greeting: 200, introduction_html: 6000, closing_html: 4000, footer_note: 500 }
const bytes = text => new TextEncoder().encode(text).length
const same = (a, b) => Object.keys(limits).every(key => a?.[key] === b?.[key])

export default function WebsiteBookingEmails() {
  const [type, setType] = useState('received')
  const [brandingRevision, setBrandingRevision] = useState(0)
  const [settings, setSettings] = useState(null)
  const [drafts, setDrafts] = useState({})
  const [loading, setLoading] = useState(true)
  const [busy, setBusy] = useState(false)
  const [loadError, setLoadError] = useState('')
  const [error, setError] = useState('')
  const [message, setMessage] = useState('')
  const [target, setTarget] = useState('introduction_html')
  const [linkReload, setLinkReload] = useState(false)
  const inputs = useRef({})
  const positions = useRef({})
  const intro = useRef(null)
  const closing = useRef(null)
  const requestId = useRef(0)
  const draft = drafts[type]
  const isContact = type.startsWith('contact_')
  const category = categoryOf(type)
  const placeholders = settings?.placeholders_by_type?.[type] || settings?.placeholders || {}
  const dirty = settings && draft && !same(draft, settings.templates[type].content)
  const anyDirty = settings && Object.keys(types).some(key => !same(drafts[key], settings.templates[key].content))
  useEffect(() => {
    if (!anyDirty) return undefined
    const warn = event => { event.preventDefault(); event.returnValue = '' }
    window.addEventListener('beforeunload', warn)
    return () => window.removeEventListener('beforeunload', warn)
  }, [anyDirty])
  useEffect(() => {
    let active = true
    fetchBookingEmailSettings().then(data => {
      if (!active) return
      setSettings(data); setDrafts(Object.fromEntries(Object.entries(data.templates).map(([key, template]) => [key, { ...template.content }])))
    }).catch(e => { if (active) setLoadError(e.message || 'Could not load booking email settings.') })
      .finally(() => { if (active) setLoading(false) })
    return () => { active = false; requestId.current++ }
  }, [])
  const change = (field, value) => { setDrafts(current => ({ ...current, [type]: { ...current[type], [field]: value } })); setMessage(''); setError('') }
  const remember = (field, node) => { setTarget(field); positions.current[field] = [node.selectionStart ?? node.value.length, node.selectionEnd ?? node.value.length] }
  const insert = token => {
    if (!token || busy) return
    const value = `{${token}}`
    if (target === 'introduction_html') intro.current?.insertText(value)
    else if (target === 'closing_html') closing.current?.insertText(value)
    else {
      const node = inputs.current[target]
      const [start, end] = positions.current[target] || [draft[target].length, draft[target].length]
      change(target, draft[target].slice(0, start) + value + draft[target].slice(end))
      requestAnimationFrame(() => { node?.focus(); node?.setSelectionRange(start + value.length, start + value.length) })
    }
  }
  async function save() {
    setError(''); setMessage(''); setLinkReload(false)
    for (const [field, value] of Object.entries(draft)) if (bytes(value) > limits[field]) { setError(`${fieldLabels[field]} is too long. Shorten the text before saving.`); return }
    const currentType = type
    const currentRequest = ++requestId.current
    setBusy(true)
    try {
      const saved = await saveBookingEmailSettings(currentType, draft, settings.templates[currentType].version)
      if (requestId.current !== currentRequest) return
      setSettings(current => ({ ...current, templates: { ...current.templates, [currentType]: saved } }))
      setDrafts(current => ({ ...current, [currentType]: { ...saved.content } }))
      setMessage(`${types[currentType].title} content saved.`)
    } catch (e) {
      if (requestId.current === currentRequest) { setError(e.message || 'Could not save email content.'); setLinkReload(e.status === 409) }
    } finally { if (requestId.current === currentRequest) setBusy(false) }
  }
  if (loading) return <SectionCard><p role="status" className="flex items-center gap-2 text-sm text-text-secondary"><Loader2 className="h-4 w-4 animate-spin" />Loading booking emails...</p></SectionCard>
  if (loadError) return <SectionCard><p role="alert" className="text-sm text-red-700">{loadError}</p><Button className="mt-4" variant="outline" onClick={() => window.location.reload()}>Reload settings</Button></SectionCard>
  return <div className="booking-email-page">
    <div className="mb-4 flex flex-wrap gap-2" role="group" aria-label="Email category">
      {[['received', 'Guest bookings'], ['contact_customer', 'Contact enquiries'], ['admin_new_booking', 'Admin notifications']].map(([key, title]) => <button key={key} type="button" disabled={busy} aria-pressed={categoryOf(key) === category}
        onClick={() => { setType(key); setTarget('introduction_html'); positions.current = {}; setError(''); setMessage('') }}
        className={`rounded-lg border px-4 py-2 text-sm font-semibold ${categoryOf(key) === category ? 'border-blue-600 bg-blue-600 text-white' : 'border-border bg-white text-text-secondary'}`}>{title}</button>)}
    </div>
    <EmailBrandingSettings onSaved={() => setBrandingRevision(n => n + 1)} />
    <div className="booking-email-workspace">
      <section className="booking-email-card booking-email-editor" aria-label="Email content editor">
        <div className="booking-email-card-heading"><h2>Email content</h2>
          {dirty && <span className="booking-email-draft-badge">Unsaved changes</span>}
        </div>
        <div role="tablist" aria-label="Email type" className="booking-email-type-tabs">
          {Object.entries(types).filter(([key]) => categoryOf(key) === category).map(([key, item]) => <button key={key} type="button" role="tab" aria-selected={type === key} disabled={busy}
            onClick={() => { setType(key); setTarget('introduction_html'); positions.current = {}; setError(''); setMessage(''); setLinkReload(false) }}
            className={`flex-1 rounded-lg px-3 py-2.5 text-sm font-semibold transition disabled:opacity-50 ${type === key ? 'bg-blue-600 text-white shadow-sm' : 'text-text-secondary hover:bg-white'}`}>{item.title}{settings && !same(drafts[key], settings.templates[key].content) ? ' •' : ''}</button>)}
        </div>
        <p className="booking-email-type-description">{types[type].description}</p>
        {error && <div role="alert" className="mb-5 rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-700"><span className="flex items-start gap-2"><AlertCircle className="mt-0.5 h-4 w-4 shrink-0" />{error}</span>{linkReload && <p className="mt-2">Copy your draft before reloading this page.</p>}</div>}
        {message && <p role="status" className="mb-5 flex items-center gap-2 rounded-xl bg-emerald-50 p-3 text-sm text-emerald-800"><CheckCircle2 className="h-4 w-4" />{message}</p>}
        <div className="booking-email-fields" key={type}>
          {Object.entries(fieldLabels).map(([field, label]) => <div key={field} className={`booking-email-field ${field === 'closing_html' ? 'booking-email-field--closing' : ''}`}>
            <div className="mb-2 flex items-center justify-between gap-3"><Label id={`email-${field}-label`} htmlFor={`email-${field}`}>{label}{['subject', 'greeting', 'introduction_html'].includes(field) && <span className="ml-1 text-blue-600">*</span>}</Label>
</div>
            {field.endsWith('_html') ? <BookingEmailTextEditor ref={field === 'introduction_html' ? intro : closing} id={`email-${field}`} value={draft[field]} onChange={value => change(field, value)} onFocus={() => setTarget(field)} disabled={busy} />
              : field === 'footer_note' ? <Textarea id={`email-${field}`} value={draft[field]} disabled={busy} ref={node => { inputs.current[field] = node }} onFocus={e => remember(field, e.target)} onSelect={e => remember(field, e.target)} onBlur={e => remember(field, e.target)} onChange={e => change(field, e.target.value)} />
                : <Input id={`email-${field}`} value={draft[field]} disabled={busy} ref={node => { inputs.current[field] = node }} onFocus={e => remember(field, e.target)} onSelect={e => remember(field, e.target)} onBlur={e => remember(field, e.target)} onChange={e => change(field, e.target.value)} />}
          </div>)}
        </div>
        <div className="booking-email-insert">
          <Label htmlFor="email-placeholder">Insert {isContact ? 'enquiry' : 'booking'} detail</Label>
          <div className="booking-email-insert-row">
            <div className="relative"><select id="email-placeholder" value="" disabled={busy} onChange={e => insert(e.target.value)} aria-describedby="email-placeholder-target">
              <option value="">Insert {isContact ? 'enquiry' : 'booking'} detail</option>{Object.entries(placeholders).map(([key, label]) => <option key={key} value={key}>{label}</option>)}
            </select><ChevronDown className="pointer-events-none absolute right-3 top-2.5 h-4 w-4 text-text-secondary" /></div>
            <div className="booking-email-token-chips">{Object.entries(placeholders).filter(([key]) => key !== 'hotel_name').map(([key, label]) =>
              <button type="button" key={key} disabled={busy} title={`Insert ${label.toLowerCase()}`} onMouseDown={e => e.preventDefault()} onClick={() => insert(key)}>{'{' + key + '}'}</button>)}</div>
          </div>
          <p id="email-placeholder-target" className="booking-email-insert-target">Insert into: <strong>{fieldLabels[target]}</strong></p>
        </div>
        <div className="booking-email-info"><Info className="h-4 w-4 shrink-0" /><span>{isContact ? 'Sender details and the original enquiry message are filled in automatically.' : 'Room assignments, guest counts and payment details are filled in automatically.'}</span></div>
        <div className="booking-email-actions">
          <button type="button" disabled={busy || !dirty} onClick={() => { setDrafts(current => ({ ...current, [type]: { ...settings.templates[type].content } })); setError(''); setMessage('Changes discarded.') }} className="text-sm font-semibold text-text-secondary hover:text-blue-700 disabled:opacity-40">Discard changes</button>
          <div className="flex flex-wrap gap-3"><Button variant="outline" disabled={busy} onClick={() => { setDrafts(current => ({ ...current, [type]: { ...settings.defaults[type] } })); setError(''); setMessage('Default wording restored in the editor. Save to apply it.') }}><RotateCcw className="h-4 w-4" />Reset to default</Button>
            <Button disabled={busy || !dirty} onClick={save}>{busy ? <Loader2 className="h-4 w-4 animate-spin" /> : <Save className="h-4 w-4" />}{busy ? 'Saving...' : 'Save changes'}</Button></div>
        </div>
      </section>
      <aside className="booking-email-preview-column">
        <BookingEmailPreview type={type} content={draft} brandingRevision={brandingRevision} />
      </aside>
    </div>
  </div>
}
