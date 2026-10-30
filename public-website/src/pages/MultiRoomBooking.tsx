import { useGuestAgePolicy } from '../services/guestAgePolicy';
import GuestPartyFields from '../components/ui/GuestPartyFields';
import { readParty, writeParty, partyError, partyTotal, partyRequest, occupancy, roomAllocationSearch, roomMaxOccupancy, roomGuestLabel, type GuestParty } from '../services/guestOccupancy';
import { useEffect, useMemo, useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { Calendar, Home, Users } from 'lucide-react';
import PageHero from '../components/ui/PageHero';
import { fetchPublicFeatures, fetchPublicRooms, submitMultiRoomBooking, createCheckoutSession } from '../services/publicApi';
import type { Room } from '../data/rooms';
import bannerRooms from '../assets/images/banners/banner-rooms.jpg';
import { useToast } from '../components/ui/ToastProvider';
import { PublicRequestError } from '../services/publicErrors';

type AssignedRoom = Room & { allocatedGuests: number; party: GuestParty };

// Calendar-only arithmetic avoids shifting a local midnight to the previous UTC day.
function nextBookingDate(value: string): string {
  if (!/^\d{4}-\d{2}-\d{2}$/.test(value)) return '';
  const date = new Date(`${value}T00:00:00Z`);
  if (!Number.isFinite(date.getTime()) || date.toISOString().slice(0, 10) !== value) return '';
  date.setUTCDate(date.getUTCDate() + 1);
  return date.toISOString().slice(0, 10);
}

function todayBookingDate(): string {
  const date = new Date();
  return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
}


export default function MultiRoomBooking() {
  const { maxChildAge } = useGuestAgePolicy();
  const toast = useToast();
  const [params, setParams] = useSearchParams();
  const filters = useMemo(()=>({checkIn:params.get('checkin')||'',checkOut:params.get('checkout')||'',...readParty(params),guests:partyTotal(readParty(params)),rooms:Math.max(2,Number(params.get('rooms')||2))}),[params]);
  const [allocationIssue,setAllocationIssue]=useState('');
  const [suggestion,setSuggestion]=useState<{rooms:number;slug:string}|null>(null);
  const [available,setAvailable]=useState<Room[]>([]); const [assigned,setAssigned]=useState<AssignedRoom[]>([]);
  const searchKey = `${filters.checkIn}|${filters.checkOut}|${filters.adults}|${filters.children}|${filters.rooms}|${maxChildAge}`;
  const [checkedSearchKey,setCheckedSearchKey]=useState('');
  const [loading,setLoading]=useState(true); const [submitting,setSubmitting]=useState(false); const [error,setError]=useState('');
  const [form,setForm]=useState({full_name:'',email:'',phone:'',message:'',payment_method:'Cash' as 'Cash'|'PayHere'});
  const [onlinePaymentEnabled,setOnlinePaymentEnabled]=useState(false);
  const [arrivalEnabled,setArrivalEnabled]=useState(false);

  useEffect(()=>{let active=true;fetchPublicFeatures().then(features=>{if(!active)return;setOnlinePaymentEnabled(Boolean(features.online_payment_enabled));setArrivalEnabled(Boolean(features.pay_on_arrival_enabled));setForm(current=>({...current,payment_method:features.pay_on_arrival_enabled?'Cash':'PayHere'}));}).catch(()=>{if(active){setOnlinePaymentEnabled(false);setArrivalEnabled(false);}});return()=>{active=false};},[]);

  const selectPaymentMethod=(method:'Cash'|'PayHere')=>{if(method==='Cash'&&!arrivalEnabled){toast.warning('Pay on Arrival is unavailable.');return;}if(method==='PayHere'&&!onlinePaymentEnabled){setForm(current=>({...current,payment_method:'Cash'}));toast.warning('Online payment is not available at the moment. Please use Pay on Arrival.');return;}setForm(current=>({...current,payment_method:method}));};

  useEffect(()=>{let active=true; setError('');setAllocationIssue('');setSuggestion(null);
    if (!filters.checkIn || !filters.checkOut || filters.checkOut <= filters.checkIn || partyError(filters, false, maxChildAge)) {
      setAvailable([]); setAssigned([]); setLoading(false);
      return()=>{active=false};
    }
    setLoading(true); setAvailable([]); setAssigned([]); setError(''); fetchPublicRooms({check_in_date:filters.checkIn,check_out_date:filters.checkOut})
    .then(rows=>{if(active){setAvailable(rows);const result=roomAllocationSearch(rows,filters,filters.rooms);setAssigned(result.assignments.map(({room,party})=>({...room,party,allocatedGuests:partyTotal(party)})));setAllocationIssue(result.reason);setSuggestion(result.alternative.length?{rooms:result.alternative.length,slug:result.alternative[0].room.slug}:null);setCheckedSearchKey(searchKey);}})
    .catch(e=>{if(active){setAvailable([]);setAssigned([]);setError(e instanceof Error?e.message:'Unable to load rooms.');setCheckedSearchKey(searchKey);}}).finally(()=>active&&setLoading(false)); return()=>{active=false};},[filters,maxChildAge]);
  const hasValidDates = Boolean(filters.checkIn && filters.checkOut && filters.checkOut > filters.checkIn);
  const allocated=assigned.reduce((s,r)=>s+r.allocatedGuests,0); const totalCapacity=assigned.reduce((s,r)=>s+roomMaxOccupancy(r),0);
  const allocationReady = !loading && checkedSearchKey === searchKey && assigned.length === filters.rooms
    && new Set(assigned.map(room=>room.id)).size === assigned.length
    && assigned.every(room=>partyTotal(room.party)>0 && !partyError(room.party,true,maxChildAge) && occupancy(room,room.party).fits)
    && assigned.reduce((sum,room)=>sum+room.party.adults,0) === filters.adults
    && assigned.reduce((sum,room)=>sum+room.party.children,0) === filters.children;
  const suggestionParams = writeParty(new URLSearchParams({checkin:filters.checkIn,checkout:filters.checkOut,rooms:String(suggestion?.rooms ?? 1)}),filters);
  if (suggestion?.rooms === 1) suggestionParams.set('room',suggestion.slug);
  const suggestionPath = `${suggestion?.rooms === 1 ? '/booking' : '/multi-room-booking'}?${suggestionParams.toString()}`;
  const nights=Math.max(1,Math.ceil((new Date(filters.checkOut).getTime()-new Date(filters.checkIn).getTime())/86400000));
  const total=assigned.reduce((s,r)=>s+r.price*nights,0);

  const updateFilter = (field: 'checkin'|'checkout'|'guests'|'rooms', value: string) => {
    const next = new URLSearchParams(params);
    next.set(field, value);
    if (field === 'checkin') {
      const currentCheckout = next.get('checkout') || '';
      if (!value) {
        next.delete('checkout');
      } else if (!currentCheckout || currentCheckout <= value) {
        next.set('checkout', nextBookingDate(value));
      }
    }
    setParams(next, { replace: true });
  };

  const warnCheckoutRequiresCheckIn = () => {
    if (!filters.checkIn) toast.warning('Please select the check-in date first.');
  };

  const updateRoomParty = (slot:number, party:GuestParty) => setAssigned(current=>current.map((room,index)=>index===slot?{...room,party,allocatedGuests:partyTotal(party)}:room));
  const changeRoom=(slot:number,id:string)=>{const replacement=available.find(r=>r.id===id);if(!replacement)return;setAssigned(current=>current.map((r,i)=>i===slot?{...replacement,party:r.party,allocatedGuests:r.allocatedGuests}:r));};
  const submit=async(e:React.FormEvent)=>{e.preventDefault();if(!allocationReady){toast.warning('Allocate every adult and child within the room limits before entering guest details.');return;}if(loading||assigned.length!==filters.rooms||new Set(assigned.map(r=>r.id)).size!==assigned.length){toast.warning('Wait for available rooms to load and select a different room for each slot.');return;}setError('');
    const allocatedChildren=assigned.reduce((sum,r)=>sum+r.party.children,0);
    const occupancyError=partyError(filters, false, maxChildAge)||assigned.some(r=>partyError(r.party,true,maxChildAge)||!partyTotal(r.party)||!occupancy(r,r.party).fits);
    if(occupancyError||assigned.reduce((sum,r)=>sum+r.party.adults,0)!==filters.adults||allocatedChildren!==filters.children){toast.warning('Allocate every adult and child exactly once, within each room’s occupancy limits.');return;}
    const emailIsValid=/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email.trim());const phoneDigits=form.phone.replace(/\D/g,'');const validationMessage=!form.full_name.trim()?'Please enter the guest name.':!emailIsValid?'Please enter a valid email address, for example name@example.com.':phoneDigits.length<7?'Please enter a valid phone number including the country code.':'';if(validationMessage){setError(validationMessage);toast.warning(validationMessage);return;}if(allocated!==filters.guests){const message=`Allocate exactly ${filters.guests} guests across the rooms.`;setError(message);toast.warning(message);return;}if(form.payment_method==='Cash'&&!arrivalEnabled){toast.warning('Pay on Arrival is unavailable.');return;}if(form.payment_method==='PayHere'&&!onlinePaymentEnabled){selectPaymentMethod('PayHere');return;}setSubmitting(true);try{
    const result=await submitMultiRoomBooking({...form,check_in_date:filters.checkIn,check_out_date:filters.checkOut,...partyRequest(filters),total_guests:filters.guests,rooms:assigned.map(r=>({room_id:r.id,...partyRequest(r.party)}))});
    if(form.payment_method==='PayHere'){const checkout=await createCheckoutSession(result.booking_id, result.booking_access_token);window.location.href=checkout.checkout_url;return;}
    if(!result.bill_url)throw new Error('Booking saved but bill link was not returned.');window.location.href=result.bill_url;
  }catch(e){const message=e instanceof Error?e.message:'Unable to book rooms.';setError(message);if(e instanceof PublicRequestError&&e.status>0&&e.status<500)toast.warning(message);else toast.error(message);setSubmitting(false);}};

  return <main><PageHero title="Multiple Room Booking" subtitle="Choose actual available rooms and allocate your guests." image={bannerRooms} breadcrumb="Reservation"/>
    <section className="section-padding bg-background"><div className="container-custom max-w-6xl">
      <div className="mb-3 grid bg-surface border border-border sm:grid-cols-2 lg:grid-cols-4">
        <label className="flex flex-col px-5 py-4 border-b sm:border-r lg:border-b-0 border-border">
          <span className="mb-2 flex items-center gap-1.5 text-[9px] tracking-[0.2em] uppercase text-gold"><Calendar size={12}/>Check In</span>
          <input type="date" value={filters.checkIn} min={todayBookingDate()} onChange={e=>updateFilter('checkin',e.target.value)} className="bg-transparent text-sm outline-hidden"/>
        </label>
        <label onMouseDown={warnCheckoutRequiresCheckIn} onTouchStart={warnCheckoutRequiresCheckIn} className="flex flex-col px-5 py-4 border-b lg:border-b-0 lg:border-r border-border">
          <span className="mb-2 flex items-center gap-1.5 text-[9px] tracking-[0.2em] uppercase text-gold"><Calendar size={12}/>Check Out</span>
          <input type="date" value={filters.checkOut} disabled={!filters.checkIn} min={nextBookingDate(filters.checkIn)||todayBookingDate()} onChange={e=>updateFilter('checkout',e.target.value)} className="bg-transparent text-sm outline-hidden disabled:cursor-not-allowed disabled:opacity-50"/>
        </label>
        <div className="p-4 border-r border-border"><span className="mb-2 flex items-center gap-1.5 text-[9px] tracking-[0.2em] uppercase text-gold"><Users size={12}/>Your group</span><GuestPartyFields value={filters} onChange={party=>setParams(writeParty(new URLSearchParams(params),party),{replace:true})}/></div>
        <label className="flex flex-col px-5 py-4">
          <span className="mb-2 flex items-center gap-1.5 text-[9px] tracking-[0.2em] uppercase text-gold"><Home size={12}/>Rooms</span>
          <select value={filters.rooms} onChange={e=>updateFilter('rooms',e.target.value)} className="bg-transparent text-sm outline-hidden">
            {Array.from({length:19},(_,i)=>i+2).map(n=><option key={n} value={n} disabled={n>filters.guests || (!loading && checkedSearchKey===searchKey && n>available.length)}>{n} Rooms</option>)}
          </select>
        </label>
      </div>
      <p className="mb-8 text-[11px] text-muted">Availability and room assignments update automatically.</p>{error&&<p role="alert" className="mb-4 text-sm text-red-600">{error}</p>}
      {!hasValidDates?<div className="border border-gold/30 bg-surface py-16 text-center"><h2 className="font-serif text-2xl text-dark">Select your stay dates</h2><p className="mt-2 text-sm text-muted">Room availability and allocation will appear after both dates are selected.</p></div>:partyError(filters, false, maxChildAge)?<p role="status" className="py-10 text-center text-muted">Complete the adult and child counts above to find rooms.</p>:loading||checkedSearchKey!==searchKey?<p className="py-20 text-center">Assigning rooms...</p>:assigned.length===0?<div role="alert" className="border border-amber-300 bg-surface p-6 text-center sm:p-10"><h2 className="font-serif text-2xl">Your selected room count cannot be allocated</h2><p className="mt-3 text-sm text-muted">{allocationIssue || 'No suitable rooms are available for this search.'}</p>{suggestion && <p className="mt-3 text-sm text-dark">Your group can fit in {suggestion.rooms} {suggestion.rooms===1?'available room':'available rooms'}. Review this option before continuing.</p>}<div className="mt-5 flex flex-wrap justify-center gap-3">{suggestion && <Link className="btn-primary" to={suggestionPath}>Review {suggestion.rooms} {suggestion.rooms===1?'room':'rooms'}</Link>}<Link className="btn-outline" to={`/rooms?${params.toString()}`}>Change Search</Link></div></div>:
      <form onSubmit={submit} className="space-y-10"><div><div className="flex justify-between mb-5"><div><h2 className="font-serif text-3xl">Choose Your Rooms</h2><p className="text-sm text-muted">Select rooms and assign your guests.</p></div><p className={allocationReady?'text-green-700':'text-red-600'}>Allocated {allocated}/{filters.guests} · Up to {totalCapacity} guests</p></div>
      <div className="grid md:grid-cols-2 gap-5">{assigned.map((room,index)=>{const used=new Set(assigned.filter((_,i)=>i!==index).map(r=>r.id));return <div key={index} className="bg-surface border border-border p-6"><label className="text-[9px] uppercase tracking-widest text-muted">Room {index+1}<select value={room.id} onChange={e=>changeRoom(index,e.target.value)} className="mt-2 w-full border border-border p-3 text-sm">{available.filter(r=>r.id===room.id||!used.has(r.id)).map(r=><option key={r.id} value={r.id}>{r.name} — {roomMaxOccupancy(r)} {roomMaxOccupancy(r) === 1 ? 'Guest' : 'Guests'} — {r.currency} {r.price}</option>)}</select></label><h3 className="font-serif text-2xl mt-5">{room.name}</h3><p className="mt-2 text-xs text-muted">{roomGuestLabel(room)}</p><div className="mt-4 border-t border-border pt-4"><GuestPartyFields value={room.party} onChange={party=>updateRoomParty(index,party)} allowZeroAdults disabled={submitting}/>{!occupancy(room,room.party).fits&&<p className="mt-2 text-xs text-red-600">This allocation exceeds the adult, child or combined guest limit shown for this room.</p>}</div></div>})}</div></div>
      {!allocationReady && <p role="alert" className="border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">Allocate all {filters.adults} adults and {filters.children} children within each room’s limits to continue. Guest details and payment stay disabled until the allocation is valid.</p>}
      <fieldset disabled={!allocationReady || submitting} className="grid min-w-0 lg:grid-cols-3 gap-7 disabled:opacity-50"><legend className="sr-only">Guest details and payment — complete the room allocation first</legend><div className="lg:col-span-2 bg-surface border border-border p-7"><h2 className="font-serif text-2xl mb-5">Guest Information</h2><div className="grid sm:grid-cols-2 gap-5"><input required placeholder="Full name" value={form.full_name} onChange={e=>setForm({...form,full_name:e.target.value})} className="border border-border p-3"/><input required type="email" placeholder="Email" value={form.email} onChange={e=>setForm({...form,email:e.target.value})} className="border border-border p-3"/><input required placeholder="Phone" value={form.phone} onChange={e=>setForm({...form,phone:e.target.value})} className="border border-border p-3"/><textarea placeholder="Special requests" value={form.message} onChange={e=>setForm({...form,message:e.target.value})} className="border border-border p-3 sm:col-span-2"/></div></div><div className="bg-surface border border-border p-7"><h2 className="font-serif text-2xl">Payment</h2>{arrivalEnabled && <label className="block mt-5"><input type="radio" checked={form.payment_method==='Cash'} onChange={()=>selectPaymentMethod('Cash')}/> Pay on Arrival</label>}{onlinePaymentEnabled && <label className="block mt-3"><input type="radio" checked={form.payment_method==='PayHere'} onChange={()=>selectPaymentMethod('PayHere')}/> Pay Online</label>}{!arrivalEnabled && !onlinePaymentEnabled && <p className="mt-3 text-red-600">Booking is temporarily unavailable.</p>}<p className="mt-6 text-lg font-semibold">Estimated total: {assigned[0]?.currency||'LKR'} {total.toFixed(2)}</p><button disabled={submitting||!allocationReady||(!arrivalEnabled&&!onlinePaymentEnabled)} className="btn-primary w-full mt-6">{submitting?'Booking...':'Book Selected Rooms'}</button></div></fieldset></form>}
    </div></section></main>;
}
