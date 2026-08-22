import { useEffect } from 'react';
import { useLocation } from 'react-router-dom';

const SITE_URL = 'https://www.tulipguestinn.com';
const DEFAULT_IMAGE = `${SITE_URL}/assets/tulip-logo.png`;
const MAP_URL = 'https://maps.app.goo.gl/y76uNqinTRjg6Hic6';

const routeNames: Record<string, string> = {
  '/rooms': 'Rooms',
  '/facilities': 'Facilities',
  '/gallery': 'Gallery',
  '/attractions': 'Nearby Attractions',
  '/accommodation-point-pedro': 'Point Pedro Accommodation',
  '/about': 'About',
  '/contact': 'Contact',
  '/booking': 'Book a Room',
  '/multi-room-booking': 'Multiple Room Booking',
  '/privacy-policy': 'Privacy Policy',
  '/terms-and-conditions': 'Terms and Conditions',
};

type SeoConfig = {
  title: string;
  description: string;
  index?: boolean;
};

const pages: Record<string, SeoConfig> = {
  '/': {
    title: 'Tulip Guest Inn | Rooms in Point Pedro, Sri Lanka',
    description:
      'Book clean, comfortable rooms at Tulip Guest Inn in Point Pedro, Northern Province, Sri Lanka. Enjoy free WiFi, family rooms, parking and a peaceful garden.',
  },
  '/rooms': {
    title: 'Rooms in Point Pedro | Tulip Guest Inn',
    description:
      'Explore comfortable rooms for individuals, couples and families at Tulip Guest Inn in Point Pedro, Northern Province, Sri Lanka.',
  },
  '/facilities': {
    title: 'Guest House Facilities in Point Pedro | Tulip Guest Inn',
    description:
      'See the facilities at Tulip Guest Inn in Point Pedro, including free WiFi, family rooms, free parking, a shared kitchen and a peaceful garden.',
  },
  '/gallery': {
    title: 'Photo Gallery | Tulip Guest Inn Point Pedro',
    description:
      'View rooms, facilities, gardens and property photos from Tulip Guest Inn, a comfortable place to stay in Point Pedro, Sri Lanka.',
  },
  '/attractions': {
    title: 'Things to Do Near Point Pedro | Tulip Guest Inn',
    description:
      'Discover beaches, temples, landmarks and attractions near Tulip Guest Inn in Point Pedro and across Northern Sri Lanka.',
  },
  '/accommodation-point-pedro': {
    title: 'Accommodation in Point Pedro | Tulip Guest Inn',
    description:
      'Stay at Tulip Guest Inn for clean, comfortable rooms in Point Pedro, Northern Province, Sri Lanka. Check in from 1 PM and check out by 12 PM.',
  },
  '/about': {
    title: 'About Tulip Guest Inn | Point Pedro, Sri Lanka',
    description:
      'Learn about Tulip Guest Inn, a family-run guest house offering clean, comfortable accommodation and local hospitality in Point Pedro.',
  },
  '/contact': {
    title: 'Contact Tulip Guest Inn | Point Pedro',
    description:
      'Contact Tulip Guest Inn in Point Pedro for room enquiries, directions and direct reservations. Call 0212 261 186.',
  },
  '/booking': {
    title: 'Book a Room in Point Pedro | Tulip Guest Inn',
    description:
      'Book your stay directly at Tulip Guest Inn for comfortable room accommodation in Point Pedro, Northern Province, Sri Lanka.',
  },
  '/multi-room-booking': {
    title: 'Book Multiple Rooms | Tulip Guest Inn',
    description:
      'Choose multiple available rooms and allocate guests for your stay at Tulip Guest Inn in Point Pedro.',
    index: false,
  },
  '/booking-bill': {
    title: 'Booking Payment Status | Tulip Guest Inn',
    description: 'View the status of your Tulip Guest Inn booking payment.',
    index: false,
  },
  '/privacy-policy': {
    title: 'Privacy Policy | Tulip Guest Inn',
    description: 'Read how Tulip Guest Inn collects, uses and protects personal information provided through this website.',
  },
  '/terms-and-conditions': {
    title: 'Terms and Conditions | Tulip Guest Inn',
    description: 'Read the booking and website terms and conditions for Tulip Guest Inn in Point Pedro, Sri Lanka.',
  },
};

function setMeta(selector: string, attributes: Record<string, string>) {
  let element = document.head.querySelector<HTMLMetaElement>(selector);
  if (!element) {
    element = document.createElement('meta');
    document.head.appendChild(element);
  }
  Object.entries(attributes).forEach(([name, value]) => element?.setAttribute(name, value));
}

function setCanonical(url: string) {
  let canonical = document.head.querySelector<HTMLLinkElement>('link[rel="canonical"]');
  if (!canonical) {
    canonical = document.createElement('link');
    canonical.rel = 'canonical';
    document.head.appendChild(canonical);
  }
  canonical.href = url;
}

function setJsonLd(id: string, data: Record<string, unknown> | Record<string, unknown>[]) {
  let script = document.head.querySelector<HTMLScriptElement>(`script[data-seo-jsonld="${id}"]`);
  if (!script) {
    script = document.createElement('script');
    script.type = 'application/ld+json';
    script.dataset.seoJsonld = id;
    document.head.appendChild(script);
  }
  script.textContent = JSON.stringify(data);
}

function removeJsonLd(id: string) {
  document.head.querySelector(`script[data-seo-jsonld="${id}"]`)?.remove();
}

export default function SeoManager() {
  const { pathname } = useLocation();

  useEffect(() => {
    const normalizedPath = pathname !== '/' ? pathname.replace(/\/+$/, '') : '/';
    const isRoomDetail = /^\/rooms\/[^/]+$/.test(normalizedPath);
    const config =
      pages[normalizedPath] ??
      (isRoomDetail
        ? {
            title: 'Room Details | Tulip Guest Inn Point Pedro',
            description:
              'View room details, amenities and availability at Tulip Guest Inn in Point Pedro, Northern Province, Sri Lanka.',
          }
        : {
            title: 'Page Not Found | Tulip Guest Inn',
            description: 'The requested page could not be found.',
            index: false,
          });

    const canonicalUrl = `${SITE_URL}${normalizedPath === '/' ? '/' : normalizedPath}`;
    const robots = config.index === false ? 'noindex, nofollow' : 'index, follow';

    document.title = config.title;
    document.documentElement.lang = 'en';
    setCanonical(canonicalUrl);
    setMeta('meta[name="description"]', { name: 'description', content: config.description });
    setMeta('meta[name="robots"]', { name: 'robots', content: robots });
    setMeta('meta[property="og:type"]', { property: 'og:type', content: 'website' });
    setMeta('meta[property="og:site_name"]', { property: 'og:site_name', content: 'Tulip Guest Inn' });
    setMeta('meta[property="og:locale"]', { property: 'og:locale', content: 'en_LK' });
    setMeta('meta[property="og:title"]', { property: 'og:title', content: config.title });
    setMeta('meta[property="og:description"]', { property: 'og:description', content: config.description });
    setMeta('meta[property="og:url"]', { property: 'og:url', content: canonicalUrl });
    setMeta('meta[property="og:image"]', { property: 'og:image', content: DEFAULT_IMAGE });
    setMeta('meta[property="og:image:alt"]', { property: 'og:image:alt', content: 'Tulip Guest Inn logo' });
    setMeta('meta[name="twitter:card"]', { name: 'twitter:card', content: 'summary_large_image' });
    setMeta('meta[name="twitter:title"]', { name: 'twitter:title', content: config.title });
    setMeta('meta[name="twitter:description"]', { name: 'twitter:description', content: config.description });
    setMeta('meta[name="twitter:image"]', { name: 'twitter:image', content: DEFAULT_IMAGE });

    setJsonLd('lodging-business', {
      '@context': 'https://schema.org',
      '@type': 'LodgingBusiness',
      '@id': `${SITE_URL}/#lodging-business`,
      name: 'Tulip Guest Inn',
      url: `${SITE_URL}/`,
      image: DEFAULT_IMAGE,
      logo: DEFAULT_IMAGE,
      description:
        'A family-run guest house offering clean, comfortable rooms in Point Pedro, Northern Province, Sri Lanka.',
      telephone: '+94 21 226 1186',
      address: {
        '@type': 'PostalAddress',
        streetAddress: '189 V.M. Road',
        addressLocality: 'Point Pedro',
        addressRegion: 'Northern Province',
        postalCode: '40000',
        addressCountry: 'LK',
      },
      hasMap: MAP_URL,
      checkinTime: '13:00',
      checkoutTime: '12:00',
      amenityFeature: [
        { '@type': 'LocationFeatureSpecification', name: 'Free WiFi', value: true },
        { '@type': 'LocationFeatureSpecification', name: 'Free on-site parking', value: true },
        { '@type': 'LocationFeatureSpecification', name: 'Family rooms', value: true },
        { '@type': 'LocationFeatureSpecification', name: 'Air conditioning', value: true },
        { '@type': 'LocationFeatureSpecification', name: 'Shared kitchen', value: true },
        { '@type': 'LocationFeatureSpecification', name: 'Garden', value: true },
        { '@type': 'LocationFeatureSpecification', name: 'Non-smoking rooms', value: true },
      ],
      sameAs: [
        'https://www.booking.com/hotel/lk/tulip-guest-inn.html',
        'https://www.tripadvisor.com/Hotel_Review-g3646677-d9886016-Reviews-Tulip_Guest_Inn-Point_Pedro_Northern_Province.html',
      ],
    });

    const routeName = routeNames[normalizedPath];
    if (routeName) {
      setJsonLd('breadcrumbs', {
        '@context': 'https://schema.org',
        '@type': 'BreadcrumbList',
        itemListElement: [
          { '@type': 'ListItem', position: 1, name: 'Home', item: `${SITE_URL}/` },
          { '@type': 'ListItem', position: 2, name: routeName, item: canonicalUrl },
        ],
      });
    } else {
      removeJsonLd('breadcrumbs');
    }

    if (normalizedPath === '/accommodation-point-pedro') {
      setJsonLd('faq', {
        '@context': 'https://schema.org',
        '@type': 'FAQPage',
        mainEntity: [
          {
            '@type': 'Question',
            name: 'Where is Tulip Guest Inn located?',
            acceptedAnswer: {
              '@type': 'Answer',
              text: 'Tulip Guest Inn is at 189 V.M. Road, Point Pedro, Northern Province, Sri Lanka.',
            },
          },
          {
            '@type': 'Question',
            name: 'What are the check-in and check-out times?',
            acceptedAnswer: {
              '@type': 'Answer',
              text: 'Check-in is available from 1:00 PM, and check-out is by 12:00 PM.',
            },
          },
          {
            '@type': 'Question',
            name: 'Can I book a room directly?',
            acceptedAnswer: {
              '@type': 'Answer',
              text: 'Yes. Guests can check room options and book directly through the Tulip Guest Inn website.',
            },
          },
        ],
      });
    } else {
      removeJsonLd('faq');
    }
  }, [pathname]);

  return null;
}
