import { motion } from 'framer-motion';
import { MapPin } from 'lucide-react';
import PageHero from '../components/ui/PageHero';
import SectionTitle from '../components/ui/SectionTitle';
import AttractionCard from '../components/ui/AttractionCard';
import CTASection from '../components/ui/CTASection';
import AnimatedSection from '../components/ui/AnimatedSection';
import { attractions } from '../data/attractions';

export default function Attractions() {
  return (
    <main>
      <PageHero
        title="Nearby Attractions"
        subtitle="Discover the natural beauty, history and culture of Northern Sri Lanka from our doorstep."
        image="https://images.pexels.com/photos/1032650/pexels-photo-1032650.jpeg?auto=compress&cs=tinysrgb&w=1600"
        breadcrumb="Explore"
      />

      {/* Intro */}
      <section className="section-padding bg-white">
        <div className="container-custom">
          <div className="max-w-3xl mx-auto text-center">
            <AnimatedSection>
              <p className="text-[10px] tracking-[0.35em] uppercase text-gold font-medium mb-4">Location</p>
              <h2 className="font-serif text-4xl md:text-5xl font-light text-dark mb-5">
                Point Pedro & Beyond
              </h2>
              <div className="w-12 h-[1px] bg-gold mx-auto mb-6" />
              <p className="text-sm text-gray-500 leading-relaxed">
                Tulip Guest Inn sits at the northernmost tip of Sri Lanka, placing you at the heart of one of the country's most fascinating and unspoiled regions. Ancient temples, pristine beaches, sacred springs and historic landmarks are all within easy reach.
              </p>
            </AnimatedSection>
          </div>
        </div>
      </section>

      {/* Attraction Cards */}
      <section className="section-padding bg-background">
        <div className="container-custom">
          <SectionTitle
            eyebrow="Places to Visit"
            title="Attractions Near Us"
          />
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            {attractions.map((attraction, i) => (
              <AttractionCard key={attraction.id} attraction={attraction} index={i} />
            ))}
          </div>
        </div>
      </section>

      {/* Map Section */}
      <section className="section-padding bg-white">
        <div className="container-custom">
          <SectionTitle
            eyebrow="Find Us"
            title="Our Location"
            subtitle="Conveniently located on V.M Road in Point Pedro, with easy access to all major attractions."
          />
          <AnimatedSection className="relative overflow-hidden shadow-luxury-lg border border-border">
            <div className="bg-background h-[420px] flex items-center justify-center relative">
              {/* Map Placeholder */}
              <div className="absolute inset-0">
                <img
                  src="https://images.pexels.com/photos/1032650/pexels-photo-1032650.jpeg?auto=compress&cs=tinysrgb&w=1200"
                  alt="Point Pedro Location"
                  className="w-full h-full object-cover opacity-20"
                  loading="lazy"
                />
              </div>
              <div className="relative z-10 text-center">
                <div className="w-16 h-16 bg-gold flex items-center justify-center mx-auto mb-5 shadow-gold">
                  <MapPin size={28} className="text-white" />
                </div>
                <h3 className="font-serif text-2xl font-light text-dark mb-2">Tulip Guest Inn</h3>
                <p className="text-sm text-gray-500 mb-1">V.M Road 189, Point Pedro</p>
                <p className="text-sm text-gray-500 mb-6">Northern Sri Lanka</p>
                <a
                  href="https://maps.google.com/?q=Point+Pedro+Sri+Lanka"
                  target="_blank"
                  rel="noopener noreferrer"
                  className="btn-dark"
                >
                  Open in Google Maps
                </a>
              </div>
            </div>

            {/* Distance Info */}
            <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 divide-x divide-border bg-white border-t border-border">
              {attractions.map((a) => (
                <div key={a.id} className="p-5 text-center">
                  <p className="font-serif text-lg font-light text-dark mb-1">{a.distance}</p>
                  <p className="text-[9px] tracking-[0.15em] uppercase text-gray-400 leading-tight">{a.name}</p>
                </div>
              ))}
            </div>
          </AnimatedSection>
        </div>
      </section>

      <CTASection
        title="Stay in the Heart of It All"
        subtitle="Book your stay at Tulip Guest Inn and explore everything Northern Sri Lanka has to offer."
        btnLabel="Book Your Stay"
        btnPath="/booking"
      />
    </main>
  );
}
