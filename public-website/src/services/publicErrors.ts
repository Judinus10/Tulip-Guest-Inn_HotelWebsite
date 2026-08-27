export class PublicRequestError extends Error {
  code: string; status: number; retryable: boolean; outcomeUnknown: boolean;
  constructor(message: string, options: { code?: string; status?: number; retryable?: boolean; outcomeUnknown?: boolean } = {}) { super(message); this.name = 'PublicRequestError'; this.code = options.code || 'REQUEST_FAILED'; this.status = options.status || 0; this.retryable = Boolean(options.retryable); this.outcomeUnknown = Boolean(options.outcomeUnknown); }
}
const fallbacks: Record<string, string> = {
  rooms: 'Rooms could not be loaded right now. Please refresh the page or try again shortly.', room: 'This room could not be loaded right now. Please return to the rooms page and try again.', gallery: 'The gallery is temporarily unavailable. Please try again later.', bill: 'Your booking bill is temporarily unavailable. Your booking has not been cancelled.', payment: 'Online payment could not be started. Your booking has not been cancelled.', contact: 'Your message could not be sent. Please try again or contact the property by phone.', booking: 'Your booking could not be completed. Please review your details and try again.', multiBooking: 'Your multi-room booking could not be completed. Please review the room allocation and try again.',
};
const actionable: Record<string, string> = {
  INVALID_PAYMENT_METHOD: 'Please choose Pay on Arrival and submit the booking again.',
  ONLINE_PAYMENT_DISABLED: 'Online payment is not available at the moment. Please use Pay on Arrival.',
  MISSING_REQUIRED_FIELDS: 'Please complete every field marked with an asterisk (*).',
  INVALID_EMAIL: 'Please enter a valid email address, for example name@example.com.',
  INVALID_DATES: 'Please select a valid check-in date and a check-out date after check-in.',
  ROOM_CAPACITY_EXCEEDED: 'This room cannot accommodate the selected number of guests. Reduce the guest count or choose multiple rooms.',
  ROOM_UNAVAILABLE: 'This room is no longer available for the selected dates. Please choose another room or change the dates.',
  ROOM_SELECTION_CHANGED: 'One of the selected rooms is no longer available. Please search and select the rooms again.',
  BOOKING_SAVED_BILL_UNAVAILABLE: 'Your booking was saved, but its bill could not be opened. Do not book again. Please contact reception.',
  BOOKING_OUTCOME_UNKNOWN: 'Your booking may already be saved. Do not submit it again. Please contact reception with your name and booking dates.',
  BOOKING_NOT_SAVED: 'The server could not save your booking. Your form details may be correct. Please try once more or contact reception.',
};
const technical = /(sql|pdo|exception|stack|trace|\.php|database|query|syntax|fatal|warning|undefined|json)/i;
export function publicErrorMessage(error: unknown, context = 'rooms') {
  const value = error as { name?: string; code?: string; status?: number; message?: string };
  if (typeof navigator !== 'undefined' && !navigator.onLine) return 'You appear to be offline. Check your internet connection and try again.';
  if (value?.name === 'AbortError' || value?.code === 'TIMEOUT') return 'The request is taking longer than expected. Please try again.';
  const message = String(value?.message || '').trim();
  if (value?.code && actionable[value.code] && (!message || technical.test(message))) return actionable[value.code];
  if (technical.test(message)) return fallbacks[context] || 'This service is temporarily unavailable. Please try again.';
  if ((value?.status || 0) >= 500 && !message) return fallbacks[context] || 'This service is temporarily unavailable. Please try again.';
  return message || fallbacks[context] || 'Something went wrong. Please try again.';
}
