import { initialBookings } from './bookingData'

export const paymentStatuses = ['pending', 'paid', 'failed', 'cancelled', 'refunded']

export const paymentMethods = ['Manual', 'Bank Transfer', 'Cash']

export const paymentGateways = ['Manual', 'Bank']

function normalisePaymentStatus(status) {
  if (status === 'unpaid') return 'pending'
  if (status === 'payment_pending') return 'pending'
  if (paymentStatuses.includes(status)) return status
  return 'pending'
}

export const initialPayments = initialBookings.map((booking, index) => {
  const status = normalisePaymentStatus(booking.payment_status)
  const amount = Number(booking.total_amount ?? 0)

  return {
    id: index + 1,
    booking_id: booking.id,
    amount,
    payment_method: status === 'pending' ? 'Manual' : 'Bank Transfer',
    payment_gateway: status === 'pending' ? 'Manual' : 'Bank',
    transaction_id: booking.transaction_id || '',
    payment_status: status,
    paid_at: status === 'paid' || status === 'refunded' ? booking.updated_at || booking.created_at : null,
    created_at: booking.created_at,
    booking_no: booking.booking_no,
    guest_name: booking.guest_name,
    guest_email: booking.guest_email,
    guest_phone: booking.guest_phone,
    room_id: booking.room_id,
    check_in: booking.check_in,
    check_out: booking.check_out,
  }
})
