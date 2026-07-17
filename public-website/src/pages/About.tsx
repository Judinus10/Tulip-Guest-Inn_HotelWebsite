import { Link } from 'react-router-dom';
import { motion } from 'framer-motion';
import { Heart, Eye, Star, Shield } from 'lucide-react';
import PageHero from '../components/ui/PageHero';
import SectionTitle from '../components/ui/SectionTitle';
import CTASection from '../components/ui/CTASection';
import AnimatedSection from '../components/ui/AnimatedSection';
import bannerAbout from '../assets/images/banners/banner-about.jpg';
import aboutStory01 from '../assets/images/about/about-story-01.jpg';
import aboutStory02 from '../assets/images/about/about-story-02.jpg';
import aboutStory03 from '../assets/images/about/about-story-03.jpg';
import aboutStory04 from '../assets/images/about/about-story-04.jpg';
import aboutWhyChooseUs from '../assets/images/about/about-why-choose-us.jpg';

const values = [
  {
    icon: Heart,
    title: 'Genuine Hospitality',
    description: 'We welcome every guest as a friend, offering warm, personalised service that reflects the true spirit of Sri Lankan hospitality.',
  },
  {
    icon: Star,
    title: 'Boutique Quality',
    description: 'Every detail of our property — from the linens to the gardens — is maintained to the highest standards of boutique luxury.',
  },
  {
    icon: Shield,
    title: 'Trust & Comfort',
    description: 'Your safety, privacy and comfort are our absolute priority. We provide a secure, peaceful environment for all our guests.',
  },
  {
    icon: Eye,
    title: 'Mindful Experience',
    description: 'We are deeply connected to the beauty of Northern Sri Lanka and believe in providing experiences that are thoughtful and authentic.',
  },
];

const whyChooseUs = [
  { label: 'Prime Location', desc: 'Walking distance to Point Pedro Beach and major landmarks.' },
  { label: 'Boutique Scale', desc: 'Intimate, personalised service that larger hotels simply cannot match.' },
  { label: 'Premium Comfort', desc: 'Carefully curated rooms with quality furnishings and premium linens.' },
  { label: 'Transparent Pricing', desc: 'No hidden fees. What you see is what you pay.' },
  { label: 'Locally Owned', desc: 'A family-run property with deep roots in the Northern Sri Lanka community.' },
  { label: 'Sustainable Practices', desc: 'We care for our environment and community with responsible practices.' },
];

const galleryImages = [
  aboutStory01,
  aboutStory02,
  aboutStory03,
  aboutStory04,
];

export default function About() {
  return (
    <main>
      <PageHero
        title="Our Story"
        subtitle="A family-run boutique property dedicated to genuine luxury and Sri Lankan hospitality."
        image={bannerAbout}
        breadcrumb="About Us"
      />

      {/* Story Section */}
      <section className="section-padding bg-background">
        <div className="container-custom">
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-16 items-center">
            <AnimatedSection direction="left">
              <p className="text-[10px] tracking-[0.35em] uppercase text-gold font-medium mb-4">Our Story</p>
              <h2 className="font-serif text-4xl md:text-5xl font-light text-dark mb-5 leading-tight">
                A Passion for<br />Boutique Hospitality
              </h2>
              <div className="w-10 h-[1px] bg-gold mb-6" />
              <p className="text-sm text-gray-500 leading-relaxed mb-5">
                Tulip Guest Inn was founded with a simple but powerful vision: to create a space where guests could experience the very best of Northern Sri Lanka — its beauty, its culture and its warmth — without ever feeling like a tourist.
              </p>
              <p className="text-sm text-gray-500 leading-relaxed mb-5">
                Located in the historic town of Point Pedro at the northernmost tip of Sri Lanka, our property has grown from a small family guesthouse into a premium boutique inn celebrated for its elegantly appointed rooms, tranquil pool and genuine care for every guest.
              </p>
              <p className="text-sm text-gray-500 leading-relaxed">
                Today, Tulip Guest Inn stands as one of the finest boutique accommodation options in the Jaffna Peninsula — a place where luxury meets authenticity, and where every guest leaves feeling truly at home.
              </p>
            </AnimatedSection>

            <AnimatedSection direction="right" className="grid grid-cols-2 gap-3">
              {galleryImages.map((src, i) => (
                <div key={i} className="overflow-hidden shadow-luxury">
                  <img
                    src={src}
                    alt={`Tulip Guest Inn ${i + 1}`}
                    className="w-full h-48 object-cover hover:scale-105 transition-transform duration-700"
                    loading="lazy"
                  />
                </div>
              ))}
            </AnimatedSection>
          </div>
        </div>
      </section>

      {/* Mission & Vision */}
      <section className="section-padding bg-white">
        <div className="container-custom">
          <div className="grid grid-cols-1 md:grid-cols-2 gap-8">
            <AnimatedSection direction="left" className="bg-deep-green p-10 lg:p-14">
              <p className="text-[9px] tracking-[0.3em] uppercase text-gold font-medium mb-4">Our Mission</p>
              <h3 className="font-serif text-3xl font-light text-white mb-5">
                To Deliver Genuine Luxury
              </h3>
              <div className="w-10 h-[1px] bg-gold mb-5" />
              <p className="text-gray-300 text-sm leading-relaxed">
                Our mission is to provide every guest with a premium boutique experience in Northern Sri Lanka — combining elegant accommodation, warm personalised service and a deep respect for the culture and natural beauty of our region.
              </p>
            </AnimatedSection>
            <AnimatedSection direction="right" className="bg-background border border-border p-10 lg:p-14">
              <p className="text-[9px] tracking-[0.3em] uppercase text-gold font-medium mb-4">Our Vision</p>
              <h3 className="font-serif text-3xl font-light text-dark mb-5">
                Sri Lanka's Finest Boutique Stay
              </h3>
              <div className="w-10 h-[1px] bg-gold mb-5" />
              <p className="text-gray-500 text-sm leading-relaxed">
                We envision Tulip Guest Inn as the definitive luxury boutique destination in the Jaffna Peninsula — a property that sets the standard for thoughtful hospitality, sustainable tourism and authentic Sri Lankan excellence.
              </p>
            </AnimatedSection>
          </div>
        </div>
      </section>

      {/* Values */}
      <section className="section-padding bg-background">
        <div className="container-custom">
          <SectionTitle
            eyebrow="What Drives Us"
            title="Our Values"
            subtitle="The principles that guide every aspect of what we do at Tulip Guest Inn."
          />
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
            {values.map((value, i) => (
              <AnimatedSection
                key={value.title}
                delay={i * 0.1}
                className="bg-white border border-border p-8 text-center hover:border-gold hover:shadow-luxury transition-all duration-400"
              >
                <div className="w-14 h-14 border border-gold/30 flex items-center justify-center mx-auto mb-5">
                  <value.icon size={22} className="text-gold" />
                </div>
                <h4 className="font-serif text-xl font-light text-dark mb-3">{value.title}</h4>
                <div className="w-8 h-[1px] bg-gold mx-auto mb-4" />
                <p className="text-sm text-gray-500 leading-relaxed">{value.description}</p>
              </AnimatedSection>
            ))}
          </div>
        </div>
      </section>

      {/* Why Choose Us */}
      <section className="section-padding bg-white">
        <div className="container-custom">
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-16 items-center">
            <AnimatedSection direction="left">
              <div className="relative overflow-hidden shadow-luxury-lg">
                <img
                  src={aboutWhyChooseUs}
                  alt="Tulip Guest Inn"
                  className="w-full h-96 object-cover"
                  loading="lazy"
                />
              </div>
            </AnimatedSection>
            <div>
              <AnimatedSection direction="right">
                <p className="text-[10px] tracking-[0.35em] uppercase text-gold font-medium mb-4">Reasons to Stay</p>
                <h2 className="font-serif text-4xl font-light text-dark mb-5">Why Guests Choose Us</h2>
                <div className="w-10 h-[1px] bg-gold mb-8" />
                <div className="space-y-5">
                  {whyChooseUs.map((item) => (
                    <div key={item.label} className="flex gap-4">
                      <div className="w-1.5 h-1.5 rounded-full bg-gold mt-2 shrink-0" />
                      <div>
                        <h4 className="font-medium text-sm text-dark mb-1">{item.label}</h4>
                        <p className="text-sm text-gray-500 leading-relaxed">{item.desc}</p>
                      </div>
                    </div>
                  ))}
                </div>
                <div className="mt-8">
                  <Link to="/booking" className="btn-dark">
                    Book Your Stay
                  </Link>
                </div>
              </AnimatedSection>
            </div>
          </div>
        </div>
      </section>

      <CTASection
        title="Come and Experience It Yourself"
        subtitle="Reserve your room at Tulip Guest Inn and discover why our guests keep coming back."
        btnLabel="Book Now"
        btnPath="/booking"
        secondBtnLabel="Contact Us"
        secondBtnPath="/contact"
      />
    </main>
  );
}
