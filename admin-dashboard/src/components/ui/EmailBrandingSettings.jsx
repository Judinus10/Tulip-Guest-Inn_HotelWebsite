import { useEffect, useRef, useState } from 'react'
import { ImagePlus, Loader2, RotateCcw, Save } from 'lucide-react'
import { apiFetch, buildApiUrl, readJsonResponse } from '@/services/apiClient'

const endpoint = buildApiUrl('/settings/email-branding.php')
export default function EmailBrandingSettings({ onSaved, onDraftChange }) {
  const [saved, setSaved] = useState(null)
  const [width, setWidth] = useState(76)
  const [file, setFile] = useState(null)
  const [reset, setReset] = useState(false)
  const [localLogo, setLocalLogo] = useState(null)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState('')
  const [message, setMessage] = useState('')
  const [loading, setLoading] = useState(true)
  const [reload, setReload] = useState(0)
  const picker = useRef(null)
  const active = useRef(true)
  useEffect(() => { active.current = true; return () => { active.current = false } }, [])
  useEffect(() => {
    let current = true
    setLoading(true); setError('')
    apiFetch(endpoint, { cache: 'no-store' }).then(readJsonResponse).then(result => {
      if (current) { setSaved(result.data); setWidth(result.data.width) }
    }).catch(e => { if (current) setError(e.message || 'Could not load email branding.') }).finally(() => { if (current) setLoading(false) })
    return () => { current = false }
  }, [reload])
  useEffect(() => {
    if (!file) { setLocalLogo(null); return undefined }
    let current = true
    const reader = new FileReader()
    const image = new Image()
    const fail = message => {
      if (!current) return
      setError(message); setFile(null); setLocalLogo(null)
      if (picker.current) picker.current.value = ''
    }
    reader.onerror = () => fail('Could not read the logo. Choose another PNG or JPEG.')
    reader.onload = () => {
      if (!current) return
      const src = typeof reader.result === 'string' ? reader.result : ''
      if (!/^data:image\/(png|jpeg);base64,[A-Za-z0-9+/=]+$/.test(src)) { fail('Choose a valid PNG or JPEG image.'); return }
      image.onload = () => {
        if (!current) return
        const ratio = image.naturalWidth / image.naturalHeight
        if (!image.naturalWidth || !image.naturalHeight || image.naturalWidth > 2000 || image.naturalHeight > 2000 || ratio < 0.25 || ratio > 8) {
          fail('Use an image up to 2000 × 2000 pixels, with a suitable logo shape.'); return
        }
        setLocalLogo({ file, src })
      }
      image.onerror = () => fail('This file is not a readable PNG or JPEG image.')
      image.src = src
    }
    reader.readAsDataURL(file)
    return () => { current = false; reader.onload = null; reader.onerror = null; image.onload = null; image.onerror = null; if (reader.readyState === 1) reader.abort() }
  }, [file])
  const dirty = Boolean(saved && (reset || file || width !== saved.width))
  const preparing = Boolean(file && localLogo?.file !== file)
  const logo = reset ? saved?.default_logo : (file && localLogo?.file === file ? localLogo.src : saved?.logo)
  useEffect(() => {
    onDraftChange?.(saved ? { logo: logo || '', width, dirty, preparing } : null)
  }, [saved, logo, width, dirty, preparing, onDraftChange])
  useEffect(() => {
    if (!dirty) return undefined
    const warn = event => { event.preventDefault(); event.returnValue = '' }
    window.addEventListener('beforeunload', warn); return () => window.removeEventListener('beforeunload', warn)
  }, [dirty])
  const clearPicker = () => { if (picker.current) picker.current.value = '' }
  async function save() {
    if (busy || preparing) return
    setBusy(true); setError(''); setMessage('')
    const form = new FormData()
    form.set('width', String(width)); form.set('version', String(saved.version)); form.set('reset', reset ? '1' : '0')
    if (file) form.set('logo', file)
    try {
      const response = await apiFetch(endpoint, { method: 'POST', body: form })
      const result = await readJsonResponse(response)
      if (!active.current) return
      setSaved(result.data); setWidth(result.data.width); setFile(null); setReset(false); clearPicker()
      setMessage('Email branding saved. The email preview has been refreshed.'); onSaved?.()
    } catch (e) { if (active.current) setError(e.message || 'Could not save email branding.') }
    finally { if (active.current) setBusy(false) }
  }
  return <section className="booking-email-card mb-4" aria-label="Email logo and branding">
    <div className="booking-email-card-heading"><h2>Email logo &amp; branding</h2><span className="text-xs text-text-secondary">Emails only</span></div>
    {loading ? <p role="status" className="flex items-center gap-2 text-sm text-text-secondary"><Loader2 className="h-4 w-4 animate-spin" />Loading email branding...</p> : saved ? <>
      <div className="flex flex-wrap items-center gap-5">
        <div className="flex min-h-[135px] w-[260px] max-w-full items-center justify-center rounded-lg border border-dashed border-border bg-slate-50 p-3">
          {logo ? <img src={logo} alt="Email logo preview" style={{ width, maxWidth: '100%', height: 'auto', maxHeight: 260, objectFit: 'contain' }} /> : <span className="text-xs text-text-secondary">No logo available</span>}
        </div>
        <div className="min-w-0 flex-1 space-y-3" style={{ minWidth: 220 }}>
          <p className="text-xs leading-5 text-text-secondary">Replace the logo or adjust its width. Proportions stay automatic. Changes appear immediately in the full email preview. Save branding to apply them to future emails.</p>
          <label className="inline-flex cursor-pointer items-center gap-2 rounded-lg border border-blue-200 px-3 py-2 text-xs font-semibold text-blue-700"><ImagePlus className="h-4 w-4" />Replace email logo
            <input ref={picker} type="file" accept="image/png,image/jpeg" disabled={busy} className="sr-only" onChange={event => {
              const selected = event.target.files?.[0]
              if (!selected) return
              if (!['image/png', 'image/jpeg'].includes(selected.type) || selected.size > 2097152) { setError('Choose a PNG or JPEG image up to 2 MB.'); clearPicker(); return }
              setFile(selected); setReset(false); setError(''); setMessage('')
            }} />
          </label>
          <p className="text-xs text-text-secondary">PNG or JPEG · Up to 2 MB · Maximum 2000 × 2000 pixels</p>
          <div><label htmlFor="email-logo-width" className="flex items-center justify-between text-xs font-semibold">Logo width <span>{width} px</span></label>
            <input id="email-logo-width" type="range" min="40" max="240" step="1" value={width} disabled={busy} className="mt-2 w-full accent-blue-600" onChange={e => { setWidth(Number(e.target.value)); setMessage('') }} />
          </div>
          <div className="flex flex-wrap gap-2">
            <button type="button" disabled={busy} className="inline-flex items-center gap-1 rounded-lg border border-border px-3 py-2 text-xs font-semibold disabled:opacity-50" onClick={() => { setReset(true); setFile(null); setWidth(saved.default_width); clearPicker(); setMessage('Default logo restored in the editor. Save branding to apply.'); setError('') }}><RotateCcw className="h-3.5 w-3.5" />Reset logo</button>
            <button type="button" disabled={busy || !dirty} className="rounded-lg px-3 py-2 text-xs text-text-secondary disabled:opacity-50" onClick={() => { setFile(null); setReset(false); setWidth(saved.width); clearPicker(); setError(''); setMessage('') }}>Discard</button>
            <button type="button" disabled={busy || !dirty || preparing} onClick={save} className="inline-flex items-center gap-1 rounded-lg bg-blue-600 px-3 py-2 text-xs font-semibold text-white disabled:opacity-50">{busy ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : <Save className="h-3.5 w-3.5" />}Save branding</button>
          </div>
        </div>
      </div>
      <p className="mt-3 text-xs text-text-secondary">{preparing ? 'Preparing logo preview…' : dirty ? 'Unsaved branding is shown in the full email preview. Save branding before sending a test email.' : 'The preview uses saved email branding. Previously queued emails retain their original logo.'}</p>
    </> : <button type="button" className="text-sm text-blue-700" onClick={() => setReload(n => n + 1)}>Reload branding settings</button>}
    {error && <p role="alert" className="mt-3 text-xs text-red-700">{error}</p>}
    {message && <p role="status" className="mt-3 text-xs text-emerald-700">{message}</p>}
  </section>
}
