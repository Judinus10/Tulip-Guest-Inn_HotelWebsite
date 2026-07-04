import { Link } from 'react-router-dom';
import { Phone, Mail, MapPin, Instagram, Facebook, Twitter } from 'lucide-react';

const quickLinks = [
  { label: 'Home', path: '/' },
  { label: 'Our Rooms', path: '/rooms' },
  { label: 'Facilities', path: '/facilities' },
  { label: 'Gallery', path: '/gallery' },
  { label: 'Nearby Attractions', path: '/attractions' },
  { label: 'About Us', path: '/about' },
  { label: 'Contact', path: '/contact' },
];

const roomLinks = [
  { label: 'Standard Room', path: '/rooms/standard-room' },
  { label: 'Deluxe Room', path: '/rooms/deluxe-room' },
  { label: 'Family Room', path: '/rooms/family-room' },
  { label: 'Garden Suite', path: '/rooms/garden-suite' },
];

export default function Footer() {
  return (
    <footer className="bg-dark text-white">
      {/* Hero Strip */}
      <div className="relative h-56 overflow-hidden">
        <img
          src="https://images.pexels.com/photos/338504/pexels-photo-338504.jpeg?auto=compress&cs=tinysrgb&w=1600"
          alt="Tulip Guest Inn"
          className="w-full h-full object-cover opacity-40"
          loading="lazy"
        />
        <div className="absolute inset-0 bg-gradient-to-b from-transparent to-dark" />
        <div className="absolute inset-0 flex flex-col items-center justify-center">
          <p className="text-[10px] tracking-[0.35em] uppercase text-gold mb-3">Boutique Luxury</p>
          <h2 className="font-serif text-4xl lg:text-5xl text-white font-light text-center">
            Tulip Guest Inn
          </h2>
        </div>
      </div>

      {/* Main Footer */}
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-12">
          {/* About */}
          <div>
            <div className="flex flex-col leading-none mb-6">
              <span className="font-serif text-2xl font-light tracking-wide text-white">Tulip</span>
              <span className="text-[9px] tracking-[0.3em] uppercase text-gold">Guest Inn</span>
            </div>
            <p className="text-gray-400 text-sm leading-relaxed mb-6">
              A premium boutique guest house in Point Pedro, Northern Sri Lanka. Discover luxury accommodation, peaceful gardens and genuine Sri Lankan hospitality.
            </p>
            <div className="flex items-center gap-3">
              <a
                href="#"
                className="w-9 h-9 border border-white/20 flex items-center justify-center text-gray-400 hover:border-gold hover:text-gold transition-all duration-300"
                aria-label="Instagram"
              >
                <Instagram size={15} />
              </a>
              <a
                href="#"
                className="w-9 h-9 border border-white/20 flex items-center justify-center text-gray-400 hover:border-gold hover:text-gold transition-all duration-300"
                aria-label="Facebook"
              >
                <Facebook size={15} />
              </a>
              <a
                href="#"
                className="w-9 h-9 border border-white/20 flex items-center justify-center text-gray-400 hover:border-gold hover:text-gold transition-all duration-300"
                aria-label="Twitter"
              >
                <Twitter size={15} />
              </a>
            </div>
          </div>

          {/* Quick Links */}
          <div>
            <h3 className="text-[10px] tracking-[0.25em] uppercase text-gold mb-6 font-medium">
              Quick Links
            </h3>
            <ul className="space-y-3">
              {quickLinks.map((link) => (
                <li key={link.path}>
                  <Link
                    to={link.path}
                    className="text-sm text-gray-400 hover:text-white transition-colors duration-200 flex items-center gap-2 group"
                  >
                    <span className="w-4 h-[1px] bg-gold/40 group-hover:bg-gold transition-colors duration-200" />
                    {link.label}
                  </Link>
                </li>
              ))}
            </ul>
          </div>

          {/* Rooms */}
          <div>
            <h3 className="text-[10px] tracking-[0.25em] uppercase text-gold mb-6 font-medium">
              Our Rooms
            </h3>
            <ul className="space-y-3">
              {roomLinks.map((link) => (
                <li key={link.path}>
                  <Link
                    to={link.path}
                    className="text-sm text-gray-400 hover:text-white transition-colors duration-200 flex items-center gap-2 group"
                  >
                    <span className="w-4 h-[1px] bg-gold/40 group-hover:bg-gold transition-colors duration-200" />
                    {link.label}
                  </Link>
                </li>
              ))}
            </ul>

            <div className="mt-8">
              <h3 className="text-[10px] tracking-[0.25em] uppercase text-gold mb-4 font-medium">
                Newsletter
              </h3>
              <div className="flex">
                <input
                  type="email"
                  placeholder="Your email"
                  className="flex-1 bg-white/10 border border-white/20 px-4 py-2.5 text-xs text-white placeholder-gray-500 outline-none focus:border-gold transition-colors duration-200"
                />
                <button className="bg-gold px-4 py-2.5 text-white text-xs hover:bg-gold-dark transition-colors duration-200">
                  Join
                </button>
              </div>
            </div>
          </div>

          {/* Contact */}
          <div>
            <h3 className="text-[10px] tracking-[0.25em] uppercase text-gold mb-6 font-medium">
              Contact Us
            </h3>
            <ul className="space-y-4">
              <li className="flex items-start gap-3">
                <MapPin size={15} className="text-gold mt-0.5 shrink-0" />
                <span className="text-sm text-gray-400 leading-relaxed">
                  V.M Road 189<br />Point Pedro<br />Northern Sri Lanka
                </span>
              </li>
              <li className="flex items-center gap-3">
                <Phone size={15} className="text-gold shrink-0" />
                <a
                  href="tel:+94212261186"
                  className="text-sm text-gray-400 hover:text-white transition-colors duration-200"
                >
                  0212 261 186
                </a>
              </li>
              <li className="flex items-center gap-3">
                <Mail size={15} className="text-gold shrink-0" />
                <a
                  href="mailto:info@tulipguestinn.com"
                  className="text-sm text-gray-400 hover:text-white transition-colors duration-200"
                >
                  info@tulipguestinn.com
                </a>
              </li>
            </ul>

            <div className="mt-8 pt-6 border-t border-white/10">
              <p className="text-[10px] tracking-[0.15em] uppercase text-gray-500 mb-2">Opening Hours</p>
              <p className="text-sm text-gray-400">Reception: 24 Hours, 7 Days</p>
            </div>
          </div>
        </div>
      </div>

      {/* Bottom Bar */}
      <div className="border-t border-white/10">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-5 flex flex-col sm:flex-row items-center justify-between gap-3">
          <p className="text-xs text-gray-500">
            &copy; {new Date().getFullYear()} Tulip Guest Inn. All Rights Reserved.
          </p>
          <p className="text-xs text-gray-600">
            Point Pedro, Northern Sri Lanka
          </p>
        </div>
      </div>
    </footer>
  );
}
