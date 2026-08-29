import { requestJson } from './apiClient';

export type BookingFormPayload = {
  full_name: string;
  email: string;
  phone: string;
  room_name: string;
  check_in_date: string;
  check_out_date: string;
  guests: number;
  rooms?: number;
  booking_group_id?: number | null;
  message?: string;
  is_booking_for_other?: boolean;
  staying_guest_name?: string;
  staying_guest_email?: string;
  staying_guest_phone?: string;
  staying_guest_note?: string;
  payment_method: 'Cash' | 'PayHere';
};

export type CheckoutSession = {
  checkout_url: string;
  order_id: string;
  amount: string | number;
  currency: string;
};

export type PublicFeatures = {
  online_payment_enabled: boolean;
  calendar_sync_enabled: boolean;
};

export async function fetchPublicFeatures(): Promise<PublicFeatures> {
  return requestJson('/settings/public-features.php');
}

export type PaymentHistoryItem = {
  attempt: number;
  order_id: string;
  payment_id: string;
  amount: number;
  subtotal_amount?: number;
  discount_amount?: number;
  applied_offer_title?: string;
  currency: string;
  status: string;
  method: string;
  invoice_number: string;
  created_at: string;
  updated_at: string;
};

export type BookingPaymentStatus = {
  id: number;
  full_name: string;
  email: string;
  phone: string;
  room_name: string;
  check_in_date: string;
  check_out_date: string;
  guests: number;
  booking_status: string;
  payment_status: string;
  amount: number;
  currency: string;
  invoice_number: string;
  order_id: string;
  payment_id: string;
  payment_method: string;
  room_url: string;
  room_main_image?: string;
  invoice_download_url?: string | null;
  hold_minutes: number;
  expires_at?: string | null;
  seconds_remaining: number;
  can_retry_payment: boolean;
  payment_history: PaymentHistoryItem[];
};

export async function submitBookingRequest(payload: BookingFormPayload): Promise<{
  inquiry_id?: string;
  booking_id?: string | number;
  booking_no?: string;
  amount?: number;
  currency?: string;
  payment_method?: 'Cash' | 'PayHere';
  order_id?: string | null;
  bill_url?: string | null;
  requires_online_checkout?: boolean;
}> {
  return requestJson('/submit-booking.php', {
    method: 'POST',
    body: JSON.stringify(payload),
  });
}

export type MultiRoomBookingPayload = {
  full_name: string;
  email: string;
  phone: string;
  check_in_date: string;
  check_out_date: string;
  total_guests: number;
  payment_method: 'Cash' | 'PayHere';
  message?: string;
  rooms: Array<{ room_id: string | number; guests: number }>;
};

export async function submitMultiRoomBooking(payload: MultiRoomBookingPayload): Promise<{
  booking_id: number;
  booking_no: string;
  bill_url?: string | null;
  requires_online_checkout?: boolean;
}> {
  return requestJson('/submit-multi-room-booking.php', {
    method: 'POST',
    body: JSON.stringify(payload),
  });
}


export async function createCheckoutSession(bookingId: string | number): Promise<CheckoutSession> {
  return requestJson('/payments/create-checkout-session.php', {
    method: 'POST',
    body: JSON.stringify({ booking_id: bookingId }),
  });
}

export async function fetchBookingPaymentStatus(params: {
  booking_id: string | number;
  order_id: string;
  token: string;
}): Promise<BookingPaymentStatus> {
  const query = new URLSearchParams({
    booking_id: String(params.booking_id),
    order_id: params.order_id,
    token: params.token,
  });

  const payload = await requestJson<{ booking: BookingPaymentStatus }>(`/payments/status.php?${query.toString()}`);
  return payload.booking;
}
