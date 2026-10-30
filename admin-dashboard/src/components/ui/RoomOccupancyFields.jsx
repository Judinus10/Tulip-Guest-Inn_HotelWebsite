import { Users } from 'lucide-react'
import { Input, Label } from '@/components/ui/input'
import { occupancyFields } from '@/services/roomOccupancy'

export default function RoomOccupancyFields({ form, errors, onChange, countChildren, onToggle, onCommit, disabled }) {
  return <section className="md:col-span-2 overflow-hidden rounded-2xl border border-border bg-white" aria-labelledby="room-occupancy-title">
    <div className="flex items-start gap-3 border-b border-border bg-slate-50/80 px-5 py-4">
      <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-blue-100 text-blue-600"><Users size={20} /></span>
      <div><h3 id="room-occupancy-title" className="text-sm font-bold text-text-primary">Room occupancy</h3><p className="mt-1 text-xs leading-relaxed text-text-secondary">Dependent limits adjust automatically. Guest capacity stays as you set it; total occupancy is always a hard limit.</p></div>
    </div>
    <div className="grid gap-5 p-5 sm:grid-cols-2">
      {occupancyFields.filter(({ key }) => key !== 'max_child_age').map(({ key, label, min, max, hint }) => <div key={key} className="space-y-2">
        <Label htmlFor={`occupancy-${key}`}>{label}</Label>
        <Input id={`occupancy-${key}`} type="number" inputMode="numeric" min={min} max={key === 'max_adults' || (key === 'max_children' && countChildren) || (key === 'max_total_occupants' && countChildren) ? (Number(form.capacity) || max) : max} step="1" value={form[key] ?? ''} disabled={disabled}
          onChange={event => onChange(key, event.target.value)} onBlur={onCommit} aria-invalid={Boolean(errors[key])} aria-describedby={`occupancy-${key}-hint${errors[key] ? ` occupancy-${key}-error` : ''}`} />
        <p id={`occupancy-${key}-hint`} className="text-xs leading-relaxed text-text-secondary">{hint}</p>
        {errors[key] && <p id={`occupancy-${key}-error`} className="text-xs font-medium text-red-600">{errors[key]}</p>}
      </div>)}
    </div>
    <div className="border-t border-border bg-blue-50/60 p-5">
      <span className="mb-4 inline-flex rounded-full border border-blue-200 bg-white px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wide text-blue-700">Applies to all rooms</span>
      <div className="mb-5 space-y-2 sm:max-w-sm">
        <Label htmlFor="occupancy-max_child_age">Maximum child age (inclusive)</Label>
        <Input id="occupancy-max_child_age" type="number" inputMode="numeric" min="0" max="17" step="1"
          value={form.max_child_age ?? ''} disabled={disabled} onChange={event => onChange('max_child_age', event.target.value)}
          aria-invalid={Boolean(errors.max_child_age)} aria-describedby={`occupancy-max_child_age-hint${errors.max_child_age ? ' occupancy-max_child_age-error' : ''}`} />
        <p id="occupancy-max_child_age-hint" className="text-xs leading-relaxed text-text-secondary">The same age limit applies to every room. Ages are measured at check-in; older children use adult places.</p>
        {errors.max_child_age && <p id="occupancy-max_child_age-error" className="text-xs font-medium text-red-600">{errors.max_child_age}</p>}
      </div>
      <div className="flex items-start justify-between gap-5">
        <div className="min-w-0">
          <p id="count-children-label" className="text-sm font-semibold text-text-primary">Count children toward guest capacity</p>
          <p id="count-children-help" className="mt-1 text-xs leading-relaxed text-text-secondary">{countChildren ? 'Adults and children both count toward guest capacity.' : 'Only adults count toward guest capacity. The child limit and total occupancy limit still apply.'}</p>
        </div>
        <button type="button" role="switch" aria-checked={countChildren} aria-labelledby="count-children-label" aria-describedby="count-children-help" disabled={disabled}
          onClick={() => onToggle(!countChildren)} className={`mt-1 inline-flex h-7 w-12 shrink-0 items-center rounded-full p-1 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 ${countChildren ? 'bg-blue-600' : 'bg-slate-300'}`}>
          <span className={`h-5 w-5 rounded-full bg-white shadow-sm transition-transform ${countChildren ? 'translate-x-5' : 'translate-x-0'}`} />
        </button>
      </div>
      <p className="mt-3 text-xs font-medium text-blue-800">Changing either setting applies to every room when you save. Cancel leaves both shared settings unchanged.</p>
    </div>
  </section>
}
