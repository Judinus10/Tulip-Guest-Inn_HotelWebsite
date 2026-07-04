import { useState, useRef, useEffect } from 'react'
import { cn } from '@/lib/utils'

export function Dropdown({ trigger, children, align = 'right', className, contentClassName }) {
  const [open, setOpen] = useState(false)
  const ref = useRef(null)

  useEffect(() => {
    function handleClick(event) {
      if (ref.current && !ref.current.contains(event.target)) {
        setOpen(false)
      }
    }

    function handleEscape(event) {
      if (event.key === 'Escape') setOpen(false)
    }

    document.addEventListener('mousedown', handleClick)
    document.addEventListener('keydown', handleEscape)
    return () => {
      document.removeEventListener('mousedown', handleClick)
      document.removeEventListener('keydown', handleEscape)
    }
  }, [])

  return (
    <div ref={ref} className={cn('relative', className)}>
      <div onClick={() => setOpen((prev) => !prev)}>{trigger}</div>
      {open && (
        <div
          className={cn(
            'absolute top-[calc(100%+8px)] z-50 min-w-[240px] overflow-hidden rounded-xl border border-slate-200 bg-white shadow-lg shadow-slate-900/5',
            align === 'right' ? 'right-0' : 'left-0',
            contentClassName
          )}
        >
          {typeof children === 'function' ? children(() => setOpen(false)) : children}
        </div>
      )}
    </div>
  )
}

export function DropdownLabel({ className, ...props }) {
  return (
    <div
      className={cn('px-4 py-2 text-[11px] font-semibold uppercase tracking-wider text-slate-500', className)}
      {...props}
    />
  )
}

export function DropdownItem({ className, destructive, ...props }) {
  return (
    <button
      type="button"
      className={cn(
        'flex w-full items-center gap-2.5 px-4 py-2.5 text-left text-sm transition-colors',
        destructive
          ? 'text-red-600 hover:bg-red-50'
          : 'text-slate-900 hover:bg-slate-100',
        className
      )}
      {...props}
    />
  )
}

export function DropdownSeparator() {
  return <div className="my-1 h-px bg-border" />
}
