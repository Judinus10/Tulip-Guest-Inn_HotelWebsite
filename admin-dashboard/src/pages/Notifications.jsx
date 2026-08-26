import { useEffect, useMemo, useRef, useState } from 'react'
import { useLocation, useNavigate } from 'react-router-dom'
import {
  AlertTriangle,
  Bell,
  CalendarCheck,
  CheckCircle2,
  Clock,
  Eye,
  MoreHorizontal,
  LogIn,
  LogOut,
  Search,
  Trash2,
} from 'lucide-react'
import { PageHeader } from '@/components/ui/page-header'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { useToastState } from '@/context/ToastContext'

import { fetchNotificationActivities, getNotificationNavigation, markNotificationActivityRead } from '@/services/notificationsApi'

const typeVariants = {
  Booking: 'default',
  Payment: 'success',
  'Check-in': 'info',
  'Check-out': 'secondary',
  Cancellation: 'destructive',
  Contact: 'info',
  Offer: 'warning',
  System: 'outline',
}

const statusVariants = {
  New: 'warning',
  Viewed: 'secondary',
  Resolved: 'success',
}

const types = ['All Types', 'Booking', 'Payment', 'Contact', 'Check-in', 'Check-out', 'Cancellation', 'Offer', 'System']
const statuses = ['All Statuses', 'New', 'Viewed', 'Resolved']
const NOTIFICATIONS_PER_PAGE = 6

function formatDate(value) {
  if (!value) return '-'
  const date = new Date(String(value).replace(' ', 'T'))
  if (Number.isNaN(date.getTime())) return '-'
  return new Intl.DateTimeFormat('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  }).format(date)
}

function formatReferenceId(value) {
  const reference = String(value || '').trim()
  if (!reference) return '-'

  // Booking/contact IDs are already short and readable. Only shorten long payment/transaction references.
  if (reference.length <= 18 || reference.startsWith('BK-') || reference.startsWith('INQ-')) {
    return reference
  }

  return `${reference.slice(0, 14)}...`
}

function StatCard({ title, value, icon: Icon }) {
  return (
    <Card>
      <CardContent className="p-4 sm:p-5">
        <div className="flex items-center justify-between gap-3 sm:gap-4">
          <div className="min-w-0">
            <p className="truncate text-xs font-medium text-text-secondary sm:text-sm">{title}</p>
            <p className="mt-2 text-xl font-bold text-text-primary sm:text-2xl">{value}</p>
          </div>
          <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-primary-600 sm:h-11 sm:w-11">
            <Icon className="h-5 w-5" />
          </div>
        </div>
      </CardContent>
    </Card>
  )
}


function Pagination({ page, totalPages, totalItems, startItem, endItem, onPageChange }) {
  if (totalItems === 0) return null

  return (
    <div className="sticky bottom-0 z-20 flex w-full flex-col gap-3 border-t border-border bg-white/95 px-3 py-3 shadow-[0_-8px_18px_rgba(15,23,42,0.06)] backdrop-blur sm:px-5 md:flex-row md:items-center md:justify-between">
      <p className="text-center text-sm font-medium text-text-secondary md:text-left">Showing {startItem}-{endItem} of {totalItems}</p>
      <div className="flex w-full items-center justify-center gap-2 md:w-auto md:justify-end">
        <Button type="button" variant="outline" size="sm" disabled={page === 1} onClick={() => onPageChange(page - 1)}>Previous</Button>
        <span className="shrink-0 rounded-lg border border-border bg-white px-3 py-1.5 text-sm font-bold text-text-primary">{page} / {totalPages}</span>
        <Button type="button" variant="outline" size="sm" disabled={page === totalPages} onClick={() => onPageChange(page + 1)}>Next</Button>
      </div>
    </div>
  )
}


export default function Notifications() {
  const navigate = useNavigate()
  const location = useLocation()
  const focusRefs = useRef({})
  const focusRequestRef = useRef('')
  const [activities, setActivities] = useState([])
  const [selectedActivity, setSelectedActivity] = useState(null)
  const [search, setSearch] = useState('')
  const [typeFilter, setTypeFilter] = useState('All Types')
  const [statusFilter, setStatusFilter] = useState('All Statuses')
  const [toast, setToast] = useToastState('')
  const [openActionId, setOpenActionId] = useState(null)
  const [isLoading, setIsLoading] = useState(true)
  const [currentPage, setCurrentPage] = useState(1)
  const [focusedNotificationId, setFocusedNotificationId] = useState('')
  const [flashNotificationId, setFlashNotificationId] = useState('')

  const loadActivities = async () => {
    setIsLoading(true)
    try {
      setActivities(await fetchNotificationActivities())
    } catch (error) {
      showToast(error.message || 'Could not load live notifications.')
    } finally {
      setIsLoading(false)
    }
  }

  useEffect(() => {
    loadActivities()
  }, [])

  const stats = useMemo(
    () => ({
      newBookings: activities.filter((item) => item.type === 'Booking').length,
      pendingConfirmations: activities.filter((item) => item.type === 'Booking' && item.status === 'New').length,
      checkIns: activities.filter((item) => item.type === 'Check-in').length,
      checkOuts: activities.filter((item) => item.type === 'Check-out').length,
      cancellations: activities.filter((item) => item.type === 'Cancellation').length,
      unread: activities.filter((item) => item.status === 'New').length,
    }),
    [activities]
  )

  const filteredActivities = useMemo(() => {
    const query = search.trim().toLowerCase()
    return activities
      .filter((item) => {
        const matchesSearch = !query || [item.title, item.reference_id, item.description, item.related_room, item.related_booking, item.details, item.searchText]
          .join(' ')
          .toLowerCase()
          .includes(query)
        const matchesType = typeFilter === 'All Types' || item.type === typeFilter
        const matchesStatus = statusFilter === 'All Statuses' || item.status === statusFilter
        return matchesSearch && matchesType && matchesStatus
      })
      .sort((a, b) => new Date(b.created_at) - new Date(a.created_at))
  }, [activities, search, typeFilter, statusFilter])


  useEffect(() => {
    if (focusRequestRef.current) return
    setCurrentPage(1)
  }, [search, typeFilter, statusFilter])

  const totalPages = Math.max(1, Math.ceil(filteredActivities.length / NOTIFICATIONS_PER_PAGE))
  const safeCurrentPage = Math.min(currentPage, totalPages)
  const startIndex = (safeCurrentPage - 1) * NOTIFICATIONS_PER_PAGE
  const paginatedActivities = filteredActivities.slice(startIndex, startIndex + NOTIFICATIONS_PER_PAGE)
  const startItem = filteredActivities.length === 0 ? 0 : startIndex + 1
  const endItem = Math.min(startIndex + NOTIFICATIONS_PER_PAGE, filteredActivities.length)

  useEffect(() => {
    const queryFocus = new URLSearchParams(location.search).get('focus')
    const stateFocus = location.state?.notificationFocus?.id || ''
    const focusValue = queryFocus || stateFocus
    if (!focusValue) return

    focusRequestRef.current = String(focusValue)
    setSearch('')
    setTypeFilter('All Types')
    setStatusFilter('All Statuses')
    setFocusedNotificationId(String(focusValue))
  }, [location.search, location.state])

  useEffect(() => {
    if (!focusedNotificationId || isLoading) return

    const focusedIndex = filteredActivities.findIndex((item) => {
      const values = [item.id, item.reference_id, item.related_booking]
      return values.some((value) => value != null && String(value) === String(focusedNotificationId))
    })

    if (focusedIndex < 0) return

    setCurrentPage(Math.floor(focusedIndex / NOTIFICATIONS_PER_PAGE) + 1)
  }, [focusedNotificationId, filteredActivities, isLoading])

  useEffect(() => {
    if (!focusedNotificationId || isLoading) return

    const element = focusRefs.current[focusedNotificationId]
    if (!element) return

    const timer = window.setTimeout(() => {
      element.scrollIntoView({ behavior: 'smooth', block: 'center' })
      setFlashNotificationId(String(focusedNotificationId))
      window.setTimeout(() => {
        setFlashNotificationId('')
        setFocusedNotificationId('')
        focusRequestRef.current = ''
      }, 1400)
    }, 300)

    return () => window.clearTimeout(timer)
  }, [focusedNotificationId, paginatedActivities, isLoading, currentPage])


  useEffect(() => {
    if (!openActionId) return

    const handleOutsideClick = (event) => {
      if (!event.target.closest('[data-notification-action-menu]')) {
        setOpenActionId(null)
      }
    }

    document.addEventListener('mousedown', handleOutsideClick)
    return () => document.removeEventListener('mousedown', handleOutsideClick)
  }, [openActionId])

  const showToast = (message) => {
    setToast(message)
    window.setTimeout(() => setToast(''), 2200)
  }

  const markViewed = (id) => {
    markNotificationActivityRead(id)
    setActivities((current) => current.map((item) => (item.id === id ? { ...item, status: 'Viewed' } : item)))
    setSelectedActivity((current) => (current?.id === id ? { ...current, status: 'Viewed' } : current))
    showToast('Notification marked as viewed.')
  }

  const goToRelatedCase = (activity) => {
    markViewed(activity.id)
    const target = getNotificationNavigation(activity)
    navigate(target.pathname, { state: target.state })
  }

  const deleteActivity = (id) => {
    if (!window.confirm('Delete this activity notification?')) return
    setActivities((current) => current.filter((item) => item.id !== id))
    setSelectedActivity(null)
    showToast('Notification deleted.')
  }

  return (
    <div className="space-y-6">
      <PageHeader
        title="Hotel Activity Center"
        description="Monitor booking, payment, check-in, cancellation, offer, and system activity."
      />

      {toast && (
        <div className="fixed right-6 top-6 z-50 rounded-xl bg-slate-900 px-4 py-3 text-sm font-medium text-white shadow-lg">
          {toast}
        </div>
      )}

      <div className="grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-3 2xl:grid-cols-6">
        <StatCard title="New Bookings" value={stats.newBookings} icon={CalendarCheck} />
        <StatCard title="Pending Confirmations" value={stats.pendingConfirmations} icon={Clock} />
        <StatCard title="Check-ins Today" value={stats.checkIns} icon={LogIn} />
        <StatCard title="Check-outs Today" value={stats.checkOuts} icon={LogOut} />
        <StatCard title="Cancellation Requests" value={stats.cancellations} icon={AlertTriangle} />
        <StatCard title="Unread Notifications" value={stats.unread} icon={Bell} />
      </div>

      <Card>
        <CardHeader>
          <div className="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
            <div>
              <CardTitle>Notification Log</CardTitle>
              <p className="mt-1 text-sm text-text-secondary">Search and review operational notifications.</p>
            </div>
            <div className="grid gap-3 sm:grid-cols-3 xl:min-w-[720px]">
              <div className="relative">
                <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                <Input value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Search activity..." className="pl-9" />
              </div>
              <select
                value={typeFilter}
                onChange={(event) => setTypeFilter(event.target.value)}
                className="h-10 rounded-lg border border-border bg-white px-3 text-sm text-text-primary focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-100"
              >
                {types.map((type) => <option key={type}>{type}</option>)}
              </select>
              <select
                value={statusFilter}
                onChange={(event) => setStatusFilter(event.target.value)}
                className="h-10 rounded-lg border border-border bg-white px-3 text-sm text-text-primary focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-100"
              >
                {statuses.map((status) => <option key={status}>{status}</option>)}
              </select>
            </div>
          </div>
        </CardHeader>
        <CardContent>
          {isLoading ? (
            <div className="rounded-xl border border-dashed border-border bg-slate-50 p-8 text-center text-sm text-text-secondary">
              Loading live notifications...
            </div>
          ) : filteredActivities.length === 0 ? (
            <div className="rounded-xl border border-dashed border-border bg-slate-50 p-8 text-center text-sm text-text-secondary">
              No live notifications match the selected filters.
            </div>
          ) : (
            <div className="rounded-xl border border-border">
              <div className="overflow-x-auto">
                <table className="w-full min-w-[1080px] text-sm">
                <thead className="bg-slate-50">
                  <tr>
                    {['Type', 'Reference ID', 'Description', 'Related Room', 'Related Booking', 'Date & Time', 'Status', 'Actions'].map((header) => (
                      <th key={header} className="whitespace-nowrap px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-text-secondary">
                        {header}
                      </th>
                    ))}
                  </tr>
                </thead>
                <tbody className="divide-y divide-border bg-white">
                  {paginatedActivities.map((item) => {
                    const shouldFlashNotification = Boolean(
                      flashNotificationId &&
                      [item.id, item.reference_id, item.related_booking].some(
                        (value) => value != null && String(value) === String(flashNotificationId)
                      )
                    )

                    return (
                    <tr
                      key={item.id}
                      ref={(element) => {
                        if (element) {
                          if (item.id) focusRefs.current[item.id] = element
                          if (item.reference_id) focusRefs.current[item.reference_id] = element
                          if (item.related_booking) focusRefs.current[item.related_booking] = element
                        }
                      }}
                      className={`align-middle hover:bg-blue-50/40 ${shouldFlashNotification ? 'dashboard-focus-flash' : ''}`}
                    >
                      <td className="whitespace-nowrap px-4 py-3"><Badge variant={typeVariants[item.type] || 'outline'}>{item.type}</Badge></td>
                      <td className="max-w-[170px] whitespace-nowrap px-4 py-3 font-semibold text-primary-700">
                        <span className="block truncate" title={item.reference_id || '-'}>
                          {formatReferenceId(item.reference_id)}
                        </span>
                      </td>
                      <td className="px-4 py-3 text-text-primary">
                        <div className="max-w-[320px]">
                          <p className="line-clamp-1 font-medium text-text-primary">{item.title}</p>
                          <p className="mt-0.5 line-clamp-1 text-xs text-text-secondary">{item.description}</p>
                        </div>
                      </td>
                      <td className="whitespace-nowrap px-4 py-3 text-text-secondary">{item.related_room}</td>
                      <td className="whitespace-nowrap px-4 py-3 text-text-secondary">{item.related_booking}</td>
                      <td className="whitespace-nowrap px-4 py-3 text-text-secondary">{formatDate(item.created_at)}</td>
                      <td className="whitespace-nowrap px-4 py-3"><Badge variant={statusVariants[item.status]}>{item.status}</Badge></td>
                      <td className="whitespace-nowrap px-4 py-3">
                        <div className="relative inline-block text-left" data-notification-action-menu>
                          <Button
                            size="sm"
                            variant="outline"
                            className="h-9 gap-2"
                            onClick={() => setOpenActionId((current) => (current === item.id ? null : item.id))}
                          >
                            Actions
                            <MoreHorizontal className="h-4 w-4" />
                          </Button>

                          {openActionId === item.id && (
                            <div className="absolute right-0 z-30 mt-2 w-44 overflow-hidden rounded-xl border border-border bg-white py-1 shadow-xl">
                              <button
                                type="button"
                                onClick={() => {
                                  setSelectedActivity(item)
                                  setOpenActionId(null)
                                }}
                                className="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-text-primary hover:bg-blue-50"
                              >
                                <Eye className="h-4 w-4 text-primary-600" />
                                View
                              </button>
                              <button
                                type="button"
                                onClick={() => {
                                  goToRelatedCase(item)
                                  setOpenActionId(null)
                                }}
                                className="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-text-primary hover:bg-blue-50"
                              >
                                <Eye className="h-4 w-4 text-primary-600" />
                                Open Case
                              </button>
                              <button
                                type="button"
                                onClick={() => {
                                  markViewed(item.id)
                                  setOpenActionId(null)
                                }}
                                className="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-text-primary hover:bg-blue-50"
                              >
                                <CheckCircle2 className="h-4 w-4 text-primary-600" />
                                Mark Read
                              </button>
                              <button
                                type="button"
                                onClick={() => {
                                  setOpenActionId(null)
                                  deleteActivity(item.id)
                                }}
                                className="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-red-600 hover:bg-red-50"
                              >
                                <Trash2 className="h-4 w-4" />
                                Delete
                              </button>
                            </div>
                          )}
                        </div>
                      </td>
                    </tr>
                    )
                  })}
                </tbody>
                </table>
              </div>
              <Pagination page={safeCurrentPage} totalPages={totalPages} totalItems={filteredActivities.length} startItem={startItem} endItem={endItem} onPageChange={setCurrentPage} />
            </div>
          )}
        </CardContent>
      </Card>

      {selectedActivity && (
        <div
          className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4"
          onMouseDown={(event) => {
            if (event.target === event.currentTarget) setSelectedActivity(null)
          }}
        >
          <div className="w-full max-w-2xl rounded-2xl bg-white shadow-2xl">
            <div className="border-b border-border p-6">
              <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                  <p className="text-sm font-semibold text-primary-700">{selectedActivity.reference_id}</p>
                  <h2 className="mt-1 text-xl font-bold text-text-primary">{selectedActivity.title}</h2>
                  <p className="mt-1 text-sm text-text-secondary">{formatDate(selectedActivity.created_at)}</p>
                </div>
                <div className="flex gap-2">
                  <Badge variant={typeVariants[selectedActivity.type] || 'outline'}>{selectedActivity.type}</Badge>
                  <Badge variant={statusVariants[selectedActivity.status]}>{selectedActivity.status}</Badge>
                </div>
              </div>
            </div>
            <div className="space-y-4 p-6">
              <div className="rounded-xl border border-border p-4">
                <h3 className="text-sm font-semibold text-text-primary">Activity Details</h3>
                <p className="mt-3 whitespace-pre-line text-sm leading-6 text-text-secondary">{selectedActivity.details}</p>
              </div>
              <div className="grid gap-4 sm:grid-cols-2">
                <div className="rounded-xl border border-border p-4 text-sm">
                  <p><span className="text-text-secondary">Related Room:</span> {selectedActivity.related_room}</p>
                  <p className="mt-2"><span className="text-text-secondary">Related Booking:</span> {selectedActivity.related_booking}</p>
                </div>
                <div className="rounded-xl border border-border p-4 text-sm">
                  <p><span className="text-text-secondary">Type:</span> {selectedActivity.type}</p>
                  <p className="mt-2"><span className="text-text-secondary">Status:</span> {selectedActivity.status}</p>
                </div>
              </div>
            </div>
            <div className="flex flex-col gap-2 border-t border-border p-6 sm:flex-row sm:justify-end">
              <Button variant="outline" onClick={() => goToRelatedCase(selectedActivity)}>Open Case</Button>
              <Button variant="outline" onClick={() => markViewed(selectedActivity.id)}>Mark Read</Button>
              <Button variant="outline" onClick={() => deleteActivity(selectedActivity.id)}>Delete</Button>
              <Button onClick={() => setSelectedActivity(null)}>Close</Button>
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
