import { useEffect, useState } from 'react'
import defaultHero01 from '@/assets/hero-default/home-hero-01.jpg'
import defaultHero02 from '@/assets/hero-default/home-hero-02.jpg'
import defaultHero03 from '@/assets/hero-default/home-hero-03.jpg'
import { ArrowDown, ArrowLeft, ArrowUp, CheckCircle2, X, Film, ImagePlus, Images, Loader2, Save, Trash2, UploadCloud, Eye, RotateCcw, Send } from 'lucide-react'
import { Link } from 'react-router-dom'
import { PageHeader, SectionCard } from '@/components/ui/page-header'
import { Button } from '@/components/ui/button'
import { apiFetch, buildApiUrl, readJsonResponse } from '@/services/apiClient'

const base = buildApiUrl('/settings')
const defaultHeroImages = [defaultHero01, defaultHero02, defaultHero03]
const empty = { source: 'default', mode: 'images', images: [], video: '', poster: '' }
const normalized = (hero) => {
  const value = { ...empty, ...(hero || {}) }
  const hasMedia = value.mode === 'video' ? Boolean(value.video) : Boolean(value.images?.length)
  const source = hero?.source || (hasMedia ? 'custom' : 'default')
  return { ...value, source: source === 'custom' && hasMedia ? 'custom' : 'default' }
}
const same = (a, b) => JSON.stringify(a) === JSON.stringify(b)

export default function HeroContent({ embedded = false }) {
  const [draft, setDraft] = useState(empty)
  const [saved, setSaved] = useState(empty)
  const [published, setPublished] = useState(empty)
  const [busy, setBusy] = useState(false)
  const [loading, setLoading] = useState(true)
  const [toast, setToast] = useState(null)
  const notify = (type, text) => setToast({ type, text, id: Date.now() })
  useEffect(() => {
    if (!toast) return
    const timer = window.setTimeout(() => setToast(null), 4500)
    return () => window.clearTimeout(timer)
  }, [toast])
  const [preview, setPreview] = useState('draft')
  const dirty = !same(draft, saved)

  async function refresh() {
    const response = await apiFetch(`${base}/hero-content.php?admin=1`)
    const payload = await readJsonResponse(response)
    const nextDraft = normalized(payload.data?.draft)
    const nextPublished = normalized(payload.data?.published)
    setDraft(nextDraft)
    setSaved(nextDraft)
    setPublished(nextPublished)
  }
  useEffect(() => {
    let active = true
    async function load() {
      try { if (active) await refresh() }
      catch (e) { if (active) notify('error', e.message || 'Could not load hero settings.') }
      finally { if (active) setLoading(false) }
    }
    load()
    return () => { active = false }
  }, [])

  async function action(kind) {
    setBusy(true); setToast(null)
    try {
      const response = await apiFetch(`${base}/hero-content.php`, {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: kind, hero: draft }),
      })
      await readJsonResponse(response)
      await refresh()
      notify('success', kind === 'publish' ? 'Saved draft published to the website.' : kind === 'draft' ? 'Draft saved successfully.' : 'Draft discarded.')
    } catch (e) { notify('error', e.message || 'Unable to update hero.') }
    finally { setBusy(false) }
  }

  async function upload(file, kind, poster = false, replaceIndex = -1) {
    if (!file) return
    setBusy(true); setToast(null)
    try {
      const data = new FormData()
      data.append('file', file)
      data.append('kind', kind)
      const response = await apiFetch(`${base}/hero-upload.php`, { method: 'POST', body: data })
      const result = await readJsonResponse(response)
      const url = result.data.url
      setDraft(prev => {
        if (poster) return { ...prev, poster: url }
        if (kind === 'video') return { ...prev, video: url }
        if (replaceIndex >= 0) return { ...prev, images: prev.images.map((old, i) => i === replaceIndex ? url : old) }
        return { ...prev, images: [...prev.images, url].slice(0, 3) }
      })
      notify('success', 'Media uploaded. Save the draft to keep your changes.')
    } catch (e) { notify('error', e.message || 'Upload failed.') }
    finally { setBusy(false) }
  }

  function moveImage(index, delta) {
    setDraft(prev => {
      const images = [...prev.images]
      const target = index + delta
      if (target < 0 || target >= images.length) return prev
      ;[images[index], images[target]] = [images[target], images[index]]
      return { ...prev, images }
    })
  }

  const shown = preview === 'draft' ? draft : published
  const previewImages = shown.source === 'default' ? defaultHeroImages : shown.images
  const previewHasMedia = shown.source === 'default' || (shown.mode === 'video' ? Boolean(shown.video) : shown.images.length > 0)
  const [previewImageIndex, setPreviewImageIndex] = useState(0)
  useEffect(() => {
    const timer = window.setInterval(() => setPreviewImageIndex(i => i + 1), 5200)
    return () => window.clearInterval(timer)
  }, [])
  const mediaPicker = (kind, label, poster = false, replaceIndex = -1) => (
    <label className={`inline-flex cursor-pointer items-center justify-center gap-2 rounded-lg border border-border bg-white px-3 py-2 text-sm font-medium text-text-primary transition hover:border-blue-300 hover:bg-blue-50 ${busy ? 'pointer-events-none opacity-50' : ''}`}>
      <UploadCloud className="h-4 w-4" /> {label}
      <input className="sr-only" type="file" accept={kind === 'video' ? 'video/mp4,video/webm' : 'image/jpeg,image/png,image/webp'} disabled={busy} onChange={e => { upload(e.target.files?.[0], kind, poster, replaceIndex); e.target.value = '' }} />
    </label>
  )

  return <div className="space-y-6">
    {!embedded && (<PageHeader title="Homepage Hero" description="Manage homepage background media without changing the website layout.">
      <Link to="/website-settings" className="inline-flex items-center gap-2 rounded-lg border border-border bg-white px-3 py-2 text-sm font-medium text-text-secondary hover:bg-slate-50"><ArrowLeft className="h-4 w-4" /> Website Settings</Link>
    </PageHeader>)}

    {toast && <div key={toast.id} role={toast.type === 'error' ? 'alert' : 'status'} className={`fixed right-5 top-5 z-[9999] flex w-[min(24rem,calc(100vw-2.5rem))] items-start gap-3 rounded-xl border bg-white p-4 shadow-xl ${toast.type === 'error' ? 'border-red-200 text-red-700' : 'border-emerald-200 text-emerald-700'}`}>
      <CheckCircle2 className="mt-0.5 h-5 w-5 shrink-0" /><span className="flex-1 text-sm">{toast.text}</span><button type="button" aria-label="Dismiss notification" onClick={() => setToast(null)}><X className="h-4 w-4" /></button>
    </div>}

    {loading ? <SectionCard><div className="flex items-center gap-2 text-sm text-text-secondary"><Loader2 className="h-4 w-4 animate-spin" /> Loading hero content...</div></SectionCard> : <>
      <SectionCard>
        <h2 className="text-lg font-semibold text-text-primary">Homepage Banner Source</h2>
        <p className="mt-1 mb-4 text-sm text-text-secondary">Select the original protected images or your uploaded media. Changing the source never deletes uploads.</p>
        <div className="grid gap-3 sm:grid-cols-2">
          {[{value:'default', title:'Use Default Images', detail:'Three original website banners, protected from editing.'}, {value:'custom', title:'Use Custom Media', detail:'Display your own uploaded images or video.'}].map(option => <button key={option.value} type="button" onClick={() => setDraft(prev => ({...prev, source:option.value}))} className={`rounded-xl border p-4 text-left ${draft.source === option.value ? 'border-blue-500 bg-blue-50 ring-1 ring-blue-400' : 'border-border bg-white'}`}><span className="font-semibold text-text-primary">{option.title}</span>{published.source === option.value && <span className="ml-2 rounded-full bg-emerald-100 px-2 py-0.5 text-[11px] font-semibold text-emerald-700">Currently live</span>}<p className="mt-1 text-xs text-text-secondary">{option.detail}</p></button>)}
        </div>
        {draft.source === 'default' && (
          <div className="mt-4 grid gap-3 sm:grid-cols-3" aria-label="Protected default homepage images">
            {defaultHeroImages.map((image, index) => (
              <div key={image} className="overflow-hidden rounded-xl border border-border bg-white">
                <img src={image} alt={`Default homepage banner ${index + 1}`} className="h-32 w-full object-cover sm:h-36" />
                <div className="px-3 py-2 text-xs font-medium text-text-secondary">Default {index + 1} · Protected</div>
              </div>
            ))}
          </div>
        )}
      </SectionCard>
      {draft.source === 'custom' && <SectionCard>
        <div className="mb-6 flex items-start gap-3">
          <div className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-primary-600"><Images className="h-5 w-5" /></div>
          <div><h2 className="text-lg font-semibold text-text-primary">Hero Media</h2><p className="mt-1 text-sm text-text-secondary">Choose one background type. Switching modes keeps the other media saved.</p></div>
        </div>
        <div className="grid gap-3 sm:grid-cols-2">
          {[{ mode: 'images', icon: Images, title: 'Image Slideshow', description: 'Up to 3 images' }, { mode: 'video', icon: Film, title: 'Background Video', description: 'One muted, looping video' }].map(option => {
            const Icon = option.icon
            const selected = draft.mode === option.mode
            return <button key={option.mode} type="button" onClick={() => setDraft(p => ({ ...p, mode: option.mode }))} className={`flex items-center gap-3 rounded-xl border p-4 text-left transition ${selected ? 'border-blue-500 bg-blue-50/60 ring-1 ring-blue-500' : 'border-border hover:bg-slate-50'}`}>
              <div className={`rounded-lg p-2 ${selected ? 'bg-blue-100 text-blue-700' : 'bg-slate-100 text-slate-500'}`}><Icon className="h-5 w-5" /></div>
              <div className="flex-1"><p className="text-sm font-semibold text-text-primary">{option.title}</p><p className="mt-0.5 text-xs text-text-secondary">{option.description}</p></div>
              <span className={`h-4 w-4 rounded-full border-4 ${selected ? 'border-blue-600 bg-white' : 'border-slate-300 bg-white'}`} />
            </button>
          })}
        </div>
      </SectionCard>}

      {draft.source === 'custom' && <SectionCard>
        <div className="mb-5 flex flex-wrap items-start justify-between gap-3">
          <div><h2 className="text-lg font-semibold text-text-primary">{draft.mode === 'images' ? 'Slideshow Images' : 'Hero Video'}</h2>
            <p className="mt-1 text-sm text-text-secondary">{draft.mode === 'images' ? 'Upload, replace, reorder, or remove images.' : 'Upload one video and a fallback poster image.'}</p>
          </div>
          {draft.mode === 'images' && <span className="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-text-secondary">{draft.images.length} / 3 images</span>}
        </div>
        {draft.mode === 'images' ? <div className="space-y-4">
          <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            {draft.images.map((url, index) => <div key={`${index}-${url}`} className="overflow-hidden rounded-xl border border-border">
              <div className="relative h-44 bg-slate-100"><img src={url} alt={`Slideshow image ${index + 1}`} className="h-full w-full object-cover" /><span className="absolute left-3 top-3 rounded-md bg-slate-900/75 px-2 py-1 text-xs font-semibold text-white">Image {index + 1}</span></div>
              <div className="flex flex-wrap items-center gap-2 p-3">
                {mediaPicker('image', 'Replace', false, index)}
                <button type="button" title="Move earlier" aria-label={`Move image ${index + 1} earlier`} disabled={index === 0} onClick={() => moveImage(index, -1)} className="rounded-lg border border-border p-2 disabled:opacity-30"><ArrowUp className="h-4 w-4" /></button>
                <button type="button" title="Move later" aria-label={`Move image ${index + 1} later`} disabled={index === draft.images.length - 1} onClick={() => moveImage(index, 1)} className="rounded-lg border border-border p-2 disabled:opacity-30"><ArrowDown className="h-4 w-4" /></button>
                <button type="button" title="Remove image" onClick={() => setDraft(p => ({ ...p, images: p.images.filter((_, i) => i !== index) }))} className="ml-auto rounded-lg border border-red-100 p-2 text-red-600 hover:bg-red-50"><Trash2 className="h-4 w-4" /></button>
              </div>
            </div>)}
            {draft.images.length < 3 && <div className="flex min-h-52 flex-col items-center justify-center gap-3 rounded-xl border-2 border-dashed border-slate-200 bg-slate-50/60 p-5 text-center">
              <div className="rounded-xl bg-blue-50 p-3 text-blue-600"><ImagePlus className="h-6 w-6" /></div>
              <p className="text-sm font-semibold text-text-primary">Add slideshow image</p>
              <p className="text-xs text-text-secondary">JPG, PNG or WebP</p>
              {mediaPicker('image', 'Choose Image')}
            </div>}
          </div>
          <p className="text-xs text-text-secondary">The original homepage slideshow remains the fallback until you publish replacement images.</p>
        </div> : <div className="grid gap-5 lg:grid-cols-2">
          <div className="space-y-3">
            <p className="text-sm font-semibold text-text-primary">Background video</p>
            {draft.video ? <video src={draft.video} controls preload="metadata" className="aspect-video w-full rounded-xl bg-slate-900 object-cover" /> : <div className="flex aspect-video items-center justify-center rounded-xl border-2 border-dashed border-border bg-slate-50"><Film className="h-9 w-9 text-slate-400" /></div>}
            <div className="flex flex-wrap gap-2">{mediaPicker('video', draft.video ? 'Replace Video' : 'Upload Video')}{draft.video && <Button variant="outline" onClick={() => setDraft(p => ({ ...p, video: '' }))}><Trash2 className="h-4 w-4" /> Remove</Button>}</div>
            <p className="text-xs text-text-secondary">MP4 or WebM · Maximum 50 MB · Video plays muted on the public homepage.</p>
          </div>
          <div className="space-y-3">
            <p className="text-sm font-semibold text-text-primary">Fallback poster</p>
            {draft.poster ? <img src={draft.poster} alt="Video poster" className="aspect-video w-full rounded-xl object-cover" /> : <div className="flex aspect-video items-center justify-center rounded-xl border-2 border-dashed border-border bg-slate-50"><Images className="h-9 w-9 text-slate-400" /></div>}
            <div className="flex flex-wrap gap-2">{mediaPicker('image', draft.poster ? 'Replace Poster' : 'Upload Poster', true)}{draft.poster && <Button variant="outline" onClick={() => setDraft(p => ({ ...p, poster: '' }))}><Trash2 className="h-4 w-4" /> Remove</Button>}</div>
            <p className="text-xs text-text-secondary">Shown while the video loads or when video playback is unavailable.</p>
          </div>
        </div>}
      </SectionCard>}

      {draft.source === 'custom' && <SectionCard>
        <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
          <div>
            <h2 className="text-lg font-semibold text-text-primary">Custom Hero Preview</h2>
            <p className="mt-1 text-sm text-text-secondary">Preview your uploaded media before publishing it.</p>
          </div>
          <div className="flex rounded-lg border border-border bg-slate-50 p-1" role="group" aria-label="Preview version">
            {['draft', 'published'].map(value => <button key={value} type="button" onClick={() => setPreview(value)} className={`rounded-md px-3 py-1.5 text-sm font-medium ${preview === value ? 'bg-white text-blue-700 shadow-sm' : 'text-text-secondary'}`}>{value === 'draft' ? 'Draft' : 'Published'}</button>)}
          </div>
        </div>
        {previewHasMedia ? (
          <div className="relative flex aspect-[16/8] min-h-[320px] items-center justify-center overflow-hidden rounded-xl bg-slate-950 sm:min-h-[400px]">
            {shown.source === 'custom' && shown.mode === 'video' ? (
              <video key={shown.video} src={shown.video} poster={shown.poster || undefined} muted loop autoPlay playsInline className="absolute inset-0 h-full w-full object-cover" />
            ) : previewImages.length > 0 ? (
              <img src={previewImages[previewImageIndex % previewImages.length]} alt="Homepage hero preview" className="absolute inset-0 h-full w-full object-cover transition-opacity duration-700" />
            ) : null}
            <div className="absolute inset-0 bg-gradient-to-b from-black/50 via-black/30 to-black/60" />
            <div className="relative z-10 mx-auto max-w-4xl px-4 text-center">
              <p className="mb-4 text-[10px] font-medium uppercase tracking-[0.4em] text-[#e7c56b]">Point Pedro — Northern Sri Lanka</p>
              <h3 className="mb-5 font-serif text-3xl font-light leading-tight text-white drop-shadow-lg sm:text-5xl lg:text-6xl">
                Guest House &amp; Rooms in<br /><span className="italic text-[#e7c56b]">Point Pedro</span>
              </h3>
              <div className="mx-auto mb-5 h-px w-16 bg-[#e7c56b]" />
              <p className="mx-auto mb-7 max-w-lg text-sm leading-relaxed text-white/85 sm:text-base">Experience peaceful accommodation, modern comfort and genuine Sri Lankan hospitality.</p>
              <div className="flex flex-wrap items-center justify-center gap-3">
                <span className="rounded bg-[#e7c56b] px-5 py-2.5 text-sm font-semibold text-slate-950">Book Your Stay</span>
                <span className="rounded border border-white px-5 py-2.5 text-sm font-semibold text-white">Explore Rooms</span>
              </div>
            </div>
          </div>
        ) : <div className="flex min-h-52 items-center justify-center rounded-xl border border-dashed border-border bg-slate-50 px-6 text-center text-sm text-text-secondary">Upload custom media to see its preview here.</div>}
        {previewHasMedia && (shown.source === 'default' || shown.mode === 'images') && previewImages.length > 1 && <p className="mt-2 text-xs text-text-secondary">Preview cycles through {previewImages.length} images, as on the public homepage.</p>}
      </SectionCard>}

      <div className="flex flex-col gap-3 rounded-2xl border border-border bg-white p-4 shadow-sm shadow-slate-200/60 sm:flex-row sm:items-center sm:justify-between">
        <p className="text-sm text-text-secondary">{dirty ? 'You have unsaved changes.' : 'All current edits are saved as a draft.'}</p>
        <div className="flex flex-wrap gap-2">
          <Button type="button" variant="outline" disabled={busy} onClick={() => action('discard')}><RotateCcw className="h-4 w-4" /> Discard Draft</Button>
          <Button type="button" variant="outline" disabled={busy || !dirty} onClick={() => action('draft')}>{busy ? <Loader2 className="h-4 w-4 animate-spin" /> : <Save className="h-4 w-4" />} Save Draft</Button>
          <Button type="button" disabled={busy || dirty} onClick={() => action('publish')}><Send className="h-4 w-4" /> Publish Draft</Button>
        </div>
      </div>
    </>}
  </div>
}
