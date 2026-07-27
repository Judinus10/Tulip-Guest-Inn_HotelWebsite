import { useEffect } from 'react';
import { useLocation } from 'react-router-dom';

const SITE_URL = 'https://www.tulipguestinn.com';
const DEFAULT_IMAGE = `${SITE_URL}/assets/tulip-logo.png`;

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
  '/booking-bill': {
    title: 'Booking Payment Status | Tulip Guest Inn',
    description: 'View the status of your Tulip Guest Inn booking payment.',
    index: false,
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
  }, [pathname]);

  return null;
}
