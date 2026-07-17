import { Link } from 'react-router-dom';
import { motion } from 'framer-motion';
import { Waves, Wifi, Car, Wind, Bath, Monitor, Leaf, Sparkles, Clock, Sun } from 'lucide-react';
import PageHero from '../components/ui/PageHero';
import SectionTitle from '../components/ui/SectionTitle';
import FacilityCard from '../components/ui/FacilityCard';
import CTASection from '../components/ui/CTASection';
import AnimatedSection from '../components/ui/AnimatedSection';
import { facilities } from '../data/facilities';
import bannerFacilities from '../assets/images/banners/banner-facilities.jpg';
import facilitiesFeaturePool from '../assets/images/facilities/facilities-feature-pool.jpg';
import facilitiesFeatureGarden from '../assets/images/facilities/facilities-feature-garden.jpg';
import facilitiesFeatureComfort from '../assets/images/facilities/facilities-feature-comfort.jpg';

const iconMap: Record<string, React.ComponentType<{ size?: number; className?: string }>> = {
  Waves,
  Wifi,
  Car,
  Wind,
  Bath,
  Monitor,
  Leaf,
  Sparkles,
  Clock,
  Sun,
};

const alternatingFacilities = [
  {
    id: 'pool',
    title: 'Swimming Pool',
    description:
      'Our outdoor swimming pool is a serene oasis, surrounded by lush tropical greenery and comfortable sun loungers. Cool off after a day of exploring the Jaffna Peninsula or simply float in peace.',
    image: facilitiesFeaturePool,
  },
  {
    id: 'garden',
    title: 'Tropical Garden',
    description:
      'Wander through our beautifully maintained tropical garden — a green sanctuary in the heart of Point Pedro. Morning walks among the plants and birdsong make for the perfect start to any day.',
    image: facilitiesFeatureGarden,
  },
  {
    id: 'outdoor',
    title: 'Outdoor Terrace',
    description:
      'Our shaded outdoor terrace is the ideal place to unwind with a cup of tea and take in the tropical surroundings. Comfortable seating and gentle breezes make this a favourite spot for our guests.',
    image: facilitiesFeatureComfort,
  },
];

export default function Facilities() {
  return (
    <main>
      <PageHero
        title="Facilities"
        subtitle="Everything you need for a comfortable, peaceful and memorable stay."
        image={bannerFacilities}
        breadcrumb="Hotel Facilities"
      />

      {/* Facility Cards Grid */}
      <section className="section-padding bg-background">
        <div className="container-custom">
          <SectionTitle
            eyebrow="What We Offer"
            title="Our Amenities"
            subtitle="From our outdoor pool to complimentary WiFi, we have everything you need for a perfect stay."
          />
          <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-5">
            {facilities.map((facility, i) => (
              <FacilityCard key={facility.id} facility={facility} index={i} />
            ))}
          </div>
        </div>
      </section>

      {/* Alternating Sections */}
      <section className="bg-white">
        {alternatingFacilities.map((item, i) => {
          const isEven = i % 2 === 0;
          return (
            <div
              key={item.id}
              className="grid grid-cols-1 lg:grid-cols-2 min-h-[480px]"
            >
              {/* Image */}
              <div className={`relative overflow-hidden ${isEven ? 'lg:order-1' : 'lg:order-2'}`}>
                <motion.img
                  src={item.image}
                  alt={item.title}
                  className="w-full h-full object-cover min-h-[340px]"
                  whileInView={{ scale: 1 }}
                  initial={{ scale: 1.06 }}
                  viewport={{ once: true }}
                  transition={{ duration: 1.2, ease: 'easeOut' }}
                  loading="lazy"
                />
              </div>
              {/* Content */}
              <div className={`flex items-center px-10 lg:px-16 py-14 ${isEven ? 'lg:order-2' : 'lg:order-1'} ${i === 1 ? 'bg-background' : 'bg-white'}`}>
                <AnimatedSection direction={isEven ? 'right' : 'left'} className="max-w-lg">
                  <p className="text-[10px] tracking-[0.3em] uppercase text-gold font-medium mb-4">
                    Facilities
                  </p>
                  <h2 className="font-serif text-3xl lg:text-4xl font-light text-dark mb-5">
                    {item.title}
                  </h2>
                  <div className="w-10 h-[1px] bg-gold mb-5" />
                  <p className="text-sm text-gray-500 leading-relaxed mb-8">{item.description}</p>
                  <Link to="/booking" className="btn-dark">
                    Book Your Stay
                  </Link>
                </AnimatedSection>
              </div>
            </div>
          );
        })}
      </section>

      <CTASection
        title="Experience Our Facilities"
        subtitle="Book your stay at Tulip Guest Inn and enjoy all the comforts of our boutique property."
        btnLabel="Book Now"
        btnPath="/booking"
      />
    </main>
  );
}
