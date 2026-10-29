import { ArrowLeft, ArrowRight, GripVertical, Trash2, Star } from 'lucide-react'
import usePhotoDrag from '@/hooks/usePhotoDrag'

export default function RoomPhotoOrder({ items, onChange, onRemove, disabled }) {
  const { containerRef, handleProps, drag } = usePhotoDrag(items, onChange, disabled)
  const move = (index, step) => {
    const next = [...items]; const [item] = next.splice(index, 1); next.splice(index + step, 0, item); onChange(next)
  }
  return <div ref={containerRef} className="grid grid-cols-2 gap-3 sm:grid-cols-3">
    {items.map((item, index) => <div key={item.key} data-photo-key={item.key} className={`overflow-hidden rounded-xl border bg-white transition-colors ${drag?.target === item.key ? 'border-blue-500 ring-2 ring-blue-100' : 'border-slate-200'} ${drag?.key === item.key ? 'opacity-60' : ''}`}>
      <div className="relative"><img src={item.src} alt={item.title} draggable="false" className="aspect-[4/3] w-full object-cover" />
        <span className="absolute left-2 top-2 rounded bg-white/95 px-2 py-1 text-xs font-bold">{index === 0 ? <span className="flex items-center gap-1"><Star size={12} />Cover</span> : `#${index + 1}`}</span>
      </div>
      <div className="p-2"><p className="truncate text-xs text-slate-500">{item.upload_index !== undefined ? 'New photo' : 'Saved photo'}</p>
        <div className="mt-2 flex items-center justify-between gap-1">
          <button type="button" disabled={disabled} {...handleProps(item.key)} aria-label={`Drag photo ${index + 1} to reorder`} className="rounded p-2 text-slate-500 hover:bg-blue-50"><GripVertical size={17} /></button>
          <button type="button" disabled={disabled || index === 0} onClick={() => move(index, -1)} aria-label={`Move photo ${index + 1} earlier`} className="rounded p-2 disabled:opacity-30"><ArrowLeft size={15} /></button>
          <button type="button" disabled={disabled || index === items.length - 1} onClick={() => move(index, 1)} aria-label={`Move photo ${index + 1} later`} className="rounded p-2 disabled:opacity-30"><ArrowRight size={15} /></button>
          <button type="button" disabled={disabled} onClick={() => onRemove(item)} aria-label={`Remove photo ${index + 1}`} className="rounded p-2 text-red-600 hover:bg-red-50"><Trash2 size={15} /></button>
        </div>
      </div>
    </div>)}
  </div>
}
