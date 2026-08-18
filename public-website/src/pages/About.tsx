import { Link } from 'react-router-dom';
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
    title: 'Family Warmth',
    description: 'You are not a booking number here. From the moment you arrive, you are welcomed as part of the family and treated with genuine care throughout your stay.',
  },
  {
    icon: Shield,
    title: 'Always Here for You',
    description: 'Whatever you need, whenever you need it, our host is on hand around the clock. From a late arrival to a local tip, help is never more than a moment away.',
  },
  {
    icon: Star,
    title: 'Natural Tranquility',
    description: 'You slow down the moment you step into our garden. Under the shade of tall coconut trees, with the ocean breeze nearby, you find the kind of quiet that is hard to come by.',
  },
  {
    icon: Eye,
    title: 'Local Connection',
    description: 'You experience the real Point Pedro, not the tourist version. We guide you to family-run eateries and hidden gems most visitors never find, so you taste and see the north as we know it.',
  },
];

const whyChooseUs = [
  { label: 'Steps from the Coast', desc: 'Point Pedro Beach and the historic 1916 lighthouse are just a short stroll away, with the northern tip of Sri Lanka right on your doorstep.' },
  { label: 'A True Home Base', desc: 'Set beside the Point Pedro bus stand, we connect you easily to Jaffna town, the temples, and beyond. Our host is available around the clock to arrange a tuktuk, a bicycle or a driver whenever you need one.' },
  { label: 'Restored Heritage', desc: 'Sleep in a lovingly renovated century-old building where original character meets a spotless, modern, air-conditioned room.' },
  { label: 'Local Flavours at Your Door', desc: 'No standard hotel menu here. We recommend the best local eateries nearby, and our host is happy to collect a meal for you so you can dine in comfort and taste the real north.' },
  { label: 'Transparent & Fair', desc: 'No hidden fees and no surprises. What you see is what you pay, with honest local advice included.' },
  { label: 'Family Run, Deeply Rooted', desc: 'A family with real roots in Point Pedro, ready to share the hidden gems most visitors walk straight past.' },
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
                A Tale of<br />Two Homes
              </h2>
              <div className="w-10 h-[1px] bg-gold mb-6" />
              <p className="text-sm text-gray-500 leading-relaxed mb-5">
                Tulip Guest Inn was born from a blend of cultures and a deep love for Northern Sri Lanka. As a family with roots right here in Point Pedro and a second home in the Netherlands, we named the inn 'Tulip' to symbolize the bridge between our two worlds. We founded it with a simple idea: to create a place where guests experience the very best of the north, never as a standard tourist, but as a welcome friend.
              </p>
              <p className="text-sm text-gray-500 leading-relaxed mb-5">
                When we found our century-old heritage building, it was full of character but had seen better days. We chose to restore it with care rather than replace it, preserving the authentic architecture and history while quietly adding the comforts of a modern stay.
              </p>
              <p className="text-sm text-gray-500 leading-relaxed">
                Tranquility is at the heart of what we offer. Our spacious front garden is a true oasis, where guests unwind under the cooling shade of tall coconut trees, enjoying the gentle ocean breeze and the calm of the surroundings. Today, Tulip Guest Inn is a place where history and comfort meet, and where every guest leaves feeling truly at home. We would love to welcome you into our story.
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
                Authentic Heritage &amp; Genuine Comfort
              </h3>
              <div className="w-10 h-[1px] bg-gold mb-5" />
              <p className="text-gray-300 text-sm leading-relaxed">
                Our mission is to give every guest an authentic experience of Northern Sri Lanka. We bring together the charm of our restored heritage home, warm and personal service, and a deep respect for the culture and natural beauty of Point Pedro.
              </p>
            </AnimatedSection>
            <AnimatedSection direction="right" className="bg-background border border-border p-10 lg:p-14">
              <p className="text-[9px] tracking-[0.3em] uppercase text-gold font-medium mb-4">Our Vision</p>
              <h3 className="font-serif text-3xl font-light text-dark mb-5">
                A Home You Remember
              </h3>
              <div className="w-10 h-[1px] bg-gold mb-5" />
              <p className="text-gray-500 text-sm leading-relaxed">
                We envision Tulip Guest Inn as the most beloved heritage stay in the Jaffna Peninsula. A place that honors its roots, celebrates the local community and shows guests the real north: its history, its warmth and its quiet beauty. Not a hotel you pass through, but a home you remember.
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
