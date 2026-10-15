import { useCallback, useEffect, useState } from 'react'
import { CalendarDays, RefreshCw, Loader2, BedDouble, Clock3, Activity, MoreHorizontal, Pencil, Trash2, X, CheckCircle2, AlertCircle, Link2 } from 'lucide-react'
import { PageHeader } from '@/components/ui/page-header'
import { Button } from '@/components/ui/button'
import { apiFetch, buildApiUrl, readJsonResponse } from '@/services/apiClient'

const endpoint = buildApiUrl('/calendar/manage.php')
const dateText = value => value ? new Date(String(value).replace(' ', 'T')).toLocaleString() : 'Never'

export default function CalendarSync() {
  const [data, setData] = useState(null)
  const [loading, setLoading] = useState(true)
  const [busy, setBusy] = useState(false)
  const [roomBusy, setRoomBusy] = useState(null)
  const [menu, setMenu] = useState(null)
  const [editing, setEditing] = useState(null)
  const [url, setUrl] = useState('')
  const [error, setError] = useState('')
  const [message, setMessage] = useState('')
  const refresh = useCallback(async () => {
    const response = await readJsonResponse(await apiFetch(endpoint))
    setData(response.data)
  }, [])
  useEffect(() => { refresh().catch(e => setError(e.message)).finally(() => setLoading(false)) }, [refresh])
  async function act(action, extra = {}, roomId = null) {
    setError(''); setMessage(''); setMenu(null)
    if (roomId !== null) setRoomBusy(roomId); else setBusy(true)
    try {
      const response = await readJsonResponse(await apiFetch(endpoint, {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action, ...extra }),
      }))
      setMessage(response.message || 'Changes saved.')
      await refresh()
      return true
    } catch (e) { setError(e.message || 'Request failed.'); return false }
    finally { setBusy(false); setRoomBusy(null) }
  }
  const rooms = data?.rooms || []
  return <div className="mx-auto max-w-7xl space-y-5 pb-12">
    <PageHeader title="Calendar Synchronization" description="Manage Booking.com calendar imports across all configured rooms." />
    {error && <div role="alert" className="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">{error}</div>}
    {message && <div role="status" className="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-700">{message}</div>}
    {loading ? <div className="flex justify-center p-20"><Loader2 className="animate-spin" /></div> : data && <>
      <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
        {[
          { label: 'Configured Rooms', value: data.configured_rooms, sub: 'Rooms with valid calendars', icon: BedDouble, color: 'text-blue-600 bg-blue-50' },
          { label: 'Last Successful Sync', value: dateText(data.last_sync), sub: data.last_sync ? 'Most recent completed import' : 'No sync completed yet', icon: Clock3, color: 'text-amber-600 bg-amber-50' },
          { label: 'Automatic Sync', value: data.enabled ? 'Enabled' : 'Paused', sub: 'Scheduled by your server cron', icon: CalendarDays, color: 'text-blue-600 bg-blue-50' },
          { label: 'Sync Health', value: data.failed_rooms ? `${data.failed_rooms} failed` : data.last_sync ? 'No reported failures' : 'Not verified', sub: 'Based on latest recorded results', icon: Activity, color: 'text-rose-600 bg-rose-50' },
        ].map(item => <div key={item.label} className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><div className="flex items-start gap-3"><div className={`rounded-xl p-3 ${item.color}`}><item.icon className="h-6 w-6" /></div><div className="min-w-0"><p className="text-sm text-slate-500">{item.label}</p><p className="mt-1 break-words text-xl font-bold text-slate-900">{item.value}</p><p className="mt-1 text-xs text-slate-400">{item.sub}</p></div></div></div>)}
      </div>
      <section className="rounded-2xl border border-blue-100 bg-white p-5 shadow-sm sm:p-7">
        <div className="flex flex-wrap items-center gap-4"><div className="rounded-xl bg-blue-50 p-3 text-blue-600"><RefreshCw className="h-7 w-7" /></div><div className="min-w-0 flex-1"><h2 className="text-xl font-bold text-slate-900">Automatic Calendar Sync</h2><p className="mt-1 text-sm text-slate-500">One master switch controls imports for every configured room.</p></div><button type="button" role="switch" aria-label="Automatic calendar synchronization" aria-checked={!!data.enabled} disabled={busy} onClick={() => act('toggle', { enabled: !data.enabled })} className={`relative h-8 w-14 rounded-full transition-colors disabled:opacity-50 ${data.enabled ? 'bg-blue-600' : 'bg-slate-300'}`}><span className={`absolute left-1 top-1 h-6 w-6 rounded-full bg-white shadow transition-transform ${data.enabled ? 'translate-x-6' : ''}`} /></button><span className={`rounded-full px-3 py-1 text-xs font-semibold ${data.enabled ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600'}`}>{data.enabled ? 'Enabled' : 'Paused'}</span></div>
        <div className="mt-5 flex flex-col gap-4 rounded-xl border border-slate-200 bg-slate-50/70 p-4 sm:flex-row sm:items-center sm:justify-between"><div><p className="font-semibold text-slate-800">Master synchronization control</p><p className="mt-1 text-sm text-slate-500">Automatic imports require a configured server cron job. Disabling imports does not remove existing reservations.</p></div><Button type="button" disabled={!data.enabled || busy || !data.configured_rooms} onClick={() => act('sync')}><RefreshCw className={`mr-2 h-4 w-4 ${busy ? 'animate-spin' : ''}`} />Sync Now</Button></div>
        {!data.legacy_enabled && <p className="mt-4 rounded-xl bg-amber-50 p-3 text-sm text-amber-800">Enable ICS_SYNC_ENABLED in your server configuration to permit imports.</p>}
      </section>
      <section className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-6"><div className="mb-5 flex items-center justify-between gap-3"><div><h2 className="text-lg font-bold text-slate-900">Room Calendar Status</h2><p className="mt-1 text-sm text-slate-500">Manage each room's Booking.com iCal URL and run individual imports.</p></div><Button variant="outline" type="button" disabled={busy} onClick={() => refresh().catch(e => setError(e.message))}><RefreshCw className="mr-2 h-4 w-4" />Refresh</Button></div>
        <div className="overflow-x-auto"><table className="w-full min-w-[780px] text-left text-sm"><thead className="bg-slate-50 text-slate-500"><tr>{['Room', 'iCal Calendar', 'Connection / Sync Status', 'Last Sync', 'Actions'].map(x => <th key={x} className="px-4 py-3 font-semibold">{x}</th>)}</tr></thead><tbody>{rooms.length ? rooms.map(room => <tr key={room.id} className="border-t border-slate-100"><td className="px-4 py-4 font-semibold text-slate-900">{room.room_name}</td><td className="max-w-[250px] px-4 py-4"><span className="flex items-center gap-2 text-blue-600"><Link2 className="h-4 w-4 shrink-0" /><span className="truncate">{room.booking_ical_url}</span></span></td><td className="px-4 py-4"><span className={`rounded-full px-3 py-1 text-xs font-semibold ${room.last_sync_status === 'success' ? 'bg-emerald-50 text-emerald-700' : room.last_sync_status === 'failed' ? 'bg-red-50 text-red-700' : 'bg-amber-50 text-amber-700'}`}>{room.last_sync_status === 'success' ? 'Synced' : room.last_sync_status === 'failed' ? 'Failed' : 'Not verified'}</span>{room.last_sync_status === 'failed' && room.last_sync_error && <p className="mt-2 max-w-xs text-xs text-red-600">{room.last_sync_error}</p>}</td><td className="px-4 py-4 text-slate-600">{dateText(room.last_sync_completed_at)}</td><td className="relative px-4 py-4"><button type="button" aria-label={`Actions for ${room.room_name}`} className="rounded-lg border border-slate-200 p-2 hover:bg-slate-50" onClick={() => setMenu(menu === room.id ? null : room.id)}><MoreHorizontal className="h-4 w-4" /></button>{menu === room.id && <div className="absolute right-5 top-12 z-20 w-52 rounded-xl border border-slate-200 bg-white p-1 shadow-xl"><button className="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left hover:bg-slate-50" onClick={() => { setEditing(room); setUrl(room.booking_ical_url || ''); setMenu(null) }}><Pencil className="h-4 w-4" />Edit calendar URL</button><button disabled={!data.enabled || roomBusy !== null} className="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left hover:bg-slate-50 disabled:opacity-40" onClick={() => act('sync_room', { room_id: Number(room.id) }, room.id)}><RefreshCw className="h-4 w-4" />Sync this room</button><button className="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-red-600 hover:bg-red-50" onClick={() => { setMenu(null); if (window.confirm(`Remove the Booking.com calendar URL for ${room.room_name}? Existing imported reservations will remain.`)) act('update_url', { room_id: Number(room.id), url: '' }, room.id) }}><Trash2 className="h-4 w-4" />Remove calendar URL</button></div>}</td></tr>) : <tr><td colSpan="5" className="py-12 text-center text-slate-500">No calendars configured. Add an iCal URL in Rooms → Edit Room.</td></tr>}</tbody></table></div>
      </section>
    </>}
    {editing && <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4" role="presentation" onMouseDown={e => { if (e.target === e.currentTarget) setEditing(null) }}><form className="w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl" onSubmit={async e => { e.preventDefault(); if (await act('update_url', { room_id: Number(editing.id), url: url.trim() }, editing.id)) setEditing(null) }}><div className="flex items-start justify-between"><div><h3 className="text-lg font-bold text-slate-900">Edit Calendar URL</h3><p className="mt-1 text-sm text-slate-500">{editing.room_name}</p></div><button type="button" aria-label="Close" onClick={() => setEditing(null)}><X className="h-5 w-5" /></button></div><label className="mt-6 block text-sm font-medium text-slate-700" htmlFor="calendar-url">Booking.com iCal export URL</label><input id="calendar-url" type="url" required value={url} onChange={e => setUrl(e.target.value)} placeholder="https://ical.booking.com/..." className="mt-2 w-full rounded-xl border border-slate-300 px-3 py-3 text-sm outline-none focus:border-blue-500" /><p className="mt-2 text-xs text-slate-500">Only HTTPS Booking.com calendar URLs are accepted. Saving the URL does not run synchronization automatically.</p><div className="mt-6 flex justify-end gap-2"><Button type="button" variant="outline" onClick={() => setEditing(null)}>Cancel</Button><Button type="submit" disabled={roomBusy !== null}>{roomBusy !== null ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : <CheckCircle2 className="mr-2 h-4 w-4" />}Save URL</Button></div></form></div>}
  </div>
}
