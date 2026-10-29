import usePhotoDrag from '@/hooks/usePhotoDrag'
import { useState } from 'react'
import { ArrowUp, ArrowDown, GripVertical, Check, RotateCcw, X } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { reorderGalleryImages } from '@/services/galleryApi'

export default function GalleryOrderEditor({ folder, images, onSaved, onClose }) {
  const [original] = useState(() => images.filter(image => Number(image.folder_id) === Number(folder.id)).sort((a, b) => Number(a.sort_order) - Number(b.sort_order) || Number(a.id) - Number(b.id)))
  const [ordered, setOrdered] = useState(original)
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState('')
  const { containerRef, handleProps, drag } = usePhotoDrag(ordered, setOrdered, saving)
  const changed = ordered.some((image, index) => image.id !== original[index].id)
  const move = (index, direction) => {
    setOrdered(current => {
      const next = [...current]
      const target = index + direction
      if (target < 0 || target >= next.length) return current
      ;[next[index], next[target]] = [next[target], next[index]]
      return next
    })
  }
  const save = async () => {
    if (saving || !changed) return
    setSaving(true); setError('')
    try {
      await reorderGalleryImages({ folder_id: Number(folder.id), image_ids: ordered.map(image => Number(image.id)), expected_ids: original.map(image => Number(image.id)) })
      onSaved()
    } catch (err) { setError(err.message || 'Could not save photo order.'); setSaving(false) }
  }
  return <section className="overflow-hidden rounded-2xl border border-blue-200 bg-white shadow-sm" aria-label={`Arrange photos in ${folder.name}`}>
    <div className="flex flex-wrap items-start justify-between gap-4 border-b border-slate-200 bg-blue-50/50 p-5">
      <div><h3 className="font-bold text-text-primary">Arrange Photos · {folder.name}</h3><p className="mt-1 max-w-xl text-sm text-text-secondary">Drag photos by their handles, or use the arrows, then save. This includes all photos in the folder; inactive photos stay hidden on the website.</p></div>
      <Button variant="ghost" size="icon" disabled={saving} onClick={onClose} aria-label="Close photo ordering"><X className="h-4 w-4" /></Button>
    </div>
    {error && <p role="alert" className="m-4 rounded-lg bg-red-50 p-3 text-sm text-red-700">{error}</p>}
    <ol ref={containerRef} className="max-h-[520px] space-y-2 overflow-y-auto p-4" aria-live="polite">
      {ordered.map((image, index) => <li key={image.id} data-photo-key={image.id} className={`flex items-center gap-3 rounded-xl border bg-white p-3 ${drag?.target === String(image.id) ? 'border-blue-500 ring-2 ring-blue-100' : 'border-slate-200'} ${drag?.key === String(image.id) ? 'opacity-60' : ''}`}>
        <button type="button" disabled={saving} {...handleProps(image.id)} aria-label={`Drag ${image.title} to reorder`} className="rounded p-2 text-slate-500 hover:bg-blue-50"><GripVertical size={18} /></button>
        <span className="w-7 shrink-0 text-center text-sm font-bold text-slate-400">{index + 1}</span>
        <img src={image.image_path} alt="" className="h-14 w-16 shrink-0 rounded-lg object-cover" />
        <div className="min-w-0 flex-1"><p className="truncate text-sm font-semibold text-text-primary">{image.title}</p><p className="mt-1 text-xs text-text-secondary">{image.status === 'active' ? 'Visible on website' : 'Inactive'}</p></div>
        <div className="flex shrink-0 gap-1">
          <Button variant="outline" size="icon" disabled={saving || index === 0} onClick={() => move(index, -1)} aria-label={`Move ${image.title} up`}><ArrowUp className="h-4 w-4" /></Button>
          <Button variant="outline" size="icon" disabled={saving || index === ordered.length - 1} onClick={() => move(index, 1)} aria-label={`Move ${image.title} down`}><ArrowDown className="h-4 w-4" /></Button>
        </div>
      </li>)}
    </ol>
    <div className="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 bg-slate-50 p-4">
      <p className="text-xs text-text-secondary">{changed ? 'Unsaved order changes' : 'Current saved order'}</p>
      <div className="flex flex-wrap gap-2"><Button variant="outline" disabled={saving || !changed} onClick={() => setOrdered(original)}><RotateCcw className="h-4 w-4" />Reset</Button><Button disabled={saving || !changed} onClick={save}><Check className="h-4 w-4" />{saving ? 'Saving…' : 'Save Order'}</Button></div>
    </div>
  </section>
}
