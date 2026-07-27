import { useEffect, useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import PageHero from '../components/ui/PageHero';
import RoomCard from '../components/ui/RoomCard';
import CTASection from '../components/ui/CTASection';
import { rooms as fallbackRooms } from '../data/rooms';
import type { Room } from '../data/rooms';
import { fetchPublicRooms } from '../services/publicApi';
import bannerRooms from '../assets/images/banners/banner-rooms.jpg';

type Category = 'all' | Room['category'];

const categories: { value: Category; label: string }[] = [
  { value: 'all', label: 'All Rooms' },
  { value: 'standard', label: 'Standard' },
  { value: 'deluxe', label: 'Deluxe' },
  { value: 'family', label: 'Family' },
  { value: 'suite', label: 'Suite' },
];

const amenitiesHighlight = [
  'Air Conditioning',
  'Free WiFi',
  'Private Bathroom',
  'Daily Housekeeping',
  'Flat Screen TV',
  'Swimming Pool Access',
];

export default function Rooms() {
  const [searchParams] = useSearchParams();
  const [active, setActive] = useState<Category>('all');
  const [roomList, setRoomList] = useState<Room[]>(fallbackRooms);

  useEffect(() => {
    let mounted = true;

    const checkIn = searchParams.get('checkin') || searchParams.get('checkIn') || '';
    const checkOut = searchParams.get('checkout') || searchParams.get('checkOut') || '';
    const guests = searchParams.get('guests') || '';

    const params: Record<string, string> = {};
    const hasDateFilter = Boolean(checkIn && checkOut);

    if (hasDateFilter) {
      params.check_in_date = checkIn;
      params.check_out_date = checkOut;
    }

    if (guests) {
      params.guests = guests;
    }

    fetchPublicRooms(params)
      .then((backendRooms) => {
        if (mounted) setRoomList(backendRooms);
      })
      .catch(() => {
        if (mounted) setRoomList(hasDateFilter ? [] : fallbackRooms);
      });

    return () => {
      mounted = false;
    };
  }, [searchParams]);

  const filtered = active === 'all' ? roomList : roomList.filter((r) => r.category === active);

  return (
    <main>
      <PageHero
        title="Rooms in Point Pedro"
        subtitle="Clean, comfortable accommodation for individuals, couples and families in Northern Province, Sri Lanka."
        image={bannerRooms}
        breadcrumb="Accommodation"
      />

      {/* Filters */}
      <section className="bg-white border-b border-border">
        <div className="container-custom">
          <div className="flex flex-wrap items-center gap-0 py-0">
            {categories.map((cat) => (
              <button
                key={cat.value}
                onClick={() => setActive(cat.value)}
                className={`px-7 py-5 text-[10px] tracking-[0.2em] uppercase font-medium transition-all duration-300 border-b-2 ${
                  active === cat.value
                    ? 'border-gold text-gold'
                    : 'border-transparent text-gray-400 hover:text-dark hover:border-border'
                }`}
              >
                {cat.label}
              </button>
            ))}
          </div>
        </div>
      </section>

      {/* Rooms Grid */}
      <section className="section-padding bg-background">
        <div className="container-custom">
          {/* Amenities Info Bar */}
          <div className="bg-white border border-border p-6 mb-12 flex flex-wrap items-center gap-3">
            <span className="text-[9px] tracking-[0.25em] uppercase text-gold font-medium mr-2">All Rooms Include:</span>
            {amenitiesHighlight.map((a) => (
              <span key={a} className="text-xs text-gray-500 bg-background px-3 py-1.5 border border-border">
                {a}
              </span>
            ))}
          </div>

          {filtered.length === 0 ? (
            <div className="text-center py-20">
              <p className="text-gray-400 text-sm">No rooms found in this category.</p>
            </div>
          ) : (
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
              {filtered.map((room, i) => (
                <RoomCard key={room.id} room={room} index={i} />
              ))}
            </div>
          )}
        </div>
      </section>

      {/* Booking CTA */}
      <section className="bg-white section-padding">
        <div className="container-custom">
          <div className="bg-deep-green p-10 lg:p-16 flex flex-col lg:flex-row items-center justify-between gap-8">
            <div>
              <p className="text-[9px] tracking-[0.3em] uppercase text-gold mb-3">Direct Booking</p>
              <h3 className="font-serif text-3xl lg:text-4xl text-white font-light">
                Book Direct for the Best Rate
              </h3>
            </div>
            <Link to="/booking" className="btn-white shrink-0">
              Check Availability
            </Link>
          </div>
        </div>
      </section>

      <CTASection
        title="Ready for a Restful Stay?"
        subtitle="Our team is on hand to help you find the perfect room for your visit to Northern Sri Lanka."
        btnLabel="Book Your Room"
        btnPath="/booking"
      />
    </main>
  );
}
