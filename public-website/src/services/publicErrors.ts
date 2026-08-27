export class PublicRequestError extends Error {
  code: string; status: number; retryable: boolean; outcomeUnknown: boolean;
  constructor(message: string, options: { code?: string; status?: number; retryable?: boolean; outcomeUnknown?: boolean } = {}) { super(message); this.name = 'PublicRequestError'; this.code = options.code || 'REQUEST_FAILED'; this.status = options.status || 0; this.retryable = Boolean(options.retryable); this.outcomeUnknown = Boolean(options.outcomeUnknown); }
}
const fallbacks: Record<string, string> = {
  rooms: 'Rooms could not be loaded right now. Please refresh the page or try again shortly.', room: 'This room could not be loaded right now. Please return to the rooms page and try again.', gallery: 'The gallery is temporarily unavailable. Please try again later.', bill: 'Your booking bill is temporarily unavailable. Your booking has not been cancelled.', payment: 'Online payment could not be started. Your booking has not been cancelled.', contact: 'Your message could not be sent. Please try again or contact the property by phone.', booking: 'Your booking could not be completed. Please review your details and try again.', multiBooking: 'Your multi-room booking could not be completed. Please review the room allocation and try again.',
};
const technical = /(sql|pdo|exception|stack|trace|\.php|database|query|syntax|fatal|warning|undefined|json)/i;
export function publicErrorMessage(error: unknown, context = 'rooms') {
  const value = error as { name?: string; code?: string; status?: number; message?: string };
  if (typeof navigator !== 'undefined' && !navigator.onLine) return 'You appear to be offline. Check your internet connection and try again.';
  if (value?.name === 'AbortError' || value?.code === 'TIMEOUT') return 'The request is taking longer than expected. Please try again.';
  const message = String(value?.message || '').trim();
  if ((value?.status || 0) >= 500 || technical.test(message)) return fallbacks[context] || 'This service is temporarily unavailable. Please try again.';
  return message || fallbacks[context] || 'Something went wrong. Please try again.';
}
