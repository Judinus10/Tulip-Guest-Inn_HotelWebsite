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
  const navigate = useNavigate();
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
    `bg-transparent text-sm text-dark outline-none cursor-pointer w-full transition-colors duration-200 ${
      invalidFields[field] ? 'booking-field-error' : ''
    }`;

  const fieldClass = (field: BookingBarField, base = 'flex flex-col px-6 py-5 border-r border-border') =>
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

    if (!validateForm()) return;

    navigate(buildBookingQuery(form));
  };

  return (
    <motion.div
      initial={{ y: 60, opacity: 0 }}
      animate={{ y: 0, opacity: 1 }}
      transition={{ delay: 0.5, duration: 0.7, ease: 'easeOut' }}
      className="relative z-20 max-w-5xl mx-auto px-4"
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
        className="glass shadow-luxury-lg border border-border grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-0"
      >
        {/* Check In */}
        <div className={fieldClass('checkIn')}>
          <label className="flex items-center gap-1.5 text-[9px] tracking-[0.2em] uppercase text-gold font-medium mb-2">
            <Calendar size={11} />
            Check In
          </label>
          <input
            ref={(node) => { fieldRefs.current.checkIn = node; }}
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
          <label className="flex items-center gap-1.5 text-[9px] tracking-[0.2em] uppercase text-gold font-medium mb-2">
            <Calendar size={11} />
            Check Out
          </label>
          <input
            ref={(node) => { fieldRefs.current.checkOut = node; }}
            type="date"
            name="checkOut"
            value={form.checkOut}
            onChange={handleChange}
            className={inputClass('checkOut')}
            min={form.checkIn || today()}
          />
        </div>

        {/* Guests */}
        <div className={fieldClass('guests')}>
          <label className="flex items-center gap-1.5 text-[9px] tracking-[0.2em] uppercase text-gold font-medium mb-2">
            <Users size={11} />
            Guests
          </label>
          <select
            ref={(node) => { fieldRefs.current.guests = node; }}
            name="guests"
            value={form.guests}
            onChange={handleChange}
            className={inputClass('guests')}
          >
            {Array.from({ length: 20 }, (_, index) => index + 1).map((n) => (
              <option key={n} value={n}>
                {n} {n === 1 ? 'Guest' : 'Guests'}
              </option>
            ))}
          </select>
        </div>

        {/* Search */}
        <div className="col-span-2 md:col-span-3 lg:col-span-1">
          <button
            type="submit"
            className="w-full h-full bg-gold hover:bg-deep-green text-white flex items-center justify-center gap-2 text-[10px] tracking-[0.2em] uppercase font-medium transition-colors duration-300 py-5 lg:py-0"
          >
            <Search size={14} />
            Check Availability
          </button>
        </div>
      </form>
    </motion.div>
  );
}
