import { useEffect, useState } from 'react'
import { apiFetch, buildApiUrl, readJsonResponse } from '@/services/apiClient'

// Read physical-room availability from the database, including every group row
// and external calendar block. Never label a failed or stale check available.
export function useRoomAvailability(checkIn, checkOut, excludeBookingId = 0) {
  const valid = Boolean(checkIn && checkOut && checkOut > checkIn)
  const key = `${checkIn}|${checkOut}|${excludeBookingId}`
  const [result, setResult] = useState({ key: '', rows: [], loading: false, error: '' })

  useEffect(() => {
    if (!valid) return
    let active = true
    let sequence = 0
    const load = async () => {
      const request = ++sequence
      setResult((current) => ({ key, rows: current.key === key ? current.rows : [], loading: true, error: '' }))
      try {
        const response = await apiFetch(buildApiUrl('/bookings/group-room-availability.php'), {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ check_in_date: checkIn, check_out_date: checkOut, exclude_booking_id: excludeBookingId }),
        })
        const payload = await readJsonResponse(response)
        if (!Array.isArray(payload.data)) throw new Error('Unable to check room availability.')
        if (active && request === sequence) setResult({ key, rows: payload.data, loading: false, error: '' })
      } catch (error) {
        if (active && request === sequence) setResult({ key, rows: [], loading: false, error: error.message || 'Unable to check room availability.' })
      }
    }
    const timer = window.setTimeout(load, 150)
    const refresh = () => { if (!document.hidden) load() }
    const interval = window.setInterval(refresh, 30000)
    window.addEventListener('focus', refresh)
    return () => {
      active = false
      window.clearTimeout(timer)
      window.clearInterval(interval)
      window.removeEventListener('focus', refresh)
    }
  }, [checkIn, checkOut, excludeBookingId, key, valid])

  const current = valid && result.key === key
  const rows = current ? result.rows : []
  return {
    loading: valid && (!current || result.loading),
    error: current ? result.error : '',
    availableNames: new Set(rows.filter((room) => room.available).map((room) => room.room_name.trim().toLowerCase())),
    unavailableNames: new Set(rows.filter((room) => !room.available).map((room) => room.room_name.trim().toLowerCase())),
  }
}
