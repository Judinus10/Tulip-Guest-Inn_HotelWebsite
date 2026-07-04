import type { Room } from '../data/rooms';
import type { GalleryImage, GalleryCategory } from '../data/gallery';
import type { Attraction } from '../data/attractions';

const API_BASE_URL = (import.meta.env.VITE_API_BASE_URL || 'http://localhost/HotelWebsite/api').replace(/\/$/, '');

export type ApiResponse<T> = {
  success?: boolean;
  message?: string;
  data?: T;
  rooms?: T;
  room?: T;
};

export type ContactSettings = {
  business_name: string;
  address: string;
  phone: string;
  reception_contact_number: string;
  whatsapp_reservation_number: string;
  email: string;
  business_hours: string;
  facebook_link: string;
  instagram_link: string;
  map_embed_url: string;
};

export type ContactFormPayload = {
  name: string;
  email: string;
  phone?: string;
  subject?: string;
  message: string;
};

type BackendRoom = Record<string, unknown>;
type BackendGalleryImage = Record<string, unknown>;
type BackendExperienceItem = Record<string, unknown>;

const fallbackRoomImage = 'https://images.unsplash.com/photo-1611892440506-42a832e657fb?w=1200&q=80';
const fallbackAttractionImage = 'https://images.pexels.com/photos/1032650/pexels-photo-1032650.jpeg?auto=compress&cs=tinysrgb&w=1200';

async function requestJson<T>(path: string, options: RequestInit = {}): Promise<T> {
  const response = await fetch(`${API_BASE_URL}${path}`, {
    ...options,
    headers: {
      Accept: 'application/json',
      ...(options.body ? { 'Content-Type': 'application/json' } : {}),
      ...options.headers,
    },
  });

  const payload = (await response.json()) as ApiResponse<T>;

  if (!response.ok || payload.success === false) {
    throw new Error(payload.message || 'Unable to load data from server.');
  }

  return (payload.data ?? payload.rooms ?? payload.room ?? payload) as T;
}

function asString(value: unknown, fallback = ''): string {
  return typeof value === 'string' && value.trim() !== '' ? value : fallback;
}

function asNumber(value: unknown, fallback = 0): number {
  const numberValue = Number(value);
  return Number.isFinite(numberValue) ? numberValue : fallback;
}

function asStringArray(value: unknown): string[] {
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

function normalizeGalleryImage(image: BackendGalleryImage, index: number): GalleryImage {
  const category = galleryCategory(image.folder_slug ?? image.folder_name);

  return {
    id: String(image.id ?? index + 1),
    src: asString(image.image_path),
    alt: asString(image.title, asString(image.folder_name, 'Tulip Guest Inn')),
    category,
    width: index % 5 === 0 ? 'wide' : 'normal',
  };
}

function normalizeAttraction(item: BackendExperienceItem, index: number): Attraction {
  return {
    id: String(item.id ?? index + 1),
    name: asString(item.title, 'Nearby Attraction'),
    description: asString(item.description),
    longDescription: asString(item.description),
    distance: asString(item.distance, asString(item.location, 'Nearby')),
    duration: asString(item.location, 'Explore'),
    image: asString(item.image_path, fallbackAttractionImage),
    category: asString(item.category, 'Experience'),
  };
}

export async function fetchPublicRooms(params: Record<string, string> = {}): Promise<Room[]> {
  const search = new URLSearchParams();
  Object.entries(params).forEach(([key, value]) => {
    if (value) search.set(key, value);
  });

  const suffix = search.toString() ? `?${search.toString()}` : '';
  const rooms = await requestJson<BackendRoom[]>(`/rooms/public-list.php${suffix}`);
  return Array.isArray(rooms) ? rooms.map(normalizeRoom) : [];
}

export async function fetchPublicRoom(idOrSlug: string): Promise<Room> {
  const room = await requestJson<BackendRoom>(`/rooms/detail.php?slug=${encodeURIComponent(idOrSlug)}`);
  return normalizeRoom(room);
}

export async function getPublicRooms(): Promise<Room[]> {
  return fetchPublicRooms();
}

export async function getPublicGalleryImages(): Promise<GalleryImage[]> {
  const payload = await requestJson<{ images?: BackendGalleryImage[] } | BackendGalleryImage[]>('/gallery/public-list.php');
  const rows = Array.isArray(payload) ? payload : payload.images || [];
  return rows.map(normalizeGalleryImage).filter((image) => image.src !== '');
}

export async function getPublicAttractions(): Promise<Attraction[]> {
  const rows = await requestJson<BackendExperienceItem[]>('/experience/public-list.php');
  return Array.isArray(rows) ? rows.map(normalizeAttraction).filter((item) => item.image !== '') : [];
}


export async function getPublicContactSettings(): Promise<ContactSettings> {
  return requestJson<ContactSettings>('/settings/get-contact.php');
}

export async function submitContactMessage(payload: ContactFormPayload): Promise<{ inquiry_id?: string }> {
  return requestJson<{ inquiry_id?: string }>('/contact/submit_contact.php', {
    method: 'POST',
    body: JSON.stringify(payload),
  });
}
