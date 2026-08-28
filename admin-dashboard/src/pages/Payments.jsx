import { useEffect, useMemo, useRef, useState } from 'react'
import { useLocation } from 'react-router-dom'
import {
  Banknote,
  CreditCard,
  Download,
  Edit3,
  Filter,
  Eye,
  MoreVertical,
  Search,
  TrendingUp,
  WalletCards,
  X,
} from 'lucide-react'
import { PageHeader } from '@/components/ui/page-header'
import { Button } from '@/components/ui/button'
import { Dropdown, DropdownItem } from '@/components/ui/dropdown'
import { Badge } from '@/components/ui/badge'
import { Card, CardContent } from '@/components/ui/card'
import { Input, Label } from '@/components/ui/input'
import { fetchPayments, updateCombinedStatusByBooking } from '@/services/paymentsApi'
import { exportCsv, exportExcel, exportPdf } from '@/utils/exportData'

const PAGE_SIZE = 6

const currencyFormatter = new Intl.NumberFormat('en-US', {
  style: 'currency',
  currency: 'LKR',
  maximumFractionDigits: 0,
})

const paymentStatuses = ['pending', 'paid', 'failed', 'cancelled', 'refunded', 'no_pay']
const editablePaymentStatuses = ['pending', 'paid', 'cancelled', 'refunded', 'no_pay']
const bookingStatuses = ['pending', 'confirmed', 'cancelled']
const defaultPaymentMethods = ['PayHere', 'Cash', 'Bank Transfer', 'Card', 'No Pay', 'Other']

const statusVariant = {
  pending: 'warning',
  paid: 'success',
  failed: 'destructive',
  cancelled: 'secondary',
  refunded: 'purple',
}

const statusLabel = {
  pending: 'Payment Pending',
  paid: 'Paid',
  failed: 'Failed',
  cancelled: 'Cancelled',
  refunded: 'Refunded',
  no_pay: 'No Pay',
}

function formatCurrency(value) {
  return currencyFormatter.format(Number(value || 0))
}

function formatDate(value) {
  if (!value) return 'Not paid yet'

  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return 'Not paid yet'

  return new Intl.DateTimeFormat('en-US', {
    month: 'short',
    day: '2-digit',
    year: 'numeric',
  }).format(date)
}

function isInDateRange(value, from, to) {
  if (!from && !to) return true
  if (!value) return false

  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return false
  date.setHours(0, 0, 0, 0)

  if (from) {
    const start = new Date(from)
    start.setHours(0, 0, 0, 0)
    if (date < start) return false
  }

  if (to) {
    const end = new Date(to)
    end.setHours(23, 59, 59, 999)
    if (date > end) return false
  }

  return true
}

function safeText(value) {
  return String(value || '').toLowerCase()
}

function StatCard({ title, value, description, icon: Icon }) {
  return (
    <Card>
      <CardContent className="p-5">
        <div className="flex items-start justify-between gap-4">
          <div>
            <p className="text-sm font-medium text-text-secondary">{title}</p>
            <p className="mt-2 text-2xl font-bold tracking-tight text-text-primary">{value}</p>
            {description ? <p className="mt-1 text-xs text-text-secondary">{description}</p> : null}
          </div>
          <div className="rounded-xl bg-blue-50 p-3 text-blue-700">
            <Icon className="h-5 w-5" />
          </div>
        </div>
      </CardContent>
    </Card>
  )
}


function buildPaymentExportRows(payments) {
  return payments.map((payment) => ({
    'Payment ID': payment.payment_id || `PAY-${String(payment.id || '').padStart(4, '0')}`,
    'Transaction ID': payment.transaction_id || '-',
    'Booking No': payment.booking_no || '-',
    Amount: Number(payment.amount || 0),
    Method: payment.payment_method || '-',
    Status: statusLabel[payment.payment_status] || payment.payment_status || '-',
    'Paid Date': payment.paid_at || '-',
    'Created Date': payment.created_at || '-',
  }))
}

function PaymentStatusBadge({ status }) {
  return <Badge variant={statusVariant[status] || 'secondary'}>{statusLabel[status] || status || '-'}</Badge>
}

function Toast({ message, type, onClose }) {
  if (!message) return null

  const tone = type === 'error'
    ? 'border-red-200 bg-red-50 text-red-800'
    : type === 'warning'
      ? 'border-amber-200 bg-amber-50 text-amber-800'
      : 'border-blue-100 bg-white text-blue-900'

  return (
    <div className={`fixed right-4 top-4 z-50 flex items-center gap-3 rounded-xl border px-4 py-3 text-sm font-medium shadow-lg shadow-slate-200 ${tone}`}>
      <span>{message}</span>
      <button type="button" onClick={onClose} className="rounded p-1 hover:bg-slate-100" aria-label="Close toast">
        <X className="h-4 w-4" />
      </button>
    </div>
  )
}

function ActionsDropdown({ payment, onView, onEdit }) {
  const invoiceDownloadUrl = payment.invoice_number ? `/api/invoices/download.php?id=${payment.booking_id}` : ''

  return (
    <Dropdown className="flex justify-end" contentClassName="w-52 py-1" trigger={<Button type="button" variant="outline" size="sm"><MoreVertical className="h-4 w-4" />Actions</Button>}>
      {(close) => (
        <>
          <button
            type="button"
            onClick={() => { onView(); close() }}
            className="flex w-full items-center gap-2 px-3 py-2 text-left text-sm font-medium text-text-primary transition hover:bg-slate-50"
          >
            <Eye className="h-4 w-4 text-blue-700" />
            View Details
          </button>
          <button
            type="button"
            onClick={() => { onEdit(); close() }}
            className="flex w-full items-center gap-2 px-3 py-2 text-left text-sm font-medium text-text-primary transition hover:bg-slate-50"
          >
            <Edit3 className="h-4 w-4 text-emerald-600" />
            Edit Payment
          </button>
          {invoiceDownloadUrl ? (
            <button
              type="button"
              onClick={() => { window.open(invoiceDownloadUrl, '_blank', 'noopener,noreferrer'); close() }}
              className="flex w-full items-center gap-2 px-3 py-2 text-left text-sm font-medium text-text-primary transition hover:bg-slate-50"
            >
              <Download className="h-4 w-4 text-slate-700" />
              Download Invoice
            </button>
          ) : null}
        </>
      )}
    </Dropdown>
  )
}

function DetailCard({ label, value }) {
  return (
    <div className="rounded-xl border border-border bg-slate-50 p-3">
      <p className="text-xs font-semibold uppercase tracking-wide text-text-secondary">{label}</p>
      <p className="mt-1 break-words text-sm font-semibold text-text-primary">{value || '-'}</p>
    </div>
  )
}

function DetailSection({ title, children }) {
  return (
    <section className="space-y-3">
      <h3 className="text-sm font-bold uppercase tracking-wide text-text-secondary">{title}</h3>
      <div className="grid gap-3 sm:grid-cols-2">{children}</div>
    </section>
  )
}

function formatStayDate(value) {
  if (!value) return '-'

  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return '-'

  return new Intl.DateTimeFormat('en-US', {
    month: 'short',
    day: '2-digit',
    year: 'numeric',
  }).format(date)
}

function PaymentDetailsModal({ payment, onClose }) {
  if (!payment) return null

  const invoiceDownloadUrl = payment.invoice_number ? `/api/invoices/download.php?id=${payment.booking_id}` : ''
  const guestCount = Number(payment.guests || payment.total_guests || payment.adults || 0)
  const guestText = guestCount > 0 ? `${guestCount} guest${guestCount === 1 ? '' : 's'}` : '-'
  const nights = Number(payment.total_nights || payment.nights || 0)
  const nightsText = nights > 0 ? `${nights} night${nights === 1 ? '' : 's'}` : '-'
  const groupRooms = Array.isArray(payment.group_rooms) ? payment.group_rooms : []
  const isMultiRoom = Number(payment.booking_group_id || 0) > 0 || groupRooms.length > 1

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 px-4 py-6 backdrop-blur-sm" onMouseDown={onClose}>
      <div className="max-h-[90vh] w-full max-w-3xl overflow-y-auto rounded-2xl border border-border bg-white shadow-2xl" onMouseDown={(event) => event.stopPropagation()}>
        <div className="sticky top-0 z-10 flex items-center justify-between border-b border-border bg-white px-6 py-4">
          <div>
            <h2 className="text-lg font-semibold text-text-primary">Payment Details</h2>
            <p className="text-sm text-text-secondary">{payment.transaction_id}</p>
          </div>
          <button
            type="button"
            onClick={onClose}
            className="rounded-lg p-2 text-slate-500 transition hover:bg-slate-100 hover:text-slate-900"
            aria-label="Close details"
          >
            <X className="h-5 w-5" />
          </button>
        </div>

        <div className="space-y-6 p-6">
          <div className="rounded-2xl border border-blue-100 bg-blue-50 p-5">
            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
              <div>
                <p className="text-sm font-medium text-blue-700">Payment Amount</p>
                <p className="mt-1 text-3xl font-bold text-blue-950">{formatCurrency(payment.amount)}</p>
              </div>
              <PaymentStatusBadge status={payment.payment_status} />
            </div>
          </div>

          <DetailSection title="Booking Details">
            <DetailCard label="Booking ID" value={payment.booking_no} />
            <DetailCard label="Booking Type" value={isMultiRoom ? `${payment.total_rooms || groupRooms.length} rooms` : 'Single room'} />
            <DetailCard label="Booking Status" value={payment.booking_status || payment.status || '-'} />
            {!isMultiRoom ? <DetailCard label="Room Name" value={payment.room_name} /> : null}
            <DetailCard label="Check-in" value={formatStayDate(payment.check_in || payment.checkin_date)} />
            <DetailCard label="Check-out" value={formatStayDate(payment.check_out || payment.checkout_date)} />
            <DetailCard label="Nights" value={nightsText} />
            <DetailCard label="Guests" value={guestText} />
          </DetailSection>

          {isMultiRoom ? (
            <DetailSection title="Rooms in this booking">
              {groupRooms.map((room) => (
                <DetailCard
                  key={room.booking_id || room.room_name}
                  label={room.is_primary ? 'Primary room' : 'Room'}
                  value={`${room.room_name} · ${room.guests} guest${room.guests === 1 ? '' : 's'} · ${formatCurrency(room.amount)}`}
                />
              ))}
            </DetailSection>
          ) : null}

          <DetailSection title="Guest Details">
            <DetailCard label="Guest Name" value={payment.guest_name} />
            <DetailCard label="Phone" value={payment.guest_phone || payment.phone} />
            <DetailCard label="Email" value={payment.guest_email || payment.email} />
            <DetailCard label="Special Request" value={payment.special_request || payment.notes || '-'} />
          </DetailSection>

          <DetailSection title="Payment Details">
            <DetailCard label="Payment ID" value={payment.payment_id || `PAY-${String(payment.id).padStart(4, '0')}`} />
            <DetailCard label="Transaction ID" value={payment.transaction_id} />
            <DetailCard label="Amount" value={formatCurrency(payment.amount)} />
            <DetailCard label="Payment Method" value={payment.payment_method} />
            <DetailCard label="Payment Gateway" value={payment.payment_gateway} />
            <DetailCard label="Paid Date" value={formatDate(payment.paid_at)} />
            <DetailCard label="Created Date" value={formatDate(payment.created_at)} />
            <DetailCard label="Updated Date" value={formatDate(payment.updated_at)} />
          </DetailSection>

          {invoiceDownloadUrl ? (
            <div className="flex justify-end">
              <Button type="button" onClick={() => window.open(invoiceDownloadUrl, '_blank', 'noopener,noreferrer')}>
                <Download className="h-4 w-4" />
                Download Invoice
              </Button>
            </div>
          ) : null}
        </div>
      </div>
    </div>
  )
}

function EditPaymentModal({ payment, methodOptions, onClose, onSave, saving }) {
  const normalizedBookingStatus = String(payment.booking_status || 'pending').toLowerCase()
  const [bookingStatus, setBookingStatus] = useState(['pending', 'confirmed', 'cancelled'].includes(normalizedBookingStatus) ? normalizedBookingStatus : 'pending')
  const [paymentStatus, setPaymentStatus] = useState(payment.payment_status || 'pending')
  const [paymentMethod, setPaymentMethod] = useState(payment.payment_method || 'PayHere')
  const [reference, setReference] = useState(payment.payment_id || payment.transaction_id || '')
  const [remarks, setRemarks] = useState('')
  const [sendEmail, setSendEmail] = useState(true)
  const [errors, setErrors] = useState({})

  const validate = () => {
    const nextErrors = {}
    if (!paymentStatus) nextErrors.paymentStatus = 'Please select payment status.'
    if (!paymentMethod) nextErrors.paymentMethod = 'Please select payment method.'
    setErrors(nextErrors)
    return Object.keys(nextErrors).length === 0
  }

  const handleSubmit = (event) => {
    event.preventDefault()
    if (!validate()) return

    onSave(payment, {
      booking_status: bookingStatus,
      payment_status: paymentStatus,
      payment_method: paymentMethod,
      transaction_reference: reference.trim(),
      remarks: remarks.trim(),
      send_email: sendEmail,
    })
  }

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 px-4 py-6 backdrop-blur-sm" onMouseDown={onClose}>
      <div className="w-full max-w-xl overflow-hidden rounded-2xl border border-border bg-white shadow-2xl" onMouseDown={(event) => event.stopPropagation()}>
        <div className="flex items-start justify-between border-b border-border px-5 py-3">
          <div>
            <h2 className="text-lg font-bold text-text-primary">Edit Payment</h2>
            <p className="mt-1 text-sm text-text-secondary">{payment.booking_no}</p>
          </div>
          <button
            type="button"
            onClick={onClose}
            className="rounded-lg p-2 text-slate-500 transition hover:bg-slate-100 hover:text-slate-900"
            aria-label="Close edit payment"
          >
            <X className="h-5 w-5" />
          </button>
        </div>

        <form onSubmit={handleSubmit} className="space-y-4 p-5">
          <div className="rounded-xl border border-border bg-slate-50 p-3">
            <p className="text-sm font-semibold text-text-primary">{formatCurrency(payment.amount)}</p>
            <p className="mt-1 text-sm text-text-secondary">Current status: {statusLabel[payment.payment_status] || payment.payment_status}</p>
          </div>

          <div className="grid gap-3 sm:grid-cols-2">
            <div className="space-y-1.5">
              <Label htmlFor="edit-booking-status">Booking Status *</Label>
              <select
                id="edit-booking-status"
                value={bookingStatus}
                onChange={(event) => setBookingStatus(event.target.value)}
                className="h-10 w-full rounded-lg border border-border bg-white px-3 text-sm font-medium text-text-primary shadow-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20"
              >
                {bookingStatuses.map((status) => (
                  <option key={status} value={status}>{status.charAt(0).toUpperCase() + status.slice(1)}</option>
                ))}
              </select>
            </div>

            <div className="space-y-1.5">
              <Label htmlFor="edit-payment-status">Payment Status *</Label>
              <select
                id="edit-payment-status"
                value={paymentStatus}
                onChange={(event) => setPaymentStatus(event.target.value)}
                className={`h-10 w-full rounded-lg border bg-white px-3 text-sm font-medium text-text-primary shadow-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 ${errors.paymentStatus ? 'border-red-500' : 'border-border'}`}
              >
                {editablePaymentStatuses.map((status) => (
                  <option key={status} value={status}>{statusLabel[status]}</option>
                ))}
              </select>
              {errors.paymentStatus ? <p className="text-xs font-medium text-red-600">{errors.paymentStatus}</p> : null}
            </div>

            <div className="space-y-1.5">
              <Label htmlFor="edit-payment-method">Payment Method *</Label>
              <select
                id="edit-payment-method"
                value={paymentMethod}
                onChange={(event) => setPaymentMethod(event.target.value)}
                className={`h-10 w-full rounded-lg border bg-white px-3 text-sm font-medium text-text-primary shadow-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 ${errors.paymentMethod ? 'border-red-500' : 'border-border'}`}
              >
                {methodOptions.map((method) => (
                  <option key={method} value={method}>{method}</option>
                ))}
              </select>
              {errors.paymentMethod ? <p className="text-xs font-medium text-red-600">{errors.paymentMethod}</p> : null}
            </div>
          </div>

          <div className="space-y-1.5">
            <Label htmlFor="payment-reference">Reference / Transaction No.</Label>
            <Input
              id="payment-reference"
              value={reference}
              onChange={(event) => setReference(event.target.value)}
              placeholder="PayHere ID, bank slip no, cash receipt no..."
            />
          </div>

          <div className="space-y-1.5">
            <Label htmlFor="payment-remarks">Remarks</Label>
            <textarea
              id="payment-remarks"
              value={remarks}
              onChange={(event) => setRemarks(event.target.value)}
              rows={2}
              placeholder="Optional internal note"
              className="w-full rounded-lg border border-border bg-white px-3 py-2 text-sm text-text-primary shadow-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20"
            />
          </div>

          <label className="flex items-start gap-3 rounded-xl border border-blue-100 bg-blue-50 p-3 text-xs font-medium leading-5 text-blue-900">
            <input type="checkbox" checked={sendEmail} onChange={(event) => setSendEmail(event.target.checked)} className="mt-1" />
            <span>Send one customer email for the final status update. If booking and payment both change, only one combined email will be sent.</span>
          </label>

          <div className="flex justify-end gap-3 pt-1">
            <Button type="button" variant="outline" onClick={onClose} disabled={saving}>Cancel</Button>
            <Button type="submit" disabled={saving}>{saving ? 'Saving...' : 'Save Changes'}</Button>
          </div>
        </form>
      </div>
    </div>
  )
}

function Pagination({ page, totalPages, totalItems, onPageChange }) {
  if (totalItems === 0) return null

  const start = (page - 1) * PAGE_SIZE + 1
  const end = Math.min(page * PAGE_SIZE, totalItems)

  return (
    <div className="flex flex-col gap-3 border-t border-border px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
      <p className="text-sm font-medium text-text-secondary">Showing {start}-{end} of {totalItems}</p>
      <div className="flex items-center justify-end gap-2">
        <Button type="button" variant="outline" size="sm" disabled={page === 1} onClick={() => onPageChange(page - 1)}>Previous</Button>
        <span className="rounded-lg border border-border bg-white px-3 py-1.5 text-sm font-bold text-text-primary">{page} / {totalPages}</span>
        <Button type="button" variant="outline" size="sm" disabled={page === totalPages} onClick={() => onPageChange(page + 1)}>Next</Button>
      </div>
    </div>
  )
}

export default function Payments() {
  const location = useLocation()
  const focusRefs = useRef({})
  const [focusedTransaction, setFocusedTransaction] = useState('')
  const [flashTransaction, setFlashTransaction] = useState('')
  const [payments, setPayments] = useState([])
  const [loading, setLoading] = useState(true)
  const [savingPayment, setSavingPayment] = useState(false)
  const [error, setError] = useState('')
  const [searchTerm, setSearchTerm] = useState('')
  const [statusFilter, setStatusFilter] = useState('all')
  const [methodFilter, setMethodFilter] = useState('all')
  const [dateFrom, setDateFrom] = useState('')
  const [dateTo, setDateTo] = useState('')
  const [currentPage, setCurrentPage] = useState(1)
  const [selectedPayment, setSelectedPayment] = useState(null)
  const [editingPayment, setEditingPayment] = useState(null)
  const [toast, setToast] = useState(null)

  const showToast = (message, type = 'success') => {
    setToast({ message, type })
    window.setTimeout(() => setToast(null), 2400)
  }

  const loadPayments = async () => {
    try {
      setLoading(true)
      setError('')
      const data = await fetchPayments()
      setPayments(data)
    } catch (err) {
      setError(err.message || 'Unable to load payments.')
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    loadPayments()
  }, [])

  useEffect(() => {
    setCurrentPage(1)
  }, [searchTerm, statusFilter, methodFilter, dateFrom, dateTo])
  const methodOptions = useMemo(() => {
    const methods = payments.map((payment) => payment.payment_method).filter(Boolean)
    return Array.from(new Set([...defaultPaymentMethods, ...methods]))
  }, [payments])

  const filteredPayments = useMemo(() => {
    const query = searchTerm.trim().toLowerCase()

    return payments.filter((payment) => {
      const matchesSearch =
        !query ||
        safeText(payment.transaction_id).includes(query) ||
        safeText(payment.booking_no).includes(query) ||
        safeText(payment.payment_id).includes(query)

      const matchesStatus = statusFilter === 'all' || payment.payment_status === statusFilter
      const matchesMethod = methodFilter === 'all' || payment.payment_method === methodFilter
      const matchesDate = isInDateRange(payment.paid_at || payment.created_at, dateFrom, dateTo)

      return matchesSearch && matchesStatus && matchesMethod && matchesDate
    })
  }, [payments, searchTerm, statusFilter, methodFilter, dateFrom, dateTo])

  const totalPages = Math.max(1, Math.ceil(filteredPayments.length / PAGE_SIZE))
  const paginatedPayments = filteredPayments.slice((currentPage - 1) * PAGE_SIZE, currentPage * PAGE_SIZE)

  useEffect(() => {
    const focusValue = new URLSearchParams(location.search).get('focus')
    if (!focusValue) return

    setSearchTerm('')
    setStatusFilter('all')
    setMethodFilter('all')
    setDateFrom('')
    setDateTo('')
    setFocusedTransaction(focusValue)
  }, [location.search])

  useEffect(() => {
    if (!focusedTransaction || loading) return

    const focusedIndex = filteredPayments.findIndex(
      (payment) => [payment.transaction_id, payment.payment_id, payment.order_id, payment.id, payment.booking_no]
        .some((value) => value != null && String(value) === String(focusedTransaction))
    )

    if (focusedIndex < 0) return

    setCurrentPage(Math.floor(focusedIndex / PAGE_SIZE) + 1)
  }, [focusedTransaction, filteredPayments, loading])

  useEffect(() => {
    if (!focusedTransaction || loading) return

    const focusedPayment = paginatedPayments.find((payment) =>
      [payment.transaction_id, payment.payment_id, payment.order_id, payment.id, payment.booking_no]
        .some((value) => value != null && String(value) === String(focusedTransaction))
    )
    const element = focusedPayment
      ? [focusedPayment.transaction_id, focusedPayment.payment_id, focusedPayment.order_id, focusedPayment.id, focusedPayment.booking_no]
          .map((value) => focusRefs.current[String(value)])
          .find(Boolean)
      : null
    if (!element) return

    const timer = window.setTimeout(() => {
      element.scrollIntoView({ behavior: 'smooth', block: 'center' })
      setFlashTransaction(String(focusedPayment.transaction_id))
      window.setTimeout(() => setFlashTransaction(''), 1900)
      setFocusedTransaction('')
    }, 150)

    return () => window.clearTimeout(timer)
  }, [focusedTransaction, paginatedPayments, loading])



  const summary = useMemo(() => {
    return payments.reduce(
      (acc, payment) => {
        if (payment.payment_status === 'paid') acc.totalPaid += Number(payment.amount)
        if (payment.payment_status === 'pending') acc.pending += Number(payment.amount)
        if (payment.payment_status === 'refunded') acc.refunded += Number(payment.amount)
        return acc
      },
      { totalPaid: 0, pending: 0, refunded: 0 }
    )
  }, [payments])

  const handleResetFilters = () => {
    setSearchTerm('')
    setStatusFilter('all')
    setMethodFilter('all')
    setDateFrom('')
    setDateTo('')
    setCurrentPage(1)
    showToast('Payment filters reset.')
  }

  const handleSavePayment = async (payment, payload) => {
    try {
      setSavingPayment(true)
      const refreshedPayments = await updateCombinedStatusByBooking(payment.booking_id, payload)
      setPayments(refreshedPayments)
      setEditingPayment(null)
      showToast('Statuses updated successfully. Email handled by the server.')
    } catch (err) {
      showToast(err.message || 'Unable to update statuses.', err.severity === 'warning' ? 'warning' : 'error')
    } finally {
      setSavingPayment(false)
    }
  }

  const handleDownload = (format) => {
    const rows = buildPaymentExportRows(filteredPayments)

    if (rows.length === 0) {
      showToast('No payment data available for download.', 'error')
      return
    }

    const payload = {
      fileName: 'jebal-guest-house-payments',
      title: 'Tulip Guest Inn Payment Report',
      rows,
    }

    if (format === 'csv') exportCsv(payload)
    if (format === 'excel') exportExcel(payload)
    if (format === 'pdf') exportPdf(payload)
  }

  return (
    <div className="space-y-6">
      <Toast message={toast?.message} type={toast?.type} onClose={() => setToast(null)} />

      <PageHeader title="Payments" description="Track Tulip Guest Inn payment status and payment methods.">
        <div className="flex flex-wrap gap-2">
          <Button type="button" variant="outline" onClick={handleResetFilters}>
            <Filter className="h-4 w-4" />
            Clear Filters
          </Button>
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
        </div>
      </PageHeader>

      <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        <StatCard title="Total Revenue" value={formatCurrency(summary.totalPaid)} description="Successfully collected" icon={TrendingUp} />
        <StatCard title="Pending Payments" value={formatCurrency(summary.pending)} description="Awaiting confirmation" icon={WalletCards} />
        <StatCard title="Refunded Amount" value={formatCurrency(summary.refunded)} description="Returned to guests" icon={Banknote} />
      </div>

      <Card>
        <CardContent className="p-5">
          <div className="mb-5">
            <div className="grid gap-4 md:grid-cols-4 xl:grid-cols-5">
              <div className="md:col-span-4 xl:col-span-1">
                <Label htmlFor="payment-search">Search payments</Label>
                <div className="relative mt-2">
                  <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                  <Input
                    id="payment-search"
                    value={searchTerm}
                    onChange={(event) => setSearchTerm(event.target.value)}
                    placeholder="Transaction or booking no"
                    className="pl-9"
                  />
                </div>
              </div>

              <div>
                <Label htmlFor="payment-status">Status</Label>
                <select
                  id="payment-status"
                  value={statusFilter}
                  onChange={(event) => setStatusFilter(event.target.value)}
                  className="mt-2 h-10 w-full rounded-lg border border-border bg-white px-3 text-sm font-medium text-text-primary shadow-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20"
                >
                  <option value="all">All statuses</option>
                  {paymentStatuses.map((status) => (
                    <option key={status} value={status}>{statusLabel[status]}</option>
                  ))}
                </select>
              </div>

              <div>
                <Label htmlFor="payment-method">Method</Label>
                <select
                  id="payment-method"
                  value={methodFilter}
                  onChange={(event) => setMethodFilter(event.target.value)}
                  className="mt-2 h-10 w-full rounded-lg border border-border bg-white px-3 text-sm font-medium text-text-primary shadow-sm outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20"
                >
                  <option value="all">All methods</option>
                  {methodOptions.map((method) => (
                    <option key={method} value={method}>{method}</option>
                  ))}
                </select>
              </div>

              <div className="grid grid-cols-2 gap-3 md:contents">
                <div className="min-w-0">
                  <Label htmlFor="date-from">From</Label>
                  <Input
                    id="date-from"
                    type="date"
                    value={dateFrom}
                    onChange={(event) => setDateFrom(event.target.value)}
                    className="mt-2 w-full min-w-0 px-3 text-sm"
                  />
                </div>

                <div className="min-w-0">
                  <Label htmlFor="date-to">To</Label>
                  <Input
                    id="date-to"
                    type="date"
                    value={dateTo}
                    onChange={(event) => setDateTo(event.target.value)}
                    className="mt-2 w-full min-w-0 px-3 text-sm"
                  />
                </div>
              </div>
            </div>

          </div>

          {error ? (
            <div className="rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-medium text-red-800">{error}</div>
          ) : null}

          <div className="overflow-x-auto">
            <table className="w-full min-w-[820px] text-left text-sm">
              <thead>
                <tr className="border-b border-border text-xs uppercase tracking-wide text-text-secondary">
                  <th className="px-3 py-3">Transaction</th>
                  <th className="px-3 py-3">Booking</th>
                  <th className="px-3 py-3">Amount</th>
                  <th className="px-3 py-3">Method</th>
                  <th className="px-3 py-3">Status</th>
                  <th className="px-3 py-3">Date</th>
                  <th className="px-3 py-3 text-right">Action</th>
                </tr>
              </thead>
              <tbody>
                {loading ? (
                  <tr><td colSpan="7" className="px-3 py-8 text-center text-text-secondary">Loading payments...</td></tr>
                ) : filteredPayments.length === 0 ? (
                  <tr><td colSpan="7" className="px-3 py-8 text-center text-text-secondary">No payments found.</td></tr>
                ) : (
                  paginatedPayments.map((payment) => (
                    <tr
                      key={payment.id}
                      ref={(element) => {
                        if (element) {
                          [payment.transaction_id, payment.payment_id, payment.order_id, payment.id, payment.booking_no]
                            .filter((value) => value != null && String(value) !== '')
                            .forEach((value) => { focusRefs.current[String(value)] = element })
                        }
                      }}
                      className={`transition hover:bg-blue-50/40 ${flashTransaction === payment.transaction_id ? 'dashboard-payment-focus-row' : 'border-b border-border last:border-0'}`}
                    >
                      <td className="px-3 py-4 font-semibold text-text-primary">{payment.transaction_id}</td>
                      <td className="px-3 py-4 text-text-secondary">{payment.booking_no}</td>
                      <td className="px-3 py-4 font-semibold text-text-primary">{formatCurrency(payment.amount)}</td>
                      <td className="px-3 py-4 text-text-secondary">{payment.payment_method || '-'}</td>
                      <td className="px-3 py-4"><PaymentStatusBadge status={payment.payment_status} /></td>
                      <td className="px-3 py-4 text-text-secondary">{formatDate(payment.paid_at || payment.created_at)}</td>
                      <td className="px-3 py-4 text-right">
                        <ActionsDropdown
                          payment={payment}
                          onView={() => setSelectedPayment(payment)}
                          onEdit={() => setEditingPayment(payment)}
                        />
                      </td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>
        </CardContent>

        {!loading && filteredPayments.length > 0 ? (
          <Pagination
            page={currentPage}
            totalPages={totalPages}
            totalItems={filteredPayments.length}
            onPageChange={setCurrentPage}
          />
        ) : null}
      </Card>

      {selectedPayment ? <PaymentDetailsModal payment={selectedPayment} onClose={() => setSelectedPayment(null)} /> : null}
      {editingPayment ? (
        <EditPaymentModal
          payment={editingPayment}
          methodOptions={methodOptions}
          onClose={() => setEditingPayment(null)}
          onSave={handleSavePayment}
          saving={savingPayment}
        />
      ) : null}
    </div>
  )
}
