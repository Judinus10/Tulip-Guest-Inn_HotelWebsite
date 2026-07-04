import { useEffect, useMemo, useState } from 'react';
import { Phone, Mail, MapPin, Clock, Send, Instagram, Facebook } from 'lucide-react';
import PageHero from '../components/ui/PageHero';
import AnimatedSection from '../components/ui/AnimatedSection';
import { getPublicContactSettings, submitContactMessage, type ContactSettings } from '../services/publicApi';

const contactCards = [
  {
    icon: Phone,
    label: 'Phone',
    value: '0212 261 186',
    subValue: '+94 212 261 186',
    href: 'tel:+94212261186',
  },
  {
    icon: Mail,
    label: 'Email',
    value: 'info@tulipguestinn.com',
    href: 'mailto:info@tulipguestinn.com',
  },
  {
    icon: MapPin,
    label: 'Address',
    value: 'V.M Road 189',
    subValue: 'Point Pedro, Northern Sri Lanka',
  },
  {
    icon: Clock,
    label: 'Reception',
    value: '24 Hours',
    subValue: '7 Days a Week',
  },
];

const openingHours = [
  { day: 'Monday – Friday', time: 'Open 24 Hours' },
  { day: 'Saturday', time: 'Open 24 Hours' },
  { day: 'Sunday', time: 'Open 24 Hours' },
];

const fallbackContactSettings: ContactSettings = {
  business_name: 'Tulip Guest Inn',
  address: 'V.M Road 189, Point Pedro, Northern Sri Lanka',
  phone: '+94 212 261 186',
  reception_contact_number: '+94 212 261 186',
  whatsapp_reservation_number: '+94 212 261 186',
  email: 'info@tulipguestinn.com',
  business_hours: 'Open 24 Hours',
  facebook_link: '',
  instagram_link: '',
  map_embed_url: '',
};

function cleanPhoneForLink(phone: string) {
  return phone.replace(/[^+0-9]/g, '');
}

function isValidUrl(url: string) {
  try {
    const parsed = new URL(url);
    return parsed.protocol === 'http:' || parsed.protocol === 'https:';
  } catch {
    return false;
  }
}

function getMapOpenUrl(address: string, mapUrl: string) {
  const cleanMapUrl = mapUrl.trim();
  return isValidUrl(cleanMapUrl) ? cleanMapUrl : `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(address)}`;
}

function getMapEmbedUrl(address: string, mapUrl: string) {
  const cleanMapUrl = mapUrl.trim();

  if (!isValidUrl(cleanMapUrl)) {
    return `https://www.google.com/maps?q=${encodeURIComponent(address)}&output=embed`;
  }

  try {
    const parsed = new URL(cleanMapUrl);
    const url = parsed.toString();

    if (url.includes('/maps/embed') || parsed.searchParams.get('output') === 'embed') {
      return url;
    }

    if (parsed.hostname.includes('google') && parsed.pathname.includes('/maps')) {
      parsed.searchParams.set('output', 'embed');
      return parsed.toString();
    }

    const query = parsed.searchParams.get('q') || parsed.searchParams.get('query');
    if (query) {
      return `https://www.google.com/maps?q=${encodeURIComponent(query)}&output=embed`;
    }

    return `https://www.google.com/maps?q=${encodeURIComponent(address)}&output=embed`;
  } catch {
    return `https://www.google.com/maps?q=${encodeURIComponent(address)}&output=embed`;
  }
}

export default function Contact() {
  const [form, setForm] = useState({
    name: '',
    email: '',
    phone: '',
    subject: '',
    message: '',
  });
  const [submitted, setSubmitted] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [settings, setSettings] = useState<ContactSettings>(fallbackContactSettings);

  useEffect(() => {
    let active = true;

    getPublicContactSettings()
      .then((data) => {
        if (!active) return;
        setSettings({ ...fallbackContactSettings, ...data });
      })
      .catch(() => {
        if (!active) return;
        setSettings(fallbackContactSettings);
      });

    return () => {
      active = false;
    };
  }, []);

  const activePhone = settings.phone || settings.reception_contact_number || fallbackContactSettings.phone;
  const activeEmail = settings.email || fallbackContactSettings.email;
  const activeAddress = settings.address || fallbackContactSettings.address;
  const activeHours = settings.business_hours || fallbackContactSettings.business_hours;
  const phoneHref = `tel:${cleanPhoneForLink(activePhone)}`;
  const emailHref = `mailto:${activeEmail}`;
  const mapHref = getMapOpenUrl(activeAddress, settings.map_embed_url || '');
  const mapEmbedUrl = getMapEmbedUrl(activeAddress, settings.map_embed_url || '');

  const socialLinks = useMemo(
    () => [
      {
        label: 'Instagram',
        href: settings.instagram_link,
        icon: Instagram,
      },
      {
        label: 'Facebook',
        href: settings.facebook_link,
        icon: Facebook,
      },
    ].filter((item) => isValidUrl(item.href || '')),
    [settings.facebook_link, settings.instagram_link],
  );

  const dynamicContactCards = contactCards.map((card) => {
    if (card.label === 'Phone') {
      return { ...card, value: activePhone, subValue: settings.reception_contact_number || undefined, href: phoneHref };
    }
    if (card.label === 'Email') {
      return { ...card, value: activeEmail, href: emailHref };
    }
    if (card.label === 'Address') {
      return { ...card, value: activeAddress, subValue: settings.business_name || undefined };
    }
    if (card.label === 'Reception') {
      return { ...card, value: activeHours, subValue: '7 Days a Week' };
    }
    return card;
  });

  const dynamicOpeningHours = openingHours.map((item) => ({ ...item, time: activeHours }));

  const handleChange = (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement>) => {
    setForm((prev) => ({ ...prev, [e.target.name]: e.target.value }));
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setIsSubmitting(true);

    try {
      await submitContactMessage(form);
      setSubmitted(true);
      setForm({ name: '', email: '', phone: '', subject: '', message: '' });
    } catch (error) {
      window.alert(error instanceof Error ? error.message : 'Could not send your message. Please try again.');
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <main>
      <PageHero
        title="Contact Us"
        subtitle="We would love to hear from you. Reach out for bookings, enquiries or any assistance."
        image="https://images.pexels.com/photos/237371/pexels-photo-237371.jpeg?auto=compress&cs=tinysrgb&w=1600"
        breadcrumb="Get in Touch"
      />

      {/* Contact Cards */}
      <section className="section-padding bg-background">
        <div className="container-custom">
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
            {dynamicContactCards.map((card, i) => (
              <AnimatedSection
                key={card.label}
                delay={i * 0.1}
                className="bg-white border border-border p-8 text-center hover:border-gold hover:shadow-luxury transition-all duration-400 group"
              >
                <div className="w-14 h-14 border border-gold/30 group-hover:bg-gold group-hover:border-gold flex items-center justify-center mx-auto mb-5 transition-all duration-300">
                  <card.icon size={22} className="text-gold group-hover:text-white transition-colors duration-300" />
                </div>
                <p className="text-[9px] tracking-[0.25em] uppercase text-gray-400 font-medium mb-2">{card.label}</p>
                {card.href ? (
                  <a
                    href={card.href}
                    className="font-serif text-lg font-light text-dark hover:text-gold transition-colors duration-200 block mb-1"
                  >
                    {card.value}
                  </a>
                ) : (
                  <p className="font-serif text-lg font-light text-dark mb-1">{card.value}</p>
                )}
                {card.subValue && (
                  <p className="text-xs text-gray-400">{card.subValue}</p>
                )}
              </AnimatedSection>
            ))}
          </div>
        </div>
      </section>

      {/* Form & Info */}
      <section className="section-padding bg-white">
        <div className="container-custom">
          <div className="grid grid-cols-1 lg:grid-cols-3 gap-12">
            {/* Form */}
            <div className="lg:col-span-2">
              <AnimatedSection direction="left">
                <p className="text-[10px] tracking-[0.35em] uppercase text-gold font-medium mb-4">Send a Message</p>
                <h2 className="font-serif text-3xl lg:text-4xl font-light text-dark mb-5">Get in Touch</h2>
                <div className="w-10 h-[1px] bg-gold mb-8" />

                {submitted ? (
                  <div className="bg-deep-green/5 border border-deep-green/20 p-10 text-center">
                    <div className="w-12 h-12 bg-gold flex items-center justify-center mx-auto mb-4">
                      <Send size={20} className="text-white" />
                    </div>
                    <h3 className="font-serif text-2xl font-light text-dark mb-3">Message Sent</h3>
                    <p className="text-sm text-gray-500">
                      Thank you for getting in touch. Our team will respond within 24 hours.
                    </p>
                  </div>
                ) : (
                  <form onSubmit={handleSubmit} className="space-y-5">
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-5">
                      <div>
                        <label className="block text-[9px] tracking-[0.2em] uppercase text-gray-400 font-medium mb-2">
                          Full Name *
                        </label>
                        <input
                          type="text"
                          name="name"
                          value={form.name}
                          onChange={handleChange}
                          required
                          placeholder="Your full name"
                          className="w-full border border-border px-5 py-3.5 text-sm text-dark outline-none focus:border-gold transition-colors duration-200 bg-background placeholder-gray-400"
                        />
                      </div>
                      <div>
                        <label className="block text-[9px] tracking-[0.2em] uppercase text-gray-400 font-medium mb-2">
                          Email Address *
                        </label>
                        <input
                          type="email"
                          name="email"
                          value={form.email}
                          onChange={handleChange}
                          required
                          placeholder="your@email.com"
                          className="w-full border border-border px-5 py-3.5 text-sm text-dark outline-none focus:border-gold transition-colors duration-200 bg-background placeholder-gray-400"
                        />
                      </div>
                    </div>

                    <div className="grid grid-cols-1 md:grid-cols-2 gap-5">
                      <div>
                        <label className="block text-[9px] tracking-[0.2em] uppercase text-gray-400 font-medium mb-2">
                          Phone Number
                        </label>
                        <input
                          type="tel"
                          name="phone"
                          value={form.phone}
                          onChange={handleChange}
                          placeholder="+94 xxx xxx xxx"
                          className="w-full border border-border px-5 py-3.5 text-sm text-dark outline-none focus:border-gold transition-colors duration-200 bg-background placeholder-gray-400"
                        />
                      </div>
                      <div>
                        <label className="block text-[9px] tracking-[0.2em] uppercase text-gray-400 font-medium mb-2">
                          Subject
                        </label>
                        <select
                          name="subject"
                          value={form.subject}
                          onChange={handleChange}
                          className="w-full border border-border px-5 py-3.5 text-sm text-dark outline-none focus:border-gold transition-colors duration-200 bg-background"
                        >
                          <option value="">Select a subject</option>
                          <option value="booking">Room Booking</option>
                          <option value="enquiry">General Enquiry</option>
                          <option value="facilities">Facilities</option>
                          <option value="other">Other</option>
                        </select>
                      </div>
                    </div>

                    <div>
                      <label className="block text-[9px] tracking-[0.2em] uppercase text-gray-400 font-medium mb-2">
                        Message *
                      </label>
                      <textarea
                        name="message"
                        value={form.message}
                        onChange={handleChange}
                        required
                        rows={6}
                        placeholder="How can we help you?"
                        className="w-full border border-border px-5 py-3.5 text-sm text-dark outline-none focus:border-gold transition-colors duration-200 bg-background placeholder-gray-400 resize-none"
                      />
                    </div>

                    <button type="submit" className="btn-primary" disabled={isSubmitting}>
                      <Send size={14} />
                      {isSubmitting ? 'Sending...' : 'Send Message'}
                    </button>
                  </form>
                )}
              </AnimatedSection>
            </div>

            {/* Side Info */}
            <div>
              <AnimatedSection direction="right" className="space-y-8">
                {/* Hours */}
                <div className="bg-background border border-border p-8">
                  <h3 className="font-serif text-xl font-light text-dark mb-4">Opening Hours</h3>
                  <div className="w-8 h-[1px] bg-gold mb-5" />
                  <div className="space-y-3">
                    {dynamicOpeningHours.map((h) => (
                      <div key={h.day} className="flex items-center justify-between text-sm">
                        <span className="text-gray-500">{h.day}</span>
                        <span className="text-gold font-medium text-xs">{h.time}</span>
                      </div>
                    ))}
                  </div>
                  <div className="mt-5 pt-5 border-t border-border">
                    <p className="text-xs text-gray-400 leading-relaxed">
                      Our reception team is available 24 hours a day, 7 days a week to assist you.
                    </p>
                  </div>
                </div>

                {/* Social */}
                <div className="bg-deep-green p-8">
                  <h3 className="font-serif text-xl font-light text-white mb-4">Follow Us</h3>
                  <div className="w-8 h-[1px] bg-gold mb-5" />
                  <p className="text-gray-400 text-sm mb-6">
                    Stay connected and follow our latest updates on social media.
                  </p>
                  {socialLinks.length > 0 ? (
                    <div className="flex gap-3">
                      {socialLinks.map((social) => {
                        const Icon = social.icon;
                        return (
                          <a
                            key={social.label}
                            href={social.href}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="w-10 h-10 border border-white/20 flex items-center justify-center text-gray-400 hover:border-gold hover:text-gold transition-all duration-300"
                            aria-label={social.label}
                          >
                            <Icon size={16} />
                          </a>
                        );
                      })}
                    </div>
                  ) : (
                    <p className="text-xs text-gray-500">Social links are not available yet.</p>
                  )}
                </div>

                {/* Direct Book */}
                <div className="border border-gold p-8">
                  <p className="text-[9px] tracking-[0.25em] uppercase text-gold font-medium mb-3">Quick Booking</p>
                  <h3 className="font-serif text-xl font-light text-dark mb-3">Ready to Book?</h3>
                  <p className="text-sm text-gray-500 mb-5">
                    Call us directly or use our online booking form for the fastest response.
                  </p>
                  <a href={phoneHref} className="btn-dark w-full justify-center mb-3" aria-label={`Call ${activePhone}`}>
                    <Phone size={14} />
                    Call Now
                  </a>
                </div>
              </AnimatedSection>
            </div>
          </div>
        </div>
      </section>

      {/* Map */}
      <section className="relative h-[430px] lg:h-[480px] overflow-hidden border-t border-border bg-background">
        <iframe
          src={mapEmbedUrl}
          title={`${settings.business_name || fallbackContactSettings.business_name} location map`}
          className="absolute inset-0 h-full w-full border-0"
          loading="lazy"
          referrerPolicy="no-referrer-when-downgrade"
          allowFullScreen
        />

        <div className="absolute inset-0 flex items-center justify-center px-6 pointer-events-none">
          <div
            className="
              bg-white/[0.001] backdrop-blur-[1px] backdrop-saturate-100
              border border-white/10 shadow-none
              ring-1 ring-white/5
              shadow-none
              px-10 py-10
              text-center
              max-w-md
              w-full
              pointer-events-auto
            "
            >
            <MapPin size={32} className="text-gold mx-auto mb-4" />

            <p className="font-serif text-2xl font-light text-white mb-2 drop-shadow-lg">
              Find Us Here
            </p>

            <p className="text-white/90 text-sm mb-6 drop-shadow">
              {activeAddress}
            </p>

            <a
              href={mapHref}
              target="_blank"
              rel="noopener noreferrer"
              className="btn-outline inline-flex"
            >
              Open in Google Maps
            </a>
          </div>
        </div>
      </section>
    </main>
  );
}
