import GuestPartyFields from '../components/ui/GuestPartyFields';
import { readParty, writeParty, partyError, partyTotal, occupancy, allocateParty } from '../services/guestOccupancy';
import { useEffect, useMemo, useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { BedDouble, Calendar } from 'lucide-react';
import PageHero from '../components/ui/PageHero';
import RoomCard from '../components/ui/RoomCard';
import CTASection from '../components/ui/CTASection';
import type { Room } from '../data/rooms';
import { fetchPublicRooms } from '../services/publicApi';
import bannerRooms from '../assets/images/banners/banner-rooms.jpg';
import { useToast } from '../components/ui/ToastProvider';

type Category = 'all' | Room['category'];
const categories: { value: Category; label: string }[] = [
  { value: 'all', label: 'All Rooms' }, { value: 'standard', label: 'Standard' },
  { value: 'deluxe', label: 'Deluxe' }, { value: 'family', label: 'Family' }, { value: 'suite', label: 'Suite' },
];

const readFilters = (params: URLSearchParams) => ({
  checkIn: params.get('checkin') || params.get('checkIn') || '',
  checkOut: params.get('checkout') || params.get('checkOut') || '',
  guests: params.get('guests') || '2',
  ...readParty(params),
});

export default function Rooms() {
  const toast = useToast();
  const [searchParams, setSearchParams] = useSearchParams();
  const urlFilters = useMemo(() => readFilters(searchParams), [searchParams]);
  const [form, setForm] = useState(urlFilters);
  const [active, setActive] = useState<Category>('all');
  const [roomList, setRoomList] = useState<Room[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');

  useEffect(() => setForm(urlFilters), [urlFilters]);

  // Keep the guest filter live even when dates have not been selected. Dates
  // are added only as a valid pair, so partial date input never triggers an
  // availability request.
  useEffect(() => {
    const timer = window.setTimeout(() => {
      const next = writeParty(new URLSearchParams(), form);
      if (form.checkIn) next.set('checkin', form.checkIn);
      if (form.checkIn && form.checkOut && form.checkOut > form.checkIn) {
        next.set('checkout', form.checkOut);
      }
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
      .catch((e) => { if (mounted) { const message=e instanceof Error?e.message:'Unable to load rooms.'; setRoomList([]); setError(message); toast.error(message); } })
      .finally(() => { if (mounted) setLoading(false); });
    return () => { mounted = false; };
  }, [urlFilters.checkIn, urlFilters.checkOut]);

  const guests = partyTotal(form);
  const hasSearched = Boolean(urlFilters.checkIn && urlFilters.checkOut && urlFilters.checkOut > urlFilters.checkIn);
  const datesPending = Boolean((form.checkIn || form.checkOut) && (!form.checkIn || !form.checkOut || form.checkOut <= form.checkIn || form.checkIn !== urlFilters.checkIn || form.checkOut !== urlFilters.checkOut));
  const categoryRooms = active === 'all' ? roomList : roomList.filter((room) => room.category === active);
  const singleRoomMatches = partyError(form) ? [] : categoryRooms.filter((room) => occupancy(room, form).fits);
  const suggestedRooms = allocateParty(categoryRooms, form).map(item => item.room);
  const needsMultipleRooms = singleRoomMatches.length === 0 && suggestedRooms.length >= 2;
  const suggestedCapacity = suggestedRooms.reduce((sum, room) => sum + (room.max_total_occupants ?? room.guests), 0);
  // The regular Rooms page represents a single-room search. Never fall back
  // to showing smaller rooms that cannot hold the selected guest count.
  const resultRooms = singleRoomMatches;
  const multiParams = new URLSearchParams({
    guests: String(guests),
    rooms: String(Math.max(2, suggestedRooms.length)),
  });
  if (hasSearched) {
    multiParams.set('checkin', urlFilters.checkIn);
    multiParams.set('checkout', urlFilters.checkOut);
  }
  writeParty(multiParams, form);
  const multiQuery = multiParams.toString();
  const multipleRoomBookingPath = `/multi-room-booking?${multiQuery}`;

  const warnCheckoutRequiresCheckIn = () => {
    if (!form.checkIn) toast.warning('Please select the check-in date first.');
  };

  return <main>
    <PageHero title="Rooms in Point Pedro" subtitle="Clean, comfortable accommodation for individuals, couples and families in Northern Province, Sri Lanka." image={bannerRooms} breadcrumb="Accommodation" />
    <section className="bg-surface border-b border-border"><div className="container-custom"><div className="flex flex-wrap">
      {categories.map((cat) => <button key={cat.value} onClick={() => setActive(cat.value)} className={`px-7 py-5 text-[10px] tracking-[0.2em] uppercase font-medium border-b-2 ${active === cat.value ? 'border-gold text-gold' : 'border-transparent text-muted'}`}>{cat.label}</button>)}
    </div></div></section>

    <section className="bg-background pt-10"><div className="container-custom">
      <div className="glass shadow-luxury border border-border grid grid-cols-1 md:grid-cols-3">
        <label className="flex flex-col px-6 py-3.5 border-r border-border"><span className="flex items-center gap-1.5 text-[9px] tracking-[0.2em] uppercase text-gold mb-1.5"><Calendar size={11}/>Check In</span><input type="date" value={form.checkIn} min={new Date().toISOString().split('T')[0]} onChange={(e) => setForm({...form, checkIn:e.target.value, checkOut: form.checkOut && form.checkOut <= e.target.value ? '' : form.checkOut})} className="bg-transparent text-sm outline-hidden" /></label>
        <label onMouseDown={warnCheckoutRequiresCheckIn} onTouchStart={warnCheckoutRequiresCheckIn} className="flex flex-col px-6 py-3.5 border-r border-border"><span className="flex items-center gap-1.5 text-[9px] tracking-[0.2em] uppercase text-gold mb-1.5"><Calendar size={11}/>Check Out</span><input type="date" value={form.checkOut} disabled={!form.checkIn} min={form.checkIn || new Date().toISOString().split('T')[0]} onChange={(e) => setForm({...form, checkOut:e.target.value})} className="bg-transparent text-sm outline-hidden disabled:cursor-not-allowed disabled:opacity-50" /></label>
        <div className="p-4"><GuestPartyFields value={form} onChange={party => setForm({...form,...party,guests:String(partyTotal(party))})} /></div>
      </div>
      {form.checkIn && form.checkOut && form.checkOut <= form.checkIn && <p className="mt-2 text-sm text-red-600">Check-out must be after check-in.</p>}
      {error && <p className="mt-2 text-sm text-red-600">{error}</p>}
      <div className="mt-5 flex flex-col items-start justify-between gap-4 border border-gold/30 bg-surface px-6 py-5 shadow-sm sm:flex-row sm:items-center">
        <div className="flex items-start gap-3">
          <span className="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center bg-gold/10 text-gold"><BedDouble size={18} /></span>
          <div>
            <p className="font-serif text-lg text-dark">Need more than one room?</p>
            <p className="mt-1 text-xs text-muted">Tulip Guest Inn supports one booking for multiple rooms and guests.</p>
          </div>
        </div>
        <Link to={multipleRoomBookingPath} className="btn-primary whitespace-nowrap">Book Multiple Rooms</Link>
      </div>
    </div></section>

    <section className="section-padding bg-background"><div className="container-custom">
      {hasSearched && !datesPending && !loading && needsMultipleRooms && <div className="mb-10 border border-gold/40 bg-gold/5 p-7 text-center">
        <p className="text-[10px] uppercase tracking-[0.22em] text-gold">Multiple rooms recommended</p>
        <h2 className="mt-2 font-serif text-2xl text-dark">{guests} guests require at least {suggestedRooms.length} available rooms</h2>
        <p className="mt-2 text-sm text-muted">Suggested combined capacity: {suggestedCapacity} guests. You can change the assigned rooms and guest allocation on the next page.</p>
        {urlFilters.checkIn && urlFilters.checkOut && <Link to={`/multi-room-booking?${multiQuery}`} className="btn-primary mt-5">Choose {suggestedRooms.length} Rooms</Link>}
      </div>}

      {hasSearched && !loading && singleRoomMatches.length > 0 && <div className="mb-8 border border-border bg-surface p-5 text-center text-sm text-muted">These rooms can accommodate all {guests} guests in one room. You may book one directly.</div>}
      {datesPending ? <p className="py-20 text-center text-muted">Select valid check-in and check-out dates to see available rooms.</p> : loading ? <p className="py-20 text-center text-muted">Checking availability...</p> : categoryRooms.length === 0 ? <p className="py-20 text-center text-muted">No rooms are available for the selected dates.</p> : resultRooms.length === 0 ? <div className="py-20 text-center"><p className="text-muted">No room is available for a capacity of {guests} guests.</p><p className="mt-2 text-sm text-muted">Please use the multiple-room booking option above.</p></div> : <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">{resultRooms.map((room,i)=><RoomCard key={room.id} room={room} index={i}/>)}</div>}
    </div></section>
    <CTASection title="Ready for a Restful Stay?" subtitle="Our team is on hand to help you find the perfect rooms for your visit." btnLabel="Book Your Room" btnPath="/booking" />
  </main>;
}
