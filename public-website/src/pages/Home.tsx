import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { motion } from 'framer-motion';
import { ChevronDown, Wifi, Car, Waves, Users, Shield, Coffee } from 'lucide-react';
import SectionTitle from '../components/ui/SectionTitle';
import AnimatedSection from '../components/ui/AnimatedSection';
import BookingBar from '../components/ui/BookingBar';
import RoomCard from '../components/ui/RoomCard';
import FacilityCard from '../components/ui/FacilityCard';
import TestimonialSlider from '../components/ui/TestimonialSlider';
import OfferCard from '../components/ui/OfferCard';
import StatCounter from '../components/ui/StatCounter';
import CTASection from '../components/ui/CTASection';
import AttractionCard from '../components/ui/AttractionCard';
import { rooms } from '../data/rooms';
import { facilities } from '../data/facilities';
import { galleryImages } from '../data/gallery';
import { offers } from '../data/offers';
import { statistics } from '../data/statistics';
import { attractions } from '../data/attractions';
import { getPublicAttractions, getPublicGalleryImages, getPublicRooms } from '../services/publicApi';

const heroImages = [
  'https://images.pexels.com/photos/338504/pexels-photo-338504.jpeg?auto=compress&cs=tinysrgb&w=1920',
  'https://images.pexels.com/photos/261102/pexels-photo-261102.jpeg?auto=compress&cs=tinysrgb&w=1920',
  'https://images.pexels.com/photos/1838554/pexels-photo-1838554.jpeg?auto=compress&cs=tinysrgb&w=1920',
];

const welcomeFeatures = [
  { icon: Waves, label: 'Swimming Pool' },
  { icon: Wifi, label: 'Free WiFi' },
  { icon: Car, label: 'Free Parking' },
  { icon: Users, label: 'Family Friendly' },
  { icon: Shield, label: '24-Hr Security' },
  { icon: Coffee, label: 'Peaceful Garden' },
];

export default function Home() {
  const [heroIndex, setHeroIndex] = useState(0);
  const [lightbox, setLightbox] = useState<string | null>(null);
  const [homeRooms, setHomeRooms] = useState(rooms);
  const [homeGalleryImages, setHomeGalleryImages] = useState(galleryImages);
  const [homeAttractions, setHomeAttractions] = useState(attractions);

  const previewImages = homeGalleryImages.slice(0, 8);
  const featuredRooms = homeRooms.filter((r) => r.featured).slice(0, 3);
  const activeHeroImages = homeGalleryImages.length >= 3 ? homeGalleryImages.slice(0, 3).map((img) => img.src) : heroImages;

  useEffect(() => {
    const interval = setInterval(() => {
      setHeroIndex((prev) => (prev + 1) % activeHeroImages.length);
    }, 7000);
    return () => clearInterval(interval);
  }, [activeHeroImages.length]);

  useEffect(() => {
    let isMounted = true;

    async function loadHomeData() {
      const [roomsResult, galleryResult, attractionsResult] = await Promise.allSettled([
        getPublicRooms(),
        getPublicGalleryImages(),
        getPublicAttractions(),
      ]);

      if (!isMounted) return;

      if (roomsResult.status === 'fulfilled' && roomsResult.value.length > 0) {
        setHomeRooms(roomsResult.value);
      }

      if (galleryResult.status === 'fulfilled' && galleryResult.value.length > 0) {
        setHomeGalleryImages(galleryResult.value);
      }

      if (attractionsResult.status === 'fulfilled' && attractionsResult.value.length > 0) {
        setHomeAttractions(attractionsResult.value);
      }
    }

    loadHomeData();

    return () => {
      isMounted = false;
    };
  }, []);

  return (
    <main>
      {/* ── HERO ─────────────────────────────────────────── */}
      <section className="relative h-screen min-h-[600px] flex items-center justify-center overflow-hidden">
        {activeHeroImages.map((src, i) => (
          <motion.div
            key={src}
            className="absolute inset-0"
            initial={{ opacity: 0 }}
            animate={{ opacity: i === heroIndex ? 1 : 0 }}
            transition={{ duration: 1.5, ease: 'easeInOut' }}
          >
            <motion.img
              src={src}
              alt="Tulip Guest Inn"
              className="w-full h-full object-cover"
              animate={{ scale: i === heroIndex ? 1.07 : 1 }}
              transition={{ duration: 8, ease: 'easeOut' }}
            />
          </motion.div>
        ))}
        <div className="absolute inset-0 bg-gradient-to-b from-black/50 via-black/30 to-black/60" />

        <div className="relative z-10 text-center px-4 max-w-4xl mx-auto">
          <motion.p
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: 0.4, duration: 0.8 }}
            className="text-[10px] tracking-[0.4em] uppercase text-gold mb-5 font-medium"
          >
            Point Pedro — Northern Sri Lanka
          </motion.p>
          <motion.h1
            initial={{ opacity: 0, y: 40 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: 0.6, duration: 0.9 }}
            className="font-serif text-5xl md:text-6xl lg:text-7xl text-white font-light leading-[1.05] mb-6 text-shadow"
          >
            Boutique Comfort in the<br />
            <span className="text-gold italic">Heart of Point Pedro</span>
          </motion.h1>
          <motion.div
            initial={{ scaleX: 0 }}
            animate={{ scaleX: 1 }}
            transition={{ delay: 1, duration: 0.6 }}
            className="w-16 h-[1px] bg-gold mx-auto mb-6"
          />
          <motion.p
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: 1.1, duration: 0.7 }}
            className="text-white/80 text-sm md:text-base leading-relaxed mb-10 max-w-lg mx-auto"
          >
            Experience peaceful accommodation, modern comfort and genuine Sri Lankan hospitality.
          </motion.p>
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: 1.3, duration: 0.7 }}
            className="flex flex-wrap items-center justify-center gap-4"
          >
            <Link to="/booking" className="btn-primary">
              Book Your Stay
            </Link>
            <Link to="/rooms" className="btn-outline border-white text-white hover:bg-white hover:text-dark">
              Explore Rooms
            </Link>
          </motion.div>
        </div>

        {/* Scroll Indicator */}
        <motion.div
          initial={{ opacity: 0 }}
          animate={{ opacity: 1 }}
          transition={{ delay: 2, duration: 0.8 }}
          className="absolute bottom-8 left-1/2 -translate-x-1/2 flex flex-col items-center gap-2"
        >
          <motion.div
            animate={{ y: [0, 8, 0] }}
            transition={{ duration: 1.5, repeat: Infinity }}
          >
            <ChevronDown size={20} className="text-white/60" />
          </motion.div>
          <p className="text-[9px] tracking-[0.3em] uppercase text-white/40">Scroll</p>
        </motion.div>

        {/* Hero Dots */}
        <div className="absolute bottom-8 right-8 flex gap-2">
          {activeHeroImages.map((_, i) => (
            <button
              key={i}
              onClick={() => setHeroIndex(i)}
              className={`transition-all duration-400 ${
                i === heroIndex ? 'w-8 h-1.5 bg-gold' : 'w-1.5 h-1.5 rounded-full bg-white/40 hover:bg-white/70'
              }`}
              aria-label={`Hero image ${i + 1}`}
            />
          ))}
        </div>
      </section>

      {/* ── BOOKING BAR ──────────────────────────────────── */}
      <div className="-mt-8 pb-0 relative z-20">
        <BookingBar />
      </div>

      {/* ── WELCOME SECTION ──────────────────────────────── */}
      <section className="section-padding bg-background">
        <div className="container-custom">
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-16 items-center">
            {/* Left */}
            <div>
              <AnimatedSection direction="left">
                <p className="text-[10px] tracking-[0.35em] uppercase text-gold font-medium mb-4">
                  Welcome
                </p>
                <h2 className="font-serif text-4xl md:text-5xl font-light text-dark leading-tight mb-5">
                  Welcome to<br />Tulip Guest Inn
                </h2>
                <div className="gold-line-left" />
                <p className="text-sm text-gray-500 leading-relaxed mb-5">
                  Nestled in the heart of Point Pedro in Northern Sri Lanka, Tulip Guest Inn is a premium boutique property designed for guests who appreciate genuine comfort, thoughtful service and serene surroundings.
                </p>
                <p className="text-sm text-gray-500 leading-relaxed mb-8">
                  From our tranquil outdoor pool and lush tropical gardens to our elegantly appointed rooms, every detail has been carefully considered to ensure a stay that is as restful as it is memorable.
                </p>
                <div className="grid grid-cols-2 gap-3 mb-8">
                  {welcomeFeatures.map(({ icon: Icon, label }) => (
                    <div key={label} className="flex items-center gap-3">
                      <Icon size={15} className="text-gold shrink-0" />
                      <span className="text-sm text-gray-600">{label}</span>
                    </div>
                  ))}
                </div>
                <Link to="/about" className="btn-dark">
                  Discover More
                </Link>
              </AnimatedSection>
            </div>

            {/* Right — Image Collage */}
            <AnimatedSection direction="right" className="relative h-[480px] hidden lg:block">
              <div className="absolute top-0 right-0 w-64 h-72 overflow-hidden shadow-luxury-lg">
                <img
                  src="https://images.pexels.com/photos/1457842/pexels-photo-1457842.jpeg?auto=compress&cs=tinysrgb&w=800"
                  alt="Deluxe Room"
                  className="w-full h-full object-cover hover:scale-105 transition-transform duration-700"
                  loading="lazy"
                />
              </div>
              <div className="absolute bottom-0 right-16 w-72 h-52 overflow-hidden shadow-luxury-lg">
                <img
                  src="https://images.pexels.com/photos/261102/pexels-photo-261102.jpeg?auto=compress&cs=tinysrgb&w=800"
                  alt="Swimming Pool"
                  className="w-full h-full object-cover hover:scale-105 transition-transform duration-700"
                  loading="lazy"
                />
              </div>
              <div className="absolute top-20 left-0 w-52 h-64 overflow-hidden shadow-luxury-lg">
                <img
                  src="https://images.pexels.com/photos/1366919/pexels-photo-1366919.jpeg?auto=compress&cs=tinysrgb&w=800"
                  alt="Tropical Garden"
                  className="w-full h-full object-cover hover:scale-105 transition-transform duration-700"
                  loading="lazy"
                />
              </div>
              {/* Gold Accent */}
              <div className="absolute -bottom-4 -right-4 w-40 h-40 border-2 border-gold/20 -z-10" />
            </AnimatedSection>
          </div>
        </div>
      </section>

      {/* ── WHY CHOOSE US ─────────────────────────────────── */}
      <section className="section-padding bg-white">
        <div className="container-custom">
          <SectionTitle
            eyebrow="Our Promise"
            title="Why Choose Us"
            subtitle="Every aspect of your stay is designed with care, comfort and luxury in mind."
          />
          <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
            {facilities.slice(0, 8).map((facility, i) => (
              <FacilityCard key={facility.id} facility={facility} index={i} />
            ))}
          </div>
        </div>
      </section>

      {/* ── FEATURED ROOMS ────────────────────────────────── */}
      <section className="section-padding bg-background">
        <div className="container-custom">
          <SectionTitle
            eyebrow="Accommodation"
            title="Discover Our Rooms"
            subtitle="Each room is a private sanctuary — thoughtfully designed for comfort, elegance and peaceful rest."
          />
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            {featuredRooms.map((room, i) => (
              <RoomCard key={room.id} room={room} index={i} />
            ))}
          </div>
          <div className="text-center mt-12">
            <Link to="/rooms" className="btn-outline">
              View All Rooms
            </Link>
          </div>
        </div>
      </section>

      {/* ── LUXURY HIGHLIGHT ──────────────────────────────── */}
      <section className="relative py-0 overflow-hidden">
        <div className="grid grid-cols-1 lg:grid-cols-2 min-h-[580px]">
          {/* Image Side */}
          <div className="relative overflow-hidden">
            <motion.img
              src="https://images.pexels.com/photos/261102/pexels-photo-261102.jpeg?auto=compress&cs=tinysrgb&w=1200"
              alt="Swimming Pool"
              className="w-full h-full object-cover min-h-[400px]"
              whileInView={{ scale: 1 }}
              initial={{ scale: 1.08 }}
              viewport={{ once: true }}
              transition={{ duration: 1.2, ease: 'easeOut' }}
              loading="lazy"
            />
          </div>
          {/* Content Side */}
          <div className="bg-deep-green flex items-center px-10 lg:px-16 py-16">
            <AnimatedSection direction="right" className="max-w-md">
              <p className="text-[10px] tracking-[0.35em] uppercase text-gold font-medium mb-5">
                Luxury Experience
              </p>
              <h2 className="font-serif text-4xl lg:text-5xl font-light text-white leading-tight mb-6">
                Relax, Unwind and<br />Feel at Home
              </h2>
              <div className="w-12 h-[1px] bg-gold mb-6" />
              <p className="text-gray-300 text-sm leading-relaxed mb-8">
                Escape the busy city and enjoy comfortable accommodation surrounded by peaceful gardens and modern facilities. Our outdoor swimming pool, shaded terraces and lush gardens create a sanctuary of calm in the heart of Point Pedro.
              </p>
              <Link to="/rooms" className="btn-white">
                Explore Rooms
              </Link>
            </AnimatedSection>
          </div>
        </div>
      </section>

      {/* ── TESTIMONIALS ──────────────────────────────────── */}
      <section className="section-padding bg-white">
        <div className="container-custom">
          <SectionTitle
            eyebrow="Guest Reviews"
            title="Words from Our Guests"
            subtitle="Authentic experiences shared by travellers who have stayed with us."
          />
          <TestimonialSlider />
        </div>
      </section>

      {/* ── GALLERY PREVIEW ───────────────────────────────── */}
      <section className="section-padding bg-background">
        <div className="container-custom">
          <SectionTitle
            eyebrow="Photo Gallery"
            title="A Glimpse of Tulip"
          />
          <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
            {previewImages.map((img, i) => (
              <motion.div
                key={img.id}
                initial={{ opacity: 0, scale: 0.95 }}
                whileInView={{ opacity: 1, scale: 1 }}
                viewport={{ once: true, margin: '-40px' }}
                transition={{ duration: 0.5, delay: i * 0.06 }}
                className={`relative overflow-hidden cursor-pointer group ${
                  i === 0 || i === 5 ? 'col-span-2 row-span-2' : ''
                }`}
                style={{ aspectRatio: i === 0 || i === 5 ? '16/9' : '4/3' }}
                onClick={() => setLightbox(img.src)}
              >
                <img
                  src={img.src}
                  alt={img.alt}
                  className="w-full h-full object-cover transition-transform duration-700 group-hover:scale-110"
                  loading="lazy"
                />
                <div className="absolute inset-0 bg-black/0 group-hover:bg-black/30 transition-colors duration-400" />
              </motion.div>
            ))}
          </div>
          <div className="text-center mt-10">
            <Link to="/gallery" className="btn-outline">
              View Full Gallery
            </Link>
          </div>
        </div>
      </section>

      {/* ── ATTRACTIONS PREVIEW ───────────────────────────── */}
      <section className="section-padding bg-white">
        <div className="container-custom">
          <SectionTitle
            eyebrow="Explore"
            title="Nearby Attractions"
            subtitle="Point Pedro and the Jaffna Peninsula are rich in natural beauty, history and culture."
          />
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            {homeAttractions.slice(0, 3).map((attraction, i) => (
              <AttractionCard key={attraction.id} attraction={attraction} index={i} />
            ))}
          </div>
          <div className="text-center mt-10">
            <Link to="/attractions" className="btn-outline">
              Explore All Attractions
            </Link>
          </div>
        </div>
      </section>

      {/* ── SPECIAL OFFERS ────────────────────────────────── */}
      <section className="section-padding bg-background">
        <div className="container-custom">
          <SectionTitle
            eyebrow="Special Offers"
            title="Exclusive Packages"
            subtitle="Take advantage of our curated offers designed to make your stay even more special."
          />
          <div className="grid grid-cols-1 md:grid-cols-3 gap-8">
            {offers.map((offer, i) => (
              <OfferCard key={offer.id} offer={offer} index={i} />
            ))}
          </div>
        </div>
      </section>

      {/* ── STATISTICS ────────────────────────────────────── */}
      <section className="bg-dark py-20">
        <div className="container-custom">
          <div className="grid grid-cols-2 lg:grid-cols-4 gap-12">
            {statistics.map((stat) => (
              <StatCounter key={stat.id} stat={stat} />
            ))}
          </div>
        </div>
      </section>

      {/* ── CTA ───────────────────────────────────────────── */}
      <CTASection
        title="Book Your Perfect Stay Today"
        subtitle="Discover peaceful luxury accommodation in the heart of Point Pedro, Northern Sri Lanka."
        btnLabel="Book Now"
        btnPath="/booking"
        secondBtnLabel="View Rooms"
        secondBtnPath="/rooms"
      />

      {/* Lightbox */}
      {lightbox && (
        <motion.div
          initial={{ opacity: 0 }}
          animate={{ opacity: 1 }}
          exit={{ opacity: 0 }}
          className="fixed inset-0 bg-black/95 z-50 flex items-center justify-center p-4"
          onClick={() => setLightbox(null)}
        >
          <img
            src={lightbox}
            alt="Gallery"
            className="max-w-full max-h-full object-contain"
            onClick={(e) => e.stopPropagation()}
          />
          <button
            onClick={() => setLightbox(null)}
            className="absolute top-6 right-6 text-white text-3xl hover:text-gold transition-colors duration-200"
            aria-label="Close"
          >
            ×
          </button>
        </motion.div>
      )}
    </main>
  );
}
