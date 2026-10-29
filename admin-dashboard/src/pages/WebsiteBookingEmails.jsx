import BookingEmailPreview from '@/components/ui/BookingEmailPreview'
import { useEffect, useRef, useState } from 'react'
import { AlertCircle, CheckCircle2, ChevronDown, Info, Loader2, Mail, RotateCcw, Save, Braces } from 'lucide-react'
import { SectionCard } from '@/components/ui/page-header'
import { Button } from '@/components/ui/button'
import { Input, Label, Textarea } from '@/components/ui/input'
import BookingEmailTextEditor from '@/components/ui/BookingEmailTextEditor'
import { fetchBookingEmailSettings, saveBookingEmailSettings } from '@/services/bookingEmailSettings'

const types = { received: { title: 'Booking Received', description: 'Sent when a booking request is submitted. This does not confirm the reservation.' },
  confirmed: { title: 'Booking Confirmed', description: 'Sent when the property confirms the reservation.' } }
const fieldLabels = { subject: 'Subject', greeting: 'Greeting', introduction_html: 'Introduction', closing_html: 'Closing message', footer_note: 'Footer note' }
const limits = { subject: 200, greeting: 200, introduction_html: 6000, closing_html: 4000, footer_note: 500 }
const bytes = text => new TextEncoder().encode(text).length
const same = (a, b) => Object.keys(limits).every(key => a?.[key] === b?.[key])

export default function WebsiteBookingEmails() {
  const [type, setType] = useState('received')
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
  return <div className="space-y-5">
    <SectionCard>
      <div className="flex flex-wrap items-center justify-between gap-4">
        <div className="flex items-start gap-3"><div className="rounded-2xl bg-blue-50 p-3 text-blue-600"><Mail className="h-6 w-6" /></div>
          <div><h2 className="text-xl font-bold text-text-primary">Booking Emails</h2><p className="mt-1 text-sm text-text-secondary">Customize guest emails and preview changes before saving.</p></div></div>
        <span className={`rounded-full px-3 py-1.5 text-xs font-semibold ${dirty ? 'bg-amber-50 text-amber-800' : 'bg-slate-100 text-slate-600'}`}>{dirty ? 'Unsaved changes' : 'Content up to date'}</span>
      </div>
    </SectionCard>
    <div className="grid items-start gap-5 2xl:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
      <SectionCard>
        <div role="tablist" aria-label="Email type" className="mb-3 flex gap-1 rounded-xl bg-slate-100 p-1">
          {Object.entries(types).map(([key, item]) => <button key={key} type="button" role="tab" aria-selected={type === key} disabled={busy}
            onClick={() => { setType(key); setTarget('introduction_html'); positions.current = {}; setError(''); setMessage(''); setLinkReload(false) }}
            className={`flex-1 rounded-lg px-3 py-2.5 text-sm font-semibold transition disabled:opacity-50 ${type === key ? 'bg-blue-600 text-white shadow-sm' : 'text-text-secondary hover:bg-white'}`}>{item.title}{settings && !same(drafts[key], settings.templates[key].content) ? ' •' : ''}</button>)}
        </div>
        <p className="mb-6 text-sm leading-6 text-text-secondary">{types[type].description}</p>
        {error && <div role="alert" className="mb-5 rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-700"><span className="flex items-start gap-2"><AlertCircle className="mt-0.5 h-4 w-4 shrink-0" />{error}</span>{linkReload && <p className="mt-2">Copy your draft before reloading this page.</p>}</div>}
        {message && <p role="status" className="mb-5 flex items-center gap-2 rounded-xl bg-emerald-50 p-3 text-sm text-emerald-800"><CheckCircle2 className="h-4 w-4" />{message}</p>}
        <div className="space-y-5" key={type}>
          {Object.entries(fieldLabels).map(([field, label]) => <div key={field}>
            <div className="mb-2 flex items-center justify-between gap-3"><Label id={`email-${field}-label`} htmlFor={`email-${field}`}>{label}{['subject', 'greeting', 'introduction_html'].includes(field) && <span className="ml-1 text-blue-600">*</span>}</Label>
</div>
            {field.endsWith('_html') ? <BookingEmailTextEditor ref={field === 'introduction_html' ? intro : closing} id={`email-${field}`} value={draft[field]} onChange={value => change(field, value)} onFocus={() => setTarget(field)} disabled={busy} />
              : field === 'footer_note' ? <Textarea id={`email-${field}`} value={draft[field]} disabled={busy} ref={node => { inputs.current[field] = node }} onFocus={e => remember(field, e.target)} onSelect={e => remember(field, e.target)} onBlur={e => remember(field, e.target)} onChange={e => change(field, e.target.value)} />
                : <Input id={`email-${field}`} value={draft[field]} disabled={busy} ref={node => { inputs.current[field] = node }} onFocus={e => remember(field, e.target)} onSelect={e => remember(field, e.target)} onBlur={e => remember(field, e.target)} onChange={e => change(field, e.target.value)} />}
          </div>)}
        </div>
        <div className="mt-6 rounded-xl border border-blue-100 bg-blue-50/50 p-4">
          <Label htmlFor="email-placeholder">Insert booking detail</Label>
          <p className="mb-3 mt-1 text-xs text-text-secondary">Insert into: <span className="font-semibold">{fieldLabels[target]}</span>. Click a field to choose a different location.</p>
          <div className="relative"><select id="email-placeholder" value="" disabled={busy} onChange={e => insert(e.target.value)} className="h-10 w-full appearance-none rounded-lg border border-blue-100 bg-white px-3 pr-9 text-sm text-text-primary">
            <option value="">Choose a booking detail...</option>{Object.entries(settings.placeholders).map(([key, label]) => <option key={key} value={key}>{label}</option>)}
          </select><ChevronDown className="pointer-events-none absolute right-3 top-3 h-4 w-4 text-text-secondary" /></div>
        </div>
        <div className="mt-6 flex flex-wrap items-center justify-between gap-3 border-t border-border pt-5">
          <button type="button" disabled={busy || !dirty} onClick={() => { setDrafts(current => ({ ...current, [type]: { ...settings.templates[type].content } })); setError(''); setMessage('Changes discarded.') }} className="text-sm font-semibold text-text-secondary hover:text-blue-700 disabled:opacity-40">Discard changes</button>
          <div className="flex flex-wrap gap-3"><Button variant="outline" disabled={busy} onClick={() => { setDrafts(current => ({ ...current, [type]: { ...settings.defaults[type] } })); setError(''); setMessage('Default wording restored in the editor. Save to apply it.') }}><RotateCcw className="h-4 w-4" />Reset to default</Button>
            <Button disabled={busy || !dirty} onClick={save}>{busy ? <Loader2 className="h-4 w-4 animate-spin" /> : <Save className="h-4 w-4" />}{busy ? 'Saving...' : 'Save changes'}</Button></div>
        </div>
      </SectionCard>
      <aside className="min-w-0 space-y-5">
        <BookingEmailPreview type={type} content={draft} />
        <SectionCard><div className="mb-3 flex items-center gap-2"><Braces className="h-5 w-5 text-blue-600" /><h3 className="font-semibold text-text-primary">Personalized details</h3></div>
          <p className="mb-4 text-sm leading-6 text-text-secondary">The preview uses sample values. Real emails use the booking’s information.</p>
          <dl className="space-y-4">{Object.entries(settings.placeholders).map(([key, label]) => <div key={key}><dt className="text-xs font-semibold text-text-secondary">{label}</dt><dd className="mt-1 break-all rounded-lg bg-slate-50 px-2 py-1.5 font-mono text-xs text-blue-700">{'{' + key + '}'}</dd></div>)}</dl>
        </SectionCard>
        <SectionCard><div className="mb-3 flex items-center gap-2"><Info className="h-5 w-5 text-blue-600" /><h3 className="font-semibold text-text-primary">Booking information</h3></div>
          <p className="text-sm leading-6 text-text-secondary">Room assignments, dates, guest counts, child ages and payment details belong to the automatic booking summary.</p>
          <p className="mt-3 text-sm leading-6 text-text-secondary">Received and Confirmed emails have separate wording. Save each email type individually.</p>
        </SectionCard>
      </aside>
    </div>
  </div>
}
