import { Link } from 'react-router-dom';
import { motion } from 'framer-motion';
import PageHero from '../components/ui/PageHero';
import SectionTitle from '../components/ui/SectionTitle';
import FacilityCard from '../components/ui/FacilityCard';
import CTASection from '../components/ui/CTASection';
import AnimatedSection from '../components/ui/AnimatedSection';
import { facilities } from '../data/facilities';
import bannerFacilities from '../assets/images/banners/banner-facilities.jpg';
import facilitiesFeatureGarden from '../assets/images/facilities/facilities-feature-garden.jpg';
import facilitiesFeatureComfort from '../assets/images/facilities/facilities-feature-comfort.jpg';
import facilitiesFeatureKitchen from '../assets/images/facilities/facilities-feature-kitchen.jpg';

const alternatingFacilities = [
  {
    id: 'garden',
    title: 'Peaceful Garden',
    description:
      'Our spacious garden is shaded by tall coconut trees and offers comfortable seating for quiet moments. It is a peaceful place to slow down, enjoy the ocean breeze and unwind after exploring Point Pedro.',
    image: facilitiesFeatureGarden,
  },
  {
    id: 'family-rooms',
    title: 'Clean, Fresh Family Rooms',
    description:
      'Our comfortable family rooms sleep up to three guests and each includes an en suite bathroom. Every room is prepared spotless on arrival, most include a flat screen TV, and housekeeping is available during your stay on request.',
    image: facilitiesFeatureComfort,
  },
  {
    id: 'shared-kitchen',
    title: 'Shared Kitchen',
    description:
      'A shared kitchen is available for guests who prefer to prepare their own meals, giving you extra flexibility and a comfortable, practical option during your stay.',
    image: facilitiesFeatureKitchen,
  },
];

export default function Facilities() {
  return (
    <main>
      <PageHero
        title="Guest House Facilities"
        subtitle="Everything you need for a comfortable stay at Tulip Guest Inn in Point Pedro."
        image={bannerFacilities}
        breadcrumb="Point Pedro Accommodation"
      />

      {/* Facility Cards Grid */}
      <section className="section-padding bg-background">
        <div className="container-custom">
          <SectionTitle
            eyebrow="What We Offer"
            title="Our Amenities"
            subtitle="From free WiFi and a shared kitchen to a peaceful garden and a host who is always on hand, we have the essentials for a restful, easy stay in Point Pedro."
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
