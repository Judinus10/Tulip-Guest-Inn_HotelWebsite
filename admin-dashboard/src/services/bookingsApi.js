import { bookingRooms } from '@/data/bookingData'
import { apiFetch, buildApiUrl, readJsonResponse } from '@/services/apiClient'

const BOOKINGS_API_BASE_URL = buildApiUrl('/bookings')
const PUBLIC_BOOKING_URL = buildApiUrl('/submit-booking.php')

export const paymentStatusOptions = ['pending', 'paid', 'cancelled', 'refunded', 'no_pay']
export const paymentMethodOptions = ['PayHere', 'Cash', 'Bank Transfer']

function normalizeBookingStatus(status) {
  const value = String(status || 'pending')
    .trim()
    .toLowerCase()
    .replace(/^booking\s+/, '')
    .replace(/^payment\s+/, '')
    .replace(/\s+/g, '_')

  if (value === 'confirmed') return 'confirmed'
  if (value === 'checked_in' || value === 'check_in' || value === 'checkedin') return 'checked_in'
  if (value === 'checked_out' || value === 'check_out' || value === 'checkedout') return 'checked_out'
  if (value === 'no_show' || value === 'noshow') return 'no_show'
  if (value === 'cancelled' || value === 'canceled') return 'cancelled'

  // Some API rows may expose the payment status in a generic `status` field.
  // Do not show that as the booking status. Treat it as a pending booking instead.
  if (['paid', 'failed', 'refunded', 'unpaid', 'pending'].includes(value)) return 'pending'

  return 'pending'
}

export function toApiPaymentStatus(status) {
  const value = String(status || 'pending').trim().toLowerCase().replace(/^payment\s+/, '').replace(/\s+/g, '_')

  if (value === 'paid') return 'Paid'
  if (value === 'failed') return 'Failed'
  if (value === 'cancelled' || value === 'canceled') return 'Cancelled'
  if (value === 'refunded') return 'Refunded'
  if (value === 'no_pay') return 'No Pay'
  return 'Payment Pending'
}

export function normalizePaymentStatus(status) {
  const value = String(status || 'Payment Pending')
    .trim()
    .toLowerCase()
    .replace(/^payment\s+/, '')
    .replace(/\s+/g, '_')

  if (value === 'paid') return 'paid'
  if (value === 'failed') return 'failed'
  if (value === 'cancelled' || value === 'canceled') return 'cancelled'
  if (value === 'refunded') return 'refunded'
  if (value === 'no_pay' || value === 'nopay' || value === 'no_payment') return 'no_pay'
  if (value === 'unpaid') return 'pending'
  return 'pending'
}

function calculateNights(checkIn, checkOut) {
  if (!checkIn || !checkOut) return 1

  const start = new Date(`${checkIn}T00:00:00`)
  const end = new Date(`${checkOut}T00:00:00`)

  if (Number.isNaN(start.getTime()) || Number.isNaN(end.getTime())) return 1

  return Math.max(1, Math.round((end.getTime() - start.getTime()) / 86400000))
}

function getRoomByName(roomName) {
  return bookingRooms.find((room) => room.room_name.toLowerCase() === String(roomName || '').toLowerCase())
}

export function normalizeBooking(booking) {
  const roomName = booking.room_name || booking.roomName || booking.room || ''
  const room = getRoomByName(roomName)
  const checkIn = booking.check_in || booking.check_in_date || booking.checkIn || booking.arrival_date || booking.arrival || ''
  const checkOut = booking.check_out || booking.check_out_date || booking.checkOut || booking.departure_date || booking.departure || ''
  const guests = Number(booking.guests || booking.guest_count || booking.no_of_guests || booking.adults || 1)
  const totalNights = Number(booking.total_nights || booking.nights || calculateNights(checkIn, checkOut))
  const bookingNumber = booking.booking_no || booking.bookingNo || booking.booking_number || `BK-${String(booking.id || booking.booking_id || 0).padStart(5, '0')}`
  const source = String(booking.source || '').trim().toLowerCase()
  const externalFlag = booking.is_external === true || Number(booking.is_external) === 1
  const isExternal = externalFlag || source === 'booking.com' || /^(?:BDC|BC)-/i.test(String(bookingNumber))
  const amount = isExternal
    ? 0
    : Number(booking.total_amount || booking.amount || booking.payment_amount || (Number(room?.price_per_night || 0) * totalNights) || 0)

  return {
    id: Number(booking.id || booking.booking_id || 0),
    booking_no: bookingNumber,
    guest_name: isExternal ? 'Booking.com reservation' : (booking.guest_name || booking.staying_guest_name || booking.full_name || booking.customer_name || booking.name || 'Guest'),
    guest_email: booking.guest_email || booking.staying_guest_email || booking.email || booking.customer_email || '',
    guest_phone: booking.guest_phone || booking.staying_guest_phone || booking.phone || booking.mobile || booking.customer_phone || '',
    booker_name: booking.booker_name || booking.full_name || booking.customer_name || booking.name || booking.guest_name || 'Guest',
    booker_email: booking.booker_email || booking.email || booking.customer_email || booking.guest_email || '',
    booker_phone: booking.booker_phone || booking.phone || booking.mobile || booking.customer_phone || booking.guest_phone || '',
    is_booking_for_other: Boolean(Number(booking.is_booking_for_other || 0)) || booking.is_booking_for_other === true,
    staying_guest_name: booking.staying_guest_name || '',
    staying_guest_email: booking.staying_guest_email || '',
    staying_guest_phone: booking.staying_guest_phone || '',
    staying_guest_note: booking.staying_guest_note || '',
    room_id: Number(booking.room_id || room?.id || 0),
    room_name: roomName || room?.room_name || 'Unknown room',
    room_type: booking.room_type || room?.room_type || '-',
    room_code: booking.room_code || `R${String(room?.id || booking.room_id || 0).padStart(2, '0')}`,
    property_type: booking.property_type || room?.room_type || 'Guest House',
    check_in: checkIn,
    check_out: checkOut,
    check_in_date: checkIn,
    check_out_date: checkOut,
    guests,
    adults: Number(booking.adults || guests || 1),
    children: Number(booking.children || 0),
    total_nights: totalNights,
    booking_status: normalizeBookingStatus(booking.booking_status || booking.status || booking.bookingState),
    payment_status: normalizePaymentStatus(booking.payment_status || booking.paymentStatus),
    payment_method: booking.payment_method || booking.method || booking.paymentMethod || '',
    total_amount: amount,
    payment_currency: booking.payment_currency || booking.currency || 'LKR',
    special_requests: booking.special_requests || booking.special_request || booking.message || booking.note || '',
    special_request: booking.special_request || booking.special_requests || booking.message || booking.note || '',
    invoice_number: booking.invoice_number || '',
    invoice_file_path: booking.invoice_file_path || '',
    email_status: booking.email_status || 'Pending',
    created_at: booking.created_at || booking.createdAt || booking.booking_date || booking.date || '',
    updated_at: booking.updated_at || booking.updatedAt || booking.modified_at || '',
    source: isExternal ? 'booking.com' : (booking.source || 'website'),
    is_external: isExternal,
    external_uid: booking.external_uid || '',
  }
}

export async function fetchBookings() {
  const response = await apiFetch(`${BOOKINGS_API_BASE_URL}/list.php`, {
    method: 'GET',
    headers: { Accept: 'application/json' },
  })

  const payload = await readJsonResponse(response)
  return (payload.data || []).map(normalizeBooking)
}

export async function createManualBooking(formData) {
  const payload = {
    full_name: formData.full_name,
    email: formData.email,
    phone: formData.phone,
    room_name: formData.room_name,
    check_in_date: formData.check_in_date,
    check_out_date: formData.check_out_date,
    guests: Number(formData.guests || 1),
    message: formData.message || '',
    is_booking_for_other: Boolean(formData.is_booking_for_other),
    staying_guest_name: formData.staying_guest_name || '',
    staying_guest_email: formData.staying_guest_email || '',
    staying_guest_phone: formData.staying_guest_phone || '',
    staying_guest_note: formData.staying_guest_note || '',
    payment_status: toApiPaymentStatus(formData.payment_status || 'pending'),
    payment_method: formData.payment_method || 'Cash',
  }

  const response = await apiFetch(`${BOOKINGS_API_BASE_URL}/create-manual.php`, {
    method: 'POST',
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
    },
    body: JSON.stringify(payload),
  })

  const result = await readJsonResponse(response)
  return normalizeBooking(result.data || result)
}

export async function createPublicBooking(formData) {
  const response = await apiFetch(PUBLIC_BOOKING_URL, {
    method: 'POST',
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
    },
    body: JSON.stringify(formData),
  })

  return readJsonResponse(response)
}

export async function updateBookingStatus(bookingId, status) {
  const response = await apiFetch(`${BOOKINGS_API_BASE_URL}/update-status.php`, {
    method: 'POST',
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({ id: bookingId, status }),
  })

  const payload = await readJsonResponse(response)
  const data = payload.data || {}

  return {
    id: Number(data.id || bookingId),
    booking_status: normalizeBookingStatus(data.booking_status || data.status || status),
  }
}

export async function updatePaymentStatus(bookingId, paymentStatus, paymentMethod = '') {
  const response = await apiFetch(`${BOOKINGS_API_BASE_URL}/update-payment-status.php`, {
    method: 'POST',
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      id: bookingId,
      payment_status: toApiPaymentStatus(paymentStatus),
      payment_method: paymentMethod,
    }),
  })

  const payload = await readJsonResponse(response)
  const data = payload.data || {}

  return {
    id: Number(data.id || bookingId),
    payment_status: normalizePaymentStatus(data.payment_status || paymentStatus),
    payment_method: data.payment_method || paymentMethod,
  }
}

export async function updateBookingAndPaymentStatus(bookingId, updates) {
  const response = await apiFetch(`${BOOKINGS_API_BASE_URL}/update-statuses.php`, {
    method: 'POST',
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      id: bookingId,
      booking_status: updates.booking_status,
      payment_status: toApiPaymentStatus(updates.payment_status),
      payment_method: updates.payment_method || 'Manual',
      transaction_reference: updates.transaction_reference || '',
      remarks: updates.remarks || '',
      send_email: updates.send_email !== false,
    }),
  })

  const payload = await readJsonResponse(response)
  const data = payload.data || {}

  return {
    id: Number(data.id || bookingId),
    booking_status: normalizeBookingStatus(data.booking_status || updates.booking_status),
    payment_status: normalizePaymentStatus(data.payment_status || updates.payment_status),
    payment_method: data.payment_method || updates.payment_method || 'Manual',
  }
}

export async function deleteBooking(bookingId) {
  const response = await apiFetch(`${BOOKINGS_API_BASE_URL}/delete.php`, {
    method: 'POST',
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({ id: bookingId }),
  })

  const payload = await readJsonResponse(response)
  return payload.data
}
