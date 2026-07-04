import { apiFetch, buildApiUrl, readJsonResponse } from '@/services/apiClient'

const API_BASE_URL = buildApiUrl('/payments')
const BOOKINGS_API_BASE_URL = buildApiUrl('/bookings')

function normalizePaymentStatus(status) {
  const value = String(status || 'Payment Pending')
    .trim()
    .toLowerCase()
    .replace(/^payment\s+/, '')
    .replace(/[\s-]+/g, '_')

  if (value === 'paid') return 'paid'
  if (value === 'failed') return 'failed'
  if (value === 'cancelled' || value === 'canceled') return 'cancelled'
  if (value === 'refunded') return 'refunded'
  if (value === 'no_pay' || value === 'nopay' || value === 'no_payment') return 'no_pay'
  return 'pending'
}

function normalizePaymentMethod(method) {
  const value = String(method || 'PayHere').trim()
  if (!value) return 'PayHere'

  const normalized = value.toLowerCase().replace(/[\s_-]+/g, '_')
  if (normalized === 'payhere') return 'PayHere'
  if (normalized === 'cash') return 'Cash'
  if (normalized === 'bank_transfer' || normalized === 'bank') return 'Bank Transfer'
  if (normalized === 'card' || normalized === 'card_pos' || normalized === 'pos') return 'Card'
  if (normalized === 'no_pay' || normalized === 'nopay' || normalized === 'no_payment') return 'No Pay'
  if (normalized === 'other') return 'Other'
  return value
}

function dbPaymentStatus(status) {
  const value = normalizePaymentStatus(status)
  if (value === 'paid') return 'Paid'
  if (value === 'failed') return 'Failed'
  if (value === 'cancelled') return 'Cancelled'
  if (value === 'refunded') return 'Refunded'
  if (value === 'no_pay') return 'No Pay'
  return 'Payment Pending'
}

function normalizePayment(payment) {
  const paymentStatus = normalizePaymentStatus(payment.payment_status || payment.status)
  const bookingId = Number(payment.booking_id || 0)

  return {
    id: Number(payment.id || 0),
    booking_id: bookingId,
    booking_no: payment.booking_no || `BK-${String(bookingId).padStart(5, '0')}`,
    guest_name: payment.guest_name || payment.staying_guest_name || payment.full_name || 'Guest',
    guest_email: payment.guest_email || payment.staying_guest_email || payment.email || '',
    guest_phone: payment.guest_phone || payment.staying_guest_phone || payment.phone || '',
    booker_name: payment.booker_name || payment.full_name || payment.guest_name || 'Guest',
    booker_email: payment.booker_email || payment.email || payment.guest_email || '',
    booker_phone: payment.booker_phone || payment.phone || payment.guest_phone || '',
    is_booking_for_other: Boolean(Number(payment.is_booking_for_other || 0)) || payment.is_booking_for_other === true,
    staying_guest_name: payment.staying_guest_name || '',
    staying_guest_email: payment.staying_guest_email || '',
    staying_guest_phone: payment.staying_guest_phone || '',
    staying_guest_note: payment.staying_guest_note || '',
    booking_status: payment.booking_status || payment.status_booking || '',
    check_in: payment.check_in || payment.check_in_date || '',
    check_out: payment.check_out || payment.check_out_date || '',
    guests: Number(payment.guests || payment.total_guests || 0),
    total_nights: Number(payment.total_nights || payment.nights || 0),
    special_request: payment.special_request || payment.special_requests || payment.message || '',
    room_name: payment.room_name || '-',
    order_id: payment.order_id || '',
    payment_id: payment.payment_id || '',
    amount: Number(payment.amount || 0),
    currency: payment.currency || 'LKR',
    payment_status: paymentStatus,
    payment_method: normalizePaymentMethod(payment.payment_method || payment.method),
    payment_gateway: payment.payment_gateway || normalizePaymentMethod(payment.payment_method || payment.method),
    transaction_id: payment.transaction_id || payment.payment_id || payment.order_id || `PAY-${String(payment.id || 0).padStart(4, '0')}`,
    invoice_id: payment.invoice_id || null,
    invoice_number: payment.invoice_number || '',
    invoice_file_path: payment.invoice_file_path || '',
    email_status: payment.email_status || 'Pending',
    paid_at: payment.paid_at || null,
    created_at: payment.created_at || null,
    updated_at: payment.updated_at || null,
  }
}

export async function fetchPayments() {
  const response = await apiFetch(`${API_BASE_URL}/list.php?_=${Date.now()}`, {
    method: 'GET',
    cache: 'no-store',
    headers: {
      Accept: 'application/json',
      'Cache-Control': 'no-cache',
    },
  })

  const payload = await readJsonResponse(response)
  return (payload.data || []).map(normalizePayment)
}

export async function updateCombinedStatusByBooking(bookingId, updates) {
  const response = await apiFetch(`${BOOKINGS_API_BASE_URL}/update-statuses.php`, {
    method: 'POST',
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      id: bookingId,
      booking_status: updates.booking_status,
      payment_status: dbPaymentStatus(updates.payment_status),
      payment_method: normalizePaymentMethod(updates.payment_method),
      transaction_reference: updates.transaction_reference || '',
      remarks: updates.remarks || '',
      send_email: updates.send_email !== false,
    }),
  })

  await readJsonResponse(response)
  return fetchPayments()
}

export async function updatePaymentStatus(paymentId, updates) {
  const response = await apiFetch(`${API_BASE_URL}/update-status.php`, {
    method: 'POST',
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      id: paymentId,
      payment_status: dbPaymentStatus(updates.payment_status),
      payment_method: normalizePaymentMethod(updates.payment_method),
      transaction_reference: updates.transaction_reference || '',
      remarks: updates.remarks || '',
    }),
  })

  const payload = await readJsonResponse(response)
  return normalizePayment(payload.data || {})
}
