import { useCallback, useEffect, useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { Check, CreditCard, Download, Loader2, Phone, RefreshCcw, XCircle } from 'lucide-react';
import AnimatedSection from '../components/ui/AnimatedSection';
import PageHero from '../components/ui/PageHero';
import { createCheckoutSession, fetchBookingPaymentStatus } from '../services/publicApi';
import type { BookingPaymentStatus } from '../services/bookingApi';

function statusClass(status: string) {
  const normalized = status.toLowerCase();
  if (normalized === 'paid') return 'text-green-700 bg-green-50 border-green-200';
  if (['failed', 'cancelled', 'refunded'].includes(normalized)) return 'text-red-700 bg-red-50 border-red-200';
  return 'text-amber-700 bg-amber-50 border-amber-200';
}

function formatAmount(amount: number, currency: string) {
  return `${currency || 'LKR'} ${Number(amount || 0).toLocaleString(undefined, {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  })}`;
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
    if (!booking || booking.payment_status !== 'Payment Pending') return;

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

  return (
    <main>
      <PageHero
        title="Booking Bill"
        subtitle="Review your booking payment status and invoice details."
        image="https://images.pexels.com/photos/261102/pexels-photo-261102.jpeg?auto=compress&cs=tinysrgb&w=1600"
        breadcrumb="Payment"
      />

      <section className="section-padding bg-background">
        <div className="container-custom">
          <AnimatedSection>
            <div className="max-w-4xl mx-auto bg-white border border-border shadow-luxury p-8 md:p-10">
              {loading ? (
                <div className="flex items-center justify-center gap-3 py-16 text-gray-500">
                  <Loader2 size={20} className="animate-spin" />
                  <span className="text-sm">Loading payment status...</span>
                </div>
              ) : error ? (
                <div className="text-center py-14">
                  <XCircle size={36} className="text-red-500 mx-auto mb-4" />
                  <h2 className="font-serif text-3xl font-light text-dark mb-3">Payment Status Unavailable</h2>
                  <p className="text-sm text-gray-500 mb-6">{error}</p>
                  <Link to="/booking" className="btn-primary">Back to Booking</Link>
                </div>
              ) : booking ? (
                <div className="space-y-8">
                  <div className="flex flex-col md:flex-row md:items-start md:justify-between gap-5 border-b border-border pb-6">
                    <div>
                      <p className="text-[9px] tracking-[0.25em] uppercase text-gold font-medium mb-3">Booking Reference</p>
                      <h2 className="font-serif text-3xl font-light text-dark">BK-{String(booking.id).padStart(5, '0')}</h2>
                      <p className="text-sm text-gray-500 mt-2">Order ID: {booking.order_id}</p>
                    </div>
                    <div className={`border px-4 py-2 text-xs tracking-[0.18em] uppercase ${statusClass(booking.payment_status)}`}>
                      {booking.payment_status}
                    </div>
                  </div>

                  {booking.payment_status === 'Paid' ? (
                    <div className="flex items-start gap-4 border border-green-200 bg-green-50 p-5">
                      <Check size={20} className="text-green-700 shrink-0 mt-0.5" />
                      <div>
                        <h3 className="text-sm font-medium text-green-800 mb-1">Payment verified</h3>
                        <p className="text-sm text-green-700">Your booking is confirmed. Confirmation and invoice emails will be sent by the email queue.</p>
                      </div>
                    </div>
                  ) : (
                    <div className="flex items-start gap-4 border border-amber-200 bg-amber-50 p-5">
                      <CreditCard size={20} className="text-amber-700 shrink-0 mt-0.5" />
                      <div>
                        <h3 className="text-sm font-medium text-amber-800 mb-1">Payment not completed yet</h3>
                        <p className="text-sm text-amber-700">This page refreshes automatically while PayHere confirms the result.</p>
                      </div>
                    </div>
                  )}

                  <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div className="border border-border p-6">
                      <p className="text-[9px] tracking-[0.25em] uppercase text-gray-400 mb-4">Guest Details</p>
                      <div className="space-y-3 text-sm">
                        <div className="flex justify-between gap-4"><span className="text-gray-500">Name</span><span className="text-dark text-right">{booking.full_name}</span></div>
                        <div className="flex justify-between gap-4"><span className="text-gray-500">Email</span><span className="text-dark text-right">{booking.email}</span></div>
                        <div className="flex justify-between gap-4"><span className="text-gray-500">Phone</span><span className="text-dark text-right">{booking.phone}</span></div>
                      </div>
                    </div>

                    <div className="border border-border p-6">
                      <p className="text-[9px] tracking-[0.25em] uppercase text-gray-400 mb-4">Stay Details</p>
                      <div className="space-y-3 text-sm">
                        <div className="flex justify-between gap-4"><span className="text-gray-500">Room</span><span className="text-dark text-right">{booking.room_name}</span></div>
                        <div className="flex justify-between gap-4"><span className="text-gray-500">Check In</span><span className="text-dark text-right">{booking.check_in_date}</span></div>
                        <div className="flex justify-between gap-4"><span className="text-gray-500">Check Out</span><span className="text-dark text-right">{booking.check_out_date}</span></div>
                        <div className="flex justify-between gap-4"><span className="text-gray-500">Guests</span><span className="text-dark text-right">{booking.guests}</span></div>
                      </div>
                    </div>
                  </div>

                  <div className="border-t border-border pt-6 flex flex-col md:flex-row md:items-center md:justify-between gap-5">
                    <div>
                      <p className="text-[9px] tracking-[0.25em] uppercase text-gray-400 mb-2">Amount</p>
                      <p className="font-serif text-4xl font-light text-dark">{formatAmount(booking.amount, booking.currency)}</p>
                      {booking.invoice_number && <p className="text-xs text-gray-400 mt-1">Invoice: {booking.invoice_number}</p>}
                    </div>
                    <div className="flex flex-wrap gap-3">
                      {booking.invoice_download_url && (
                        <a href={booking.invoice_download_url} className="btn-primary">
                          <Download size={14} />
                          Download Invoice
                        </a>
                      )}
                      {booking.can_retry_payment && (
                        <button type="button" onClick={handleRetryPayment} disabled={retrying} className="btn-primary">
                          <RefreshCcw size={14} />
                          {retrying ? 'Restarting...' : 'Retry Payment'}
                        </button>
                      )}
                      <a href="tel:+94212261186" className="btn-outline">
                        <Phone size={14} />
                        Call Reception
                      </a>
                    </div>
                  </div>
                </div>
              ) : null}
            </div>
          </AnimatedSection>
        </div>
      </section>
    </main>
  );
}
