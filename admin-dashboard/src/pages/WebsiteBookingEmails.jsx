import './WebsiteBookingEmails.css'
import { notifyError, notifyInfo, notifySuccess } from '@/utils/notifications'
import BookingEmailPreview from '@/components/ui/BookingEmailPreview'
import { useEffect, useRef, useState } from 'react'
import { ChevronDown, ImagePlus, Info, Loader2, RotateCcw, Save } from 'lucide-react'
import { SectionCard } from '@/components/ui/page-header'
import { Button } from '@/components/ui/button'
import { Input, Label, Textarea } from '@/components/ui/input'
import BookingEmailTextEditor from '@/components/ui/BookingEmailTextEditor'
import { fetchBookingEmailSettings, saveBookingEmailSettings } from '@/services/bookingEmailSettings'
import { apiFetch, buildApiUrl, readJsonResponse } from '@/services/apiClient'

const emailBrandingEndpoint = buildApiUrl('/settings/email-branding.php')

async function convertLogoToPng(file) {
  if (!file.type.startsWith('image/') || file.size > 20 * 1024 * 1024) throw new Error('Choose an image file under 20 MB.')
  const objectUrl = URL.createObjectURL(file)
  try {
    const image = new Image()
    await new Promise((resolve, reject) => {
      image.onload = resolve
      image.onerror = () => reject(new Error('This image format cannot be opened by your browser.'))
      image.src = objectUrl
    })
    const ratio = image.naturalWidth / image.naturalHeight
    if (!image.naturalWidth || !image.naturalHeight || ratio < 0.25 || ratio > 8) throw new Error('Choose a logo with a suitable shape (width between ¼ and 8 times its height).')
    let scale = Math.min(1, 2000 / image.naturalWidth, 2000 / image.naturalHeight)
    for (let attempt = 0; attempt < 8; attempt++) {
      const canvas = document.createElement('canvas')
      canvas.width = Math.max(1, Math.round(image.naturalWidth * scale))
      canvas.height = Math.max(1, Math.round(image.naturalHeight * scale))
      const context = canvas.getContext('2d')
      if (!context) throw new Error('Could not prepare this image. Choose another file.')
      context.drawImage(image, 0, 0, canvas.width, canvas.height)
      const blob = await new Promise(resolve => canvas.toBlob(resolve, 'image/png'))
      if (!blob) throw new Error('Could not prepare this image. Choose another file.')
      if (blob.size <= 2 * 1024 * 1024) return blob
      scale *= 0.75
    }
    throw new Error('This logo is too large to use. Choose a smaller image.')
  } finally { URL.revokeObjectURL(objectUrl) }
}

const types = { admin_daily_summary: { title: 'Daily stay summary', description: 'One scheduled email for the hotel, containing today’s arrivals and departures. Booking details are filled automatically. The existing cron schedule controls delivery.' }, received: { title: 'Booking Received', description: 'Sent when a booking request is submitted. This does not confirm the reservation.' },
  payment_pending: { title: 'Payment pending', description: 'Sent to the guest. Your online payment has not been completed. Your reservation is not confirmed by this message. Please review your booking before the hold expires.' },
  payment_failed: { title: 'Payment failed', description: 'Sent to the guest. Your payment attempt was unsuccessful. Please review your booking or contact us before trying again. This email does not confirm a reservation.' },
  payment_received: { title: 'Payment received', description: 'Sent to the guest. We have recorded your payment. Please check the booking status below; a payment receipt alone does not confirm a reservation.' },
  expired: { title: 'Booking hold expired', description: 'Sent to the guest. Your unpaid online booking hold has expired and the room is no longer held. Please check availability before making a new booking.' },
  cancelled: { title: 'Booking cancelled', description: 'Sent to the guest. Your reservation has been cancelled. Please contact us if you have questions. Any refund must be verified separately.' },
  status_updated: { title: 'Booking details updated', description: 'Sent to the guest. Your booking or payment details have changed. Please review the current statuses below.' },
  admin_payment_pending: { title: 'Payment pending', description: 'Sent to the hotel. Online payment for the booking by {guest_name} has not been completed. Review the current booking and payment status.' },
  admin_booking_expired: { title: 'Booking hold expired', description: 'Sent to the hotel. The unpaid online reservation hold for {guest_name} has expired. Review the booking record before following up.' },
  admin_booking_cancelled: { title: 'Booking cancelled', description: 'Sent to the hotel. The booking by {guest_name} has been cancelled. Review the booking and any refund requirements separately.' },
  confirmed: { title: 'Booking Confirmed', description: 'Sent when the property confirms the reservation.' },
  contact_customer: { title: 'Customer acknowledgement', description: 'Sent to the customer after a contact enquiry is received.' },
  contact_admin: { title: 'Admin enquiry notification', description: 'Sent to the hotel with enquiry details. Reply goes to the customer.' },
  admin_new_booking: { title: 'New booking', description: 'Sent to the hotel when a booking is received.' },
  admin_payment_received: { title: 'Payment received', description: 'Sent to the hotel after a successful payment.' },
  admin_payment_failed: { title: 'Payment failed', description: 'Sent to the hotel after a failed payment attempt.' } }
const categoryOf = key => key === 'admin_daily_summary' ? 'daily' : key.startsWith('contact_') ? 'contact' : 'booking'
const fieldLabels = { subject: 'Subject', heading: 'Email heading', greeting: 'Greeting', introduction_html: 'Introduction', please_note: 'Please note (optional)', closing_html: 'Closing message', footer_note: 'Footer note' }
const limits = { subject: 200, heading: 200, greeting: 200, introduction_html: 6000, please_note: 1500, closing_html: 4000, footer_note: 500 }
const bytes = text => new TextEncoder().encode(text).length
const same = (a, b) => Object.keys(limits).every(key => (a?.[key] ?? '') === (b?.[key] ?? ''))

export default function WebsiteBookingEmails() {
  const [type, setType] = useState('received')
  const [brandingRevision, setBrandingRevision] = useState(0)
  const [brandingSaved, setBrandingSaved] = useState(null)
  const [localLogo, setLocalLogo] = useState('')
  const [logoWidth, setLogoWidth] = useState(76)
  const [settings, setSettings] = useState(null)
  const [drafts, setDrafts] = useState({})
  const [loading, setLoading] = useState(true)
  const [busy, setBusy] = useState(false)
  const [loadError, setLoadError] = useState('')
  const [target, setTarget] = useState('introduction_html')
  const inputs = useRef({})
  const positions = useRef({})
  const intro = useRef(null)
  const closing = useRef(null)
  const requestId = useRef(0)
  const draft = drafts[type]
  const isContact = type.startsWith('contact_')
  const isDaily = type === 'admin_daily_summary'
  const isAdminBooking = type.startsWith('admin_') && !isDaily
  const category = categoryOf(type)
  const placeholders = settings?.placeholders_by_type?.[type] || settings?.placeholders || {}
  const logoDirty = Boolean(brandingSaved && logoWidth !== brandingSaved.width)
  const brandingDraft = brandingSaved ? { logo: localLogo || brandingSaved.logo, width: logoWidth, dirty: logoDirty } : null
  const dirty = settings && draft && (!same(draft, settings.templates[type].content) || logoDirty)
  const anyDirty = logoDirty || (settings && Object.keys(types).some(key => !same(drafts[key], settings.templates[key].content)))
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
      if (!data.branding) throw new Error('Could not load email logo size. Update the email settings API and reload.')
      setBrandingSaved(data.branding); setLogoWidth(data.branding.width)
      setSettings(data); setDrafts(Object.fromEntries(Object.entries(data.templates).map(([key, template]) => [key, { ...template.content }])))
    }).catch(e => { if (active) { setLoadError('Email settings are unavailable.'); notifyError(e, { duration: 6000 }) } })
      .finally(() => { if (active) setLoading(false) })
    return () => { active = false; requestId.current++ }
  }, [])
  const change = (field, value) => { setDrafts(current => ({ ...current, [type]: { ...current[type], [field]: value } })); }
  async function changeLogo(file) {
    if (!file || busy) return
    setBusy(true)
    try {
      const png = await convertLogoToPng(file)
      const localPreview = await new Promise((resolve, reject) => {
        const reader = new FileReader()
        reader.onload = () => resolve(String(reader.result || ''))
        reader.onerror = () => reject(new Error('Could not prepare the logo preview.'))
        reader.readAsDataURL(png)
      })
      setLocalLogo(localPreview)
      const form = new FormData()
      form.set('logo', png, 'email-logo.png')
      form.set('width', String(brandingSaved.width))
      form.set('version', String(brandingSaved.version))
      form.set('reset', '0')
      const response = await apiFetch(emailBrandingEndpoint, { method: 'POST', body: form })
      const result = await readJsonResponse(response)
      setBrandingSaved(result.data)
      setLocalLogo('')
      setBrandingRevision(value => value + 1)
      notifySuccess('Email logo updated.', { duration: 4000 })
    } catch (error) {
      setLocalLogo('')
      notifyError(error, { duration: 6000 })
    } finally { setBusy(false) }
  }
  async function resetLogoAndSize() {
    if (busy || !brandingSaved) return
    setBusy(true)
    try {
      const form = new FormData()
      form.set('width', String(brandingSaved.default_width))
      form.set('version', String(brandingSaved.version))
      form.set('reset', '1')
      const response = await apiFetch(emailBrandingEndpoint, { method: 'POST', body: form })
      const result = await readJsonResponse(response)
      setBrandingSaved(result.data)
      setLogoWidth(result.data.width)
      setLocalLogo('')
      setBrandingRevision(value => value + 1)
      notifySuccess('Original Tulip logo and default size restored.', { duration: 4000 })
    } catch (error) { notifyError(error, { duration: 6000 }) }
    finally { setBusy(false) }
  }
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
    
    for (const [field, value] of Object.entries(draft)) if (bytes(value) > limits[field]) { notifyError({ message: `${fieldLabels[field]} is too long. Shorten the text before saving.` }, { duration: 6000 }); return }
    const currentType = type
    const currentRequest = ++requestId.current
    setBusy(true)
    try {
      const saved = await saveBookingEmailSettings(currentType, draft, settings.templates[currentType].version, logoDirty ? { width: logoWidth, version: brandingSaved.version } : null)
      if (requestId.current !== currentRequest) return
      setSettings(current => ({ ...current, templates: { ...current.templates, [currentType]: saved } }))
      setDrafts(current => ({ ...current, [currentType]: { ...saved.content } }))
      if (saved.branding) { setBrandingSaved(saved.branding); setLogoWidth(saved.branding.width); setBrandingRevision(n => n + 1) }
      notifySuccess(`${types[currentType].title} changes saved.`, { duration: 4000 })
    } catch (e) {
      if (requestId.current === currentRequest) { notifyError(e.status === 409 ? { status: 409, message: `${e.message || 'These settings changed in another session.'} Copy your draft before reloading.` } : e, { duration: 6000 }) }
    } finally { if (requestId.current === currentRequest) setBusy(false) }
  }
  if (loading) return <SectionCard><p role="status" className="flex items-center gap-2 text-sm text-text-secondary"><Loader2 className="h-4 w-4 animate-spin" />Loading booking emails...</p></SectionCard>
  if (loadError) return <SectionCard><p role="alert" className="text-sm text-red-700">{loadError}</p><Button className="mt-4" variant="outline" onClick={() => window.location.reload()}>Reload settings</Button></SectionCard>
  return <div className="booking-email-page">
    <div className="mb-4 flex flex-wrap gap-2" role="group" aria-label="Email category">
      {[['received', 'Booking emails'], ['contact_customer', 'Contact emails'], ['admin_daily_summary', 'Daily admin summary']].map(([key, title]) => <button key={key} type="button" disabled={busy} aria-pressed={categoryOf(key) === category}
        onClick={() => { setType(key); setTarget('introduction_html'); positions.current = {}; }}
        className={`rounded-lg border px-4 py-2 text-sm font-semibold ${categoryOf(key) === category ? 'border-blue-600 bg-blue-600 text-white' : 'border-border bg-white text-text-secondary'}`}>{title}</button>)}
    </div>
    <div className="booking-email-workspace">
      <section className="booking-email-card booking-email-editor" aria-label="Email content editor">
        <div className="booking-email-card-heading"><h2>Email content</h2>
          {dirty && <span className="booking-email-draft-badge">Unsaved changes</span>}
        </div>
        {category === 'booking' ? <div className="booking-email-selector-row">
          <div className="booking-email-recipient-buttons" role="group" aria-label="Booking email recipient">
          {[['received','Guest emails',false],['admin_new_booking','Admin booking notifications',true]].map(([key,label,admin]) => <button key={key} type="button" disabled={busy} aria-pressed={isAdminBooking === admin}
            onClick={() => { setType(key); setTarget('introduction_html'); positions.current = {} }}
            className={`rounded-lg border px-3 py-2 text-xs font-semibold ${isAdminBooking === admin ? 'border-blue-300 bg-blue-50 text-blue-700' : 'border-border text-text-secondary'}`}>{label}</button>)}
          </div>
          <div className="booking-email-template-select">
            <label htmlFor="email-template-select" className="sr-only">Booking email template</label>
            <select id="email-template-select" value={type} disabled={busy}
              onChange={e => { setType(e.target.value); setTarget('introduction_html'); positions.current = {} }}>
              {Object.entries(types).filter(([key]) => categoryOf(key) === 'booking' && key.startsWith('admin_') === isAdminBooking).map(([key, item]) =>
                <option key={key} value={key}>{item.title}{settings && !same(drafts[key], settings.templates[key].content) ? ' •' : ''}</option>)}
            </select>
            <ChevronDown className="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-text-secondary" />
          </div>
        </div> : <>
        <div role="group" aria-label="Email type" className="booking-email-recipient-buttons">
          {Object.entries(types).filter(([key]) => categoryOf(key) === category && (category !== 'booking' || key.startsWith('admin_') === isAdminBooking)).map(([key, item]) => <button key={key} type="button" aria-pressed={type === key} disabled={busy}
            onClick={() => { setType(key); setTarget('introduction_html'); positions.current = {} }}
            className={`rounded-lg border px-3 py-2 text-xs font-semibold disabled:opacity-50 ${type === key ? 'border-blue-300 bg-blue-50 text-blue-700' : 'border-border text-text-secondary'}`}>{item.title}{settings && !same(drafts[key], settings.templates[key].content) ? ' •' : ''}</button>)}
        </div>
        </>}
        <p className="booking-email-type-description">{types[type].description}</p>
        <div className="mb-4 rounded-lg border border-border bg-slate-50 px-3 py-3">
          <div className="mb-2 flex items-center justify-between gap-3"><label htmlFor="email-logo-width" className="text-xs font-semibold text-text-primary">Logo size</label><span className="text-xs text-text-secondary">{logoWidth} px</span></div>
          <div className="flex items-center gap-3"><input id="email-logo-width" type="range" min="40" max="240" step="1" value={logoWidth} disabled={busy}
            onChange={e => { setLogoWidth(Number(e.target.value)); }} className="min-w-0 flex-1 accent-blue-600" />
            <button type="button" disabled={busy} onClick={resetLogoAndSize}
              className="inline-flex shrink-0 items-center gap-1 rounded-md border border-border bg-white px-2.5 py-1.5 text-xs font-semibold text-text-secondary disabled:opacity-50" title="Restore the original logo and default size"><RotateCcw className="h-3.5 w-3.5" />Reset</button>
            <label className={`inline-flex shrink-0 cursor-pointer items-center gap-1 rounded-md border border-blue-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-blue-700 ${busy ? 'pointer-events-none opacity-50' : ''}`}>
              <ImagePlus className="h-3.5 w-3.5" />Change Logo
              <input className="sr-only" type="file" accept="image/*" disabled={busy} aria-label="Choose an email logo image" onChange={event => { const file = event.target.files?.[0]; event.target.value = ''; changeLogo(file) }} />
            </label>
          </div>
          <p className="mt-2 text-xs text-text-secondary">Choose an image in any format your browser can open. It will be converted for email use and updated immediately. Reset restores the original logo and its default size.</p>
        </div>
        <div className="booking-email-fields" key={type}>
          {Object.entries(fieldLabels).map(([field, label]) => <div key={field} className={`booking-email-field ${field === 'closing_html' ? 'booking-email-field--closing' : ''}`}>
            <div className="mb-2 flex items-center justify-between gap-3"><Label id={`email-${field}-label`} htmlFor={`email-${field}`}>{label}{['subject', 'heading', 'greeting', 'introduction_html'].includes(field) && <span className="ml-1 text-blue-600">*</span>}</Label>
</div>
            {field.endsWith('_html') ? <BookingEmailTextEditor ref={field === 'introduction_html' ? intro : closing} id={`email-${field}`} value={draft[field] ?? ''} onChange={value => change(field, value)} onFocus={() => setTarget(field)} disabled={busy} />
              : ['footer_note', 'please_note'].includes(field) ? <Textarea id={`email-${field}`} value={draft[field] ?? ''} disabled={busy} ref={node => { inputs.current[field] = node }} onFocus={e => remember(field, e.target)} onSelect={e => remember(field, e.target)} onBlur={e => remember(field, e.target)} onChange={e => change(field, e.target.value)} />
                : <Input id={`email-${field}`} value={draft[field] ?? ''} disabled={busy} ref={node => { inputs.current[field] = node }} onFocus={e => remember(field, e.target)} onSelect={e => remember(field, e.target)} onBlur={e => remember(field, e.target)} onChange={e => change(field, e.target.value)} />}
            {field === 'subject' && <p className="mt-2 text-xs text-text-secondary">Shown in the recipient’s inbox and above the preview.</p>}
            {field === 'heading' && <p className="mt-2 text-xs text-text-secondary">The large title inside the email. Changes appear in the live preview.</p>}
            {field === 'please_note' && <p className="mt-2 text-xs text-text-secondary">Shown in a highlighted box near the end of the email. Leave empty to hide. Saved separately for each email template; maximum 1,500 UTF-8 bytes.</p>}
          </div>)}
        </div>
        <div className="booking-email-insert">
          <Label htmlFor="email-placeholder">Insert {isDaily ? 'summary' : isContact ? 'enquiry' : 'booking'} detail</Label>
          <div className="booking-email-insert-row">
            <div className="relative"><select id="email-placeholder" value="" disabled={busy} onChange={e => insert(e.target.value)} aria-describedby="email-placeholder-target">
              <option value="">Insert {isDaily ? 'summary' : isContact ? 'enquiry' : 'booking'} detail</option>{Object.entries(placeholders).map(([key, label]) => <option key={key} value={key}>{label}</option>)}
            </select><ChevronDown className="pointer-events-none absolute right-3 top-2.5 h-4 w-4 text-text-secondary" /></div>
            <div className="booking-email-token-chips">{Object.entries(placeholders).filter(([key]) => key !== 'hotel_name').map(([key, label]) =>
              <button type="button" key={key} disabled={busy} title={`Insert ${label.toLowerCase()}`} onMouseDown={e => e.preventDefault()} onClick={() => insert(key)}>{'{' + key + '}'}</button>)}</div>
          </div>
          <p id="email-placeholder-target" className="booking-email-insert-target">Insert into: <strong>{fieldLabels[target]}</strong></p>
        </div>
        <div className="booking-email-info"><Info className="h-4 w-4 shrink-0" /><span>{isDaily ? 'Arrival and departure lists, room assignments, guest counts and payment statuses are filled automatically. No occupancy or daily revenue estimates are included.' : isContact ? 'Sender details and the original enquiry message are filled in automatically.' : 'Room assignments, guest counts and payment details are filled in automatically.'}</span></div>
        <div className="booking-email-actions">
          <button type="button" disabled={busy || !dirty} onClick={() => { setDrafts(current => ({ ...current, [type]: { ...settings.templates[type].content } })); setLogoWidth(brandingSaved.width); notifyInfo('Changes discarded.', { duration: 4000 }) }} className="text-sm font-semibold text-text-secondary hover:text-blue-700 disabled:opacity-40">Discard changes</button>
          <div className="flex flex-wrap gap-3"><Button variant="outline" disabled={busy} onClick={() => { setDrafts(current => ({ ...current, [type]: { ...settings.defaults[type] } })); notifyInfo('Default wording restored in the editor. Save to apply it.', { duration: 4000 }) }}><RotateCcw className="h-4 w-4" />Reset to default</Button>
            <Button disabled={busy || !dirty} onClick={save}>{busy ? <Loader2 className="h-4 w-4 animate-spin" /> : <Save className="h-4 w-4" />}{busy ? 'Saving...' : 'Save changes'}</Button></div>
        </div>
      </section>
      <aside className="booking-email-preview-column">
        <BookingEmailPreview type={type} content={draft} brandingRevision={brandingRevision} branding={brandingDraft} />
      </aside>
    </div>
  </div>
}
