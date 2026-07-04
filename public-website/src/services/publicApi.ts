import type { Room } from '../data/rooms';
import type { GalleryImage, GalleryCategory } from '../data/gallery';
import type { Attraction } from '../data/attractions';

const API_BASE_URL = (import.meta.env.VITE_API_BASE_URL || 'http://localhost/HotelWebsite/api').replace(/\/$/, '');

async function fetchJson<T>(endpoint: string): Promise<T> {
  const response = await fetch(`${API_BASE_URL}${endpoint}`, {
    headers: { Accept: 'application/json' },
  });

  if (!response.ok) {
    throw new Error(`Request failed: ${response.status}`);
  }

  return response.json() as Promise<T>;
}

function roomCategory(value: unknown): Room['category'] {
  const text = String(value || '').toLowerCase();
  if (text.includes('family')) return 'family';
  if (text.includes('suite') || text.includes('cottage')) return 'suite';
  if (text.includes('deluxe') || text.includes('first')) return 'deluxe';
  return 'standard';
}

interface ApiRoom {
  id?: number | string;
  slug?: string;
  name?: string;
  room_name?: string;
  type?: string;
  room_type?: string;
  description?: string;
  longDescription?: string;
  price?: number | string;
  base_price?: number | string;
  guests?: number | string;
  max_guests?: number | string;
  beds?: string;
  bed_type?: string;
  size?: number | string;
  main_image?: string;
  image?: string | { image_url?: string; image_path?: string } | null;
  images?: string[];
  amenities?: string[];
  currency?: string;
}

interface RoomsResponse {
  success?: boolean;
  data?: ApiRoom[];
  rooms?: ApiRoom[];
}

interface ApiGalleryImage {
  id?: number | string;
  title?: string;
  image_path?: string;
  folder_slug?: string;
  folder_name?: string;
}

interface GalleryResponse {
  success?: boolean;
  data?: {
    images?: ApiGalleryImage[];
  };
}

interface ApiExperienceItem {
  id?: number | string;
  title?: string;
  category?: string;
  location?: string;
  distance?: string;
  description?: string;
  image_path?: string;
}

interface ExperienceResponse {
  success?: boolean;
  data?: ApiExperienceItem[];
}

function firstImage(room: ApiRoom): string {
  if (Array.isArray(room.images) && room.images[0]) return room.images[0];
  if (room.main_image) return room.main_image;
  if (typeof room.image === 'string') return room.image;
  if (room.image && typeof room.image === 'object') return room.image.image_url || room.image.image_path || '';
  return 'https://images.unsplash.com/photo-1611892440506-42a832e657fb?w=1200&q=80';
}

function normalizeRoom(room: ApiRoom, index: number): Room {
  const image = firstImage(room);
  const name = room.name || room.room_name || 'Room';
  const price = Number(room.price ?? room.base_price ?? 0);
  const guests = Number(room.guests ?? room.max_guests ?? 2);
  const sizeValue = Number(room.size);

  return {
    id: String(room.id ?? index + 1),
    slug: room.slug || String(name).toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, ''),
    name,
    category: roomCategory(room.type || room.room_type || name),
    description: room.description || 'Comfortable accommodation with essential guest facilities.',
    longDescription: room.longDescription || room.description || 'Comfortable accommodation with essential guest facilities.',
    price: Number.isFinite(price) ? price : 0,
    currency: room.currency || 'LKR',
    guests: Number.isFinite(guests) ? guests : 2,
    beds: room.beds || room.bed_type || 'Comfortable Bed',
    bathrooms: 1,
    size: Number.isFinite(sizeValue) ? sizeValue : 0,
    image,
    images: Array.isArray(room.images) && room.images.length > 0 ? room.images : [image],
    amenities: Array.isArray(room.amenities) ? room.amenities : [],
    featured: index < 3,
  };
}

function normalizeGalleryImage(image: ApiGalleryImage, index: number): GalleryImage {
  const category = String(image.folder_slug || image.folder_name || 'facilities').toLowerCase() as GalleryCategory;

  return {
    id: String(image.id ?? index + 1),
    src: image.image_path || '',
    alt: image.title || image.folder_name || 'Tulip Guest Inn',
    category,
    width: index % 5 === 0 ? 'wide' : 'normal',
  };
}

function normalizeAttraction(item: ApiExperienceItem, index: number): Attraction {
  return {
    id: String(item.id ?? index + 1),
    name: item.title || 'Nearby Attraction',
    description: item.description || '',
    longDescription: item.description || '',
    distance: item.distance || item.location || 'Nearby',
    duration: item.location || 'Explore',
    image: item.image_path || 'https://images.pexels.com/photos/1032650/pexels-photo-1032650.jpeg?auto=compress&cs=tinysrgb&w=1200',
    category: item.category || 'Experience',
  };
}

export async function getPublicRooms(): Promise<Room[]> {
  const payload = await fetchJson<RoomsResponse>('/rooms/public-list.php');
  const rows = payload.data || payload.rooms || [];
  return rows.map(normalizeRoom);
}

export async function getPublicGalleryImages(): Promise<GalleryImage[]> {
  const payload = await fetchJson<GalleryResponse>('/gallery/public-list.php');
  const rows = payload.data?.images || [];
  return rows.map(normalizeGalleryImage).filter((image) => image.src !== '');
}

export async function getPublicAttractions(): Promise<Attraction[]> {
  const payload = await fetchJson<ExperienceResponse>('/experience/public-list.php');
  const rows = payload.data || [];
  return rows.map(normalizeAttraction).filter((item) => item.image !== '');
}
