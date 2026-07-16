import { useEffect, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import { Calendar, Users, Home, Check, Phone, Mail } from 'lucide-react';
import PageHero from '../components/ui/PageHero';
import AnimatedSection from '../components/ui/AnimatedSection';
import { rooms as fallbackRooms } from '../data/rooms';
import type { Room } from '../data/rooms';
import { createCheckoutSession, fetchPublicRooms, submitBookingRequest } from '../services/publicApi';

function buildRoomTypeOptions(roomList: Room[]) {
  return [
    { value: '', label: 'Select Room Type' },
    ...roomList.map((r) => ({
      value: r.slug,
      label: `${r.name} — From ${r.currency ? `${r.currency} ` : '$'}${r.price}/night`,
    })),
  ];
}

interface BookingForm {
  checkIn: string;
  checkOut: string;
  guests: string;
  rooms: string;
  roomType: string;
  firstName: string;
  lastName: string;
  email: string;
  phone: string;
  nationality: string;
  specialRequests: string;
  isBookingForOther: boolean;
  stayingGuestName: string;
  stayingGuestEmail: string;
  stayingGuestPhone: string;
  stayingGuestNote: string;
}

export default function Booking() {
  const [searchParams] = useSearchParams();

  const readBookingParams = (): Pick<BookingForm, 'checkIn' | 'checkOut' | 'guests' | 'rooms' | 'roomType'> => ({
    checkIn: searchParams.get('checkin') || searchParams.get('checkIn') || '',
    checkOut: searchParams.get('checkout') || searchParams.get('checkOut') || '',
    guests: searchParams.get('guests') || '2',
    rooms: searchParams.get('rooms') || '1',
    roomType: searchParams.get('room') || '',
  });

  const [form, setForm] = useState<BookingForm>({
    ...readBookingParams(),
    firstName: '',
    lastName: '',
    email: '',
    phone: '',
    nationality: '',
    specialRequests: '',
    isBookingForOther: false,
    stayingGuestName: '',
    stayingGuestEmail: '',
    stayingGuestPhone: '',
    stayingGuestNote: '',
  });
  const [bookingRooms, setBookingRooms] = useState<Room[]>(fallbackRooms);
  const [submitError, setSubmitError] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);

  useEffect(() => {
    const params = readBookingParams();

    setForm((prev) => ({
      ...prev,
      checkIn: params.checkIn || prev.checkIn,
      checkOut: params.checkOut || prev.checkOut,
      guests: params.guests || prev.guests,
      rooms: params.rooms || prev.rooms,
      roomType: params.roomType || prev.roomType,
    }));
  }, [searchParams]);

  const handleChange = (
    e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>
  ) => {
    const target = e.target as HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement;
    const value = target instanceof HTMLInputElement && target.type === 'checkbox' ? target.checked : target.value;
    setForm((prev) => ({ ...prev, [target.name]: value }));
  };

  useEffect(() => {
    let mounted = true;

    const params: Record<string, string> = {};
    const hasDateFilter = Boolean(form.checkIn && form.checkOut);

    if (hasDateFilter) {
      params.check_in_date = form.checkIn;
      params.check_out_date = form.checkOut;
    }

    if (form.guests) {
      params.guests = form.guests;
    }

    fetchPublicRooms(params)
      .then((backendRooms) => {
        if (mounted) setBookingRooms(backendRooms);
      })
      .catch(() => {
        if (mounted) setBookingRooms(hasDateFilter ? [] : fallbackRooms);
      });

    return () => {
      mounted = false;
    };
  }, [form.checkIn, form.checkOut, form.guests]);

  const roomTypeOptions = buildRoomTypeOptions(bookingRooms);
  const selectedRoom = bookingRooms.find((r) => r.slug === form.roomType);

  const nights =
    form.checkIn && form.checkOut
      ? Math.max(
          0,
          Math.ceil(
            (new Date(form.checkOut).getTime() - new Date(form.checkIn).getTime()) /
              (1000 * 60 * 60 * 24)
          )
        )
      : 0;

  const total = selectedRoom ? selectedRoom.price * nights : 0;

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setSubmitError('');

    if (!selectedRoom) {
      setSubmitError('Please select a valid room type.');
      return;
    }

    setIsSubmitting(true);

    try {
      const booking = await submitBookingRequest({
        full_name: `${form.firstName} ${form.lastName}`.trim(),
        email: form.email,
        phone: form.phone,
        room_name: selectedRoom.name,
        check_in_date: form.checkIn,
        check_out_date: form.checkOut,
        guests: Number(form.guests),
        is_booking_for_other: form.isBookingForOther,
        staying_guest_name: form.isBookingForOther ? form.stayingGuestName : '',
        staying_guest_email: form.isBookingForOther ? form.stayingGuestEmail : '',
        staying_guest_phone: form.isBookingForOther ? form.stayingGuestPhone : '',
        staying_guest_note: form.isBookingForOther ? form.stayingGuestNote : '',
        message: [
          form.specialRequests,
          form.nationality ? `Nationality: ${form.nationality}` : '',
          `Rooms requested: ${form.rooms}`,
        ]
          .filter(Boolean)
          .join('\n'),
      });

      const bookingId = booking.booking_id || booking.inquiry_id;
      if (!bookingId) {
        throw new Error('Booking was saved but the payment checkout could not start. Missing booking ID.');
      }

      const checkout = await createCheckoutSession(bookingId);
      if (!checkout.checkout_url) {
        throw new Error('Payment checkout could not start. Missing PayHere checkout URL.');
      }

      window.location.href = checkout.checkout_url;
    } catch (error) {
      setSubmitError(error instanceof Error ? error.message : 'Unable to submit booking request.');
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <main>
      <PageHero
        title="Book Your Stay"
        subtitle="Reserve your room directly for the best rate and personalised service."
        image="https://images.pexels.com/photos/271624/pexels-photo-271624.jpeg?auto=compress&cs=tinysrgb&w=1600"
        breadcrumb="Reservation"
      />

      <section className="section-padding bg-background">
        <div className="container-custom">
          {false ? (
            <div className="max-w-2xl mx-auto text-center py-20">
              <div className="w-20 h-20 bg-deep-green flex items-center justify-center mx-auto mb-6 shadow-luxury">
                <Check size={32} className="text-white" />
              </div>
              <h2 className="font-serif text-4xl font-light text-dark mb-4">
                Booking Request Received
              </h2>
              <div className="w-12 h-[1px] bg-gold mx-auto mb-6" />
              <p className="text-sm text-gray-500 leading-relaxed mb-4">
                Thank you, {form.firstName}. Your booking request has been received. Our team will review it and send a confirmation to{' '}
                <strong>{form.email}</strong> within 2–4 hours.
              </p>
              <p className="text-sm text-gray-400 mb-8">
                For immediate assistance, please call us at{' '}
                <a href="tel:+94212261186" className="text-gold hover:underline">
                  0212 261 186
                </a>
              </p>
              <div className="flex flex-wrap items-center justify-center gap-4">
                <a href="tel:+94212261186" className="btn-primary">
                  <Phone size={14} />
                  Call Reception
                </a>
                <a href="mailto:info@tulipguestinn.com" className="btn-outline">
                  <Mail size={14} />
                  Email Us
                </a>
              </div>
            </div>
          ) : (
            <div className="grid grid-cols-1 lg:grid-cols-3 gap-12">
              {/* Booking Form */}
              <form onSubmit={handleSubmit} className="lg:col-span-2 space-y-10">
                {/* Stay Details */}
                <AnimatedSection>
                  <div className="bg-white border border-border p-8">
                    <h3 className="font-serif text-2xl font-light text-dark mb-5 flex items-center gap-3">
                      <Calendar size={18} className="text-gold" />
                      Stay Details
                    </h3>
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-5">
                      <div>
                        <label className="block text-[9px] tracking-[0.2em] uppercase text-gray-400 mb-2">Check In *</label>
                        <input
                          type="date"
                          name="checkIn"
                          value={form.checkIn}
                          onChange={handleChange}
                          required
                          min={new Date().toISOString().split('T')[0]}
                          className="w-full border border-border px-5 py-3.5 text-sm outline-none focus:border-gold transition-colors duration-200 bg-background"
                        />
                      </div>
                      <div>
                        <label className="block text-[9px] tracking-[0.2em] uppercase text-gray-400 mb-2">Check Out *</label>
                        <input
                          type="date"
                          name="checkOut"
                          value={form.checkOut}
                          onChange={handleChange}
                          required
                          min={form.checkIn || new Date().toISOString().split('T')[0]}
                          className="w-full border border-border px-5 py-3.5 text-sm outline-none focus:border-gold transition-colors duration-200 bg-background"
                        />
                      </div>
                      <div>
                        <label className="block text-[9px] tracking-[0.2em] uppercase text-gray-400 mb-2">
                          <Users size={10} className="inline mr-1" />
                          Guests *
                        </label>
                        <select
                          name="guests"
                          value={form.guests}
                          onChange={handleChange}
                          className="w-full border border-border px-5 py-3.5 text-sm outline-none focus:border-gold transition-colors duration-200 bg-background"
                        >
                          {[1, 2, 3, 4, 5, 6].map((n) => (
                            <option key={n} value={n}>{n} {n === 1 ? 'Guest' : 'Guests'}</option>
                          ))}
                        </select>
                      </div>
                      <div>
                        <label className="block text-[9px] tracking-[0.2em] uppercase text-gray-400 mb-2">
                          <Home size={10} className="inline mr-1" />
                          Rooms *
                        </label>
                        <select
                          name="rooms"
                          value={form.rooms}
                          onChange={handleChange}
                          className="w-full border border-border px-5 py-3.5 text-sm outline-none focus:border-gold transition-colors duration-200 bg-background"
                        >
                          {[1, 2, 3, 4].map((n) => (
                            <option key={n} value={n}>{n} {n === 1 ? 'Room' : 'Rooms'}</option>
                          ))}
                        </select>
                      </div>
                      <div className="md:col-span-2">
                        <label className="block text-[9px] tracking-[0.2em] uppercase text-gray-400 mb-2">Room Type</label>
                        <select
                          name="roomType"
                          value={form.roomType}
                          onChange={handleChange}
                          className="w-full border border-border px-5 py-3.5 text-sm outline-none focus:border-gold transition-colors duration-200 bg-background"
                        >
                          {roomTypeOptions.map((opt) => (
                            <option key={opt.value} value={opt.value}>{opt.label}</option>
                          ))}
                        </select>
                      </div>
                    </div>
                  </div>
                </AnimatedSection>

                {/* Guest Information */}
                <AnimatedSection delay={0.1}>
                  <div className="bg-white border border-border p-8">
                    <h3 className="font-serif text-2xl font-light text-dark mb-5 flex items-center gap-3">
                      <Users size={18} className="text-gold" />
                      Guest Information
                    </h3>
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-5">
                      <div>
                        <label className="block text-[9px] tracking-[0.2em] uppercase text-gray-400 mb-2">First Name *</label>
                        <input
                          type="text"
                          name="firstName"
                          value={form.firstName}
                          onChange={handleChange}
                          required
                          placeholder="First name"
                          className="w-full border border-border px-5 py-3.5 text-sm outline-none focus:border-gold transition-colors duration-200 bg-background placeholder-gray-400"
                        />
                      </div>
                      <div>
                        <label className="block text-[9px] tracking-[0.2em] uppercase text-gray-400 mb-2">Last Name *</label>
                        <input
                          type="text"
                          name="lastName"
                          value={form.lastName}
                          onChange={handleChange}
                          required
                          placeholder="Last name"
                          className="w-full border border-border px-5 py-3.5 text-sm outline-none focus:border-gold transition-colors duration-200 bg-background placeholder-gray-400"
                        />
                      </div>
                      <div>
                        <label className="block text-[9px] tracking-[0.2em] uppercase text-gray-400 mb-2">Email Address *</label>
                        <input
                          type="email"
                          name="email"
                          value={form.email}
                          onChange={handleChange}
                          required
                          placeholder="your@email.com"
                          className="w-full border border-border px-5 py-3.5 text-sm outline-none focus:border-gold transition-colors duration-200 bg-background placeholder-gray-400"
                        />
                      </div>
                      <div>
                        <label className="block text-[9px] tracking-[0.2em] uppercase text-gray-400 mb-2">Phone Number *</label>
                        <input
                          type="tel"
                          name="phone"
                          value={form.phone}
                          onChange={handleChange}
                          required
                          placeholder="+xx xxx xxx xxxx"
                          className="w-full border border-border px-5 py-3.5 text-sm outline-none focus:border-gold transition-colors duration-200 bg-background placeholder-gray-400"
                        />
                      </div>
                      <div className="md:col-span-2">
                        <label className="flex items-center gap-3 text-sm text-dark">
                          <input
                            type="checkbox"
                            name="isBookingForOther"
                            checked={form.isBookingForOther}
                            onChange={handleChange}
                            className="h-4 w-4 accent-gold"
                          />
                          I am booking for another guest
                        </label>
                      </div>

                      {form.isBookingForOther && (
                        <>
                          <div>
                            <label className="block text-[9px] tracking-[0.2em] uppercase text-gray-400 mb-2">Staying Guest Name *</label>
                            <input
                              type="text"
                              name="stayingGuestName"
                              value={form.stayingGuestName}
                              onChange={handleChange}
                              required={form.isBookingForOther}
                              placeholder="Guest full name"
                              className="w-full border border-border px-5 py-3.5 text-sm outline-none focus:border-gold transition-colors duration-200 bg-background placeholder-gray-400"
                            />
                          </div>
                          <div>
                            <label className="block text-[9px] tracking-[0.2em] uppercase text-gray-400 mb-2">Staying Guest Phone (Optional)</label>
                            <input
                              type="tel"
                              name="stayingGuestPhone"
                              value={form.stayingGuestPhone}
                              onChange={handleChange}
                              placeholder="Guest phone number"
                              className="w-full border border-border px-5 py-3.5 text-sm outline-none focus:border-gold transition-colors duration-200 bg-background placeholder-gray-400"
                            />
                          </div>
                          <div className="md:col-span-2">
                            <label className="block text-[9px] tracking-[0.2em] uppercase text-gray-400 mb-2">Staying Guest Email (Optional)</label>
                            <input
                              type="email"
                              name="stayingGuestEmail"
                              value={form.stayingGuestEmail}
                              onChange={handleChange}
                              placeholder="guest@email.com"
                              className="w-full border border-border px-5 py-3.5 text-sm outline-none focus:border-gold transition-colors duration-200 bg-background placeholder-gray-400"
                            />
                            <p className="mt-2 text-[10px] leading-relaxed text-gray-400">If provided, booking updates will also be emailed to the staying guest.</p>
                          </div>
                          <div className="md:col-span-2">
                            <label className="block text-[9px] tracking-[0.2em] uppercase text-gray-400 mb-2">Staying Guest Note</label>
                            <textarea
                              name="stayingGuestNote"
                              value={form.stayingGuestNote}
                              onChange={handleChange}
                              rows={3}
                              placeholder="Any note about the staying guest..."
                              className="w-full border border-border px-5 py-3.5 text-sm outline-none focus:border-gold transition-colors duration-200 bg-background placeholder-gray-400 resize-none"
                            />
                          </div>
                        </>
                      )}

                      <div className="md:col-span-2">
                        <label className="block text-[9px] tracking-[0.2em] uppercase text-gray-400 mb-2">Nationality</label>
                        <input
                          type="text"
                          name="nationality"
                          value={form.nationality}
                          onChange={handleChange}
                          placeholder="Your nationality"
                          className="w-full border border-border px-5 py-3.5 text-sm outline-none focus:border-gold transition-colors duration-200 bg-background placeholder-gray-400"
                        />
                      </div>
                      <div className="md:col-span-2">
                        <label className="block text-[9px] tracking-[0.2em] uppercase text-gray-400 mb-2">Special Requests</label>
                        <textarea
                          name="specialRequests"
                          value={form.specialRequests}
                          onChange={handleChange}
                          rows={4}
                          placeholder="Any special requirements, dietary needs or preferences..."
                          className="w-full border border-border px-5 py-3.5 text-sm outline-none focus:border-gold transition-colors duration-200 bg-background placeholder-gray-400 resize-none"
                        />
                      </div>
                    </div>
                  </div>
                </AnimatedSection>

                {submitError && (
                  <div className="border border-red-200 bg-red-50 px-5 py-4 text-sm text-red-700">
                    {submitError}
                  </div>
                )}

                <button type="submit" className="btn-primary w-full justify-center py-4 text-xs" disabled={isSubmitting}>
                  {isSubmitting ? 'Preparing Payment...' : 'Continue to Payment'}
                </button>
              </form>

              {/* Price Summary */}
              <AnimatedSection direction="right">
                <div className="space-y-6 sticky top-28">
                  {/* Summary Card */}
                  <div className="bg-white border border-border shadow-luxury p-8">
                    <p className="text-[9px] tracking-[0.25em] uppercase text-gold font-medium mb-4">
                      Price Summary
                    </p>
                    <h3 className="font-serif text-2xl font-light text-dark mb-5">Booking Overview</h3>
                    <div className="w-8 h-[1px] bg-gold mb-6" />

                    <div className="space-y-4 mb-6">
                      <div className="flex justify-between text-sm">
                        <span className="text-gray-500">Room Type</span>
                        <span className="text-dark font-medium">
                          {selectedRoom ? selectedRoom.name : '—'}
                        </span>
                      </div>
                      <div className="flex justify-between text-sm">
                        <span className="text-gray-500">Rate Per Night</span>
                        <span className="text-dark font-medium">
                          {selectedRoom ? `${selectedRoom.currency ? `${selectedRoom.currency} ` : '$'}${selectedRoom.price}` : '—'}
                        </span>
                      </div>
                      <div className="flex justify-between text-sm">
                        <span className="text-gray-500">Nights</span>
                        <span className="text-dark font-medium">{nights || '—'}</span>
                      </div>
                      <div className="flex justify-between text-sm">
                        <span className="text-gray-500">Rooms</span>
                        <span className="text-dark font-medium">{form.rooms}</span>
                      </div>
                      <div className="flex justify-between text-sm">
                        <span className="text-gray-500">Guests</span>
                        <span className="text-dark font-medium">{form.guests}</span>
                      </div>
                    </div>

                    <div className="border-t border-border pt-5">
                      <div className="flex justify-between items-baseline">
                        <span className="text-[9px] tracking-[0.2em] uppercase text-gray-400">Estimated Total</span>
                        <span className="font-serif text-3xl font-light text-dark">
                          {total > 0 ? `${selectedRoom?.currency ? `${selectedRoom.currency} ` : '$'}${total}` : '—'}
                        </span>
                      </div>
                      {total > 0 && (
                        <p className="text-[10px] text-gray-400 mt-1 text-right">
                          Final amount is verified by the backend before PayHere checkout
                        </p>
                      )}
                    </div>
                  </div>

                  {/* Why Book Direct */}
                  <div className="bg-deep-green p-7">
                    <p className="text-[9px] tracking-[0.25em] uppercase text-gold font-medium mb-4">
                      Book Direct Benefits
                    </p>
                    <div className="space-y-3">
                      {[
                        'Best rate guaranteed',
                        'No booking fees',
                        'Free cancellation',
                        'Personalised service',
                        'Secure online payment',
                      ].map((b) => (
                        <div key={b} className="flex items-center gap-3">
                          <Check size={12} className="text-gold shrink-0" />
                          <span className="text-gray-300 text-sm">{b}</span>
                        </div>
                      ))}
                    </div>
                  </div>

                  {/* Contact */}
                  <div className="border border-border p-7">
                    <p className="text-[9px] tracking-[0.2em] uppercase text-gray-400 mb-4">Need Help?</p>
                    <a
                      href="tel:+94212261186"
                      className="flex items-center gap-3 text-dark hover:text-gold transition-colors duration-200 mb-3"
                    >
                      <Phone size={14} className="text-gold" />
                      <span className="text-sm font-medium">0212 261 186</span>
                    </a>
                    <a
                      href="mailto:info@tulipguestinn.com"
                      className="flex items-center gap-3 text-dark hover:text-gold transition-colors duration-200"
                    >
                      <Mail size={14} className="text-gold" />
                      <span className="text-sm">info@tulipguestinn.com</span>
                    </a>
                  </div>
                </div>
              </AnimatedSection>
            </div>
          )}
        </div>
      </section>
    </main>
  );
}
