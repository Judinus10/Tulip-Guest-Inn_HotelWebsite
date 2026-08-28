import { useEffect, useLayoutEffect, useRef, useState } from 'react'
import { createPortal } from 'react-dom'
import { cn } from '@/lib/utils'

const VIEWPORT_GAP = 8

export function Dropdown({ trigger, children, align = 'right', className, contentClassName }) {
  const [open, setOpen] = useState(false)
  const [position, setPosition] = useState({ top: 0, left: 0, visibility: 'hidden' })
  const triggerRef = useRef(null)
  const menuRef = useRef(null)
  const close = () => setOpen(false)

  const updatePosition = () => {
    if (!triggerRef.current || !menuRef.current) return
    const triggerRect = triggerRef.current.getBoundingClientRect()
    const menuRect = menuRef.current.getBoundingClientRect()
    const availableBelow = window.innerHeight - triggerRect.bottom - VIEWPORT_GAP
    const availableAbove = triggerRect.top - VIEWPORT_GAP
    const openAbove = menuRect.height > availableBelow && availableAbove > availableBelow
    let top = openAbove ? triggerRect.top - menuRect.height - VIEWPORT_GAP : triggerRect.bottom + VIEWPORT_GAP
    let left = align === 'right' ? triggerRect.right - menuRect.width : triggerRect.left
    top = Math.max(VIEWPORT_GAP, Math.min(top, window.innerHeight - menuRect.height - VIEWPORT_GAP))
    left = Math.max(VIEWPORT_GAP, Math.min(left, window.innerWidth - menuRect.width - VIEWPORT_GAP))
    setPosition({ top, left, visibility: 'visible' })
  }

  useLayoutEffect(() => {
    if (open) updatePosition()
  }, [open, align])

  useEffect(() => {
    if (!open) return undefined
    const handlePointerDown = (event) => {
      if (!triggerRef.current?.contains(event.target) && !menuRef.current?.contains(event.target)) close()
    }
    const handleKeyDown = (event) => {
      if (event.key === 'Escape') close()
    }
    const handleViewportChange = () => updatePosition()
    document.addEventListener('mousedown', handlePointerDown)
    document.addEventListener('touchstart', handlePointerDown)
    document.addEventListener('keydown', handleKeyDown)
    window.addEventListener('resize', handleViewportChange)
    window.addEventListener('scroll', handleViewportChange, true)
    return () => {
      document.removeEventListener('mousedown', handlePointerDown)
      document.removeEventListener('touchstart', handlePointerDown)
      document.removeEventListener('keydown', handleKeyDown)
      window.removeEventListener('resize', handleViewportChange)
      window.removeEventListener('scroll', handleViewportChange, true)
    }
  }, [open])

  return (
    <div ref={triggerRef} className={cn('relative', className)}>
      <div onClick={() => setOpen((value) => !value)}>{trigger}</div>
      {open && typeof document !== 'undefined'
        ? createPortal(
            <div ref={menuRef} style={position} className={cn('fixed z-[1000] min-w-[240px] overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl shadow-slate-900/10', contentClassName)} role="menu">
              {typeof children === 'function' ? children(close) : children}
            </div>,
            document.body
          )
        : null}
    </div>
  )
}

export function DropdownLabel({ className, ...props }) {
  return <div className={cn('px-4 py-2 text-[11px] font-semibold uppercase tracking-wider text-slate-500', className)} {...props} />
}

export function DropdownItem({ className, destructive, ...props }) {
  return <button type="button" className={cn('flex w-full items-center gap-2.5 px-4 py-2.5 text-left text-sm transition-colors', destructive ? 'text-red-600 hover:bg-red-50' : 'text-slate-900 hover:bg-slate-100', className)} {...props} />
}

export function DropdownSeparator() {
  return <div className="my-1 h-px bg-border" />
}
