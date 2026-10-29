import { useRef, useState } from 'react'

export default function usePhotoDrag(items, onChange, disabled = false) {
  const containerRef = useRef(null)
  const gesture = useRef(null)
  const [drag, setDrag] = useState(null)
  const handleProps = key => ({
    style: { touchAction: 'none', cursor: disabled ? 'default' : 'grab' },
    onPointerDown: event => {
      if (disabled || event.button !== 0) return
      event.preventDefault()
      event.currentTarget.setPointerCapture(event.pointerId)
      gesture.current = { key: String(key), target: String(key) }
      setDrag({ ...gesture.current })
    },
    onPointerMove: event => {
      if (!gesture.current) return
      const hit = document.elementFromPoint(event.clientX, event.clientY)?.closest('[data-photo-key]')
      if (hit && containerRef.current?.contains(hit)) {
        gesture.current.target = hit.dataset.photoKey
        setDrag({ ...gesture.current })
      }
      // Scroll the nearest scrollable list/modal while dragging near its edges.
      let scroll = containerRef.current
      while (scroll && scroll.scrollHeight <= scroll.clientHeight) scroll = scroll.parentElement
      if (scroll) {
        const bounds = scroll.getBoundingClientRect()
        if (event.clientY < bounds.top + 48) scroll.scrollTop -= 16
        if (event.clientY > bounds.bottom - 48) scroll.scrollTop += 16
      }
    },
    onPointerUp: () => {
      const move = gesture.current
      gesture.current = null; setDrag(null)
      if (!move || disabled || move.key === move.target) return
      const from = items.findIndex(item => String(item.key ?? item.id) === move.key)
      const to = items.findIndex(item => String(item.key ?? item.id) === move.target)
      if (from < 0 || to < 0) return
      const next = [...items]; const [item] = next.splice(from, 1); next.splice(to, 0, item); onChange(next)
    },
    onPointerCancel: () => { gesture.current = null; setDrag(null) },
    onLostPointerCapture: () => { gesture.current = null; setDrag(null) },
  })
  return { containerRef, handleProps, drag }
}
