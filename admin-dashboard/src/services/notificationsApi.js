import { fetchBookings } from '@/services/bookingsApi'
import { fetchPayments } from '@/services/paymentsApi'
import { apiFetch, buildApiUrl, readJsonResponse } from '@/services/apiClient'

const CONTACT_API_URL = buildApiUrl('/contact/list_enquiries.php')

const READ_NOTIFICATIONS_KEY = 'jebal_read_notifications'

function readStoredNotificationIds() {
  if (typeof window === 'undefined') return new Set()
  try {
    const value = window.localStorage.getItem(READ_NOTIFICATIONS_KEY)
    const parsed = value ? JSON.parse(value) : []
    return new Set(Array.isArray(parsed) ? parsed : [])
  } catch {
    return new Set()
  }
}

export function markNotificationActivityRead(id) {
  if (typeof window === 'undefined' || !id) return
  const ids = readStoredNotificationIds()
  ids.add(String(id))
  window.localStorage.setItem(READ_NOTIFICATIONS_KEY, JSON.stringify([...ids]))
  window.dispatchEvent(new CustomEvent('notification-read-changed'))
}

function applyStoredReadStatus(activities) {
  const ids = readStoredNotificationIds()
  return activities.map((activity) => (ids.has(String(activity.id)) ? { ...activity, status: activity.status === 'Resolved' ? 'Resolved' : 'Viewed' } : activity))
}

function notificationRouteState(activity) {
  return {
    notificationFocus: {
      id: activity.id,
      type: activity.type,
      referenceId: activity.related_booking !== '-' ? activity.related_booking : activity.reference_id,
      paymentReference: activity.reference_id,
    },
  }
}

export function getNotificationNavigation(activity) {
  const state = notificationRouteState(activity)
  const basePath = activity.route || '/notifications'
  const focusValue = basePath === '/payments'
    ? state.notificationFocus.paymentReference
    : state.notificationFocus.referenceId

  return {
    pathname: focusValue
      ? `${basePath}?focus=${encodeURIComponent(focusValue)}`
      : basePath,
    state,
  }
}

function asDate(value) {
  if (!value) return null
  const date = new Date(String(value).replace(' ', 'T'))
  return Number.isNaN(date.getTime()) ? null : date
}

function isToday(value) {
  if (!value) return false
  const raw = String(value).trim()
  const ymd = raw.match(/^(\d{4})-(\d{2})-(\d{2})/)
  const date = ymd
    ? new Date(Number(ymd[1]), Number(ymd[2]) - 1, Number(ymd[3]))
    : asDate(value)
  if (!date) return false
  const now = new Date()
  return date.getFullYear() === now.getFullYear() && date.getMonth() === now.getMonth() && date.getDate() === now.getDate()
}

function isActiveStayBooking(booking) {
  const bookingStatus = String(booking.booking_status || '').toLowerCase()
  const paymentStatus = String(booking.payment_status || '').toLowerCase()

  if (['cancelled', 'canceled', 'no_show', 'checked_out', 'expired'].includes(bookingStatus)) {
    return false
  }

  if (['cancelled', 'canceled', 'failed', 'refunded', 'expired'].includes(paymentStatus)) {
    return false
  }

  return true
}

function titleCaseStatus(status) {
  const value = String(status || '').replace(/_/g, ' ').trim()
  if (!value) return 'Pending'
  return value.replace(/\b\w/g, (char) => char.toUpperCase())
}

function money(amount, currency = 'LKR') {
  const number = Number(amount || 0)
  if (!number) return currency
  return `${currency} ${number.toLocaleString()}`
}

function normalizeInquiry(item) {
  return {
    id: Number(item.id || 0),
    inquiry_id: item.inquiry_id || `INQ-${String(item.id || 0).padStart(5, '0')}`,
    name: item.name || 'Guest',
    email: item.email || '',
    phone: item.phone || '',
    subject: item.subject || 'General Inquiry',
    message: item.message || '',
    status: item.status || 'New',
    created_at: item.created_at || item.updated_at || '',
    updated_at: item.updated_at || item.created_at || '',
  }
}

async function fetchEnquiries() {
  const response = await apiFetch(CONTACT_API_URL, { headers: { Accept: 'application/json' } })
  const payload = await readJsonResponse(response)
  return (payload.data || []).map(normalizeInquiry)
}

function makeActivity(activity) {
  return {
    related_room: '-',
    related_booking: '-',
    status: 'Viewed',
    details: '',
    created_at: new Date().toISOString(),
    ...activity,
  }
}

function activitiesFromBookings(bookings) {
  const activities = []

  bookings.forEach((booking) => {
    const bookingStatus = String(booking.booking_status || '').toLowerCase()
    const paymentStatus = String(booking.payment_status || '').toLowerCase()
    const isCancelled = bookingStatus === 'cancelled'

    activities.push(makeActivity({
      id: `booking-${booking.id}`,
      type: isCancelled ? 'Cancellation' : 'Booking',
      title: isCancelled ? 'Booking Cancelled' : 'New Booking Received',
      reference_id: booking.booking_no,
      description: `${booking.guest_name} booked ${booking.room_name} from ${booking.check_in || '-'} to ${booking.check_out || '-'}.`,
      related_room: booking.room_name || '-',
      related_booking: booking.booking_no || '-',
      status: bookingStatus === 'pending' || isCancelled ? 'New' : 'Resolved',
      created_at: booking.updated_at || booking.created_at,
      details: `Guest: ${booking.guest_name}\nEmail: ${booking.guest_email || '-'}\nPhone: ${booking.guest_phone || '-'}\nBooking status: ${titleCaseStatus(booking.booking_status)}\nPayment status: ${titleCaseStatus(paymentStatus)}\nAmount: ${money(booking.total_amount, booking.payment_currency)}\nSpecial request: ${booking.special_requests || '-'}`,
      route: '/bookings',
      searchText: [booking.booking_no, booking.guest_name, booking.guest_email, booking.guest_phone, booking.room_name].join(' '),
    }))

    if (isActiveStayBooking(booking) && isToday(booking.check_in)) {
      const alreadyCheckedIn = bookingStatus === 'checked_in'
      activities.push(makeActivity({
        id: `checkin-${booking.id}`,
        type: 'Check-in',
        title: alreadyCheckedIn ? 'Checked In Today' : 'Check-in Today',
        reference_id: booking.booking_no,
        description: `${booking.guest_name} is scheduled to check in today.`,
        related_room: booking.room_name || '-',
        related_booking: booking.booking_no || '-',
        status: alreadyCheckedIn ? 'Resolved' : 'New',
        created_at: new Date().toISOString(),
        details: `Check-in for ${booking.room_name}. Contact ${booking.guest_phone || booking.guest_email || 'guest'} before arrival.`,
        route: '/bookings',
        searchText: [booking.booking_no, booking.guest_name, booking.room_name, 'check in'].join(' '),
      }))
    }

    if (isActiveStayBooking(booking) && isToday(booking.check_out)) {
      const alreadyCheckedOut = bookingStatus === 'checked_out'
      activities.push(makeActivity({
        id: `checkout-${booking.id}`,
        type: 'Check-out',
        title: alreadyCheckedOut ? 'Checked Out Today' : 'Check-out Today',
        reference_id: booking.booking_no,
        description: `${booking.guest_name} is scheduled to check out today.`,
        related_room: booking.room_name || '-',
        related_booking: booking.booking_no || '-',
        status: alreadyCheckedOut ? 'Resolved' : 'New',
        created_at: new Date().toISOString(),
        details: `Prepare room cleaning after check-out for ${booking.room_name}.`,
        route: '/bookings',
        searchText: [booking.booking_no, booking.guest_name, booking.room_name, 'check out'].join(' '),
      }))
    }
  })

  return activities
}

function activitiesFromPayments(payments) {
  return payments.map((payment) => {
    const status = String(payment.payment_status || 'pending').toLowerCase()
    const isPending = status === 'pending'
    const isProblem = ['failed', 'cancelled', 'refunded'].includes(status)

    return makeActivity({
      id: `payment-${payment.id}`,
      type: 'Payment',
      title: isPending ? 'Payment Pending' : `Payment ${titleCaseStatus(status)}`,
      reference_id: payment.transaction_id || payment.order_id || `PAY-${String(payment.id).padStart(4, '0')}`,
      description: `${money(payment.amount, payment.currency)} ${titleCaseStatus(status)} for ${payment.booking_no}.`,
      related_room: payment.room_name || '-',
      related_booking: payment.booking_no || '-',
      status: isPending || isProblem ? 'New' : 'Viewed',
      created_at: payment.updated_at || payment.created_at || payment.paid_at,
      details: `Booking: ${payment.booking_no}\nGuest: ${payment.guest_name}\nMethod: ${payment.payment_method}\nGateway/order: ${payment.order_id || '-'}\nPayment ID: ${payment.payment_id || '-'}\nStatus: ${titleCaseStatus(status)}`,
      route: '/payments',
      searchText: [payment.transaction_id, payment.order_id, payment.payment_id, payment.booking_no, payment.guest_name, payment.room_name].join(' '),
    })
  })
}

function activitiesFromEnquiries(enquiries) {
  return enquiries.map((item) => makeActivity({
    id: `contact-${item.id}`,
    type: 'Contact',
    title: item.subject || 'New Contact Enquiry',
    reference_id: item.inquiry_id,
    description: `${item.name}: ${item.message || item.email || 'Contact enquiry received.'}`,
    related_room: '-',
    related_booking: '-',
    status: item.status === 'New' ? 'New' : item.status === 'Replied' ? 'Resolved' : 'Viewed',
    created_at: item.created_at,
    details: `Name: ${item.name}\nEmail: ${item.email || '-'}\nPhone: ${item.phone || '-'}\nSubject: ${item.subject}\nMessage: ${item.message || '-'}`,
    route: '/messages',
    searchText: [item.inquiry_id, item.name, item.email, item.phone, item.subject, item.message].join(' '),
  }))
}

export async function fetchNotificationActivities() {
  const [bookings, payments, enquiries] = await Promise.all([
    fetchBookings().catch(() => []),
    fetchPayments().catch(() => []),
    fetchEnquiries().catch(() => []),
  ])

  return applyStoredReadStatus([
    ...activitiesFromBookings(bookings),
    ...activitiesFromPayments(payments),
    ...activitiesFromEnquiries(enquiries),
  ].sort((a, b) => (asDate(b.created_at)?.getTime() || 0) - (asDate(a.created_at)?.getTime() || 0)))
}

export async function fetchTopbarSearchData() {
  const [bookings, payments, enquiries] = await Promise.all([
    fetchBookings().catch(() => []),
    fetchPayments().catch(() => []),
    fetchEnquiries().catch(() => []),
  ])

  return [
    ...bookings.map((item) => ({
      id: `booking-${item.id}`,
      type: 'Booking',
      title: item.booking_no || `Booking ${item.id}`,
      subtitle: `${item.guest_name} · ${item.room_name} · ${item.check_in || '-'} to ${item.check_out || '-'}`,
      route: '/bookings',
      searchText: [item.booking_no, item.guest_name, item.guest_email, item.guest_phone, item.room_name, item.booking_status, item.payment_status].join(' '),
    })),
    ...payments.map((item) => ({
      id: `payment-${item.id}`,
      type: 'Payment',
      title: item.transaction_id || item.order_id || `Payment ${item.id}`,
      subtitle: `${item.booking_no} · ${item.guest_name} · ${titleCaseStatus(item.payment_status)} · ${money(item.amount, item.currency)}`,
      route: '/payments',
      searchText: [item.transaction_id, item.order_id, item.payment_id, item.booking_no, item.guest_name, item.room_name, item.payment_status, item.payment_method].join(' '),
    })),
    ...enquiries.map((item) => ({
      id: `contact-${item.id}`,
      type: 'Message',
      title: item.inquiry_id,
      subtitle: `${item.name} · ${item.subject} · ${item.email || item.phone || ''}`,
      route: '/messages',
      searchText: [item.inquiry_id, item.name, item.email, item.phone, item.subject, item.message, item.status].join(' '),
    })),
  ]
}
