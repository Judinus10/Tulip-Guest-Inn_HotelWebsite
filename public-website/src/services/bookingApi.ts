import { requestJson } from './apiClient';

export type BookingFormPayload = {
  full_name: string;
  email: string;
  phone: string;
  room_name: string;
  check_in_date: string;
  check_out_date: string;
  guests: number;
  message?: string;
  is_booking_for_other?: boolean;
  staying_guest_name?: string;
  staying_guest_email?: string;
  staying_guest_phone?: string;
  staying_guest_note?: string;
};

export async function submitBookingRequest(payload: BookingFormPayload): Promise<{
  inquiry_id?: string;
  booking_id?: string | number;
  booking_no?: string;
  amount?: number;
  currency?: string;
}> {
  return requestJson('/submit-booking.php', {
    method: 'POST',
    body: JSON.stringify(payload),
  });
}
