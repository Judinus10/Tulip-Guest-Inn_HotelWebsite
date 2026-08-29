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
  { label: 'Point Pedro Accommodation', path: '/accommodation-point-pedro' },
  { label: 'About Us', path: '/about' },
  { label: 'Contact', path: '/contact' },
];

const fallbackContactSettings: ContactSettings = {
  business_name: 'Tulip Guest Inn',
  address: '189 V.M. Road\nPoint Pedro\nNorthern Province, Sri Lanka',
  phone: '0212 261 186',
  reception_contact_number: '0212 261 186',
  whatsapp_reservation_number: '',
  email: 'info@tulipguestinn.com',
  business_hours: 'Reception: 24 Hours, 7 Days',
  business_hours_mode: '24_7',
  business_hours_schedule: '{}',
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

export default function Footer() {
  const [contactSettings, setContactSettings] = useState<ContactSettings>(fallbackContactSettings);
  const [footerRoomLinks, setFooterRoomLinks] = useState<Array<{ label: string; path: string }>>([]);

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
        if (!isMounted) return;

        setFooterRoomLinks(
          rooms.slice(0, 8).map((room) => ({
            label: room.name,
            path: `/rooms/${room.slug}`,
          }))
        );
      })
      .catch(() => {
        if (isMounted) setFooterRoomLinks([]);
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
              A family-run guest house offering clean, comfortable accommodation and thoughtful service in Point Pedro.
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

          </div>

          {/* Contact */}
          <div>
            <h3 className="text-[10px] tracking-[0.25em] uppercase text-gold mb-6 font-medium">
              Contact Us
            </h3>
            <ul className="space-y-4">
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
              <li className="flex items-start gap-3">
                <MapPin size={15} className="mt-0.5 text-gold shrink-0" />
                <p className="whitespace-pre-line text-sm leading-relaxed text-gray-400">
                  {displayAddress}
                </p>
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
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-5 grid grid-cols-1 items-center gap-4 text-center sm:grid-cols-3 sm:text-left">
          <p className="text-xs text-gray-500">
            &copy; {new Date().getFullYear()} Tulip Guest Inn. All Rights Reserved.
          </p>

          <a
            href="#"
            className="flex items-center justify-center gap-3 text-[11px] text-gray-500 hover:text-gray-300 transition-colors duration-200"
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

          <div className="flex flex-wrap items-center justify-center gap-x-4 gap-y-2 text-xs text-gray-500 sm:justify-end">
            <Link to="/privacy-policy" className="hover:text-white transition-colors duration-200">
              Privacy Policy
            </Link>
            <Link to="/terms-and-conditions" className="hover:text-white transition-colors duration-200">
              Terms &amp; Conditions
            </Link>
          </div>
        </div>
      </div>
    </footer>
  );
}
