import { useEffect, useMemo, useState } from "react";
import { Link } from "react-router-dom";
import { motion } from "framer-motion";
import {
  ChevronDown,
  Wifi,
  Car,
  CookingPot,
  Users,
  Shield,
  Coffee,
} from "lucide-react";
import SectionTitle from "../components/ui/SectionTitle";
import AnimatedSection from "../components/ui/AnimatedSection";
import BookingBar from "../components/ui/BookingBar";
import RoomCard from "../components/ui/RoomCard";
import FacilityCard from "../components/ui/FacilityCard";
import TestimonialSlider from "../components/ui/TestimonialSlider";
import OfferCard from "../components/ui/OfferCard";
import StatCounter from "../components/ui/StatCounter";
import CTASection from "../components/ui/CTASection";
import AttractionCard from "../components/ui/AttractionCard";
import { rooms } from "../data/rooms";
import { facilities } from "../data/facilities";
import { galleryImages } from "../data/gallery";
import { statistics } from "../data/statistics";
import { attractions } from "../data/attractions";
import {
  getPublicAttractions,
  getPublicGalleryImages,
  getPublicOffers,
  getPublicRooms,
  getPublicHomeStatistics,
} from "../services/publicApi";
import homeHero01 from "../assets/images/home/home-hero-01.jpg";
import homeHero02 from "../assets/images/home/home-hero-02.jpg";
import homeHero03 from "../assets/images/home/home-hero-03.jpg";
import homeWelcome01 from "../assets/images/home/home-welcome-01.jpg";
import homeWelcome02 from "../assets/images/home/home-welcome-02.jpg";
import homeWelcome03 from "../assets/images/home/home-welcome-03.jpg";
import homeLuxuryExperience from "../assets/images/home/home-luxury-experience.jpg";

const heroImages = [
  homeHero01,
  homeHero02,
  homeHero03,
];

const welcomeFeatures = [
  { icon: CookingPot, label: "Shared Kitchen" },
  { icon: Wifi, label: "Free WiFi" },
  { icon: Car, label: "Free Parking" },
  { icon: Users, label: "Family Friendly" },
  { icon: Shield, label: "24-Hr Security" },
  { icon: Coffee, label: "Peaceful Garden" },
];

const galleryCategoryOrder = [
  "rooms",
  "pool",
  "garden",
  "exterior",
  "facilities",
] as const;

type HomeGalleryImage = (typeof galleryImages)[number];

function normalizeGalleryCategory(
  folderSlug?: string,
  folderName?: string,
): HomeGalleryImage["category"] {
  const value = `${folderSlug ?? ""} ${folderName ?? ""}`.toLowerCase();

  if (value.includes("room")) return "rooms";
  if (value.includes("pool") || value.includes("swim")) return "pool";
  if (value.includes("garden")) return "garden";
  if (
    value.includes("exterior") ||
    value.includes("outside") ||
    value.includes("front")
  )
    return "exterior";
  return "facilities";
}

function pickRandomGalleryPreview(
  images: HomeGalleryImage[],
  limit = 10,
): HomeGalleryImage[] {
  if (images.length <= limit) return images;

  const selected: HomeGalleryImage[] = [];
  const selectedIds = new Set<string>();

  galleryCategoryOrder.forEach((category) => {
    const categoryImages = images.filter(
      (image) => image.category === category,
    );
    if (categoryImages.length === 0) return;

    const randomImage =
      categoryImages[Math.floor(Math.random() * categoryImages.length)];
    selected.push(randomImage);
    selectedIds.add(randomImage.id);
  });

  const remainingImages = images.filter((image) => !selectedIds.has(image.id));
  const shuffledRemaining = [...remainingImages].sort(
    () => Math.random() - 0.5,
  );

  return [...selected, ...shuffledRemaining].slice(0, limit);
}

export default function Home() {
  const [heroIndex, setHeroIndex] = useState(0);
  const [lightbox, setLightbox] = useState<string | null>(null);
  const [homeRooms, setHomeRooms] = useState(rooms);
  const [homeGalleryImages, setHomeGalleryImages] = useState(galleryImages);
  const [homeAttractions, setHomeAttractions] = useState(attractions);
  const [homeOffers, setHomeOffers] = useState<Awaited<ReturnType<typeof getPublicOffers>>>([]);
  const [homeStatistics, setHomeStatistics] = useState(statistics);

  const previewImages = useMemo(
    () => pickRandomGalleryPreview(homeGalleryImages, 10),
    [homeGalleryImages],
  );
  const previewGroups = useMemo(() => {
    const groups: HomeGalleryImage[][] = [];
    previewImages.forEach((image, index) => {
      const groupIndex = Math.floor(index / 5);
      if (!groups[groupIndex]) groups[groupIndex] = [];
      groups[groupIndex].push(image);
    });
    return groups;
  }, [previewImages]);
  const featuredRooms = homeRooms.filter((r) => r.featured).slice(0, 3);
  const activeHeroImages = heroImages;

  useEffect(() => {
    const interval = setInterval(() => {
      setHeroIndex((prev) => (prev + 1) % activeHeroImages.length);
    }, 7000);
    return () => clearInterval(interval);
  }, [activeHeroImages.length]);

  useEffect(() => {
    let isMounted = true;

    async function loadHomeData() {
      const [roomsResult, galleryResult, attractionsResult, offersResult, statisticsResult] =
        await Promise.allSettled([
          getPublicRooms(),
          getPublicGalleryImages(),
          getPublicAttractions(),
          getPublicOffers(),
          getPublicHomeStatistics(),
        ]);

      if (!isMounted) return;

      if (roomsResult.status === "fulfilled" && roomsResult.value.length > 0) {
        setHomeRooms(roomsResult.value);
      }

      if (
        galleryResult.status === "fulfilled" &&
        galleryResult.value.length > 0
      ) {
        const normalizedGalleryImages = galleryResult.value
          .filter(
            (img) =>
              typeof img.image_path === "string" &&
              img.image_path.trim() !== "",
          )
          .map((img, index) => ({
            id: String(img.id ?? `api-gallery-${index}`),
            src: img.image_path,
            alt: img.title || img.folder_name || "Tulip Guest Inn Gallery",
            category: normalizeGalleryCategory(
              img.folder_slug,
              img.folder_name,
            ),
            width: (index % 5 === 0 ? "wide" : "normal") as "wide" | "normal",
          }));

        if (normalizedGalleryImages.length > 0) {
          setHomeGalleryImages(normalizedGalleryImages);
        }
      }

      if (
        attractionsResult.status === "fulfilled" &&
        attractionsResult.value.length > 0
      ) {
        setHomeAttractions(attractionsResult.value);
      }

      setHomeOffers(offersResult.status === "fulfilled" ? offersResult.value : []);

      if (statisticsResult.status === "fulfilled" && statisticsResult.value.length > 0) {
        setHomeStatistics(statisticsResult.value);
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
            key={`${src}-${i}`}
            className="absolute inset-0"
            initial={{ opacity: 0 }}
            animate={{ opacity: i === heroIndex ? 1 : 0 }}
            transition={{ duration: 1.5, ease: "easeInOut" }}
          >
            <motion.img
              src={src}
              alt="Tulip Guest Inn"
              className="w-full h-full object-cover"
              animate={{ scale: i === heroIndex ? 1.07 : 1 }}
              transition={{ duration: 8, ease: "easeOut" }}
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
            Guest House &amp; Rooms in
            <br />
            <span className="text-gold italic">Point Pedro</span>
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
            Experience peaceful accommodation, modern comfort and genuine Sri
            Lankan hospitality.
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
            <Link
              to="/rooms"
              className="btn-outline border-white text-white hover:bg-white hover:text-dark"
            >
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
          <p className="text-[9px] tracking-[0.3em] uppercase text-white/40">
            Scroll
          </p>
        </motion.div>

        {/* Hero Dots */}
        <div className="absolute bottom-8 right-8 flex gap-2">
          {activeHeroImages.map((_, i) => (
            <button
              key={i}
              onClick={() => setHeroIndex(i)}
              className={`transition-all duration-400 ${
                i === heroIndex
                  ? "w-8 h-1.5 bg-gold"
                  : "w-1.5 h-1.5 rounded-full bg-white/40 hover:bg-white/70"
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
                  Welcome to
                  <br />
                  Tulip Guest Inn
                </h2>
                <div className="gold-line-left" />
                <p className="text-sm text-gray-500 leading-relaxed mb-5">
                  Located at 189 V.M. Road in Point Pedro, Northern Province,
                  Tulip Guest Inn is a family-run guest house designed for
                  guests who appreciate genuine comfort, thoughtful service and
                  serene surroundings.
                </p>
                <p className="text-sm text-gray-500 leading-relaxed mb-8">
                  From our practical shared kitchen and lush tropical gardens to
                  our elegantly appointed rooms, every detail has been carefully
                  considered to ensure a stay that is as restful as it is
                  memorable.
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
            <AnimatedSection
              direction="right"
              className="relative h-[480px] hidden lg:block"
            >
              <div className="absolute top-0 right-0 w-64 h-72 overflow-hidden shadow-luxury-lg">
                <img
                  src={homeWelcome01}
                  alt="Deluxe Room"
                  className="w-full h-full object-cover hover:scale-105 transition-transform duration-700"
                  loading="lazy"
                />
              </div>
              <div className="absolute bottom-0 right-16 w-72 h-52 overflow-hidden shadow-luxury-lg">
                <img
                  src={homeWelcome02}
                  alt="Tulip Guest Inn guest area"
                  className="w-full h-full object-cover hover:scale-105 transition-transform duration-700"
                  loading="lazy"
                />
              </div>
              <div className="absolute top-20 left-0 w-52 h-64 overflow-hidden shadow-luxury-lg">
                <img
                  src={homeWelcome03}
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
              src={homeLuxuryExperience}
              alt="Tulip Guest Inn garden and guest facilities"
              className="w-full h-full object-cover min-h-[400px]"
              whileInView={{ scale: 1 }}
              initial={{ scale: 1.08 }}
              viewport={{ once: true }}
              transition={{ duration: 1.2, ease: "easeOut" }}
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
                Relax, Unwind and
                <br />
                Feel at Home
              </h2>
              <div className="w-12 h-[1px] bg-gold mb-6" />
              <p className="text-gray-300 text-sm leading-relaxed mb-8">
                Escape the busy city and enjoy comfortable accommodation
                surrounded by peaceful gardens and modern facilities. Our
                comfortable rooms, shaded terraces and lush gardens create a
                sanctuary of calm in the heart of Point Pedro.
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
          <SectionTitle eyebrow="Photo Gallery" title="A Glimpse of Tulip" />
          <div className="space-y-3">
            {previewGroups.map((group, groupIndex) => {
              const big = group[0];
              const small = group.slice(1);
              const bigOnLeft = groupIndex % 2 === 1;
              return <motion.div
                key={group.map((image) => image.id).join("-")}
                initial={{ opacity: 0, scale: 0.97 }}
                whileInView={{ opacity: 1, scale: 1 }}
                viewport={{ once: true, margin: "-40px" }}
                transition={{ duration: 0.5 }}
                className="grid grid-cols-1 gap-3 md:grid-cols-2"
              >
                {big && <button type="button" onClick={() => setLightbox(big.src)} className={`group relative aspect-square overflow-hidden ${bigOnLeft ? "md:order-1" : "md:order-2"}`}>
                  <img src={big.src} alt={big.alt} className="h-full w-full object-cover transition-transform duration-700 group-hover:scale-105" loading="lazy" />
                  <span className="absolute inset-0 bg-black/0 transition-colors duration-300 group-hover:bg-black/30" />
                </button>}
                <div className={`grid aspect-square grid-cols-2 grid-rows-2 gap-3 ${bigOnLeft ? "md:order-2" : "md:order-1"}`}>
                  {small.map((img) => <button type="button" key={img.id} onClick={() => setLightbox(img.src)} className="group relative min-h-0 overflow-hidden">
                    <img src={img.src} alt={img.alt} className="h-full w-full object-cover transition-transform duration-700 group-hover:scale-110" loading="lazy" />
                    <span className="absolute inset-0 bg-black/0 transition-colors duration-300 group-hover:bg-black/30" />
                  </button>)}
                </div>
              </motion.div>;
            })}
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
              <AttractionCard
                key={attraction.id}
                attraction={attraction}
                index={i}
              />
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
      {homeOffers.length > 0 && <section className="section-padding bg-background">
        <div className="container-custom">
          <SectionTitle
            eyebrow="Special Offers"
            title="Exclusive Packages"
            subtitle="Take advantage of our curated offers designed to make your stay even more special."
          />
          <div className="grid grid-cols-1 md:grid-cols-3 gap-8">
            {homeOffers.map((offer, i) => (
              <OfferCard key={offer.id} offer={offer} index={i} />
            ))}
          </div>
        </div>
      </section>}

      {/* ── STATISTICS ────────────────────────────────────── */}
      <section className="bg-dark py-20">
        <div className="container-custom">
          <div className="grid grid-cols-2 lg:grid-cols-4 gap-12">
            {homeStatistics.map((stat) => (
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
