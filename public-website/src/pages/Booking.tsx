import { useGuestAgePolicy } from '../services/guestAgePolicy';
import GuestPartyFields from '../components/ui/GuestPartyFields';
import { readParty, writeParty, partyError, partyTotal, partyRequest, bookingRoomOptions, roomGuestLabel } from '../services/guestOccupancy';
import { useContactPhones } from '../hooks/useContactPhones';
import { useEffect, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import { Calendar, Users, Home, Check, Phone, Mail } from 'lucide-react';
import PageHero from '../components/ui/PageHero';
import AnimatedSection from '../components/ui/AnimatedSection';
import type { Room } from '../data/rooms';
import { createCheckoutSession, fetchPublicFeatures, fetchPublicRooms, submitBookingRequest } from '../services/publicApi';
import bannerBooking from '../assets/images/banners/banner-booking.jpg';
import { useToast } from '../components/ui/ToastProvider';
import { formatMoney } from '../services/formatters';
import { PublicRequestError } from '../services/publicErrors';

function buildRoomTypeOptions(roomList: Room[]) {
  return [
    { value: '', label: 'Select Room Type' },
    ...roomList.map((r) => ({
      value: r.slug,
      label: `${r.name} — From ${formatMoney(r.currency, r.price)}/night`,
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
  paymentMethod: 'Cash' | 'PayHere';
}

export default function Booking() {
  const { maxChildAge } = useGuestAgePolicy();
  const toast = useToast();
  const phones = useContactPhones();
  const [searchParams] = useSearchParams();
  const [party, setParty] = useState(() => readParty(searchParams));

  const readBookingParams = (): Pick<BookingForm, 'checkIn' | 'checkOut' | 'guests' | 'rooms' | 'roomType'> => ({
    checkIn: searchParams.get('checkin') || searchParams.get('checkIn') || '',
    checkOut: searchParams.get('checkout') || searchParams.get('checkOut') || '',
    guests: String(partyTotal(readParty(searchParams))),
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
    paymentMethod: 'Cash',
  });
  const [bookingRooms, setBookingRooms] = useState<Room[]>([]);
  const [multiRoomCount, setMultiRoomCount] = useState(0);
  const [roomSelectionNotice, setRoomSelectionNotice] = useState('');
  const [submitError, setSubmitError] = useState('');
  const availabilityKey = `${form.checkIn}|${form.checkOut}|${JSON.stringify(party)}|${maxChildAge}`;
  const [checkedAvailabilityKey, setCheckedAvailabilityKey] = useState('');
  const [availabilityAttempt, setAvailabilityAttempt] = useState(0);
  const [availabilityError, setAvailabilityError] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [onlinePaymentEnabled, setOnlinePaymentEnabled] = useState(false);
  const [arrivalEnabled, setArrivalEnabled] = useState(false);

  useEffect(() => {
    let mounted = true;
    fetchPublicFeatures()
      .then((features) => {
        if (!mounted) return;
        setOnlinePaymentEnabled(Boolean(features.online_payment_enabled));
        setArrivalEnabled(Boolean(features.pay_on_arrival_enabled));
        setForm((current) => ({ ...current, paymentMethod: features.pay_on_arrival_enabled ? 'Cash' : 'PayHere' }));
      })
      .catch(() => {
        if (mounted) { setOnlinePaymentEnabled(false); setArrivalEnabled(false); }
      });
    return () => { mounted = false; };
  }, []);

  const selectPaymentMethod = (method: 'Cash' | 'PayHere') => {
    if (method === 'Cash' && !arrivalEnabled) { toast.warning('Pay on Arrival is unavailable.'); return; }
    if (method === 'PayHere' && !onlinePaymentEnabled) {
      setForm((current) => ({ ...current, paymentMethod: 'Cash' }));
      toast.warning('Online payment is not available at the moment. Please use Pay on Arrival.');
      return;
    }
    setForm((current) => ({ ...current, paymentMethod: method }));
  };

  useEffect(() => {
    const params = readBookingParams();
    setParty(readParty(searchParams));

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
    if (target.name==='rooms' && Number(value)>1) { window.location.href=`/multi-room-booking?${writeParty(new URLSearchParams({checkin:form.checkIn,checkout:form.checkOut,rooms:String(value)}),party).toString()}`; return; }
    setForm((prev) => ({ ...prev, [target.name]: value }));
  };

  useEffect(() => {
    let mounted = true;

    const params: Record<string, string> = {};
    setBookingRooms([]);
    setMultiRoomCount(0);
    setRoomSelectionNotice('');
    setSubmitError('');
    setAvailabilityError('');
    setCheckedAvailabilityKey('');
    if (!form.checkIn || !form.checkOut || form.checkOut <= form.checkIn) {
      setCheckedAvailabilityKey('');
      return () => { mounted = false; };
    }
    const guestError = partyError(party, false, maxChildAge);
    if (guestError) {
      setAvailabilityError(guestError);
      return () => { mounted = false; };
    }
    // Fetch date availability without a party filter so alternative single rooms
    // and multi-room combinations are checked against the same available stock.
    params.check_in_date = form.checkIn;
    params.check_out_date = form.checkOut;
    fetchPublicRooms(params)
      .then((backendRooms) => {
        if (!mounted) return;
        const options = bookingRoomOptions(backendRooms, party, form.roomType);
        setBookingRooms(options.matches);
        setMultiRoomCount(options.multiRoomCount);
        setCheckedAvailabilityKey(availabilityKey);
        if (options.selected) {
          setForm(current => ({ ...current, roomType: options.selected!.slug }));
          if (form.roomType && options.selected.slug !== form.roomType) {
            const message = `The previous room cannot accommodate this group for these dates. ${options.selected.name} has been selected instead. Review the room and updated price before continuing.`;
            setRoomSelectionNotice(message);
            toast.warning(message);
          }
        } else {
          setForm(current => ({ ...current, roomType: '' }));
          toast.warning(options.multiRoomCount >= 2
            ? 'No single room can accommodate this group. Choose multiple rooms before entering guest details.'
            : 'No available room combination can accommodate this group. Change your dates or guest count.');
        }
      })
      .catch(() => {
        if (mounted) { setBookingRooms([]); setAvailabilityError('Unable to check room availability. Change the search or retry.'); setCheckedAvailabilityKey(availabilityKey); }
      });

    return () => {
      mounted = false;
    };
  }, [form.checkIn, form.checkOut, form.guests, JSON.stringify(party), maxChildAge, availabilityAttempt]);

  const validStayDates = Boolean(form.checkIn && form.checkOut && form.checkOut > form.checkIn);
  const availabilityReady = validStayDates && checkedAvailabilityKey === availabilityKey && !availabilityError;
  const roomTypeOptions = buildRoomTypeOptions(availabilityReady ? bookingRooms : []);
  const selectedRoom = (availabilityReady ? bookingRooms : []).find((r) => r.slug === form.roomType);

  const canEnterGuestDetails = availabilityReady && Boolean(selectedRoom);
  const multipleRoomPath = `/multi-room-booking?${writeParty(new URLSearchParams({checkin:form.checkIn,checkout:form.checkOut,rooms:String(multiRoomCount)}),party).toString()}`;

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

    if (partyError(party, false, maxChildAge)) { setSubmitError(partyError(party, false, maxChildAge)); toast.warning(partyError(party, false, maxChildAge)); return; }
    if (!canEnterGuestDetails) {
      toast.warning(availabilityError || (multiRoomCount >= 2 ? 'Choose multiple rooms before entering guest details.' : 'Choose an available room for your dates and guest count first.'));
      return;
    }
    const emailIsValid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email.trim());
    const phoneDigits = form.phone.replace(/\D/g, '');
    const validationMessage = !form.firstName.trim() || !form.lastName.trim()
      ? 'Please enter both your first name and last name.'
      : !emailIsValid
        ? 'Please enter a valid email address, for example name@example.com.'
        : phoneDigits.length < 7
          ? 'Please enter a valid phone number including the country code.'
          : !form.checkIn || !form.checkOut
            ? 'Please select both check-in and check-out dates.'
            : form.checkOut <= form.checkIn
              ? 'Check-out must be after the check-in date.'
              : Number(form.guests) < 1
                ? 'Please select at least one guest.'
                : '';

    if (validationMessage) {
      setSubmitError(validationMessage);
      toast.warning(validationMessage);
      return;
    }

    if (Number(form.rooms)>1) { window.location.href=`/multi-room-booking?${writeParty(new URLSearchParams({checkin:form.checkIn,checkout:form.checkOut,rooms:form.rooms}),party).toString()}`; return; }
    if (!selectedRoom) {
      setSubmitError(availabilityError || 'Choose an available room for these dates and guest count.');
      toast.warning(availabilityError || 'Choose an available room for these dates and guest count.');
      return;
    }

    if (form.paymentMethod === 'Cash' && !arrivalEnabled) { toast.warning('Pay on Arrival is unavailable.'); return; }
    if (form.paymentMethod === 'PayHere' && !onlinePaymentEnabled) {
      selectPaymentMethod('PayHere');
      return;
    }

    setIsSubmitting(true);

    try {
      const booking = await submitBookingRequest({
        full_name: `${form.firstName} ${form.lastName}`.trim(),
        email: form.email,
        phone: form.phone,
        room_id: selectedRoom.id,
        room_name: selectedRoom.name,
        check_in_date: form.checkIn,
        check_out_date: form.checkOut,
        ...partyRequest(party),
        is_booking_for_other: form.isBookingForOther,
        staying_guest_name: form.isBookingForOther ? form.stayingGuestName : '',
        staying_guest_email: form.isBookingForOther ? form.stayingGuestEmail : '',
        staying_guest_phone: form.isBookingForOther ? form.stayingGuestPhone : '',
        staying_guest_note: form.isBookingForOther ? form.stayingGuestNote : '',
        payment_method: form.paymentMethod,
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

      if (form.paymentMethod === 'PayHere') {
        const checkout = await createCheckoutSession(bookingId, booking.booking_access_token);
        if (!checkout.checkout_url) {
          throw new Error('Payment checkout could not start. Missing PayHere checkout URL.');
        }
        window.location.href = checkout.checkout_url;
        return;
      }

      const billUrl = booking.bill_url;
      if (!billUrl) {
        throw new Error('Booking was saved, but the booking bill link was not returned. Please contact reception.');
      }
      window.location.href = billUrl;
    } catch (error) {
      const message = error instanceof Error ? error.message : 'Unable to submit booking request.';
      setSubmitError(message);
      if (error instanceof PublicRequestError && error.status > 0 && error.status < 500) toast.warning(message);
      else toast.error(message);
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <main>
      <PageHero
        title="Book Your Stay"
        subtitle="Reserve your room directly for the best rate and personalised service."
        image={bannerBooking}
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
              <p className="text-sm text-muted leading-relaxed mb-4">
                Thank you, {form.firstName}. Your booking request has been received. Our team will review it and send a confirmation to{' '}
                <strong>{form.email}</strong> within 2–4 hours.
              </p>
              {phones.length > 0 && <p className="text-sm text-muted mb-8">
                For immediate assistance, please call us at{' '}
                {phones.map((phone, index) => (
                  <span key={phone.href}>
                    {index > 0 && ' or '}
                    <a href={phone.href} className="text-gold hover:underline">{phone.number}</a>
                  </span>
                ))}
              </p>}
              <div className="flex flex-wrap items-center justify-center gap-4">
                {phones.map((phone) => (
                  <a key={phone.href} href={phone.href} className="btn-primary">
                    <Phone size={14} />
                    {phone.number}
                  </a>
                ))}
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
                  <div className="bg-surface border border-border p-8">
                    <h3 className="font-serif text-2xl font-light text-dark mb-5 flex items-center gap-3">
                      <Calendar size={18} className="text-gold" />
                      Stay Details
                    </h3>
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-5">
                      <div>
                        <label className="block text-[9px] tracking-[0.2em] uppercase text-muted mb-2">Check In *</label>
                        <input
                          type="date"
                          name="checkIn"
                          value={form.checkIn}
                          onChange={handleChange}
                          required
                          min={new Date().toISOString().split('T')[0]}
                          className="w-full border border-border px-5 py-3.5 text-sm outline-hidden focus:border-gold transition-colors duration-200 bg-background"
                        />
                      </div>
                      <div>
                        <label className="block text-[9px] tracking-[0.2em] uppercase text-muted mb-2">Check Out *</label>
                        <input
                          type="date"
                          name="checkOut"
                          value={form.checkOut}
                          onChange={handleChange}
                          required
                          min={form.checkIn || new Date().toISOString().split('T')[0]}
                          className="w-full border border-border px-5 py-3.5 text-sm outline-hidden focus:border-gold transition-colors duration-200 bg-background"
                        />
                      </div>
                      <div>
                        <label className="block text-[9px] tracking-[0.2em] uppercase text-muted mb-2">
                          <Users size={10} className="inline mr-1" />
                          Guests *
                        </label>
                        <GuestPartyFields value={party} disabled={isSubmitting} onChange={next => { setParty(next); setForm(prev => ({...prev,guests:String(partyTotal(next))})); }} />
                      </div>
                      <div>
                        <label className="block text-[9px] tracking-[0.2em] uppercase text-muted mb-2">
                          <Home size={10} className="inline mr-1" />
                          Rooms *
                        </label>
                        <select
                          name="rooms"
                          value={form.rooms}
                          onChange={handleChange}
                          className="w-full border border-border px-5 py-3.5 text-sm outline-hidden focus:border-gold transition-colors duration-200 bg-background"
                        >
                          {[1, 2, 3, 4].map((n) => (
                            <option key={n} value={n}>{n} {n === 1 ? 'Room' : 'Rooms'}</option>
                          ))}
                        </select>
                      </div>
                      <div className="md:col-span-2">
                        <label className="block text-[9px] tracking-[0.2em] uppercase text-muted mb-2">Room Type</label>
                        <select
                          name="roomType"
                          disabled={!availabilityReady || isSubmitting}
                          value={form.roomType}
                          onChange={handleChange}
                          className="w-full border border-border px-5 py-3.5 text-sm outline-hidden focus:border-gold transition-colors duration-200 bg-background"
                        >
                          {roomTypeOptions.map((opt) => (
                            <option key={opt.value} value={opt.value}>{opt.label}</option>
                          ))}
                        </select>
                      </div>
                    </div>
                    {selectedRoom && <p className="mt-3 text-xs leading-relaxed text-muted">{roomGuestLabel(selectedRoom)}</p>}
                    {roomSelectionNotice && availabilityReady && <p role="status" className="mt-4 border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">{roomSelectionNotice}</p>}
                    {!validStayDates ? <p role="status" className="mt-4 text-sm text-muted">Select valid check-in and check-out dates to choose a room before entering guest details.</p>
                      : availabilityError ? <div role="alert" className="mt-4 border border-red-200 bg-red-50 p-4 text-sm text-red-700">{availabilityError}<button type="button" className="ml-3 underline" onClick={() => setAvailabilityAttempt(current => current + 1)}>Retry availability</button></div>
                      : !availabilityReady ? <p role="status" className="mt-4 text-sm text-muted">Checking rooms for your updated guest count…</p>
                      : bookingRooms.length === 0 && <div role="alert" className="mt-4 border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">
                        <p className="font-semibold">{multiRoomCount >= 2 ? 'Your group needs multiple rooms.' : 'No suitable rooms are available for this group.'}</p>
                        <p className="mt-2">{multiRoomCount >= 2 ? `No single available room can accommodate ${party.adults} adults and ${party.children} children. Continue with ${multiRoomCount} rooms to review the allocation before entering guest details.` : 'Change the stay dates or guest count to continue. Guest details and payment are unavailable until suitable rooms are selected.'}</p>
                        {multiRoomCount >= 2 && <a className="btn-primary mt-4 w-full justify-center sm:w-auto" href={multipleRoomPath}>Choose {multiRoomCount} rooms</a>}
                      </div>}
                  </div>
                </AnimatedSection>

                <fieldset disabled={!canEnterGuestDetails || isSubmitting} className="min-w-0 space-y-10 disabled:opacity-50">
                <legend className="sr-only">Guest details and payment — choose suitable rooms first</legend>
                <AnimatedSection delay={0.15}>
                  <div className="bg-surface border border-border p-8">
                    <h3 className="font-serif text-2xl font-light text-dark mb-5">Payment Method</h3>
                    <div className="flex flex-wrap gap-x-8 gap-y-3 text-sm text-dark">
                      {arrivalEnabled && <label className="flex cursor-pointer items-center gap-3">
                        <input
                          type="radio"
                          name="paymentMethod"
                          value="Cash"
                          checked={form.paymentMethod === 'Cash'}
                          onChange={() => selectPaymentMethod('Cash')}
                          className="h-4 w-4 accent-gold"
                        />
                        <span>Pay on Arrival</span>
                      </label>}
                      {onlinePaymentEnabled && <label className="flex cursor-pointer items-center gap-3">
                        <input
                          type="radio"
                          name="paymentMethod"
                          value="PayHere"
                          checked={form.paymentMethod === 'PayHere'}
                          onChange={() => selectPaymentMethod('PayHere')}
                          className="h-4 w-4 accent-gold"
                        />
                        <span>Pay Online</span>
                      </label>}
                    </div>
                    {!arrivalEnabled && !onlinePaymentEnabled && <p className="mt-3 text-sm text-red-600">Booking is temporarily unavailable. Please contact the property.</p>}
                    {form.paymentMethod === 'PayHere' && <p className="mt-3 text-[10px] leading-relaxed text-muted">
                      You will continue to the secure PayHere payment page after submitting.
                    </p>}
                  </div>
                </AnimatedSection>

                {/* Guest Information */}
                <AnimatedSection delay={0.1}>
                  <div className="bg-surface border border-border p-8">
                    <h3 className="font-serif text-2xl font-light text-dark mb-5 flex items-center gap-3">
                      <Users size={18} className="text-gold" />
                      Guest Information
                    </h3>
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-5">
                      <div>
                        <label className="block text-[9px] tracking-[0.2em] uppercase text-muted mb-2">First Name *</label>
                        <input
                          type="text"
                          name="firstName"
                          value={form.firstName}
                          onChange={handleChange}
                          required
                          placeholder="First name"
                          className="w-full border border-border px-5 py-3.5 text-sm outline-hidden focus:border-gold transition-colors duration-200 bg-background placeholder-gray-400"
                        />
                      </div>
                      <div>
                        <label className="block text-[9px] tracking-[0.2em] uppercase text-muted mb-2">Last Name *</label>
                        <input
                          type="text"
                          name="lastName"
                          value={form.lastName}
                          onChange={handleChange}
                          required
                          placeholder="Last name"
                          className="w-full border border-border px-5 py-3.5 text-sm outline-hidden focus:border-gold transition-colors duration-200 bg-background placeholder-gray-400"
                        />
                      </div>
                      <div>
                        <label className="block text-[9px] tracking-[0.2em] uppercase text-muted mb-2">Email Address *</label>
                        <input
                          type="email"
                          name="email"
                          value={form.email}
                          onChange={handleChange}
                          required
                          placeholder="your@email.com"
                          className="w-full border border-border px-5 py-3.5 text-sm outline-hidden focus:border-gold transition-colors duration-200 bg-background placeholder-gray-400"
                        />
                      </div>
                      <div>
                        <label className="block text-[9px] tracking-[0.2em] uppercase text-muted mb-2">Phone Number *</label>
                        <input
                          type="tel"
                          name="phone"
                          value={form.phone}
                          onChange={handleChange}
                          required
                          placeholder="+xx xxx xxx xxxx"
                          className="w-full border border-border px-5 py-3.5 text-sm outline-hidden focus:border-gold transition-colors duration-200 bg-background placeholder-gray-400"
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
                            <label className="block text-[9px] tracking-[0.2em] uppercase text-muted mb-2">Staying Guest Name *</label>
                            <input
                              type="text"
                              name="stayingGuestName"
                              value={form.stayingGuestName}
                              onChange={handleChange}
                              required={form.isBookingForOther}
                              placeholder="Guest full name"
                              className="w-full border border-border px-5 py-3.5 text-sm outline-hidden focus:border-gold transition-colors duration-200 bg-background placeholder-gray-400"
                            />
                          </div>
                          <div>
                            <label className="block text-[9px] tracking-[0.2em] uppercase text-muted mb-2">Staying Guest Phone (Optional)</label>
                            <input
                              type="tel"
                              name="stayingGuestPhone"
                              value={form.stayingGuestPhone}
                              onChange={handleChange}
                              placeholder="Guest phone number"
                              className="w-full border border-border px-5 py-3.5 text-sm outline-hidden focus:border-gold transition-colors duration-200 bg-background placeholder-gray-400"
                            />
                          </div>
                          <div className="md:col-span-2">
                            <label className="block text-[9px] tracking-[0.2em] uppercase text-muted mb-2">Staying Guest Email (Optional)</label>
                            <input
                              type="email"
                              name="stayingGuestEmail"
                              value={form.stayingGuestEmail}
                              onChange={handleChange}
                              placeholder="guest@email.com"
                              className="w-full border border-border px-5 py-3.5 text-sm outline-hidden focus:border-gold transition-colors duration-200 bg-background placeholder-gray-400"
                            />
                            <p className="mt-2 text-[10px] leading-relaxed text-muted">If provided, booking updates will also be emailed to the staying guest.</p>
                          </div>
                          <div className="md:col-span-2">
                            <label className="block text-[9px] tracking-[0.2em] uppercase text-muted mb-2">Staying Guest Note</label>
                            <textarea
                              name="stayingGuestNote"
                              value={form.stayingGuestNote}
                              onChange={handleChange}
                              rows={3}
                              placeholder="Any note about the staying guest..."
                              className="w-full border border-border px-5 py-3.5 text-sm outline-hidden focus:border-gold transition-colors duration-200 bg-background placeholder-gray-400 resize-none"
                            />
                          </div>
                        </>
                      )}

                      <div className="md:col-span-2">
                        <label className="block text-[9px] tracking-[0.2em] uppercase text-muted mb-2">Nationality</label>
                        <input
                          type="text"
                          name="nationality"
                          value={form.nationality}
                          onChange={handleChange}
                          placeholder="Your nationality"
                          className="w-full border border-border px-5 py-3.5 text-sm outline-hidden focus:border-gold transition-colors duration-200 bg-background placeholder-gray-400"
                        />
                      </div>
                      <div className="md:col-span-2">
                        <label className="block text-[9px] tracking-[0.2em] uppercase text-muted mb-2">Special Requests</label>
                        <textarea
                          name="specialRequests"
                          value={form.specialRequests}
                          onChange={handleChange}
                          rows={4}
                          placeholder="Any special requirements, dietary needs or preferences..."
                          className="w-full border border-border px-5 py-3.5 text-sm outline-hidden focus:border-gold transition-colors duration-200 bg-background placeholder-gray-400 resize-none"
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

                <button type="submit" className="btn-primary w-full justify-center py-4 text-xs" disabled={(!arrivalEnabled && !onlinePaymentEnabled) || isSubmitting || !availabilityReady || !selectedRoom}>
                  {isSubmitting
                    ? form.paymentMethod === 'Cash' ? 'Submitting Booking...' : 'Preparing Payment...'
                    : form.paymentMethod === 'Cash' ? 'Submit Booking' : 'Continue to Payment'}
                </button>
                </fieldset>
              </form>

              {/* Price Summary */}
              <AnimatedSection direction="right">
                <div className="space-y-6 sticky top-28">
                  {/* Summary Card */}
                  <div className="bg-surface border border-border shadow-luxury p-8">
                    <p className="text-[9px] tracking-[0.25em] uppercase text-gold font-medium mb-4">
                      Price Summary
                    </p>
                    <h3 className="font-serif text-2xl font-light text-dark mb-5">Booking Overview</h3>
                    <div className="w-8 h-[1px] bg-gold mb-6" />

                    <div className="space-y-4 mb-6">
                      <div className="flex justify-between text-sm">
                        <span className="text-muted">Room Type</span>
                        <span className="text-dark font-medium">
                          {selectedRoom ? selectedRoom.name : '—'}
                        </span>
                      </div>
                      <div className="flex justify-between text-sm">
                        <span className="text-muted">Rate Per Night</span>
                        <span className="text-dark font-medium">
                          {selectedRoom ? `${selectedRoom.currency ? `${selectedRoom.currency} ` : '$'}${selectedRoom.price}` : '—'}
                        </span>
                      </div>
                      <div className="flex justify-between text-sm">
                        <span className="text-muted">Nights</span>
                        <span className="text-dark font-medium">{nights || '—'}</span>
                      </div>
                      <div className="flex justify-between text-sm">
                        <span className="text-muted">Rooms</span>
                        <span className="text-dark font-medium">{form.rooms}</span>
                      </div>
                      <div className="flex justify-between text-sm">
                        <span className="text-muted">Guests</span>
                        <span className="text-dark font-medium">{partyTotal(party)}</span>
                      </div>
                    </div>

                    <div className="border-t border-border pt-5">
                      <div className="flex justify-between items-baseline">
                        <span className="text-[9px] tracking-[0.2em] uppercase text-muted">Estimated Total</span>
                        <span className="font-serif text-3xl font-light text-dark">
                          {total > 0 ? `${selectedRoom?.currency ? `${selectedRoom.currency} ` : '$'}${total}` : '—'}
                        </span>
                      </div>

                    </div>
                  </div>

                  {/* Why Book Direct */}
                  <div className="bg-deep-green theme-secondary p-7">
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
                    <p className="text-[9px] tracking-[0.2em] uppercase text-muted mb-4">Need Help?</p>
                    {phones.map((phone) => (
                      <a key={phone.href} href={phone.href}
                        className="flex items-center gap-3 text-dark hover:text-gold transition-colors duration-200 mb-3">
                        <Phone size={14} className="text-gold" />
                        <span className="text-sm font-medium">{phone.number}</span>
                      </a>
                    ))}
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
