import { useState } from 'react';
import { motion } from 'framer-motion';
import { Calendar, Users, Home, Search } from 'lucide-react';
import { useNavigate } from 'react-router-dom';

export default function BookingBar() {
  const navigate = useNavigate();
  const [form, setForm] = useState({
    checkIn: '',
    checkOut: '',
    guests: '2',
    rooms: '1',
  });

  const handleChange = (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) => {
    setForm((prev) => ({ ...prev, [e.target.name]: e.target.value }));
  };

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    navigate('/booking', { state: form });
  };

  return (
    <motion.div
      initial={{ y: 60, opacity: 0 }}
      animate={{ y: 0, opacity: 1 }}
      transition={{ delay: 0.5, duration: 0.7, ease: 'easeOut' }}
      className="relative z-20 max-w-5xl mx-auto px-4"
    >
      <form
        onSubmit={handleSubmit}
        className="glass shadow-luxury-lg border border-border grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-0"
      >
        {/* Check In */}
        <div className="flex flex-col px-6 py-5 border-r border-border">
          <label className="flex items-center gap-1.5 text-[9px] tracking-[0.2em] uppercase text-gold font-medium mb-2">
            <Calendar size={11} />
            Check In
          </label>
          <input
            type="date"
            name="checkIn"
            value={form.checkIn}
            onChange={handleChange}
            className="bg-transparent text-sm text-dark outline-none cursor-pointer w-full"
            min={new Date().toISOString().split('T')[0]}
          />
        </div>

        {/* Check Out */}
        <div className="flex flex-col px-6 py-5 border-r border-border">
          <label className="flex items-center gap-1.5 text-[9px] tracking-[0.2em] uppercase text-gold font-medium mb-2">
            <Calendar size={11} />
            Check Out
          </label>
          <input
            type="date"
            name="checkOut"
            value={form.checkOut}
            onChange={handleChange}
            className="bg-transparent text-sm text-dark outline-none cursor-pointer w-full"
            min={form.checkIn || new Date().toISOString().split('T')[0]}
          />
        </div>

        {/* Guests */}
        <div className="flex flex-col px-6 py-5 border-r border-border">
          <label className="flex items-center gap-1.5 text-[9px] tracking-[0.2em] uppercase text-gold font-medium mb-2">
            <Users size={11} />
            Guests
          </label>
          <select
            name="guests"
            value={form.guests}
            onChange={handleChange}
            className="bg-transparent text-sm text-dark outline-none cursor-pointer w-full"
          >
            {[1, 2, 3, 4, 5, 6].map((n) => (
              <option key={n} value={n}>
                {n} {n === 1 ? 'Guest' : 'Guests'}
              </option>
            ))}
          </select>
        </div>

        {/* Rooms */}
        <div className="flex flex-col px-6 py-5 border-r border-border">
          <label className="flex items-center gap-1.5 text-[9px] tracking-[0.2em] uppercase text-gold font-medium mb-2">
            <Home size={11} />
            Rooms
          </label>
          <select
            name="rooms"
            value={form.rooms}
            onChange={handleChange}
            className="bg-transparent text-sm text-dark outline-none cursor-pointer w-full"
          >
            {[1, 2, 3, 4].map((n) => (
              <option key={n} value={n}>
                {n} {n === 1 ? 'Room' : 'Rooms'}
              </option>
            ))}
          </select>
        </div>

        {/* Search */}
        <div className="col-span-2 md:col-span-4 lg:col-span-1">
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
