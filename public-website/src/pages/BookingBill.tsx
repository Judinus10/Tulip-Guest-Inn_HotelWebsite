import { useCallback, useEffect, useMemo, useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import {
  BedDouble,
  CalendarDays,
  Check,
  CheckCircle2,
  Loader2,
  Mail,
  Phone,
  RefreshCcw,
  UserRound,
  Users,
  XCircle,
} from 'lucide-react';
import { createCheckoutSession, fetchBookingPaymentStatus } from '../services/publicApi';
import type { BookingPaymentStatus } from '../services/bookingApi';

function statusLabel(status: string) {
  const normalized = status.toLowerCase();
  if (normalized === 'paid') return 'Payment Successful';
  if (normalized === 'failed') return 'Payment Failed';
  if (normalized === 'cancelled') return 'Payment Cancelled';
  return status || 'Payment Pending';
}

function formatAmount(amount: number, currency: string) {
  return `${currency || 'LKR'} ${Number(amount || 0).toLocaleString(undefined, {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  })}`;
}

function formatDate(value?: string) {
  if (!value) return '-';
  const date = new Date(`${value}T00:00:00`);
  if (Number.isNaN(date.getTime())) return value;
  return date.toLocaleDateString(undefined, { day: '2-digit', month: 'short', year: 'numeric' });
}

function formatDateTime(value?: string) {
  if (!value) return '-';
  const date = new Date(value.replace(' ', 'T'));
  if (Number.isNaN(date.getTime())) return value;
  return date.toLocaleString(undefined, {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}

function getNights(checkIn?: string, checkOut?: string) {
  if (!checkIn || !checkOut) return 1;
  const start = new Date(`${checkIn}T00:00:00`).getTime();
  const end = new Date(`${checkOut}T00:00:00`).getTime();
  if (!Number.isFinite(start) || !Number.isFinite(end) || end <= start) return 1;
  return Math.max(1, Math.round((end - start) / 86400000));
}

function bookingNumber(id?: number) {
  return `TGI-${String(id || 0).padStart(6, '0')}`;
}

export default function BookingBill() {
  const [searchParams] = useSearchParams();
  const bookingId = searchParams.get('booking_id') || '';
  const orderId = searchParams.get('order_id') || '';
  const token = searchParams.get('token') || '';

  const [booking, setBooking] = useState<BookingPaymentStatus | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [retrying, setRetrying] = useState(false);

  const loadStatus = useCallback(async () => {
    if (!bookingId || !orderId || !token) {
      setError('Missing booking payment details. Please contact reception.');
      setLoading(false);
      return;
    }

    try {
      const status = await fetchBookingPaymentStatus({ booking_id: bookingId, order_id: orderId, token });
      setBooking(status);
      setError('');
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Unable to load payment status.');
    } finally {
      setLoading(false);
    }
  }, [bookingId, orderId, token]);

  useEffect(() => {
    loadStatus();
  }, [loadStatus]);

  useEffect(() => {
    if (!booking || booking.payment_status !== 'Payment Pending' || booking.payment_method.toLowerCase() === 'cash') return;
    const interval = window.setInterval(loadStatus, 10000);
    return () => window.clearInterval(interval);
  }, [booking, loadStatus]);

  const handleRetryPayment = async () => {
    if (!booking) return;
    setRetrying(true);
    setError('');

    try {
      const checkout = await createCheckoutSession(booking.id);
      window.location.href = checkout.checkout_url;
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Unable to restart payment checkout.');
      setRetrying(false);
    }
  };

  const nights = useMemo(() => getNights(booking?.check_in_date, booking?.check_out_date), [booking]);
  const latestPayment = booking?.payment_history?.[booking.payment_history.length - 1];
  const paidAt = latestPayment?.updated_at || latestPayment?.created_at;
  const roomImage = booking?.room_main_image || 'https://images.unsplash.com/photo-1611892440506-42a832e657fb?w=1200&q=80';
  const isPaid = booking?.payment_status?.toLowerCase() === 'paid';
  const isCashPayment = booking?.payment_method?.toLowerCase() === 'cash';
  const heroImage = 'https://images.pexels.com/photos/261102/pexels-photo-261102.jpeg?auto=compress&cs=tinysrgb&w=1600';

  return (
    <main className="bg-[#faf8f4] text-[#14251f]">
      <section className="relative h-[300px] overflow-hidden md:h-[335px] lg:h-[360px]">
        <img src={heroImage} alt="Billing and Payment" className="absolute inset-0 h-full w-full object-cover" />
        <div className="absolute inset-0 bg-[#071923]/70" />
        <div className="absolute inset-x-0 bottom-0 h-28 bg-gradient-to-b from-transparent to-[#faf8f4]" />
        <div className="relative z-10 mx-auto flex h-full max-w-7xl flex-col items-center justify-center px-4 pt-16 text-center">
          <h1 className="font-serif text-[38px] font-light leading-none text-white drop-shadow md:text-[48px] lg:text-[54px]">
            Billing &amp; Payment
          </h1>
          <div className="mt-5 flex items-center gap-2 text-[11px] font-medium text-white/90">
            <Link to="/" className="hover:text-[#c99d53]">Home</Link>
            <span className="text-[#c99d53]">›</span>
            <span>Billing &amp; Payment</span>
          </div>
        </div>
      </section>

      <section className="relative -mt-16 pb-14 md:-mt-20 lg:-mt-24">
        <div className="mx-auto max-w-[1050px] px-4 sm:px-6 lg:px-8">
          {loading ? (
            <div className="rounded-[10px] border border-[#e7dfd3] bg-white py-16 text-center shadow-[0_18px_60px_rgba(20,30,25,0.10)]">
              <Loader2 size={20} className="mx-auto mb-3 animate-spin text-[#c99d53]" />
              <p className="text-[13px] text-[#53635c]">Loading payment status...</p>
            </div>
          ) : error ? (
            <div className="rounded-[10px] border border-[#e7dfd3] bg-white px-6 py-14 text-center shadow-[0_18px_60px_rgba(20,30,25,0.10)]">
              <XCircle size={34} className="mx-auto mb-4 text-red-500" />
              <h2 className="mb-3 font-serif text-3xl font-light text-[#14251f]">Payment Status Unavailable</h2>
              <p className="mb-6 text-[13px] text-[#64726d]">{error}</p>
              <Link to="/booking" className="inline-flex h-11 items-center justify-center rounded-[4px] bg-[#c99d53] px-8 text-[10px] font-bold uppercase tracking-[0.22em] text-white">
                Back to Booking
              </Link>
            </div>
          ) : booking ? (
            <div className="space-y-5">
              <div className="flex flex-col gap-4 rounded-[10px] border border-[#dfe9dc] bg-[#f4fff4] px-5 py-4 shadow-[0_18px_60px_rgba(20,30,25,0.08)] sm:flex-row sm:items-center sm:justify-between md:px-7">
                <div className="flex items-center gap-4">
                  <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-[#bfdabf] bg-white text-[#4b8f52]">
                    <Check size={19} />
                  </span>
                  <div>
                    <h2 className="font-sans text-[14px] font-bold text-[#28582f]">
                      {isCashPayment ? 'Booking Request Received' : isPaid ? 'Your booking is confirmed!' : 'Payment confirmation pending'}
                    </h2>
                    <p className="mt-1 text-[11px] text-[#51645a]">
                      {isCashPayment
                        ? 'Your booking is pending review. Payment will be collected at the property.'
                        : isPaid
                          ? 'Thank you for choosing Tulip Guest Inn. We look forward to welcoming you.'
                          : 'This page refreshes automatically while PayHere confirms your payment.'}
                    </p>
                  </div>
                </div>
                <div className="text-left sm:text-right">
                  <p className="text-[10px] text-[#4d5c56]">Booking ID</p>
                  <p className="mt-1 text-[12px] font-semibold text-[#14251f]">{bookingNumber(booking.id)}</p>
                </div>
              </div>

              <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
                <article className="rounded-[8px] border border-[#e2ddd5] bg-white p-5 shadow-[0_10px_35px_rgba(20,30,25,0.04)]">
                  <h3 className="mb-5 font-serif text-[21px] font-semibold text-[#14251f]">Booking Summary</h3>
                  <div className="grid gap-4 md:grid-cols-[260px_1fr] lg:block">
                    <img src={roomImage} alt={booking.room_name} className="h-[145px] w-full rounded-[5px] object-cover md:h-[150px] lg:h-[150px]" />
                    <div>
                      <h4 className="mt-4 font-serif text-[20px] font-semibold leading-tight text-[#14251f] md:mt-1 lg:mt-4">{booking.room_name}</h4>
                      <div className="mt-3 flex flex-wrap items-center gap-x-5 gap-y-2 text-[12px] text-[#42504a]">
                        <span className="inline-flex items-center gap-1.5"><Users size={13} className="text-[#b78335]" /> {booking.guests} Guests</span>
                        <span className="inline-flex items-center gap-1.5"><BedDouble size={13} className="text-[#b78335]" /> 1 Room</span>
                        <span className="inline-flex items-center gap-1.5"><CalendarDays size={13} className="text-[#b78335]" /> {nights} {nights === 1 ? 'Night' : 'Nights'}</span>
                      </div>
                      <div className="mt-5 grid gap-3 border-t border-[#eee8df] pt-4 text-[12px] sm:grid-cols-2 lg:grid-cols-1">
                        <div>
                          <p className="font-bold text-[#14251f]">Check-in</p>
                          <p className="mt-1 text-[#4f5d57]">{formatDate(booking.check_in_date)}</p>
                        </div>
                        <div>
                          <p className="font-bold text-[#14251f]">Check-out</p>
                          <p className="mt-1 text-[#4f5d57]">{formatDate(booking.check_out_date)}</p>
                        </div>
                      </div>
                    </div>
                  </div>
                </article>

                <article className="rounded-[8px] border border-[#e2ddd5] bg-white p-5 shadow-[0_10px_35px_rgba(20,30,25,0.04)]">
                  <h3 className="mb-6 font-serif text-[21px] font-semibold text-[#14251f]">Payment Summary</h3>
                  <div className="space-y-4 text-[12px]">
                    <div className="flex items-center justify-between gap-4">
                      <span className="text-[#4f5d57]">Room Charges ({nights} {nights === 1 ? 'Night' : 'Nights'})</span>
                      <span className="font-medium text-[#14251f]">{formatAmount(booking.amount, booking.currency)}</span>
                    </div>
                    <div className="mt-8 border-t border-[#eee8df] pt-5">
                      <div className="flex items-center justify-between gap-4">
                        <span className="font-serif text-[20px] font-bold text-[#14251f]">Total Amount</span>
                        <span className="font-serif text-[23px] font-bold text-[#b78335]">{formatAmount(booking.amount, booking.currency)}</span>
                      </div>
                    </div>
                    <div className="mt-7 rounded-[6px] border border-[#ead6b3] bg-[#fff7e9] p-4 text-[11px] leading-relaxed text-[#405049]">
                      <p className="mb-2 font-serif text-[17px] font-semibold text-[#14251f]">Important Notes</p>
                      <ul className="space-y-1.5 pl-4">
                        <li className="list-disc">Check-in is available from 1:00 PM.</li>
                        <li className="list-disc">Check-out is by 12:00 PM.</li>
                        <li className="list-disc">{isCashPayment ? 'Payment will be collected at the property.' : 'Please keep your payment receipt for verification at reception.'}</li>
                        <li className="list-disc">Contact support for booking changes before arrival.</li>
                      </ul>
                    </div>
                  </div>
                </article>

                <article className="rounded-[8px] border border-[#e2ddd5] bg-white p-5 shadow-[0_10px_35px_rgba(20,30,25,0.04)]">
                  <h3 className="mb-6 font-serif text-[21px] font-semibold text-[#14251f]">Payment Status</h3>
                  <div className="mb-5 flex justify-center text-center">
                    <div>
                      <CheckCircle2 size={34} className={isPaid ? 'mx-auto mb-2 text-[#2f8b47]' : 'mx-auto mb-2 text-[#b78335]'} />
                      <p className={isPaid ? 'font-sans text-[14px] font-bold text-[#2f8b47]' : 'font-sans text-[14px] font-bold text-[#b78335]'}>{statusLabel(booking.payment_status)}</p>
                      <p className="mt-1 text-[11px] text-[#4f5d57]">
                        {isCashPayment ? 'Payment will be collected on arrival' : paidAt ? `Paid on ${formatDateTime(paidAt)}` : 'Latest status from PayHere'}
                      </p>
                    </div>
                  </div>
                  <div className="rounded-[6px] border border-[#ead6b3] bg-[#fff7e9] p-5 text-[12px]">
                    <div className="space-y-4">
                      <div>
                        <p className="font-bold text-[#14251f]">Payment Method</p>
                        <p className="mt-1 text-[#35463f]">{isCashPayment ? 'Pay on Arrival' : booking.payment_method || 'PayHere'}</p>
                      </div>
                      {!isCashPayment && (
                        <>
                          <div>
                            <p className="font-bold text-[#14251f]">Transaction ID</p>
                            <p className="mt-1 break-all text-[#35463f]">{booking.payment_id || booking.order_id}</p>
                          </div>
                          <div>
                            <p className="font-bold text-[#14251f]">Payment Gateway</p>
                            <p className="mt-1 text-[#35463f]">PayHere</p>
                          </div>
                        </>
                      )}
                    </div>
                  </div>
                </article>
              </div>

              <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
                <article className="rounded-[8px] border border-[#e2ddd5] bg-white p-5 shadow-[0_10px_35px_rgba(20,30,25,0.04)] lg:col-span-2">
                  <h3 className="mb-5 font-serif text-[21px] font-semibold text-[#14251f]">{isCashPayment ? 'Booking Timeline' : 'Payment Timeline'}</h3>
                  <div className="space-y-0">
                    {(isCashPayment
                      ? [
                          ['Booking Placed', 'Your booking request was received successfully.', latestPayment?.created_at],
                          ['Pending Review', 'The property will review and confirm your booking request.', latestPayment?.created_at],
                          ['Pay on Arrival', 'Payment will be collected when you arrive at the property.', undefined],
                          [booking.booking_status || 'Pending', 'Contact the property if you need to change your booking.', undefined],
                        ]
                      : [
                          ['Booking Placed', 'Your room has been successfully reserved.', latestPayment?.created_at],
                          ['Payment Initiated', 'You were redirected to the secure payment gateway.', latestPayment?.created_at],
                          [statusLabel(booking.payment_status), isPaid ? 'Your payment has been processed successfully.' : 'Waiting for gateway confirmation.', paidAt],
                          [booking.booking_status || 'Booking Status', 'Your booking is confirmed. We look forward to your stay!', paidAt],
                        ]).map(([title, body, time], index) => (
                      <div key={`${title}-${index}`} className="grid grid-cols-[26px_120px_1fr] gap-3 text-[11px] sm:grid-cols-[26px_135px_1fr]">
                        <div className="flex flex-col items-center">
                          <span className={index === 2 && isPaid ? 'flex h-[18px] w-[18px] items-center justify-center rounded-full bg-[#4e9a55] text-white' : 'flex h-[18px] w-[18px] items-center justify-center rounded-full bg-[#183548] text-white'}>
                            {index === 2 && isPaid ? <Check size={11} /> : <span className="text-[8px] font-bold">{index + 1}</span>}
                          </span>
                          {index < 3 && <span className="h-9 w-px bg-[#d7ded8]" />}
                        </div>
                        <div className="pb-4">
                          <p className="font-bold leading-tight text-[#14251f]">{title}</p>
                          <p className="mt-1 text-[10px] text-[#6b7771]">{formatDateTime(time)}</p>
                        </div>
                        <p className="pb-4 leading-relaxed text-[#4f5d57]">{body}</p>
                      </div>
                    ))}
                  </div>
                </article>

                <article className="rounded-[8px] border border-[#e2ddd5] bg-white p-5 shadow-[0_10px_35px_rgba(20,30,25,0.04)]">
                  <h3 className="mb-3 font-serif text-[21px] font-semibold text-[#14251f]">Need Help?</h3>
                  <p className="mb-5 text-[12px] leading-relaxed text-[#4f5d57]">If you have any questions or need assistance, our support team is here to help.</p>
                  <div className="space-y-4 text-[12px] text-[#35463f]">
                    <p className="flex items-center gap-3"><Phone size={14} className="text-[#b78335]" /> +94 77 123 4567</p>
                    <p className="flex items-center gap-3"><Mail size={14} className="text-[#b78335]" /> info@tulipguestinn.com</p>
                    <p className="flex items-center gap-3"><UserRound size={14} className="text-[#b78335]" /> WhatsApp Chat</p>
                  </div>
                  <Link to="/contact" className="mt-6 flex h-11 w-full items-center justify-center rounded-[4px] bg-[#c99d53] text-[10px] font-bold uppercase tracking-[0.22em] text-white transition hover:bg-[#19352b]">
                    Contact Support
                  </Link>
                </article>
              </div>

              <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                {booking.can_retry_payment ? (
                  <button type="button" onClick={handleRetryPayment} disabled={retrying} className="flex h-11 items-center justify-center gap-2 rounded-[5px] border border-[#d8d2c8] bg-white text-[10px] font-bold uppercase tracking-[0.18em] text-[#14251f] transition hover:border-[#c99d53] hover:text-[#c99d53]">
                    <RefreshCcw size={13} /> {retrying ? 'Restarting' : 'Retry Payment'}
                  </button>
                ) : (
                  <a href={booking.invoice_download_url || '#'} className={booking.invoice_download_url ? 'flex h-11 items-center justify-center gap-2 rounded-[5px] border border-[#d8d2c8] bg-white text-[10px] font-bold uppercase tracking-[0.18em] text-[#14251f] transition hover:border-[#c99d53] hover:text-[#c99d53]' : 'pointer-events-none flex h-11 items-center justify-center gap-2 rounded-[5px] border border-[#d8d2c8] bg-white text-[10px] font-bold uppercase tracking-[0.18em] text-[#14251f] opacity-50'}>
                    {isCashPayment ? 'Download Booking Confirmation' : 'Download Receipt'}
                  </a>
                )}

                <Link to={booking.room_url || '/rooms'} className="flex h-11 items-center justify-center rounded-[5px] bg-[#c99d53] text-[10px] font-bold uppercase tracking-[0.2em] text-white transition hover:bg-[#19352b]">
                  Manage Booking
                </Link>
              </div>
            </div>
          ) : null}
        </div>
      </section>
    </main>
  );
}
