import type { Room } from '../data/rooms';
import type { GalleryImage, GalleryCategory } from '../data/gallery';
import type { Attraction } from '../data/attractions';
import type { Offer } from '../data/offers';

export type BackendRoom = Record<string, unknown>;
export type BackendGalleryImage = Record<string, unknown>;
export type BackendExperienceItem = Record<string, unknown>;
export type BackendOffer = Record<string, unknown>;

const fallbackRoomImage = 'https://images.unsplash.com/photo-1611892440506-42a832e657fb?w=1200&q=80';
const fallbackAttractionImage = 'https://images.pexels.com/photos/1032650/pexels-photo-1032650.jpeg?auto=compress&cs=tinysrgb&w=1200';

export function asString(value: unknown, fallback = ''): string {
  return typeof value === 'string' && value.trim() !== '' ? value : fallback;
}

export function asNumber(value: unknown, fallback = 0): number {
  const numberValue = Number(value);
  return Number.isFinite(numberValue) ? numberValue : fallback;
}

export function asStringArray(value: unknown): string[] {
  if (Array.isArray(value)) return value.filter((item): item is string => typeof item === 'string' && item.trim() !== '');
  if (typeof value === 'string' && value.trim() !== '') {
    try {
      const parsed = JSON.parse(value);
      if (Array.isArray(parsed)) return parsed.filter((item): item is string => typeof item === 'string' && item.trim() !== '');
    } catch {
      return value.split(',').map((item) => item.trim()).filter(Boolean);
    }
  }
  return [];
}

function roomCategory(room: BackendRoom | unknown): Room['category'] {
  const source = typeof room === 'object' && room
    ? `${asString((room as BackendRoom).category)} ${asString((room as BackendRoom).type)} ${asString((room as BackendRoom).room_type)} ${asString((room as BackendRoom).name)} ${asString((room as BackendRoom).room_name)} ${asString((room as BackendRoom).slug)}`.toLowerCase()
    : String(room || '').toLowerCase();

  if (source.includes('family')) return 'family';
  if (source.includes('suite') || source.includes('cottage') || source.includes('private')) return 'suite';
  if (source.includes('deluxe') || source.includes('first')) return 'deluxe';
  return 'standard';
}

export function normalizeRoom(room: BackendRoom, index = 0): Room {
  const name = asString(room.name, asString(room.room_name, 'Room'));
  const description = asString(room.description, 'Comfortable accommodation with essential guest facilities.');
  const imageList = asStringArray(room.images);
  const mainImage = asString(room.main_image, asString(room.image, imageList[0] || fallbackRoomImage));
  const images = imageList.length > 0 ? imageList : [mainImage];
  const guests = asNumber(room.guests, asNumber(room.max_guests, asNumber(room.capacity, 2)));
  const size = asNumber(room.size, guests > 3 ? 45 : 24);
  const amenityCatalog = Array.isArray(room.amenities_catalog)
    ? room.amenities_catalog
        .filter((item): item is Record<string, unknown> => Boolean(item) && typeof item === 'object')
        .map((item) => ({
          id: asNumber(item.id),
          amenity_name: asString(item.amenity_name, asString(item.name)),
          selected: Boolean(item.selected),
        }))
        .filter((item) => item.amenity_name !== '')
    : [];

  return {
    id: String(room.id ?? index + 1),
    slug: asString(room.slug, String(room.id ?? name.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, ''))),
    name,
    category: roomCategory(room),
    description,
    longDescription: asString(room.longDescription, asString(room.long_description, description)),
    price: asNumber(room.price, asNumber(room.base_price, asNumber(room.price_per_night, 0))),
    currency: asString(room.currency, 'LKR'),
    guests,
    beds: asString(room.beds, asString(room.bed_type, 'Comfortable Bed')),
    bathrooms: asNumber(room.bathrooms, 1),
    size,
    image: mainImage,
    images,
    amenities: asStringArray(room.amenities),
    amenityCatalog,
    showUnavailableAmenities: Boolean(room.show_unavailable_amenities),
    featured: Boolean(room.featured ?? index < 3),
  };
}

function galleryCategory(value: unknown): GalleryCategory {
  const source = asString(value, 'facilities').toLowerCase();

  if (source.includes('room') || source.includes('bed')) return 'rooms';
  if (source.includes('pool') || source.includes('swim')) return 'pool';
  if (source.includes('garden') || source.includes('yard')) return 'garden';
  if (source.includes('exterior') || source.includes('outside') || source.includes('building')) return 'exterior';
  return 'facilities';
}

export function normalizeGalleryImage(image: BackendGalleryImage, index: number): GalleryImage {
  const category = galleryCategory(image.folder_slug ?? image.folder_name);

  return {
    id: String(image.id ?? index + 1),
    src: asString(image.image_path),
    alt: asString(image.title, asString(image.folder_name, 'Tulip Guest Inn')),
    category,
    width: index % 5 === 0 ? 'wide' : 'normal',
  };
}

export function normalizeAttraction(item: BackendExperienceItem, index: number): Attraction {
  return {
    id: String(item.id ?? index + 1),
    name: asString(item.title, 'Nearby Attraction'),
    description: asString(item.description),
    longDescription: asString(item.description),
    distance: asString(item.distance, 'Nearby'),
    duration: asString(item.duration, asString(item.time, asString(item.location, 'Explore'))),
    image: asString(item.image_path, fallbackAttractionImage),
    category: asString(item.category, 'Experience'),
  };
}


export function normalizeOffer(offer: BackendOffer, index = 0): Offer {
  const name = asString(offer.name, asString(offer.title, 'Special Offer'));

  return {
    id: String(offer.id ?? index + 1),
    name,
    description: asString(offer.description),
    discount: asString(offer.discount, asString(offer.discount_label, 'Offer')),
    validity: asString(offer.validity, asString(offer.validity_label, asString(offer.subtitle))),
    image: asString(offer.image, asString(offer.image_path)),
    details: asStringArray(offer.details),
    bookingScope: (['single', 'multi', 'both'].includes(asString(offer.booking_scope)) ? asString(offer.booking_scope) : 'both') as Offer['bookingScope'],
    minimumNights: asNumber(offer.minimum_nights, 1),
    minimumRooms: asNumber(offer.minimum_rooms, 1),
    minimumGuests: asNumber(offer.minimum_guests, 1),
  };
}
