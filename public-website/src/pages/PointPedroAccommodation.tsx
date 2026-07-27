import { Link } from 'react-router-dom';
import { BedDouble, Car, Clock3, MapPin, Users, Wifi } from 'lucide-react';
import PageHero from '../components/ui/PageHero';
import bannerRooms from '../assets/images/banners/banner-rooms.jpg';
import homeWelcome from '../assets/images/home/home-welcome-01.jpg';

const facts = [
  { icon: MapPin, title: 'Point Pedro Location', text: '189 V.M. Road, Point Pedro, Northern Province, Sri Lanka.' },
  { icon: Clock3, title: 'Stay Times', text: 'Check-in from 1:00 PM and check-out by 12:00 PM.' },
  { icon: BedDouble, title: 'Comfortable Rooms', text: 'Clean room options for solo travellers, couples and families.' },
  { icon: Wifi, title: 'Useful Facilities', text: 'Free WiFi, air conditioning and practical guest facilities.' },
  { icon: Car, title: 'On-site Parking', text: 'Free parking is available for guests travelling by vehicle.' },
  { icon: Users, title: 'Family Friendly', text: 'Family rooms and a peaceful setting for short or longer stays.' },
];

const faqs = [
  {
    question: 'Where is Tulip Guest Inn located?',
    answer: 'Tulip Guest Inn is at 189 V.M. Road, Point Pedro, Northern Province, Sri Lanka.',
  },
  {
    question: 'What are the check-in and check-out times?',
    answer: 'Check-in is available from 1:00 PM, and check-out is by 12:00 PM.',
  },
  {
    question: 'Can I book a room directly?',
    answer: 'Yes. View the available room options and use the booking page to make a direct reservation.',
  },
];

export default function PointPedroAccommodation() {
  return (
    <main>
      <PageHero
        title="Accommodation in Point Pedro"
        subtitle="Clean, comfortable rooms in Northern Province, Sri Lanka"
        image={bannerRooms}
      />

      <section className="section-padding bg-white">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 grid lg:grid-cols-2 gap-12 lg:gap-16 items-center">
          <div>
            <p className="text-[10px] tracking-[0.3em] uppercase text-gold mb-4">Stay in Point Pedro</p>
            <h2 className="font-serif text-4xl md:text-5xl font-light text-dark leading-tight mb-6">
              Rooms for a comfortable stay in Point Pedro
            </h2>
            <p className="text-gray-500 leading-relaxed mb-5">
              Tulip Guest Inn is a family-run guest house offering practical room accommodation at
              189 V.M. Road, Point Pedro. It is a convenient base for guests visiting Point Pedro and
              the surrounding Northern Province.
            </p>
            <p className="text-gray-500 leading-relaxed mb-8">
              Choose a room that suits your stay, review the available facilities, and book directly
              through the website. Check-in starts at 1:00 PM and check-out is by 12:00 PM.
            </p>
            <div className="flex flex-wrap gap-3">
              <Link to="/rooms" className="btn-primary">View Rooms</Link>
              <Link to="/booking" className="btn-outline">Book Direct</Link>
            </div>
          </div>
          <img
            src={homeWelcome}
            alt="Comfortable guest accommodation at Tulip Guest Inn in Point Pedro"
            className="w-full aspect-[4/3] object-cover shadow-luxury"
            width="1200"
            height="900"
            loading="lazy"
            decoding="async"
          />
        </div>
      </section>

      <section className="section-padding bg-cream">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="text-center max-w-2xl mx-auto mb-12">
            <p className="text-[10px] tracking-[0.3em] uppercase text-gold mb-4">Plan Your Stay</p>
            <h2 className="font-serif text-4xl font-light text-dark">What guests need to know</h2>
          </div>
          <div className="grid sm:grid-cols-2 lg:grid-cols-3 gap-6">
            {facts.map(({ icon: Icon, title, text }) => (
              <article key={title} className="bg-white border border-border p-7">
                <Icon className="text-gold mb-5" size={24} aria-hidden="true" />
                <h3 className="font-serif text-xl text-dark mb-3">{title}</h3>
                <p className="text-sm text-gray-500 leading-relaxed">{text}</p>
              </article>
            ))}
          </div>
        </div>
      </section>

      <section className="section-padding bg-white">
        <div className="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
          <p className="text-[10px] tracking-[0.3em] uppercase text-gold mb-4 text-center">Common Questions</p>
          <h2 className="font-serif text-4xl font-light text-dark text-center mb-10">Point Pedro stay FAQs</h2>
          <div className="space-y-4">
            {faqs.map((faq) => (
              <details key={faq.question} className="border border-border bg-cream p-6 group">
                <summary className="font-serif text-lg text-dark cursor-pointer">{faq.question}</summary>
                <p className="text-sm text-gray-500 leading-relaxed mt-4">{faq.answer}</p>
              </details>
            ))}
          </div>
          <div className="text-center mt-10">
            <Link to="/contact" className="text-gold text-xs uppercase tracking-[0.18em] hover:text-dark">
              Contact Tulip Guest Inn
            </Link>
          </div>
        </div>
      </section>
    </main>
  );
}
