import { getApiBaseUrl } from './config';
import { PublicRequestError, publicErrorMessage } from './publicErrors';

export type ApiResponse<T> = {
  success?: boolean;
  message?: string;
  error_code?: string;
  support_reference?: string;
  data?: T;
  rooms?: T;
  room?: T;
};

export async function requestJson<T>(path: string, options: RequestInit = {}): Promise<T> {
  const context = path.includes('submit-multi') ? 'multiBooking' : path.includes('submit-booking') ? 'booking' : path.includes('/payments/') ? 'payment' : path.includes('gallery') ? 'gallery' : path.includes('contact') ? 'contact' : path.includes('room') ? 'rooms' : 'rooms';
  const controller = new AbortController();
  const timeout = window.setTimeout(() => controller.abort(), 20000);
  let response: Response;
  try {
    response = await fetch(`${getApiBaseUrl()}${path}`, {
      ...options,
      signal: controller.signal,
      headers: { Accept: 'application/json', ...(options.body ? { 'Content-Type': 'application/json' } : {}), ...options.headers },
    });
  } catch (error) {
    const wrapped = new PublicRequestError('', { code: error instanceof DOMException && error.name === 'AbortError' ? 'TIMEOUT' : 'NETWORK_ERROR', retryable: true, outcomeUnknown: ['booking', 'multiBooking', 'contact'].includes(context) });
    wrapped.message = publicErrorMessage(wrapped, context);
    throw wrapped;
  } finally {
    window.clearTimeout(timeout);
  }

  const payload = (await response.json().catch(() => null)) as ApiResponse<T> | null;

  if (!response.ok || !payload || payload.success === false) {
    const wrapped = new PublicRequestError(payload?.message || '', {
      code: payload?.error_code || 'REQUEST_FAILED',
      status: response.status,
      retryable: response.status >= 500,
    });
    wrapped.message = publicErrorMessage(wrapped, context);
    throw wrapped;
  }

  if (payload.data !== undefined) return payload.data as T;

  // Booking endpoints return their result fields at the top level. A
  // multi-room result also contains a `rooms` array, but that array is only
  // one part of the response. Returning it here would discard booking_id,
  // order_id and bill_url after the booking has already been committed.
  const responseEnvelope = payload as ApiResponse<T> & Record<string, unknown>;
  if (
    'booking_id' in responseEnvelope ||
    'booking_no' in responseEnvelope ||
    'bill_url' in responseEnvelope ||
    'checkout_url' in responseEnvelope
  ) {
    return responseEnvelope as unknown as T;
  }

  return (payload.rooms ?? payload.room ?? payload) as T;
}
