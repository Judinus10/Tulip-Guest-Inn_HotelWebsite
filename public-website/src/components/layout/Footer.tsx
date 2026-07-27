import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { Phone, Mail, MapPin, Instagram, Facebook } from 'lucide-react';
import { getPublicContactSettings, fetchPublicRooms, type ContactSettings } from '../../services/publicApi';
import footerBackground from '../../assets/images/shared/footer-background.jpg';

const quickLinks = [
  { label: 'Home', path: '/' },
  { label: 'Rooms in Point Pedro', path: '/rooms' },
  { label: 'Guest House Facilities', path: '/facilities' },
  { label: 'Gallery', path: '/gallery' },
  { label: 'Nearby Attractions', path: '/attractions' },
  { label: 'About Us', path: '/about' },
  { label: 'Contact', path: '/contact' },
];

const fallbackRoomLinks = [
  { label: 'Standard Room', path: '/rooms/standard-room' },
  { label: 'Deluxe Room', path: '/rooms/deluxe-room' },
  { label: 'Family Room', path: '/rooms/family-room' },
  { label: 'Garden Suite', path: '/rooms/garden-suite' },
];

const fallbackContactSettings: ContactSettings = {
  business_name: 'Tulip Guest Inn',
  address: '189 V.M. Road\nPoint Pedro\nNorthern Province, Sri Lanka',
  phone: '0212 261 186',
  reception_contact_number: '0212 261 186',
  whatsapp_reservation_number: '',
  email: 'info@tulipguestinn.com',
  business_hours: 'Reception: 24 Hours, 7 Days',
  facebook_link: '#',
  instagram_link: '#',
  map_embed_url: '',
};

function telephoneHref(phone: string): string {
  const normalized = phone.replace(/[^+\d]/g, '');
  return normalized ? `tel:${normalized}` : '#';
}

function mailHref(email: string): string {
  return email ? `mailto:${email}` : '#';
}

function addressLines(address: string): string[] {
  return address
    .split(/\r?\n|,/)
    .map((line) => line.trim())
    .filter(Boolean);
}

export default function Footer() {
  const [contactSettings, setContactSettings] = useState<ContactSettings>(fallbackContactSettings);
  const [footerRoomLinks, setFooterRoomLinks] = useState(fallbackRoomLinks);

  useEffect(() => {
    let isMounted = true;

    getPublicContactSettings()
      .then((settings) => {
        if (isMounted && settings) {
          setContactSettings({ ...fallbackContactSettings, ...settings });
        }
      })
      .catch(() => {
        if (isMounted) setContactSettings(fallbackContactSettings);
      });

    fetchPublicRooms()
      .then((rooms) => {
        if (!isMounted || rooms.length === 0) return;

        setFooterRoomLinks(
          rooms.slice(0, 4).map((room) => ({
            label: room.name,
            path: `/rooms/${room.slug}`,
          }))
        );
      })
      .catch(() => {
        if (isMounted) setFooterRoomLinks(fallbackRoomLinks);
      });

    return () => {
      isMounted = false;
    };
  }, []);

  const businessName = contactSettings.business_name || fallbackContactSettings.business_name;
  const displayPhone = contactSettings.reception_contact_number || contactSettings.phone || fallbackContactSettings.phone;
  const displayEmail = contactSettings.email || fallbackContactSettings.email;
  const displayAddress = contactSettings.address || fallbackContactSettings.address;
  const openingHours = contactSettings.business_hours || fallbackContactSettings.business_hours;
  const footerLocation = addressLines(displayAddress).slice(-3).join(', ') || 'Point Pedro, Northern Province, Sri Lanka';

  return (
    <footer className="bg-dark text-white">
      {/* Hero Strip */}
      <div className="relative h-56 overflow-hidden">
        <img
          src={footerBackground}
          alt="Tulip Guest Inn"
          className="w-full h-full object-cover opacity-40"
          loading="lazy"
        />
        <div className="absolute inset-0 bg-gradient-to-b from-transparent to-dark" />
        <div className="absolute inset-0 flex flex-col items-center justify-center">
          <p className="text-[10px] tracking-[0.35em] uppercase text-gold mb-3">Boutique Luxury</p>
          <h2 className="font-serif text-4xl lg:text-5xl text-white font-light text-center">
            {businessName}
          </h2>
        </div>
      </div>

      {/* Main Footer */}
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-12">
          {/* About */}
          <div>
            <Link to="/" className="inline-block mb-6" aria-label="Tulip Guest Inn home">
              <img
                src="/assets/tulip-logo-white.png"
                alt="Tulip Guest Inn"
                className="h-16 w-auto object-contain"
              />
            </Link>
            <p className="text-gray-400 text-sm leading-relaxed mb-6">
              A family-run guest house offering clean, comfortable rooms at 189 V.M. Road, Point Pedro, Northern Province, Sri Lanka.
            </p>
            <div className="flex items-center gap-3">
              <a
                href={contactSettings.instagram_link || "#"}
                className="w-9 h-9 border border-white/20 flex items-center justify-center text-gray-400 hover:border-gold hover:text-gold transition-all duration-300"
                aria-label="Instagram"
                target={contactSettings.instagram_link ? "_blank" : undefined}
                rel={contactSettings.instagram_link ? "noreferrer" : undefined}
              >
                <Instagram size={15} />
              </a>
              <a
                href={contactSettings.facebook_link || "#"}
                className="w-9 h-9 border border-white/20 flex items-center justify-center text-gray-400 hover:border-gold hover:text-gold transition-all duration-300"
                aria-label="Facebook"
                target={contactSettings.facebook_link ? "_blank" : undefined}
                rel={contactSettings.facebook_link ? "noreferrer" : undefined}
              >
                <Facebook size={15} />
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
              {footerRoomLinks.map((link) => (
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
                  {addressLines(displayAddress).map((line, index) => (
                    <span key={line}>
                      {line}
                      {index < addressLines(displayAddress).length - 1 && <br />}
                    </span>
                  ))}
                </span>
              </li>
              <li className="flex items-center gap-3">
                <Phone size={15} className="text-gold shrink-0" />
                <a
                  href={telephoneHref(displayPhone)}
                  className="text-sm text-gray-400 hover:text-white transition-colors duration-200"
                >
                  {displayPhone}
                </a>
              </li>
              <li className="flex items-center gap-3">
                <Mail size={15} className="text-gold shrink-0" />
                <a
                  href={mailHref(displayEmail)}
                  className="text-sm text-gray-400 hover:text-white transition-colors duration-200"
                >
                  {displayEmail}
                </a>
              </li>
            </ul>

            <div className="mt-8 pt-6 border-t border-white/10">
              <p className="text-[10px] tracking-[0.15em] uppercase text-gray-500 mb-2">Opening Hours</p>
              <p className="text-sm text-gray-400">{openingHours}</p>
            </div>
          </div>
        </div>
      </div>

      {/* Bottom Bar */}
      <div className="border-t border-white/10">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-5 relative flex flex-col sm:flex-row items-center justify-between gap-3">
          <p className="text-xs text-gray-500">
            &copy; {new Date().getFullYear()} Tulip Guest Inn. All Rights Reserved.
          </p>

          <a
            href="#"
            className="sm:absolute sm:left-1/2 sm:-translate-x-1/2 flex items-center gap-3 text-[11px] text-gray-500 hover:text-gray-300 transition-colors duration-200"
            aria-label="Designed and developed by CompyX"
          >
            <span className="tracking-[0.08em]  whitespace-nowrap">Designed & Developed by</span>
            <img
              src="/assets/company_logo.png"
              alt="CompyX"
              className="h-9 sm:h-10 w-auto object-contain opacity-90"
              loading="lazy"
            />
          </a>

          <p className="text-xs text-gray-600">
            {footerLocation}
          </p>
        </div>
      </div>
    </footer>
  );
}
