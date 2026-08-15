import { useEffect, useMemo, useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { Calendar, Users } from 'lucide-react';
import PageHero from '../components/ui/PageHero';
import RoomCard from '../components/ui/RoomCard';
import CTASection from '../components/ui/CTASection';
import { rooms as fallbackRooms } from '../data/rooms';
import type { Room } from '../data/rooms';
import { fetchPublicRooms } from '../services/publicApi';
import bannerRooms from '../assets/images/banners/banner-rooms.jpg';

type Category = 'all' | Room['category'];
const categories: { value: Category; label: string }[] = [
  { value: 'all', label: 'All Rooms' }, { value: 'standard', label: 'Standard' },
  { value: 'deluxe', label: 'Deluxe' }, { value: 'family', label: 'Family' }, { value: 'suite', label: 'Suite' },
];

const readFilters = (params: URLSearchParams) => ({
  checkIn: params.get('checkin') || params.get('checkIn') || '',
  checkOut: params.get('checkout') || params.get('checkOut') || '',
  guests: params.get('guests') || '2',
});

function minimumRoomCombination(rooms: Room[], guests: number): Room[] {
  const sorted = [...rooms].filter((room) => room.guests > 0).sort((a, b) => b.guests - a.guests);
  const selected: Room[] = [];
  let capacity = 0;
  for (const room of sorted) {
    selected.push(room);
    capacity += room.guests;
    if (capacity >= guests) return selected;
  }
  return [];
}

export default function Rooms() {
  const [searchParams, setSearchParams] = useSearchParams();
  const urlFilters = useMemo(() => readFilters(searchParams), [searchParams]);
  const [form, setForm] = useState(urlFilters);
  const [active, setActive] = useState<Category>('all');
  const [roomList, setRoomList] = useState<Room[]>(fallbackRooms);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');

  useEffect(() => setForm(urlFilters), [urlFilters]);

  // Live filter: update the URL shortly after a valid field change. There is
  // deliberately no submit button and no manual room-count decision.
  useEffect(() => {
    if (!form.checkIn || !form.checkOut || form.checkOut <= form.checkIn) return;
    const timer = window.setTimeout(() => {
      const next = new URLSearchParams({ checkin: form.checkIn, checkout: form.checkOut, guests: form.guests });
      if (next.toString() !== searchParams.toString()) setSearchParams(next, { replace: true });
    }, 250);
    return () => window.clearTimeout(timer);
  }, [form, searchParams, setSearchParams]);

  useEffect(() => {
    let mounted = true;
    const hasDates = Boolean(urlFilters.checkIn && urlFilters.checkOut);
    const params: Record<string, string> = {};
    if (hasDates) { params.check_in_date = urlFilters.checkIn; params.check_out_date = urlFilters.checkOut; }

    setLoading(true); setError('');
    // Load every available physical room for the dates. Filtering the API by
    // total guests here would incorrectly demand one room for the whole group.
    fetchPublicRooms(params).then((data) => { if (mounted) setRoomList(data); })
      .catch((e) => { if (mounted) { setRoomList(hasDates ? [] : fallbackRooms); setError(e instanceof Error ? e.message : 'Unable to load rooms.'); } })
      .finally(() => { if (mounted) setLoading(false); });
    return () => { mounted = false; };
  }, [urlFilters.checkIn, urlFilters.checkOut]);

  const guests = Math.max(1, Number(urlFilters.guests));
  const categoryRooms = active === 'all' ? roomList : roomList.filter((room) => room.category === active);
  const singleRoomMatches = categoryRooms.filter((room) => room.guests >= guests);
  const suggestedRooms = minimumRoomCombination(categoryRooms, guests);
  const needsMultipleRooms = singleRoomMatches.length === 0 && suggestedRooms.length >= 2;
  const suggestedCapacity = suggestedRooms.reduce((sum, room) => sum + room.guests, 0);
  const resultRooms = singleRoomMatches.length > 0 ? singleRoomMatches : categoryRooms;
  const multiQuery = new URLSearchParams({
    checkin: urlFilters.checkIn,
    checkout: urlFilters.checkOut,
    guests: String(guests),
    rooms: String(suggestedRooms.length),
  }).toString();

  return <main>
    <PageHero title="Rooms in Point Pedro" subtitle="Clean, comfortable accommodation for individuals, couples and families in Northern Province, Sri Lanka." image={bannerRooms} breadcrumb="Accommodation" />
    <section className="bg-white border-b border-border"><div className="container-custom"><div className="flex flex-wrap">
      {categories.map((cat) => <button key={cat.value} onClick={() => setActive(cat.value)} className={`px-7 py-5 text-[10px] tracking-[0.2em] uppercase font-medium border-b-2 ${active === cat.value ? 'border-gold text-gold' : 'border-transparent text-gray-400'}`}>{cat.label}</button>)}
    </div></div></section>

    <section className="bg-background pt-10"><div className="container-custom">
      <div className="glass shadow-luxury border border-border grid grid-cols-1 md:grid-cols-3">
        <label className="flex flex-col px-6 py-5 border-r border-border"><span className="flex items-center gap-1.5 text-[9px] tracking-[0.2em] uppercase text-gold mb-2"><Calendar size={11}/>Check In</span><input type="date" value={form.checkIn} min={new Date().toISOString().split('T')[0]} onChange={(e) => setForm({...form, checkIn:e.target.value, checkOut: form.checkOut && form.checkOut <= e.target.value ? '' : form.checkOut})} className="bg-transparent text-sm outline-none" /></label>
        <label className="flex flex-col px-6 py-5 border-r border-border"><span className="flex items-center gap-1.5 text-[9px] tracking-[0.2em] uppercase text-gold mb-2"><Calendar size={11}/>Check Out</span><input type="date" value={form.checkOut} min={form.checkIn || new Date().toISOString().split('T')[0]} onChange={(e) => setForm({...form, checkOut:e.target.value})} className="bg-transparent text-sm outline-none" /></label>
        <label className="flex flex-col px-6 py-5"><span className="flex items-center gap-1.5 text-[9px] tracking-[0.2em] uppercase text-gold mb-2"><Users size={11}/>Guests</span><select value={form.guests} onChange={(e) => setForm({...form, guests:e.target.value})} className="bg-transparent text-sm outline-none">{Array.from({length:20},(_,i)=>i+1).map(n=><option key={n} value={n}>{n} Guest{n>1?'s':''}</option>)}</select></label>
      </div>
      <p className="mt-3 text-[11px] text-gray-400">Results update automatically when dates or guests change.</p>
      {form.checkIn && form.checkOut && form.checkOut <= form.checkIn && <p className="mt-2 text-sm text-red-600">Check-out must be after check-in.</p>}
      {error && <p className="mt-2 text-sm text-red-600">{error}</p>}
    </div></section>

    <section className="section-padding bg-background"><div className="container-custom">
      {needsMultipleRooms && <div className="mb-10 border border-gold/40 bg-gold/5 p-7 text-center">
        <p className="text-[10px] uppercase tracking-[0.22em] text-gold">Multiple rooms recommended</p>
        <h2 className="mt-2 font-serif text-2xl text-dark">{guests} guests require at least {suggestedRooms.length} available rooms</h2>
        <p className="mt-2 text-sm text-gray-500">Suggested combined capacity: {suggestedCapacity} guests. You can change the assigned rooms and guest allocation on the next page.</p>
        {urlFilters.checkIn && urlFilters.checkOut && <Link to={`/multi-room-booking?${multiQuery}`} className="btn-primary mt-5">Choose {suggestedRooms.length} Rooms</Link>}
      </div>}

      {!loading && singleRoomMatches.length > 0 && <div className="mb-8 border border-border bg-white p-5 text-center text-sm text-gray-500">These rooms can accommodate all {guests} guests in one room. You may book one directly.</div>}
      {loading ? <p className="py-20 text-center text-gray-400">Checking availability...</p> : categoryRooms.length === 0 ? <p className="py-20 text-center text-gray-400">No rooms are available for the selected dates.</p> : suggestedRooms.length === 0 && singleRoomMatches.length === 0 ? <p className="py-20 text-center text-gray-400">The available rooms do not have enough combined capacity for {guests} guests. Change the dates or category.</p> : <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">{resultRooms.map((room,i)=><RoomCard key={room.id} room={room} index={i} bookingPath={needsMultipleRooms ? `/multi-room-booking?${multiQuery}` : undefined}/>)}</div>}
    </div></section>
    <CTASection title="Ready for a Restful Stay?" subtitle="Our team is on hand to help you find the perfect rooms for your visit." btnLabel="Book Your Room" btnPath="/booking" />
  </main>;
}
