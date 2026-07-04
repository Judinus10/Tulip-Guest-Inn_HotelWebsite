import { useEffect, useMemo, useRef, useState } from 'react'
import { useLocation } from 'react-router-dom'
import {
  CalendarDays,
  CheckCircle2,
  CreditCard,
  Download,
  Eye,
  Filter,
  Hotel,
  Mail,
  MoreVertical,
  Moon,
  Phone,
  Plus,
  Search,
  Trash2,
  UserRound,
  WalletCards,
  X,
  XCircle,
} from 'lucide-react'
import { PageHeader } from '@/components/ui/page-header'
import { Button } from '@/components/ui/button'
import { Dropdown, DropdownItem } from '@/components/ui/dropdown'
import { Badge } from '@/components/ui/badge'
import { Card, CardContent } from '@/components/ui/card'
import { Input, Label } from '@/components/ui/input'
import { bookingRooms, bookingStatuses, initialBookings, paymentStatuses } from '@/data/bookingData'
import { listRooms } from '@/services/roomsApi'
import { exportCsv, exportExcel, exportPdf } from '@/utils/exportData'
import {
  createManualBooking,
  deleteBooking,
  fetchBookings,
  paymentMethodOptions,
  updateBookingAndPaymentStatus,
  updateBookingStatus,
  updatePaymentStatus,
} from '@/services/bookingsApi'

const BOOKINGS_PER_PAGE = 6

const currencyFormatter = new Intl.NumberFormat('en-US', {
  style: 'currency',
  currency: 'LKR',
  maximumFractionDigits: 0,
})

const dateFormatter = new Intl.DateTimeFormat('en-US', {
  month: 'short',
  day: 'numeric',
  year: 'numeric',
})

const shortDateFormatter = new Intl.DateTimeFormat('en-US', {
  month: 'short',
  day: 'numeric',
})

const bookingStatusVariant = {
  pending: 'warning',
  confirmed: 'success',
  cancelled: 'destructive',
}

const paymentStatusVariant = {
  pending: 'warning',
  paid: 'success',
  failed: 'destructive',
  cancelled: 'secondary',
  refunded: 'secondary',
  no_pay: 'secondary',
}

const emptyManualBooking = {
  full_name: '',
  email: '',
  phone: '',
  room_name: '',
  check_in_date: '',
  check_out_date: '',
  guests: 1,
  payment_status: 'pending',
  payment_method: 'Cash',
  message: '',
}

function formatDate(date, formatter = dateFormatter) {
  if (!date) return '-'

  const rawValue = String(date).trim()
  if (!rawValue || rawValue === '0000-00-00' || rawValue === '0000-00-00 00:00:00') return '-'

  // API can return either YYYY-MM-DD or full MySQL datetime.
  // Do not blindly append T00:00:00 to a datetime string; that turns valid DB values into Invalid Date.
  const dateOnly = rawValue.includes(' ') ? rawValue.split(' ')[0] : rawValue.split('T')[0]
  const parsed = new Date(`${dateOnly}T00:00:00`)
  if (Number.isNaN(parsed.getTime())) return '-'
  return formatter.format(parsed)
}

function formatMoney(amount) {
  return currencyFormatter.format(Number(amount || 0))
}

function titleCaseStatus(value) {
  if (!value) return '-'
  return String(value)
    .trim()
    .replace(/_/g, ' ')
    .replace(/\s+/g, ' ')
    .split(' ')
    .filter(Boolean)
    .map((word) => word.charAt(0).toUpperCase() + word.slice(1).toLowerCase())
    .join(' ')
}

function humanizeBookingStatus(value) {
  const normalized = String(value || 'pending').trim().toLowerCase().replace(/^booking\s+/, '').replace(/\s+/g, '_')

  if (normalized === 'confirmed') return 'Confirmed'
  if (normalized === 'cancelled' || normalized === 'canceled') return 'Cancelled'
  return 'Pending'
}

function humanizePaymentStatus(value) {
  const normalized = String(value || 'pending').trim().toLowerCase().replace(/^payment\s+/, '').replace(/\s+/g, '_')

  if (normalized === 'paid') return 'Paid'
  if (normalized === 'failed') return 'Failed'
  if (normalized === 'cancelled' || normalized === 'canceled') return 'Cancelled'
  if (normalized === 'refunded') return 'Refunded'
  if (normalized === 'no_pay' || normalized === 'nopay' || normalized === 'no_payment') return 'No Pay'
  return 'Payment Pending'
}

function normalizeRoom(room) {
  return {
    id: Number(room.id || 0),
    room_name: room.room_name || room.name || '',
    room_type: room.room_type || room.type || room.floor || 'Guest House',
    price_per_night: Number(room.price_per_night || room.price || room.rate || 0),
    capacity: Number(room.capacity || room.max_guests || room.guests || 1),
    status: room.status || 'Available',
  }
}

function getRoom(roomId, roomName = '', rooms = bookingRooms) {
  return (
    rooms.find((room) => Number(room.id) === Number(roomId)) ||
    rooms.find((room) => room.room_name === roomName) ||
    bookingRooms.find((room) => Number(room.id) === Number(roomId)) ||
    bookingRooms.find((room) => room.room_name === roomName)
  )
}

function getNights(checkIn, checkOut) {
  if (!checkIn || !checkOut) return 0
  const start = new Date(`${checkIn}T00:00:00`)
  const end = new Date(`${checkOut}T00:00:00`)
  if (Number.isNaN(start.getTime()) || Number.isNaN(end.getTime())) return 0
  return Math.max(0, Math.round((end.getTime() - start.getTime()) / 86400000))
}


function getTodayInputDate() {
  return new Date().toISOString().slice(0, 10)
}

function isActiveBookingForAvailability(booking) {
  const bookingStatus = String(booking.booking_status || booking.status || '').toLowerCase()
  const paymentStatus = String(booking.payment_status || '').toLowerCase().replace(/^payment\s+/, '')

  if (bookingStatus === 'cancelled' || bookingStatus === 'canceled') return false
  if (['failed', 'cancelled', 'canceled', 'refunded'].includes(paymentStatus)) return false

  return ['pending', 'confirmed'].includes(bookingStatus)
}

function hasDateOverlap(requestedCheckIn, requestedCheckOut, existingCheckIn, existingCheckOut) {
  if (!requestedCheckIn || !requestedCheckOut || !existingCheckIn || !existingCheckOut) return false

  return requestedCheckIn < existingCheckOut && requestedCheckOut > existingCheckIn
}

function isRoomAvailableForDates(roomName, checkInDate, checkOutDate, bookings) {
  if (!roomName || !checkInDate || !checkOutDate) return true

  return !bookings.some((booking) => {
    const bookingRoom = String(booking.room_name || '').trim().toLowerCase()
    const selectedRoom = String(roomName || '').trim().toLowerCase()

    if (bookingRoom !== selectedRoom) return false
    if (!isActiveBookingForAvailability(booking)) return false

    return hasDateOverlap(checkInDate, checkOutDate, booking.check_in || booking.check_in_date, booking.check_out || booking.check_out_date)
  })
}

function getUnavailableRoomNames(checkInDate, checkOutDate, bookings) {
  if (!checkInDate || !checkOutDate || checkOutDate <= checkInDate) return new Set()

  return new Set(
    bookings
      .filter((booking) => isActiveBookingForAvailability(booking))
      .filter((booking) => hasDateOverlap(checkInDate, checkOutDate, booking.check_in || booking.check_in_date, booking.check_out || booking.check_out_date))
      .map((booking) => String(booking.room_name || '').trim().toLowerCase())
      .filter(Boolean)
  )
}

function errorClass(hasError) {
  return hasError
    ? 'border-red-500 bg-red-50 focus:border-red-500 focus:ring-red-500/20'
    : 'border-border bg-white focus:border-blue-500 focus:ring-blue-500/20'
}


function buildBookingExportRows(bookings) {
  return bookings.map((booking) => ({
    'Booking No': booking.booking_no || '-',
    'Guest Name': booking.guest_name || '-',
    Phone: booking.guest_phone || '-',
    Email: booking.guest_email || '-',
    Room: booking.room_name || '-',
    'Check In': booking.check_in || '-',
    'Check Out': booking.check_out || '-',
    Nights: booking.total_nights || 0,
    Guests: booking.guests || 0,
    Amount: Number(booking.total_amount || 0),
    'Booking Status': humanizeBookingStatus(booking.booking_status),
    'Payment Status': humanizePaymentStatus(booking.payment_status),
    'Payment Method': booking.payment_method || '-',
    'Created Date': booking.created_at || '-',
  }))
}

function FieldError({ message }) {
  if (!message) return null
  return <p className="text-xs font-semibold text-red-600">{message}</p>
}

function Modal({ title, description, children, onClose, size = 'max-w-3xl' }) {
  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 p-4 backdrop-blur-sm" onMouseDown={onClose}>
      <div className={`max-h-[90vh] w-full ${size} overflow-hidden rounded-2xl bg-white shadow-2xl`} onMouseDown={(event) => event.stopPropagation()}>
        <div className="flex items-start justify-between border-b border-border px-6 py-5">
          <div>
            <h2 className="text-lg font-bold text-text-primary">{title}</h2>
            {description ? <p className="mt-1 text-sm text-text-secondary">{description}</p> : null}
          </div>
          <button
            type="button"
            onClick={onClose}
            className="rounded-lg p-2 text-slate-500 transition hover:bg-slate-100 hover:text-slate-900"
            aria-label="Close modal"
          >
            <X className="h-5 w-5" />
          </button>
        </div>
        <div className="max-h-[calc(90vh-88px)] overflow-y-auto p-6">{children}</div>
      </div>
    </div>
  )
}

function Toast({ toast, onClose }) {
  if (!toast) return null

  const Icon = toast.type === 'error' ? XCircle : CheckCircle2
  const tone = toast.type === 'error' ? 'border-red-200 bg-red-50 text-red-800' : 'border-emerald-200 bg-emerald-50 text-emerald-800'

  return (
    <div className={`fixed right-5 top-5 z-[60] flex items-center gap-3 rounded-xl border px-4 py-3 shadow-lg ${tone}`}>
      <Icon className="h-5 w-5" />
      <p className="text-sm font-semibold">{toast.message}</p>
      <button type="button" onClick={onClose} className="ml-2 rounded p-1 hover:bg-white/60">
        <X className="h-4 w-4" />
      </button>
    </div>
  )
}

function SummaryCard({ title, value, icon: Icon, description }) {
  return (
    <Card>
      <CardContent className="flex items-center justify-between p-5">
        <div>
          <p className="text-sm font-medium text-text-secondary">{title}</p>
          <p className="mt-2 text-2xl font-bold text-text-primary">{value}</p>
          {description ? <p className="mt-1 text-xs font-medium text-text-secondary">{description}</p> : null}
        </div>
        <div className="rounded-xl bg-blue-50 p-3 text-blue-700">
          <Icon className="h-6 w-6" />
        </div>
      </CardContent>
    </Card>
  )
}

function MobileBookingCard({ booking, shouldFlashBooking, setRef, onView, onUpdateStatus, onCancel }) {
  return (
    <div ref={setRef} className={`rounded-2xl border border-border bg-white p-4 shadow-sm ${shouldFlashBooking ? 'dashboard-focus-flash' : ''}`}>
      <div className="flex min-w-0 items-start justify-between gap-3">
        <div className="min-w-0">
          <p className="text-sm font-bold text-text-primary">{booking.booking_no}</p>
          <p className="mt-1 truncate text-sm font-semibold text-text-primary">{booking.room_name}</p>
          <p className="mt-1 text-xs text-text-secondary">Created {formatDate(booking.created_at)}</p>
        </div>
        <ActionsDropdown booking={booking} onView={onView} onUpdateStatus={onUpdateStatus} onCancel={onCancel} />
      </div>

      <div className="mt-4 grid gap-3 text-sm">
        <div className="min-w-0">
          <p className="text-xs font-semibold uppercase tracking-wide text-text-secondary">Guest</p>
          <p className="mt-1 truncate font-semibold text-text-primary">{booking.guest_name}</p>
          <p className="mt-1 text-xs text-text-secondary">{booking.guest_phone}</p>
          {booking.guest_email ? <p className="mt-1 truncate text-xs text-text-secondary">{booking.guest_email}</p> : null}
        </div>

        <div className="grid grid-cols-2 gap-3">
          <div>
            <p className="text-xs font-semibold uppercase tracking-wide text-text-secondary">Stay</p>
            <p className="mt-1 font-semibold text-text-primary">{formatDate(booking.check_in, shortDateFormatter)} - {formatDate(booking.check_out, shortDateFormatter)}</p>
            <p className="mt-1 text-xs text-text-secondary">{booking.total_nights} night{booking.total_nights === 1 ? '' : 's'} · {booking.guests} guest{booking.guests === 1 ? '' : 's'}</p>
          </div>
          <div>
            <p className="text-xs font-semibold uppercase tracking-wide text-text-secondary">Amount</p>
            <p className="mt-1 font-bold text-text-primary">{formatMoney(booking.total_amount)}</p>
          </div>
        </div>

        <div className="flex flex-wrap gap-2">
          <Badge variant={bookingStatusVariant[booking.booking_status] || 'warning'}>{humanizeBookingStatus(booking.booking_status)}</Badge>
          <Badge variant={paymentStatusVariant[booking.payment_status] || 'warning'}>{humanizePaymentStatus(booking.payment_status)}</Badge>
        </div>
      </div>
    </div>
  )
}

function ActionsDropdown({ booking, onView, onUpdateStatus, onCancel }) {
  const [open, setOpen] = useState(false)
  const dropdownRef = useRef(null)

  useEffect(() => {
    if (!open) return undefined

    const handleOutsideClick = (event) => {
      if (!dropdownRef.current?.contains(event.target)) {
        setOpen(false)
      }
    }

    document.addEventListener('mousedown', handleOutsideClick)
    return () => document.removeEventListener('mousedown', handleOutsideClick)
  }, [open])

  const handleAction = (callback) => {
    callback()
    setOpen(false)
  }

  return (
    <div ref={dropdownRef} className="relative flex justify-end">
      <Button type="button" variant="outline" size="sm" onClick={() => setOpen((value) => !value)}>
        <MoreVertical className="h-4 w-4" />
        Actions
      </Button>

      {open ? (
        <div className="absolute right-0 top-10 z-30 w-56 overflow-hidden rounded-xl border border-border bg-white py-1 shadow-xl">
          <button type="button" onClick={() => handleAction(onView)} className="flex w-full items-center gap-2 px-3 py-2 text-left text-sm font-medium text-text-primary transition hover:bg-slate-50">
            <Eye className="h-4 w-4 text-blue-700" />
            View Details
          </button>
          <button type="button" onClick={() => handleAction(onUpdateStatus)} className="flex w-full items-center gap-2 px-3 py-2 text-left text-sm font-medium text-text-primary transition hover:bg-slate-50">
            <CheckCircle2 className="h-4 w-4 text-emerald-600" />
            Update Status
          </button>
          <button
            type="button"
            disabled={booking.booking_status === 'cancelled'}
            onClick={() => handleAction(onCancel)}
            className="flex w-full items-center gap-2 px-3 py-2 text-left text-sm font-medium text-red-700 transition hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-50"
          >
            <Trash2 className="h-4 w-4" />
            Delete Booking
          </button>
        </div>
      ) : null}
    </div>
  )
}

function StatusSelectModal({ booking, onClose, onSave }) {
  const [bookingStatus, setBookingStatus] = useState(booking.booking_status)

  const isCancelling = booking.booking_status !== 'cancelled' && bookingStatus === 'cancelled'

  const handleSubmit = (event) => {
    event.preventDefault()

    if (isCancelling) {
      const confirmed = window.confirm('Cancel this booking? This action updates the booking status to cancelled.')
      if (!confirmed) return
    }

    onSave(booking.id, bookingStatus)
  }

  return (
    <Modal title="Update booking status" description={`Manage ${booking.booking_no}`} onClose={onClose} size="max-w-xl">
      <form onSubmit={handleSubmit} className="space-y-5">
        <div className="rounded-xl border border-border bg-slate-50 p-4">
          <p className="text-sm font-semibold text-text-primary">{booking.guest_name}</p>
          <p className="mt-1 text-sm text-text-secondary">{booking.room_name}</p>
        </div>

        <div className="grid gap-4 sm:grid-cols-2">
          <div className="space-y-2">
            <Label>Booking status</Label>
            <select value={bookingStatus} onChange={(event) => setBookingStatus(event.target.value)} className="h-10 w-full rounded-lg border border-border bg-white px-3 text-sm text-text-primary shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
              {bookingStatuses.map((status) => (
                <option key={status} value={status}>{humanizeBookingStatus(status)}</option>
              ))}
            </select>
          </div>

          <div className="space-y-2">
            <Label>Current status</Label>
            <div className="flex h-10 items-center rounded-lg border border-border bg-slate-50 px-3 text-sm font-semibold text-text-primary">
              {humanizeBookingStatus(booking.booking_status)}
            </div>
          </div>
        </div>

        <div className="flex justify-end gap-3 pt-2">
          <Button type="button" variant="outline" onClick={onClose}>Cancel</Button>
          <Button type="submit">Save Changes</Button>
        </div>
      </form>
    </Modal>
  )
}

function PaymentStatusModal({ booking, onClose, onSave }) {
  const [paymentStatus, setPaymentStatus] = useState(booking.payment_status)
  const [paymentMethod, setPaymentMethod] = useState(booking.payment_method || 'Cash')

  const handleSubmit = (event) => {
    event.preventDefault()
    onSave(booking.id, paymentStatus, paymentMethod)
  }

  return (
    <Modal title="Update payment" description={`${booking.booking_no} · ${booking.guest_name}`} onClose={onClose} size="max-w-xl">
      <form onSubmit={handleSubmit} className="space-y-5">
        <div className="rounded-xl border border-border bg-slate-50 p-4">
          <div className="flex items-center justify-between gap-4">
            <div>
              <p className="text-sm font-semibold text-text-primary">{booking.room_name}</p>
              <p className="mt-1 text-sm text-text-secondary">{formatDate(booking.check_in)} - {formatDate(booking.check_out)}</p>
            </div>
            <p className="text-lg font-bold text-text-primary">{formatMoney(booking.total_amount)}</p>
          </div>
        </div>

        <div className="grid gap-4 sm:grid-cols-2">
          <div className="space-y-2">
            <Label>Payment status</Label>
            <select value={paymentStatus} onChange={(event) => setPaymentStatus(event.target.value)} className="h-10 w-full rounded-lg border border-border bg-white px-3 text-sm text-text-primary shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
              {paymentStatuses.map((status) => (
                <option key={status} value={status}>{humanizePaymentStatus(status)}</option>
              ))}
            </select>
          </div>

          <div className="space-y-2">
            <Label>Payment method</Label>
            <select value={paymentMethod} onChange={(event) => setPaymentMethod(event.target.value)} className="h-10 w-full rounded-lg border border-border bg-white px-3 text-sm text-text-primary shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
              {paymentMethodOptions.map((method) => (
                <option key={method} value={method}>{method}</option>
              ))}
            </select>
          </div>
        </div>

        <div className="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm font-medium text-amber-800">
          This updates only the payment status. It does not auto-confirm the booking. Confirm the booking separately after you verify the customer/payment.
        </div>

        <div className="flex justify-end gap-3 pt-2">
          <Button type="button" variant="outline" onClick={onClose}>Cancel</Button>
          <Button type="submit">Save Payment</Button>
        </div>
      </form>
    </Modal>
  )
}


function CombinedStatusModal({ booking, focus = 'booking', onClose, onSave }) {
  const [bookingStatus, setBookingStatus] = useState(booking.booking_status || 'pending')
  const [paymentStatus, setPaymentStatus] = useState(booking.payment_status || 'pending')
  const [paymentMethod, setPaymentMethod] = useState(booking.payment_method || 'Cash')
  const [reference, setReference] = useState('')
  const [remarks, setRemarks] = useState('')
  const [sendEmail, setSendEmail] = useState(true)

  const handleSubmit = (event) => {
    event.preventDefault()

    if (booking.booking_status !== 'cancelled' && bookingStatus === 'cancelled') {
      const confirmed = window.confirm('Cancel this booking? The customer can be notified by email if Send email is checked.')
      if (!confirmed) return
    }

    onSave(booking.id, {
      booking_status: bookingStatus,
      payment_status: paymentStatus,
      payment_method: paymentMethod,
      transaction_reference: reference.trim(),
      remarks: remarks.trim(),
      send_email: sendEmail,
    })
  }

  return (
    <Modal title="Update booking & payment status" description={`${booking.booking_no} · ${booking.guest_name}`} onClose={onClose} size="max-w-2xl">
      <form onSubmit={handleSubmit} className="space-y-5">
        <div className="rounded-xl border border-border bg-slate-50 p-4">
          <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
              <p className="text-sm font-semibold text-text-primary">{booking.room_name}</p>
              <p className="mt-1 text-sm text-text-secondary">{formatDate(booking.check_in)} - {formatDate(booking.check_out)}</p>
            </div>
            <p className="text-lg font-bold text-text-primary">{formatMoney(booking.total_amount)}</p>
          </div>
        </div>

        <div className="grid gap-4 sm:grid-cols-2">
          <div className="space-y-2">
            <Label>Booking status</Label>
            <select autoFocus={focus === 'booking'} value={bookingStatus} onChange={(event) => setBookingStatus(event.target.value)} className="h-10 w-full rounded-lg border border-border bg-white px-3 text-sm text-text-primary shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
              {bookingStatuses.map((status) => (
                <option key={status} value={status}>{humanizeBookingStatus(status)}</option>
              ))}
            </select>
          </div>

          <div className="space-y-2">
            <Label>Payment status</Label>
            <select autoFocus={focus === 'payment'} value={paymentStatus} onChange={(event) => setPaymentStatus(event.target.value)} className="h-10 w-full rounded-lg border border-border bg-white px-3 text-sm text-text-primary shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
              {paymentStatuses.map((status) => (
                <option key={status} value={status}>{humanizePaymentStatus(status)}</option>
              ))}
            </select>
          </div>

          <div className="space-y-2">
            <Label>Payment method</Label>
            <select value={paymentMethod} onChange={(event) => setPaymentMethod(event.target.value)} className="h-10 w-full rounded-lg border border-border bg-white px-3 text-sm text-text-primary shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
              {paymentMethodOptions.map((method) => (
                <option key={method} value={method}>{method}</option>
              ))}
            </select>
          </div>

          <div className="space-y-2">
            <Label>Reference / transaction no.</Label>
            <Input value={reference} onChange={(event) => setReference(event.target.value)} placeholder="Optional receipt, bank slip, PayHere ID" />
          </div>
        </div>

        <div className="space-y-2">
          <Label>Remarks</Label>
          <textarea value={remarks} onChange={(event) => setRemarks(event.target.value)} rows={3} className="w-full rounded-lg border border-border bg-white px-3 py-2 text-sm text-text-primary shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20" placeholder="Optional internal note" />
        </div>

        <label className="flex items-start gap-3 rounded-xl border border-blue-100 bg-blue-50 p-4 text-sm font-medium text-blue-900">
          <input type="checkbox" checked={sendEmail} onChange={(event) => setSendEmail(event.target.checked)} className="mt-1" />
          <span>Send customer email. If booking and payment both change, the system sends one combined email, not two.</span>
        </label>

        <div className="flex justify-end gap-3 pt-2">
          <Button type="button" variant="outline" onClick={onClose}>Cancel</Button>
          <Button type="submit">Save Statuses</Button>
        </div>
      </form>
    </Modal>
  )
}

function AddBookingModal({ rooms, bookings, onClose, onSave }) {
  const [form, setForm] = useState({ ...emptyManualBooking, room_name: rooms[0]?.room_name || '' })
  const [errors, setErrors] = useState({})
  const [submitWarning, setSubmitWarning] = useState('')
  const fieldRefs = {
    full_name: useRef(null),
    email: useRef(null),
    phone: useRef(null),
    guests: useRef(null),
    room_name: useRef(null),
    check_in_date: useRef(null),
    check_out_date: useRef(null),
  }

  const today = getTodayInputDate()
  const unavailableRoomNames = useMemo(
    () => getUnavailableRoomNames(form.check_in_date, form.check_out_date, bookings),
    [form.check_in_date, form.check_out_date, bookings]
  )

  const availableRooms = useMemo(() => {
    if (!form.check_in_date || !form.check_out_date || form.check_out_date <= form.check_in_date) return rooms
    return rooms.filter((room) => !unavailableRoomNames.has(String(room.room_name || '').trim().toLowerCase()))
  }, [rooms, unavailableRoomNames, form.check_in_date, form.check_out_date])

  const selectedRoom = getRoom(0, form.room_name, rooms)
  const nights = getNights(form.check_in_date, form.check_out_date)
  const estimatedAmount = nights * Number(selectedRoom?.price_per_night || 0)
  const selectedRoomAvailable = isRoomAvailableForDates(form.room_name, form.check_in_date, form.check_out_date, bookings)
  const canCheckAvailability = form.check_in_date && form.check_out_date && form.check_out_date > form.check_in_date

  useEffect(() => {
    if (!canCheckAvailability) return

    if (form.room_name && unavailableRoomNames.has(String(form.room_name).trim().toLowerCase())) {
      const firstAvailableRoom = availableRooms[0]?.room_name || ''
      if (firstAvailableRoom) {
        setForm((current) => ({ ...current, room_name: firstAvailableRoom }))
      }
    }
  }, [canCheckAvailability, unavailableRoomNames, availableRooms, form.room_name])

  const scrollToField = (fieldName) => {
    const element = fieldRefs[fieldName]?.current
    if (!element) return

    element.scrollIntoView({ behavior: 'smooth', block: 'center' })
    window.setTimeout(() => element.focus?.(), 250)
  }

  const setFieldError = (fieldName, message) => {
    setErrors((current) => ({ ...current, [fieldName]: message }))
  }

  const updateField = (name, value) => {
    setForm((current) => ({ ...current, [name]: value }))
    setSubmitWarning('')
    if (errors[name]) {
      setErrors((current) => ({ ...current, [name]: '' }))
    }
  }

  const validateForm = () => {
    const nextErrors = {}
    const requiredFields = [
      ['full_name', 'Please fill guest name.'],
      ['email', 'Please fill email address.'],
      ['phone', 'Please fill phone number.'],
      ['guests', 'Please fill guest count.'],
      ['check_in_date', 'Please select check-in date.'],
      ['check_out_date', 'Please select check-out date.'],
      ['room_name', 'Please select a room.'],
    ]

    for (const [field, message] of requiredFields) {
      if (!String(form[field] ?? '').trim()) {
        nextErrors[field] = message
      }
    }

    if (form.email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email.trim())) {
      nextErrors.email = 'Please enter a valid email address.'
    }

    if (Number(form.guests || 0) < 1) {
      nextErrors.guests = 'Please enter at least 1 guest.'
    }

    if (form.check_in_date && form.check_in_date < today) {
      nextErrors.check_in_date = 'Check-in date cannot be in the past.'
    }

    if (form.check_in_date && form.check_out_date && form.check_out_date <= form.check_in_date) {
      nextErrors.check_out_date = 'Check-out date must be after check-in date.'
    }

    if (canCheckAvailability && !selectedRoomAvailable) {
      nextErrors.room_name = 'This room is not available for the selected dates.'
    }

    if (canCheckAvailability && availableRooms.length === 0) {
      nextErrors.room_name = 'No rooms are available for the selected dates.'
    }

    setErrors(nextErrors)

    const firstErrorField = Object.keys(nextErrors)[0]
    if (firstErrorField) {
      setSubmitWarning(nextErrors[firstErrorField] || 'Please fill the required field.')
      scrollToField(firstErrorField)
      return false
    }

    setSubmitWarning('')
    return true
  }

  const handleSubmit = (event) => {
    event.preventDefault()

    if (!validateForm()) return

    onSave(form)
  }

  return (
    <Modal title="Add manual reservation" description="Use this for walk-in, phone, onsite, and bank-transfer reservations." onClose={onClose} size="max-w-3xl">
      <form onSubmit={handleSubmit} noValidate className="space-y-5">
        {submitWarning ? (
          <div className="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">
            {submitWarning}
          </div>
        ) : null}

        <div className="grid gap-4 md:grid-cols-2">
          <div className="space-y-2">
            <Label>Guest name *</Label>
            <Input
              ref={fieldRefs.full_name}
              value={form.full_name}
              onChange={(event) => updateField('full_name', event.target.value)}
              placeholder="Customer full name"
              className={errorClass(Boolean(errors.full_name))}
              aria-invalid={Boolean(errors.full_name)}
            />
            <FieldError message={errors.full_name} />
          </div>

          <div className="space-y-2">
            <Label>Email *</Label>
            <Input
              ref={fieldRefs.email}
              type="email"
              value={form.email}
              onChange={(event) => updateField('email', event.target.value)}
              placeholder="customer@email.com"
              className={errorClass(Boolean(errors.email))}
              aria-invalid={Boolean(errors.email)}
            />
            <FieldError message={errors.email} />
          </div>

          <div className="space-y-2">
            <Label>Phone *</Label>
            <Input
              ref={fieldRefs.phone}
              value={form.phone}
              onChange={(event) => updateField('phone', event.target.value)}
              placeholder="Phone number"
              className={errorClass(Boolean(errors.phone))}
              aria-invalid={Boolean(errors.phone)}
            />
            <FieldError message={errors.phone} />
          </div>

          <div className="space-y-2">
            <Label>Guests *</Label>
            <Input
              ref={fieldRefs.guests}
              type="number"
              min="1"
              max="20"
              value={form.guests}
              onChange={(event) => updateField('guests', event.target.value)}
              className={errorClass(Boolean(errors.guests))}
              aria-invalid={Boolean(errors.guests)}
            />
            <FieldError message={errors.guests} />
          </div>
        </div>

        <div className="grid gap-4 md:grid-cols-3">
          <div className="space-y-2">
            <Label>Check-in *</Label>
            <Input
              ref={fieldRefs.check_in_date}
              type="date"
              min={today}
              value={form.check_in_date}
              onChange={(event) => updateField('check_in_date', event.target.value)}
              className={errorClass(Boolean(errors.check_in_date))}
              aria-invalid={Boolean(errors.check_in_date)}
            />
            <FieldError message={errors.check_in_date} />
          </div>

          <div className="space-y-2">
            <Label>Check-out *</Label>
            <Input
              ref={fieldRefs.check_out_date}
              type="date"
              min={form.check_in_date || today}
              value={form.check_out_date}
              onChange={(event) => updateField('check_out_date', event.target.value)}
              className={errorClass(Boolean(errors.check_out_date))}
              aria-invalid={Boolean(errors.check_out_date)}
            />
            <FieldError message={errors.check_out_date} />
          </div>

          <div className="space-y-2">
            <Label>Room *</Label>
            <select
              ref={fieldRefs.room_name}
              value={form.room_name}
              disabled={!canCheckAvailability}
              onChange={(event) => updateField('room_name', event.target.value)}
              className={`h-10 w-full rounded-lg border px-3 text-sm text-text-primary shadow-sm focus:outline-none focus:ring-2 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-500 ${errorClass(Boolean(errors.room_name))}`}
              aria-invalid={Boolean(errors.room_name)}
            >
              {!canCheckAvailability ? <option value="">Select dates first</option> : null}
              {canCheckAvailability && availableRooms.length === 0 ? <option value="">No rooms available</option> : null}
              {canCheckAvailability && availableRooms.map((room) => (
                <option key={room.id || room.room_name} value={room.room_name}>
                  {room.room_name} · {formatMoney(room.price_per_night)} / night
                </option>
              ))}
            </select>
            <FieldError message={errors.room_name} />
          </div>
        </div>

        {canCheckAvailability ? (
          availableRooms.length > 0 ? (
            <div className="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
              {availableRooms.length} room{availableRooms.length === 1 ? '' : 's'} available for {formatDate(form.check_in_date)} to {formatDate(form.check_out_date)}. Same-day turnover is allowed from the checkout date.
            </div>
          ) : (
            <div className="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">
              No rooms are available for the selected dates. Change the dates before saving.
            </div>
          )
        ) : (
          <div className="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-800">
            Select valid check-in and check-out dates first. Rooms will be filtered automatically.
          </div>
        )}

        <div className="grid gap-4 md:grid-cols-2">
          <div className="space-y-2">
            <Label>Payment status</Label>
            <select value={form.payment_status} onChange={(event) => updateField('payment_status', event.target.value)} className="h-10 w-full rounded-lg border border-border bg-white px-3 text-sm text-text-primary shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
              {paymentStatuses.map((status) => (
                <option key={status} value={status}>{humanizePaymentStatus(status)}</option>
              ))}
            </select>
          </div>
          <div className="space-y-2">
            <Label>Payment method</Label>
            <select value={form.payment_method} onChange={(event) => updateField('payment_method', event.target.value)} className="h-10 w-full rounded-lg border border-border bg-white px-3 text-sm text-text-primary shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20">
              {paymentMethodOptions.map((method) => (
                <option key={method} value={method}>{method}</option>
              ))}
            </select>
          </div>
        </div>

        <div className="rounded-xl border border-border bg-slate-50 p-4">
          <div className="grid gap-3 text-sm sm:grid-cols-3">
            <div>
              <p className="font-semibold text-text-secondary">Room rate</p>
              <p className="mt-1 font-bold text-text-primary">{formatMoney(selectedRoom?.price_per_night)}</p>
            </div>
            <div>
              <p className="font-semibold text-text-secondary">Nights</p>
              <p className="mt-1 font-bold text-text-primary">{nights || '-'}</p>
            </div>
            <div>
              <p className="font-semibold text-text-secondary">Estimated total</p>
              <p className="mt-1 font-bold text-text-primary">{formatMoney(estimatedAmount)}</p>
            </div>
          </div>
        </div>

        <div className="space-y-2">
          <Label>Note / special request</Label>
          <textarea value={form.message} onChange={(event) => updateField('message', event.target.value)} rows={4} className="w-full rounded-lg border border-border bg-white px-3 py-2 text-sm text-text-primary shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20" placeholder="Optional note" />
        </div>

        <div className="flex justify-end gap-3 pt-2">
          <Button type="button" variant="outline" onClick={onClose}>Cancel</Button>
          <Button type="submit" disabled={!canCheckAvailability || availableRooms.length === 0}>Save Booking</Button>
        </div>
      </form>
    </Modal>
  )
}

function BookingDetailsModal({ booking, rooms, onClose }) {
  const room = getRoom(booking.room_id, booking.room_name, rooms)

  return (
    <Modal title="Booking details" description={booking.booking_no} onClose={onClose}>
      <div className="grid gap-5 lg:grid-cols-[1.1fr_0.9fr]">
        <div className="space-y-5">
          <section className="rounded-2xl border border-border bg-white p-5">
            <h3 className="mb-4 text-sm font-bold uppercase tracking-wide text-text-secondary">Guest information</h3>
            <div className="space-y-3 text-sm">
              <div className="flex items-center gap-3"><UserRound className="h-4 w-4 text-blue-700" /><span className="font-semibold text-text-primary">{booking.guest_name}</span></div>
              <div className="flex items-center gap-3 text-text-secondary"><Mail className="h-4 w-4 text-blue-700" /><span>{booking.guest_email}</span></div>
              <div className="flex items-center gap-3 text-text-secondary"><Phone className="h-4 w-4 text-blue-700" /><span>{booking.guest_phone}</span></div>
            </div>
          </section>

          <section className="rounded-2xl border border-border bg-white p-5">
            <h3 className="mb-4 text-sm font-bold uppercase tracking-wide text-text-secondary">Stay information</h3>
            <div className="grid gap-4 sm:grid-cols-2">
              <div><p className="text-xs font-semibold uppercase text-text-secondary">Check-in</p><p className="mt-1 font-semibold text-text-primary">{formatDate(booking.check_in)}</p></div>
              <div><p className="text-xs font-semibold uppercase text-text-secondary">Check-out</p><p className="mt-1 font-semibold text-text-primary">{formatDate(booking.check_out)}</p></div>
              <div><p className="text-xs font-semibold uppercase text-text-secondary">Guests</p><p className="mt-1 font-semibold text-text-primary">{booking.guests}</p></div>
              <div><p className="text-xs font-semibold uppercase text-text-secondary">Total nights</p><p className="mt-1 font-semibold text-text-primary">{booking.total_nights}</p></div>
            </div>
          </section>

          <section className="rounded-2xl border border-border bg-white p-5">
            <h3 className="mb-3 text-sm font-bold uppercase tracking-wide text-text-secondary">Special request</h3>
            <p className="text-sm leading-6 text-text-secondary">{booking.special_request || 'No special request added.'}</p>
          </section>
        </div>

        <div className="space-y-5">
          <section className="rounded-2xl border border-border bg-slate-50 p-5">
            <div className="flex items-center gap-3">
              <div className="rounded-xl bg-blue-100 p-3 text-blue-700"><Hotel className="h-6 w-6" /></div>
              <div><p className="text-sm font-bold text-text-primary">{room?.room_name || booking.room_name || 'Unknown room'}</p><p className="text-sm text-text-secondary">{room?.room_type || booking.room_type} · Capacity {room?.capacity || '-'}</p></div>
            </div>
            <div className="mt-5 grid gap-3 text-sm">
              <div className="flex justify-between border-t border-border pt-4"><span className="text-text-secondary">Price per night</span><span className="font-semibold text-text-primary">{formatMoney(room?.price_per_night)}</span></div>
              <div className="flex justify-between"><span className="text-text-secondary">Total amount</span><span className="text-lg font-bold text-text-primary">{formatMoney(booking.total_amount)}</span></div>
            </div>
          </section>

          <section className="rounded-2xl border border-border bg-white p-5">
            <h3 className="mb-4 text-sm font-bold uppercase tracking-wide text-text-secondary">Current status</h3>
            <div className="flex flex-wrap gap-2">
              <Badge variant={bookingStatusVariant[booking.booking_status]}>{humanizeBookingStatus(booking.booking_status)}</Badge>
              <Badge variant={paymentStatusVariant[booking.payment_status]}>{humanizePaymentStatus(booking.payment_status)}</Badge>
            </div>
          </section>

          <section className="rounded-2xl border border-border bg-white p-5">
            <h3 className="mb-4 text-sm font-bold uppercase tracking-wide text-text-secondary">Timeline</h3>
            <div className="space-y-3 text-sm text-text-secondary">
              <p>Created: {formatDate(booking.created_at)}</p>
              <p>Updated: {formatDate(booking.updated_at)}</p>
            </div>
          </section>
        </div>
      </div>
    </Modal>
  )
}

function DeleteBookingModal({ booking, onClose, onConfirm }) {
  return (
    <Modal title="Delete booking" description="This will permanently remove this booking inquiry from the admin list." onClose={onClose} size="max-w-lg">
      <div className="space-y-5">
        <div className="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">
          Are you sure you want to delete <span className="font-bold">{booking.booking_no}</span> for <span className="font-bold">{booking.guest_name}</span>?
        </div>
        <div className="flex justify-end gap-3">
          <Button variant="outline" onClick={onClose}>Keep Booking</Button>
          <Button variant="destructive" onClick={() => onConfirm(booking.id)}>Delete Booking</Button>
        </div>
      </div>
    </Modal>
  )
}

function Pagination({ page, totalPages, totalItems, startItem, endItem, onPageChange }) {
  if (totalItems === 0) return null

  return (
    <div className="flex flex-col gap-3 border-t border-border px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
      <p className="text-sm font-medium text-text-secondary">Showing {startItem}-{endItem} of {totalItems}</p>
      <div className="flex items-center justify-end gap-2">
        <Button type="button" variant="outline" size="sm" disabled={page === 1} onClick={() => onPageChange(page - 1)}>Previous</Button>
        <span className="rounded-lg border border-border bg-white px-3 py-1.5 text-sm font-bold text-text-primary">{page} / {totalPages}</span>
        <Button type="button" variant="outline" size="sm" disabled={page === totalPages} onClick={() => onPageChange(page + 1)}>Next</Button>
      </div>
    </div>
  )
}

export default function Bookings() {
  const location = useLocation()
  const focusRefs = useRef({})
  const focusRequestRef = useRef('')
  const [focusedBookingNo, setFocusedBookingNo] = useState('')
  const [flashBookingNo, setFlashBookingNo] = useState('')
  const [bookings, setBookings] = useState(initialBookings)
  const [rooms, setRooms] = useState(bookingRooms)
  const [isLoading, setIsLoading] = useState(true)
  const [searchTerm, setSearchTerm] = useState('')
  const [bookingStatusFilter, setBookingStatusFilter] = useState('all')
  const [paymentStatusFilter, setPaymentStatusFilter] = useState('all')
  const [dateFrom, setDateFrom] = useState('')
  const [dateTo, setDateTo] = useState('')
  const [currentPage, setCurrentPage] = useState(1)
  const [selectedBooking, setSelectedBooking] = useState(null)
  const [statusBooking, setStatusBooking] = useState(null)
  const [statusFocus, setStatusFocus] = useState('booking')
  const [paymentBooking, setPaymentBooking] = useState(null)
  const [deleteTargetBooking, setDeleteTargetBooking] = useState(null)
  const [isAddBookingOpen, setIsAddBookingOpen] = useState(false)
  const [toast, setToast] = useState(null)

  const showToast = (message, type = 'success') => {
    setToast({ message, type })
    window.setTimeout(() => setToast(null), 2600)
  }

  const loadRooms = async () => {
    try {
      const data = await listRooms()
      const normalizedRooms = data.map(normalizeRoom).filter((room) => room.room_name)
      if (normalizedRooms.length > 0) setRooms(normalizedRooms)
    } catch {
      setRooms(bookingRooms)
    }
  }

  const loadBookings = async () => {
    try {
      setIsLoading(true)
      const data = await fetchBookings()
      setBookings(data)
    } catch (error) {
      showToast(error.message || 'Unable to load bookings.', 'error')
    } finally {
      setIsLoading(false)
    }
  }

  useEffect(() => {
    loadRooms()
    loadBookings()
  }, [])

  const filteredBookings = useMemo(() => {
    const query = searchTerm.trim().toLowerCase()

    return bookings.filter((booking) => {
      const searchableText = [booking.booking_no, booking.guest_name, booking.guest_phone, booking.guest_email, booking.room_name, booking.room_type].join(' ').toLowerCase()
      const matchesSearch = !query || searchableText.includes(query)
      const matchesBookingStatus = bookingStatusFilter === 'all' || booking.booking_status === bookingStatusFilter
      const matchesPaymentStatus = paymentStatusFilter === 'all' || booking.payment_status === paymentStatusFilter
      const matchesDateFrom = !dateFrom || booking.check_in >= dateFrom
      const matchesDateTo = !dateTo || booking.check_in <= dateTo

      return matchesSearch && matchesBookingStatus && matchesPaymentStatus && matchesDateFrom && matchesDateTo
    })
  }, [bookings, searchTerm, bookingStatusFilter, paymentStatusFilter, dateFrom, dateTo])

  useEffect(() => {
    if (focusRequestRef.current) return
    setCurrentPage(1)
  }, [searchTerm, bookingStatusFilter, paymentStatusFilter, dateFrom, dateTo])
  const totalPages = Math.max(1, Math.ceil(filteredBookings.length / BOOKINGS_PER_PAGE))
  const safeCurrentPage = Math.min(currentPage, totalPages)
  const startIndex = (safeCurrentPage - 1) * BOOKINGS_PER_PAGE
  const paginatedBookings = filteredBookings.slice(startIndex, startIndex + BOOKINGS_PER_PAGE)
  const startItem = filteredBookings.length === 0 ? 0 : startIndex + 1
  const endItem = Math.min(startIndex + BOOKINGS_PER_PAGE, filteredBookings.length)

  useEffect(() => {
    const queryFocus = new URLSearchParams(location.search).get('focus')
    const stateFocus = location.state?.notificationFocus?.referenceId || ''
    const focusValue = queryFocus || stateFocus
    if (!focusValue) return

    focusRequestRef.current = String(focusValue)
    setSearchTerm('')
    setBookingStatusFilter('all')
    setPaymentStatusFilter('all')
    setDateFrom('')
    setDateTo('')
    setFocusedBookingNo(String(focusValue))
  }, [location.search, location.state])

  useEffect(() => {
    if (!focusedBookingNo || isLoading) return

    const focusedIndex = filteredBookings.findIndex((booking) => {
      const values = [booking.booking_no, booking.bookingNo, booking.id]
      return values.some((value) => String(value || '') === String(focusedBookingNo))
    })

    if (focusedIndex < 0) return

    setCurrentPage(Math.floor(focusedIndex / BOOKINGS_PER_PAGE) + 1)
  }, [focusedBookingNo, filteredBookings, isLoading])

  useEffect(() => {
    if (!focusedBookingNo || isLoading) return

    const element = focusRefs.current[focusedBookingNo]
    if (!element) return

    const timer = window.setTimeout(() => {
      element.scrollIntoView({ behavior: 'smooth', block: 'center' })
      setFlashBookingNo(String(focusedBookingNo))
      window.setTimeout(() => {
        setFlashBookingNo('')
        setFocusedBookingNo('')
        focusRequestRef.current = ''
      }, 1400)
    }, 300)

    return () => window.clearTimeout(timer)
  }, [focusedBookingNo, paginatedBookings, isLoading, currentPage])



  const summary = useMemo(() => {
    return bookings.reduce(
      (acc, booking) => {
        acc.total += 1
        acc[booking.booking_status] = (acc[booking.booking_status] || 0) + 1
        if (booking.payment_status === 'paid') acc.revenue += Number(booking.total_amount || 0)
        return acc
      },
      { total: 0, pending: 0, confirmed: 0, cancelled: 0, revenue: 0 }
    )
  }, [bookings])

  const clearFilters = () => {
    setSearchTerm('')
    setBookingStatusFilter('all')
    setPaymentStatusFilter('all')
    setDateFrom('')
    setDateTo('')
    setCurrentPage(1)
  }

  const handleCombinedStatusSave = async (bookingId, updates) => {
    try {
      const updatedBooking = await updateBookingAndPaymentStatus(bookingId, updates)
      setBookings((current) => current.map((booking) => (booking.id === bookingId ? { ...booking, ...updatedBooking } : booking)))
      setStatusBooking(null)
      setPaymentBooking(null)
      showToast('Statuses updated successfully. Email handled by the server.')
    } catch (error) {
      showToast(error.message || 'Unable to update statuses.', 'error')
    }
  }

  const handleStatusSave = async (bookingId, bookingStatus) => {
    return handleCombinedStatusSave(bookingId, { booking_status: bookingStatus, payment_status: statusBooking?.payment_status || 'pending', payment_method: statusBooking?.payment_method || 'Manual', send_email: true })
  }

  const handlePaymentSave = async (bookingId, paymentStatus, paymentMethod) => {
    return handleCombinedStatusSave(bookingId, { booking_status: paymentBooking?.booking_status || 'pending', payment_status: paymentStatus, payment_method: paymentMethod, send_email: true })
  }

  const handleAddBooking = async (formData) => {
    try {
      const newBooking = await createManualBooking(formData)
      setBookings((current) => [newBooking, ...current])
      setIsAddBookingOpen(false)
      setCurrentPage(1)
      showToast('Manual booking added successfully.')
    } catch (error) {
      showToast(error.message || 'Unable to add manual booking.', 'error')
    }
  }

  const handleDeleteBooking = async (bookingId) => {
    try {
      await deleteBooking(bookingId)
      setBookings((current) => current.filter((booking) => booking.id !== bookingId))
      setDeleteTargetBooking(null)
      showToast('Booking deleted successfully.')
    } catch (error) {
      showToast(error.message || 'Unable to delete booking.', 'error')
    }
  }

  const handleDownload = (format) => {
    const rows = buildBookingExportRows(filteredBookings)

    if (rows.length === 0) {
      showToast('No booking data available for download.', 'error')
      return
    }

    const payload = {
      fileName: 'jebal-guest-house-bookings',
      title: 'Jebal Guest House Booking Report',
      rows,
    }

    if (format === 'csv') exportCsv(payload)
    if (format === 'excel') exportExcel(payload)
    if (format === 'pdf') exportPdf(payload)
  }

  return (
    <div className="w-full min-w-0 max-w-full space-y-6 overflow-x-hidden">
      <Toast toast={toast} onClose={() => setToast(null)} />

      <PageHeader title="Bookings" description="View, filter, and manage Jebal Guest House reservations.">
        <div className="flex max-w-full flex-wrap gap-2 md:flex-nowrap md:items-center">
          <Button variant="outline" onClick={clearFilters}><Filter className="h-4 w-4" />Clear Filters</Button>
          <Dropdown
            trigger={
              <Button type="button" variant="outline">
                <Download className="h-4 w-4" />
                Download
              </Button>
            }
          >
            {(close) => (
              <>
                <DropdownItem onClick={() => { close(); handleDownload('excel') }}>Excel</DropdownItem>
                <DropdownItem onClick={() => { close(); handleDownload('csv') }}>CSV</DropdownItem>
                <DropdownItem onClick={() => { close(); handleDownload('pdf') }}>PDF</DropdownItem>
              </>
            )}
          </Dropdown>
          <Button onClick={() => setIsAddBookingOpen(true)}><Plus className="h-4 w-4" />Add Booking</Button>
        </div>
      </PageHeader>

      <div className="grid min-w-0 gap-4 md:grid-cols-2 xl:grid-cols-5">
        <SummaryCard title="Total bookings" value={summary.total} icon={CalendarDays} description="All reservations" />
        <SummaryCard title="Pending" value={summary.pending} icon={Moon} description="Need attention" />
        <SummaryCard title="Confirmed" value={summary.confirmed} icon={CheckCircle2} description="Upcoming stays" />
        <SummaryCard title="Cancelled" value={summary.cancelled} icon={XCircle} description="Cancelled reservations" />
        <SummaryCard title="Paid revenue" value={formatMoney(summary.revenue)} icon={CreditCard} description="Paid booking value" />
      </div>

      <div className="w-full min-w-0 max-w-full overflow-hidden">
        <Card className="w-full min-w-0 max-w-full overflow-hidden">
        <CardContent className="min-w-0 max-w-full space-y-4 p-5">
          <div className="grid min-w-0 grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-4 md:gap-4 lg:grid-cols-[1.4fr_1fr_1fr_1fr_1fr]">
            <div className="relative sm:col-span-2 md:col-span-4 lg:col-span-1">
              <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
              <Input value={searchTerm} onChange={(event) => setSearchTerm(event.target.value)} placeholder="Search booking, guest, email, phone..." className="pl-9" />
            </div>

            <select value={bookingStatusFilter} onChange={(event) => setBookingStatusFilter(event.target.value)} className="h-10 min-w-0 rounded-lg border border-border bg-white px-3 text-sm text-text-primary shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 sm:col-span-2 md:col-span-1">
              <option value="all">All booking statuses</option>
              {bookingStatuses.map((status) => <option key={status} value={status}>{humanizeBookingStatus(status)}</option>)}
            </select>

            <select value={paymentStatusFilter} onChange={(event) => setPaymentStatusFilter(event.target.value)} className="h-10 min-w-0 rounded-lg border border-border bg-white px-3 text-sm text-text-primary shadow-sm focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 sm:col-span-2 md:col-span-1">
              <option value="all">All payment statuses</option>
              {paymentStatuses.map((status) => <option key={status} value={status}>{humanizePaymentStatus(status)}</option>)}
            </select>

            <div className="grid min-w-0 grid-cols-2 gap-3 sm:col-span-2 md:contents">
              <Input type="date" value={dateFrom} onChange={(event) => setDateFrom(event.target.value)} className="min-w-0" />
              <Input type="date" value={dateTo} onChange={(event) => setDateTo(event.target.value)} className="min-w-0" />
            </div>
          </div>
        </CardContent>
      </Card>
      </div>

      <div className="w-full min-w-0 max-w-full overflow-hidden">
        <Card className="w-full min-w-0 max-w-full overflow-hidden">
        <CardContent className="min-w-0 max-w-full p-0">
          <div className="w-full min-w-0 max-w-full md:overflow-x-auto md:overscroll-x-contain" style={{ WebkitOverflowScrolling: 'touch', contain: 'inline-size' }}>
            <div className="hidden min-w-[760px] grid-cols-[1fr_1.15fr_1.25fr_0.8fr_0.9fr_0.95fr_0.8fr] items-center gap-4 border-b border-border bg-slate-50 px-5 py-3 text-xs font-bold uppercase tracking-wide text-text-secondary md:grid xl:min-w-[900px]">
              <span>Booking</span>
              <span>Guest</span>
              <span>Stay</span>
              <span>Amount</span>
              <span>Booking Status</span>
              <span>Payment Status</span>
              <span className="text-right">Actions</span>
            </div>

          {isLoading ? (
            <div className="flex flex-col items-center justify-center px-6 py-16 text-center">
              <div className="rounded-2xl bg-blue-50 p-4 text-blue-700"><CalendarDays className="h-8 w-8" /></div>
              <h3 className="mt-4 text-lg font-bold text-text-primary">Loading bookings</h3>
              <p className="mt-2 max-w-md text-sm text-text-secondary">Fetching booking inquiries from the server.</p>
            </div>
          ) : filteredBookings.length === 0 ? (
            <div className="flex flex-col items-center justify-center px-6 py-16 text-center">
              <div className="rounded-2xl bg-blue-50 p-4 text-blue-700"><CalendarDays className="h-8 w-8" /></div>
              <h3 className="mt-4 text-lg font-bold text-text-primary">No bookings found</h3>
              <p className="mt-2 max-w-md text-sm text-text-secondary">No booking matches your current search or filter settings.</p>
              <Button className="mt-5" variant="outline" onClick={clearFilters}>Clear Filters</Button>
            </div>
          ) : (
            <>
              <div className="space-y-3 p-4 md:hidden">
                {paginatedBookings.map((booking) => {
                  const shouldFlashBooking = Boolean(
                    flashBookingNo &&
                    [booking.booking_no, booking.bookingNo, booking.id].some(
                      (value) => value != null && String(value) === String(flashBookingNo)
                    )
                  )

                  return (
                    <MobileBookingCard
                      key={booking.id}
                      booking={booking}
                      shouldFlashBooking={shouldFlashBooking}
                      setRef={(element) => {
                        if (element) {
                          if (booking.booking_no) focusRefs.current[booking.booking_no] = element
                          if (booking.bookingNo) focusRefs.current[booking.bookingNo] = element
                          if (booking.id) focusRefs.current[booking.id] = element
                        }
                      }}
                      onView={() => setSelectedBooking(booking)}
                      onUpdateStatus={() => { setStatusFocus('booking'); setStatusBooking(booking) }}
                      onCancel={() => setDeleteTargetBooking(booking)}
                    />
                  )
                })}
              </div>

              <div className="hidden divide-y divide-border md:block">
                {paginatedBookings.map((booking) => {
                  const shouldFlashBooking = Boolean(
                    flashBookingNo &&
                    [booking.booking_no, booking.bookingNo, booking.id].some(
                      (value) => value != null && String(value) === String(flashBookingNo)
                    )
                  )

                  return (
                    <div
                      key={booking.id}
                      ref={(element) => {
                        if (element) {
                          if (booking.booking_no) focusRefs.current[booking.booking_no] = element
                          if (booking.bookingNo) focusRefs.current[booking.bookingNo] = element
                          if (booking.id) focusRefs.current[booking.id] = element
                        }
                      }}
                      className={`grid min-w-[760px] grid-cols-[1fr_1.15fr_1.25fr_0.8fr_0.9fr_0.95fr_0.8fr] items-center gap-4 px-5 py-4 transition hover:bg-blue-50/40 xl:min-w-[900px] ${shouldFlashBooking ? 'dashboard-focus-flash rounded-xl' : ''}`}
                    >
                      <div>
                        <p className="sr-only">Booking</p>
                        <p className="font-bold text-text-primary">{booking.booking_no}</p>
                        <p className="mt-1 line-clamp-1 text-sm font-semibold text-text-primary">{booking.room_name}</p>
                        <p className="mt-1 text-xs text-text-secondary">Created {formatDate(booking.created_at)}</p>
                      </div>

                      <div>
                        <p className="sr-only">Guest</p>
                        <p className="line-clamp-1 font-semibold text-text-primary">{booking.guest_name}</p>
                        <p className="mt-1 text-xs text-text-secondary">{booking.guest_phone}</p>
                        {booking.guest_email ? <p className="mt-1 line-clamp-1 text-xs text-text-secondary">{booking.guest_email}</p> : null}
                      </div>

                      <div>
                        <p className="sr-only">Stay</p>
                        <p className="font-semibold text-text-primary">{formatDate(booking.check_in, shortDateFormatter)} - {formatDate(booking.check_out, shortDateFormatter)}</p>
                        <p className="mt-1 text-xs text-text-secondary">{booking.total_nights} night{booking.total_nights === 1 ? '' : 's'} · {booking.guests} guest{booking.guests === 1 ? '' : 's'}</p>
                      </div>

                      <div>
                        <p className="sr-only">Amount</p>
                        <p className="font-bold text-text-primary">{formatMoney(booking.total_amount)}</p>
                      </div>

                      <div>
                        <p className="sr-only">Booking Status</p>
                        <Badge variant={bookingStatusVariant[booking.booking_status] || 'warning'}>{humanizeBookingStatus(booking.booking_status)}</Badge>
                      </div>

                      <div>
                        <p className="sr-only">Payment Status</p>
                        <Badge variant={paymentStatusVariant[booking.payment_status] || 'warning'}>{humanizePaymentStatus(booking.payment_status)}</Badge>
                      </div>

                      <ActionsDropdown booking={booking} onView={() => setSelectedBooking(booking)} onUpdateStatus={() => { setStatusFocus('booking'); setStatusBooking(booking) }} onCancel={() => setDeleteTargetBooking(booking)} />
                    </div>
                  )
                })}
              </div>
            </>
          )}
          </div>
          <Pagination page={safeCurrentPage} totalPages={totalPages} totalItems={filteredBookings.length} startItem={startItem} endItem={endItem} onPageChange={setCurrentPage} />
        </CardContent>
        </Card>
      </div>

      {isAddBookingOpen ? <AddBookingModal rooms={rooms} bookings={bookings} onClose={() => setIsAddBookingOpen(false)} onSave={handleAddBooking} /> : null}
      {selectedBooking ? <BookingDetailsModal booking={selectedBooking} rooms={rooms} onClose={() => setSelectedBooking(null)} /> : null}
      {statusBooking ? <CombinedStatusModal booking={statusBooking} focus={statusFocus} onClose={() => setStatusBooking(null)} onSave={handleCombinedStatusSave} /> : null}
      {paymentBooking ? <PaymentStatusModal booking={paymentBooking} onClose={() => setPaymentBooking(null)} onSave={handlePaymentSave} /> : null}
      {deleteTargetBooking ? <DeleteBookingModal booking={deleteTargetBooking} onClose={() => setDeleteTargetBooking(null)} onConfirm={handleDeleteBooking} /> : null}
    </div>
  )
}
