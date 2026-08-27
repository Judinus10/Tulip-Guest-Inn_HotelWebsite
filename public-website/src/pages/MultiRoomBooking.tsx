import { useEffect, useMemo, useState } from 'react';
import { Link, Navigate, useSearchParams } from 'react-router-dom';
import { Calendar, Home, Minus, Plus, Users } from 'lucide-react';
import PageHero from '../components/ui/PageHero';
import { fetchPublicFeatures, fetchPublicRooms, submitMultiRoomBooking, createCheckoutSession } from '../services/publicApi';
import type { Room } from '../data/rooms';
import bannerRooms from '../assets/images/banners/banner-rooms.jpg';
import { useToast } from '../components/ui/ToastProvider';
import { PublicRequestError } from '../services/publicErrors';

type AssignedRoom = Room & { allocatedGuests: number };

function assignRooms(available: Room[], roomCount: number, guests: number): AssignedRoom[] {
  const selected = [...available].sort((a,b)=>b.guests-a.guests).slice(0, roomCount);
  if (selected.length !== roomCount || selected.reduce((s,r)=>s+r.guests,0) < guests) return [];
  let remaining = guests;
  return selected.map((room, index) => {
    const roomsLeft = selected.length - index - 1;
    const allocation = Math.min(room.guests, Math.max(1, remaining - roomsLeft));
    remaining -= allocation;
    return {...room, allocatedGuests: allocation};
  });
}

export default function MultiRoomBooking() {
  const toast = useToast();
  const [params, setParams] = useSearchParams();
  const filters = useMemo(()=>({checkIn:params.get('checkin')||'',checkOut:params.get('checkout')||'',guests:Number(params.get('guests')||0),rooms:Number(params.get('rooms')||0)}),[params]);
  const [available,setAvailable]=useState<Room[]>([]); const [assigned,setAssigned]=useState<AssignedRoom[]>([]);
  const [loading,setLoading]=useState(true); const [submitting,setSubmitting]=useState(false); const [error,setError]=useState('');
  const [form,setForm]=useState({full_name:'',email:'',phone:'',message:'',payment_method:'Cash' as 'Cash'|'PayHere'});
  const [onlinePaymentEnabled,setOnlinePaymentEnabled]=useState(false);

  useEffect(()=>{let active=true;fetchPublicFeatures().then(features=>{if(!active)return;setOnlinePaymentEnabled(Boolean(features.online_payment_enabled));if(!features.online_payment_enabled)setForm(current=>({...current,payment_method:'Cash'}));}).catch(()=>{if(active)setOnlinePaymentEnabled(false);});return()=>{active=false};},[]);

  const selectPaymentMethod=(method:'Cash'|'PayHere')=>{if(method==='PayHere'&&!onlinePaymentEnabled){setForm(current=>({...current,payment_method:'Cash'}));toast.warning('Online payment is not available at the moment. Please use Pay on Arrival.');return;}setForm(current=>({...current,payment_method:method}));};

  useEffect(()=>{let active=true; setLoading(true); setError(''); fetchPublicRooms({check_in_date:filters.checkIn,check_out_date:filters.checkOut})
    .then(rows=>{if(active){setAvailable(rows);setAssigned(assignRooms(rows,filters.rooms,filters.guests));}})
    .catch(e=>active&&setError(e instanceof Error?e.message:'Unable to load rooms.')).finally(()=>active&&setLoading(false)); return()=>{active=false};},[filters]);

  if(!filters.checkIn||!filters.checkOut||filters.rooms<2||filters.guests<2) return <Navigate to="/rooms" replace/>;
  const allocated=assigned.reduce((s,r)=>s+r.allocatedGuests,0); const totalCapacity=assigned.reduce((s,r)=>s+r.guests,0);
  const nights=Math.max(1,Math.ceil((new Date(filters.checkOut).getTime()-new Date(filters.checkIn).getTime())/86400000));
  const total=assigned.reduce((s,r)=>s+r.price*nights,0);

  const updateFilter = (field: 'checkin'|'checkout'|'guests'|'rooms', value: string) => {
    const next = new URLSearchParams(params);
    next.set(field, value);
    if (field === 'checkin') {
      const currentCheckout = next.get('checkout') || '';
      if (!currentCheckout || currentCheckout <= value) {
        const followingDay = new Date(`${value}T00:00:00`);
        followingDay.setDate(followingDay.getDate() + 1);
        next.set('checkout', followingDay.toISOString().split('T')[0]);
      }
    }
    setParams(next, { replace: true });
  };

  const changeRoom=(slot:number,id:string)=>{const replacement=available.find(r=>r.id===id);if(!replacement)return;setAssigned(current=>current.map((r,i)=>i===slot?{...replacement,allocatedGuests:Math.min(replacement.guests,r.allocatedGuests)}:r));};
  const adjust=(slot:number,diff:number)=>setAssigned(current=>current.map((r,i)=>i===slot?{...r,allocatedGuests:Math.max(1,Math.min(r.guests,r.allocatedGuests+diff))}:r));
  const submit=async(e:React.FormEvent)=>{e.preventDefault();setError('');const emailIsValid=/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email.trim());const phoneDigits=form.phone.replace(/\D/g,'');const validationMessage=!form.full_name.trim()?'Please enter the guest name.':!emailIsValid?'Please enter a valid email address, for example name@example.com.':phoneDigits.length<7?'Please enter a valid phone number including the country code.':'';if(validationMessage){setError(validationMessage);toast.warning(validationMessage);return;}if(allocated!==filters.guests){const message=`Allocate exactly ${filters.guests} guests across the rooms.`;setError(message);toast.warning(message);return;}if(form.payment_method==='PayHere'&&!onlinePaymentEnabled){selectPaymentMethod('PayHere');return;}setSubmitting(true);try{
    const result=await submitMultiRoomBooking({...form,check_in_date:filters.checkIn,check_out_date:filters.checkOut,total_guests:filters.guests,rooms:assigned.map(r=>({room_id:r.id,guests:r.allocatedGuests}))});
    if(form.payment_method==='PayHere'){const checkout=await createCheckoutSession(result.booking_id);window.location.href=checkout.checkout_url;return;}
    if(!result.bill_url)throw new Error('Booking saved but bill link was not returned.');window.location.href=result.bill_url;
  }catch(e){const message=e instanceof Error?e.message:'Unable to book rooms.';setError(message);if(e instanceof PublicRequestError&&e.status>0&&e.status<500)toast.warning(message);else toast.error(message);setSubmitting(false);}};

  return <main><PageHero title="Multiple Room Booking" subtitle="Choose actual available rooms and allocate your guests." image={bannerRooms} breadcrumb="Reservation"/>
    <section className="section-padding bg-background"><div className="container-custom max-w-6xl">
      <div className="mb-3 grid bg-white border border-border sm:grid-cols-2 lg:grid-cols-4">
        <label className="flex flex-col px-5 py-4 border-b sm:border-r lg:border-b-0 border-border">
          <span className="mb-2 flex items-center gap-1.5 text-[9px] tracking-[0.2em] uppercase text-gold"><Calendar size={12}/>Check In</span>
          <input type="date" value={filters.checkIn} min={new Date().toISOString().split('T')[0]} onChange={e=>updateFilter('checkin',e.target.value)} className="bg-transparent text-sm outline-none"/>
        </label>
        <label className="flex flex-col px-5 py-4 border-b lg:border-b-0 lg:border-r border-border">
          <span className="mb-2 flex items-center gap-1.5 text-[9px] tracking-[0.2em] uppercase text-gold"><Calendar size={12}/>Check Out</span>
          <input type="date" value={filters.checkOut} min={filters.checkIn||new Date().toISOString().split('T')[0]} onChange={e=>updateFilter('checkout',e.target.value)} className="bg-transparent text-sm outline-none"/>
        </label>
        <label className="flex flex-col px-5 py-4 border-b sm:border-b-0 sm:border-r border-border">
          <span className="mb-2 flex items-center gap-1.5 text-[9px] tracking-[0.2em] uppercase text-gold"><Users size={12}/>Guests</span>
          <select value={filters.guests} onChange={e=>updateFilter('guests',e.target.value)} className="bg-transparent text-sm outline-none">
            {Array.from({length:19},(_,i)=>i+2).map(n=><option key={n} value={n}>{n} Guests</option>)}
          </select>
        </label>
        <label className="flex flex-col px-5 py-4">
          <span className="mb-2 flex items-center gap-1.5 text-[9px] tracking-[0.2em] uppercase text-gold"><Home size={12}/>Rooms</span>
          <select value={filters.rooms} onChange={e=>updateFilter('rooms',e.target.value)} className="bg-transparent text-sm outline-none">
            {[2,3,4,5,6].map(n=><option key={n} value={n}>{n} Rooms</option>)}
          </select>
        </label>
      </div>
      <p className="mb-8 text-[11px] text-gray-400">Availability and room assignments update automatically.</p>
      {loading?<p className="py-20 text-center">Assigning rooms...</p>:assigned.length===0?<div className="text-center py-20"><p>There are not enough available rooms or combined capacity.</p><Link className="btn-primary mt-5" to={`/rooms?${params.toString()}`}>Change Search</Link></div>:
      <form onSubmit={submit} className="space-y-10"><div><div className="flex justify-between mb-5"><div><h2 className="font-serif text-3xl">Choose Your Rooms</h2><p className="text-sm text-gray-500">Capacity comes directly from the backend.</p></div><p className={allocated===filters.guests?'text-green-700':'text-red-600'}>Allocated {allocated}/{filters.guests} · Capacity {totalCapacity}</p></div>
      <div className="grid md:grid-cols-2 gap-5">{assigned.map((room,index)=>{const used=new Set(assigned.filter((_,i)=>i!==index).map(r=>r.id));return <div key={index} className="bg-white border border-border p-6"><label className="text-[9px] uppercase tracking-widest text-gray-400">Room {index+1}<select value={room.id} onChange={e=>changeRoom(index,e.target.value)} className="mt-2 w-full border border-border p-3 text-sm">{available.filter(r=>r.id===room.id||!used.has(r.id)).map(r=><option key={r.id} value={r.id}>{r.name} — capacity {r.guests} — {r.currency} {r.price}</option>)}</select></label><h3 className="font-serif text-2xl mt-5">{room.name}</h3><p className="text-xs text-gray-500 mt-1">Maximum {room.guests} guests</p><div className="mt-5 pt-4 border-t flex justify-between items-center"><span className="text-xs uppercase tracking-wider">Guests in room</span><div className="flex items-center gap-3"><button type="button" onClick={()=>adjust(index,-1)} className="w-9 h-9 border grid place-items-center"><Minus size={14}/></button><strong>{room.allocatedGuests}</strong><button type="button" onClick={()=>adjust(index,1)} className="w-9 h-9 border grid place-items-center"><Plus size={14}/></button></div></div></div>})}</div></div>
      <div className="grid lg:grid-cols-3 gap-7"><div className="lg:col-span-2 bg-white border border-border p-7"><h2 className="font-serif text-2xl mb-5">Guest Information</h2><div className="grid sm:grid-cols-2 gap-5"><input required placeholder="Full name" value={form.full_name} onChange={e=>setForm({...form,full_name:e.target.value})} className="border border-border p-3"/><input required type="email" placeholder="Email" value={form.email} onChange={e=>setForm({...form,email:e.target.value})} className="border border-border p-3"/><input required placeholder="Phone" value={form.phone} onChange={e=>setForm({...form,phone:e.target.value})} className="border border-border p-3"/><textarea placeholder="Special requests" value={form.message} onChange={e=>setForm({...form,message:e.target.value})} className="border border-border p-3 sm:col-span-2"/></div></div><div className="bg-white border border-border p-7"><h2 className="font-serif text-2xl">Payment</h2><label className="block mt-5"><input type="radio" checked={form.payment_method==='Cash'} onChange={()=>selectPaymentMethod('Cash')}/> Pay on Arrival</label><label className="block mt-3"><input type="radio" checked={form.payment_method==='PayHere'} onChange={()=>selectPaymentMethod('PayHere')}/> Pay Online</label><p className="mt-6 text-lg font-semibold">Estimated total: {assigned[0]?.currency||'LKR'} {total.toFixed(2)}</p><button disabled={submitting||allocated!==filters.guests} className="btn-primary w-full mt-6">{submitting?'Booking...':'Book Selected Rooms'}</button></div></div>{error&&<p className="text-red-600">{error}</p>}</form>}
    </div></section></main>;
}
