import { useEffect, useRef, useState } from 'react'

const ITEM_HEIGHT = 40
function Wheel({ values, value, onChange, label }) {
  const ref = useRef(null)
  const scrolling = useRef(false)
  const timer = useRef(null)
  const index = Math.max(0, values.findIndex(item => item.value === value))
  useEffect(() => {
    if (!scrolling.current && ref.current) ref.current.scrollTop = index * ITEM_HEIGHT
  }, [index])
  useEffect(() => () => clearTimeout(timer.current), [])
  return <div className="relative min-w-0 flex-1" aria-label={label}>
    <div className="pointer-events-none absolute inset-x-0 top-1/2 z-10 h-10 -translate-y-1/2 rounded-lg bg-blue-50 ring-1 ring-blue-100" />
    <div ref={ref} role="listbox" aria-label={label} tabIndex={0}
      className="relative z-20 h-[200px] snap-y snap-mandatory overflow-y-auto overscroll-contain text-center [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
      onScroll={event => {
        scrolling.current = true
        const next = Math.max(0, Math.min(values.length - 1, Math.round(event.currentTarget.scrollTop / ITEM_HEIGHT)))
        if (values[next].value !== value) onChange(values[next].value)
        clearTimeout(timer.current)
        timer.current = setTimeout(() => {
          if (ref.current) ref.current.scrollTo({ top: next * ITEM_HEIGHT, behavior: 'smooth' })
          scrolling.current = false
        }, 110)
      }}
      onKeyDown={event => {
        if (event.key !== 'ArrowUp' && event.key !== 'ArrowDown') return
        event.preventDefault()
        const next = Math.max(0, Math.min(values.length - 1, index + (event.key === 'ArrowDown' ? 1 : -1)))
        onChange(values[next].value)
        ref.current?.scrollTo({ top: next * ITEM_HEIGHT, behavior: 'smooth' })
      }}>
      <div style={{ height: ITEM_HEIGHT * 2 }} />
      {values.map(item => <div key={item.value} role="option" aria-selected={item.value === value}
        onClick={() => { onChange(item.value); ref.current?.scrollTo({ top: values.indexOf(item) * ITEM_HEIGHT, behavior: 'smooth' }) }}
        className={`flex h-10 snap-center cursor-pointer items-center justify-center text-lg transition-colors ${item.value === value ? 'font-semibold text-slate-900' : 'text-slate-400'}`}>{item.label}</div>)}
      <div style={{ height: ITEM_HEIGHT * 2 }} />
    </div>
  </div>
}
const hours = Array.from({ length: 12 }, (_, i) => ({ value: String(i + 1), label: String(i + 1).padStart(2, '0') }))
const minutes = Array.from({ length: 60 }, (_, i) => ({ value: String(i), label: String(i).padStart(2, '0') }))
const periods = [{ value: 'AM', label: 'AM' }, { value: 'PM', label: 'PM' }]
function decode(value) {
  const [h, m] = String(value || '00:00').split(':').map(Number)
  return { hour: String(h % 12 || 12), minute: String(Number.isFinite(m) ? m : 0), period: h >= 12 ? 'PM' : 'AM' }
}
export default function TimeWheelPicker({ value, onChange, label }) {
  const [open, setOpen] = useState(false)
  const parsed = decode(value)
  const [selection, setSelection] = useState(parsed)
  useEffect(() => { if (!open) setSelection(decode(value)) }, [value, open])
  const commit = () => {
    const hour = (Number(selection.hour) % 12) + (selection.period === 'PM' ? 12 : 0)
    onChange(`${String(hour).padStart(2, '0')}:${String(selection.minute).padStart(2, '0')}`)
    setOpen(false)
  }
  const format = (time) => `${String(time.hour).padStart(2, '0')}:${String(time.minute).padStart(2, '0')} ${time.period}`
  return <>
    <button type="button" onClick={() => { setSelection(decode(value)); setOpen(true) }} className="flex h-11 w-full items-center justify-between rounded-xl border border-border bg-white px-3 text-sm text-text-primary hover:border-blue-400" aria-label={`Choose ${label} time`}>
      <span>{format(parsed)}</span><span className="text-blue-600">Change</span>
    </button>
    {open && <div className="fixed inset-0 z-[100] flex items-center justify-center bg-slate-950/50 p-4" onMouseDown={event => { if (event.target === event.currentTarget) setOpen(false) }}>
      <div role="dialog" aria-modal="true" aria-label={`${label} time picker`} className="w-full max-w-sm rounded-2xl bg-white p-5 shadow-2xl">
        <div className="mb-4 flex items-center justify-between"><button type="button" className="text-slate-500" onClick={() => setOpen(false)}>Cancel</button><strong>{label}</strong><button type="button" className="font-semibold text-blue-600" onClick={commit}>Done</button></div>
        <div className="flex gap-2 rounded-xl bg-slate-50 px-3">
          <Wheel label="Hour" values={hours} value={selection.hour} onChange={hour => setSelection(current => ({ ...current, hour }))} />
          <Wheel label="Minute" values={minutes} value={selection.minute} onChange={minute => setSelection(current => ({ ...current, minute }))} />
          <Wheel label="AM or PM" values={periods} value={selection.period} onChange={period => setSelection(current => ({ ...current, period }))} />
        </div>
      </div>
    </div>}
  </>
}
