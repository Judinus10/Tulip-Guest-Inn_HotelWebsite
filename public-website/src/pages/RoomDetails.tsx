import { useEffect, useRef, useState } from 'react';
import { useParams, Link, Navigate, useNavigate, useSearchParams } from 'react-router-dom';
import { motion } from 'framer-motion';
import { Users, BedDouble, Maximize, Bath, Check, X, MapPin, ChevronLeft, ChevronRight } from 'lucide-react';
import RoomCard from '../components/ui/RoomCard';
import AnimatedSection from '../components/ui/AnimatedSection';
import { rooms as fallbackRooms } from '../data/rooms';
import type { Room } from '../data/rooms';
import { fetchPublicRoom, fetchPublicRooms } from '../services/publicApi';
import { fetchPropertyContent } from '../services/propertyContentApi';
import type { NearbyPlace } from '../services/propertyContentApi';

interface RoomBookingForm {
  checkIn: string;
  checkOut: string;
  guests: string;
}

type RoomBookingField = keyof RoomBookingForm;

const today = () => new Date().toISOString().split('T')[0];

export default function RoomDetails() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const [searchParams] = useSearchParams();
  const queryCheckIn = searchParams.get('checkin') || searchParams.get('checkIn') || '';
  const queryCheckOut = searchParams.get('checkout') || searchParams.get('checkOut') || '';
  const queryGuests = searchParams.get('guests') || '1';
  const fallbackRoom = fallbackRooms.find((r) => r.slug === id || r.id === id);
  const [room, setRoom] = useState<Room | undefined>(fallbackRoom);
  const [roomList, setRoomList] = useState<Room[]>(fallbackRooms);
  const [nearbyPlaces, setNearbyPlaces] = useState<NearbyPlace[]>([]);
  const [loading, setLoading] = useState(!fallbackRoom);
  const [bookingForm, setBookingForm] = useState<RoomBookingForm>({
    checkIn: queryCheckIn,
    checkOut: queryCheckOut,
    guests: queryGuests,
  });
  const [invalidFields, setInvalidFields] = useState<Partial<Record<RoomBookingField, boolean>>>({});
  const fieldRefs = useRef<Record<RoomBookingField, HTMLInputElement | HTMLSelectElement | null>>({
    checkIn: null,
    checkOut: null,
    guests: null,
  });

  const [activeImg, setActiveImg] = useState(0);
  const touchStartX = useRef<number | null>(null);

  useEffect(() => {
    let mounted = true;
    fetchPropertyContent().then((content) => {
      if (mounted) setNearbyPlaces(content.nearby_places);
    });
    return () => { mounted = false; };
  }, []);

  useEffect(() => {
    if (!id) return;

    let mounted = true;
    setLoading(true);
    setActiveImg(0);

    const roomFilters: Record<string, string> = {};
    if (queryCheckIn && queryCheckOut) {
      roomFilters.check_in_date = queryCheckIn;
      roomFilters.check_out_date = queryCheckOut;
    }

    Promise.allSettled([fetchPublicRoom(id), fetchPublicRooms(roomFilters)]).then(([roomResult, roomsResult]) => {
      if (!mounted) return;

      if (roomResult.status === 'fulfilled') {
        setRoom(roomResult.value);
      } else {
        setRoom(fallbackRooms.find((r) => r.slug === id || r.id === id));
      }

      if (roomsResult.status === 'fulfilled' && roomsResult.value.length > 0) {
        setRoomList(roomsResult.value);
      } else {
        setRoomList(fallbackRooms);
      }

      setLoading(false);
    });

    return () => {
      mounted = false;
    };
  }, [id, queryCheckIn, queryCheckOut]);

  useEffect(() => {
    setBookingForm({
      checkIn: queryCheckIn,
      checkOut: queryCheckOut,
      guests: room ? String(Math.min(Math.max(1, Number(queryGuests) || 1), room.guests)) : queryGuests,
    });
  }, [queryCheckIn, queryCheckOut, queryGuests, room]);

  useEffect(() => {
    const scriptId = 'room-structured-data';
    let script = document.getElementById(scriptId) as HTMLScriptElement | null;

    if (!room) {
      script?.remove();
      return;
    }

    if (!script) {
      script = document.createElement('script');
      script.id = scriptId;
      script.type = 'application/ld+json';
      document.head.appendChild(script);
    }

    const roomUrl = `https://www.tulipguestinn.com/rooms/${room.slug}`;
    script.textContent = JSON.stringify([
      {
        '@context': 'https://schema.org',
        '@type': 'HotelRoom',
        '@id': `${roomUrl}#room`,
        name: room.name,
        url: roomUrl,
        description: room.longDescription,
        image: room.images,
        occupancy: {
          '@type': 'QuantitativeValue',
          maxValue: room.guests,
        },
        bed: room.beds,
        numberOfBathroomsTotal: room.bathrooms,
        floorSize: {
          '@type': 'QuantitativeValue',
          value: room.size,
          unitCode: 'MTK',
        },
        amenityFeature: room.amenities.map((amenity) => ({
          '@type': 'LocationFeatureSpecification',
          name: amenity,
          value: true,
        })),
        containedInPlace: {
          '@id': 'https://www.tulipguestinn.com/#lodging-business',
        },
      },
      {
        '@context': 'https://schema.org',
        '@type': 'BreadcrumbList',
        itemListElement: [
          {
            '@type': 'ListItem',
            position: 1,
            name: 'Home',
            item: 'https://www.tulipguestinn.com/',
          },
          {
            '@type': 'ListItem',
            position: 2,
            name: 'Rooms',
            item: 'https://www.tulipguestinn.com/rooms',
          },
          {
            '@type': 'ListItem',
            position: 3,
            name: room.name,
            item: roomUrl,
          },
        ],
      },
    ]);

    return () => {
      document.getElementById(scriptId)?.remove();
    };
  }, [room]);

  if (!room && loading) return <main />;
  if (!room) return <Navigate to="/rooms" replace />;

  const relatedRooms = roomList.filter((r) => r.id !== room.id).slice(0, 3);

  const prevImg = () => setActiveImg((i) => (i - 1 + room.images.length) % room.images.length);
  const nextImg = () => setActiveImg((i) => (i + 1) % room.images.length);

  const markInvalid = (field: RoomBookingField, isInvalid: boolean) => {
    setInvalidFields((prev) => ({ ...prev, [field]: isInvalid }));
  };

  const validateBookingForm = () => {
    const nextInvalid: Partial<Record<RoomBookingField, boolean>> = {
      checkIn: !bookingForm.checkIn,
      checkOut: !bookingForm.checkOut,
      guests: !bookingForm.guests,
    };

    const orderedFields: RoomBookingField[] = ['checkIn', 'checkOut', 'guests'];
    const firstInvalid = orderedFields.find((field) => nextInvalid[field]);

    setInvalidFields(nextInvalid);

    if (firstInvalid) {
      fieldRefs.current[firstInvalid]?.focus();
      return false;
    }

    return true;
  };

  const handleBookingChange = (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) => {
    const field = e.target.name as RoomBookingField;
    const value = e.target.value;

    setBookingForm((prev) => {
      const next = { ...prev, [field]: value };

      if (field === 'checkIn' && next.checkOut && next.checkOut < value) {
        next.checkOut = '';
      }

      return next;
    });

    if (value) markInvalid(field, false);
  };

  const handleBookRoom = () => {
    if (!validateBookingForm()) return;

    const params = new URLSearchParams({
      room: room.slug,
      checkin: bookingForm.checkIn,
      checkout: bookingForm.checkOut,
      guests: bookingForm.guests,
      rooms: '1',
    });

    navigate(`/booking?${params.toString()}`);
  };

  const bookingInputClass = (field: RoomBookingField) =>
    `border px-4 py-2.5 text-sm text-dark outline-none focus:border-gold transition-colors duration-200 bg-background ${
      invalidFields[field] ? 'booking-field-shake border-red-500 ring-1 ring-red-300' : 'border-border'
    }`;

  return (
    <main>
      <style>{`
        @keyframes bookingFieldShake {
          0%, 100% { transform: translateX(0); }
          20% { transform: translateX(-5px); }
          40% { transform: translateX(5px); }
          60% { transform: translateX(-4px); }
          80% { transform: translateX(4px); }
        }
        .booking-field-shake { animation: bookingFieldShake 0.35s ease-in-out; }
      `}</style>

      {/* Gallery Slider */}
      <section
        className="relative h-[70vh] min-h-[480px] overflow-hidden bg-dark"
        onTouchStart={(event) => { touchStartX.current = event.touches[0]?.clientX ?? null; }}
        onTouchEnd={(event) => {
          if (touchStartX.current === null) return;
          const distance = (event.changedTouches[0]?.clientX ?? touchStartX.current) - touchStartX.current;
          touchStartX.current = null;
          if (Math.abs(distance) < 45) return;
          if (distance > 0) prevImg(); else nextImg();
        }}
      >
        {room.images.map((src, i) => (
          <motion.div
            key={src}
            className="absolute inset-0"
            animate={{ opacity: i === activeImg ? 1 : 0 }}
            transition={{ duration: 0.6 }}
          >
            <img src={src} alt={`${room.name} ${i + 1}`} className="w-full h-full object-cover" />
            <div className="absolute inset-0 bg-gradient-to-t from-black/50 to-transparent" />
          </motion.div>
        ))}

        {/* Navigation */}
        <button
          onClick={prevImg}
          className="absolute left-6 top-1/2 -translate-y-1/2 w-11 h-11 bg-white/20 hover:bg-gold flex items-center justify-center text-white transition-colors duration-300"
          aria-label="Previous image"
        >
          <ChevronLeft size={22} />
        </button>
        <button
          onClick={nextImg}
          className="absolute right-6 top-1/2 -translate-y-1/2 w-11 h-11 bg-white/20 hover:bg-gold flex items-center justify-center text-white transition-colors duration-300"
          aria-label="Next image"
        >
          <ChevronRight size={22} />
        </button>

        {/* Dots */}
        <div className="absolute bottom-6 left-1/2 -translate-x-1/2 flex gap-2">
          {room.images.map((_, i) => (
            <button
              key={i}
              onClick={() => setActiveImg(i)}
              className={`transition-all duration-300 ${
                i === activeImg ? 'w-8 h-1.5 bg-gold' : 'w-1.5 h-1.5 rounded-full bg-white/50'
              }`}
              aria-label={`Image ${i + 1}`}
            />
          ))}
        </div>

        {/* Room Badge */}
        <div className="absolute top-8 left-8">
          <span className="bg-gold text-white text-[9px] tracking-[0.2em] uppercase px-4 py-2">
            {room.category}
          </span>
        </div>

        {/* Thumbs */}
        <div className="absolute bottom-6 right-6 hidden md:flex gap-2">
          {room.images.map((src, i) => (
            <button
              key={i}
              onClick={() => setActiveImg(i)}
              className={`w-16 h-12 overflow-hidden border-2 transition-colors duration-200 ${
                i === activeImg ? 'border-gold' : 'border-transparent opacity-60 hover:opacity-100'
              }`}
            >
              <img src={src} alt="" className="w-full h-full object-cover" />
            </button>
          ))}
        </div>
      </section>

      {/* Room Info */}
      <section className="section-padding bg-background">
        <div className="container-custom">
          <div className="grid grid-cols-1 lg:grid-cols-3 gap-12">
            {/* Main Info */}
            <div className="lg:col-span-2">
              <AnimatedSection>
                <Link
                  to={`/rooms${searchParams.toString() ? `?${searchParams.toString()}` : ''}`}
                  className="inline-flex items-center gap-2 text-[10px] tracking-[0.18em] uppercase text-gray-400 hover:text-gold transition-colors duration-200 mb-6"
                >
                  <ChevronLeft size={12} />
                  All Rooms
                </Link>
                <h1 className="font-serif text-4xl lg:text-5xl font-light text-dark mb-4">{room.name}</h1>
                <div className="w-12 h-[1px] bg-gold mb-6" />

                {/* Stats Row */}
                <div className="flex flex-wrap gap-8 py-6 border-y border-border mb-8">
                  <div className="flex items-center gap-2 text-gray-600">
                    <Users size={16} className="text-gold" />
                    <span className="text-sm">{room.guests} Guests</span>
                  </div>
                  <div className="flex items-center gap-2 text-gray-600">
                    <BedDouble size={16} className="text-gold" />
                    <span className="text-sm">{room.beds}</span>
                  </div>
                  <div className="flex items-center gap-2 text-gray-600">
                    <Bath size={16} className="text-gold" />
                    <span className="text-sm">{room.bathrooms} Bathroom{room.bathrooms > 1 ? 's' : ''}</span>
                  </div>
                  <div className="flex items-center gap-2 text-gray-600">
                    <Maximize size={16} className="text-gold" />
                    <span className="text-sm">{room.size} m²</span>
                  </div>
                </div>

                <h3 className="font-serif text-xl text-dark font-light mb-4">About This Room</h3>
                <p className="text-sm text-gray-500 leading-relaxed mb-8">{room.longDescription}</p>

                {/* Amenities */}
                <h3 className="font-serif text-xl text-dark font-light mb-5">Room Amenities</h3>
                <div className="grid grid-cols-2 md:grid-cols-3 gap-3">
                  {(room.amenityCatalog?.length
                    ? room.amenityCatalog.filter((amenity) => amenity.selected || room.showUnavailableAmenities)
                    : room.amenities.map((amenity, index) => ({ id: index, amenity_name: amenity, selected: true }))
                  ).map((amenity) => (
                    <div key={`${amenity.id}-${amenity.amenity_name}`} className="flex items-center gap-2.5">
                      {amenity.selected
                        ? <Check size={13} className="text-gold shrink-0" />
                        : <X size={13} className="text-red-500 shrink-0" />}
                      <span className={`text-sm ${amenity.selected ? 'text-gray-600' : 'text-gray-400'}`}>{amenity.amenity_name}</span>
                    </div>
                  ))}
                </div>

                {nearbyPlaces.length > 0 && (
                  <div className="mt-8 border-t border-border pt-8">
                    <h3 className="font-serif text-xl text-dark font-light mb-5">Nearby Places</h3>
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-3">
                      {nearbyPlaces.map((place) => (
                        <div key={place.id} className="flex items-center justify-between gap-4 border-b border-border pb-3">
                          <span className="flex items-center gap-2.5 text-sm text-gray-600">
                            <MapPin size={14} className="text-gold shrink-0" />
                            {place.name}
                          </span>
                          <span className="text-sm text-gray-600">{place.distance} {place.distance_unit}</span>
                        </div>
                      ))}
                    </div>
                  </div>
                )}
              </AnimatedSection>
            </div>

            {/* Booking Card */}
            <AnimatedSection direction="right" className="lg:col-span-1">
              <div className="bg-white border border-border shadow-luxury p-8 sticky top-28">
                <p className="text-[9px] tracking-[0.25em] uppercase text-gold font-medium mb-2">Rate From</p>
                <p className="font-serif text-4xl font-light text-dark mb-1">
                  {room.currency ? `${room.currency} ` : '$'}{room.price}
                  <span className="text-gray-400 text-base font-sans"> / night</span>
                </p>
                <div className="w-8 h-[1px] bg-gold my-5" />

                <div className="space-y-4 mb-6">
                  <div className="flex flex-col">
                    <label className="text-[9px] tracking-[0.2em] uppercase text-gray-400 mb-1.5">Check In</label>
                    <input
                      ref={(node) => { fieldRefs.current.checkIn = node; }}
                      type="date"
                      name="checkIn"
                      value={bookingForm.checkIn}
                      onChange={handleBookingChange}
                      className={bookingInputClass('checkIn')}
                      min={today()}
                    />
                  </div>
                  <div className="flex flex-col">
                    <label className="text-[9px] tracking-[0.2em] uppercase text-gray-400 mb-1.5">Check Out</label>
                    <input
                      ref={(node) => { fieldRefs.current.checkOut = node; }}
                      type="date"
                      name="checkOut"
                      value={bookingForm.checkOut}
                      onChange={handleBookingChange}
                      className={bookingInputClass('checkOut')}
                      min={bookingForm.checkIn || today()}
                    />
                  </div>
                  <div className="flex flex-col">
                    <label className="text-[9px] tracking-[0.2em] uppercase text-gray-400 mb-1.5">Guests</label>
                    <select
                      ref={(node) => { fieldRefs.current.guests = node; }}
                      name="guests"
                      value={bookingForm.guests}
                      onChange={handleBookingChange}
                      className={bookingInputClass('guests')}
                    >
                      {Array.from({ length: room.guests }, (_, i) => i + 1).map((n) => (
                        <option key={n} value={n}>{n} {n === 1 ? 'Guest' : 'Guests'}</option>
                      ))}
                    </select>
                  </div>
                </div>

                <button
                  type="button"
                  onClick={handleBookRoom}
                  className="btn-primary w-full justify-center mb-3"
                >
                  Book This Room
                </button>
                <Link
                  to="/contact"
                  className="btn-outline w-full justify-center text-[9px]"
                >
                  Enquire Now
                </Link>

                <div className="mt-6 pt-6 border-t border-border">
                  <div className="flex items-start gap-2.5 mb-3">
                    <Check size={13} className="text-gold mt-0.5 shrink-0" />
                    <span className="text-xs text-gray-500">Free cancellation available</span>
                  </div>
                  <div className="flex items-start gap-2.5">
                    <Check size={13} className="text-gold mt-0.5 shrink-0" />
                    <span className="text-xs text-gray-500">Direct booking — best rate guaranteed</span>
                  </div>
                </div>
              </div>
            </AnimatedSection>
          </div>
        </div>
      </section>

      {/* Related Rooms */}
      {relatedRooms.length > 0 && (
        <section className="section-padding bg-white">
          <div className="container-custom">
            <h2 className="font-serif text-3xl font-light text-dark mb-2">You May Also Like</h2>
            <div className="w-10 h-[1px] bg-gold mb-10" />
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
              {relatedRooms.map((room, i) => (
                <RoomCard key={room.id} room={room} index={i} />
              ))}
            </div>
          </div>
        </section>
      )}
    </main>
  );
}
