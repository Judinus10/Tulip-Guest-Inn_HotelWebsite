import { useGuestAgePolicy } from '../../services/guestAgePolicy';
import GuestPartyFields from './GuestPartyFields';
import { readParty, writeParty, partyError, partyTotal } from '../../services/guestOccupancy';
import { useRef, useState } from 'react';
import { motion } from 'framer-motion';
import { Calendar, Users, Search } from 'lucide-react';
import { useNavigate } from 'react-router-dom';

interface BookingBarForm {
  checkIn: string;
  checkOut: string;
  guests: string;
}

type BookingBarField = keyof BookingBarForm;

const today = () => new Date().toISOString().split('T')[0];

const buildBookingQuery = (form: BookingBarForm) => {
  const params = new URLSearchParams({
    checkin: form.checkIn,
    checkout: form.checkOut,
    guests: form.guests,
  });

  return `/rooms?${params.toString()}`;
};

export default function BookingBar() {
  const { maxChildAge } = useGuestAgePolicy();
  const navigate = useNavigate();
  const [party, setParty] = useState(() => readParty(new URLSearchParams()));
  const fieldRefs = useRef<Record<BookingBarField, HTMLInputElement | HTMLSelectElement | null>>({
    checkIn: null,
    checkOut: null,
    guests: null,
  });

  const [form, setForm] = useState<BookingBarForm>({
    checkIn: '',
    checkOut: '',
    guests: '2',
  });
  const [invalidFields, setInvalidFields] = useState<Partial<Record<BookingBarField, boolean>>>({});

  const markInvalid = (field: BookingBarField, isInvalid: boolean) => {
    setInvalidFields((prev) => ({ ...prev, [field]: isInvalid }));
  };

  const validateForm = () => {
    const nextInvalid: Partial<Record<BookingBarField, boolean>> = {
      checkIn: !form.checkIn,
      checkOut: !form.checkOut,
      guests: !form.guests,
    };

    const orderedFields: BookingBarField[] = ['checkIn', 'checkOut', 'guests'];
    const firstInvalid = orderedFields.find((field) => nextInvalid[field]);

    setInvalidFields(nextInvalid);

    if (firstInvalid) {
      fieldRefs.current[firstInvalid]?.focus();
      return false;
    }

    return true;
  };

  const inputClass = (field: BookingBarField) =>
    `bg-transparent text-sm text-dark outline-hidden cursor-pointer w-full min-w-0 transition-colors duration-200 ${
      invalidFields[field] ? 'booking-field-error' : ''
    }`;

  const fieldClass = (field: BookingBarField, base = 'flex min-w-0 flex-col px-4 py-3.5 border-border lg:border-r') =>
    `${base} transition-colors duration-200 ${invalidFields[field] ? 'booking-field-shake bg-red-50/70 ring-1 ring-red-400' : ''}`;

  const handleChange = (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) => {
    const field = e.target.name as BookingBarField;
    const value = e.target.value;

    setForm((prev) => {
      const next = { ...prev, [field]: value };

      if (field === 'checkIn' && next.checkOut && next.checkOut < value) {
        next.checkOut = '';
      }

      return next;
    });

    if (value) markInvalid(field, false);
  };

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();

    if (!validateForm() || partyError(party, false, maxChildAge)) return;

    const query = new URL(buildBookingQuery(form), window.location.origin);
    writeParty(query.searchParams, party);
    navigate(query.pathname + query.search);
  };

  return (
    <motion.div
      initial={{ y: 60, opacity: 0 }}
      animate={{ y: 0, opacity: 1 }}
      transition={{ delay: 0.5, duration: 0.7, ease: 'easeOut' }}
      className="relative z-20 max-w-4xl mx-auto px-4"
    >
      <style>{`
        @keyframes bookingFieldShake {
          0%, 100% { transform: translateX(0); }
          20% { transform: translateX(-5px); }
          40% { transform: translateX(5px); }
          60% { transform: translateX(-4px); }
          80% { transform: translateX(4px); }
        }
        .booking-field-shake { animation: bookingFieldShake 0.35s ease-in-out; }
        .booking-field-error { color: #b91c1c; }
      `}</style>
      <form
        onSubmit={handleSubmit}
        noValidate
        className="glass shadow-luxury-lg border border-border grid grid-cols-2 lg:grid-cols-[minmax(145px,1fr)_minmax(145px,1fr)_minmax(340px,1.9fr)_auto] gap-0"
      >
        {/* Check In */}
        <div className={fieldClass('checkIn')}>
          <label htmlFor="home-booking-check-in" className="flex items-center gap-1.5 text-[9px] tracking-[0.2em] uppercase text-gold font-medium mb-2">
            <Calendar size={11} />
            Check In
          </label>
          <input
            ref={(node) => { fieldRefs.current.checkIn = node; }}
            id="home-booking-check-in"
            type="date"
            name="checkIn"
            value={form.checkIn}
            onChange={handleChange}
            className={inputClass('checkIn')}
            min={today()}
          />
        </div>

        {/* Check Out */}
        <div className={fieldClass('checkOut')}>
          <label htmlFor="home-booking-check-out" className="flex items-center gap-1.5 text-[9px] tracking-[0.2em] uppercase text-gold font-medium mb-2">
            <Calendar size={11} />
            Check Out
          </label>
          <input
            ref={(node) => { fieldRefs.current.checkOut = node; }}
            id="home-booking-check-out"
            type="date"
            name="checkOut"
            value={form.checkOut}
            onChange={handleChange}
            className={inputClass('checkOut')}
            min={form.checkIn || today()}
          />
        </div>

        {/* Guests */}
        <div className={fieldClass('guests', 'col-span-2 flex min-w-0 flex-col border-t border-border px-4 py-3.5 lg:col-span-1 lg:border-t-0 lg:border-r')}>
          <p className="flex items-center gap-1.5 text-[9px] tracking-[0.2em] uppercase text-gold font-medium mb-2">
            <Users size={11} />
            Guests
          </p>
          <GuestPartyFields compact value={party} onChange={next => { setParty(next); setForm(prev => ({...prev,guests:String(partyTotal(next))})); }} />
        </div>

        {/* Search */}
        <div className="col-span-2 lg:col-span-1">
          <button
            type="submit"
            className="w-full h-full bg-gold hover:bg-deep-green text-white flex items-center justify-center gap-2 text-[10px] tracking-[0.2em] uppercase font-medium transition-colors duration-300 whitespace-nowrap px-5 py-3.5 lg:py-0"
          >
            <Search size={14} />
            Check Availability
          </button>
        </div>
      </form>
    </motion.div>
  );
}
